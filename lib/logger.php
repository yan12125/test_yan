<?php
class Logger
{
    CONST DEBUG   = 'debug';
    CONST INFO    = 'info';
    CONST WARNING = 'warning';
    CONST ERROR   = 'error';

    public static function write($content, $level = self::INFO, $additional_info = "")
    {
        $timestamp_float = microtime(true); // true indicates returned as a float

        $ip = '0.0.0.0';
        if(isset($_SERVER['REMOTE_ADDR']))
        {
            $ip = $_SERVER['REMOTE_ADDR'];
        }

        $backtrace = debug_backtrace();
        $function = '__main__'; // well, a python style
        if(count($backtrace) > 1) // at least Logger::write()
        {
            $previous_call = $backtrace[1];
            $function = $previous_call['function'];
            if(isset($previous_call['class']))
            {
                $function = $previous_call['class'] . '::' . $function;
            }
        }

        $stmt = Db::prepare('INSERT INTO log (time,ip,function,content,priority,additional_info) VALUES (?,?,?,?,?,?)');
        $stmt->execute(array($timestamp_float, $ip, $function, $content, $level, $additional_info));
    }
}
?>
