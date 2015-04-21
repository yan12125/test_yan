<?php
require_once './common_inc.php';
require_once './unittest/Common.php';

class PluginsTest extends Common
{
    public function pluginsAndParams()
    {
        return array(
            array('GoogleImgSearch', 123),
            array('BingImgSearch', 123),
            array('YahooImgSearch', 123),
            array('PttReader', 'https://www.ptt.cc/bbs/Gossiping/index.html'),
            array('RssReader', 'http://www.cw.com.tw/RSS/cw_content.xml'),
            array('RandomString', '20,50'),
        );
    }

    /**
     * @dataProvider pluginsAndParams
     */
    public function testRunWithoutErrors($plugin_name, $param)
    {
        $handler = new $plugin_name;
        $this->assertInternalType('string', $handler->run($param));
    }
}
?>
