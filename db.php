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
    }

    public static function query($sql)
    {
        self::loadDB();
        return self::$db->query($sql);
    }

    public static function prepare($sql)
    {
        self::loadDB();
        return self::$db->prepare($sql);
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
        $stmt = self::query($sql);
        if(!$stmt)
        {
            throw new Exception(Db::getErr());
        }
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
?>
