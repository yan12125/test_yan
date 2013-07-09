<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<title>Text Manager</title>
<?php
require '../common_inc.php';
External::setRelativePath('..');
echo External::loadJsCss('jquery-ui');
?>
<script>
$(document).on('ready', function(e){
    getAccessToken(function(data){
        $('#token').val(data.access_token);
        $(window).on('resize', function(e){
            resizeAll();
        });
        $('#save, #discard').button({'disabled': true});
        $('#new_title').button();
        $('#save').on('click', function(e){
            updateText($('.title.selected')[0]);
        });
        $('#discard').on('click', function(e){
            loadText($('.title.selected')[0]);
        });
        $('#new_title').on('click', function(e){
            newTitle();
        });
        updateTitles();
        loadPlugins();
    });
});

function resizeAll()
{
    // $(document).height() and $(window).height() are different!
    // http://stackoverflow.com/questions/1304378/jquery-web-page-height
    // two steps below can't be exchanged! View in chrome debugger
    $('#texts').width($(window).width() - $('#controls').offset().left - 40);
    $('#titles').height($(window).height());
}

function updateTitles(cb)
{
    $('#titles').html('');
    callWrapper('list_titles', function(data){
        for(var i = 0;i < data.length;i++)
        {
            $('#titles').append('<div class="title">'+data[i]+'</div>');
        }
        $('.title').on('click', function(e){
            loadText(this);
        });
        resizeAll();
        if(typeof cb == 'function')
        {
            cb();
        }
    });
}

function loadPlugins()
{
    callWrapper('get_plugins', function(plugins){
        for(var i = 0;i < plugins.length;i++)
        {
            var p = plugins[i];
            $('#handler').append('<option value="'+p+'">'+p+'</option>');
        }
    });
}

function loadText(titleButton)
{
    $('#save, #discard').button('option', 'disabled', false);
    $('#texts').val('Loading...');
    var title = $(titleButton).text();
    $('.title').removeClass('selected');
    $(titleButton).addClass('selected');
    $('#caption').text(title);
    callWrapper('get_texts', { 'title': title }, function(response){
        // use val() instead of text()
        // http://stackoverflow.com/questions/8854288
        $('#texts').val(response.text).focus();
        $('#handler').val(response.handler);
    });
}

function updateText(titleButton)
{
    callWrapper('update_text', {
        title: $(titleButton).text(), 
        texts: $('#texts').val(), 
        handler: $('#handler').val(), 
        access_token: $('#token').val()
    }, function(response){
        if(response.status == 'success')
        {
            loadText(titleButton);
            $('#dialog').text('內容成功更新');
        }
        else
        {
            $('#dialog').text(response.error);
        }
        $('#dialog').dialog({
            modal: true
        });
    });
}

function newTitle()
{
    var newTitle_impl = function(){
        var _title = $('#title_new').val();
        callWrapper('add_title', { title: _title }, function(response){
            if(response.status == 'success')
            {
                updateTitles(function(){
                    var titleButton = $('.title').filter(function(){
                        return $(this).text() == _title;
                    });
                    loadText(titleButton[0]);
                });
            }
            else
            {
                $('#dialog').text(response.error);
                $('#dialog').dialog({
                    modal: true
                });
            }
        });
    };
    $('#title_new').val('');
    $('#new_title_dialog').dialog({
        modal: true, 
        buttons: {
            'OK': function(){
                newTitle_impl();
                $(this).dialog('close');
            }
        }
    });
}
</script>
<style>
body
{
    margin: 0px;
}

#titles
{
    float: left;
    width: 250px;
    overflow-y: scroll;
}

.title
{
    margin: 10px;
    padding: 3px;
    background-color: #00FFFF; /* Cyan */
    width: 200px;
    text-align: center;
    cursor: pointer;
}

.title:hover, .title.selected
{
    background-color: #00CED1; /* DarkTurquoise */
}

#controls
{
    margin-left: 250px;
    padding: 5px;
}

#texts
{
    height: 400px;
    white-space: nowrap;
    font-size: 16px;
}

#caption
{
    font-size: 24px;
    font-weight: bold;
}
</style>
<body>
<div id="fb-root"></div>
<div id="titles"></div>
<div id="controls">
    <div id="caption">請選擇一個標題</div>
    <textarea id="texts"></textarea><br>
    外掛：
    <select id="handler">
        <option value="__none__">(None)</option>
    </select><br>
    <div class="right">
        <input type="button" value="新增內容" id="new_title">
        <input type="button" value="存檔" id="save">
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
