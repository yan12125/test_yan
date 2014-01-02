<?php
require_once 'PHPUnit/Framework/TestCase.php';
require_once './common_inc.php';

class TextsTest extends PHPUnit_Framework_TestCase
{
    protected function titleLocked($title)
    {
        return array(
            'error' => 'title_locked', 
            'title' => $title, 
            'msg' => "(title locked)", 
            'm' => -1
        );
    }

    protected function textInvalid($title)
    {
        return array(
            'error' => 'Texts in specified title not valid!', 
            'title' => $title, 
            'msg' => null
        );
    }

    protected function errorQueryData($title)
    {
        return array(
            'error'=> 'Error query data!', 
            'msg' => NULL, 
            'title' => $title
        );
    }

    public function testCheckTitle()
    {
        $this->assertTrue(Texts::checkTitle('empty'));
        $this->assertFalse(Texts::checkTitle('does_not_exist'));
    }

    /**
     * @expectedException Exception
     * @expectedExceptionMessage Unable to find title does_not_exist
     */
    public function testGetTextsErr()
    {
        Texts::getTexts('does_not_exist');
    }

    public function testGetTexts()
    {
        $this->assertEquals(Texts::getTexts('empty'), array('msg' => ''));
        $this->assertEquals(Texts::getTexts('Error'), 
            array('handler' => 'Error', 'text' => 'Just generates an error'));
    }

    public function testGetTextFromTitle()
    {
        $this->assertEquals(Texts::getTextFromTitle('does_not_exist'), 
                            $this->errorQueryData('does_not_exist'));
        $this->assertEquals(Texts::getTextFromTitle('empty'), 
                            $this->titleLocked('empty'));
        $this->assertEquals(Texts::getTextFromTitle('empty', 0), 
                            $this->textInvalid('empty'));
    }

    public function testGetTextFromTexts()
    {
        $texts = "";
        $this->assertEquals(Texts::getTextFromTexts('empty', null, $texts), 
                            $this->textInvalid('empty'));
    }
}
?>
