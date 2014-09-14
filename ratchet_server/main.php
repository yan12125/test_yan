<?php
require '../common_inc.php';

use Ratchet\MessageComponentInterface;
use Ratchet\ConnectionInterface;

class Handler implements MessageComponentInterface
{
    protected $client = null;

    public function __construct()
    {
    }

    public function onOpen(ConnectionInterface $conn)
    {
        print("A connection established\n");
        if(!is_null($this->client))
        {
            $conn->close();
            return;
        }
        $this->clients = $conn;
    }

    public function onMessage(ConnectionInterface $from, $msg)
    {
        print("Msg: ".$msg."\n");
        $data = json_decode($msg, true);
        $uids = $data['uids'];
        unset($data['uids']);
        $result = Post::postUids($uids, $data);
        $from->send(Util::jsonEncode($result));
    }

    public function onClose(ConnectionInterface $conn)
    {
        print("A connection closed\n");
        $this->client = null;
    }

    public function onError(ConnectionInterface $conn, \Exception $e)
    {
        $conn->close();
        throw($e);
    }
}

$app = new Ratchet\App('localhost', 23456);
$app->route('/', new Handler, array('*'));
$app->run();
?>
