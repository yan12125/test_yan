<?php
class Texts
{
    public static function listTitles()
    {
        $stmt = Db::query("SELECT title FROM texts where locked=0");
        $arr = $stmt->fetchAll(PDO::FETCH_NUM);
        $titles = array_map(function($item){
            return $item[0];
        }, $arr);
        return $titles;
    }

    public static function checkTitle($title)
    {
        // check that specified title exists or not
        // return true if such title exists
        $stmt = Db::prepare('select count(*) from texts where title=?');
        $stmt->execute(array($title));
        $num = $stmt->fetch(PDO::FETCH_NUM);
        return $num[0] > 0;
    }

    public static function addTitle($title)
    {
        $title = trim($title);
        if($title == '')
        {
            throw new Exception('標題不得為空白！');
        }
        if(self::checkTitle($title)) // title exists
        {
            throw new Exception('指定的標題已存在！');
        }
        $stmt = Db::prepare('insert texts (title,handler,text) values(?,NULL,"")');
        if($stmt->execute(array($title)) !== false)
        {
            return array('status' => 'success');
        }
        else
        {
            return array('error' => Db::getErr());
        }
    }

    public static function updateText($title, $texts, $handler, $access_token)
    {
        if(!self::checkTitle($title))
        {
            throw new Exception('指定的標題不存在！');
        }
        $validHandlers = self::getPlugins();
        array_unshift($validHandlers, '__none__');
        if(array_search($handler, $validHandlers) === false)
        {
            throw new Exception('指定的外掛不存在！');
        }
        if($handler == '__none__')
        {
            $handler = null;
        }
        $textArr = explode("\n", str_replace("\r\n", "\n", $texts));

        // array_filter preserve keys, but we want continuous array for json_encode
        $textArr = array_values(array_filter($textArr, array('Util', 'not_empty')));
        if(count($textArr) == 0)
        {
            return array('error' => 'No strings given');
        }

        $texts = Util::json_unicode($textArr);
        $stmt = Db::prepare('update texts set text=?,handler=? where title=?');
        if($stmt->execute(array($texts, $handler, $title)) === false)
        {
            throw new Exception("PDO execute() failed: ".Db::getErr());
        }

        // logging
        $userData = Users::getDataFromFb($access_token, array('token'));
        $stmt = Db::prepare('insert texts_log (uid,title,update_time) values (?,?,?)');
        $stmt->execute(array($userData['uid'], $title, Util::timestr()));
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
                'handler' => is_null($arr['handler'])?'__none__':$arr['handler'], 
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
        if(isset($ret_val['msg']) && !Util::not_empty($ret_val['msg']))
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
            // __construct should not throw an exception, and
            // all plugins should have a handleException method
            $errStr = json_encode($instance->handleException($e));
            throw new Exception($errStr);
        }
    }

    public static function getPlugins()
    {
        $pluginDir = opendir(APP_ROOT.'plugins');
        $plugins = array();
        while(true)
        {
            $entry = readdir($pluginDir);
            if($entry === false)
            {
                break;
            }
            if(!preg_match("/(.*)\\.php$/", $entry, $matches))
            {
                continue;
            }
            $plugins[] = ucfirst($matches[1]);
        }
        closedir($pluginDir);
        return $plugins;
    }
}
?>
