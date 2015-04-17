<?php
use Ratchet\Server\IoServer;
use Ratchet\Http\HttpServer;
use Ratchet\WebSocket\WsServer;

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
    $options = getopt('', array('port:'));
    if(isset($options['port']))
    {
        if(!is_numeric($options['port']))
        {
            print("Error: Non-numeric value {$options['port']} for port\n");
            return;
        }
        $port = intval($options['port']);
        if($port >= 65536 || $port <= 0)
        {
            print("Error: Invalid port value {$port}\n");
            return;
        }
    }
    print("Listening on port ".$port."\n");
    try
    {
        $server = IoServer::factory(new HttpServer(new WsServer(new Handler)), $port, '127.0.0.1');
        $server->run();
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
