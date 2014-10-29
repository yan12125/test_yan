<?php
class Db
{
    protected static $db = null;
    protected static $config = array();

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
        return self::callStaticImpl($name, $args, true);
    }

    public static function callStaticImpl($name, $args, $retry)
    {
        self::loadDB();
        return call_user_func_array(array(self::$db, $name), $args);
    }

    public static function getErrInfo()
    {
        // self::loadDB();
        return self::$db->errorInfo();
    }

    public static function getErr()
    {
        $errInfo = self::getErrInfo();
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
        if(isset(self::$config[$key]))
        {
            return self::$config[$key];
        }
        $stmt = self::query("SELECT * FROM main");
        $arr_result=$stmt->fetchAll(PDO::FETCH_ASSOC);
        $retval = null;
        for($i = 0; $i < count($arr_result); $i++)
        {
            $name = $arr_result[$i]['name'];
            $value = $arr_result[$i]['value'];
            self::$config[$name] = $value;
            if($name == $key)
            {
                $retval = $value;
            }
        }
        return $retval;
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
