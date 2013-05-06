<?php
error_reporting(0); // suppress all error outputs
chdir('..');
require_once 'common_inc.php';

libxml_use_internal_errors(true); // handle errors manually

try
{
	if(isset($_GET['param']))
	{
        $url = $_GET['param'];
        $ch = curl_init();
        curl_setopt_array($ch, array(
            CURLOPT_URL => $url, 
            CURLOPT_RETURNTRANSFER => true, 
            CURLOPT_FOLLOWLOCATION => true
        ));
        $xml = curl_exec($ch);
        curl_close($ch);
        if(empty($xml))
        {
            throw new Exception('Failed to retrieve '.$url);
        }
		$feed=new SimpleXMLElement($xml);
		$n=rand(0, $feed->channel->item->count()-1);
		header("Content-Type:text/plain; charset=utf-8");
		echo $feed->channel->item[$n]->title."\n".$feed->channel->item[$n]->link;
	}
}
catch(Exception $e)
{
    header('HTTP/1.1 500 Internal server error');
    $xmlErr = libxml_get_last_error();
    if($xmlErr !== false)
    {
        $output = array(
            'source' => 'LibXML', 
            'code' => $xmlErr->code, 
            'message' => $xmlErr->message, 
        );
    }
    else
    {
        $output = array(
            'source' => 'unknown', 
            'message' => $e->getMessage(), 
            'line' => $e->getLine()
        );
    }
    if(isset($xml))
    {
        $output['xml'] = $xml;
    }
    if(isset($url))
    {
        $output['url'] = $url;
    }
    echo json_encode($output);
}
?>
