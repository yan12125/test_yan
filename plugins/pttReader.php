<?php
class PttReader extends PluginBase
{
    private $ch = null;

    public function __construct()
    {
        $this->ch = curl_init();
    }

    public function __destruct()
    {
        curl_close($this->ch);
    }

    public function run($param)
    {
        curl_setopt_array($this->ch, array(
            CURLOPT_URL => $param, 
            CURLOPT_BINARYTRANSFER => true,
            CURLOPT_RETURNTRANSFER => true, 
            CURLOPT_HTTPHEADER => array('Cookie: over18=1')
        ));
        $content = curl_exec($this->ch);
        $dom = new simple_html_dom();
        $dom->load($content);
        $entries = $dom->find(".r-ent .title a");
        $i = rand(0, count($entries) - 1);
        $title = $entries[$i]->innertext;
        $link = 'http://www.ptt.cc'.$entries[$i]->href;
        return $title."\n".$link;
    }

    public function handleException($e)
    {
        return array(
            'msg' => $e->getMessage(), 
            'line' => $e->getLine()
        );
    }
}
?>
