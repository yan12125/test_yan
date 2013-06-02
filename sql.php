<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<title>sql.php</title>
<?php
require 'common_inc.php';
echo External::loadJsCss('jquery', 'codemirror');
?>
<script>
$(document).on('ready', function(e){
    var editor = CodeMirror.fromTextArea($('#query')[0], {
        mode: 'text/x-mysql', 
        lineWrapping: true, 
        autofocus: true, 
    });
    editor.setSize("100%", 100); // (width, height)
    editor.on('beforeChange', function(cm, e){
        if(e.text.length == 2 && e.text[0] == "" && e.text[1] == "")
        {
            e.cancel();
            $('#b_submit').click();
        }
    });
    $('#b_submit').on('click', function(e){
        $('#result tbody').html('<tr></tr>');
        var q = editor.getDoc().getValue();
        $.ajax({
            url: 'wrapper.php', 
            data: { action: 'query_sql', query:  q }, 
            success: function(response, status, xhr){
                parseQueryResult(q, response, editor);
            }, 
            dataType: 'json', 
            type: 'POST'
        });
    });
});

function parseQueryResult(query, result, editor)
{
    if(typeof result['error'] !== 'undefined')
    {
        $('#result tr').html('<td>'+result['error']+'</td>');
        return;
    }
    var notAddLink = false; // whether to add a history link or not
    var historyItems = $('.historyItem');
    for(var i = 0;i < historyItems.length;i++)
    {
        if(historyItems.eq(i).data('query') == query)
        {
            notAddLink = true;
        }
    }
    if(!notAddLink)
    {
        $('#history').append('<div class="historyItem">'+query+'</div>');
        $('.historyItem:last').data('query', query)
            .on('click', function(e){
                editor.setValue($(this).data('query'));
                $('#b_submit').click();
            });
    }
    if(result.length == 0)
    {
        return;
    }
    else if(typeof result == 'object' && typeof result[0] == 'string')
    {   // simple string, come from UPDATE
        $('#result tbody').html('<tr><td>'+result[0]+'</td></tr>');
        return;
    }
    var resultHTML = '';
    resultHTML += '<tr>';
    for(var field in result[0])
    {
        resultHTML += '<td>'+field+'</td>';
    }
    resultHTML += '</tr>';
    for(var i = 0;i < result.length;i++)
    {
        resultHTML += '<tr>';
        for(var field in result[i])
        {
            resultHTML += '<td>'+escapeHtml(""+result[i][field])+'</td>';
        }
        resultHTML += '</tr>'
    }
    $('#result tbody').html(resultHTML);
}
</script>
<style>
#result
{
    border-collapse: collapse;
}

#result td
{
    border-width: 1px;
    border-style: solid;
    border-color: black;
    word-break: break-all;
    min-width: 50px;
}

.CodeMirror
{
    border: 1px solid black;
    font-size: 20px;
}

.historyItem
{
    cursor: pointer;
    color: blue;
    text-decoration: underline;
}
</style>
</head>
<body>
    Query:<br>
    <textarea id="query"></textarea><br>
    <input type="button" id="b_submit" value="Submit">
    <div id="history"></div>
    <table id="result"><tbody>
    </tbody></table>
</body>
</html>
