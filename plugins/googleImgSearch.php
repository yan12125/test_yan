<?php
use \Curl\Curl;

class GoogleImgSearch extends PluginBase
{
    protected $url;
    protected $content;
    protected $keyword;
    protected $last_error;

    public function __construct()
    {
        $this->url = null;
        $this->content = null;
        $this->keyword = null;
        $this->last_error = null;
    }

    public function run($param)
    {
        $this->keyword = $param;
        $this->url = 'https://www.google.com/search?tbm=isch&hl=zh-TW&oe=utf8&q='.urlencode($param);

        // get the html of search result page
        $ch = curl_init();
        curl_setopt_array($ch, array(
            CURLOPT_URL => $this->url,
            CURLOPT_USERAGENT => Util::CHROME_UA,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_RETURNTRANSFER => true,
        ));
        $this->content = curl_exec($ch);
        if(curl_error($ch) !== '')
        {
            $this->last_error = curl_error($ch);
        }
        curl_close($ch);
        $ret = $this->keyword . "\n" . $this->parseResult();
        return $ret;
    }

    protected function parseResult()
    {
        if(preg_match_all('/\<div[^>]+class="[^"]*rg_di[^"]*"[^>]*\>\s*\<a[^>]+href="([^"]+)"/', $this->content, $matches))
        {
            $matches = $matches[1];
            $link = Util::array_rand_item($matches);
            $query = html_entity_decode(parse_url($link)['query']);
            parse_str($query, $parts);
            return $parts['imgurl'];
        }
        throw new Exception('Can\'t retrieve images');
    }

    public function handleException($e)
    {
        $output = array(
            'message' => $e->getMessage(), 
            'keyword' => $this->keyword, 
            'url' => $this->url, 
            'content' => $this->content
        );
        Logger::write($e->getMessage(), Logger::ERROR, is_null($this->content)?"":$this->content);
        if(!is_null($this->last_error))
        {
            $output['curl_error'] = $this->last_error;
        }
        return $output;
    }
}
?>
