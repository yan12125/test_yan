<?php
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
        try
        {
            $data = json_decode($msg, true);
            $uids = $data['uids'];
            unset($data['uids']);
            Util::$debug = isset($data['debug']);
            $result = Post::postUids($uids, $data);
            /*
             * JSON_PARTIAL_OUTPUT_ON_ERROR
             * Backtrace may contains resources, which cause json_encode() to fail
             * This option ignores errors and continue
             */
            $retval = Util::jsonEncode($result, JSON_UNESCAPED_UNICODE | JSON_PARTIAL_OUTPUT_ON_ERROR);
            print("Result: ".$retval."\n");
            $from->send($retval);
        }
        catch(Exception $e)
        {
            $response_error = array();
            Util::handleException($e, $response_error, false);
            file_put_contents("output.log", print_r($response_error, true));
            $from->send(json_encode($response_error, JSON_PARTIAL_OUTPUT_ON_ERROR));
        }
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
?>
