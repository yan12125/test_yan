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
function cleanTable()
{
    $('#wrapper').html('<table id="list"></table><div id="pager"></div>');
}

function viewUsers()
{
    cleanTable();
    $("#list").jqGrid({
        url: 'users.php',
        postData: { action: 'view_users' },
        mtype: 'POST', 
        datatype: "json",
        colNames: [ '姓名','狀態', '有效', '其他資訊' ],
        colModel: [
            {name:'name', width: 200},
            {name:'status', width: 70},
            {name:'valid', width: 50}, 
            {name:'msg', width: 600},
        ],
        rowNum: 40,
        pager: '#pager',
        caption: "所有使用者", 
        height: 500
    });
    $("#list").jqGrid('navGrid','#pager',{edit:false,add:false,del:false});
}
</script>
</head>
<body>
<a href="#" onclick="viewUsers();">所有使用者</a>
<div id="wrapper">選擇一個表格</div>
</body>
</html>
