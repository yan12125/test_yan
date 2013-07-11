<?php
error_reporting(E_ALL|E_STRICT);

checkPHP();

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

set_error_handler(array('Util', 'errorHandler'));

function checkPHP()
{
    // check PHP version
    if(PHP_MAJOR_VERSION < 5 || PHP_MINOR_VERSION < 3)
    {
        echo 'Require PHP 5.3 or higher.';
        exit(0);
    }

    // check extensions
    $required = array('curl', 'PDO', 'pdo_mysql', 'openssl', 'mbstring', 'iconv');
    $loaded = get_loaded_extensions();
    $diff = array_diff($required, $loaded);
    if(count($diff) != 0)
    {
        foreach($diff as $ext)
        {
            echo "Extension {$ext} required.\n";
        }
        exit(0);
    }
}
?>
