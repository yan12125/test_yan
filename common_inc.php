<?php
$secrets=json_decode(file_get_contents("../.htsecret"), true);
foreach($secrets as $key=>$value)
{
	${$key}=$value;
}

// not using get total count because it's too slow
function getCount($access_token)
{
    global $facebook;
	$result=$facebook->api(array(
        'method' => 'fql.query',
        'query' => 'SELECT comments FROM stream WHERE post_id="198971170174405_198971283507727"', 
        'access_token' => $access_token
	));
	return (integer)$result[0]['comments']['count'];
}

function load_params($paramName)
{
    $stmt = $GLOBALS['db']->prepare("SELECT value FROM main WHERE name=?");
    $stmt->execute(array($paramName));
	$arr_result=$stmt->fetch(PDO::FETCH_ASSOC);
	return $arr_result['value'];
}

function getPDOErr($db)
{
    // print_r(debug_backtrace());
    // using PDO, get text messages
    $errorInfo = $db->errorInfo();
    return $errorInfo[2];
}

$facebook = null;
function loadFB()
{
    global $facebook, $fb_prefix;
    if(is_null($facebook))
    {
        set_include_path(get_include_path().PATH_SEPARATOR.$fb_prefix);
        require_once $fb_prefix.'facebook.php';
        // Disable ssl verify to hide messages in error.log
        // Reference: http://stackoverflow.com/questions/7374223/invalid-or-no-certificate-authority-found-using-bundled-information
        Facebook::$CURL_OPTS[CURLOPT_SSL_VERIFYPEER]=false;
        $facebook=new Facebook(array('appId'=>$GLOBALS['appId'],'secret'=>$GLOBALS['appSecret']));
    }
}

function getAppToken()
{
    global $db, $appId, $appSecret;
    $stmt = $db->query('select value from main where name="app_token"');
    $app_token = $stmt->fetch(PDO::FETCH_ASSOC);
    // App token never expires
    if($app_token)
    {
        return $app_token['value'];
    }
    else
    {
        $params = http_build_query(array(
            'client_id' => $appId, 
            'client_secret' => $appSecret, 
            'grant_type' => 'client_credentials'
        ));
        $url = 'https://graph.facebook.com/oauth/access_token?'.$params;
        $response = file_get_contents($url);
        parse_str($response, $result);
        if(isset($result['access_token']))
        {
            $token = $result['access_token'];
            $stmt = $db->prepare('insert into main (name,value) values ("app_token",?)');
            $stmt->execute(array($token));
            return $token; 
        }
        else
        {
            throw new Exception('Failed to get App access token. '.$response);
        }
    }
}

try
{
    $dsn = "mysql:host={$sqlhost};dbname={$dbname};port={$sqlPort};charset=utf8";
    $db = new PDO($dsn, $sqlusername, $mysqlPass);
    unset($sqlusername, $mysqlPass, $sqlhost, $dbname, $sqlPort);
}
catch(PDOException $e)
{
    echo json_encode(array(
        'error' => $e->getMessage(), 
        'next_wait_time' => 600
    ));
    exit(0);
}
?>
