<?php
$useFB=false;
require_once 'common_inc.php';

$successful=false;
$msg='';

try
{
	if(isset($_POST['texts'])&&isset($_POST['title']))
	{
		$title=$_POST['title'];
		$query="INSERT INTO texts (title,handler,text) VALUES ('".$title."',NULL,'".json_encode(explode("\r\n", $_POST['texts']), JSON_UNESCAPED_UNICODE)."')";
		if(mysql_query($query)==FALSE)
		{
			$msg=$query."<br />\n".mysql_error();
		}
		else
		{
			$successful=true;
		}
	}
}
catch(Exception $e)
{
	echo $e->getMessage;
	exit(0);
}
?>
<html>
<head>
<meta http-equiv="Content-Type" content="text/html; charset=utf-8">
<script>
function checkData()
{
	if(document.forms["addTextForm"]["title"].value!=""&&document.forms["addTextForm"]["texts"].value!="")
	{
		return true;
	}
	else
	{
		alert("標題和內容皆為必填！");
		return false;
	}
}
</script>
</head>
<body>
<form method="POST" name="addTextForm" onsubmit="return checkData();">
請輸入想要增加到資料表test_yan.texts的內容： <br />
標題(必填)： <input type="text" name="title" /><br />
內容(必填)：<br />
<textarea cols="80" rows="10" name="texts"></textarea><br />
<input type="submit" value="Submit" />
</form><br />
<div>
說明：<br />
1.內容分行填入，一行代表一則留言<br />
2.請刪除空白行<br />
3.標題一定要填，因為這是讓人在主程式選擇的項目<br />
</div><br />
<?php
if($successful)
{
	echo "內容成功增加。<br />\n";
}
else
{
	echo $msg;
}
?>
</body>
</html>
