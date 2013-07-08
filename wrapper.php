<?php
require 'common_inc.php';

try
{
    if(!isset($_POST['action']))
    {
        exit(0);
    }
    header('Content-type: application/json;charset=UTF-8');
    switch($_POST['action'])
    {
        /*
         * stats.php
         */
        case 'report_stats':
            Util::checkPOST(array('page', 'rows'));
            echo json_encode(Stats::report($_POST['page'], $_POST['rows']));
            break;
        case 'running_state':
            echo json_encode(Stats::runningState());
            break;
        /*
         * users.php
         */
        case 'list_users':
            Util::checkPOST(array('IDs'));
            echo json_encode(Users::listUsersSimple($_POST['IDs']));
            break;
        case 'add_user':
            Util::checkPOST(array('access_token', 'interval_min', 'interval_max', 'titles', 'goal', 'groups'));
            echo json_encode(Users::addUser($_POST)); // $_POST contains all needed fields
            break;
        case 'get_user_info': // used in index.php
            Util::checkPOST(array('uid'));
            echo json_encode(Users::getData($_POST['uid'], Users::detailed_user_data));
            break;
        case 'set_user_status':
            Util::checkPOST(array('uid', 'status', 'access_token'));
            echo json_encode(Users::setUserStatus($_POST['uid'], $_POST['status'], $_POST['access_token']));
            break;
        case 'view_users':
            Util::checkPOST(array('page', 'rows'));
            echo json_encode(Users::viewUsers($_POST['page'], $_POST['rows']));
            break;
        case 'logout':
            Util::checkPOST(array('access_token'));
            echo json_encode(Users::logout($_POST['access_token']));
            break;
        case 'get_basic_data':
            Util::checkPOST(array('access_token'));
            echo json_encode(Users::getBasicData($_POST['access_token']));
            break;
        /*
         * groups.php
         */
        case 'get_group_info':
            Util::checkPOST(array('access_token', 'gid'));
            echo Util::json_unicode(Groups::getFromGroup($_POST['gid'], $_POST['access_token']));
            break;
        case 'get_primary_group':
            echo json_encode(Groups::getPrimaryGroup());
            break;
        /*
         * texts.php
         */
        case "list_titles":
            echo Util::json_unicode(Texts::listTitles());
            break;
        case 'update_text':
            Util::checkPOST(array('title', 'texts'));
            echo json_encode(Texts::updateText($_POST['title'], $_POST['texts']));
            break;
        case 'add_title':
            Util::checkPOST(array('title'));
            echo json_encode(Texts::addTitle($_POST['title']));
            break;
        case 'check_title':
            Util::checkPOST(array('title'));
            echo json_encode(Texts::checkTitle($_POST['title']));
            break;
        case 'get_texts':
            Util::checkPOST(array('title'));
            echo Util::json_unicode(Texts::getTexts($_POST['title']));
            break;
        case 'get_plugins':
            echo json_encode(Texts::getPlugins());
            break;
        /*
         * db.php
         */
        case 'query_sql':
            Util::checkPOST(array('query'));
            echo json_encode(Db::queryToArray($_POST['query']));
            break;
        /*
         * post.php
         */
        case 'post_uids':
            Util::checkPOST(array('uids'));
            echo Util::json_unicode(Post::postUids($_POST['uids'], $_POST));
            break;
        /*
         * auth.php
         */
        case 'get_app_info':
            echo json_encode(Auth::getAppInfo());
            break;
        case 'exchange_token':
            Util::checkPOST(array('access_token'));
            echo json_encode(Auth::exchangeToken($_POST['access_token']));
            break;
    }
}
catch(Exception $e)
{
    $errClass = get_class($e);
    $trace = $e->getTrace();
    $classNames = array();
    foreach($trace as &$item)
    {
        // not set in error handler
        if(isset($item['file']))
        {
            $item['file'] = basename($item['file']);
        }
        // determine which class cause the error
        if(isset($item['class']))
        {
            if($item['class'] == 'Util' && $item['function'] == 'errorHandler')
            {
                continue;
            }
            $classNames[] = $item['class'];
        }
    }
    $response_error=array(
        "code" => $e->getCode(), 
        "err_class_name" => $errClass, 
        "class_name" => $classNames, 
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

    for($i = 0;$i < count($classNames);$i++)
    {
        if(method_exists($classNames[$i], 'report_fields'))
        {
            // some tricks required to use call_user_func with reference values
            // http://stackoverflow.com/questions/295016
            $fReportFields = array($classNames[$i], 'report_fields');
            call_user_func_array($fReportFields, array(&$response_error));
        }
    }

    try
    {
        echo json_encode($response_error);
    }
    catch(Exception $e)
    {
        $err = var_export($response_error, true);
        echo json_encode(array(
            'error' => base64_encode($err), 
            'next_wait_time' => 300
        ));
    }
}
?>
