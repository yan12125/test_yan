<?php

class PttReader extends PluginBase
{
    private $lastContent = null;
    private $threshold = 10; // ptt has 20 entries per page
    private $items = array();
    private $nextLnk = null;

    public function run($param)
    {
        $this->parsePage($param);
        if(count($this->items) < $this->threshold)
        {
            $this->parsePage($this->nextLnk);
        }
        $item = Util::array_rand_item($this->items);
        return $item['title']."\n".$item['link'];
    }

    protected function parsePage($url)
    {
        $conn = curl_init();
        curl_setopt_array($conn, array(
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_USERAGENT => Util::FIREFOX_UA,
            CURLOPT_URL => $url,
            CURLOPT_COOKIE => 'over18=1'
        ));
        $this->lastContent = $content = curl_exec($conn);
        curl_close($conn);

        if(preg_match_all('/\<div class="title"\>\s*\<a href="([^"]+)"\>([^<]+)\<\/a\>/', $content, $matches))
        {
            for($i = 0; $i < count($matches[0]); $i++)
            {
                $this->items[] = array(
                    'link' => $this->getFullUrl($matches[1][$i]), 
                    'title' => $matches[2][$i]
                );
            }
        }
        preg_match('/\<a[^>]+href="([^"]+)"\>[^<]+上頁/', $content, $matches);
        $this->nextLnk = $this->getFullUrl($matches[1]);
    }

    protected function getFullUrl($relativeUrl)
    {
        return 'http://www.ptt.cc'.$relativeUrl;
    }

    public function handleException($e)
    {
        return array(
            'msg' => $e->getMessage(), 
            'line' => $e->getLine(), 
            'lastContent' => $this->lastContent
        );
    }
}
?>
