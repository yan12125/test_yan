<?php
class Db
{
    protected static $db = null;

    protected static function loadDB()
    {
        if(!is_null(self::$db))
        {
            return;
        }
        $dbConf = Config::getParamArr(array('sqlhost', 'dbname', 'sqlPort', 'sqlusername', 'mysqlPass'), true);
        $dsn = 'mysql:'.http_build_query(array(
            'host' => $dbConf['sqlhost'], 
            'dbname' => $dbConf['dbname'], 
            'port' => $dbConf['sqlPort'], 
            'charset' => 'utf8'
        ), '', ';');
        self::$db = new PDO($dsn, $dbConf['sqlusername'], $dbConf['mysqlPass']);
        self::$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    }

    public static function __callStatic($name, $args)
    {
        self::loadDB();
        $ret = call_user_func_array(array(self::$db, $name), $args);
        if($ret === false && $name == 'query')
        {
            throw new Exception(self::getErr());
        }
        return $ret;
    }

    public static function getErr()
    {
        // self::loadDB();
        $errInfo = self::$db->errorInfo();
        return $errInfo[2];
    }

    public static function getStmtErr($stmt)
    {
        if(!method_exists($stmt, 'errorInfo'))
        {
            throw new Exception('Not a valid PDOStatement');
        }
        $errInfo = $stmt->errorInfo();
        return $errInfo[2];
    }

    public static function getConfig($key)
    {
        $stmt = self::prepare("SELECT value FROM main WHERE name=?");
        $stmt->execute(array($key));
        $arr_result=$stmt->fetch(PDO::FETCH_NUM);
        return $arr_result[0];
    }

    public static function queryToArray($sql)
    {
        Util::ip_only('127.0.0.1');
        External::loadPhp('php-sql-parser');
        $stmt = self::prepare($sql);
        $ret = $stmt->execute();
        // determine the type of query
        $parser = new PHPSQLParser();
        $result = $parser->parse($sql);
        if(isset($result['UPDATE']) || isset($result['INSERT']) || isset($result['DELETE']))
        {
            return array($stmt->rowCount().' rows updated');
        }
        else
        {
            $retval = $stmt->fetchAll(PDO::FETCH_ASSOC);
            if(count($retval) == 0)
            {
                $retval = array('No results');
            }
            return $retval;
        }
    }
}
?>
