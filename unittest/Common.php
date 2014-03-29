<?php
class Common extends PHPUnit_Framework_TestCase
{
    public function setUp()
    {
        Util::$debug = false;
        Texts::$ignoreLocked = false;
    }
}
