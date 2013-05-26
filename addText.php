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
    var title_exists_msg = '這個標題已存在！';
    $('#title').on('keydown', function(e){
        $('#msg').text('');
    });
    $('#title').on('blur', function(e){
        $('#msg').text('');
        $.post('wrapper.php', { action: 'check_title', title: $(this).val() }, function(response, status, xhr){
            if(response['status'] == 'title_exists')
            {
                $('#msg').text(title_exists_msg);
            }
        }, 'json');
    });
    $('#btn_submit').on('click', function(e){
        if(!checkData())
        {
            return;
        }
        $.ajax({
            url: 'wrapper.php', 
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
                    alert('內容成功增加！');
                    $('#title,#texts').val('');
                }
                else if(response['status'] == 'title_exists')
                {
                    alert(title_exists_msg);
                }
                else
                {
                    $('#result').text(response['error']);
                }
            }
        });
    });
});
</script>
<style>
#msg
{
    margin-left: 5px;
    color: red;
}
</style>
</head>
<body>
請輸入想要增加到洗版機的內容： <br />
標題(必填)： <input type="text" id="title" /><span id="msg"></span><br />
內容(必填)：<br />
<textarea cols="80" rows="10" id="texts"></textarea><br />
<input type="button" value="送出" id="btn_submit" />
<br />
<div>
說明：<br />
1.內容分行填入，一行代表一則留言<br />
2.標題一定要填，因為這是讓人在主程式選擇的項目<br />
3.不可與資料庫中已存在的標題重複<br>
</div>
<div id="result"></div>
</body>
</html>

