<?php
ini_set('display_errors', 'on');
require_once 'common_inc.php';
require_once 'users.php';
require_once 'util.php';

class Groups
{
    public static function updateGroupFeed($gid, $access_token)
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
            $IDs[] = $post['id'];
        }
        $IDstr = implode('_', $IDs);

        $stmt = $db->prepare('update groups set feed_id=?,title=?,last_update=? where gid=?');
        $stmt->execute(array($IDstr, $groupTitle, time(), $gid));
        return array(
            'feed_id' => $IDstr, 
            'title' => $groupTitle, 
        ); // keys here should be consistent with column names in the database
    }

    public static function getFromGroup($gid, $access_token)
    {
        global $db;
        $stmt = $db->prepare('select * from groups where gid=?');
        $stmt->execute(array($gid));
        $groups = $stmt->fetchAll(PDO::FETCH_ASSOC);
        if(count($groups) == 0) // newly added group
        {
            $curGroup = self::updateGroupFeed($gid, $access_token);
        }
        else
        {
            $curGroup = $groups[0];
            $difference = abs(time() - strtotime($curGroup['last_update']));
            if($difference > 60*10 && $curGroup['persistent'] != 1) // 10 minutes
            {
                self::updateGroupFeed($gid, $access_token);
            }
        }
        $IDs = explode('_', $curGroup['feed_id']);
        return array(
            'post_id' => $gid.'_'.$IDs[array_rand($IDs)], // post id format for graph api
            'name' => $curGroup['title']
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

if(isset($_POST['action']))
{
    switch($_POST['action'])
    {
        case 'get_groups':
            if(isset($_POST['access_token']))
            {
                echo unicode_conv(json_encode(Groups::getUserGroups($_POST['access_token'])));
            }
            else
            {
                echo json_encode(array('error' => 'accesss_token not found'));
            }
            break;
        default;
            echo json_encode(array('error' => 'invalid action'));
            break;
    }
}
?>
