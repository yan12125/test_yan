<?php
require_once './common_inc.php';
require_once './unittest/Common.php';

class PostTest extends Common
{
    private $uid = 100004005107949;  // 張宗周
    private $uid2 = 100004871774624; // XiuLei Hua
    private $userData = array();

    public function setUp()
    {
        parent::setUp();
        $this->userData = Users::getData($this->uid, '*');
    }

    public function tearDown()
    {
        parent::tearDown();
        Users::setData($this->uid, $this->userData);
    }

    public function testInitExceptions()
    {
        Util::$debug = true;
        $ret = Post::postUids($this->uid, array());
        $this->assertArrayNotHasKey($this->uid, $ret);
    }

    public function testPreviousException()
    {
        Util::$debug = true;
        Texts::$ignoreLocked = true;
        Users::setData($this->uid, array('goal' => 99999999, 'titles' => json_encode(array("PluginError"))));
        $ret = Post::postUids($this->uid, array());
        $this->assertEquals('RssReader', $ret[$this->uid]['previous']['plugin']);
    }

    public function testMultipleUsers()
    {
        Util::$debug = true;
        Texts::$ignoreLocked = true;
        Users::setData($this->uid, array('goal' => 99999999, 'titles' => json_encode(array('PluginError'))));
        $ret = Post::postUids($this->uid.'_'.$this->uid2, array());
        $this->assertArrayHasKey('error', $ret[$this->uid]);
        $this->assertArrayNotHasKey('error', $ret[$this->uid2]);
    }
}
