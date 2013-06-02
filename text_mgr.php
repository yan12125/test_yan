<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<title>Text Manager</title>
<?php
require 'common_inc.php';
echo External::loadJsCss('jquery', 'ajaxq');
?>
<script>
$(document).on('ready', function(e){
    callWrapper('list_titles', function(data){
        for(var i = 0;i < data.length;i++)
        {
            $('#titles').append('<div class="title">'+data[i].title+'</div>');
        }
        // $(document).height() and $(window).height() are different!
        // http://stackoverflow.com/questions/1304378/jquery-web-page-height
        $('#titles').height($(window).height());
        $('#texts').width($(window).width() - $('#controls').offset().left - 20);
        $('.title').on('click', function(e){
            $('#texts').text('');
            var title = $(this).text();
            $('.title').removeClass('selected');
            $(this).addClass('selected');
            $('#caption').text(title);
            callWrapper('get_texts', { 'title': title }, function(data){
                $('#texts').text(data.join("\n"));
            });
        });
    });
});
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
    height: 300px;
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
</style>
<body>
    <div id="titles"></div>
    <div id="controls">
        <div id="caption">請選擇標題</div>
        <textarea id="texts"></textarea>
    </div>
</body>
</html>
