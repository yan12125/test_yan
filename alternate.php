<?php
$ip = $_SERVER['REMOTE_ADDR'];
if($ip!='127.0.0.1')
{
    header('403 Forbidden');
	echo "IP {$ip} forbidden";
	exit(0);
}

$useFB=false;
require_once 'common_inc.php';
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
var globalStarted=false;
var errors=[];
var debug = 0;

function post2(uid)
{
	if(users[uid].bStarted&&globalStarted)
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

function start2()
{
	if(!globalStarted)
	{
		for(var uid in users)
		{
			if(users[uid].status=="started")
			{
				globalStarted=true;
				users[uid].bStarted=true;
                post2(uid);
			}
		}
	}
}


function countDown(uid)
{
	if(users[uid].wait_time>=0)
	{
		$("tr#u_"+uid+" td.time").html(Math.floor(users[uid].wait_time).toString());
		if(users[uid].wait_time>=1)
		{
			users[uid].wait_time--;
			setTimeout(function(){ countDown(uid); }, 1000);
		}
		else if(users[uid].wait_time>=0)
		{
			setTimeout(function(){ post2(uid); }, users[uid].wait_time*1000);
		}
	}
	else
	{
		users[uid].wait_time=0;
		users[uid].bStarted=false;
		$.post("users.php?action=set_user_status", {"status": "stopped", "uid": users[uid].uid});
	}
}

function stopAll()
{
	globalStarted=false;
	for(var i=0;i<users.length;i++)
	{
		users[i].bStarted=false;
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
    setTimeout(update_userList, 60*1000);
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
    var row = get_user_rows().eq(n);
    return users[row[0].id.substr(2)]; // id is u_xxxx...xxxx
}

$(document).on('ready', function(e){
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
			<input type="button" id="btn_start" value="Start" onclick="start2(); ">
			<input type="button" id="btn_stop" value="Stop" onclick="stopAll(); ">
			<input type="button" value="clear log" onclick="$('#results').html('');"><br>
			Rate=<span id="rate"></span>Posts/day<br>
		</td>
	</tr>
</table>
</body>
</html>
