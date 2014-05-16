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
        $conn = new Curl();
        $conn->setCookie('over18', '1');
        $conn->setUserAgent(Util::FIREFOX_UA);
        $conn->get($url);
        $this->lastContent = $content = $conn->response;

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
