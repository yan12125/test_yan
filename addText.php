<!DOCTYPE html>
<html>
<head>
<meta http-equiv="Content-Type" content="text/html; charset=utf-8">
<script src="/HTML/library/jquery.js"></script>
<script>
function checkData()
{
	if($("#title").val() != "" && $("#texts").val() != "")
	{
		return true;
	}
	else
	{
		alert("標題和內容皆為必填！");
		return false;
	}
}

$(document).on('ready', function(e){
    $('#btn_submit').on('click', function(e){
        if(!checkData())
        {
            return;
        }
        $.ajax({
            url: 'texts.php', 
            data: { 
                action: 'add_text', 
                title: $('#title').val(), 
                texts: $('#texts').val()
            }, 
            type: 'POST', 
            dataType: 'json', 
            success: function(response, status, xhr){
                if(response['status'] == 'success')
                {
                    $('#result').html('內容成功增加！');
                    $('#title,#texts').val('');
                }
                else
                {
                    $('#result').html(response['error']);
                }
            }
        });
    });
});
</script>
</head>
<body>
請輸入想要增加到洗版機的內容： <br />
標題(必填)： <input type="text" id="title" /><br />
內容(必填)：<br />
<textarea cols="80" rows="10" id="texts"></textarea><br />
<input type="button" value="送出" id="btn_submit" />
<br />
<div>
說明：<br />
1.內容分行填入，一行代表一則留言<br />
2.請刪除空白行<br />
3.標題一定要填，因為這是讓人在主程式選擇的項目<br />
</div>
<div id="result"></div>
</body>
</html>

