<?php
require_once 'common_inc.php';

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
        /*
         * users.php
         */
        case 'list_users':
            Util::checkPOST(array('IDs'));
            echo json_encode(Users::listUsersAndRate($_POST['IDs']));
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
            echo json_encode(Users::logout());
            break;
        /*
         * groups.php
         */
        case 'get_groups':
            Util::checkPOST(array('access_token'));
            echo Util::json_unicode(Groups::getUserGroups($_POST['access_token']));
            break;
        case 'get_group_info':
            Util::checkPOST(array('access_token', 'gid'));
            echo Util::json_unicode(Groups::getFromGroup($_POST['gid'], $_POST['access_token']));
            break;
        /*
         * texts.php
         */
        case "list_titles":
            echo Util::json_unicode(Texts::listTitles());
            break;
        case 'add_text':
            Util::checkPOST(array('title', 'texts'));
            echo json_encode(Texts::addText($_POST['title'], $_POST['texts']));
            break;
        /*
         * db.php
         */
        case 'query_sql':
            Util::checkPOST(array('query'));
            echo json_encode(Db::queryToArray($_POST['query']));
            break;
    }
}
catch(Exception $e)
{
    echo json_encode(array('error' => $e->getMessage()));
}
?>
