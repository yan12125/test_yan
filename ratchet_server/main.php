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

function parse_argv(&$port)
{
    $options = getopt('', array('port:'));
    if(isset($options['port']))
    {
        if(!is_numeric($options['port']))
        {
            print("Error: Non-numeric value {$options['port']} for port\n");
            return -1;
        }
        $value= intval($options['port']);
        if($value >= 65536 || $value <= 0)
        {
            print("Error: Invalid port value {$value}\n");
            return -2;
        }
        $port = $value;
    }
    return 0;
}

function main()
{
    $port = Config::getParam('wsPort');
    if(parse_argv($port) < 0)
    {
        return;
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
        print($e->getTraceAsString()."\n");
        print("Exitting...\n");
    }
}

main();
?>
