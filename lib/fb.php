<?php
class Fb
{
    protected static $fb = null;
    protected static $post_id = '198971170174405_198971283507727';

    protected static function loadFB()
    {
        if(!is_null(self::$fb))
        {
            return;
        }
        $appConf = Config::getParamArr(array('appId', 'appSecret', 'fb_prefix'));
        External::loadPhp('facebook');
        // Disable ssl verify to hide messages in error.log
        // Reference: http://stackoverflow.com/questions/7374223
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

    // used in stats.php
    public static function getCommentsInfo($access_token)
    {
        // July 2013 breaking changes
        // total count can't be accessed by FQL anymore
        $queries = new FbBatch($access_token);
        $queries->push(null, '/'.self::$post_id.'/comments', array(
            'summary' => true
        ));
        // "now()": http://stackoverflow.com/questions/3952954
        // "anon": http://stackoverflow.com/questions/9580055
        $queries->push(null, '/fql', array(
            'q' => 'SELECT time,text,now() FROM comment WHERE post_id = "'.self::$post_id.'" ORDER BY time DESC LIMIT 1'
        ));
        $results = $queries->run();
        return array(
            'total_count' => (integer)$results[0]['summary']['total_count'], 
            'last_comment_time' => $results[1]['data'][0]['time'], 
            'last_comment' => $results[1]['data'][0]['text'], 
            'server_time' => $results[1]['data'][0]['anon'], 
            'data' => json_encode($results)
        );
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

    protected static function getAuthUrl($redirect_uri)
    {
        $conf = Config::getParamArr(array('appId', 'rootUrl'));
        $params = http_build_query(array(
            'client_id' => $conf['appId'], 
            'scope' => 'publish_stream,user_groups', 
            'redirect_uri' => $redirect_uri
        ));
        $authUrl='https://graph.facebook.com/oauth/authorize?'.$params;
        return $authUrl;
    }

    public static function getToken($code, $redirect_uri)
    {
        $context = stream_context_create(array(
            'http' => array('ignore_errors' => true),
        ));
        $conf = Config::getParamArr(array('appId', 'appSecret', 'rootUrl'));
        $params = http_build_query(array(
            'client_id' => $conf['appId'], 
            'redirect_uri' => $redirect_uri,  
            'client_secret' => $conf['appSecret'], 
            'code' => $code
        ));
        $tokenUrl='https://graph.facebook.com/oauth/access_token?'.$params;
        $authPage = file_get_contents($tokenUrl, false, $context);
        parse_str($authPage, $arr_result);
        if(!isset($arr_result['access_token']))
        {
            $arr_result = json_decode($authPage, true);
            return array(
                'error' => 'retrieve_access_token_failed', 
                'result' => $authPage, 
                'result_json' => $arr_result, 
                'redirect_uri' => $redirect_uri
            );
        }

        if($arr_result['expires'] < 7201)
        {
            $arr_result = self::exchangeToken($arr_result['access_token']);
        }
        return $arr_result;
    }

    public static function login()
    {
        Util::redirectHttps();
        if(isset($_GET['access_token']))
        {
            return;
        }
        session_start();
        if(isset($_SESSION['access_token']))
        {
            return;
        }
        if(!isset($_GET['code']) || !isset($_SESSION['redirect_uri']))
        {
            if(isset($_GET['error']))
            {
                header('Location: https://www.facebook.com/');
                exit(0);
            }
            $_SESSION['redirect_uri'] = Util::getPageUrl();
            header('Location: '.self::getAuthUrl($_SESSION['redirect_uri']));
            exit(0);
        }
        $token = self::getToken($_GET['code'], $_SESSION['redirect_uri']);
        if(!isset($token['access_token']))
        {
            echo json_encode($token);
            exit(0);
        }
        $_SESSION['access_token'] = $token['access_token'];
        $_SESSION['expires'] = $token['expires'];
        header('Location: '.Util::getPageUrl(array('code')));
    }

    public static function getTokenFromSession()
    {
        session_start();
        $data = array(
            'access_token' => $_SESSION['access_token'], 
            'expires' => $_SESSION['expires']
        );
        session_destroy();
        return $data;
    }

    protected static function exchangeToken($token)
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
