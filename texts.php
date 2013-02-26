<?php
require_once 'common_inc.php';

function text_action($verb, $params)
{
    global $db;
	$ret_val='';
	switch($verb)
	{
		case "list_titles":
			if(($stmt = $db->query("SELECT title FROM texts"))!==false)
			{
                $ret_val = $stmt->fetchAll(PDO::FETCH_ASSOC);
			}
			else
			{
				$ret_val = array('error' => getPDOErr($db));
			}
			break;
		case "get_texts":
			if(array_key_exists('title', $params))
			{
                $stmt = $db->prepare("SELECT text FROM texts WHERE title=?");
                $stmt->execute(array($params['title']));
                $arr = $stmt->fetch(PDO::FETCH_ASSOC);
				$ret_val=$arr['text'];
			}
			break;
		case "get_random_text_from_title":
			if(array_key_exists('title', $params))
			{
				$ret_val=array();
                $stmt = $db->prepare("SELECT text,handler,locked FROM texts WHERE title=?");
                $stmt->execute(array($params['title']));
                $arr = $stmt->fetch(PDO::FETCH_ASSOC);
				if($arr!==false)
				{
                    if($arr['locked'])
                    {
                        $ret_val['error'] = 'title_locked';
                        $ret_val['title'] = $params['title'];
                        $ret_val['msg'] = "(title locked)";
                        $ret_val['m'] = -1; // used in output in post.php
                        break;
                    }
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
                        if(trim($ret_val['msg']) == "")
                        {
                            $ret_val['error'] = 'Empty string!';
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
                    $ret_val['msg'] = NULL;
                    $ret_val['title'] = $params['title'];
				}
			}
			break;
		case "get_random_text_from_titles":
			if(array_key_exists('titles', $params))
			{
				$arr_titles=json_decode($params['titles']);
				$n=rand(0, count($arr_titles)-1);
				$ret_val=text_action("get_random_text_from_title", array("title"=>$arr_titles[$n]));
			}
			break;
        case "check":
            $stmt = $db->query('SELECT * FROM texts');
            while(($arr = $stmt->fetch(PDO::FETCH_ASSOC))!==false)
            {
                if(($new_text=str_replace(", \"\"", "", $arr['text']))!==$arr['text'])
                {
                    $stmt_update = $db->prepare('UPDATE texts SET text=? WHERE title=?');
                    $stmt_update->execute(array(str_replace('"', '\"', $new_text), $arr['title']));
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
    header("Content-type: application/json");
	switch($_GET['action'])
	{
		case "list_titles":
			echo json_encode(text_action("list_titles", array()));
			break;
	}
}
?>
