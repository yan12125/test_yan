<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<title>sql.php</title>
<?php
require '../common_inc.php';
External::setRelativePath('..');
echo External::loadJsCss('codemirror', 'phpjs');
?>
<script>
$(document).on('ready', function(e){
    var editor = CodeMirror.fromTextArea($('#query')[0], {
        mode: 'text/x-mysql', 
        lineWrapping: true, 
        autofocus: true, 
        extraKeys: {
            "Ctrl-Enter": function (cm) {
                sendQuery(editor);
            }
        }
    });
    editor.setSize("100%", 100); // (width, height)
    $('#b_submit').on('click', function(e){
        sendQuery(editor);
    });
    $('#b_update_status').on('click', function(e) {
        updateStatus();
    });
    updateStatus();
});

function sendQuery(editor)
{
    $('#result tbody').html('<tr></tr>');
    var q = editor.getDoc().getValue();
    callWrapper('query_sql', { query:  q }, function(response){
        parseQueryResult(q, response, editor);
        updateStatus();
    });
}

function updateStatus()
{
    callWrapper('query_sql', { query:  'SHOW GLOBAL STATUS' }, function(response) {
        for(var i = 0; i < response.length; i++)
        {
            var key = response[i].Variable_name;
            var value = parseInt(response[i].Value);
            if(key == 'Bytes_sent')
            {
                $('#bytes-sent').text(value.toLocaleString());
            }
            if(key == 'Bytes_received')
            {
                $('#bytes-received').text(value.toLocaleString());
            }
            if(key == 'Uptime')
            {
                var days = Math.floor(value / 86400);
                value = value % 86400;
                var hours = Math.floor(value / 3600);
                value = value % 3600;
                var minutes = Math.floor(value / 60);
                var seconds = value % 60;
                $('#uptime').text(sprintf("%d day %02d:%02d:%02d", days, hours, minutes, seconds));
            }
        }
    });
}

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
        (function (_query) {
            var resendBtn = $('<button>').text('Resend').click(function (e) {
                editor.setValue(_query);
                sendQuery(editor);
            });
            var copyBtn = $('<button>').text('Copy to editor').click(function (e) {
                editor.setValue(_query);
            });
            var removeBtn = $('<button>').text('Remove').click(function (e) {
                $(this).parent().remove();
            });
            var $historyItem = $('<div>')
                .addClass('historyItem')
                .data('query', _query)
                .append(_query)
                .append(resendBtn)
                .append(copyBtn)
                .append(removeBtn);
            $('#history').append($historyItem);
        }) (query);
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
            resultHTML += '<td><pre>'+escapeHtml(""+result[i][field])+'<pre></td>';
        }
        resultHTML += '</tr>'
    }
    $('#result tbody').html(resultHTML);
}
</script>
<style>
#result, #status
{
    border-collapse: collapse;
}

#status
{
    float: right;
}

#result
{
    clear: both;
}

#status td
{
    padding: 5px;
    border: 1px solid black;
}

/* 
 * http://stackoverflow.com/questions/5065766
 */
#status tr:not(:first-child) td:nth-child(2)
{
    text-align: right;
}

#result td
{
    border-width: 1px;
    border-style: solid;
    border-color: black;
    word-break: break-all;
    min-width: 50px;
    vertical-align: top;
}

.CodeMirror
{
    border: 1px solid black;
    font-size: 20px;
}

/* http://stackoverflow.com/questions/248011/how-do-i-wrap-text-in-a-pre-tag */
pre
{
    white-space: pre-wrap;
}
</style>
</head>
<body>
    Query:<br>
    <textarea id="query"></textarea><br>
    <table id="status">
        <tr>
            <td>SQL server status</td>
            <td>Value</td>
        </tr>
        <tr>
            <td>Uptime</td>
            <td id="uptime"></td>
        </tr>
        <tr>
            <td>Bytes sent</td>
            <td id="bytes-sent"></td>
        </tr>
        <tr>
            <td>Bytes received</td>
            <td id="bytes-received"></td>
        </tr>
    </table>
    <input type="button" id="b_submit" value="Submit (Ctrl-Enter)">
    <input type="button" id="b_update_status" value="Update server status">
    <div id="history"></div>
    <table id="result"><tbody>
    </tbody></table>
</body>
</html>
