<?php
require '../common_inc.php';
if(isset($_GET['login']) && $_GET['login'] == 'true')
{
    Fb::login();
}
?>
<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<title>Text Manager</title>
<?php
External::setRelativePath('..');
echo External::loadJsCss('jquery-ui', 'phpjs');
?>
<script language="javascript" src="text_mgr.js"></script>
<link rel="stylesheet" href="text_mgr.css"></script>
<script>
$(document).on('ready', function(e){
    getAccessToken(function(data){
        if(data.access_token !== '')
        {
            $('#token').val(data.access_token);
            $('#new_title').removeAttr('disabled');
            $('#login').attr('disabled', 'disabled');
        }
        $('#search').removeAttr('disabled'); // a bug???
        $(window).on('resize', function(e){
            resizeAll();
        });
        $('input[type="button"]').button();
        $('#login').on('click', function(e){
            location.href = 'https://' + location.host + location.pathname + '?login=true';
        });
        $('#save').on('click', function(e){
            updateText($('.title.selected')[0]);
        });
        $('#discard').on('click', function(e){
            loadText($('.title.selected').text());
        });
        $('#new_title').on('click', function(e){
            newTitle();
        });
        $('#test_text').on('click', function(e){
            testText();
        });
        $('#search_next').on('click', function(e){
            searchText();
        });
        $('#search_term').on('change', function(e){
            $('#search_results').val('[]');
        });
        updateTitles(function(titles){
            selectTitleByUrl();
        });
        loadPlugins();
    });
});
</script>
<body>
<div id="fb-root"></div>
<div id="titles"></div>
<div id="controls">
    <div id="caption">請選擇一個標題</div>
    <textarea id="texts"></textarea><br>
    <div class="left">
        外掛：<select id="handler"><option value="__none__">(None)</option></select>
        <button id="test_text">測試</button>
        <br>
        <img id="img_ok" src="../images/ok.png">
        <img id="img_error" src="../images/error.png">
        <span id="test_result"></span>
    </div>
    <div class="right">
        <input type="text" id="search_term">
        <input type="button" value="搜尋下一個" id="search_next">
        <input type="hidden" id="search_results"><br>
        <input type="button" value="登入" id="login">
        <input type="button" value="新增內容" id="new_title" disabled="disabled">
        <input type="button" value="存檔" id="save" disabled="disabled">
        <input type="button" value="放棄修改" id="discard">
    </div>
    <input type="hidden" id="token">
</div>
<div id="dialog" title="Text Manager" class="jqueryui-hidden"></div>
<div id="new_title_dialog" title="新增內容" class="jqueryui-hidden">
    請輸入標題：<input type="text" id="title_new">
</div>
</body>
</html>
