<?php
class Texts
{
    public static $ignoreLocked = false;

    protected static function listTitlesImpl($assoc)
    {
        $stmt = Db::query("SELECT * FROM texts");
        $arr = $stmt->fetchAll(PDO::FETCH_ASSOC);
        if($assoc) // titles as keys
        {
            $retval = array();
            for($i = 0; $i < count($arr); $i++)
            {
                $row = $arr[$i];
                $retval[$row['title']] = $row;
            }
            return $retval;
        }
        else
        {
            $titles = $lines = $locked = $handlers = array();
            for($i = 0; $i < count($arr); $i++)
            {
                $titles[] = $arr[$i]['title'];
                $lines[] = $arr[$i]['lines'];
                $locked[] = (int)$arr[$i]['locked'];
                $handlers[] = $arr[$i]['handler'];
            }
            return array(
                'titles' => $titles, 
                'lines' => $lines, 
                'locked' => $locked, 
                'handlers' => $handlers
            );
        }
    }

    public static function listTitles()
    {
        return self::listTitlesImpl(false);
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

    public static function addTitle($title, $access_token)
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

        // need to log before adding title to filter invalid users
        self::logTextModification($title, $access_token);

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
            throw new Exception('No strings given');
        }

        // the same reason as self::addTitle()
        self::logTextModification($title, $access_token);

        $texts = implode("\n", $textArr);
        $stmt = Db::prepare('update texts set text=?,handler=? where title=?');
        if($stmt->execute(array($texts, $handler, $title)) === false)
        {
            throw new Exception("PDO execute() failed: ".Db::getErr());
        }
        $linesObj = self::updateLines($title, $texts);

        return array(
            'status' => 'success', 
            'lines' => $linesObj['line_count']
        );
    }

    protected static function logTextModification($title, $access_token)
    {
        $userData = Users::getDataFromFb($access_token, array('token'));
        $stmt = Db::prepare('insert texts_log (uid,title,update_time) values (?,?,?)');
        $stmt->execute(array($userData['uid'], $title, Util::timestr()));
    }

    public static function getTexts($title)
    {
        $stmt = Db::prepare("SELECT locked,handler,text FROM texts WHERE title=?");
        $stmt->execute(array($title));
        $arr = $stmt->fetch(PDO::FETCH_ASSOC);
        if($arr === false) // no title matches
        {
            throw new Exception('Unable to find title '.$title);
        }
        $text = $arr['text'];
        Util::replaceTab($text);
        return array(
            'handler' => is_null($arr['handler'])?'__none__':$arr['handler'], 
            'text' => $text, 
            'locked' => (int)$arr['locked']
        );
    }

    /*
     * Get random text from $title
     * if $m is given, select specified one (for debugging purpose)
     */
    public static function getTextFromTitle($title, $m = -1)
    {
        if($m == -1)
        {
            $stmt = Db::prepare('
                SELECT texts_data.line, texts.locked, texts.handler, texts_data.ID AS `index`
                FROM texts_data, texts, ( SELECT FLOOR(RAND() * `lines`) AS num FROM texts WHERE title = :title) AS rand_id
                WHERE texts_data.ID = rand_id.num AND texts_data.title = :title AND texts.title = texts_data.title
            ');
            $stmt->execute(array('title' => $title));
        }
        else
        {
            $stmt = Db::prepare('
                SELECT texts_data.line, texts.locked, texts.handler, texts_data.ID AS `index` 
                FROM texts_data, texts 
                WHERE texts_data.ID = :id AND texts_data.title = :title AND texts_data.title = texts.title
            ');
            $stmt->execute(array('id' => $m, 'title' => $title));
        }
        $data = $stmt->fetch(PDO::FETCH_ASSOC);
        if($data===false)
        {
            return array('error'=> 'Error query data!', 'msg' => NULL, 'title' => $title);
        }
        if(!self::$ignoreLocked && $data['locked'] && $m == -1)
        {
            // $m used in output in post.php
            return array( 'error' => 'title_locked', 'title' => $title, 'msg' => "(title locked)", 'm' => -1);
        }
        return self::handlePlugins($title, $data['handler'], $data['line'], $data['index']);
    }

    public static function getTextFromTexts($title, $handler, &$texts, $m = -1)
    {
        if($texts === "")
        {
            self::stripTitle($title);
            return array(
                'error' => 'Texts in specified title not valid!', 
                'title' => $title, 
                'msg' => null, 
                'source' => 'Texts'
            );
        }
        Util::replaceTab($texts);
        $json_texts = explode("\n", $texts);
        if($m < 0 || $m >= count($json_texts)) // if $m == -1 or invalid, randomly select a number
        {
            $m = rand(0, count($json_texts)-1);
        }
        return self::handlePlugins($title, $handler, $json_texts[$m], $m);
    }

    protected static function handlePlugins($title, $handler, $text, $m)
    {
        $ret_val = array(
            'm' => $m, 
            'title' => $title
        );
        if(!is_null($handler) && $handler !== '__none__')
        {
            $ret_val = array_merge($ret_val, Plugins::callPlugin($handler, $text));
        }
        else
        {
            // no need to escape html special chars because facebook will do it
            $ret_val['msg'] = $text;
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

    public static function checkTitlesJSON($string)
    {
        $givenTitles = json_decode($string, true);
        if(!is_array($givenTitles) || Util::isAssoc($givenTitles))
        {
            throw new Exception('Titles should be a JSON array.');
        }
        $realTitles = self::listTitlesImpl(true);
        for($i = 0; $i < count($givenTitles); $i++)
        {
            $curTitle = $givenTitles[$i];
            if(!isset($realTitles[$curTitle]))
            {
                throw new Exception("Title '{$curTitle}' not found.");
            }
            $row = $realTitles[$curTitle];
            if($row['locked'] == 1 || $row['lines'] <= 0)
            {
                throw new Exception("Title '{$curTitle}' locked or invalid.");
            }
        }
    }

    protected static function stripTitle($title)
    {
        // $title is not escaped here, while data in the database might be encoded (browser dependent)
        $stmt = Db::prepare("SELECT uid FROM users WHERE titles LIKE ? OR titles LIKE ?");
        $stmt->execute(array('%'.$title.'%', '%'.Util::escapeUtf8($title, true).'%'));
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
        for($i = 0; $i < count($results); $i++)
        {
            $uid = $results[$i]['uid'];
            $stmt2 = Db::prepare("SELECT access_token,titles FROM users WHERE uid = ?");
            $stmt2->execute(array($uid));
            $results2 = $stmt2->fetch(PDO::FETCH_ASSOC);
            $titles = json_decode($results2['titles'], true);
            $new_titles = array_values(array_filter($titles, function ($item) use ($title) {
                return ($item != $title) && ($item != Util::escapeUtf8($title, true));
            }));
            if(count($titles) == count($new_titles))
            {
                continue;
            }
            $stmt3 = Db::prepare("UPDATE users SET titles = ? WHERE uid = ?");
            $stmt3->execute(array(json_encode($new_titles), $uid));
            $access_token = $results2['access_token'];
            if(count($new_titles) == 0)
            {
                Users::setUserStatus($uid, 'stopped', $access_token);
            }
            Logger::write("Title \"{$title}\" stripped from user {$uid}");
        }
    }
}
?>
