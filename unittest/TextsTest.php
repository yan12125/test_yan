<?php
require_once './common_inc.php';
require_once './unittest/Common.php';

class TextsTest extends Common
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
            'msg' => null, 
            'source' => 'Texts'
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

    public function testListTitles()
    {
        $titles = Texts::listTitles();
        $this->assertEquals(count($titles['titles']), count($titles['locked']));
        $this->assertInternalType('integer', $titles['locked'][0]);
    }

    public function testCheckTitle()
    {
        $this->assertTrue(Texts::checkTitle('empty'));
        $this->assertFalse(Texts::checkTitle('does_not_exist'));
    }

    /**
     * @expectedException Exception
     * @expectedExceptionMessage 標題不得為空白！
     */
    public function testAddTitle_EmptyTitle()
    {
        Texts::addTitle('', 'invalid_token');
    }

    /**
     * @expectedException Exception
     * @expectedExceptionMessage 指定的標題已存在！
     */
    public function testAddTitle_TitleExists()
    {
        Texts::addTitle('empty', 'invalid_token');
    }

    /**
     * @expectedException Exception
     * @expectedExceptionMessage Invalid OAuth access token.
     */
    public function testAddTitle_InvalidToken()
    {
        Texts::addTitle('does_not_exist', 'invalid_token');
    }

    /**
     * @expectedException Exception
     * @expectedExceptionMessage 指定的標題不存在！
     */
    public function testUpdateText_TitleDoesNotExist()
    {
        Texts::updateText('does_not_exist', 'text', '__none__', 'invalid_token');
    }

    /**
     * @expectedException Exception
     * @expectedExceptionMessage 指定的外掛不存在！
     */
    public function testUpdateText_PluginDoesNotExist()
    {
        Texts::updateText('empty', 'text', 'does_not_exist', 'invalid_token');
    }

    /**
     * @expectedException Exception
     * @expectedExceptionMessage No strings given
     */
    public function testUpdateText_EmptyContent()
    {
        Texts::updateText('empty', '', '__none__', 'invalid_token');
    }

    /**
     * @expectedException Exception
     * @expectedExceptionMessage Invalid OAuth access token.
     */
    public function testUpdateText_InvalidToken()
    {
        Texts::updateText('empty', 'text', '__none__', 'invalid_token');
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
