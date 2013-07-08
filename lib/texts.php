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
        return $num[0] > 0;
    }

    public static function addTitle($title)
    {
        if(self::checkTitle($title)) // title exists
        {
            return array('error' => 'title_exists');
        }
        $stmt = Db::prepare('insert texts (title,handler,text) values(?,NULL,"")');
        $stmt->execute(array($title));
    }

    public static function updateText($title, $texts)
    {
        if(self::checkTitle($title))
        {
            throw new Exception('Specified title does not exists.');
        }
        $textArr = explode("\n", str_replace("\r\n", "\n", $texts));

        // remove empty lines
        function remove_empty($item)
        {
            return trim($item) != '';
        }
        // array_filter preserve keys, but we want continuous array for json_encode
        $textArr = array_values(array_filter($textArr, 'remove_empty'));
        if(count($textArr) == 0)
        {
            return array('error' => 'No strings given');
        }

        $texts = Util::json_unicode($textArr);
        $stmt = Db::prepare('update texts set text=? where title=?');
        if($stmt->execute(array($texts, $title)) === false)
        {
            throw new Exception("PDO execute() failed: ".Db::getErr());
        }
        return array('status' => 'success');
    }

    protected static function check()
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
        $stmt = Db::prepare("SELECT handler,text FROM texts WHERE title=?");
        $stmt->execute(array($title));
        $arr = $stmt->fetch(PDO::FETCH_ASSOC);
        if($arr === false) // no title matches
        {
            throw new Exception('Unable to find title '.$title);
        }
        $text = $arr['text'];
        Util::replaceTab($text);
        $json_text = json_decode($text, true);
        if(is_array($json_text))
        {
            return array(
                'handler' => is_null($arr['handler'])?'None':$arr['handler'], 
                'text' => implode("\n", $json_text)
            );
        }
        else
        {
            return array('msg' => $text);
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
        Util::replaceTab($arr['text']);
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
            // no need to escape html special chars because facebook will do it
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
        $instance = null;
        try
        {
            $instance = new $handler();
            return array('msg' => $instance->run($param));
        }
        catch(Exception $e)
        {
            if(method_exists($instance, 'handleException'))
            {
                $errStr = json_encode($instance->handleException($e));
                throw new Exception($errStr);
            }
            else
            {
                throw $e;
            }
        }
    }
}
?>
