<?php
if(isset($_GET['source']))
{
	highlight_file(__FILE__);
	exit(0);
}

$ip = $_SERVER['REMOTE_ADDR'];
if($ip!='140.112.241.234'&&$ip!='127.0.0.1')
{
    header('403 Forbidden');
	echo "IP {$ip} forbiddened.";
	exit(0);
}

if(isset($_GET['times']))
{
	$times=$_GET['times'];
}

header("Content-type: text/html; charset=utf-8");
$useFB=false;
require_once 'common_inc.php';
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

function post2(n)
{
	if(users[n].bStarted&&globalStarted)
	{
		$.ajaxq("queue_main", {
			url: "post.php", 
			type: "POST", 
			data: { "interval_max":users[n].interval_max, "interval_min":users[n].interval_min, 
				"titles":users[n].titles, "access_token":users[n].access_token, "uid":users[n].uid }, 
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
							for(var prop in response)
							{
								users[n][prop]=response[prop];
							}
							restart_banned(n);
						}, "json");
					}
                    else if(err_msg.search("expired")>0||err_msg.search('validatiing access token')>0)
                    {
                        $("#results").append(users[n].name+" expired.\n");
 						$.post("users.php?action=set_user_status", {"uid":users[n].uid, "status": "expired"});
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
					var curCount=parseInt($("tr#"+users[n].uid+" td.count").html());
					$("tr#"+users[n].uid+" td.count").html((curCount+1).toString());
					users[n].wait_time=next_wait_time;
					countDown(n);

					// update user data
					for(var s in response["user_data"])
					{
						users[n][s]=response["user_data"][s];
					}
                    // disallow too short timeout
                    if(parseInt(users[n]['interval_max'])+parseInt(users[n]['interval_min'])<140)
                    {
                        users[n]['interval_max'] = users[n]['interval_min'] = 70;
                    }
					if(response["user_data"]["status"]!="started")
					{
						users[n].bStarted=false;
					}
				}
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
	showStats();
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
		setTimeout("update_userlist();", 60*1000);
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
    // http://stackoverflow.com/questions/7080269/javascript-before-leaving-the-page
    $(window).on('beforeunload', function(e){
        return 'Leaving this page makes spammer stop working.';
    });
}

function countDown(n)
{
	if(parseInt(users[n].count)<parseInt(users[n].goal)&&users[n].wait_time>=0)
	{
		$("tr#"+users[n].uid+" td.time").html(Math.floor(users[n].wait_time).toString());
		if(users[n].wait_time>=1)
		{
			users[n].wait_time--;
			setTimeout("countDown("+n+");", 1000);
		}
		else if(users[n].wait_time>=0)
		{
			setTimeout("post2("+n+");", users[n].wait_time*1000);
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
					if(parseInt(users[n].goal)>users[n].count)
					{
						users[n].bStarted=true;
						post2(n);
					}
				}
				if(response[n].status=="stopped"&&users[n].bStarted==true)
				{
					users[n].bStarted=false;
				}
			}
		}, "json");
	}
	setTimeout("update_userlist()", 60*1000);
}

function add_user(_users, n)
{
	var user_data=_users[n];
	user_data.bStarted=false;
	user_data.wait_time=0;
	$("table#users tbody").append("<tr id=\""+user_data.uid+"\"></tr>");
	$("tr#"+user_data.uid).append("<td>"+n+"</td>"+
								  "<td class=\"name\">"+user_data.name+"</td>"+
								  "<td class=\"count\">"+user_data.count+"</td>"+
								  "<td class=\"time\">0</td>"+
								  "<td class=\"goal\">"+user_data.goal+"</td>"+
								  "<td class=\"interval\">"+user_data.interval_min+"~"+user_data.interval_max+"</td>"+
								  "<td class=\"last_msg\"></td>");
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
	var sum=0;
	var rate=0;
	for(var i=0;i<users.length;i++)
	{
		sum+=parseInt(users[i].count);
		if(users[i].status=="started")
		{
			rate+=86400*2/(parseInt(users[i].interval_max)+parseInt(users[i].interval_min));
		}
	}
	rate=Math.round(rate*100)/100; // to the second digit after .

	$("#total").html(sum.toString());
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
			<td>n</td><td>Name</td><td>Count</td><td>Time</td><td>Goal</td><td>Interval</td><td>Last Message</td>
			</tr>
			</tbody>
			</table>
		</td>
		<td align="left" valign="top">
			<textarea id="results" style="width:100%; height:100px" readonly="readonly" rows="10" cols="40"></textarea><br>
			<input type="button" id="btn_start" value="Start" onclick="start2(); ">
			<input type="button" id="btn_stop" value="Stop" onclick="stopAll(); ">
			<input type="button" value="clear log" onclick="$('#results').html('');"><br>
			Total=<span id="total"></span><br>
			Rate=<span id="rate"></span>Posts/day<br>
		</td>
	</tr>
</table>
</body>
</html>
