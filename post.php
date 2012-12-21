<?php
// cancel all error reporting to prevent ugly error output
error_reporting(0);

// for fatal errors
function shutdown()
{
	$err=error_get_last(); // returns NULL is no errors have occurred
	if(!is_null($err))
	{
		$output=array();
		$output['error']=$err['message'];
		$output['filename']=basename($err['file']);
		$output['line']=$err['line'];
		if(isset($arr_result))
		{
			$output['title']=$arr_result['title'];
			$output['msg']=$arr_result['msg'];
		}
		echo json_encode($output);
	}
}
register_shutdown_function('shutdown');

$useFB=true;
require_once 'common_inc.php';
require_once 'texts.php';
require_once 'users.php';

header("Content-type: application/json; charset=UTF-8");
header("Cache-control: no-cache");

$ret_obj=null;
$titles_json=null;

/*
	Usage: Use Box-Muller method to get a random number with a Normal Distribution (ND)
	Parameters: $max, $min : the range of result
				$nSigma : the number of standard deviation in range
	Reference: http://maupig.blogspot.com/2010/02/blog-post.html
*/
function randND($max, $min, $nSigma)
{
	if($max<$min)
	{
		$temp=$max;
		$max=$min;
		$min=$temp;
	}
	$U=rand()/getrandmax();		// uniformly distributed random variables
	$V=rand()/getrandmax();
	$sgd=sqrt(-2*log($U))*cos(2*M_PI*$V);	// random number with standard gaussian distribution
	$nRandom=$sgd*($max-$min)/$nSigma+($min+$max)/2;

	// restrict the result in the range
	if($nRandom>$max) $nRandom=$max;
	if($nRandom<$min) $nRandom=$min;
		
	return $nRandom;
}

try
{
	if(isset($_POST['uid']))
	{
        $userData = user_action('get_data', array('uid' => $_POST['uid']));
        if($userData['query_result'] != 'user_found')
        {
            throw new Exception("Specified UID not found!");
        }
		$titles_json=$userData['titles'];

        $arr_result=text_action("get_random_text_from_titles", array("titles"=>$titles_json));
        if(isset($arr_result['error']))
        {
            throw new Exception($arr_result);
        }

		$pause_time=randND($userData['interval_max'], $userData['interval_min'], 6);	// 正負三個標準差

		$starttime=microtime(true);
		$ret_obj=$facebook->api('/198971170174405_198971283507727/comments', 'POST',
			array(
				"message"=> $arr_result['msg'],
				"access_token"=>$userData['access_token']
			)
		);
		$execution_time=microtime(true)-$starttime;

		$arr_user_data=user_action('increase_user_count', array('uid'=>$_POST['uid']));

		$response=array();
		$response['msg']=$arr_result['msg'];
		$response['title']=$arr_result['title'];
		$response['item']=$arr_result['n'].','.$arr_result['m'];
		$response['ID']=$ret_obj['id'];
		$response['execution_time']=$execution_time;
		$response['user_data']=$arr_user_data;
		if(($special_wait_time=load_params('special_wait_time'))>0)
		{
			$response["next_wait_time"]=$special_wait_time;
		}
		else
		{
			$response["next_wait_time"]=$pause_time;
		}

		echo json_encode($response);
	}
	else
	{
		$response_error=array();
		$response_error['error']="Parameters not sufficient!\n".implode(",",$_POST);
		echo json_encode($response_error);
	}
}
catch(Exception $e)
{
	$response_error=array(
        "error" => $e->getMessage(), 
        "code" => $e->getCode(), 
        "class_name" => get_class($e), 
        "title" => $arr_result['title'], 
        "msg" => $arr_result['msg'], 
        "time" => date('H:i:s')
    );
	echo json_encode($response_error);
}

?>
