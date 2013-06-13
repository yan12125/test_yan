<?php
session_start();
require '../common_inc.php';

// still return result for http codes other than 200 in file_get_contents
// http://stackoverflow.com/questions/6718598/download-the-contents-of-a-url-in-php-even-if-it-returns-a-404
stream_context_set_default(array('http' => array('ignore_errors' => true)));

class Auth
{
    public static function authenticate()
    {
        $conf = Config::getParamArr(array('appId', 'rootUrl'));
        $params = http_build_query(array(
            'client_id' => $conf['appId'], 
            'scope' => 'publish_stream,user_groups', 
            'redirect_uri' => $conf['rootUrl'].'ui/auth.php'
        ));
        $authUrl='https://graph.facebook.com/oauth/authorize?'.$params;
        Header("Location: ".$authUrl);
        exit(0);
    }

    public static function getToken($fbCode)
    {
        $conf = Config::getParamArr(array('appId', 'appSecret', 'rootUrl'));
        $params = http_build_query(array(
            'client_id' => $conf['appId'], 
            'redirect_uri' => $conf['rootUrl'].'ui/auth.php', 
            'client_secret' => $conf['appSecret'], 
            'code' => $fbCode
        ));
        $tokenUrl='https://graph.facebook.com/oauth/access_token?'.$params;
        $authPage = file_get_contents($tokenUrl);
        parse_str($authPage, $arr_result);
        if(!isset($arr_result['access_token']))
        {
            $arr_result = json_decode($authPage, true);
            echo json_encode(array(
                'error' => 'retrieve_access_token_failed', 
                'result' => $authPage, 
                'result_json' => $arr_result
            ));
            exit(0);
        }

        if($arr_result['expires'] < 7201)
        {
            $arr_result = exchangeToken($arr_result['access_token']);
        }
        return array('token' => $arr_result['access_token']);
    }

    public static function exchangeToken($token)
    {
        $conf = Config::getParamArr(array('appId', 'appSecret'));
        $params = http_build_query(array(
            'client_id' => $conf['appId'], 
            'client_secret' => $conf['appSecret'], 
            'grant_type' => 'fb_exchange_token', 
            'fb_exchange_token' => $token
        ));
        $url_newToken='https://graph.facebook.com/oauth/access_token?'.$params;
        parse_str(file_get_contents($url_newToken), $arr_result);
        if($arr_result['expires'] < 7201) // 2 hours
        {
             throw new Exception('token_expired_soon');
        }
        return $arr_result;
    }
}

if(!isset($_GET['code']))
{
    session_destroy();
    if(isset($_GET['error']))
    {
        header('Location: https://www.facebook.com/');
        exit(0);
    }
    Auth::authenticate();
}
else
{
    try
    {
        header('Content-type: application/json');
        $tokenObj = Auth::getToken($_GET['code']);
        $_SESSION['access_token'] = $tokenObj['token'];
        Header('Location: '.Config::getParam('rootUrl').'ui/index.php');
    }
    catch(Exception $e)
    {
        echo json_encode(array('error' => $e->getMessage()));
    }
}
?>
