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
<form method="POST" name="addTextForm" onsubmit="return checkData();" action="addTextCore.php">
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
</div>
</body>
</html>
