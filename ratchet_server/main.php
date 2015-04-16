<?php
chdir(dirname(__FILE__));
require '../common_inc.php';
require './handler.php';

pcntl_signal(SIGTERM, "signal_handler");
pcntl_signal(SIGINT, "signal_handler");

function signal_handler($signal)
{
}

function main()
{
    $port = Config::getParam('wsPort');
    print("Listening on port ".$port."\n");
    try
    {
        $app = new Ratchet\App('localhost', $port);
        $app->route('/', new Handler(), array('*'));
        $app->run();
    }
    catch(Exception $e)
    {
        print($e->getMessage()."\n");
        print($e->getTraceAsString());
        echo "\nExitting...\n";
    }
}

main();
?>
