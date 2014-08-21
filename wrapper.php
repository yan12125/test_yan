<?php
require 'common_inc.php';

try
{
    header('Content-type: application/json;charset=UTF-8');
    header('Connection: close');
    if(isset($_POST['debug']))
    {
        Util::$debug = true;
    }
    if(!isset($_POST['action']))
    {
        throw new Exception("action is required");
    }
    $output = null;

    switch($_POST['action'])
    {
        /*
         * stats.php
         */
        case 'report_stats':
            Util::checkPOST(array('page', 'rows'));
            $output = Stats::report($_POST['page'], $_POST['rows']);
            break;
        case 'running_state':
            $output = Stats::runningState();
            break;
        /*
         * users.php
         */
        case 'list_users':
            Util::checkPOST(array('IDs'));
            $output = Users::listUsersSimple($_POST['IDs']);
            break;
        case 'add_user':
            Util::checkPOST(array('access_token', 'interval_min', 'interval_max', 'titles', 'goal', 'groups', 'contact'));
            $output = Users::addUser($_POST); // $_POST contains all needed fields
            break;
        case 'get_user_info': // used in index.php
            Util::checkPOST(array('uid'));
            $output = Users::getData($_POST['uid'], Users::detailed_user_data);
            break;
        case 'set_user_status':
            Util::checkPOST(array('uid', 'status', 'access_token'));
            $output = Users::setUserStatus($_POST['uid'], $_POST['status'], $_POST['access_token']);
            break;
        case 'view_running_users':
            Util::checkPOST(array('page', 'rows'));
            $output = Users::viewRunningUsers($_POST['page'], $_POST['rows']);
            break;
        case 'view_other_users':
            Util::checkPOST(array('page', 'rows'));
            $output = Users::viewOtherUsers($_POST['page'], $_POST['rows']);
            break;
        case 'logout':
            Util::checkPOST(array('access_token'));
            $output = Users::logout($_POST['access_token']);
            break;
        case 'get_basic_data':
            Util::checkPOST(array('access_token'));
            $output = Users::getBasicData($_POST['access_token']);
            break;
        case 'list_contacts':
            $output = Users::listContacts();
            break;
        /*
         * groups.php
         */
        case 'get_group_info':
            Util::checkPOST(array('access_token', 'gid'));
            $output = Groups::getFromGroup($_POST['gid'], $_POST['access_token']);
            break;
        case 'get_primary_group':
            $output = Groups::getPrimaryGroup();
            break;
        case 'search_name_in_group':
            Util::checkPOST(array('gid', 'name', 'access_token'));
            $output = Groups::searchNameInGroup($_POST['gid'], $_POST['name'], $_POST['access_token']);
            break;
        /*
         * texts.php
         */
        case "list_titles":
            $output = Texts::listTitles();
            break;
        case 'update_text':
            Util::checkPOST(array('title', 'texts', 'handler', 'access_token'));
            $output = Texts::updateText($_POST['title'], $_POST['texts'], $_POST['handler'], $_POST['access_token']);
            break;
        case 'add_title':
            Util::checkPOST(array('title', 'access_token'));
            $output = Texts::addTitle($_POST['title'], $_POST['access_token']);
            break;
        case 'check_title':
            Util::checkPOST(array('title'));
            $output = Texts::checkTitle($_POST['title']);
            break;
        case 'get_texts':
            Util::checkPOST(array('title'));
            $output = Texts::getTexts($_POST['title']);
            break;
        case 'texts_log':
            Util::checkPOST(array('page', 'rows'));
            $output = Texts::textsLog($_POST['page'], $_POST['rows']);
            break;
        case 'get_text_from_texts':
            Util::checkPOST(array('title', 'handler', 'texts'));
            $output = Texts::getTextFromTexts($_POST['title'], $_POST['handler'], $_POST['texts']);
            break;
        case 'search_text':
            Util::checkPOST(array('term'));
            $output = Texts::searchText($_POST['term']);
            break;
        case 'get_text_from_title':
            Util::checkPOST(array('title', 'm'));
            $output = Texts::getTextFromTitle($_POST['title'], $_POST['m']);
            break;
        /*
         * plugins.php
         */
        case 'get_plugins':
            $output = Plugins::getPlugins();
            break;
        /*
         * db.php
         */
        case 'query_sql':
            Util::checkPOST(array('query'));
            $output = Db::queryToArray($_POST['query']);
            break;
        case 'get_mysql_credentials':
            $output = Db::getMysqlCredentials();
            break;
        /*
         * post.php
         */
        case 'post_uids':
            Util::checkPOST(array('uids'));
            $output = Post::postUids($_POST['uids'], $_POST);
            break;
        /*
         * fb.php
         */
        case 'get_token':
            $output = Fb::getTokenFromSession();
            break;

        default:
            throw new Exception("Unknown action");
            break;
    }

    $output_str = Util::jsonEncode($output);
    echo $output_str;
}
catch(Exception $e)
{
    $response_error = array();
    Util::handleException($e, $response_error);
    $str = json_encode($response_error);
    if($str === false)
    {
        echo json_encode(array(
            'error' => json_last_error_msg(), 
            'response_error' => print_r($response_error, true), 
            'next_wait_time' => 300
        ));
    }
    else
    {
        echo $str;
    }
}
?>
