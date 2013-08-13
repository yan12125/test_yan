<?php
// simple wrapper for facebook batch requests
class FbBatch
{
    protected $commonToken;
    protected $queries;
    protected $keys;
    protected static $lastQueryResult;
    protected static $lastParam;

    public function __construct($common_token = null)
    {
        self::$lastParam = self::$lastQueryResult = null;
        $this->queries = $this->keys = array();
        if(is_null($common_token))
        {
            $this->commonToken = Fb::getAppToken();
        }
        else
        {
            $this->commonToken = $common_token;
        }
    }

    public function push($key, $path, $method = 'GET', $params = array())
    {
        if(count($this->queries) >= 50)
        {
            throw new Exception('The limit of batch requests is 50.');
        }
        if(is_array($method) && empty($params))
        {
            $params = $method;
            $method = 'GET';
        }
        // transform to facebook batch request formats
        $this->queries[] = array(
            'method' => $method, 
            'relative_url' => $path.'?'.http_build_query($params)
        );
        $this->keys[] = $key;
    }

    public function run()
    {
        if(empty($this->queries))
        {
            return array();
        }
        $results = self::makeRequest();
        $this->queries = array();
        foreach($results as &$item)
        {
            $item = json_decode($item['body'], true);
        }

        // array_count_values only accept integers and strings, and keys can be null
        $key_count = array_count_values(array_filter($this->keys, function($var){
            return !is_null($var);
        }));
        for($i = 0;$i < count($this->keys);$i++)
        {
            $key = $this->keys[$i];
            if(!is_null($key) && !is_numeric($key))
            {
                if($key_count[$key] > 1)
                {
                    if(!isset($results[$key]))
                    {
                        $results[$key] = array();
                    }
                    $results[$key][] = $results[$i];
                }
                else
                {
                    $results[$key] = $results[$i];
                }
            }
        }
        return $results;
    }

    protected function makeRequest()
    {
        self::$lastParam = $params = array(
            'batch' => json_encode($this->queries), 
            'access_token' => $this->commonToken
        );

        $ch = curl_init();
        curl_setopt_array($ch , array(
            CURLOPT_URL => 'https://graph.facebook.com/', 
            CURLOPT_RETURNTRANSFER => true, 
            CURLOPT_BINARYTRANSFER => true, 
            CURLOPT_POST => true, 
            CURLOPT_POSTFIELDS => http_build_query($params), 
            CURLOPT_TIMEOUT => 20
        ));
        $result = curl_exec($ch);
        if($result === false)
        {
            throw new Exception(curl_error($ch));
        }
        self::$lastQueryResult = $result;
        $json = json_decode($result, true);
        if(is_null($json))
        {
            throw new Exception('Invalid result from facebook');
        }
        if(isset($json['error']))
        {
            throw new Exception(json_encode($json['error']));
        }
        return $json;
    }

    public function getCount()
    {
        return count($this->queries);
    }

    public static function report_fields(&$output)
    {
        $output['fb_result'] = Util::tryParseJson(self::$lastQueryResult);
        self::$lastParam['batch'] = Util::tryParseJson(self::$lastParam['batch']);
        $output['last_param'] = self::$lastParam;
    }
}
?>
