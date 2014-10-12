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

#users td
{
    border: 1px solid black;
}

button
{
    margin: 10px;
    width: 200px;
}

.left
{
    float: left;
    padding-left: 5px;
}

#results
{
    width: 100%;
    height: 300px;
}
/*
 * Reference: http://stackoverflow.com/questions/6471268
 */
#wrapper
{
    overflow: hidden;
    padding-right: 15px;
    padding-left: 5px;
}

#buttons-wrapper
{
    text-align: center;
    margin: 0 auto;
}
</style>
<?php
require '../common_inc.php';
External::setRelativePath('..');
echo External::loadJsCss('phpjs');
?>
<script>
var users={};
var errors=[];
var debug = false;
var pause = false;
var b_slow_stop = false;
var conn = null; // websocket connection
var wsQueue = [];

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
    for(var i = 0; i < uids.length; i++)
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

function handleServerError(uids, status, error, xhr, extraInfo)
{
    if(error)
    {
        $("#results").append(error + "\n");
    }
    else
    {
        $("#results").append("Failed to post.\n");
    }
    var errObj = {
        'time': timestr(), 
        'status': status, 
        'error': error, 
        'uids': uids
    };
    if(xhr && xhr.responseText) // undefined if timeout
    {
        errObj.responseText = xhr.responseText;
    }
    if(typeof extraInfo !== 'undefined')
    {
        errObj.extraInfo = extraInfo;
    }
    window.errors.push(errObj);
    if(xhr && xhr.status == 500)
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
    for(var i = 0; i < uids.length; i++)
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
    wsQueue.push([ _data, realPostUids ]);
    flushWsQueue();
}

function flushWsQueue()
{
    if(window.debug)
    {
        console.log(wsQueue);
    }
    if(wsQueue.length != 0)
    {
        var current = wsQueue.shift();
        if(window.debug)
        {
            console.log(current);
        }
        sendWebsocketRequest.apply(this, current);
    }
}

// choose from single user and multiple user
function handlePostWrapper(response)
{
    for(var uid in response)
    {
        handlePost(uid, response[uid]);
    }
}

function sendWebsocketRequest(_data, realPostUids)
{
    if(!conn)
    {
        conn = new WebSocket('ws://localhost:23456/');
    }
    conn.onmessage = function (msg) {
        var response = null;
        try
        {
            response = JSON.parse(msg.data)
            if(!$.isPlainObject(response))
            {
                throw new Error("Invalid data returned from server");
            }
        }
        catch(e)
        {
            handleServerError(realPostUids, e.message, msg.data, null);
            return;
        }
        if(window.debug)
        {
            console.log(response);
        }
        handlePostWrapper(response);
        flushWsQueue();
    };
    conn.onerror = function (ev) {
        handleServerError(realPostUids, conn.readyState, ev.reason);
    };
    var sendDataImpl = function() {
        conn.send(JSON.stringify(_data));
    };
    if(conn.readyState != 1)
    {
        conn.onopen = sendDataImpl;
    }
    else
    {
        sendDataImpl();
    }
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
            window.errors.push(response);
            $('#results').append('無法取得使用者名單：' + response.error + '\n');
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
            if(!b_slow_stop)
            {
                postUids.push(uid);
            }
            else
            {
                users[uid].bStarted = false;
            }
        }
    }
    if(!b_slow_stop)
    {
        post2(postUids);
        var timestamp = new Date().getTime();
        timestamp = (timestamp - timestamp%1000)/1000;
        if(timestamp%30 == 0 && someoneStarted)
        {
            update_userList();
        }
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

function slow_stop()
{
    b_slow_stop = true;
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
        console.log('%c' + i + '%c' + errors[i].time +'%c' + errors[i].error, 
                    'color: gray', 'color: green', 'color: black');
    }
}

$(document).on('ready', function(e){
    var ids = [ '#btn_start', '#btn_stop', '#btn_slow_stop', '#btn_pause', '#btn_clearLog', '#btn_print_error' ];
    var functions = [ update_userList, stop, slow_stop, pause_or_resume, clearLog, printError ];
    for(var i = 0; i < ids.length; i++)
    {
        $(ids[i]).on('click', functions[i]);
    }
    $("#chk_debug").on('click', function(e){
        window.debug = $('#chk_debug').is(':checked');
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
<div class="left">
    <table id="users">
    <tbody>
    <tr>
    <td>n</td><td>Name</td><td>Time</td><td>Last Message</td><td>Group</td>
    </tr>
    </tbody>
    </table>
</div>
<div id="wrapper">
    <textarea id="results" readonly="readonly" cols="50" rows="20"></textarea>
    <div id="buttons-wrapper">
        <button id="btn_start">Start</button>
        <button id="btn_stop">Stop</button>
        <button id="btn_slow_stop">Slow Stop</button>
        <button id="btn_pause">Pause</button>
        <button id="btn_clearLog">clear log</button>
        <button id="btn_print_error">Print errors</button>
    </div>
    <input type="checkbox" id="chk_debug">Debug<br>
    <a href="sql.php">execute SQL</a>
    <a href="table.php">Tables</a>
    <a href="text_mgr.php">Text Manager</a><br>
</div>
</body>
</html>

