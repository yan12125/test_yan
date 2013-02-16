<?php
header("Content-type: text/html; charset=utf-8");

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

// PHP 5.2.6 doesn't support SimpleXMLElement::count(), so write one
/*
 * not used anymore
function xmlCount($xml)
{
	$i=0;
	foreach($xml as $child)
	{
		$i++;
	}
	return $i;
}
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
    print_r(debug_backtrace());
    // using PDO, get text messages
    $errorInfo = $db->errorInfo();
    return $errorInfo[2];
}

if(isset($useFB))
{
	if($useFB===true)
	{
        require_once $facebook_path;
		// Disable ssl verify to hide messages in error.log
		// Reference: http://stackoverflow.com/questions/7374223/invalid-or-no-certificate-authority-found-using-bundled-information
		Facebook::$CURL_OPTS[CURLOPT_SSL_VERIFYPEER]=false;
		$facebook=new Facebook(array('appId'=>$appId,'secret'=>$appSecret));
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
        'error' => $e->getMessage()
    ));
    exit(0);
}
?>
