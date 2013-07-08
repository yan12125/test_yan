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
    $(window).on('resize', function(e){
        resizeAll()
    });
    $('#save, #discard').button({'disabled': true});
    $('#discard').on('click', function(e){
        loadText($('.title.selected')[0]);
    });
    updateTitles();
});

function resizeAll()
{
    // $(document).height() and $(window).height() are different!
    // http://stackoverflow.com/questions/1304378/jquery-web-page-height
    $('#titles').height($(window).height());
    $('#texts').width($(window).width() - $('#controls').offset().left - 20);
}

function updateTitles()
{
    $('#titles').html('');
    callWrapper('list_titles', function(data){
        for(var i = 0;i < data.length;i++)
        {
            $('#titles').append('<div class="title">'+data[i].title+'</div>');
        }
        $('.title').on('click', function(e){
            loadText(this);
        });
        resizeAll();
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
        $('#texts').val(response.text);
        $('#handler').text(response.handler);
    });
}
</script>
<style>
body
{
    margin: 0px;
    overflow-y: hidden;
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
    background-color: #ff99bb;
    width: 200px;
    text-align: center;
    cursor: pointer;
}

.title:hover, .title.selected
{
    background-color: #ff0044;
}

#controls
{
    margin-left: 250px;
    padding: 5px;
}

#texts
{
    height: 400px;
    overflow-x: scroll;
    overflow-y: scroll;
    white-space: nowrap;
    font-size: 16px;
}

#caption
{
    font-size: 24px;
    font-weight: bold;
}

.right
{
    text-align: right;
}
</style>
<body>
    <div id="titles"></div>
    <div id="controls">
        <div id="caption">請選擇標題</div>
        <textarea id="texts"></textarea><br>
        外掛：<span id="handler">None</span><br>
        <div class="right">
            <input type="button" value="存檔" id="save">
            <input type="button" value="放棄修改" id="discard">
        </div>
    </div>
</body>
</html>
