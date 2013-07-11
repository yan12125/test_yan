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

    // Reference: 
    // http://php.net/manual/en/function.json-encode.php 
    // devilan (REMOVEIT) (at) o2 (dot) pl
    public static function json_unicode(array $arr)
    {
        // strings like "\\\\u2345" should not be changed
        // convmap since 0x80 char codes so it takes all multibyte codes (above ASCII 127). 
        // So such characters are being "hidden" from normal json_encode
        $convmap = array (0x80, 0xffff, 0, 0xffff);
        $oldEncoding = mb_internal_encoding();
        mb_internal_encoding('UTF-8');
        array_walk_recursive($arr, function (&$item, $key) use ($convmap)
        {
            if (is_string($item))
            {
                $item = mb_encode_numericentity($item, $convmap); 
            }
        });
        $result = mb_decode_numericentity(json_encode($arr), $convmap);
        mb_internal_encoding($oldEncoding);
        return $result;
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

    public static function timestr($timestamp = 0)
    {
        if($timestamp == 0)
        {
            $timestamp = time();
        }
        return date('Y-m-d H:i:s', $timestamp);
    }

    public static function tryParseJson($str)
    {
        $json = json_decode($str, true);
        if(is_null($json))
        {
            return $str;
        }
        else
        {
            return $json;
        }
    }
}
?>
