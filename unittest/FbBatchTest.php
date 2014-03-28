<?php
require './common_inc.php';

class FbBatchTest extends PHPUnit_Framework_TestCase
{
    public function testApi()
    {
        $result = Fb::api('/100002601797125');
        $this->assertEquals($result['name'], "Yen ChiHsuan");
    }
}
?>
