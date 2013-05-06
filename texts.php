<?php
require_once 'common_inc.php';
require_once 'util.php';

class Texts
{
    static $db;

    public static function listTitles()
    {
        if(($stmt = self::$db->query("SELECT title FROM texts where locked=0"))!==false)
        {
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        }
        else
        {
            throw new Exception('PDO query() failed: '.getPDOErr(self::$db));
        }
    }

    public static function addText($title, $texts)
    {
		$query="INSERT INTO texts (title,handler,text) VALUES (?,NULL,?)";
        $textArr = explode("\n", str_replace("\r\n", "\n", $texts));

        // remove empty lines
        function remove_empty($item)
        {
            return trim($item) != '';
        }
        $textArr = array_filter($textArr, 'remove_empty');

        $texts = json_unicode($textArr);
        $stmt = self::$db->prepare($query);
        if($stmt->execute(array($title, $texts)) === false)
		{
			throw new Exception("PDO execute() failed: ".$db->errorInfo());
		}
        return array('status' => 'success');
    }

    public static function check()
    {
        $stmt = self::$db->query('SELECT * FROM texts');
        while(($arr = $stmt->fetch(PDO::FETCH_ASSOC))!==false)
        {
            if(($new_text=str_replace(", \"\"", "", $arr['text']))!==$arr['text'])
            {
                $stmt_update = $db->prepare('UPDATE texts SET text=? WHERE title=?');
                $stmt_update->execute(array(str_replace('"', '\"', $new_text), $arr['title']));
            }
        }
    }

    public static function getTexts($title)
    {
        $stmt = self::$db->prepare("SELECT text FROM texts WHERE title=?");
        $stmt->execute(array($title));
        $arr = $stmt->fetch(PDO::FETCH_ASSOC);
        return $arr['text'];
    }

    /*
     * Get random text from $title
     * if $m is given, select specified one (for debugging purpose)
     */
    public static function getTextFromTitle($title, $m = -1)
    {
        global $rootUrl;
        $stmt = self::$db->prepare("SELECT text,handler,locked FROM texts WHERE title=?");
        $stmt->execute(array($title));
        $arr = $stmt->fetch(PDO::FETCH_ASSOC);
        if($arr===false)
        {
            return array(
                'error'=> 'Error query data!', 
                'msg' => NULL, 
                'title' => $title
            );
        }
        if($arr['locked'])
        {
            return array(
                'error' => 'title_locked', 
                'title' => $title, 
                'msg' => "(title locked)", 
                'm' => -1 // used in output in post.php
            );
        }
        $arr['text'] = str_replace('	', '    ', $arr['text']);
        $json_texts=json_decode($arr['text'], true);
        if(count($json_texts) == 0) // if not valid json, json_decode return null, and count(null) is 0
        {
            return array(
                'error' => 'Texts in specified title not valid!', 
                'title' => $title, 
                'msg' => null
            );
        }
        if($m < 0 || $m >= count($json_texts)) // if $m == -1 or invalid, randomly select a number
        {
            $m = rand(0, count($json_texts)-1);
        }
        $ret_val = array(
            'm' => $m, 
            'title' => $title
        );
        if(!is_null($arr['handler']))
        {
            $ret_val = array_merge($ret_val, self::callPlugin($arr['handler'], $json_texts[$m]));
        }
        else
        {
            // no need to escape html special chars because facebook will fo it
            $ret_val['msg'] = $json_texts[$m];
        }
        if(isset($ret_val['msg']) && trim($ret_val['msg']) == "")
        {
            $ret_val['error'] = 'Empty string!';
        }
        return $ret_val;
    }

    public static function getRandomTextFromTitles($titles)
    {
        $arr_titles = json_decode($titles);
        $n = rand(0, count($arr_titles)-1);
        return Texts::getTextFromTitle($arr_titles[$n]);
    }

    protected static function callPlugin($handler, $param)
    {
        global $rootUrl;
        $url=$rootUrl.'plugins/'.$handler.'?param='.$param;
        $ch=curl_init($url);
        curl_setopt_array($ch, array(
            CURLOPT_RETURNTRANSFER => true, 
            CURLOPT_HEADER => 0, 
            CURLOPT_BINARYTRANSFER => true, 
            CURLOPT_SSL_VERIFYHOST => 0, 
            CURLOPT_SSL_VERIFYPEER => false
        ));
        $data=curl_exec($ch);
        $status_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        if($status_code == 200)
        {
            return array('msg' => htmlspecialchars((string)$data));
        }
        else
        {
            return array('error' => (string)$data);
        }
    }
}

Texts::$db = $db;

if(isset($_POST['action'])&&strpos($_SERVER['REQUEST_URI'], basename(__FILE__))!==FALSE)
{
    try
    {
        switch($_POST['action'])
        {
            case "list_titles":
                echo json_unicode(Texts::listTitles());
                break;
            case 'add_text':
                checkPOST(array('title', 'texts'));
                echo json_encode(Texts::addText($_POST['title'], $_POST['texts']));
                break;
            default:
                break;
        }
    }
    catch(Exception $e)
    {
        echo json_encode(array('error' => $e->getMessage()));
    }
}
?>
