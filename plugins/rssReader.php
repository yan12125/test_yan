<?php
class RssReader extends PluginBase
{
    protected $xml;
    protected $url;
    protected $ch;

    public function __construct()
    {
        libxml_use_internal_errors(true); // handle errors manually
        $this->xml = null;
        $this->url = null;
        $this->ch = curl_init();
    }

    public function __destruct()
    {
        curl_close($this->ch);
    }

    public function run($param)
    {
        $this->getUrlContent($param);
        $feed=new SimpleXMLElement($this->xml);
        $url = $title = '';
        // RSS
        if(is_object($feed->channel) && 
           is_object($feed->channel->item) && 
           $feed->channel->item->count() != 0)
        {
            $n=rand(0, $feed->channel->item->count()-1);
            $url = $feed->channel->item[$n]->link;
            $title = $feed->channel->item[$n]->title;
        }
        // Atom
        else if(is_object($feed->entry) &&
                $feed->entry->count() != 0)
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
        curl_setopt_array($this->ch, array(
            CURLOPT_URL => $this->url, 
            CURLOPT_RETURNTRANSFER => true, 
            CURLOPT_FOLLOWLOCATION => true
        ));
        $this->xml = curl_exec($this->ch);
        if(empty($this->xml))
        {
            throw new Exception('Failed to retrieve specified url');
        }
        if(!mb_check_encoding($this->xml, 'UTF-8'))
        {
            throw new Exception('Invalid UTF-8 detected');
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
        $ch = curl_init();
        curl_setopt_array($ch, array(
            CURLOPT_URL => $url, 
            CURLOPT_CUSTOMREQUEST => 'HEAD', 
            CURLOPT_HEADER => true, 
            CURLOPT_HTTPHEADER => array('Connection: close'), 
            CURLOPT_RETURNTRANSFER => true, 
            CURLOPT_BINARYTRANSFER => true, 
            CURLOPT_FOLLOWLOCATION => false
        ));
        $results = curl_exec($ch);
        curl_close($ch);
        $matches = array();
        if(preg_match('/HTTP\/1.1 (302|301)/', $results))
        {
            preg_match('/(Location:|URI:)(.*?)\n/', $results, $matches);
            return $this->getRedirectedUrl(trim($matches[2]), $count + 1);
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
        $output['curl_error'] = curl_error($this->ch);
        return $output;
    }
}
?>
