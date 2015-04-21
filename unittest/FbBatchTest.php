<?php
require './common_inc.php';

class FbBatchTest extends PHPUnit_Framework_TestCase
{
    public function testApi()
    {
        $result = Fb::api('/100002601797125');
        $this->assertEquals("顏志軒", $result['name']);
    }

    public function testMultipleGet()
    {
        $req = new FbBatch();
        $req->push(null, '/100002601797125');
        $req->push(null, '/100002601797125');
        $result = $req->run();
        $this->assertEquals("顏志軒", $result[0]['name']);
        $this->assertEquals("顏志軒", $result[1]['name']);
    }
}
?>
