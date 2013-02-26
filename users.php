<?php
require_once 'common_inc.php';
require_once 'stats.php';

$basic_user_data = 'uid,name,status';

function user_action($action, $param)
{
    global $db;
	$ret_val='';
	switch($action)
	{
		case 'get_user_field':
			$stmt = $db->query("SELECT {$param['field']} FROM users"); // problem occurs when select multiple columns
			$users=$stmt->fetchAll(PDO::FETCH_ASSOC);
            if(isset($param['curIDs']))
            {
                foreach($users as &$user)
                {
                    if(in_array($user['uid'], $param['curIDs']))
                    {
                        unset($user['name']);
                    }
                }
            }
			$ret_val=$users;
			break;
		case 'add_user':
            loadFB();
			$token=$param['access_token'];
			$userProfile=$GLOBALS['facebook']->api('/me', array('access_token'=>$token));
			$uid=$userProfile['id'];
            $stmt = $db->prepare('SELECT count FROM users WHERE uid=?');
            $stmt->execute(array($uid));
            $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
			if(count($users)===0) // php.net says rowCount not work for select
			{
                $stmt = $db->prepare('INSERT INTO users (uid,name,access_token,IP,count,interval_max,interval_min,titles,goal) VALUES (?,?,?,?,?,?,?,?,?)');
				if(!$stmt->execute(array($uid, $userProfile['name'], $token, $_SERVER['REMOTE_ADDR'], 0, $param['interval_max'], $param['interval_min'], $param['titles'], $param['goal'])))
				{
					$ret_val = getPDOErr($db);
				}
				else
				{
					$ret_val='User added successfully';
				}
			}
			else // user had been added, so modify data
			{
                $stmt = $db->prepare('UPDATE users SET name=?,access_token=?,IP=?,interval_max=?,interval_min=?,titles=?,goal=? WHERE uid=?');
				if(!$stmt->execute(array($userProfile['name'], $token, $_SERVER['REMOTE_ADDR'], $param['interval_max'], $param['interval_min'], $param['titles'], $param['goal'], $uid)))
				{
					echo getPDOErr($db);
				}
				echo "\n";
				user_action("set_data", array("uid"=>$uid, "status"=>"started"));
			}
			break;
		case 'set_data':
			$result=array();
			foreach($param as $key=>$value)
			{
				if($key=='uid')
				{
					continue;
				}
                $stmt = $db->prepare("UPDATE users SET {$key}=? WHERE uid=?"); // can't parameterize column names
				$result[$key] = $stmt->execute(array($value, $param['uid']));
				if($result[$key]===false)
				{
					$ret_val += getPDOErr($db)."\n";
				}
			}
			if(!in_array(false, $result))
			{
				$ret_val=true;
			}
			break;
		case 'get_data':
			$field=$GLOBALS['basic_user_data'];
			if(isset($param['field']))
			{
				$field=$param['field'];
			}
            $stmt = $db->prepare("SELECT {$field} FROM users WHERE uid=?");
            $stmt->execute(array($param['uid']));
            $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
			if(count($results)==0)
			{
				$arr_info=array('query_result'=>'user_not_found');
			}
			else
			{
				$arr_info=$results[0];
				$arr_info['query_result']='user_found';
			}
			$ret_val=$arr_info;
			break;
		case 'increase_user_count':
			if(isset($param['uid']))
			{
                $stmt = $db->prepare("UPDATE users SET count=count+1 WHERE uid=?");
				if(!$stmt->execute(array($param['uid'])))
				{
					$ret_val['error'] = getPDOErr($db);
				}
			}
			$ret_val = true;
			break;
        case 'set_user_status':
            $result=false;
            $status2=$param['status'];
            $result2=user_action('get_data', array('uid'=>$param['uid'], 'field'=>'status'));
            if($result2['status']!=$status2)
            {
                if($status2=='started'||$status2=='stopped'||$status2=='banned'||$status2=='expired')
                {
                    $result=user_action('set_data', array('uid'=>$param['uid'], 'status'=>$status2));
                }
                if($status2=='banned'&&$result2['status']=='started')
                {
                    $result=$result&&user_action('set_data', array('uid'=>$param['uid'], 'banned_time'=>date("Y-m-d H:i:s")));
                    if($result===true)
                    {
                        $result2['status']=$status2;
                        $ret_val = $result2;
                    }
                    else
                    {
                        $ret_val = array("error" => getPDOErr($db));
                    }
                }
                if($status2=='started'&&$result2['status']=='banned')
                {
                    $arr_result=user_action('get_data', array('uid'=>$param['uid'], 'field'=>'count'));
                    if($arr_result['query_result']=='user_found')
                    {
                        $result=user_action('set_data', array('uid'=>$param['uid'], 'last_count'=>$arr_result['count']));
                    }
                    else
                    {
                        $result=false;
                    }
                }
                if($result===true)
                {
                    $result2['status']=$status2;
                    $ret_val = $result2;
                }
                else
                {
                    $ret_val = array("error" => getPDOErr($db));
                }
            }
            break;
		default:
			$ret_val="Invalid verb!";
			break;
	}
	return $ret_val;
}

if(isset($_GET['action']))
{
    header("Content-type: application/json");
	switch($_GET['action'])
	{
		case 'list_users':
            ip_only('127.0.0.1');
            $param = array('field'=>$basic_user_data);
            if(isset($_POST['IDs']))
            {
                $param['curIDs'] = explode('_', $_POST['IDs']);
            }
            $arr_users = user_action('get_user_field', $param);
            array_unshift($arr_users, array("rate"=>postRate()));
			echo json_encode($arr_users);
			break;
		case 'add_user':
			if(isset($_POST['access_token'])&&isset($_POST['interval_min'])&&isset($_POST['interval_max'])
			 &&isset($_POST['titles'])&&isset($_POST['goal']))
			{
				echo user_action('add_user', array('access_token'=>$_POST['access_token'], 
				    'interval_min'=>$_POST['interval_min'], 'interval_max'=>$_POST['interval_max'], 
				    'titles'=>$_POST['titles'], 'goal'=>$_POST['goal']));
			}
			break;
		case 'get_user_info':
			if(isset($_POST['uid']))
			{
				echo json_encode(user_action('get_data', array('uid'=>$_POST['uid'], 'field'=>$basic_user_data.',interval_max,interval_min,count,goal,titles')));
			}
			break;
		case 'set_user_status':
			if(isset($_POST['uid']) && isset($_POST['status']))
			{
                echo json_encode(user_action('set_user_status', array('uid'=>$_POST['uid'], 'status'=>$_POST['status'])));
			}
            break;
		default:
			echo 'Invalid action verb.';
	}
}
?>
