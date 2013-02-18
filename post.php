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

/*
    Truncate $str to the first $len chars
    * View chinese characters as two characters
    * Add ... if truncated
    Reference: http://stackoverflow.com/questions/4601032/php-iterate-on-string-characters
*/
function truncate($str, $len)
{
    $char_arr = preg_split('/(?<!^)(?!$)/u', $str);
    $cur_len = 0;
    $ret_val = '';
    foreach($char_arr as $char)
    {
        $cur_len += mb_strwidth($char, "UTF-8");
        if($cur_len >= $len)
        {
            $ret_val .= '...';
            break;
        }
        $ret_val .= $char;
    }
    return $ret_val;
}

function unicode_conv_impl($matches)
{
    $entity = '&#'.hexdec(substr($matches[0], 2)).';'; // $str is \uxxxx
    return mb_convert_encoding($entity, 'UTF-8', 'HTML-ENTITIES');
}

/*
    Replace \uxxxx in $str to utf-8 character
    Reference: http://stackoverflow.com/questions/2934563/how-to-decode-unicode-escape-sequences-like-u00ed-to-proper-utf-8-encoded-cha
 */
function unicode_conv($str)
{
    return preg_replace_callback('/\\\\u[0-9a-fA-F]{4}/', 'unicode_conv_impl', $str);
}

try
{
	if(isset($_POST['uid']))
	{
        $not_post = false;

        $userData = user_action('get_data', array('uid' => $_POST['uid'], 'field'=>'*'));
        if($userData['query_result'] != 'user_found')
        {
            throw new Exception("Specified UID not found!");
        }
		$titles_json=$userData['titles'];

        $arr_result=text_action("get_random_text_from_titles", array("titles"=>$titles_json));
        if(isset($arr_result['error']))
        {
            if($arr_result['error'] == 'title_locked')
            {
                $not_post = true;
            }
            else
            {
                throw new Exception($arr_result['error']);
            }
        }

		$pause_time=round(randND($userData['interval_max'], $userData['interval_min'], 6), 1); // 正負三個標準差
        // round to decrease amount of transmission

        if(isset($_POST['debug']))
        {
            if($_POST['debug'])
            {
                $not_post = true;
            }
        }

        if(!$not_post)
        {
            $ret_obj=$facebook->api('/198971170174405_198971283507727/comments', 'POST',
                array(
                    "message"=> $arr_result['msg'],
                    "access_token"=>$userData['access_token']
                )
            );
        }

		$arr_user_data=user_action('increase_user_count', array('uid'=>$_POST['uid']));

		$response=array();
        if(isset($_POST['truncated_msg']) && $_POST['truncated_msg'])
        {
            $response['msg'] = truncate($arr_result['msg'], 20);
        }
        else
        {
    		$response['msg']=$arr_result['msg'];
        }
		$response['title']=$arr_result['title'];
		$response['item']=$arr_result['m'];
		$response['user_data']=$arr_user_data;
		if(($special_wait_time=load_params('special_wait_time'))>0)
		{
			$response["next_wait_time"]=$special_wait_time;
		}
		else
		{
			$response["next_wait_time"]=$pause_time;
		}

		echo unicode_conv(json_encode($response));
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
        "time" => date('H:i:s')
    );
    if(isset($arr_result))
    {
        if(isset($arr_result['title']))
        {
            $response_error['title'] = $arr_result['title'];
        }
        if(isset($arr_result['msg']))
        {
            $response_error['msg'] = $arr_result['msg'];
        }
        if(isset($arr_result['m']))
        {
            $response_error['item'] = $arr_result['m'];
        }
    }
	echo json_encode($response_error);
}

?>
