<!DOCTYPE html>
<html>
<head>
<title>alternate.php</title>
<meta charset="UTF-8">
<base target="_blank">
<style>
#users
{
    border-collapse: collapse;
}
</style>
<?php
require '../common_inc.php';
External::setRelativePath('..');
echo External::loadJsCss('sprintf');
?>
<script>
var users={};
var errors=[];
var debug = false;
var pause = false;

function handlePost(uid, response)
{
    if(response.error)
    {
        handlePostError([ uid ], response);
    }
    else
    {
        users[uid].last_msg = response.msg;
        users[uid].group = response.group;
        users[uid].wait_time = parseFloat(response.next_wait_time);

        // update user data
        for(var s in response["user_data"])
        {
            users[uid][s]=response["user_data"][s];
        }
        if(response["user_data"]["status"]!="started")
        {
            users[uid].bStarted=false;
        }
    }
    users[uid].posting = false;
}

function handlePostError(uids, response)
{
    window.errors.push(response);
    var involvedUsers = [];
    var hasNewStatus = false;
    for(var i in uids)
    {
        var uid = uids[i];
        if(response.new_status)
        {   // no new status when "Timed out"
            $("#results").append(response.error + "\n");
            users[uid]['status'] = response.new_status;
            users[uid].bStarted = false; // "started" won't appear here
            hasNewStatus = true;
        }
        involvedUsers.push(users[uid].name);
        if(response[uid])
        {
            users[uid].wait_time = response[uid].next_wait_time;
        }
        else
        {
            users[uid].wait_time = response.next_wait_time;
        }
        users[uid].posting = false;
    }
    if(!hasNewStatus)
    {
        $('#results').append('Error from ' + involvedUsers.join(', ') + "\n");
    }
}

function handleServerError(uids, status, error, xhr)
{
    $("#results").append(error + "\n");
    var errObj = {
        'time': timestr(), 
        'status': status, 
        'error': error, 
        'uids': uids
    };
    if(xhr.responseText) // undefined if timeout
    {
        errObj.responseText = xhr.responseText;
    }
    window.errors.push(errObj);
    if(xhr.status == 500)
    {
        // postpone all "running" users
        for(var _uid in users)
        {
            if(users[_uid].bStarted)
            {
                users[_uid].wait_time = 300;
            }
        }
    }
    for(var i in uids)
    {
        var uid = uids[i];
        users[uid].wait_time=300;
        users[uid].posting = false;
    }
}

function post2(uids)
{
    if(uids.length == 0)
    {
        return;
    }
    var realPostUids = [];
    for(var i = 0;i < uids.length;i++)
    {
        if(users[uids[i]].bStarted)
        {
            realPostUids.push(uids[i]);
            users[uids[i]].posting = true;
        }
    }

    var _data = {
        action: 'post_uids', 
        uids: realPostUids.join('_'), 
        truncated_msg: 1 
    };
    if(debug)
    {
        _data["debug"] = 1;
    }
    $.ajaxq("queue_main", {
        url: "../wrapper.php", 
        type: "POST", 
        data: _data, 
        dataType: "json", 
        timeout: 300000, // theoretically no need, but sometimes starving occurs
        success: function(response, status, xhr)
        {
            if(realPostUids.length == 1)
            {
                handlePost(realPostUids[0], response);
            }
            else
            {
                if(response.error)
                {
                    handlePostError(realPostUids, response);
                    return;
                }
                for(var i in realPostUids)
                {
                    var uid = realPostUids[i];
                    handlePost(uid, response[uid]);
                }
            }
        }, 
        error: function(xhr, status, error)
        {
            handleServerError(realPostUids, status, error, xhr);
        }
    });
}

function update_userList()
{
    var curIDs=[];
    for(var uid in users)
    {
        curIDs.push(uid);
    }
    callWrapper("list_users", { "IDs": curIDs.join('_') }, function(response){
        if(response.error)
        {
            alert('無法取得使用者名單：' + response.error);
            return;
        }
        for(var i = 0;i < response.length;i++)
        {
            var uid = response[i].uid;
            if(!response[i].name) // not new users, only process status
            {
                users[uid].status = response[i].status;
                if(response[i].status=="started"&&users[uid].bStarted==false)
                {
                    users[uid].bStarted=true;
                }
                if(response[i].status=="stopped"&&users[uid].bStarted==true)
                {
                    users[uid].bStarted=false;
                }
            }
            else
            {
                users[uid] = response[i];
                add_user(users, uid);
            }
        }
        // update N
        var rows = get_user_rows();
        for(var i = 0;i < rows.length;i++)
        {
            rows.eq(i).find("td:first").text(i);
        }
    });
}

function add_user(_users, uid)
{
    var user_data=_users[uid];
    user_data.bStarted=false;
    user_data.wait_time=0;
    $("table#users tbody").append("<tr id=\"u_"+user_data.uid+"\"></tr>");
    $("tr#u_"+user_data.uid).append("<td></td><td class=\"name\"></td><td class=\"time\">0</td><td class=\"last_msg\"></td><td class=\"group\"></td>");
    update_user_data(_users[uid]);
}

function update_user_data(user_data)
{
    var row = $("tr#u_"+user_data.uid);
    row.find(".name").html(escapeHtml(user_data.name));
    if(user_data.group)
    {
        row.find('.group').text(user_data.group);
    }
    if(user_data.last_msg)
    {
        row.find('.last_msg').text(user_data.last_msg);
    }
}

function get_user_rows()
{
    return $("tr").filter(function(){return /^u_\d+$/.test(this.id);});
}

function get_user(n)
{
    return users[get_user_rows()[n].id.substr(2)]; // id is u_xxxx...xxxx
}

function mainLoop()
{
    if(pause)
    {
        return;
    }
    var someoneStarted = false;
    var postUids = [];
    for(var uid in users)
    {
        var user = users[uid];
        update_user_data(user);
        if(!user.bStarted || user.posting)
        {
            continue;
        }
        someoneStarted = true;
        if(user.wait_time > 0)
        {
            user.wait_time--;
            var countdownValue = Math.ceil(user.wait_time).toString();
            $("tr#u_"+uid+" td.time").text(countdownValue);
        }
        else if(users[uid].wait_time <= 0)
        {
            postUids.push(uid);
        }
    }
    post2(postUids);

    var timestamp = new Date().getTime();
    timestamp = (timestamp - timestamp%1000)/1000;
    if(timestamp%30 == 0 && someoneStarted)
    {
        update_userList();
    }
}

function stop()
{
    for(var uid in users)
    {
        users[uid].bStarted = false;
        users[uid].wait_time = 0;
        $('#users td.time').text('0');
    }
}

function pause_or_resume()
{
    $('#btn_pause').val(window.pause?'Pause':'Resume');
    window.pause = !window.pause;
}

function clearLog()
{
    $("#results").text('');
}

function printError()
{
    for(var i = 0;i < errors.length;i++)
    {
        // https://developers.google.com/chrome-developer-tools/docs/console-api
        console.log('%c' + i + '\t%c' + errors[i].time +'    %c' + errors[i].error, 
                    'color: gray', 'color: green', 'color: black');
    }
}

$(document).on('ready', function(e){
    var ids = [ '#btn_start', '#btn_stop', '#btn_pause', '#btn_clearLog', '#btn_print_error' ];
    var functions = [ update_userList, stop, pause_or_resume, clearLog, printError ];
    for(var i = 0; i < ids.length; i++)
    {
        $(ids[i]).on('click', functions[i]);
    }
    $("#chk_debug").on('click', function(e){
        window.debug = ($('#chk_debug').attr('checked') === 'checked');
    });
    // confirm when closing page
    // http://stackoverflow.com/questions/7080269
    $(window).on('beforeunload', function(e){
        var still_running = false;
        for(var uid in users)
        {
            if(users[uid].bStarted)
            {
                still_running = true;
            }
        }
        if(still_running)
        {
            return 'Leaving this page makes spammer stop working.';
        }
    });
    update_userList();
    setInterval(function(){ mainLoop(); }, 1000);
});
</script>
</head>
<body>
<table border="0">
    <tr>
        <td>
            <table id="users" border="1">
            <tbody>
            <tr>
            <td>n</td><td>Name</td><td>Time</td><td>Last Message</td><td>Group</td>
            </tr>
            </tbody>
            </table>
        </td>
        <td align="left" valign="top">
            <textarea id="results" style="width:100%; height:100px" readonly="readonly" rows="10" cols="20"></textarea><br>
            <input type="button" id="btn_start" value="Start">
            <input type="button" id="btn_stop" value="Stop">
            <input type="button" id="btn_pause" value="Pause"><br>
            <input type="button" id="btn_clearLog" value="clear log">
            <input type="button" id="btn_print_error" value="Print errors"><br>
            <input type="checkbox" id="chk_debug">Debug<br>
            <a href="sql.php">execute SQL</a>
            <a href="table.php">Tables</a>
            <a href="text_mgr.php">Text Manager</a><br>
        </td>
    </tr>
</table>
</body>
</html>

