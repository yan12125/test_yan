<?php
class GoogleImgSearch extends PluginBase
{
    protected $ch;
    protected $url;
    protected $content;
    protected $keyword;

    public function __construct()
    {
        External::loadPhp('simple_html_dom');
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
            CURLOPT_USERAGENT => Util::FIREFOX_UA
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
        $rg_meta = $dom->find('.rg_meta');
        if(count($rg_meta) > 0) // seems different ua strings yield different results
        {
            $count = count($rg_meta);
            $n = rand(0, $count - 1);
            $data1 = json_decode($rg_meta[$n]->innertext, true);
            $data2 = parse_url($data1['lu']);
            parse_str($data2['fragment'], $data3);
            $data3 = explode(';', urldecode($data3['imgrc']));
            return $data3[2];
        }
        else
        {
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
                $sign = 'http://www.google.com/imgres?';
                if(substr($href, 0, strlen($sign))===$sign)
                {
                    $qs = str_replace('&amp;', '&', substr($href, strlen($sign)));
                    parse_str($qs, $params);
                    return $params['imgurl'];
                }
            }
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
