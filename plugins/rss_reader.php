<?php
chdir('..');
require_once 'common_inc.php';

try
{
	if(isset($_GET['param']))
	{
		$data=file_get_contents($_GET['param']);
		$feed=new SimpleXMLElement($data);
		$n=rand(0, $feed->channel->item->count()-1);
		header("Content-Type:text/plain; charset=utf-8");
		echo $feed->channel->item[$n]->title."\n".$feed->channel->item[$n]->link;
	}
}
catch(Exception $e)
{
	echo 'Unexpected error occurred: '.$e->getMessage();
}
?>
