<?php
class Fb
{
    protected static $fb = null;

    protected static function loadFB()
    {
        if(!is_null(self::$fb))
        {
            return;
        }
        $appConf = Config::getParamArr(array('appId', 'appSecret', 'fb_prefix'));
        External::loadPhp('facebook');
        // Disable ssl verify to hide messages in error.log
        // Reference: http://stackoverflow.com/questions/7374223/invalid-or-no-certificate-authority-found-using-bundled-information
        Facebook::$CURL_OPTS[CURLOPT_SSL_VERIFYPEER] = false;
        // In alternate.php, timeout is 30s, and I want a shorter interval here
        Facebook::$CURL_OPTS[CURLOPT_TIMEOUT] = 20;
        self::$fb = new Facebook(array(
            'appId' => $appConf['appId'], 
            'secret' => $appConf['appSecret']
        ));
    }

    // require PHP 5.3
    public static function __callStatic($name, $args)
    {
        self::loadFB();
        return call_user_func_array(array(self::$fb, $name), $args);
    }

    // not using get total count because it's too slow
    public static function getCount($access_token)
    {
        // July 2013 breaking changes
        $post_id = '198971170174405_198971283507727';
        $result = self::api("/{$post_id}/comments", array(
            'summary' => true, 
            'access_token' => $access_token
        ));
        return (integer)$result['summary']['total_count'];
    }

    public static function getAppToken()
    {
        $stmt = Db::query('select value from main where name="app_token"');
        $app_token = $stmt->fetch(PDO::FETCH_ASSOC);
        // App token never expires
        if($app_token)
        {
            return $app_token['value'];
        }
        else
        {
            $appConf = Config::getParamArr(array('appId', 'appSecret'));
            $params = http_build_query(array(
                'client_id' => $appConf['appId'], 
                'client_secret' => $appConf['appSecret'], 
                'grant_type' => 'client_credentials'
            ));
            $url = 'https://graph.facebook.com/oauth/access_token?'.$params;
            $response = file_get_contents($url);
            parse_str($response, $result);
            if(isset($result['access_token']))
            {
                $token = $result['access_token'];
                $stmt = Db::prepare('insert into main (name,value) values ("app_token",?)');
                $stmt->execute(array($token));
                return $token; 
            }
            else
            {
                throw new Exception('Failed to get App access token. '.$response);
            }
        }
    }
}
?>
