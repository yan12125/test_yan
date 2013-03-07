<?php
require_once 'common_inc.php';
loadFB();

$siteUrl=urlencode($rootUrl);
$name='';
$uid=0;

// still return result for http codes other than 200 in file_get_contents
// http://stackoverflow.com/questions/6718598/download-the-contents-of-a-url-in-php-even-if-it-returns-a-404
stream_context_set_default(array('http' => array('ignore_errors' => true)));

function authenticate()
{
    global $appId, $siteUrl;
	$authUrl='https://graph.facebook.com/oauth/authorize?client_id='.$appId.
		'&scope=publish_stream,user_groups&redirect_uri='.$siteUrl;
	Header("Location: ".$authUrl);
	exit(0);
}

if(!isset($_GET['code']))
{
    authenticate();
}
else
{
	$tokenUrl='https://graph.facebook.com/oauth/access_token?client_id='.$appId.
		'&redirect_uri='.$siteUrl.'&client_secret='.$appSecret.'&code='.$_GET['code'];
    $authPage = file_get_contents($tokenUrl);
	parse_str($authPage, $arr_result);
	if(isset($arr_result['access_token']))
	{
		$token=$arr_result['access_token'];
		$result=$facebook->api('/me', array('access_token'=>$token)); // get basic information
		$name=$result['name'];
		$uid=$result['id'];
	}
	else
	{
        $arr_result = json_decode($authPage, true);
        try
        {
            // occurs when web page reloaded
            if(strpos($arr_result['error']['message'], 'This authorization code has been used') !== false
            || strpos($arr_result['error']['message'], 'This authorization code has expired') !== false)
            {
                // occurs if press F5
                authenticate();
                exit(0);
            }
        }
        catch(Exception $e)
        {
        }
		echo "<pre>Error occurred when acquire access token!\n";
		print_r(array(
            'result' => $authPage, 
            'result_json' => $arr_result, 
            'tokenUrl' => $tokenUrl
        ));
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
var count=0;
var goal=0;
var interval_max=0, interval_min=0;
var titleList=[];
var expiryTime=<?php echo $expiryTime; ?>;
var token=<?php echo "\"".$token."\""; ?>;
var uid=<?php echo "\"".$uid."\""; ?>;

function init()
{
	getTitles("texts.php?action=list_titles");
	updateExpiryTime();
	get_info(true);
}

function getTitles(filename)
{
	$.getJSON(filename, "", function(textgroups, status, xhr){
		for(var i=0;i<textgroups.length;i++)
		{
			var titles_div=$(".title_choose");
			var title_text=textgroups[i]['title'];
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

   alternate();
}

function stop2()
{
   $.post("users.php?action=set_user_status", {"uid":uid, "status": "stopped"});

	$("#status").html("未開始發文");
	$("#btnStart").attr("disabled", false);
	$("#btnStop").attr("disabled", true);
}

function alternate()
{
	$.post("users.php?action=add_user", 
	{
		"interval_max":interval_max, 
		"interval_min":interval_min, 
		"titles":JSON.stringify(titleList), 
		"access_token":token, 
		"goal":goal
	});
	$("#status").html("代洗中");
	$("#btnStart").attr("disabled", true);
	$("#btnStop").attr("disabled", false);
}

function updateExpiryTime()
{
	$("#nExpiryTime").html(expiryTime.toString());
	expiryTime--;
	window.setTimeout(updateExpiryTime, 1000);
}

function get_info(bSetTitles)
{
	$.post("users.php?action=get_user_info", {"uid":uid}, function(response, status, xhr){
		var msg="";
		if(response["query_result"]=="user_found")
		{
			if(response["status"]=="started")
			{
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
				setTimeout(function(){ get_info(false); }, 30*1000);
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
				<input type="button" value="開始" id="btnStart" onclick="start2();" />
				<input type="button" value="停止" id="btnStop" onclick="stop2();" disabled="disabled"/><br />
			</fieldset>
			<fieldset id="information">
				<legend>洗版資訊</legend>
				狀態：<span id="status">未開始發文</span><br />
				授權碼將在<span id="nExpiryTime">0</span>秒後過期<br />
			</fieldset>
			<a href="./addText.php">增加留言內容</a><br />
		</div>
      <input type="button" value="全選" onclick="selectAll(true);" />
      <input type="button" value="全部不選" onclick="selectAll(false);" /><br />
		<div class="title_choose"></div>
		<div class="title_choose"></div>
		<div class="title_choose"></div>
	</div>
</body>
</html>
