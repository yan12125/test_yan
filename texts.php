<?php
header("Content-type: text/html; charset=utf-8");
if(isset($_GET['source']))
{
	highlight_file(__FILE__);
	exit(0);
}

$useFB=false;
require_once 'common_inc.php';

function text_action($verb, $params)
{
	$ret_val='';
	switch($verb)
	{
		case "list_titles":
			$result=mysql_query("SELECT title FROM texts");
			$output=array();
			if($result!=false)
			{
				while(($arr=mysql_fetch_assoc($result))!=FALSE)
				{
					$output[]=$arr['title'];
				}
			}
			else
			{
				$output['error']=mysql_error();
			}
			$ret_val=$output;
			break;
		case "get_texts":
			if(array_key_exists('title', $params))
			{
				$result=mysql_query("SELECT text FROM texts WHERE title='".$params['title']."' ");
				$arr=mysql_fetch_assoc($result);
				$ret_val=$arr['text'];
			}
			break;
		case "get_random_text_from_title":
			if(array_key_exists('title', $params))
			{
				$ret_val=array();
				$query="SELECT text,handler FROM texts WHERE title='".$params['title']."'";
				$result=mysql_query($query);
				if($result!==false&&mysql_num_rows($result)>0)
				{
					$arr=mysql_fetch_assoc($result);
					$json_texts=json_decode($arr['text'], true);
                    if(count($json_texts)>=1) // if not valid json, json_decode return null, and count(null) is 0
                    {
                        $m=rand(0, count($json_texts)-1);
                        $ret_val['m']=$m;
                        $ret_val['title']=$params['title'];
                        if(!is_null($arr['handler']))
                        {
                            $url=$GLOBALS['rootUrl'].$arr['handler'].'?param='.(string)$json_texts[$m];
                            $ch=curl_init($url);
                            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                            curl_setopt($ch, CURLOPT_HEADER, 0);
                            curl_setopt($ch, CURLOPT_BINARYTRANSFER, true);
                            $data=curl_exec($ch);
                            curl_close($ch);
                            $ret_val['msg']=htmlspecialchars((string)$data);
                        }
                        else
                        {
                            $ret_val['msg']=htmlspecialchars($json_texts[$m]);
                        }
                    }
                    else
                    {
                        $ret_val['error']='Texts in specified title not valid!';
                        $ret_val['title']=$params['title'];
                        $ret_val['msg']=null;
                    }
				}
				else
				{
					$ret_val['error']='Error query data!';
                    $ret_val['title']=$params['title'];
                    $ret_val['msg']=null;
				}
			}
			break;
		case "get_random_text_from_titles":
			if(array_key_exists('titles', $params))
			{
				$arr_titles=json_decode($params['titles']);
				$n=rand(0, count($arr_titles)-1);
				$ret_val=text_action("get_random_text_from_title", array("title"=>$arr_titles[$n]));
				$ret_val['n']=$n;
			}
			break;
        case "check":
            $result = mysql_query('SELECT * FROM texts');
            while(($arr=mysql_fetch_assoc($result))!==false)
            {
                if(($new_text=str_replace(", \"\"", "", $arr['text']))!==$arr['text'])
                {
                    $query = 'UPDATE texts SET text="'.str_replace('"', '\"', $new_text).'" WHERE title="'.$arr['title'].'"';
                    //echo $query.";\n";
                    mysql_query($query);
                }
            }
            break;
		default:
			return "Invalid action verb.\n";
			break;
	}
	return $ret_val;
}

if(isset($_GET['action'])&&strpos($_SERVER['REQUEST_URI'], 'texts.php')!==FALSE)
{
	switch($_GET['action'])
	{
		case "list_titles":
			echo json_encode(text_action("list_titles", array()));
			break;
	}
}
?>
