<?php
use \Curl\Curl;

class GoogleImgSearch extends PluginBase
{
    protected $ch;
    protected $url;
    protected $content;
    protected $keyword;

    public function __construct()
    {
        $this->ch = new Curl();
        $this->url = null;
        $this->content = null;
        $this->keyword = null;
    }

    public function run($param)
    {
        $this->keyword = $param;
        $this->url = 'https://www.google.com/search?tbm=isch&hl=zh-TW&oe=utf8&q='.urlencode($param);

        // get the html of search result page
        $this->ch->setUserAgent(Util::CHROME_UA);
        $this->ch->setOpt(CURLOPT_FOLLOWLOCATION, true);
        $this->ch->error(array('Util', 'curlErrorHandler'));
        $this->ch->get($this->url);
        $this->content = $this->ch->response;
        return $this->keyword . "\n" . $this->parseResult();
    }

    protected function parseResult()
    {
        $dom = new simple_html_dom();
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
            'url' => $this->url, 
            'content' => $this->content
        );
        Logger::write($e->getMessage(), Logger::ERROR, is_null($this->content)?"":$this->content);
        if($this->ch->error !== '')
        {
            $output['curl_error'] = $this->ch->error;
        }
        return $output;
    }
}
?>
