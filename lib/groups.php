<?php
class Groups
{
    const primary_group = '198971170174405';

    public static function getPrimaryGroup()
    {
        return array('primary_group' => self::primary_group);
    }

    public static function updateGroupFeed($gid, $access_token, $newAdded)
    {
        // retrieve group title
        try
        {
            // fail if user has no access to the group
            $groupData = Fb::api('/'.$gid, array(
                'access_token' => $access_token
            ));
        }
        catch(FacebookApiException $e)
        {
            // user in group or not?
            $userGroups = Users::getDataFromFb($access_token, array('groups'));
            $userInGroup = false;
            foreach($userGroups as $group)
            {
                if($group['gid'] == $gid)
                {
                    $userInGroup = true;
                    break;
                }
            }
            if($userInGroup)
            {
                throw $e;
            }
            Users::stripGroup($access_token, $gid);
            throw new Exception('Group '.$gid.' removed.');
        }
        $groupTitle = $groupData['name'];

        // retrieve posts in the group
        $feeds = Fb::api('/'.$gid.'/feed', array(
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
            $stmt = Db::prepare('insert into groups (gid,title,feed_id,persistent,last_update) values (?,?,?,?,?)');
            $stmt->execute(array($gid, $groupTitle, $IDstr, 0, Util::timestr()));
            // persistent groups should be added manually
        }
        else
        {
            $stmt = Db::prepare('update groups set feed_id=?,title=?,last_update=? where gid=?');
            $stmt->execute(array($IDstr, $groupTitle, Util::timestr(), $gid));
        }
        Logger::write('Feeds of group '.$gid.' updated');
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
        if(preg_match('/g_\d+/', $gid))
        {
            $gid = substr($gid, 2);
        }
        if(!preg_match('/\d+/', $gid))
        {
            throw new Exception('invalid_group_id');
        }
        $stmt = Db::prepare('select * from groups where gid=?');
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
}
?>
