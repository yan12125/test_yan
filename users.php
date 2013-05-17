<?php
class Users
{
    // used in SQL SELECT
    const basic_user_data = 'uid,name,status';
    const detailed_user_data = 'uid,name,status,interval_max,interval_min,count,goal,titles,groups';

    protected static function listUsers($field, $IDs = '')
    {
        $curIDs = explode('_', $IDs);
        $stmt = Db::query("SELECT {$field} FROM users ORDER BY status"); // problem occurs when select multiple columns
        $users=$stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach($users as &$user)
        {
            if(isset($user['uid']) && in_array($user['uid'], $curIDs))
            {
                unset($user['name']);
            }
        }
        return $users;
    }

    public static function listUsersAndRate($IDs)
    {
        Util::ip_only('127.0.0.1');
        $users = self::listUsers(Users::basic_user_data, $IDs);
        array_unshift($users, array("rate" => Stats::postRate()));
        return $users;
    }

    public static function viewUsers($page, $nRows)
    {
        if($nRows > 45) // batch limit is 50
        {
            throw new Exception('Can\'t get more than 45 rows in a time');
        }

        $users = self::listUsers('name,uid,access_token,status');
        $N = count($users);
        $users_chunked = array_chunk($users, $nRows);
        $nPages = count($users_chunked);
        if($page > $nPages || $page <= 0)
        {
            throw new Exception('Invalid page number.');
        }

        // check all access_token's in one batch request
        $token_response = array();
        $queries = array();
        foreach($users_chunked[$page - 1] as $user)
        {
            $queries[] = array(
                'method' => 'GET', 
                'relative_url' => 'debug_token?input_token='.$user['access_token']
            );
        }
        $response = Fb::api('/', 'POST', array(
            'access_token' => Fb::getAppToken(), 
            'batch' => json_encode($queries)
        ));
        $token_response = array_merge($token_response, $response);
        foreach($token_response as &$response)
        {
            $response = json_decode($response['body'], true);
            if(isset($response['data']))
            {
                $response = $response['data'];
            }
        }
        $rows = array();
        // assume the returned order in batch request is the same as sent request
        $statusStr = array(
            'started' => '洗版中', 
            'stopped' => '已停止', 
            'banned' => '鎖兩天', 
            'expired' => '授權碼失效'
        );
        for($i = 0;$i < min($nRows, $N - ($page - 1) * $nRows);$i++)
        {
            $curUser = $users_chunked[$page - 1][$i];
            $curRow = array(
                'name' => $curUser['name'], 
                'status' => $statusStr[$curUser['status']]
            );
            // Parsing error messages from facebook
            if(isset($token_response[$i]['error']))
            {
                $curRow['valid'] = 'No';
                $err = $token_response[$i]['error']['message'];

                // remove redundant prefix
                $redundant = 'Error validating access token: ';
                if(strstr($err, $redundant) != false)
                {
                    $err = substr($err, strlen($redundant));
                }

                // error message from facebook may contain uid, mask it
                if(strstr($err, $curUser['uid']) != false)
                {
                    $curUid = (string)$curUser['uid'];
                    $maskedUid = substr($curUid, 0, strlen($curUid) - 4).'****';
                    $err = str_replace($curUser['uid'], $maskedUid, $err);
                }

                // real parsing
                if(preg_match('/Session has expired at unix time (\d+)/', $err, $matches) > 0)
                {
                    $curRow['msg'] = '授權碼已在'.date('Y/m/d H:i:s', $matches[1]).'過期';
                }
                else
                {
                    $curRow['msg'] = $err;
                }
            }
            else
            {
                $curRow['valid'] = 'Yes';
                $expiry = $token_response[$i]['expires_at'];
                $timestr = date('Y/m/d H:i:s', $expiry);
                $curRow['msg'] = '有效期限：'.$timestr;
            }
            $rows[] = $curRow;
        }
        return array('rows' => $rows, 'records' => $N, 'total' => ceil($N/$nRows));
    }

    public static function addUser($userData)
    {
        // get user id
        $token=$userData['access_token'];
        $userProfile=Fb::api('/me', array('access_token'=>$token));
        $uid=$userProfile['id'];
        // check user exist or not
        $stmt = Db::prepare('SELECT count FROM users WHERE uid=?');
        $stmt->execute(array($uid));
        $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
        if(count($users)===0) // php.net says rowCount not work for select
        {
            $stmt = Db::prepare('INSERT INTO users (count,name,access_token,IP,interval_max,interval_min,titles,goal,groups,uid) VALUES (0,?,?,?,?,?,?,?,?,?)');
        }
        else // user had been added, so modify data
        {
            self::setData($uid, array("status"=>"started"));
            $stmt = Db::prepare('UPDATE users SET name=?,access_token=?,IP=?,interval_max=?,interval_min=?,titles=?,goal=?,groups=? WHERE uid=?');
        }
        if(!$stmt->execute(array($userProfile['name'], $token, $_SERVER['REMOTE_ADDR'], $userData['interval_max'], $userData['interval_min'], $userData['titles'], $userData['goal'], $userData['groups'], $uid)))
        {
            throw new Exception(Db::getErr());
        }
        return array('message' => 'User added successfully');
    }

    public static function getData($uid, $field)
    {
        // determine only one field or not. 
        // If only one, return the field directly , or return an array
        $fieldCount = count(explode(',', $field));
        if($field == '*')
        {
            $fieldCount = PHP_INT_MAX;
        }

        $stmt = Db::prepare("SELECT {$field} FROM users WHERE uid=?");
        $stmt->execute(array($uid));
        if($fieldCount > 1)
        {
            $results = $stmt->fetch(PDO::FETCH_ASSOC);
        }
        else
        {
            $results = $stmt->fetch(PDO::FETCH_NUM);
        }
        if($results === false)
        {
            throw new Exception("user_not_found");
        }
        if($fieldCount > 1)
        {
            return $results;
        }
        else
        {
            return $results[0];
        }
    }

    protected static function setData($uid, $param)
    {
        foreach($param as $key=>$value)
        {
            if(!preg_match('/^[a-zA-Z_]+$/', $key))
            {
                continue;
            }
            $stmt = Db::prepare("UPDATE users SET {$key}=? WHERE uid=?"); // can't parameterize column names
            if(!$stmt->execute(array($value, $uid)))
            {
                throw new Exception(Db::getErr());
            }
        }
        return true;
    }

    public static function setUserStatus($uid, $newStatus, $token)
    {
        if($token != self::getData($uid, 'access_token'))
        {
            throw new Exception('Invalid access_token');
        }
        $oldStatus = self::getData($uid, 'status');
        if($oldStatus!=$newStatus)
        {
            // general action: set user data
            if($newStatus=='started'||$newStatus=='stopped'||$newStatus=='banned'||$newStatus=='expired')
            {
                self::setData($uid, array('status'=>$newStatus));
            }
            else
            {
                throw new Exception('invalid_status');
            }

            // if from started to banned...
            if($newStatus=='banned'&&$oldStatus=='started')
            {
                self::setData($uid, array('banned_time'=>date("Y-m-d H:i:s")));
            }
            if($newStatus=='started'&&$oldStatus=='banned')
            {
                $count = self::getData($uid, 'count');
                self::setData($uid, array('last_count'=>$count));
            }
            return array('status' => $newStatus);
        }
    }

    public static function increaseUserCount($uid)
    {
        $stmt = Db::prepare("UPDATE users SET count=count+1 WHERE uid=?");
        if(!$stmt->execute(array($uid)))
        {
            throw new Exception(Db::getErr());
        }
    }

    public static function adjustedInterval($userData, $gid)
    {
        // disallow too short timeout
        $interval = array(
            'min' => (float)$userData['interval_min'], 
            'max' => (float)$userData['interval_max']
        );
        // only limit interval in 挑戰留言2147483647
        if( ($gid == Groups::primary_group) && ($interval['min']+$interval['max'] < 150) )
        {
            $interval['min'] = $interval['max'] = 75;
        }
        return $interval;
    }

    public static function logout()
    {
        if(session_id() === '')
        {
            session_start();
        }
        if(isset($_SESSION['access_token']))
        {
            $token = $_SESSION['access_token'];
            session_destroy();
        }
        else
        {
            session_destroy();
            throw new Exception('Not logged in.');
        }
        return array('url' => 'https://www.facebook.com/logout.php?'.
            http_build_query(array(
                'next' => Config::getParam('rootUrl'), 
                'access_token' => $token
            ))
        );
    }
}
?>
