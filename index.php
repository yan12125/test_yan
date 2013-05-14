<?php
session_start();
require 'common_inc.php';

redirectHttps();

if(!isset($_SESSION['access_token']) || !isset($_SESSION['expiry']))
{
    Header('Location: '.Config::getParam('rootUrl').'auth.php');
    exit(0);
}

// get basic user information from facebook
try
{
    $result=Fb::api('/me', array('access_token'=>$_SESSION['access_token']));
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
	width: 80px;
}

/* indicates failure in jquery.validate */
.error
{
    color: red;
}

label.error,span.error
{
    margin-left: 10px;
}
</style>
<script language="javascript" src="/HTML/library/jquery.js"></script>
<script src="/HTML/library/jquery-ui.js"></script>
<script src="/HTML/library/jquery.ajaxq.js"></script>
<script src="/HTML/library/jquery.validate.js"></script>
<script src="/HTML/library/jquery.validate.message_zh_TW.js"></script>
<link rel="stylesheet" href="/HTML/library/jquery-ui.css">
<script language="javascript">
var token='<?php echo $_SESSION['access_token']; ?>';
var uid='<?php echo $result['id']; ?>';
var primary_group = '<?php echo Groups::primary_group; ?>';
var busy_img = '<img src="images/fb_busy.gif"></img>';

$(document).on('ready', function(e){
    var expiry=<?php echo $_SESSION['expiry']; ?>;
	window.setInterval(function(){
        $("#nExpiry").html(expiry.toString());
        expiry--;
    }, 1000);
    get_info(true); // first time getting info, update all information
    setInterval(function(){ get_info(false); }, 30*1000); // after that, only update post count

    // loading groups...
    $('#choose_groups').html(busy_img);
    // three buttons below are in title selection area
    var selectRandom = function(probability){
        var checkboxes = $('.title_choose :checkbox');
        var titleTexts = $('.title_choose span');
    	checkboxes.attr("checked", false);
        titleTexts.removeClass('selected_item');
        for(var i = 0;i < checkboxes.length;i++)
        {
            if(Math.random() <= probability)
            {
                checkboxes.eq(i).attr('checked', true);
                titleTexts.eq(i).addClass('selected_item');
            }
        }
    };
    $('#selectAll').on('click', function(e){
        selectRandom(1);
    });
    $('#selectRandom').on('click', function(e){
        selectRandom(0.3);
    });
    $('#selectNone').on('click', function(e){
        selectRandom(0);
    });

    // initialize validation
    var common_rule = {
        required: true, 
        digits: true, 
        min: 1
    };
    $('#parameters').validate({
        rules: {
            interval_min: common_rule, 
            interval_max: common_rule, 
            goal: common_rule
        }
    });

    $('#more_option').accordion({
        autoWidth: false, 
        autoHeight: false
    });

    $('#logout').on('click', function(e){
        $.post('users.php', { action: 'logout' }, function(response, status, xhr){
            location.href = response.url;
        });
    })
});

function getTitles(userTitles)
{
	$.ajaxq('q_main', {
        url: "texts.php", 
        type: 'POST', 
        data: { action: 'list_titles' }, 
        dataType: 'json', 
        success: function(textgroups, status, xhr){
            for(var i=0;i<textgroups.length;i++)
            {
                var titles_div = $('.title_choose');
                var title_text=textgroups[i]['title'];
                titles_div.eq(i%titles_div.length).append("<input type=\"checkbox\" value=\""+
                    title_text+"\" /><span>"+title_text+"</span><br />\n");
                if($.inArray(title_text, userTitles) > -1)
                {
                    titles_div.find("input[value=\""+title_text+"\"]")
                        .attr("checked", true)
                        .next().addClass('selected_item');
                }
            }
            $('.title_choose :checkbox').click(function(e){
                $(this).next().toggleClass('selected_item');
            });
        }
    });
}

function getGroups(userGroups)
{
    $.ajaxq('q_main', {
        url: 'groups.php', 
        data: {action: 'get_groups', access_token: token}, 
        type: 'POST', 
        dataType: 'json', 
        success: function(response, status, xhr){
            parseGroups(response, userGroups);
        }
    });
}

function parseGroups(allGroups, userGroups)
{
    $('#choose_groups').html('');
    for(var i = 0;i < allGroups.length;i++)
    {
        var gid = allGroups[i].gid;
        $('#choose_groups').append('<input type="checkbox" value="'+gid+'"><span>' + allGroups[i].name + '</span><span></span><br>');
        $('#choose_groups input[value="' + gid + '"]').on('click', function(e){
            $(this).next().toggleClass('selected_item');
            if(!$(this).attr('checked'))
            {
                return;
            }
            // retrieve group feed from facebook
            var $statusText = $(this).next().next();
            $statusText.removeClass('error').html(busy_img);
            $.post('groups.php', { action: 'get_group_info', gid: this.value, access_token: token }, function(response, status, xhr){
                if(typeof response['error'] != 'undefined')
                {
                    $statusText.addClass('error').html('無法讀取社團內容: '+response['error']);
                    var gid = this.data.split('&')[1].split('=')[1];
                    $('#choose_groups input[value="'+gid+'"]').attr('checked', false) // revert selection
                        .next().removeClass('selected_item');
                }
                else
                {
                    $statusText.html('');
                }
            }, 'json');
        });
    }
    var gids = userGroups.split('_');
    for(var i = 0;i < gids.length;i++)
    {
        $('#choose_groups input[value="'+gids[i]+'"]').attr('checked', true)
            .next().addClass('selected_item'); // make selected items bold
    }
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
    $.post("users.php", {"action": "set_user_status", "uid":uid, "status": "stopped"});

	$("#status").html("未開始發文");
	$("#btnStart").attr("disabled", false);
	$("#btnStop").attr("disabled", true);
}

function add_user()
{
    var titleList = $('.title_choose :checkbox:checked').map(function(index, element){
        return $(element).val();
    });
    if(titleList.length == 0)
    {
        alert('至少要選一像留言內容！');
        return;
    }
    var userGroups = $('#choose_groups :checkbox:checked').map(function(index, element){
        return $(element).val();
    });
    if(userGroups.length == 0)
    {
        alert('至少要選一個社團！');
        return;
    }
	$.post("users.php", {
        "action": "add_user", 
		"interval_max": $('input[name="interval_max"]').val(), 
		"interval_min": $('input[name="interval_min"]').val(), 
		"titles": JSON.stringify($.makeArray(titleList)), 
        "groups": $.makeArray(userGroups).join('_'), 
		"access_token":token, 
		"goal": $('input[name="goal"]').val()
	});
}

function get_info(initial)
{
	$.ajaxq('q_main', {
        url: "users.php", 
        data: { "action": "get_user_info", "uid":uid }, 
        type: 'POST', 
        dataType: 'json', 
        success: function(response, status, xhr){
            var msg="";
            if(typeof response["error"] != "undefined")
            {
                var err = response['error'];
                if(err == 'user_not_found')
                {
                    // default setting for new users
                    getTitles([]); // no titles selected
                    getGroups(primary_group); // publish to 挑戰留言2147483647
                    return;
                }
                else
                {
                    alert('無法取得使用者資料，請稍候再試\n' + err);
                    console.log(err);
                    return;
                }
            }

            if(response["status"]=="started")
            {
                $("#status").html("代洗中");
                $("#btnStart").attr("disabled", true);
                $("#btnStop").attr("disabled", false);
            }
            if(initial)
            {
                $('input[name="interval_max"]').val(response["interval_max"]);
                $('input[name="interval_min"]').val(response["interval_min"]);
                $('input[name="goal"]').val(response["goal"]);
                getTitles(JSON.parse(response['titles']));
                getGroups(response['groups']);
            }
            $("#count").html(response["count"]);
	    }
    });
}

</script>
</head>
<body>
<table id="wrapper">
<tr>
<td id="controls">
    <form id="parameters" onsubmit="return false;">
        <fieldset>
            <legend>設定</legend>
            時間間隔上限: <input type="text" name="interval_max" class="input_number" value="100"/><br />
            時間間隔下限: <input type="text" name="interval_min" class="input_number" value="80"/><br />
            發文次數: <input type="text" name="goal" class="input_number" value="2147483647"/><br />
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
        <input type="button" id="logout" value="登出"><br>
    </form>
</td>
<td>
    <div id="more_option">
        <h3>選留言內容</h3>
        <div>
            <input type="button" value="全選" id="selectAll">
            <input type="button" value="隨便選" id="selectRandom">
            <input type="button" value="全部不選" id="selectNone"><br />
            <div class="title_choose"></div>
            <div class="title_choose"></div>
            <div class="title_choose"></div>
        </div>
        <h3>選社團</h3>
        <div><form id="choose_groups"></form></div>
    </div>
</td>
</tr>
</table>
</body>
</html>
