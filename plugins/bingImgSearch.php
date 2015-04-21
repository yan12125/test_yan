<?php
class BingImgSearch extends PluginBase
{
    protected $url = null;
    protected $content = null;

    public function run($param)
    {
        $ch = curl_init();
        $this->url = "http://www.bing.com/images/search?q=".urlencode($param);

        curl_setopt_array($ch, array(
            CURLOPT_URL => $this->url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_USERAGENT => Util::FIREFOX_UA,
        ));
        $this->content = curl_exec($ch);
        curl_close($ch);

        preg_match_all('/\<div class="dg_u"[^>]+\>\<a[^>]+m="([^"]+)"/', $this->content, $matches);
        $imgData = Util::decodeJsonLoose(htmlspecialchars_decode(Util::array_rand_item($matches[1])));
        return $param."\n".$imgData['imgurl'];
    }

    public function handleException($e)
    {
        return array(
            'url' => $this->url, 
            'content' => $this->content
        );
    }
}
?>
