<?php
class Auth
{
    public static function getAppInfo()
    {
        $conf = Config::getParamArr(array('appId', 'rootUrl'));
        $params = http_build_query(array(
            'client_id' => $conf['appId'], 
            'scope' => 'publish_stream,user_groups', 
            'redirect_uri' => $_SERVER['HTTP_REFERER']
        ));
        $authUrl='https://graph.facebook.com/oauth/authorize?'.$params;
        return array(
            'authUrl' => $authUrl, 
            'appId' => Config::getParam('appId')
        );
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
?>
