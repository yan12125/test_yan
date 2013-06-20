<?php
require '../common_inc.php';
External::setRelativePath('..');

session_start();

if(!isset($_SESSION['access_token']))
{
    Header('Location: '.Config::getParam('rootUrl').'ui/auth.php');
    exit(0);
}

Util::redirectHttps();
?>
<!DOCTYPE html>
<html>
<head>
<title>挑戰留言2147483647</title>
<meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />
<link rel="stylesheet" href="index.css">
<?php
echo External::loadJsCss('jquery-ui', 'validate');
?>
<script language="javascript">
var busy_img = '<img src="../images/fb_busy.gif"></img>';

$(document).on('ready', function(e){
    // basic data
    $('#token').val('<?php echo $_SESSION['access_token']; ?>');
    callWrapper('get_basic_data', { access_token: $('#token').val() }, function(data){
        if(typeof data.error != 'undefined')
        {
            alert(data.error);
            location.href = 'auth.php';
        }
        document.title = data.name;
        $('#uid').val(data.uid);
        window.userGroups = data.groups;
        get_info(true); // first time getting info, update all information
        // expiry
        var expiry = data.expiry;
        window.setInterval(function(){
            $("#nExpiry").html(expiry.toString());
            expiry--;
        }, 1000);
        setInterval(function(){ get_info(false); }, 30*1000); // after that, only update post count
    });

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
        selectRandom(0.5);
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
        logout();
    })
});

function logout()
{
    callWrapper('logout', function(response){
        location.href = response.url;
    });
}

function getTitles(userTitles)
{
	callWrapper('list_titles', function(textgroups){
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
            callWrapper('get_group_info', { gid: this.value, access_token: $('#token').val() }, function(response){
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
            });
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
    add_user(function(){
    	$("#status").html("代洗中");
	    $("#btnStart").attr("disabled", true);
    	$("#btnStop").attr("disabled", false);
    });
}

function stop2()
{
    callWrapper('set_user_status', {
        "uid": $('#uid').val(), 
        "status": "stopped", 
        "access_token": $('#token').val()
    }, function(response){
        if(response.status == 'stopped')
        {
            $("#status").html("未開始發文");
            $("#btnStart").attr("disabled", false);
            $("#btnStop").attr("disabled", true);
        }
        else
        {
            alert('無法停止，有bug!\n按「更新資料」後再試一次');
        }
    });
}

function add_user(cb)
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
	callWrapper("add_user", {
		"interval_max": $('input[name="interval_max"]').val(), 
		"interval_min": $('input[name="interval_min"]').val(), 
		"titles": JSON.stringify($.makeArray(titleList)), 
        "groups": $.makeArray(userGroups).join('_'), 
		"access_token": $('#token').val(), 
		"goal": $('input[name="goal"]').val()
	}, function(){
        if(typeof cb == 'function')
        {
            cb();
        }
    });
}

function get_info(initial)
{
	callWrapper("get_user_info", { "uid": $('#uid').val() }, function(response){
        var msg="";
        if(typeof response["error"] != "undefined")
        {
            var err = response['error'];
            if(err == 'user_not_found')
            {
                // default setting for new users
                getTitles([]); // no titles selected
                callWrapper('get_primary_group', function(response){
                    parseGroups(window.userGroups, response.primary_group);
                });
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
            parseGroups(window.userGroups, response['groups']);
        }
        $("#count").html(response["count"]);
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
            <input type="hidden" id="uid" value="">
            <input type="hidden" id="token" value="">
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
        <a href="./addText.php" target="_blank">增加留言內容</a><br />
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
