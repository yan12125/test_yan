<?php
class GoogleImgSearch extends PluginBase
{
    protected $url;
    protected $content;
    protected $keyword;

    public function __construct()
    {
        $this->url = null;
        $this->content = null;
        $this->keyword = null;
    }

    public function run($param)
    {
        External::loadPhp('simple_html_dom');
        $this->keyword = $param;
        $this->url = 'http://www.google.com/search?tbm=isch&hl=zh-TW&q='.urlencode($param);

        // get the html of search result page
        $ch = curl_init();
        curl_setopt_array($ch, array(
            CURLOPT_URL => $this->url, 
            CURLOPT_RETURNTRANSFER => 1
        ));
        $this->content = curl_exec($ch);
        curl_close($ch);
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
            'content' => $this->content
        );
        $curlErr = curl_error();
        if($curlErr !== '')
        {
            $output['curl_error'] = $curlErr;
        }
        return $output;
    }
}
?>
