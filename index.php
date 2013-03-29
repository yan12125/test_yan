<?php
session_start();
require_once 'common_inc.php';

if(!isset($_SESSION['access_token']) || !isset($_SESSION['expiry']))
{
    Header('Location: '.$rootUrl.'auth.php');
    exit(0);
}

// get basic user information from facebook
loadFB();
try
{
    $result=$facebook->api('/me', array('access_token'=>$_SESSION['access_token']));
    if(!isset($result['name']) || !isset($result['id']))
    {
        print_r($result);
        exit(0);
    }
}
catch(Exception $e)
{
    echo 'Error: '.$e->getMessage();
    exit(0);
}
?>
<!DOCTYPE html>
<html>
<head>
<title><?php echo $result['name']; ?></title>
<meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />
<style type="text/css">
body
{
    overflow-y: scroll;
}

#wrapper
{
	position: relative;
	width: 1000px;
	height: auto;
	margin-left: auto;
	margin-right: auto;
}

#controls
{
	width: 180px;
	float: left;
}

#messages
{
	clear: both;
	margin-left: auto;
	margin-right: auto;
	text-align: center;
}

.title_choose
{
	width: 220px;
	float: left;
}


#choose_groups img
{
    margin-left: 10px;
}

#wrapper td
{
    vertical-align: top;
}

#more_option
{
    margin-left: -800px;
}

#more_option > div
{
    height: 400px;
    overflow-y: scroll;
}

.selected_item /* <span> in more_options */
{
    font-weight: bold;
}

.input_number
{
	width: 40px;
}
</style>
<script language="javascript" src="/HTML/library/jquery.js"></script>
<script src="/HTML/library/jquery-ui.js"></script>
<script src="/HTML/library/jquery.ajaxq.js"></script>
<link rel="stylesheet" href="/HTML/library/jquery-ui.css">
<script language="javascript">
var count=0;
var goal=0;
var interval_max=0, interval_min=0;
var titleList=[];
var userGroups = [];
var expiry=<?php echo $_SESSION['expiry']; ?>;
var token='<?php echo $_SESSION['access_token']; ?>';
var uid='<?php echo $result['id']; ?>';
var busy_img = '<img src="images/fb_busy.gif"></img>';

function init()
{
    $('#more_option').accordion({
        autoWidth: false, 
        autoHeight: false
    });
	window.setInterval(function(){
        $("#nExpiry").html(expiry.toString());
        expiry--;
    }, 1000);
    get_info();
	getTitles("texts.php?action=list_titles");
    getGroups();
    $('#selectAll').on('click', function(e){
    	$('.title_choose :checkbox').attr("checked", true);
        $('.title_choose span').addClass('selected_item');
    });
    $('#selectNone').on('click', function(e){
    	$('.title_choose :checkbox').attr("checked", false);
        $('.title_choose span').removeClass('selected_item');
    });
}

function getTitles(filename)
{
	$.ajaxq('q_main', {
        url: filename, 
        type: 'GET', 
        dataType: 'json', 
        success: function(textgroups, status, xhr){
            for(var i=0;i<textgroups.length;i++)
            {
                var titles_div = $('.title_choose');
                var title_text=textgroups[i]['title'];
                titles_div.eq(i%titles_div.length).append("<input type=\"checkbox\" value=\""+
                    title_text+"\" /><span>"+title_text+"</span><br />\n");
            }
            for(var n in titleList)
            {
                var curTitle = $(".title_choose input[value=\""+titleList[n]+"\"]");
                curTitle.attr("checked", true);
                curTitle.next().addClass('selected_item');
            }
            $('.title_choose :checkbox').click(function(e){
                $(this).next().toggleClass('selected_item');
                return parseTitles();
            });
        }
    });
}

function getGroups()
{
    $.ajaxq('q_main', {
        url: 'groups.php', 
        data: {action: 'get_groups', access_token: token}, 
        type: 'POST', 
        dataType: 'json', 
        success: function(response, status, xhr){
            for(var i = 0;i < response.length;i++)
            {
                var value = 'g_' + response[i].gid;
                $('#choose_groups').append('<input type="checkbox" value="'+value+'" id="'+value+'"><span>' + response[i].name + '</span><span></span><br>');
                $('#' + value).on('click', function(e){
                    $(this).next().toggleClass('selected_item');
                    if($(this).attr('checked'))
                    {
                        var $statusText = $(this).next().next();
                        $statusText.html(busy_img);
                        $.post('groups.php', { action: 'get_group_info', gid: this.id, access_token: token }, function(response, status, xhr){
                            $statusText.html('');
                        }, 'json');
                    }
                });
            }
            var gids = userGroups.split('_');
            for(var i = 0;i < gids.length;i++)
            {
                $('#g_'+gids[i]).attr('checked', true);
            }
            // make labels of checked items bold
            $('#choose_groups :checkbox').each(function(index, element){
                if($(this).attr('checked'))
                {
                    $(this).next().addClass('selected_item');
                }
            });
        }
    });
}

function parseOptions(selector, msg)
{
	var checkedItems = $(selector + ":checked");
	var outputObj = [];
	var n=checkedItems.length;
	if(n==0)
	{
		alert(msg);
		return [];
	}
	for(var i=0;i<n;i++)
	{
		outputObj.push(checkedItems[i].value);
	}
	return outputObj;
}

function parseTitles()
{
    titleList = parseOptions('.title_choose :checkbox', '至少要選一個標題！');
    return (titleList.length > 0);
}

function getParams()
{
	if(!parseTitles())
	{
		return false;
	}
	interval_max=parseInt($("#interval_max").val());
	interval_min=parseInt($("#interval_min").val());
	count=parseInt($("#count").html());
	goal=parseInt($("#goal").val());
	if(isNaN(interval_max)||isNaN(interval_min)||isNaN(count))
	{
		alert("所有欄位皆須輸入數字");
		interval_max=interval_min=count=0;
		return false;
	}
	if(interval_min<0||interval_max<0)
	{
		interval_min=interval_max=-1;
		alert("請輸入零秒以上！");
		return false;
	}
	return true;
}

function start2()
{
    add_user();
	$("#status").html("代洗中");
	$("#btnStart").attr("disabled", true);
	$("#btnStop").attr("disabled", false);
}

function stop2()
{
    $.post("users.php?action=set_user_status", {"uid":uid, "status": "stopped"});

	$("#status").html("未開始發文");
	$("#btnStart").attr("disabled", false);
	$("#btnStop").attr("disabled", true);
}

function add_user()
{
    if(!getParams())
    {
        return;
    }
	$.post("users.php?action=add_user", 
	{
		"interval_max":interval_max, 
		"interval_min":interval_min, 
		"titles":JSON.stringify(titleList), 
		"access_token":token, 
		"goal":goal
	});
}

function get_info(bSetTitles)
{
	$.ajaxq('q_main', {
        url: "users.php?action=get_user_info", 
        data: {"uid":uid}, 
        type: 'POST', 
        dataType: 'json', 
        success: function(response, status, xhr){
            var msg="";
            if(response["query_result"]=="user_found")
            {
                userGroups = response['groups'];
                titleList = JSON.parse(response['titles']);
                if(response["status"]=="started")
                {
                    $("#status").html("代洗中");
                    $("#btnStart").attr("disabled", true);
                    $("#btnStop").attr("disabled", false);
                }
                $("#interval_max").val(response["interval_max"]);
                $("#interval_min").val(response["interval_min"]);
                $("#count").html(response["count"]);
                $("#goal").val(response["goal"]);

                goal=response["goal"];
                count=goal-response["count"];

                setTimeout(function(){ get_info(); }, 30*1000);
            }
	    }
    });
}

</script>
</head>
<body onload="init();">
	<table id="wrapper">
        <tr>
            <td id="controls">
                <fieldset id="parameters">
                    <legend>設定</legend>
                    時間間隔上限: <input type="text" id="interval_max" class="input_number" maxlength="5" value="100"/><br />
                    時間間隔下限: <input type="text" id="interval_min" class="input_number" maxlength="5" value="80"/><br />
                    發文次數: <input type="text" id="goal" class="input_number" maxlength="7" value="2147483647"/><br />
                    已發文數：<span id="count">0</span><br />
                    <input type="button" value="開始" id="btnStart" onclick="start2();" />
                    <input type="button" value="停止" id="btnStop" onclick="stop2();" disabled="disabled"/>
                    <input type="button" value="更新資料" onclick="add_user();" /><br />
                </fieldset>
                <fieldset id="information">
                    <legend>洗版資訊</legend>
                    狀態：<span id="status">未開始發文</span><br />
                    授權碼將在<span id="nExpiry">0</span>秒後過期<br />
                </fieldset>
                <a href="./addText.php">增加留言內容</a><br />
            </td>
            <td>
                <div id="more_option">
                    <h3>選社團</h3>
                    <div id="choose_groups">
                    </div>
                    <h3>選留言內容</h3>
                    <div>
                        <input type="button" value="全選" id="selectAll">
                        <input type="button" value="全部不選" id="selectNone"><br />
                        <div class="title_choose"></div>
                        <div class="title_choose"></div>
                        <div class="title_choose"></div>
                    </div>
                </div>
            </td>
        </tr>
	</table>
</body>
</html>
