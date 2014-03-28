<?php
require_once './common_inc.php';

class PostTest extends PHPUnit_Framework_TestCase
{
    public function testInitExceptions()
    {
        Util::$debug = true;
        $uid = 100004005107949;
        $ret = Post::postUids($uid, array());
        $this->assertArrayNotHasKey($uid, $ret);
    }
}
