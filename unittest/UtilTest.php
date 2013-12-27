<?php
require 'PHPUnit/Framework/TestCase.php';
require '../common_inc.php';

class UtilTest extends PHPUnit_Framework_TestCase
{
    /**
     * @expectedException ErrorException
     * @expectedExceptionMessage Test exception
     */
    public function testErrorHandler()
    {
        Util::errorHandler(8, 'Test exception', __FILE__, __LINE__);
    }

    public function testGetSeverityStr()
    {
        $this->assertEquals('E_ERROR', Util::getSeverityStr(1));
        $this->assertNull(Util::getSeverityStr(100));
    }

    public function testAddIncludePath()
    {
        $old_path = set_include_path('/');
        Util::addIncludePath('/lib');
        $this->assertEquals('/:/lib', get_include_path());
        set_include_path($old_path);
    }

    public function testReplaceTab()
    {
        $str = "\t";
        Util::replaceTab($str);
        $this->assertEquals('    ', $str);
    }

    public function testTimeStr()
    {
        $this->assertEquals('2013/12/28 02:42:16', Util::timestr(1388169736));
        $this->assertEquals(Util::timestr(), Util::timestr(time()));
    }

    public function testTryParseJson()
    {
        $this->assertEquals(array(1, 2, 3), Util::tryParseJson('[1,2,3]'));
        $this->assertEquals('asdf', Util::tryParseJson('asdf'));
        $this->assertNull(Util::tryParseJson(null));
    }
}
?>
