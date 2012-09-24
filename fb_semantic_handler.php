<?php
if(isset($_GET['source']))
{
	header("Content-Type:text/html; charset=utf-8");
	highlight_file(__FILE__);
	exit(0);
}

$useFB=false;
require_once 'common_inc.php';

if(isset($_GET['param']))
{
	echo $_GET['param'];
}
?>
