<?php
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
?>
