<?php
error_reporting(E_ALL|E_STRICT); // suppress all error outputs
chdir('..');
require_once 'common_inc.php';

libxml_use_internal_errors(true); // handle errors manually

try
{
	if(isset($_GET['param']))
	{
		$feed=new SimpleXMLElement($_GET['param'], 0, true/*indicate 1st param is url*/);
		$n=rand(0, $feed->channel->item->count()-1);
		header("Content-Type:text/plain; charset=utf-8");
		echo $feed->channel->item[$n]->title."\n".$feed->channel->item[$n]->link;
	}
}
catch(Exception $e)
{
    header("HTTP/1.1 500 Internal Server Error");
    $xmlErr = libxml_get_last_error();
    if($xmlErr != FALSE)
    {
        echo 'LibXML: Error '.$xmlErr->code.' '.$xmlErr->message;
    }
    else
    {
        echo 'Uncaught error: '.$e->getMessage();
    }
}
?>
