<?php
// simple wrapper for facebook batch requests
class FbBatch
{
    protected $commonToken;
    protected $queries;
    protected $keys;

    public function __construct($common_token = null)
    {
        $this->commonToken = null;
        $this->queries = array();
        $this->keys = array();

        if(!is_null($common_token))
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
        $params = array('batch' => json_encode($this->queries));
        if(!empty($this->commonToken))
        {
            $params['access_token'] = $this->commonToken;
        }
        $results = Fb::api('/', 'POST', $params);
        $this->queries = array();
        // Facebook api may return null here (might be failed json_decode?)
        // but I don't want to handle it
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

    public function getCount()
    {
        return count($this->queries);
    }
}
?>
