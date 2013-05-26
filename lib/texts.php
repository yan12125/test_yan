<?php
class Texts
{
    public static function listTitles()
    {
        if(($stmt = Db::query("SELECT title FROM texts where locked=0"))!==false)
        {
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        }
        else
        {
            throw new Exception('PDO query() failed: '.Db::getErr());
        }
    }

    public static function checkTitle($title)
    {
        // check for duplicated titles
        $stmt = Db::prepare('select count(*) from texts where title=?');
        $stmt->execute(array($title));
        $num = $stmt->fetch(PDO::FETCH_NUM);
        if($num[0] > 0)
        {
            return array('status' => 'title_exists');
        }
        else
        {
            return true;
        }
    }

    public static function addText($title, $texts)
    {
        $result = self::checkTitle($title);
        if($result !== true)
        {
            return $result;
        }
		$query="INSERT INTO texts (title,handler,text) VALUES (?,NULL,?)";
        $textArr = explode("\n", str_replace("\r\n", "\n", $texts));

        // remove empty lines
        function remove_empty($item)
        {
            return trim($item) != '';
        }
        // array_filter preserve keys, but we want continuous array for json_encode
        $textArr = array_values(array_filter($textArr, 'remove_empty'));

        $texts = Util::json_unicode($textArr);
        $stmt = Db::prepare($query);
        if($stmt->execute(array($title, $texts)) === false)
		{
			throw new Exception("PDO execute() failed: ".Db::getErr());
		}
        return array('status' => 'success');
    }

    public static function check()
    {
        $stmt = Db::query('SELECT * FROM texts');
        while(($arr = $stmt->fetch(PDO::FETCH_ASSOC))!==false)
        {
            if(($new_text=str_replace(", \"\"", "", $arr['text']))!==$arr['text'])
            {
                $stmt_update = Db::prepare('UPDATE texts SET text=? WHERE title=?');
                $stmt_update->execute(array(str_replace('"', '\"', $new_text), $arr['title']));
            }
        }
    }

    public static function getTexts($title)
    {
        $stmt = Db::prepare("SELECT text FROM texts WHERE title=?");
        $stmt->execute(array($title));
        $arr = $stmt->fetch(PDO::FETCH_ASSOC);
        $text = $arr['text'];
        self::replaceTab($text);
        $json_text = json_decode($text, true);
        if(is_array($json_text))
        {
            return $json_text;
        }
        else
        {
            return $text;
        }
    }

    /*
     * Get random text from $title
     * if $m is given, select specified one (for debugging purpose)
     */
    public static function getTextFromTitle($title, $m = -1)
    {
        $stmt = Db::prepare("SELECT text,handler,locked FROM texts WHERE title=?");
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
        self::replaceTab($arr['text']);
        $json_texts=json_decode($arr['text'], true);
        if(!is_array($json_texts) || count($json_texts) == 0) // if not valid json, json_decode return null, and count(null) is 0
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
        $url = Config::getParam('rootUrl').'plugins/'.$handler.'?param='.$param;
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
    protected static function replaceTab(&$text)
    {
        $text = str_replace('	', '    ', $text);
    }
}
?>
