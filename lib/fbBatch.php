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
            'path' => $path, 
            'params' => $params
        );
        $this->keys[] = $key;
    }

    public function pushFql($key, $query)
    {
        $this->push($key, 'fql', 'GET', array('q' => $query));
    }

    public function run()
    {
        if(empty($this->queries))
        {
            return array();
        }

        $results = $this->makeRequest();
        if(count($this->queries) == 1)
        {
            $retval = $this->parseSingleResult($results);
        }
        else
        {
            $retval = $this->parseMultipleResults($results);
        }

        $this->queries = array();

        return $retval;
    }

    protected function parseSingleResult($results)
    {
        if(is_null($this->keys[0]))
        {
            $key = 0;
        }
        else
        {
            $key = $this->keys[0];
        }
        return array($key => $results);
    }

    protected function parseMultipleResults($results)
    {
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
        $base_url = 'https://graph.facebook.com';
        if(count($this->queries) > 1)
        {
            $params = array(
                'batch' => json_encode(array_map(function ($item) {
                    return array(
                        'method' => $item['method'], 
                        'relative_url' => $item['path'].'?'.http_build_query($item['params'])
                    );
                }, $this->queries)), 
                'access_token' => $this->commonToken
            );
            $url = $base_url;
            $isPost = true;
        }
        else
        {
            $query = $this->queries[0];

            $params = $query['params'];
            $url = $base_url.$query['path'];
            $isPost = ($query['method'] == 'POST');
        }
        self::$lastParam = $params;

        $ch = curl_init();
        curl_setopt_array($ch , array(
            CURLOPT_URL => $url, 
            CURLOPT_RETURNTRANSFER => true, 
            CURLOPT_BINARYTRANSFER => true, 
            CURLOPT_POST => $isPost, 
            CURLOPT_POSTFIELDS => http_build_query($params), 
            CURLOPT_TIMEOUT => 20, 
            // http://stackoverflow.com/questions/11004624/ 
            CURLOPT_IPRESOLVE => CURL_IPRESOLVE_V4
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
        return $json;
    }

    public function getCount()
    {
        return count($this->queries);
    }

    public static function report_fields(&$output)
    {
        $output['fb_result'] = Util::tryParseJson(self::$lastQueryResult);
        if(isset(self::$lastParam['batch']))
        {
            self::$lastParam['batch'] = Util::tryParseJson(self::$lastParam['batch']);
        }
        $output['last_param'] = self::$lastParam;
    }
}
?>
