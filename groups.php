<?php
require_once 'common_inc.php';
require_once 'users.php';
require_once 'util.php';

class Groups
{
    const primary_group = '198971170174405';
    public static function updateGroupFeed($gid, $access_token, $newAdded)
    {
        global $facebook, $db;
        loadFB();

        // retrieve group title
        $groupData = $facebook->api('/'.$gid, array(
            'access_token' => $access_token
        ));
        $groupTitle = $groupData['name'];

        // retrieve posts in the group
        $feeds = $facebook->api('/'.$gid.'/feed', array(
            'access_token' => $access_token
        ));
        if(count($feeds['data']) == 0) // some groups are wierd...
        {
            throw new Exception('empty_feed_result');
        }
        $IDs = array();
        foreach($feeds['data'] as $post)
        {
            $postIdArr = explode('_', $post['id']); // $post['id'] is in the form gid_postId
            $IDs[] = $postIdArr[1];
        }
        $IDstr = implode('_', $IDs);

        if($newAdded)
        {
            $stmt = $db->prepare('insert into groups (gid,title,feed_id,persistent,last_update) values (?,?,?,?,?)');
            $stmt->execute(array($gid, $groupTitle, $IDstr, 0, date('Y-m-d H:i:s')));
            // persistent groups should be added manually
        }
        else
        {
            $stmt = $db->prepare('update groups set feed_id=?,title=?,last_update=? where gid=?');
            $stmt->execute(array($IDstr, $groupTitle, date('Y-m-d H:i:s'), $gid));
        }
        return array(
            'feed_id' => $IDstr, 
            'title' => $groupTitle, 
        ); // keys here should be consistent with column names in the database
    }

    /*
     * $gid: group id in numeric form or prefixed with g_
     * */
    public static function getFromGroup($gid, $access_token)
    {
        global $db;
        if(preg_match('/g_\d+/', $gid))
        {
            $gid = substr($gid, 2);
        }
        if(!preg_match('/\d+/', $gid))
        {
            throw new Exception('invalid_group_id');
        }
        $stmt = $db->prepare('select * from groups where gid=?');
        $stmt->execute(array($gid));
        $groups = $stmt->fetchAll(PDO::FETCH_ASSOC);
        if(count($groups) == 0) // newly added group
        {
            $curGroup = self::updateGroupFeed($gid, $access_token, true);
        }
        else
        {
            $curGroup = $groups[0];
            $difference = abs(time() - strtotime($curGroup['last_update']));
            if($difference > 60*10 && $curGroup['persistent'] != 1) // 10 minutes
            {
                self::updateGroupFeed($gid, $access_token, false);
            }
        }
        $IDs = explode('_', $curGroup['feed_id']);
        return array(
            'post_id' => $gid.'_'.$IDs[array_rand($IDs)], // post id format for graph api
            'name' => $curGroup['title'], 
            'gid' => $gid
        );
    }

    public static function getFromGroups($gid, $access_token)
    {
        $gids = explode('_', $gid);
        return self::getFromGroup($gids[array_rand($gids)], $access_token);
    }

    public static function getUserGroups($access_token)
    {
        global $facebook;
        loadFB();
        $groups = $facebook->api('/me/groups', array(
            'access_token' => $access_token
        ));
        $ret_val = array();
        foreach($groups['data'] as $group)
        {
            $ret_val[] = array(
                'name' => $group['name'], 
                'gid' => $group['id']
            );
        }
        return $ret_val;
    }
}

if(isset($_POST['action']) && strpos($_SERVER['REQUEST_URI'], basename(__FILE__))!==FALSE)
{
    try
    {
        switch($_POST['action'])
        {
            case 'get_groups':
                checkPOST(array('access_token'));
                echo json_unicode(Groups::getUserGroups($_POST['access_token']));
                break;
            case 'get_group_info':
                checkPOST(array('access_token', 'gid'));
                echo json_unicode(Groups::getFromGroup($_POST['gid'], $_POST['access_token']));
                break;
            default;
                throw new Exception('invalid action');
                break;
        }
    }
    catch(Exception $e)
    {
        echo json_unicode(array('error' => $e->getMessage()));
    }
}
?>
