<?php
$useFB=false;
require_once 'common_inc.php';

if(isset($_GET['source']))
{
	header('Location: '.$source_url);
	exit(0);
}

function config($key, $value)
{
	$ret_val='(empty result)';

    $stmt = $db->prepare("SELECT value FROM main WHERE name=?");
    $stmt->execute(array($key));
	$result1 = $stmt->fetchAll(PDO::FETCH_ASSOC);
	if(count($result1)>0)
	{
        $stmt_update = "UPDATE main SET value=? WHERE name=?";
		if($stmt_update->execute(array($value, $key))==false)
		{
			$ret_val=getPDOErr($db);
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
