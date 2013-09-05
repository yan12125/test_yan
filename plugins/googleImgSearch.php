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
        External::loadPhp('simple_html_dom');
        $this->keyword = $param;
        $this->url = 'http://www.google.com/search?tbm=isch&hl=zh-TW&q='.urlencode($param);

        // get the html of search result page
        curl_setopt_array($this->ch, array(
            CURLOPT_URL => $this->url, 
            CURLOPT_RETURNTRANSFER => 1
        ));
        $this->content = curl_exec($this->ch);
        if($this->content === false)
        {
            throw new Exception('curl_exec() failed');
        }
        $dom=new simple_html_dom();
        $dom->load($this->content);

        // start to analyze
        $imgs=$dom->find("img");
        $imgurl='';
        $count=count($imgs);
        if($count < 5) // so few <img>... no results
        {
            throw new Exception('No results found');
        }
        $n=rand(0, $count-1);
        if(isset($imgs[$n]) && isset($imgs[$n]->parent()->href))
        {
            $href = $imgs[$n]->parent()->href;
            // href is like "/imgres?a=b&amp;c=d&amp;..."
            $sign = '/imgres?';
            if(substr($href, 0, strlen($sign))===$sign)
            {
                $qs = str_replace('&amp;', '&', substr($href, strlen($sign)));
                parse_str($qs, $params);
                return $param."\n".$params['imgurl'];
            }
        }
        throw new Exception('Can\'t retrieve images');
    }

    public function handleException($e)
    {
        $output = array(
            'message' => $e->getMessage(), 
            'keyword' => $this->keyword, 
            'url' => $this->url, 
            'content' => iconv('Big5', 'utf-8', $this->content)
        );
        $curlErr = curl_error($this->ch);
        if($curlErr !== '')
        {
            $output['curl_error'] = $curlErr;
        }
        return $output;
    }
}
?>
