<?php
class SingleUserPostError extends Exception
{
    private $userId;

    public function __construct($message, $userId, $previous = null)
    {
        parent::__construct($message, 0, $previous);
        $this->userId = $userId;
    }

    public function getUserId()
    {
        return $this->userId;
    }
}
?>
