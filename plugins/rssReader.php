<?php
class RssReader extends PluginBase
{
    protected $xml;
    protected $url;

    public function __construct()
    {
        libxml_use_internal_errors(true); // handle errors manually
        $this->xml = null;
        $this->url = null;
    }

    public function run($param)
    {
        $this->getUrlContent($param);
        $feed=new SimpleXMLElement($this->xml);
        if(!is_object($feed->channel) || !is_object($feed->channel->item) || $feed->channel->item->count() == 0)
        {
            throw new Exception('Invalid RSS');
        }
        $n=rand(0, $feed->channel->item->count()-1);
        return $feed->channel->item[$n]->title."\n".$feed->channel->item[$n]->link;
    }

    public function getUrlContent($url)
    {
        $this->url = $url;
        $ch = curl_init();
        curl_setopt_array($ch, array(
            CURLOPT_URL => $this->url, 
            CURLOPT_RETURNTRANSFER => true, 
            CURLOPT_FOLLOWLOCATION => true
        ));
        $this->xml = curl_exec($ch);
        if(empty($this->xml))
        {
            throw new Exception(json_encode(array(
                'msg' => 'Failed to retrieve specified url', 
                'curl_error' => curl_error($ch)
            )));
        }
        curl_close($ch);
        if(!mb_check_encoding($this->xml, 'UTF-8'))
        {
            throw new Exception('Invalid UTF-8 detected');
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
                'message' => Util::tryParseJson($e->getMessage()), 
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
        return $output;
    }
}
?>
