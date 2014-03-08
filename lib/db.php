<?php
class Db
{
    protected static $db = null;

    public static function getMysqlCredentials($local_only = true)
    {
        if($local_only)
        {
            Util::ip_only();
        }
        return Config::getParamArr(array('sqlhost', 'dbname', 'sqlPort', 'sqlusername', 'mysqlPass'));
    }

    protected static function loadDB()
    {
        if(!is_null(self::$db))
        {
            return;
        }
        $dbConf = self::getMysqlCredentials(false);
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
        Util::ip_only();
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
