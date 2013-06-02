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
require 'common_inc.php';
echo External::loadJsCss('jquery', 'ajaxq');
?>
<script>
var users={};
var errors=[];
var debug = 0;

function post2(uid)
{
	if(!users[uid].bStarted)
	{
        return false;
    }

    var _data = { "uid":users[uid].uid, "truncated_msg": 1 };
    if(debug)
    {
        _data["debug"] = 1;
    }
    $.ajaxq("queue_main", {
        url: "post.php", 
        type: "POST", 
        data: _data, 
        dataType: "json", 
        timeout: 30*1000, // in milliseconds
        success: function(response, status, xhr)
        {
            if((typeof response["error"])!="undefined")
            {
                var err_msg=response["error"];
                if(typeof response["processed"] != "undefined")
                {
                    if(typeof response['new_status'] != "undefined")
                    {   // no new status when "Timed out"
                        $("#results").append(err_msg+"\n");
                        users[uid]['status'] = response["new_status"];
                        users[uid].bStarted = false; // "started" won't appear here
                    }
                }
                else
                {
                    $("#results").append("Error from "+users[uid].name+"\n");
                    window.errors.push(response);
                }
                users[uid].wait_time = response["next_wait_time"];
                if(users[uid].wait_time > 0)
                {
                    countDown(uid);
                }
            }
            else
            {
                var msg=response["msg"];
                $("tr#u_"+users[uid].uid+" td.last_msg").text(msg);
                $("tr#u_"+users[uid].uid+" td.group").text(response['group']);
                users[uid].wait_time=parseFloat(response["next_wait_time"]);
                countDown(uid);

                // update user data
                for(var s in response["user_data"])
                {
                    users[uid][s]=response["user_data"][s];
                }
                if(response["user_data"]["status"]!="started")
                {
                    users[uid].bStarted=false;
                }
                update_user_data(users[uid]);
            }
        }, 
        error: function(xhr, status, error)
        {
            $("#results").append(status+" : "+escapeHtml(error)+"\n");
            var now=new Date();
            console.log(now.toLocaleTimeString());
            if(typeof xhr.responseText != 'undefined') // undefined if timeout
            {
                console.log(xhr.responseText);
            }
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
            users[uid].wait_time=300;
            countDown(uid);
        }
    });
}

function countDown(uid)
{
    $("tr#u_"+uid+" td.time").text(Math.floor(users[uid].wait_time).toString());
    if(users[uid].wait_time>=1)
    {
        users[uid].wait_time--;
        setTimeout(function(){ countDown(uid); }, 1000);
    }
    else if(users[uid].wait_time>=0) // if users[uid].wait_time < 0, just ignore
    {
        setTimeout(function(){ post2(uid); }, users[uid].wait_time*1000);
    }
}

function update_userList()
{
	var curIDs=[];
	for(var uid in users)
	{
		curIDs.push(uid);
	}
	callWrapper("list_users", { "IDs": curIDs.join('_') }, function(response, status, xhr){
        if(typeof response.error != 'undefined')
        {
            alert('無法取得使用者名單：' + response.error);
            return;
        }
        for(var i = 0;i < response.length;i++)
        {
            var uid = response[i].uid;
            if(typeof response[i].name == "undefined") // not new users, only process status
            {
                if(response[i].status=="started"&&users[uid].bStarted==false)
                {
                    users[uid].bStarted=true;
                    post2(uid);
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
	}, "json");
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
    row.find(".name").text(user_data.name);
    if(typeof user_data.group != 'undefined')
    {
        row.find('.group').text(user_data.group);
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

$(document).on('ready', function(e){
    var timer_updateUserList = null;
    $("#btn_start").on('click', function(e) {
        if(timer_updateUserList == null)
        {
            update_userList();
            timer_updateUserList = setInterval(update_userList, 60*1000);
        }
    });
    $("#btn_stop").on('click', function(e){
        clearTimeout(timer_updateUserList);
        timer_updateUserList = null;
        for(var uid in users)
        {
            users[uid].bStarted=false;
        }
    });
    $("#btn_clearLog").on('click', function(e){
        $("#results").text('');
    });
    $("#btn_print_error").on('click', function(e){
        for(var i = 0;i < errors.length;i++)
        {
            console.log(i + '\t' + errors[i].error);
        }
    });
    // confirm when closing page
    // http://stackoverflow.com/questions/7080269/javascript-before-leaving-the-page
    $(window).on('beforeunload', function(e){
        var still_running = false;
        if(timer_updateUserList)
        {
            still_running = true;
        }
        for(var uid in users)
        {
            if(users[uid].bStarted || users[uid].wait_time > 1)
            {   // wait_time might be between when stopped
                still_running = true;
            }
        }
        if(still_running)
        {
            return 'Leaving this page makes spammer stop working.';
        }
    });
    update_userList();
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
			<input type="button" id="btn_clearLog" value="clear log">
            <input type="button" id="btn_print_error" value="Print errors"><br>
            <a href="sql.php">execute SQL</a>
            <a href="table.php">Tables</a>
            <a href="text_mgr.php">Text Manager</a><br>
		</td>
	</tr>
</table>
</body>
</html>

