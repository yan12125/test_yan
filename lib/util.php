<?php
class Util
{
    /*
        Usage: Use Box-Muller method to get a random number with a Normal Distribution (ND)
        Parameters: $max, $min : the range of result
                    $nSigma : the number of standard deviation in range
        Reference: http://maupig.blogspot.com/2010/02/blog-post.html
    */
    public static function randND($max, $min, $nSigma)
    {
        if($max<$min)
        {
            $temp=$max;
            $max=$min;
            $min=$temp;
        }
        $U=rand()/getrandmax();     // uniformly distributed random variables
        $V=rand()/getrandmax();
        $sgd=sqrt(-2*log($U))*cos(2*M_PI*$V);   // random number with standard gaussian distribution
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
    public static function truncate(&$str, $len)
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
        $str = $ret_val;
    }

    /*
     * check required arguments in $_POST
     * $params: array of field names of required arguments
     */
    public static function checkPOST($params)
    {
        for($i = 0;$i < count($params);$i++)
        {
            $paramName = $params[$i];
            if(!isset($_POST[$paramName]))
            {
                throw new Exception('Insufficient POST argument: '.$paramName);
            }
        }
    }

    // PHP 5.2.6 doesn't support SimpleXMLElement::count(), so write one
    // but not used since PHP 5.3
    function xmlCount($xml)
    {
        $i=0;
        foreach($xml as $child)
        {
            $i++;
        }
        return $i;
    }

    public static function ip_only($ip)
    {
        $remote_ip = $_SERVER['REMOTE_ADDR'];
        if($ip !== $remote_ip)
        {
            throw new Exception("IP {$remote_ip} forbidden");
        }
        self::redirectHttps();
    }

    // not used anymore
    private static function checkAction($filename)
    {
        return isset($_POST['action']) && 
               (strpos($_SERVER['REQUEST_URI'], basename($filename)) !== FALSE);
    }

    public static function redirectHttps()
    {
        if(!isset($_SERVER['HTTPS']) || $_SERVER['HTTPS'] !== 'on')
        {
            $host = isset($_SERVER['HTTP_HOST'])?$_SERVER['HTTP_HOST']:'chyen.twbbs.org';
            $url = 'https://'.$host.$_SERVER['REQUEST_URI'];
            header('Location: '.$url);
            exit(0);
        }
    }

    public static function json_unicode($obj)
    {
        return self::unicode_conv(json_encode($obj));
    }

    /*
        Replace \uxxxx in $str to utf-8 character
        Reference: http://stackoverflow.com/questions/2934563/how-to-decode-unicode-escape-sequences-like-u00ed-to-proper-utf-8-encoded-cha
     */
    public static function unicode_conv($str)
    {
        // strings like "\\\\u2345" should not be changed
        $newStr = $str;
        preg_match_all('/\\\\u[0-9a-fA-F]{4}/', $str, $matches, PREG_OFFSET_CAPTURE);
        for($i = 0;$i < count($matches[0]);$i++)
        {
            $curMatch = $matches[0][$i]; // the i-th match for the first pattern
            if(($curMatch[1] >= 1 && substr($str, $curMatch[1]-1, 1) !== "\\") ||
                $curMatch[1] == 0)
            {
                $newStr = str_replace($curMatch[0], self::unicode_conv_impl($curMatch[0]), $newStr);
            }
        }
        return $newStr;
    }

    protected static function unicode_conv_impl($seq)
    {
        $entity = '&#'.hexdec(substr($seq, 2)).';'; // $seq is \uxxxx
        return mb_convert_encoding($entity, 'UTF-8', 'HTML-ENTITIES');
    }

    public static function errorHandler($severity, $message, $file, $line)
    {
        throw new ErrorException($message, 0, $severity, $file, $line);
        return true;
    }
    public static function getSeverityStr($severity)
    {
        // Copy from http://php.net/manual/en/errorfunc.constants.php
        $err_str = array(
            "1" => "E_ERROR", 
            "2" => "E_WARNING", 
            "4" => "E_PARSE", 
            "8" => "E_NOTICE", 
            "16" => "E_CORE_ERROR", 
            "32" => "E_CORE_WARNING", 
            "64" => "E_COMPILE_ERROR", 
            "128" => "E_COMPILE_WARNING", 
            "256" => "E_USER_ERROR", 
            "512" => "E_USER_WARNING", 
            "1024" => "E_USER_NOTICE", 
            "2048" => "E_STRICT", 
            "4096" => "E_RECOVERABLE_ERROR", 
            "8192" => "E_DEPRECATED", 
            "16384" => "E_USER_DEPRECATED"
        );
        if(isset($err_str[$severity]))
        {
            return $err_str[$severity];
        }
        else
        {
            return null;
        }
    }

    public static function addIncludePath($path)
    {
        if(!is_dir($path))
        {
            throw new Exception('Invalid path: '.$path);
        }
        set_include_path(get_include_path().PATH_SEPARATOR.$path);
    }

    public static function replaceTab(&$text)
    {
        $text = str_replace("\t", '    ', $text);
    }
}
?>
