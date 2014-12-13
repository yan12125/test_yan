<?php
use \Curl\Curl;

class RssReader extends PluginBase
{
    protected $xml;
    protected $url;
    protected $ch;
    protected $redirectHeader;

    public function __construct()
    {
        libxml_use_internal_errors(true); // handle errors manually
        $this->xml = null;
        $this->url = null;
        $this->redirectHeader = null;
        $this->ch = new Curl();
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
                $n = rand(0, $feed->channel->item->count()-1);
                $url = $feed->channel->item[$n]->link;
                $title = $feed->channel->item[$n]->title;
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
            $n = rand(0, $feed->entry->count() - 1);
            $item = $feed->entry[$n];
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
        $this->url = $url;
        $this->ch->setOpt(CURLOPT_FOLLOWLOCATION, true);
        $this->ch->setOpt(CURLOPT_ENCODING, 'gzip,deflate');
        $this->ch->get($this->url);
        $this->xml = $this->ch->response;
        if(!($this->xml instanceof SimpleXMLElement))
        {
            throw new Exception('No XML returned');
        }
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
        $ch = new Curl();
        $ch->setHeader("Connection", "close");
        $ch->setOpt(CURLOPT_FOLLOWLOCATION, false);
        $ch->head($url);
        $this->redirectHeader = $results = $ch->response;
        $matches = array();
        if($ch->http_status_code == 301 || $ch->http_status_code == 302)
        {
            return $this->getRedirectedUrl($ch->response_headers['Location'], $count + 1);
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
        $output['curl_error'] = $this->ch->curl_error_message;
        $output['redirectHeader'] = $this->redirectHeader;
        return $output;
    }
}
?>
