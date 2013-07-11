<?php
class Post
{
    protected static $posts; // class instances
    protected $uids;
    protected $config;
    protected $userData;
    protected $group;
    protected $response;

    public function __construct($uids, $config)
    {
        mb_internal_encoding('UTF-8');
        $this->uids = explode('_', $uids);
        $this->userData = array();
        $this->config = array();
        $this->response = array();
        $this->group = array();
        $default_config = array(
            'not_post' => false, 
            'truncated_msg' => true, 
            'debug' => false
        );
        foreach($default_config as $key => $value)
        {
            if(!isset($config[$key]))
            {
                $config[$key] = $value;
            }
        }
        for($i = 0;$i < count($this->uids);$i++)
        {
            $uid = $this->uids[$i];
            $this->config[$uid] = $config;
            $this->userData[$uid] = array();
            $this->response[$uid] = array();
            $this->group[$uid] = array();
        }
    }

    protected function init()
    {
        for($i = 0;$i < count($this->uids);$i++)
        {
            try
            {
                $uid = $this->uids[$i];
                $userData = $this->userData[$uid] = Users::getData($uid, '*');
                if($userData['count'] >= $userData['goal'])
                {
                    $this->fillErrorMsg($uid, false, 'stopped');
                    Users::setUserStatus($uid, 'stopped', $userData['access_token']);
                    throw new Exception($userData['name'].' : goal achieved');
                }
                $this->getGroup($uid);
                $this->getTextItem($uid);
                if($this->config[$uid]['debug'])
                {
                    $this->config[$uid]['not_post'] = true;
                }
            }
            catch(Exception $e)
            {
                $this->config[$uid]['not_post'] = true;
                $this->response[$uid]['error'] = $e->getMessage();
            }
        }
    }

    protected function getTextItem($uid)
    {
        $text = Texts::getRandomTextFromTitles($this->userData[$uid]['titles']);
        if(isset($text['error']))
        {
            if($text['error'] == 'title_locked')
            {
                $this->config[$uid]['not_post'] = true;
                $this->config[$uid]['truncated_msg'] = false; // this message shouldn't be truncated
                $this->response[$uid]['msg'] = '(title locked)';
                $this->response[$uid]['next_wait_time'] = $this->userData[$uid]['interval_max'];
            }
            else
            {
                throw new Exception(json_encode($text));
            }
            return;
        }
        foreach($text as $key => $value)
        {
            $this->response[$uid][$key] = $value;
        }
    }

    protected function getGroup($uid)
    {
        $this->group[$uid] = Groups::getFromGroups($this->userData[$uid]['groups'], $this->userData[$uid]['access_token']);
        $this->response[$uid]['group'] = $this->group[$uid]['name'];
        $this->response[$uid]['post_id'] = $this->group[$uid]['post_id'];
    }

    public function doPost()
    {
        $this->init();

        $req = new FbBatch();
        for($i = 0;$i < count($this->uids);$i++)
        {
            $uid = $this->uids[$i];
            if($this->config[$uid]['not_post'])
            {
                continue;
            }
            $req_path = '/'.$this->group[$uid]['post_id'].'/comments';
            $req->push(null, $req_path, 'POST', array(
                "message"=> $this->response[$uid]['msg'],
                "access_token"=>$this->userData[$uid]['access_token']
            ));
        }
        if($req->getCount() == 0)
        {
            return;
        }
        $responses = array();
        try
        {
            $responses = $req->run();
        }
        catch(FacebookApiException $e)
        {
            for($i = 0;$i < count($this->uids);$i++)
            {
                $this->handleFacebookError($this->uids[$i], $e->getMessage());
            }
            return;
        }
        $notInResponse = 0;
        for($i = 0;$i < count($this->uids);$i++)
        {
            $uid = $this->uids[$i];
            if($this->config[$uid]['not_post'])
            {
                $notInResponse++;
                continue;
            }
            $realIndex = $i - $notInResponse;
            $this->response[$uid]['ret_obj'] = $responses[$realIndex];
            if(isset($responses[$realIndex]['error']))
            {
                $this->handleFacebookError($uid, $responses[$realIndex]['error']['message']);
                continue;
            }
            if(isset($responses[$realIndex]['id']))
            {
                Users::increaseUserCount($this->userData[$uid]['uid']);
                Stats::success(mb_strlen($this->response[$uid]['msg']));
            }
            else
            {
                $this->response[$uid]['error'] = 'Can\'t post to facebook';
            }
        }
    }

    protected function handleFacebookError($uid, $err)
    {
        $this->response[$uid]['fbErr'] = $err;
        $this->response[$uid]['error'] = '';
        $this->config[$uid]['truncated_msg'] = false;
        if(strpos($err, 'banned') !== false)
        {
            $this->fillErrorMsg($uid, false, 'banned');
        }
        else if(strpos($err, 'expired') !== false || strpos($err, 'validating access token') !== false)
        {
            $this->fillErrorMsg($uid, false, 'expired');
        }
        else if(strpos($err, 'timed out') !== false || strpos($err, 'timeout') !== false || strpos($err, 'time-out') !== false)
        {
            Stats::timedOut(mb_strlen($this->response[$uid]['msg']));
            $this->fillErrorMsg($uid, true);
        }
        else if(strpos($err, 'An unexpected error has occurred.') !== false)
        {
            Stats::unexpected();
            $this->fillErrorMsg($uid, true);
        }
        else
        {
            $this->fillErrorMsg($uid, true);
            $this->response[$uid]['error'] = $err;
        }

        if(isset($this->response[$uid]['new_status']))
        {
            Users::setUserStatus($this->userData[$uid]['uid'], $this->response[$uid]['new_status'], $this->userData[$uid]['access_token']);
            $err = $this->userData[$uid]['name'].': '.$this->response[$uid]['new_status'];
            $this->response[$uid]['error'] = $err;
        }
        if(empty($this->response[$uid]['error']))
        {
            $this->response[$uid]['error'] = $this->response[$uid]['fbErr'];
            unset($this->response[$uid]['fbErr']);
        }
    }

    protected function fillErrorMsg($uid, $continue, $newStatus = '')
    {
        if($continue === true)
        {
            $this->response[$uid]['next_wait_time'] = $this->userData[$uid]['interval_max'];
        }
        else
        {
            $this->response[$uid]['next_wait_time'] = -1;
        }
        if($newStatus !== '')
        {
            $this->response[$uid]['new_status'] = $newStatus;
        }
    }

    protected function makeResponse($uid)
    {
        // for less typing...
        $response = &$this->response[$uid];
        $userData = &$this->userData[$uid];
        $group = &$this->group[$uid];

        $newInterval = Users::adjustedInterval($userData, $group['gid']);
        $response['next_wait_time'] = round(Util::randND($newInterval['max'], $newInterval['min'], 6), 1); // 正負三個標準差
        // round to decrease amount of transmission

        $response['user_data'] = array();
        foreach(explode(',', Users::basic_user_data) as $field)
        {
            if($field == 'uid') // uid never changes, so not sending
            {
                continue;
            }
            $response['user_data'][$field] = $userData[$field];
        }
        if(isset($response['error']))
        {
            $response['time'] = Util::timestr();
            $response['error'] = Util::tryParseJson($response['error']);
        }

        // remove unnecessary fields
        $unnecessaryFields = array('post_id', 'ret_obj', 'm', 'title');
        foreach($unnecessaryFields as $key)
        {
            unset($response[$key]);
        }

        if($this->config[$uid]['truncated_msg'])
        {
            $response['msg'] = mb_strimwidth($response['msg'], 0, 11, '...');
        }
        if($group['gid'] == Groups::primary_group)
        {
            $response['group'] = '---'; // to reduce the amount of transmission
        }
    }
    
    public function getResponse()
    {
        for($i = 0;$i < count($this->uids);$i++)
        {
            $this->makeResponse($this->uids[$i]);
        }
        if(count($this->response) == 1)
        {
            $keys = array_keys($this->response);
            $this->response = $this->response[$keys[0]];
        }
        return $this->response;
    }

    protected function reportFieldsPerUser($uid, &$output)
    {
        $optionalField = array('title', 'msg', 'm', 'new_status', 'group', 
                               'next_wait_time', 'post_id', 'ret_obj');
        $output['userData'] = $this->userData[$uid];
        foreach($optionalField as $item)
        {
            if(isset($this->response[$uid][$item]))
            {
                $output[$item] = $this->response[$uid][$item];
            }
            else
            {
                $output[$item] = "";
            }
        }
        if(!is_numeric($output['next_wait_time']))
        {
            $output['next_wait_time'] = 300;
        }
    }

    public static function postUids($uids, $config)
    {
        Util::ip_only('127.0.0.1');
        self::$posts = new Post($uids, $config);
        self::$posts->doPost();
        return self::$posts->getResponse();
    }

    public static function report_fields(&$output)
    {
        if(self::$posts instanceof Post)
        {
            for($i = 0;$i < count(self::$posts->uids);$i++)
            {
                $uid = self::$posts->uids[$i];
                $output[$uid] = array();
                self::$posts->reportFieldsPerUser($uid, $output[$uid]);
            }
        }
    }
}
?>
