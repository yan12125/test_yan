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
        $directories = array('lib', 'plugins');
        foreach($directories as $directory)
        {
            // lcfirst requires PHP 5.3.0
            $absPath = APP_ROOT.$directory.'/'.lcfirst($className).'.php';
            if(file_exists($absPath))
            {
                require $absPath;
                break;
            }
        }
    }
    spl_autoload_register('autoload');
}

require APP_ROOT.'vendor/autoload.php';

set_error_handler(array('Util', 'errorHandler'));

/*
 * checking PHP version and required extensions
 */
Util::checkPHP();
?>
