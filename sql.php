<?php
require_once 'common_inc.php';
require_once 'util.php';
ip_only('127.0.0.1');

if(isset($_POST['action'])&&strpos($_SERVER['REQUEST_URI'], basename(__FILE__))!==FALSE)
{
    try
    {
        switch($_POST['action'])
        {
            case 'query':
                checkPOST(array('query'));
                $stmt = $db->query($_POST['query']);
                if(!$stmt)
                {
                    throw new Exception(getPDOErr($db));
                    exit(0);
                }
                echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
                break;
        }
        exit(0);
    }
    catch(Exception $e)
    {
        echo json_encode(array('error' => $e->getMessage()));
        exit(0);
    }
}
?>
<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<title>sql.php</title>
<script src="/HTML/library/jquery.js"></script>
<script>
$(document).on('ready', function(e){
    $('#query').on('keyup', function(e){
        if(e.which == 13) // enter
        {
            $('#b_submit').click();
        }
    });
    $('#b_submit').on('click', function(e){
        $('#result tbody').html('<tr></tr>');
        var q = $('#query').val();
        $.ajax({
            url: 'sql.php', 
            data: { action: 'query', query:  q }, 
            success: function(response, status, xhr){
                parseQueryResult(q, response);
            }, 
            dataType: 'json', 
            type: 'POST'
        });
    });
});

// reference: http://stackoverflow.com/questions/1787322/htmlspecialchars-equivalent-in-javascript
function escapeHtml(text)
{
    return text
        .replace(/&/g, "&amp;")
        .replace(/</g, "&lt;")
        .replace(/>/g, "&gt;")
        .replace(/"/g, "&quot;")
        .replace(/'/g, "&#039;");
}

function parseQueryResult(query, result)
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
                $('#query').val($(this).data('query'));
                $('#b_submit').click();
            });
    }
    if(result.length == 0)
    {
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
    min-width: 30px;
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
    Query: <input type="text" id="query" value="">
    <input type="button" id="b_submit" value="Submit">
    <div id="history"></div>
    <table id="result"><tbody>
    </tbody></table>
</body>
</html>
