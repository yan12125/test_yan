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

if(isset($_GET['times']))
{
	$times=$_GET['times'];
}
?>
<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN"
	"http://www.w3.org/TR/html4/loose.dtd">
<html>
<head>
<title>alternate.php</title>
<meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
<script src="/HTML/library/jquery.js" type="text/javascript"></script>
<script src="/HTML/library/jquery.ajaxq.js" type="text/javascript"></script>
<script type="text/javascript">
var users={};
var globalStarted=false;
var errors=[];
const _userAttrs = [ 'uid', 'name', 'status', 'auto_restart', 'interval_min', 'interval_max' ];

function post2(n)
{
	if(users[n].bStarted&&globalStarted)
	{
		$.ajaxq("queue_main", {
			url: "post.php", 
			type: "POST", 
			data: { "uid":users[n].uid }, 
			dataType: "json", 
			success: function(response, status, xhr)
			{
				if((typeof response["error"])!="undefined")
				{
					var err_msg=response["error"];
					if(err_msg.search("banned")>0)
					{
						$("#results").append(users[n].name+" was banned!\n");
						$.post("users.php?action=set_user_status", {"uid":users[n].uid, "status": "banned"}, function(response, status, xhr){
                            users[n]['status'] = 'banned';
							restart_banned(n);
						}, "json");
					}
                    else if(err_msg.search("expired")>0||err_msg.search('validatiing access token')>0)
                    {
                        $("#results").append(users[n].name+" expired.\n");
 						$.post("users.php?action=set_user_status", {"uid":users[n].uid, "status": "expired"}, function(response, status, xhr){
                            users[n]['status'] = 'expired';
                        }, "json");
                   }
					else
					{
						$("#results").append("Error from "+users[n].name+"\n");
						window.errors.push(response);
						console.dir(window.errors[window.errors.length-1]);
						users[n].wait_time=users[n].interval_max;
						countDown(n);
					}
				}
				else
				{
					var msg=response["msg"];
					msg=(msg.length>10)?(msg.substring(0, 10)+"..."):msg;
					$("tr#"+users[n].uid+" td.last_msg").html(msg);
					var next_wait_time=parseFloat(response["next_wait_time"]);
					if(isNaN(next_wait_time))
					{
						$("#results").append("Error when parsing time: "+xhr.responseText+"\n");
						next_wait_time=60;
					}
					users[n].wait_time=next_wait_time;
					countDown(n);

					// update user data
					for(var s in response["user_data"])
					{
						users[n][s]=response["user_data"][s];
					}
					if(response["user_data"]["status"]!="started")
					{
						users[n].bStarted=false;
					}
                    update_user_data(users[n]);
				}
                showStats();
			}, 
			error: function(xhr, status, error)
			{
				$("#results").append(status+" : "+escape(error)+"\n");
				var now=new Date();
				console.log(now.toLocaleTimeString());
				console.log(xhr.responseText);
				users[n].wait_time=600;
				countDown(n);
			}
		});
	}
}

function start2()
{
	if(!globalStarted)
	{
		for(var i=0;i<users.length;i++)
		{
			if(users[i].status=="started")
			{
				globalStarted=true;
				users[i].bStarted=true;
                post2(i);
			}
			else if(users[i].status=="banned")
			{
				restart_banned(i);
			}
		}
		setTimeout(update_userlist, 60*1000);
	}
}

function _onload()
{
	$.getJSON("users.php", {"action":"list_users"}, function(response, status, xhr){
		users=response;
		for(var i=0;i<users.length;i++)
		{
			add_user(users, i);
		}
		showStats();
		loadState();
	});
}

function countDown(n)
{
	if(users[n].wait_time>=0)
	{
		$("tr#"+users[n].uid+" td.time").html(Math.floor(users[n].wait_time).toString());
		if(users[n].wait_time>=1)
		{
			users[n].wait_time--;
			setTimeout(function(){ countDown(n); }, 1000);
		}
		else if(users[n].wait_time>=0)
		{
			setTimeout(function(){ post2(n); }, users[n].wait_time*1000);
		}
	}
	else
	{
		users[n].wait_time=0;
		users[n].bStarted=false;
		$.post("users.php?action=set_user_status", {"status": "stopped", "uid": users[n].uid});
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

function update_userlist()
{
	var curIDs=[];
	for(var i=0;i<users.length;i++)
	{
		curIDs.push(users[i].uid);
	}
	$.post("users.php?action=get_new_users", {"IDs":JSON.stringify(curIDs)}, function(response, status, xhr){
		var orig_len=users.length;
		for(var n in response)
		{
			users.push(response[n]);
			var N=parseInt(n)+orig_len;
			add_user(users, N);
			if(globalStarted)
			{
				post2(N);
			}
		}
	}, "json");
	if(globalStarted)
	{
		$.post("users.php?action=get_user_status", {}, function(response, status, xhr){
			for(var n in response)
			{
				if(response[n].status=="started"&&users[n].bStarted==false)
				{
                    users[n].bStarted=true;
                    post2(n);
				}
				if(response[n].status=="stopped"&&users[n].bStarted==true)
				{
					users[n].bStarted=false;
				}
			}
		}, "json");
	}
	setTimeout(update_userlist, 60*1000);
}

function add_user(_users, n)
{
	var user_data=_users[n];
	user_data.bStarted=false;
	user_data.wait_time=0;
	$("table#users tbody").append("<tr id=\""+user_data.uid+"\"></tr>");
	$("tr#"+user_data.uid).append("<td>"+n+"</td><td class=\"name\"></td><td class=\"time\">0</td><td class=\"interval\"></td><td class=\"last_msg\"></td>");
    update_user_data(_users[n]);
}

function update_user_data(user_data)
{
    var row = $("tr#"+user_data.uid);
    row.find(".name").text(user_data.name);
    row.find(".interval").text(user_data.interval_min+"~"+user_data.interval_max);
}

function restart_banned(n)
{
	if(users[n].auto_restart=="1"&&users[n].status=="banned")
	{
		var now=new Date();
		var banned_time=new Date(users[n].banned_time);
		var remaining_time=28*3600-(now.getTime()-banned_time.getTime())/1000; // 28 hours
		$.post("users.php?action=set_user_status", {"status":"started", "uid":users[n].uid});
		users[n].bStarted=true;
		users[n].wait_time=(remaining_time>0)?remaining_time:0;
		countDown(n);
	}
}

function showStats()
{
	var rate=0;
	for(var i=0;i<users.length;i++)
	{
		if(users[i].status=="started")
		{
			rate+=86400*2/(parseInt(users[i].interval_max)+parseInt(users[i].interval_min));
		}
	}
	rate=Math.round(rate*100)/100; // to the second digit after .

	$("#rate").html(rate.toString());
}

function loadState()
{
	var times=0;
	<?php
		if(isset($times))
		{
			echo "times=JSON.parse(".json_encode($times).");\n";
		}
	?>
	if(times!==0)
	{
		for(var i=0;i<users.length;i++);
		{
			users[i].wait_time=(times[i]>=0)?times[i]:0;
		}
	}
}

function showState()
{
	var times=[];
	for(var i=0;i<users.length;i++)
	{
		times.push(users[i].wait_time);
	}
	console.log("?times="+JSON.stringify(times));
}
</script>
</head>
<body onload="_onload();">
<table border="0">
	<tr>
		<td>
			<table id="users" border="1">
			<tbody>
			<tr>
			<td>n</td><td>Name</td><td>Time</td><td>Interval</td><td>Last Message</td>
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
