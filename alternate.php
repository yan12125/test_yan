<?php
$useFB=false;
require_once 'common_inc.php';
ip_only('127.0.0.1');
?>
<!DOCTYPE html>
<html>
<head>
<title>alternate.php</title>
<meta charset="UTF-8">
<script src="/HTML/library/jquery.js"></script>
<script src="/HTML/library/jquery.ajaxq.js"></script>
<script>
var users={};
var errors=[];
var debug = 0;

function post2(uid)
{
	if(users[uid].bStarted)
	{
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
			success: function(response, status, xhr)
			{
				if((typeof response["error"])!="undefined")
				{
					var err_msg=response["error"];
                    if(typeof response["processed"] != "undefined")
                    {
						$("#results").append(err_msg);
                        users[uid]['status'] = response["new_status"];
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
					$("tr#u_"+users[uid].uid+" td.last_msg").html(msg);
					var next_wait_time=parseFloat(response["next_wait_time"]);
					if(isNaN(next_wait_time))
					{
						$("#results").append("Error when parsing time: "+xhr.responseText+"\n");
						next_wait_time=60;
					}
					users[uid].wait_time=next_wait_time;
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
				$("#results").append(status+" : "+escape(error)+"\n");
				var now=new Date();
				console.log(now.toLocaleTimeString());
				console.log(xhr.responseText);
				users[uid].wait_time=600;
				countDown(uid);
			}
		});
	}
}

function countDown(uid)
{
    $("tr#u_"+uid+" td.time").html(Math.floor(users[uid].wait_time).toString());
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
	$.post("users.php?action=list_users", {"IDs":JSON.stringify(curIDs)}, function(response, status, xhr){
        for(var i = 0;i < response.length;i++)
        {
            if(typeof response[i].uid == "undefined")
            {
                if(typeof response[i].rate != "undefined")
                {
                    $("#rate").html(response[i].rate);
                }
                continue;
            }
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
            rows.eq(i).find("td:first").html(i);
        }
	}, "json");
}

function add_user(_users, uid)
{
	var user_data=_users[uid];
	user_data.bStarted=false;
	user_data.wait_time=0;
	$("table#users tbody").append("<tr id=\"u_"+user_data.uid+"\"></tr>");
	$("tr#u_"+user_data.uid).append("<td></td><td class=\"name\"></td><td class=\"time\">0</td><td class=\"last_msg\"></td>");
    update_user_data(_users[uid]);
}

function update_user_data(user_data)
{
    var row = $("tr#u_"+user_data.uid);
    row.find(".name").text(user_data.name);
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
        update_userList();
        timer_updateUserList = setInterval(update_userList, 60*1000);
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
        $("#results").html('');
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
			<td>n</td><td>Name</td><td>Time</td><td>Last Message</td>
			</tr>
			</tbody>
			</table>
		</td>
		<td align="left" valign="top">
			<textarea id="results" style="width:100%; height:100px" readonly="readonly" rows="10" cols="40"></textarea><br>
			<input type="button" id="btn_start" value="Start">
			<input type="button" id="btn_stop" value="Stop">
			<input type="button" id="btn_clearLog" value="clear log"><br>
			Rate=<span id="rate"></span>Posts/day<br>
		</td>
	</tr>
</table>
</body>
</html>
