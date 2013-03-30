<?php
$secrets=json_decode(file_get_contents("./.htsecret"), true);
foreach($secrets as $key=>$value)
{
	${$key}=$value;
}

if(isset($_GET['source']))
{
	header('Location: '.$source_url);
	exit(0);
}

/*
// not using get total count because it's too slow
function getCount($facebook, $access_token)
{
	$result=$facebook->api(array(
			'method' => 'fql.query',
			'query' => 'SELECT comments FROM stream WHERE post_id="198971170174405_198971283507727"', 
			'access_token' => $access_token
		));
	return (integer)$result[0]['comments']['count'];
}
*/

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

function ip_only($ip)
{
    $remote_ip = $_SERVER['REMOTE_ADDR'];
    if($ip !== $remote_ip)
    {
        header('403 Forbidden');
        echo "IP {$remote_ip} forbidden";
        exit(0);
    }
}

$facebook = null;
function loadFB()
{
    global $facebook;
    if(is_null($facebook))
    {
        require_once "facebook.php";
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
