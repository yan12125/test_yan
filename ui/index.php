<?php
require '../common_inc.php';
Fb::login();
?>
<!DOCTYPE html>
<html>
<head>
<title>挑戰留言2147483647</title>
<meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />
<link rel="stylesheet" href="index.css">
<?php
External::setRelativePath('..');
echo External::loadJsCss('jquery-ui', 'validate', 'phpjs');
?>
<base target="_blank">
<script language="javascript">
$(document).on('ready', function(e){
    // basic data
    getAccessToken(function(response){
        $('#token').val(response.access_token);
        $('#text_mgr').attr('href', './text_mgr.php?access_token=' + response.access_token);
        callWrapper('get_basic_data', { access_token: $('#token').val() }, function(data){
            if(typeof data.error != 'undefined')
            {
                alert(data.error);
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
            $('input[name="contact"]').autocomplete({
                source: function (request, response) {
                    searchGroupAutoComplete(request.term, response);
                }
            });
        });
    }, true); // longterm token required

    // loading groups...
    $('#choose_groups').setBusy(true);
    // three buttons below are in title selection area
    var selectRandom = function(probability){
        var checkboxes = $('.title_choose :checkbox');
        var titleTexts = $('.title_choose a');
        checkboxes.attr("checked", false);
        titleTexts.removeClass('selected_item');
        for(var i = 0;i < checkboxes.length;i++)
        {
            if(Math.random() <= probability)
            {
                if(titleTexts.eq(i).hasClass('disabled_title'))
                {
                    continue;
                }
                checkboxes.eq(i).attr('checked', true);
                titleTexts.eq(i).addClass('selected_item');
            }
        }
    };
    $('#btnStart').on('click', function(e){ start2(); });
    $('#btnStop').on('click', function(e){ stop2(); });
    $('#addUser').on('click', function(e){ add_user(); });
    $('#selectAll').on('click', function(e){ selectRandom(1); });
    $('#selectRandom').on('click', function(e){ selectRandom(0.5); });
    $('#selectNone').on('click', function(e){ selectRandom(0); });

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

    $('#more_option > div').accordion({
        autoWidth: false, 
        autoHeight: false
    });

    $('#logout').on('click', function(e){
        callWrapper('logout', { access_token: $('#token').val() }, function(response){
            top.location.href = response.url;
        });
    })
});

function getTitles(userTitles)
{
    callWrapper('list_titles', function(data){
        var titles = data.titles, lines = data.lines, 
            locked = data.locked, handlers = data.handlers;
        // if error 'user_not_found' repeatedly occurs (unregistered users)
        // this prevents duplicated titles
        var titles_div = $('.title_choose');
        titles_div.html('');
        for(var i = 0; i < titles.length; i++)
        {
            var disabled = false;
            if(locked[i] == 1 || lines[i] <= 0)
            {
                disabled = true;
            }

            var title_text = titles[i];
            var textUrl = './text_mgr.php?title='+encodeURIComponent(title_text)+'&access_token='+$('#token').val();
            var itemContent = '<input type="checkbox" value="'+title_text+'">' + 
                              '<a href="'+textUrl+'">'+title_text+'</a>';
            if(handlers[i] != null)
            {
                itemContent += ' (<span class="handlerText">'+handlers[i]+'</span>)';
            }
            itemContent += ' ('+lines[i]+')<br>\n';
            titles_div.eq(i%titles_div.length).append(itemContent);
            var curTitleElement = titles_div.find('input[value="'+title_text+'"]');
            if(disabled)
            {
                curTitleElement.attr('disabled', true)
                    .next().addClass('disabled_title');
            }
            if(!disabled && $.inArray(title_text, userTitles) > -1)
            {
                curTitleElement.attr("checked", true)
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
    $('#choose_groups').setBusy(false);
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
            $statusText.removeClass('error').setBusy(true);
            callWrapper('get_group_info', { gid: this.value, access_token: $('#token').val() }, function(response){
                $statusText.setBusy(false);
                if(response.error)
                {
                    $statusText.addClass('error').html('無法讀取社團內容: '+response.error);
                    var gid = this.data.split('&')[1].split('=')[1];
                    $('#choose_groups input[value="'+gid+'"]').attr('checked', false) // revert selection
                        .next().removeClass('selected_item');
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
    var titleList = $('.title_choose :checkbox:checked').map(function(i, e){
        return $(e).val();
    });
    if(titleList.length == 0)
    {
        alert('至少要選一像留言內容！');
        return;
    }
    var userGroups = $('#choose_groups :checkbox:checked').map(function(i, e){
        return $(e).val();
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
        "goal": $('input[name="goal"]').val(), 
        "contact": $('input[name="contact"]').val()
    }, function(response) {
        // to update last_count
        if(typeof response.error != 'undefined')
        {
            alert("Adding user failed: " + response.error);
            return;
        }
        get_info(true);
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
        if(response.error)
        {
            var err = response.error;
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

        if(response.status == "started")
        {
            $("#status").html("代洗中");
            $("#btnStart").attr("disabled", true);
            $("#btnStop").attr("disabled", false);
        }
        if(initial)
        {
            $('input[name="interval_max"]').val(response.interval_max);
            $('input[name="interval_min"]').val(response.interval_min);
            $('input[name="goal"]').val(response.goal);
            $('input[name="contact"]').val(response.contact);
            $('#last_count').text(response.last_count);
            getTitles(JSON.parse(response.titles));
            parseGroups(window.userGroups, response.groups);
        }
        $("#count").html(response.count);
    });
}

function searchGroupAutoComplete(term, callback)
{
    callWrapper("search_name_in_group", {
        gid: "198971170174405", 
        name: term, 
        access_token: $('#token').val()
    }, function (data) {
        for(var i = 0; i < data.length; i++)
        {
            data[i].label = data[i].name;
            data[i].value = data[i].uid;
        }
        callback(data);
    });
}
</script>
</head>
<body>
<div id="fb-root"></div>
<table id="wrapper">
<tr>
<td>
    <form id="parameters" onsubmit="return false;">
        <fieldset>
            <legend>設定</legend>
            <input type="hidden" id="uid" value="">
            <input type="hidden" id="token" value="">
            時間間隔上限: <input type="text" name="interval_max" class="input_field" value="100"/><br />
            時間間隔下限: <input type="text" name="interval_min" class="input_field" value="80"/><br />
            發文次數: <input type="text" name="goal" class="input_field" value="2147483647"/><br />
            聯絡人：<input type="text" name="contact" class="input_field" /><br />
            已發文數：<span id="count">0</span><br />
            上次留言數：<span id="last_count"></span><br>
            <input type="button" value="開始" id="btnStart">
            <input type="button" value="停止" id="btnStop" disabled="disabled">
            <input type="button" value="更新資料" id="addUser"><br>
        </fieldset>
        <fieldset id="information">
            <legend>洗版資訊</legend>
            狀態：<span id="status">未開始發文</span><br />
            授權碼將在<span id="nExpiry">0</span>秒後過期<br />
        </fieldset>
        <a href="./table.php">統計資料</a><br>
        <a id="text_mgr" href="./text_mgr.php?login=true">洗版內容一覽</a><br>
        <a href="https://github.com/yan12125/test_yan/">程式原始碼</a>
        <input type="button" id="logout" value="登出Facebook"><br>
    </form>
</td>
<td id="more_option">
    <div>
        <h3>選留言內容</h3>
        <div class="accordian_tab">
            <input type="button" value="全選" id="selectAll">
            <input type="button" value="隨便選" id="selectRandom">
            <input type="button" value="全部不選" id="selectNone">
            欲知詳細內容，請點標題。括號中的數字為內容的數目<br />
            <div class="title_choose"></div>
            <div class="title_choose"></div>
        </div>
        <h3>選社團</h3>
        <div class="accordian_tab">
            <form id="choose_groups"></form>
        </div>
    </div>
</td>
</tr>
</table>
</body>
</html>
