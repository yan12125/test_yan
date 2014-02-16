<?php
require 'common_inc.php';

try
{
    if(!isset($_POST['action']))
    {
        exit(0);
    }
    header('Content-type: application/json;charset=UTF-8');
    header('Connection: close');
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
        case 'view_running_users':
            Util::checkPOST(array('page', 'rows'));
            echo json_encode(Users::viewRunningUsers($_POST['page'], $_POST['rows']));
            break;
        case 'view_other_users':
            Util::checkPOST(array('page', 'rows'));
            echo json_encode(Users::viewOtherUsers($_POST['page'], $_POST['rows']));
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
            Util::checkPOST(array('title', 'texts', 'handler', 'access_token'));
            echo json_encode(Texts::updateText($_POST['title'], $_POST['texts'], $_POST['handler'], $_POST['access_token']));
            break;
        case 'add_title':
            Util::checkPOST(array('title', 'access_token'));
            echo json_encode(Texts::addTitle($_POST['title'], $_POST['access_token']));
            break;
        case 'check_title':
            Util::checkPOST(array('title'));
            echo json_encode(Texts::checkTitle($_POST['title']));
            break;
        case 'get_texts':
            Util::checkPOST(array('title'));
            echo Util::json_unicode(Texts::getTexts($_POST['title']));
            break;
        case 'texts_log': 
            Util::checkPOST(array('page', 'rows'));
            echo json_encode(Texts::textsLog($_POST['page'], $_POST['rows']));
            break;
        case 'get_text_from_texts':
            Util::checkPOST(array('title', 'handler', 'texts'));
            echo json_encode(Texts::getTextFromTexts($_POST['title'], $_POST['handler'], $_POST['texts']));
            break;
        case 'search_text':
            Util::checkPOST(array('term'));
            echo json_encode(Texts::searchText($_POST['term']));
            break;
        /*
         * plugins.php
         */
        case 'get_plugins':
            echo json_encode(Plugins::getPlugins());
            break;
        /*
         * db.php
         */
        case 'query_sql':
            Util::checkPOST(array('query'));
            echo json_encode(Db::queryToArray($_POST['query']));
            break;
        case 'get_mysql_credentials':
            echo json_encode(Db::getMysqlCredentials());
            break;
        /*
         * post.php
         */
        case 'post_uids':
            Util::checkPOST(array('uids'));
            echo Util::json_unicode(Post::postUids($_POST['uids'], $_POST));
            break;
        /*
         * fb.php
         */
        case 'get_token':
            echo json_encode(Fb::getTokenFromSession());
            break;
    }
}
catch(Exception $e)
{
    $response_error = array();
    Util::handleException($e, $response_error);
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
