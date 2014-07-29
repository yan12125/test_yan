<?php
// simple wrapper for facebook batch requests
class FbBatch
{
    const BATCH_LIMIT = 45;
    protected $commonToken;
    protected $queries;
    protected $keys;
    protected $responses;
    protected static $lastQueryResult;
    protected static $lastParam;

    public function __construct($common_token = null)
    {
        self::$lastParam = self::$lastQueryResult = null;
        $this->queries = $this->keys = $this->responses = array();
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
        if($key == 'error')
        {
            throw new Exception("\"error\" can't be used as a key");
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

        $query_chunks = array_chunk($this->queries, self::BATCH_LIMIT);
        for($i = 0; $i < count($query_chunks); $i++)
        {
            try
            {
                $this->responses = array_merge($this->responses, $this->drainRequest($query_chunks[$i]));
            }
            catch(Exception $e)
            {
                $range_start = $i * self::BATCH_LIMIT;
                $range_len = min(count($this->queries) - $range_start, self::BATCH_LIMIT);
                $base_err_array = array(
                    'error' => $e->getMessage()
                );
                $err_array = array_fill($range_start, $range_len, $base_err_array);
                $this->responses = array_merge($this->responses, $err_array);
            }
        }
        if(count($this->queries) == 1)
        {
            $retval = $this->parseSingleResult($this->responses);
        }
        else
        {
            $retval = $this->parseMultipleResults($this->responses);
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
        // $results are always array indexed by integers
        foreach($results as &$item)
        {
            if(isset($item['body']))
            {
                $item = json_decode($item['body'], true);
            }
            // otherwise, something wrong happened
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

    protected function drainRequest($query)
    {
        $base_url = 'https://graph.facebook.com';
        if(count($query) > 1)
        {
            $params = array(
                'batch' => json_encode(array_map(function ($item) {
                    $relative_url = $item['path'].'?'.http_build_query($item['params']);
                    if($relative_url{0} == '/')
                    {
                        $relative_url = substr($relative_url, 1);
                    }
                    return array(
                        'method' => $item['method'], 
                        'relative_url' => $relative_url
                    );
                }, $query)), 
                'access_token' => $this->commonToken
            );
            $url = $base_url;
            $isPost = true;
        }
        else
        {
            $_query = $query[0];

            $params = $_query['params'];
            $url = $base_url.$_query['path'];
            $isPost = ($_query['method'] == 'POST');
        }
        self::$lastParam = $params;
        $params = http_build_query($params);

        $ch = curl_init();
        $curl_options = array(
            CURLOPT_RETURNTRANSFER => true, 
            CURLOPT_BINARYTRANSFER => true, 
            CURLOPT_TIMEOUT => 20, 
            // http://stackoverflow.com/questions/11004624/ 
            CURLOPT_IPRESOLVE => CURL_IPRESOLVE_V4, 
            // Without this, the response will be two HTTP headers - 100 Continue and 200 OK
            // http://www.laruence.com/2011/01/20/1840.html
            CURLOPT_HTTPHEADER => array('Expect:')
        );
        if($isPost)
        {
            $curl_options[CURLOPT_URL] = $url;
            $curl_options[CURLOPT_POST] = true;
            $curl_options[CURLOPT_POSTFIELDS] = $params;
        }
        else
        {
            $curl_options[CURLOPT_URL] = $url."?".$params;
            $curl_options[CURLOPT_POST] = false;
        }
        curl_setopt_array($ch , $curl_options);
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

    public static function report_fields(&$output, $e /*unused*/)
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
