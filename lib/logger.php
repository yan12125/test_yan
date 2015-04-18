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

        $ip = Util::remote_ip();
        $function = Util::getCallerNameAndLine(1);

        $stmt = Db::prepare('INSERT INTO log (time,ip,function,content,priority,additional_info) VALUES (?,?,?,?,?,?)');
        $stmt->execute(array($timestamp_float, $ip, $function, $content, $level, $additional_info));
    }
}
?>
