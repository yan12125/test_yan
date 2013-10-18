<?php
class Plugins
{
    protected static $lastPluginError;
    protected static $lastPlugin;

    public static function callPlugin($handler, $param)
    {
        $instance = null;
        self::$lastPlugin = $handler;
        try
        {
            if(!self::isValidPlugin($handler))
            {
                throw new Exception('Invalid plugin name');
            }
            $instance = new $handler();
            return array('msg' => $instance->run($param));
        }
        catch(Exception $e)
        {
            if(is_null($instance)) // failed to instantiate a plugin
            {
                self::$lastPluginError = $err = $e->getMessage();
            }
            else
            {
                self::$lastPluginError = $err = $instance->handleException($e);
            }
            $errStr = '';
            if(is_string($err))
            {
                $errStr .= $handler . ': ' . $err;
            }
            else if(isset($err['message']))
            {
                if(isset($err['source']))
                {
                    $errStr = $err['source'];
                }
                else
                {
                    $errStr = $handler;
                }
                // $err['message'] should be a string
                $errStr .= ': ' . rtrim($err['message'], "\n");
            }
            else
            {
                $errStr = 'Error from the plugin "' . $handler . '"';
            }
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
            $className = ucfirst($matches[1]);
            if(self::isValidPlugin($className))
            {
                $plugins[] = $className;
            }
        }
        closedir($pluginDir);
        return $plugins;
    }

    public static function isValidPlugin($className)
    {
        // http://stackoverflow.com/questions/8806912
        if(class_exists($className))
        {
            $reflector = new ReflectionClass($className);
            if($reflector->isSubclassOf('PluginBase'))
            {
                return true;
            }
        }
        return false;
    }

    public static function report_fields(&$output)
    {
        $output['last_plugin_error'] = self::$lastPluginError;
        $output['last_plugin'] = self::$lastPlugin;
    }
}
?>
