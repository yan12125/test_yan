<?php
require './common_inc.php';

class FbBatchTest extends PHPUnit_Framework_TestCase
{
    public function testApi()
    {
        $result = Fb::api('/100002601797125');
        $this->assertEquals($result['name'], "Yen ChiHsuan");
    }

    public function testMultipleGet()
    {
        $req = new FbBatch();
        $req->push(null, '/100002601797125');
        $req->push(null, '/100002601797125');
        $result = $req->run();
        $this->assertEquals($result[0]['name'], "Yen ChiHsuan");
        $this->assertEquals($result[1]['name'], "Yen ChiHsuan");
    }
}
?>
