<?php
class Config
{
    protected static $params = null;

    protected static function loadParam()
    {
        if(!is_null(self::$params))
        {
            return;
        }
        $oldPath = getcwd();
        chdir(dirname(__FILE__));
        $searchPaths = array('./', '../');
        $secretFilename = '.htsecret';
        foreach($searchPaths as $path)
        {
            if(file_exists($path.$secretFilename))
            {
                $strParams = file_get_contents($path.$secretFilename);
                chdir($oldPath);
                self::$params = json_decode($strParams, true);
                if(!is_array(self::$params))
                {
                    throw new Exception('Failed to load config file');
                }
                return;
            }
        }
        throw new Exception('Config file not found');
    }

    public static function getParam($key)
    {
        self::loadParam();
        if(isset(self::$params[$key]))
        {
            return self::$params[$key];
        }
        throw new Exception(json_encode(array(
            'error' => 'Key not found in config file', 
            'key' => $key, 
            'config' => self::$params
        )));
    }

    public static function getParamArr(array $keys, $delete = false)
    {
        $result = array();
        foreach($keys as $key)
        {
            $result[$key] = self::getParam($key);
            if($delete === true)
            {
                unset(self::$params[$key]);
            }
        }
        return $result;
    }
}
?>
