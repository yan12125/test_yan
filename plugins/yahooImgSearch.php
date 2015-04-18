<?php
class YahooImgSearch extends PluginBase
{
    private $content = null;

    public function run($param)
    {
        $this->url = 'http://tw.images.search.yahoo.com/search/images?fr=yfp&ei=UTF-8&p='.urlencode($param);
        $ch = curl_init();
        curl_setopt_array($ch, array(
            CURLOPT_URL => $this->url,
            CURLOPT_RETURNTRANSFER => 1,
            CURLOPT_USERAGENT => Util::FIREFOX_UA
        ));
        $this->content = curl_exec($ch);
        curl_close($ch);

        $dom = new simple_html_dom();
        $dom->load($this->content);
        $items = $dom->find('.ld a');
        $n = rand(0, count($items) - 1);
        $url1 = $items[$n]->href;
        $dom->clear();
        parse_str(parse_url($url1, PHP_URL_QUERY), $data2);
        return $param."\n".'http://'.$data2['imgurl'];
    }

    public function handleException($e)
    {
        return array(
            'message' => $e->getMessage(),
            'url' => $this->url,
            'content' => $this->content,
        );
    }
}
?>
