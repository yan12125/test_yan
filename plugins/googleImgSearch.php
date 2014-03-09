<?php
class GoogleImgSearch extends PluginBase
{
    protected $ch;
    protected $url;
    protected $content;
    protected $keyword;

    public function __construct()
    {
        $this->ch = curl_init();
        $this->url = null;
        $this->content = null;
        $this->keyword = null;
    }

    public function __destruct()
    {
        curl_close($this->ch);
    }

    public function run($param)
    {
        $this->keyword = $param;
        $this->url = 'https://www.google.com/search?tbm=isch&hl=zh-TW&oe=utf8&q='.urlencode($param);

        // get the html of search result page
        curl_setopt_array($this->ch, array(
            CURLOPT_URL => $this->url, 
            CURLOPT_RETURNTRANSFER => 1, 
            CURLOPT_USERAGENT => Util::CHROME_UA
        ));
        $this->content = curl_exec($this->ch);
        if($this->content === false)
        {
            throw new Exception('curl_exec() failed');
        }
        return $this->keyword . "\n" . $this->parseResult();
    }

    public function parseResult($content = "")
    {
        $dom=new simple_html_dom();
        if($content != "")
        {
            $this->content = $content;
        }
        $dom->load($this->content);

        // start to analyze
        $links = $dom->find('.rg_di a');
        if(count($links) >= 1)
        {
            $m = rand(0, count($links) - 1);
            $link = $links[$m]->href;
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
            'url' => $this->url
        );
        Logger::write($e->getMessage(), Logger::ERROR, is_null($this->content)?"":$this->content);
        $curlErr = curl_error($this->ch);
        if($curlErr !== '')
        {
            $output['curl_error'] = $curlErr;
        }
        return $output;
    }
}
?>
