<?php
if(isset($_GET['source']))
{
	header("Content-Type:text/html; charset=utf-8");
	highlight_file(__FILE__);
	exit(0);
}

$useFB=false;
require_once 'common_inc.php';

function config($key, $value)
{
	$ret_val='(empty result)';

	$query1="SELECT value FROM main WHERE name='$key'";
	$result1=mysql_query($query1);
	if(mysql_num_rows($result1)>0)
	{
		$query2="UPDATE main SET value='$value' WHERE name='$key'";
		if(mysql_query($query2)==false)
		{
			$ret_val=mysql_error();
		}
		else
		{
			$ret_val='Query succeeded.';
		}
	}
	else
	{
		$ret_val='Invalid field name';
	}

	return $ret_val;
}

$mess='';
$name='';
$value='';
if($_SERVER['REMOTE_ADDR']=='140.112.241.51')
{
	if(isset($_POST['name'])&&isset($_POST['value']))
	{
		$name=$_POST['name'];
		$value=$_POST['value'];
		$mess=config($name, $value);
	}
}
else
{
	echo "Only 140.112.241.51 can run this script!";
	exit(0);
}
?>
<html>
<head>
</head>
<body>
<form action="#" method="POST">
Name: <input type="text" name="name" value="<?php echo $name; ?>"/><br />
Value: <input type="text" name="value" value="<?php echo $value; ?>"/><br />
<input type="submit" value="Submit" /><br />
</form>
<?php echo $mess; ?>
</body>
</html>
