<?php
// simple wrapper for facebook batch requests
class FbBatch
{
    protected $commonToken;
    protected $queries;

    public function __construct($common_token = null)
    {
        $this->commonToken = null;
        $this->queries = array();

        if(!is_null($common_token))
        {
            $this->commonToken = $common_token;
        }
    }

    public function push($path, $method = 'GET', $params = array())
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
        array_push($this->queries, array(
            'method' => $method, 
            'relative_url' => $path.'?'.http_build_query($params)
        ));
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
        foreach($results as &$item)
        {
            $item = json_decode($item['body'], true);
        }
        return $results;
    }

    public function getCount()
    {
        return count($this->queries);
    }
}
?>
