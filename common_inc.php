<?php
error_reporting(E_ALL|E_STRICT);

define('APP_ROOT', dirname(__FILE__).'/');

/*
 * Class autoloading
 * */
if(!function_exists('autoload'))
{
    function autoload($className)
    {
        $lowerClassName = strtolower($className[0]).substr($className, 1);
        $directories = array('lib', 'plugins');
        foreach($directories as $directory)
        {
            $absPath = APP_ROOT.$directory.'/'.$lowerClassName.'.php';
            if(file_exists($absPath))
            {
                require $absPath;
                break;
            }
        }
    }
    spl_autoload_register('autoload');
}

set_error_handler(array('Util', 'errorHandler'));

?>
