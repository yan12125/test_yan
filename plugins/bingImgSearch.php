<?php
use \Curl\Curl;

class BingImgSearch extends PluginBase
{
    protected $decoder = null;
    protected $url = null;
    protected $content = null;

    public function __construct()
    {
        $this->decoder = new Services_JSON(SERVICES_JSON_LOOSE_TYPE);
    }

    public function run($param)
    {
        $ch = new Curl();
        $this->url = "http://www.bing.com/images/search?q=".urlencode($param);

        $ch->get($this->url);
        $this->content = $ch->response;

        $dom = str_get_html($ch->response);
        $links = $dom->find('.dg_u a');
        $link = $links[array_rand($links)];
        $imgData = $this->decoder->decode(htmlspecialchars_decode($link->m));
        return $param."\n".$imgData['oi'];
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
