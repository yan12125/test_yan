<?php
/*
 * Class autoloading
 * */
if(!function_exists('autoload'))
{
    function autoload($className)
    {
        $lowerClassName = strtolower($className[0]).substr($className, 1);
        require $lowerClassName.'.php';
    }
    spl_autoload_register('autoload');
}

require_once 'util.php';

?>
