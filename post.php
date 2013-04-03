<?php
require_once 'common_inc.php';
require_once 'util.php';
require_once 'texts.php';
require_once 'users.php';
require_once 'stats.php';
require_once 'groups.php';

class Post
{
    protected $config;
    protected $userData;
    protected $group;
    protected $response;

    public function __construct($uid, $config)
    {
        $this->userData = array('uid' => $uid); // temporarily storing for init() to use later
        $this->response = array();
        $this->config = $config;
        if(!isset($this->config['not_post']))
        {
            $this->config['not_post'] = false;
        }
    }

    protected function init()
    {
        $this->userData = Users::getData($this->userData['uid'], '*');
        $this->getGroup();
        $this->getTextItem();
        if(isset($this->config['debug']))
        {
            $this->config['not_post'] = true;
        }
    }

    protected function getTextItem()
    {
        $text = Texts::getRandomTextFromTitles($this->userData['titles']);
        if(isset($text['error']))
        {
            if($text['error'] == 'title_locked')
            {
                $this->config['not_post'] = true;
                $this->response['msg'] = '(title locked)';
                $this->response['processed'] = 1;
                $this->response['next_wait_time'] = $this->userData['interval_max'];
            }
            else
            {
                throw new Exception($text['error']);
            }
        }
        $this->response['msg'] = $text['msg'];
        return;
    }

    protected function getGroup()
    {
        $this->group = Groups::getFromGroups($this->userData['groups'], $this->userData['access_token']);
        $this->response['group'] = $this->group['name'];
        $this->response['post_id'] = $this->group['post_id'];
    }

    public function doPost()
    {
        global $facebook;
        $this->init();
        if(!$this->config['not_post'])
        {
            loadFB();
            try
            {
                $request_path = '/'.$this->group['post_id'].'/comments';
                $ret_obj=$facebook->api($request_path, 'POST', array(
                    "message"=> $this->response['msg'],
                    "access_token"=>$this->userData['access_token']
                ));
            }
            catch(FacebookApiException $e)
            {
                $this->handleFacebookError($e);
            }

            Users::increaseUserCount($this->userData['uid']);
            stats('success', mb_strlen($this->response['msg'], 'UTF-8'));
        }
    }

    protected function handleFacebookError(FacebookApiException $e)
    {
        $err = $e->getMessage();
        if(strpos($err, 'banned') !== false)
        {
            $this->response['new_status'] = 'banned';
            $this->response['next_wait_time'] = -1;
            $this->response['processed'] = 1;
        }
        else if(strpos($err, 'expired') !== false || strpos($err, 'validating access token') !== false)
        {
            $this->response['new_status'] = 'expired';
            $this->response['next_wait_time'] = -1;
            $this->response['processed'] = 1;
        }
        else if(strpos($err, 'Operation timed out') !== false)
        {
            stats('timed_out', mb_strlen($this->response['err'], 'UTF-8'));
            $this->response['processed'] = 1;
            $this->response['next_wait_time'] = $this->userData['interval_max'];
        }
        else
        {
            $this->response['next_wait_time'] = $this->userData['interval_max'];
            throw $e;
        }

        if(isset($this->response['new_status']))
        {
            Users::setUserStatus($this->userData['uid'], $this->response['new_status']);
            throw new Exception($userData['name'].': '.$this->response['new_status']);
        }
        else
        {
            throw new Exception("Error processed.");
        }
    }

    protected function makeResponse()
    {
        if(($special_wait_time=load_params('special_wait_time'))>0)
        {
            $this->response["next_wait_time"]=$special_wait_time;
        }
        else
        {
            $newInterval = Users::adjustedInterval($this->userData, $this->group['gid']);
            $this->response['next_wait_time'] = round(randND($newInterval['max'], $newInterval['min'], 6), 1); // 正負三個標準差
            // round to decrease amount of transmission
        }

        $this->response['user_data'] = array();
        foreach(explode(',', Users::basic_user_data) as $field)
        {
            if($field == 'uid') // uid never changes, so not sending
            {
                continue;
            }
            $this->response['user_data'][$field] = $this->userData[$field];
        }
        unset($this->response['post_id']); // if arrive here, post_id not needed anymore
        if(isset($this->config['truncated_msg']))
        {
            $this->response['msg'] = truncate($this->response['msg'], 20);
        }
        if($this->group['gid'] == Groups::primary_group)
        {
            $this->response['group'] = '---'; // to reduce the amount of transmission
        }
    }
    
    public function getResponse()
    {
        $this->makeResponse();
        return $this->response;
    }

    public function report_fields($optionalField, &$output)
    {
        $output['userData'] = $this->userData;
        foreach($optionalField as $item)
        {
            if(isset($this->response[$item]))
            {
                $output[$item] = $this->response[$item];
            }
        }
    }
}

$aPost = null;
try
{
    ip_only('127.0.0.1');
    header("Content-type: application/json; charset=UTF-8");

    checkPOST(array('uid'));

    $aPost = new Post($_POST['uid'], $_POST);
    $aPost->doPost();

    echo json_unicode($aPost->getResponse());
}
catch(Exception $e)
{
	$response_error=array(
        "error" => $e->getMessage(), 
        "code" => $e->getCode(), 
        "class_name" => get_class($e), 
        "time" => date('H:i:s'), 
    );
    if($aPost instanceof Post)
    {
        $aPost->report_fields(array('title', 'msg', 'm', 'processed', 'new_status', 'next_wait_time', 'group', 'post_id'), $response_error);
    }
    if(!isset($response_error['next_wait_time']))
    {
        $response_error['next_wait_time'] = 300;
    }
	echo json_encode($response_error);
}

?>
