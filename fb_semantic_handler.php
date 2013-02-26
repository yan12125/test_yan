<?php
require_once 'common_inc.php';

if(isset($_GET['source']))
{
	header('Location: '.$source_url);
	exit(0);
}

if(isset($_GET['param']))
{
	echo $_GET['param'];
}
?>
