<?php
class RssReader extends PluginBase
{
    protected $xml;
    protected $url;
    protected $redirectHeader;
    protected $content;
    protected $curl_error;

    public function __construct()
    {
        libxml_use_internal_errors(true); // handle errors manually
        $this->xml = null;
        $this->url = null;
        $this->redirectHeader = null;
        $this->curl_error = null;
    }

    public function run($param)
    {
        $this->getUrlContent($param);
        $feed = $this->xml;
        $url = $title = '';
        // RSS
        if($feed->channel->count() > 0)
        {
            if($feed->channel->item->count() > 0)
            {
                $item = Util::xmlNodesRand($feed->channel->item);
                $url = $item->link;
                $title = $item->title;
            }
            else
            {
                $url = $feed->channel->link;
                $title = $feed->channel->title;
            }
        }
        // Atom
        else if($feed->entry->count() != 0)
        {
            $item = Util::xmlNodesRand($feed->entry);
            $url = $item->link['href'];
            $title = $item->title;
        }
        if($title == '' || $url == '')
        {
            throw new Exception('Invalid RSS or ATOM format!');
        }
        return $title."\n".$this->getRedirectedUrl($url);
    }

    public function getUrlContent($url)
    {
        $ch = curl_init();
        $this->url = $url;
        curl_setopt_array($ch, array(
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_ENCODING => 'gzip,deflate',
            CURLOPT_URL => $this->url,
            CURLOPT_RETURNTRANSFER => true
        ));
        $this->content = curl_exec($ch);
        if(curl_errno($ch))
        {
            $this->curl_error = curl_error($ch);
            throw new Exception('curl error');
        }
        $this->xml = new SimpleXMLElement($this->content);
    }

    private function getRedirectedUrl($url, $count = 0)
    {
        // to prevent a redirection loop
        if($count >= 10)
        {
            return $url;
        }
        // PHP cUrl HTTP HEAD
        // http://stackoverflow.com/questions/770179
        $ch = curl_init();
        curl_setopt_array($ch, array(
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_HTTPHEADER => array("Connection: close"),
            CURLOPT_CUSTOMREQUEST => 'HEAD',
            CURLOPT_URL => $url,
            CURLOPT_HEADER => true,
            CURLOPT_RETURNTRANSFER => true
        ));
        $this->redirectHeader = $results = curl_exec($ch);
        $status_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        if($status_code == 301 || $status_code == 302)
        {
            preg_match('/^Location:\s*(.+)$/im', $this->redirectHeader, $matches);
            return $this->getRedirectedUrl($matches[1], $count + 1);
        }
        else
        {
            return $url;
        }
    }

    public function handleException($e)
    {
        $xmlErr = libxml_get_last_error();
        if($xmlErr !== false)
        {
            $output = array(
                'source' => 'LibXML', 
                'code' => $xmlErr->code, 
                'message' => $xmlErr->message, 
            );
        }
        else
        {
            $output = array(
                'source' => 'RSS Reader', 
                'message' => $e->getMessage(), 
                'line' => $e->getLine()
            );
        }
        if(mb_check_encoding($this->xml, 'UTF-8'))
        {
            $output['xml'] = $this->xml;
        }
        else
        {
            $output['xml_base64'] = base64_encode($this->xml);
        }
        $output['url'] = $this->url;
        $output['curl_error'] = $this->curl_error;
        $output['redirectHeader'] = $this->redirectHeader;
        return $output;
    }
}
?>
