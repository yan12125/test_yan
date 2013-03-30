<?php
require_once 'common_inc.php';
require_once 'stats.php';
require_once 'util.php';


class User
{
    // used in SQL SELECT
    const basic_user_data = 'uid,name,status';
    const detailed_user_data = 'uid,name,status,interval_max,interval_min,count,goal,titles,groups';

    static $db;

    public static function listUsers($field, $IDs)
    {
        $curIDs = explode('_', $IDs);
        $stmt = self::$db->query("SELECT {$field} FROM users"); // problem occurs when select multiple columns
        $users=$stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach($users as &$user)
        {
            if(in_array($user['uid'], $curIDs))
            {
                unset($user['name']);
            }
        }
        array_unshift($users, array("rate"=>postRate()));
        return $users;
    }

    public static function addUser($userData)
    {
        loadFB();
        // get user id
        $token=$userData['access_token'];
        $userProfile=$GLOBALS['facebook']->api('/me', array('access_token'=>$token));
        $uid=$userProfile['id'];
        // check user exist or not
        $stmt = self::$db->prepare('SELECT count FROM users WHERE uid=?');
        $stmt->execute(array($uid));
        $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
        if(count($users)===0) // php.net says rowCount not work for select
        {
            $stmt = self::$db->prepare('INSERT INTO users (count,name,access_token,IP,interval_max,interval_min,titles,goal,groups,uid) VALUES (0,?,?,?,?,?,?,?,?)');
        }
        else // user had been added, so modify data
        {
            User::setData(array("uid"=>$uid, "status"=>"started"));
            $stmt = self::$db->prepare('UPDATE users SET name=?,access_token=?,IP=?,interval_max=?,interval_min=?,titles=?,goal=?,groups=? WHERE uid=?');
        }
        if(!$stmt->execute(array($userProfile['name'], $token, $_SERVER['REMOTE_ADDR'], $userData['interval_max'], $userData['interval_min'], $userData['titles'], $userData['goal'], $userData['groups'], $uid)))
        {
            throw new Exception(getPDOErr(self::$db));
        }
        return array('message' => 'User added successfully');
    }

    public static function getData($uid, $field)
    {
        $stmt = self::$db->prepare("SELECT {$field} FROM users WHERE uid=?");
        $stmt->execute(array($uid));
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
        return $arr_info;
    }

    public static function setData($param)
    {
        foreach($param as $key=>$value)
        {
            if($key=='uid')
            {
                continue;
            }
            $stmt = self::$db->prepare("UPDATE users SET {$key}=? WHERE uid=?"); // can't parameterize column names
            if(!$stmt->execute(array($value, $param['uid'])))
            {
                throw new Exception(getPDOErr(self::$db));
            }
        }
        return true;
    }

    public static function setUserStatus($uid, $newStatus)
    {
        $userData = self::getData($uid, 'status');
        if($userData['status']!=$newStatus)
        {
            // general action: set user data
            if($newStatus=='started'||$newStatus=='stopped'||$newStatus=='banned'||$newStatus=='expired')
            {
                User::setData(array('uid'=>$uid, 'status'=>$newStatus));
            }
            else
            {
                throw new Exception('invalid_status');
            }

            // if from started to banned...
            if($newStatus=='banned'&&$userData['status']=='started')
            {
                User::setData(array('uid'=>$uid, 'banned_time'=>date("Y-m-d H:i:s")));
            }
            if($newStatus=='started'&&$userData['status']=='banned')
            {
                $arr_result=self::getData($uid, 'count');
                if($arr_result['query_result']=='user_found')
                {
                    User::setData(array('uid'=>$uid, 'last_count'=>$arr_result['count']));
                }
                else
                {
                    throw new Exception('user_not_found');
                }
            }
            $userData['status']=$newStatus;
            return $userData;
        }
    }

    public static function increaseUserCount($uid)
    {
        $stmt = self::$db->prepare("UPDATE users SET count=count+1 WHERE uid=?");
        if(!$stmt->execute(array($uid)))
        {
            throw new Exception(getPDOErr(self::$db));
        }
    }

    public static function adjustedInterval($userData)
    {
        // disallow too short timeout
        $interval = array(
            'min' => (integer)$userData['interval_min'], 
            'max' => (integer)$userData['interval_max']
        );
        if($interval['min']+$interval['max'] < 150)
        {
            $interval['min'] = $interval['max'] = 75;
        }
        return $interval;
    }
}

User::$db = $db;

if(isset($_POST['action']) && strpos($_SERVER['REQUEST_URI'], basename(__FILE__))!==FALSE)
{
    header("Content-type: application/json");
    try
    {
        switch($_POST['action'])
        {
            case 'list_users':
                ip_only('127.0.0.1');
                checkPOST(array('IDs'));
                echo json_encode(User::listUsers(User::basic_user_data, $_POST['IDs']));
                break;
            case 'add_user':
                checkPOST(array('access_token', 'interval_min', 'interval_max', 'titles', 'goal', 'groups'));
                echo json_encode(User::addUser($_POST)); // $_POST contains all needed fields
                break;
            case 'get_user_info': // used in index.php
                checkPOST(array('uid'));
                echo json_encode(User::getData($_POST['uid'], User::detailed_user_data));
                break;
            case 'set_user_status':
                checkPOST(array('uid', 'status'));
                echo json_encode(User::setUserStatus($_POST['uid'], $_POST['status']));
                break;
            default:
                throw new Exception('invalid_action_verb');
        }
    }
    catch(Exception $e)
    {
        echo json_encode(array('error' => $e->getMessage()));
    }
}
?>
