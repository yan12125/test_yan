<?php
$useFB=true;
require_once 'common_inc.php';

function user_action($action, $param)
{
	$ret_val='';
	switch($action)
	{
		case 'get_user_field':
			$query="SELECT ".$param['field']." FROM users";
			$result=mysql_query($query);
			$users=array();
			while(($arr_users=mysql_fetch_assoc($result))!=false)
			{
				$users[]=$arr_users;
			}
			$ret_val=$users;
			break;
		case 'add_user':
			$token=$param['access_token'];
			$userProfile=$GLOBALS['facebook']->api('/me', array('access_token'=>$token));
			$uid=$userProfile['id'];
			$result=mysql_query("SELECT count FROM users WHERE uid='".$uid."'");
			if(mysql_num_rows($result)===0)
			{
				$query="INSERT INTO users (uid,name,access_token,IP,count,interval_max,interval_min,titles,goal) VALUES ('".
					$uid."','".$userProfile['name']."','".$token."','".
					$_SERVER['REMOTE_ADDR']."',0,".$param['interval_max'].','.
					$param['interval_min'].",'".$param['titles']."',".$param['goal'].")";
				if(mysql_query($query)!=TRUE)
				{
					$ret_val=mysql_error();
				}
				else
				{
					$ret_val='User added successfully';
				}
			}
			else // user had been added, so modify data
			{
				$query="UPDATE users SET name='".$userProfile['name']."',access_token='".$token."',IP='".$_SERVER['REMOTE_ADDR']."',interval_max=".$param['interval_max'].",interval_min=".$param['interval_min'].",titles='".$param['titles']."',goal=".$param['goal']." WHERE uid='".$uid."'";
				if(mysql_query($query)===false)
				{
					echo mysql_error();
				}
				echo "\n";
				user_action("set_data", array("uid"=>$uid, "status"=>"started"));
			}
			break;
		case 'get_new_users':
			$query='SELECT * FROM users';
			$result=mysql_query($query);
			$new_users=array();
			$arr_IDs=json_decode(str_replace("\\\"", "\"", $param['curIDs']), true);
			while(($user=mysql_fetch_assoc($result))!=false)
			{
				if(!in_array($user['uid'], $arr_IDs))
				{
					$new_users[]=$user;
				}
			}
			$ret_val=json_encode($new_users);
			break;
		case 'set_data':
			$result=array();
			foreach($param as $key=>$value)
			{
				if($key=='uid')
				{
					continue;
				}
				$query="UPDATE users SET ".$key."='".$value."' WHERE uid='".$param['uid']."'";
				$result[$key]=mysql_query($query);
				if($result[$key]===false)
				{
					$ret_val+=mysql_error()."\n";
				}
			}
			if(!in_array(false, $result))
			{
				$ret_val=true;
			}
			break;
		case 'get_data':
			$field='*';
			if(isset($param['field']))
			{
				$field=$param['field'];
			}
			$query="SELECT ".$field." FROM users WHERE uid='".$param['uid']."'";
			$result=mysql_query($query);
			if(mysql_num_rows($result)==0)
			{
				$arr_info=array('query_result'=>'user_not_found');
			}
			else
			{
				$arr_info=mysql_fetch_assoc($result);
				$arr_info['query_result']='user_found';
			}
			$ret_val=$arr_info;
			break;
		case 'increase_user_count':
			if(isset($param['uid']))
			{
				$query="UPDATE users SET count=count+1 WHERE uid='".$param['uid']."'";
				if(mysql_query($query)==false)
				{
					$ret_val['error']=mysql_error();
				}
			}
			$ret_val=user_action("get_data", array("uid"=>$param['uid']));
			break;
		default:
			$ret_val="Invalid verb!";
			break;
	}
	return $ret_val;
}

if(isset($_GET['action']))
{
	switch($_GET['action'])
	{
		case 'list_users':
			echo json_encode(user_action('get_user_field', array('field'=>'*')));
			break;
		case 'get_user_status':
			if(isset($_POST['uid']))
			{
				$arr_result=user_action('get_data', array('uid'=>$_POST['uid']));
				if($arr_result['query_result']!="user_not_found")
				{
					echo $arr_result['status'];
				}
				else
				{
					echo 'user_not_found';
				}
			}
			else
			{
				echo json_encode(user_action('get_user_field', array('field'=>'status')));
			}
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
		case 'get_new_users':
			if(isset($_POST['IDs']))
			{
				echo user_action('get_new_users', array('curIDs'=>$_POST['IDs']));
			}
			break;
		case 'get_user_info':
			if(isset($_POST['uid']))
			{
				echo json_encode(user_action('get_data', array('uid'=>$_POST['uid'])));
			}
			break;
		case 'set_user_status':
			if(isset($_POST['status']))
			{
				$result=false;
				$status2=$_POST['status'];
				$result2=user_action('get_data', array('uid'=>$_POST['uid'], 'field'=>'status'));
				if($result2['status']!=$status2)
				{
					if($status2=='started'||$status2=='stopped'||$status2=='banned'||$status2=='expired')
					{
						$result=user_action('set_data', array('uid'=>$_POST['uid'], 'status'=>$status2));
					}
					if($status2=='banned'&&$result2['status']=='started')
					{
						$result=$result&&user_action('set_data', array('uid'=>$_POST['uid'], 'banned_time'=>date("Y-m-d H:i:s")));
						if($result===true)
						{
							$result2['status']=$status2;
							echo json_encode($result2);
						}
						else
						{
							echo '["error": "'.mysql_error().'"]';
						}
					}
					if($status2=='started'&&$result2['status']=='banned')
					{
						$arr_result=user_action('get_data', array('uid'=>$_POST['uid'], 'field'=>'count'));
						if($arr_result['query_result']=='user_found')
						{
							$result=user_action('set_data', array('uid'=>$_POST['uid'], 'last_count'=>$arr_result['count']));
						}
						else
						{
							$result=false;
						}
					}
					if($result===true)
					{
						$result2['status']=$status2;
						echo json_encode($result2);
					}
					else
					{
						echo '["error": "'.mysql_error().'"]';
					}
				}
			}
		default:
			$ret_val='Invalid action verb.';
	}
}
?>
