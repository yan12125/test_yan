<?php
// download from http://sourceforge.net/projects/simplehtmldom/files/
require '../../simple_html_dom.php';

if(isset($_GET['param']))
{
	header("Content-type:text/plain; charset=utf-8");

	$url='http://images.google.com/images?q='.$_GET['param'].'&hl=zh-TW';

	// get the html of search result page
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL,$url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER,1);
    $result=curl_exec($ch);
    curl_close($ch);
	$dom=new simple_html_dom();
	$dom->load($result);

	// start to analyze
	$imgs=$dom->find("img");
	$imgurl='';
	$count=count($imgs);
	$n=rand(0, $count-1);
	if(isset($imgs[$n]) && isset($imgs[$n]->parent()->href))
    {
        $href = $imgs[$n]->parent()->href;
        // href is like "/imgres?a=b&amp;c=d&amp;..."
        $sign = '/imgres?';
        if(substr($href, 0, strlen($sign))===$sign)
        {
            $qs = str_replace('&amp;', '&', substr($href, strlen($sign)));
            parse_str($qs, $param);
            echo $_GET['param'].' '.$param['imgurl'];
        }
    }
}

?>
