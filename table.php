<!DOCTYPE html>
<html>
<head>
<title>table.php</title>
<meta charset="UTF-8">
<style>
</style>
<script src="/HTML/library/jquery.js"></script>
<script src="/HTML/library/jquery-ui.js"></script>
<script src="/HTML/library/jquery.jqGrid.min.js"></script>
<script src="/HTML/library/grid.locale-tw.js"></script>
<link rel="stylesheet" href="/HTML/library/jquery-ui.css">
<link rel="stylesheet" href="/HTML/library/ui.jqgrid.css">
<script>
function createTable(module, _action, columns, _caption)
{
    $('#wrapper').html('<table id="list"></table><div id="pager"></div>');
    var options = {
        url: module,
        postData: { action: _action },
        mtype: 'POST', 
        datatype: "json",
        colNames: [],
        colModel: [],
        rowNum: 40,
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
}

function viewUsers()
{
    var columns = [
        { caption: '姓名', name: 'name', width: 200 }, 
        { caption: '狀態', name: 'status', width: 50 }, 
        { caption: '授權碼有效', name: 'valid', width: 70 }, 
        { caption: '訊息', name: 'msg', width: 600 }
    ];
    createTable('wrapper.php', 'view_users', columns, '所有使用者');
}

function getStats()
{
    var columns = [
        { caption: '留言字數', name: 'length', width: 70 }, 
        { caption: '成功數', name: 'success', width: 70 }, 
        { caption: '失敗數', name: 'timed_out', width: 70 }, 
        { caption: '成功率', name: 'ratio', width: 70 }
    ];
    createTable('wrapper.php', 'report_stats', columns, '發文字數統計');
}
</script>
</head>
<body>
<a href="#" onclick="viewUsers();">所有使用者</a>
<a href="#" onclick="getStats();">發文字數統計</a>
<div id="wrapper">選擇一個表格</div>
</body>
</html>
