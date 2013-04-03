<?php
$secrets=json_decode(file_get_contents("./.htsecret"), true);
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
    global $facebook;
    if(is_null($facebook))
    {
        set_include_path(get_include_path().PATH_SEPARATOR.'../../');
        require_once "../../facebook.php";
        // Disable ssl verify to hide messages in error.log
        // Reference: http://stackoverflow.com/questions/7374223/invalid-or-no-certificate-authority-found-using-bundled-information
        Facebook::$CURL_OPTS[CURLOPT_SSL_VERIFYPEER]=false;
        $facebook=new Facebook(array('appId'=>$GLOBALS['appId'],'secret'=>$GLOBALS['appSecret']));
    }
}
try
{
    $dsn = "mysql:host={$sqlhost};dbname={$dbname};port={$sqlPort};charset=utf8";
    $db = new PDO($dsn, $sqlusername, $mysqlPass);
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
