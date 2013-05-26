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
        require APP_ROOT.'lib'.'/'.$lowerClassName.'.php';
    }
    spl_autoload_register('autoload');
}

set_error_handler(array('Util', 'errorHandler'));

?>
