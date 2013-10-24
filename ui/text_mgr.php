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
echo External::loadJsCss('jquery-ui', 'parse_str');
?>
<script>
$(document).on('ready', function(e){
    getAccessToken(function(data){
        if(data.access_token !== '')
        {
            $('#token').val(data.access_token);
            $('#new_title').removeAttr('disabled');
            $('#login').attr('disabled', 'disabled');
        }
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
        updateTitles(function(titles){
            selectTitleByUrl(titles);
        });
        loadPlugins();
    });
});

function resizeAll()
{
    // $(document).height() and $(window).height() are different!
    // http://stackoverflow.com/questions/1304378
    // two steps below can't be exchanged! View in chrome debugger
    $('#texts').width($(window).width() - $('#controls').offset().left - 40);
    $('#titles').height($(window).height());
}

function updateTitles(cb)
{
    $('#titles').html('');
    callWrapper('list_titles', function(data){
        var titles = data.titles;
        for(var i = 0;i < titles.length;i++)
        {
            $('#titles').append('<div class="title">'+titles[i]+'</div>');
        }
        $('.title').on('click', function(e){
            loadText($(this).text());
        });
        resizeAll();
        if(typeof cb == 'function')
        {
            cb(titles);
        }
    });
}

function selectTitleByUrl(titles)
{
    // title may contain special chars such as ?
    // so encode in index.php and decode here
    var arr = {};
    parse_str(location.search.substring(1), arr);
    if(loadText(arr.title))
    {
        $('#titles').scrollTop(
            $('#titles').scrollTop() + 
            $('.title.selected').offset().top - 
            $('#titles').height()/2
        );
    }
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

function loadText(title)
{
    var titleArr = $('.title').map(function(i, e){ return $(e).text(); });
    if($.inArray(title, titleArr) == -1)
    {
        return false;
    }
    var titleButton = $('.title').hasText(title);
    $('#discard').button('option', 'disabled', false);
    if($('#token').val() !== '')
    {
        $('#save').button('option', 'disabled', false);
    }
    $('#texts').val('Loading...');
    $('.title').removeClass('selected');
    $(titleButton).addClass('selected');
    $('#caption').text(title);
    callWrapper('get_texts', { 'title': title }, function(response){
        // use val() instead of text()
        // http://stackoverflow.com/questions/8854288
        $('#texts').val(response.text).focus();
        $('#handler').val(response.handler);
    });
    return true;
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
            loadText($(titleButton).text());
            $('#dialog').text('內容成功更新，共'+response.lines+'則');
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
                    loadText(_title);
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

function testText()
{
    callWrapper('get_text_from_texts', {
        title: $('#caption').text(), 
        handler: $('#handler').val(), 
        texts: JSON.stringify($('#texts').val().split('\n'))
    }, function(data){
        if(data.error)
        {
            $('#img_ok').css('display', 'none');
            $('#img_error').css('display', 'inline');
            $('#test_result').text(data.error);
        }
        else
        {
            $('#img_ok').css('display', 'inline');
            $('#img_error').css('display', 'none');
            $('#test_result').text(data.msg);
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

#img_ok, #img_error
{
    height: 32px;
    width: 32px;
    display: none;
    vertical-align: middle;
}

#test_result
{
    // inline-block: http://www.blabla.cn/css_kb/html_span_width_kb.html
    display: inline-block;
    width: 400px;
}
</style>
<body>
<div id="fb-root"></div>
<div id="titles"></div>
<div id="controls">
    <div id="caption">請選擇一個標題</div>
    <textarea id="texts"></textarea><br>
    <div class="left">
        外掛：<select id="handler"><option value="__none__">(None)</option></select>
        <button id="test_text">測試外掛</button>
        <br>
        <img id="img_ok" src="../images/ok.png">
        <img id="img_error" src="../images/error.png">
        <span id="test_result"></span>
    </div>
    <div class="right">
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
