<?php
class JsonEncodeFailure extends Exception
{
    private $arr;

    public function __construct($arr, $previous = null)
    {
        parent::__construct(json_last_error_msg(), 0, $previous);
        $this->arr = $arr;
    }

    public function getData()
    {
        return $this->arr;
    }
}
?>
