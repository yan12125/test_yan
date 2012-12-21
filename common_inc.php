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

function start_db($host, $username, $password, $dbname)
{
	static $link=null;
	if(is_null($link))
	{
		$link=mysql_connect($host, $username, $password);
	}
	mysql_query("SET NAMES 'utf8'");
	mysql_select_db($dbname);
}

function load_params($paramName)
{
	$result=mysql_query("SELECT value FROM main WHERE name=\"".$paramName."\" ");
	$arr_result=mysql_fetch_row($result);
	return $arr_result[0];
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
start_db($sqlhost, $sqlusername, $mysqlPass, $dbname);
?>
