<?php
// generating errors for testing error handling
class Error extends PluginBase
{
    public function run($param)
    {
        throw new Exception($param);
    }

    public function handleException($e)
    {
        return array(
            'source' => 'Given source name', 
            'uuid' => uniqid(), 
            'message' => $e->getMessage()
        );
    }
}
?>
