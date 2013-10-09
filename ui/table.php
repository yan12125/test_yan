<!DOCTYPE html>
<html>
<head>
<title>table.php</title>
<meta charset="UTF-8">
<?php
require '../common_inc.php';
External::setRelativePath('..');
echo External::loadJsCss('jquery-ui', 'jqGrid');
?>
<style>
/*
 * jqGrid word wrapping 
 * http://stackoverflow.com/questions/6510144
 */
.ui-jqgrid tr.jqgrow td
{
    word-wrap: break-word;
    font-size: 14px;
    white-space: pre-wrap;
}
</style>
<script>
function createTable(_action, columns, _caption, _rowNum)
{
    $('#wrapper').html('<table id="list"></table><div id="pager"></div>');
    var options = {
        url: '../wrapper.php',
        postData: { action: _action },
        mtype: 'POST', 
        datatype: "json",
        colNames: [],
        colModel: [],
        rowNum: _rowNum, 
        pager: '#pager',
        caption: _caption, 
        height: 500
    };
    for(var i = 0;i < columns.length;i++)
    {
        options.colNames.push(columns[i].caption);
        options.colModel.push({
            name: columns[i].name,
            width: columns[i].width
        });
    }
    $("#list").jqGrid(options);
    $("#list").jqGrid('navGrid','#pager',{edit:false,add:false,del:false});
    $('#list').setGridParam({ rowNum: _rowNum });
}

$(document).on('ready', function (e) {
    var parameters = {
        'viewRunningUsers': {
            caption: '洗版中使用者', 
            action: 'view_running_users', 
            columns: [
                { caption: '姓名', name: 'name', width: 200 }, 
                { caption: '狀態', name: 'status', width: 100 }, 
                { caption: '授權碼有效', name: 'valid', width: 100 }, 
                { caption: '訊息', name: 'msg', width: $(document).width() - 500 }
            ], 
            row_num: 30
        }, 
        'viewOtherUsers': {
            caption: '其他使用者', 
            action: 'view_other_users', 
            columns: [
                { caption: '姓名', name: 'name', width: 200 }, 
                { caption: '狀態', name: 'status', width: 100 }, 
                { caption: '授權碼有效', name: 'valid', width: 100 }, 
                { caption: '訊息', name: 'msg', width: $(document).width() - 500 }
            ], 
            row_num: 30
        }, 
        'getStats': {
            caption: '發文字數統計', 
            action: 'report_stats', 
            columns: [
                { caption: '留言字數', name: 'length', width: 100 }, 
                { caption: '成功數', name: 'success', width: 100 }, 
                { caption: '失敗數', name: 'timed_out', width: 100 }, 
                { caption: '成功率', name: 'ratio', width: 100 }
            ], 
            row_num: 40
        }, 
        'runningState': {
            caption: '洗版狀況', 
            action: 'running_state', 
            columns: [
                { caption: '項目', name: 'name', width: 100 }, 
                { caption: '數值', name: 'value', width: 400 }, 
            ], 
            row_num: 10
        }, 
        'textsLog': {
            caption: '洗版內容修改紀錄', 
            action: 'texts_log', 
            columns: [
                { caption: '姓名', name: 'name', width: 200 }, 
                { caption: '標題', name: 'title', width: 200 }, 
                { caption: '修改時間', name: 'update_time', width: 200 }
            ], 
            row_num: 40
        }
    };
    $('input[type=button]').each(function (index, elem) {
        if(parameters[elem.id])
        {
            var cur_action = parameters[elem.id];
            $(elem).val(cur_action.caption);
            $(elem).on('click', function (e) {
                createTable(cur_action.action, cur_action.columns, cur_action.caption, cur_action.row_num);
            });
        }
    });
});
</script>
</head>
<body>
<input type="button" id="viewRunningUsers">
<input type="button" id="viewOtherUsers">
<input type="button" id="getStats">
<input type="button" id="runningState">
<input type="button" id="textsLog">
<div id="wrapper">選擇一個表格</div>
</body>
</html>
