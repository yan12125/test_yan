<?php
require '../common_inc.php';

class Post
{
    protected $uids;
    protected $config;
    protected $userData;
    protected $group;
    protected $response;

    public function __construct($uids, $config)
    {
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
            $uid = $this->uids[$i];
            $this->userData[$uid] = Users::getData($uid, '*');
            $this->getGroup($uid);
            $this->getTextItem($uid);
            if($this->config[$uid]['debug'])
            {
                $this->config[$uid]['not_post'] = true;
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
                throw new Exception($text['error']);
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
            $req->push($req_path, 'POST', array(
                "message"=> $this->response[$uid]['msg'],
                "access_token"=>$this->userData[$uid]['access_token']
            ));
        }
        if($req->getCount() == 0)
        {
            return;
        }
        $responses = $req->run();
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
                $this->handleFacebookError($uid, $responses[$realIndex]['error']);
                continue;
            }
            if(isset($responses[$realIndex]['id']))
            {
                Users::increaseUserCount($this->userData[$uid]['uid']);
                Stats::success(mb_strlen($this->response[$uid]['msg'], 'UTF-8'));
            }
            else
            {
                $this->response[$uid]['error'] = 'Can\'t post to facebook';
            }
        }
    }

    protected function handleFacebookError($uid, $e)
    {
        $err = $e['message'];
        $this->response[$uid]['error'] = $err;
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
            Stats::timedOut(mb_strlen($this->response[$uid]['msg'], 'UTF-8'));
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
        $newInterval = Users::adjustedInterval($this->userData[$uid], $this->group[$uid]['gid']);
        $this->response[$uid]['next_wait_time'] = round(Util::randND($newInterval['max'], $newInterval['min'], 6), 1); // 正負三個標準差
        // round to decrease amount of transmission

        $this->response[$uid]['user_data'] = array();
        foreach(explode(',', Users::basic_user_data) as $field)
        {
            if($field == 'uid') // uid never changes, so not sending
            {
                continue;
            }
            $this->response[$uid]['user_data'][$field] = $this->userData[$uid][$field];
        }

        // remove unnecessary fields
        $unnecessaryFields = array('post_id', 'ret_obj', 'm', 'title');
        foreach($unnecessaryFields as $key)
        {
            unset($this->response[$uid][$key]);
        }

        if($this->config[$uid]['truncated_msg'])
        {
            Util::truncate($this->response[$uid]['msg'], 10);
        }
        if($this->group[$uid]['gid'] == Groups::primary_group)
        {
            $this->response[$uid]['group'] = '---'; // to reduce the amount of transmission
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

    public function report_fields(&$output)
    {
        for($i = 0;$i < count($this->uids);$i++)
        {
            $uid = $this->uids[$i];
            $output[$uid] = array();
            $this->reportFieldsPerUser($this->uids[$i], $output[$uid]);
        }
    }
}

$posts = array();
try
{
    Util::ip_only('127.0.0.1');
    header("Content-type: application/json; charset=UTF-8");

    Util::checkPOST(array('uids'));

    $posts = new Post($_POST['uids'], $_POST);
    $posts->doPost();

    echo Util::json_unicode($posts->getResponse());
}
catch(Exception $e)
{
    $errClass = get_class($e);
    $trace = $e->getTrace();
    foreach($trace as &$item)
    {
        // not set in error handler
        if(isset($item['file']))
        {
            $item['file'] = basename($item['file']);
        }
    }
	$response_error=array(
        "code" => $e->getCode(), 
        "class_name" => $errClass, 
        "time" => date('H:i:s'), 
        "trace" => $trace, 
    );

    $err = $e->getMessage();
    $err_json = json_decode($err, true);
    if(!is_null($err_json))
    {
        $response_error['error'] = $err_json;
    }
    else
    {
        $response_error['error'] = $err;
    }

    if($errClass == 'ErrorException')
    {
        $response_error['severity'] = Util::getSeverityStr($e->getSeverity());
    }

    if(method_exists($posts, 'report_fields'))
    {
        $posts->report_fields($response_error);
    }
    try
    {
        echo json_encode($response_error);
    }
    catch(Exception $e)
    {
        $err = var_export($response_error, true);
        echo json_encode(array(
            'error' => iconv('UTF-8', 'UTF-8//IGNORE', $err), 
            'next_wait_time' => 300
        ));
    }
}

?>
