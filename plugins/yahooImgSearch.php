<?php
class YahooImgSearch extends PluginBase
{
    protected $ch = null;

    public function __construct()
    {
        $this->ch = curl_init();
    }

    public function __destruct()
    {
        curl_close($this->ch);
    }

    public function run($param)
    {
        curl_setopt_array($this->ch, array(
            CURLOPT_URL => 'http://tw.image.search.yahoo.com/search/images?fr=yfp&ei=UTF-8&p='.$param, 
            CURLOPT_RETURNTRANSFER => 1, 
            CURLOPT_USERAGENT => Util::FIREFOX_UA
        ));
        $data = curl_exec($this->ch);
        $dom = new simple_html_dom();
        $dom->load($data);
        $items = $dom->find('.ld');
        $n = rand(0, count($items) - 1);
        $url1 = $items[$n]->children(0)->href;
        $data2 = null;
        parse_str(parse_url($url1, PHP_URL_QUERY), $data2);
        return $param."\n".'http://'.$data2['imgurl'];
    }

    public function handleException($e)
    {
    }
}
?>
