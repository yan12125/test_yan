<?php
class Texts
{
    public static function listTitles()
    {
        $stmt = Db::query("SELECT `title`,`lines` FROM texts where locked=0");
        $arr = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $titles = $lines = array();
        for($i = 0; $i < count($arr); $i++)
        {
            $titles[] = $arr[$i]['title'];
            $lines[] = $arr[$i]['lines'];
        }
        return array(
            'titles' => $titles, 
            'lines' => $lines
        );
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
        $validHandlers = Plugins::getPlugins();
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
        $linesObj = self::updateLines($title, $texts);

        // logging
        $userData = Users::getDataFromFb($access_token, array('token'));
        $stmt = Db::prepare('insert texts_log (uid,title,update_time) values (?,?,?)');
        $stmt->execute(array($userData['uid'], $title, Util::timestr()));
        return array(
            'status' => 'success', 
            'lines' => $linesObj['line_count']
        );
    }

    public static function updateLines($title, $texts = '')
    {
        if($texts === '')
        {
            $stmt = Db::prepare('SELECT text FROM texts WHERE title = ?');
            $stmt->execute(array($title));
            $results = $stmt->fetchAll(PDO::FETCH_NUM);
            if(count($results) === 0)
            {
                throw new Exception("Title not found.");
            }
            $texts = $results[0][0];
        }
        $texts = json_decode($texts, true);
        $stmt = Db::prepare('UPDATE texts SET `lines` = ? WHERE `title` = ?');
        $stmt->execute(array(count($texts), $title));
        return array('line_count' => count($texts));
    }

    public static function updateAllTitles()
    {
        $results = array();
        $titles = self::listTitles();
        for($i = 0; $i < count($titles); $i++)
        {
            $results[] = array(
                'title' => $titles[$i], 
                'line_count' => self::updateLines($titles[$i])['line_count']
            );
        }
        return $results;
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
        if($arr['locked'] && $m == -1)
        {
            return array(
                'error' => 'title_locked', 
                'title' => $title, 
                'msg' => "(title locked)", 
                'm' => -1 // used in output in post.php
            );
        }
        return self::getTextFromTexts($title, $arr['handler'], $arr['text'], $m);
    }

    public static function getTextFromTexts($title, $handler, &$texts, $m = -1)
    {
        Util::replaceTab($texts);
        $json_texts=json_decode($texts, true);
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
        if(!is_null($handler) && $handler !== '__none__')
        {
            $ret_val = array_merge($ret_val, Plugins::callPlugin($handler, $json_texts[$m]));
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

    public static function textsLog($page, $rows)
    {
        // SQL Inner Join
        // http://www.dotblogs.com.tw/hatelove/archive/2010/01/23/sql-join-concept.aspx
        $stmt = Db::query('SELECT name,title,update_time FROM users,texts_log WHERE users.uid = texts_log.uid ORDER BY update_time DESC');
        $data = $stmt->fetchAll();
        $data_chunked = array_chunk($data, $rows);
        if($page > count($data_chunked) || $page <= 0)
        {
            throw new Exception('Invalid page number');
        }
        return array(
            'records' => count($data), 
            'total' => count($data_chunked), 
            'rows' => $data_chunked[$page - 1]
        );
    }

    public static function searchText($term)
    {
        $stmt = Db::prepare('SELECT title FROM texts WHERE text like ? AND locked=0');
        $stmt->execute(array('%'.$term.'%'));
        $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
        return array_map(function ($item) { return $item['title']; }, $data);
    }
}
?>
