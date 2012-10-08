<?php
header("Content-type: text/html; charset=utf-8");
if(isset($_GET['source']))
{
	highlight_file(__FILE__);
	exit(0);
}

$useFB=true;
require_once 'common_inc.php';

$siteUrl=urlencode($rootUrl);
$count_total=0;
$name='';
$uid=0;

if(!isset($_GET['code']))
{
	$authUrl='https://graph.facebook.com/oauth/authorize?client_id='.$appId.
		'&scope=publish_stream,user_groups&redirect_uri='.$siteUrl;
	Header("Location: ".$authUrl);
	exit(0);
}
else
{
	$tokenUrl='https://graph.facebook.com/oauth/access_token?client_id='.$appId.
		'&redirect_uri='.$siteUrl.'&client_secret='.$appSecret.'&code='.$_GET['code'];
	parse_str(file_get_contents($tokenUrl), $arr_result);
	if(isset($arr_result['access_token']))
	{
		$token=$arr_result['access_token'];
		$result=$facebook->api('/me', array('access_token'=>$token)); // get basic information
		$name=$result['name'];
		$uid=$result['id'];
		$count_total=getCount($facebook, $token);
	}
	else
	{
		echo "<pre>Error occurred when acquire access token!\n";
		print_r($arr_result);
		echo "</pre>";
		exit(0);
	}

	$expiryTime=60*86400; // default expiry time is 60 days
	if(isset($arr_result['expires']))
	{
		$expiryTime=$arr_result['expires'];
	}
	if($expiryTime<7201)
	{
		// exchange the access token
		$url_newToken='https://graph.facebook.com/oauth/access_token?client_id='.$appId.'&client_secret='.$appSecret.
			'&grant_type=fb_exchange_token&fb_exchange_token='.$token;
		parse_str(file_get_contents($url_newToken), $arr_result);
		if($arr_result['expires']<7201)
		{
			echo 'token將在'.$arr_result['expires'].'秒後過期，請重新登入！';
			exit(0);
		}
		else
		{
			$token=$arr_result['access_token'];
		}
	}
}
?>
<!DOCTYPE html>
<html>
<head>
<title><?php echo $name; ?></title>
<meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />
<style type="text/css">
#wrapper
{
	position: relative;
	width: 900px;
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
	width: 240px;
	float: left;
}

.input_number
{
	width: 40px;
}
</style>
<script language="javascript" src="/HTML/library/jquery.js"></script>
<script language="javascript">
var started=false;
var bAlternate=false;
var count=0;
var goal=0;
var interval_max=0, interval_min=0;
var titleList=[];
var expiryTime=<?php echo $expiryTime; ?>;
var token=<?php echo "\"".$token."\""; ?>;
var uid=<?php echo "\"".$uid."\""; ?>;

/* for IE only because IE parse contents in <textarea> as HTML */
function alterTexts(_str)
{
	if(navigator.userAgent.search("MSIE")!=-1)
	{
		return _str.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
			.replace(/"/g, '&quot;').replace(/\n/g, "<br />");
	}
	else
	{
		return _str;
	}
}

function init()
{
	getTitles("texts.php?action=list_titles");
	updateExpiryTime();
	get_info(true);
	$("input[name=type]").click(alternateOptions);
}

function getTitles(filename)
{
	$.getJSON(filename, "", function(textgroups, status, xhr){
		for(var i=0;i<textgroups.length;i++)
		{
			var titles_div=$(".title_choose");
			var title_text=textgroups[i];
			titles_div[i%titles_div.length].innerHTML+="<input type=\"checkbox\" class=\"title_checkbox\" value=\""+
				title_text+"\" />"+title_text+"<br />\n";
		}
		$(".title_checkbox").click(parseTitles).attr("checked", true);
	});
}

function parseTitles()
{
	var checkedTitles=$(".title_checkbox:checked");
	titleList=[];
	var n=checkedTitles.length;
	if(n==0)
	{
		alert("至少要選一個標題！");
		return false;
	}
	for(var i=0;i<n;i++)
	{
		titleList.push(checkedTitles[i].value);
	}
	return true;
}

function post2()
{
	if(started&&goal>=1)
	{
		if(!parseTitles())
		{
			return;
		}

		$.ajax({url: "post.php", 
			type: "post", 
			data: {
				"interval_max":interval_max, 
				"interval_min":interval_min, 
				"titles":JSON.stringify(titleList), 
				"access_token":token, 
				"uid":uid
			}, 
			success: function(response, status, xhr)
			{
				try
				{
					if((typeof response["error"])!="undefined")
					{
						var err_msg=response["error"];
						$("results").append(alterTexts("Error: "+err_msg+"\n"));
						if(err_msg.search("banned")!=-1)
						{
							countDown(3600);	// wait 1 hour if seem locked
						}
						else
						{
							countDown(60);
						}
					}
					else
					{
						var msg=response["msg"];
						var next_wait_time=parseInt(response["next_wait_time"]);
						if(isNaN(next_wait_time))
						{
							$("#results").append("Error when parsing time: "+xhr.responseText+"\n");
							next_wait_time=60;
						}
						count++;
						$("#results").append(alterTexts("Msg: "+msg+"\n"));
						$("#count").html(count.toString());
						countDown(next_wait_time);
					}
				}
				catch(e)
				{
					$("#results").append("Error! "+e+"\n");
				}
			}, 
			error: function(xhr, status, error){
				$("#results").append(alterTexts("Error: 500 Internal server error"));
				countDown(60);	// 一分鐘後再來一遍，雖然不一定每次都修那麼快
			}, 
			dataType: "json"});
		$("#status").html("等待伺服器回應中");
	}
	else
	{
		started=false;
	}
}

function countDown(interval)
{
	if(count!=0)
	{
		$("#status").html("倒數中");
		$("#remainingTime").html(Math.floor(interval).toString());
		if(interval>=1)
		{
			setTimeout("countDown("+(interval-1).toString()+");", 1000);
		}
		else if(interval>=0)
		{
			setTimeout("post2();", interval*1000);
		}
	}
	else
	{
		stop2();
	}
}

function selectAll(allOrNone)
{
	$(".title_checkbox").attr("checked", allOrNone);
}

function getParams()
{
	if(!parseTitles())
	{
		return false;
	}
	interval_max=parseInt($("#interval_max").val());
	interval_min=parseInt($("#interval_min").val());
	count=parseInt($("#count").html());
	goal=parseInt($("#goal").val());
	if(isNaN(interval_max)||isNaN(interval_min)||isNaN(count))
	{
		alert("所有欄位皆須輸入數字");
		interval_max=interval_min=count=0;
		return false;
	}
	if(interval_min<0||interval_max<0)
	{
		interval_min=interval_max=-1;
		alert("請輸入零秒以上！");
		return false;
	}
	return true;
}

function start2()
{
	if(!getParams())
	{
		return;
	}

	switch(alternateOptions())
	{
	case "normal":
		if(!started)
		{
			$("#btnStart").attr("disabled", true);
			$("#btnStop").attr("disabled", false);
			$("#results").html("");
			$("#interval_min").html(interval_min.toString());
			$("#interval_max").html(interval_max.toString());
			$("#count").html(count.toString());
			started=true;
			post2();
		}
		break;
	case "alternate":
		alternate();
		break;
	}
}

function stop2()
{
	if(started)
	{
		started=false;
	}
	else if(bAlternate)
	{
		$.post("users.php?action=set_user_status", {"uid":uid, "status": "stopped"});
		bAlternate=false;
	}
	$("#status").html("未開始發文");
	$("#btnStart").attr("disabled", false);
	$("#btnStop").attr("disabled", true);
}

function alternate()
{
	var bAutoRestart=($("#isAutoRestart")[0].checked)?1:0;
	$.post("users.php?action=add_user", 
	{
		"interval_max":interval_max, 
		"interval_min":interval_min, 
		"titles":JSON.stringify(titleList), 
		"access_token":token, 
		"goal":goal, 
		"auto_restart":bAutoRestart
	});
	$("#status").html("代洗中");
	$("#btnStart").attr("disabled", true);
	$("#btnStop").attr("disabled", false);
	bAlternate=true;
}

function alternateOptions()
{
	var type=$("input[name=type]:checked").val();
	switch(type)
	{
	case "alternate":
		$("#alternateOptions").show();
		break;
	case "normal":
		$("#alternateOptions").hide();
		break;
	}
	return type;
}

function updateExpiryTime()
{
	$("#nExpiryTime").html(expiryTime.toString());
	expiryTime--;
	window.setTimeout("updateExpiryTime();", 1000);
}

function get_info(bSetTitles)
{
	$.post("users.php?action=get_user_info", {"uid":uid}, function(response, status, xhr){
		var msg="";
		if(response["query_result"]=="user_found")
		{
			if(response["status"]=="started")
			{
				bAlternate=true;
				$("#status").html("代洗中");
				$("#btnStart").attr("disabled", true);
				$("#btnStop").attr("disabled", false);
			}
			if(!started)
			{
				$("#interval_max").val(response["interval_max"]);
				$("#interval_min").val(response["interval_min"]);
				$("#count").html(response["count"]);
				$("#goal").val(response["goal"]);

				goal=response["goal"];
				count=goal-response["count"];
				if(bSetTitles)
				{
					selectAll(false);
					var arr_titles=JSON.parse(response["titles"].replace(/\\\"/g, "\""));
					for(var n in arr_titles)
					{
						$(".title_checkbox[value=\""+arr_titles[n]+"\"]").attr("checked", true);
					}
				}
				setTimeout("get_info(false);", 30*1000);
			}
		}
	}, "json");
}

</script>
</head>
<body onload="init();">
	<div id="wrapper">
		<div id="controls">
			<fieldset id="parameters">
				<legend>設定</legend>
				時間間隔上限: <input type="text" id="interval_max" class="input_number" maxlength="5" value="100"/><br />
				時間間隔下限: <input type="text" id="interval_min" class="input_number" maxlength="5" value="80"/><br />
				發文次數: <input type="text" id="goal" class="input_number" maxlength="7" value="2147483647"/><br />
				已發文數：<span id="count">0</span><br />
				<input type="radio" name="type" value="normal"/>自己洗
				<input type="radio" name="type" value="alternate" checked="checked"/>代洗<br />
				<div id="alternateOptions">
					<input type="checkbox" id="isAutoRestart" />代洗被鎖後28小時重新開始<br />
				</div>
				<input type="button" value="全選" onclick="selectAll(true);" />
				<input type="button" value="全部不選" onclick="selectAll(false);" /><br />
				<input type="button" value="開始" id="btnStart" onclick="start2();" />
				<input type="button" value="停止" id="btnStop" onclick="stop2();" disabled="disabled"/><br />
			</fieldset>
			<fieldset id="information">
				<legend>洗版資訊</legend>
				狀態：<span id="status">未開始發文</span><br />
				距離下次發文：<span id="remainingTime">0</span>秒<br />
				總戰績：<span id="count_total"><? echo $count_total; ?></span><br />
				Token將在<span id="nExpiryTime">0</span>秒後過期<br />
			</fieldset>
			<a href="./addText.php">增加留言內容</a><br />
		</div>
		<div class="title_choose"></div>
		<div class="title_choose"></div>
		<div class="title_choose"></div>
		<div id="messages">
			<textarea id="results" cols="90" rows="10" readonly="readonly"></textarea>
		</div>
	</div>
</body>
</html>
