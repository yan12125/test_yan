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
        $i = rand(0, count($this->items) - 1);
        $item = $this->items[$i];
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

        $dom = new simple_html_dom();
        $dom->load($content);
        $entries = $dom->find(".r-ent .title a");
        for($i = 0; $i < count($entries); $i++)
        {
            $this->items[] = array(
                'link' => $this->getFullUrl($entries[$i]->href), 
                'title' => $entries[$i]->innertext
            );
        }
        $links = $dom->find('.pull-right a');
        $dom->clear(); // to prevent memory leak
        $this->nextLnk = $this->getFullUrl($links[1]->href);
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
