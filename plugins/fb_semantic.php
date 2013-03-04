<?php
chdir('..');
require_once 'common_inc.php';

if(isset($_GET['source']))
{
	header('Location: '.$source_url);
	exit(0);
}
?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN"
    "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml" xmlns:og="http://ogp.me/ns#" xmlns:fb="http://www.facebook.com/2008/fbml">
<head>
<meta property="og:title" content="Facebook images on comment" />
<meta property="og:type" content="article" /> 
<?php
if(isset($_GET['url']))
{
	echo '<meta property="og:url" content="'.$_GET['url'].'" />'."\n";
	echo '<meta property="og:image" content="'.$_GET['url'].'" />'."\n";
}
?>
</head>
<body>
<img src="<?php echo $_GET['url']; ?>" />
</body>
</html>
