<?php
abstract class PluginBase
{
    abstract public function run($param);
    abstract public function handleException($e);
}
?>
