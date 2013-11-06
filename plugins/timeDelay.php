<?php
class TimeDelay extends PluginBase
{
    protected $param = -1;

    public function run($param)
    {
        $this->param = $param;
        $timeout = floatval($param); // floating point number in seconds
        if($timeout <= 0)
        {
            throw new Exception('Invalid timeout');
        }
        usleep($timeout * 1000000);
        return $timeout.' seconds passed.';
    }

    public function handleException($e)
    {
        return array(
            'message' => $e->getMessage(), 
            'param' => $this->param
        );
    }
}
?>
