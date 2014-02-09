<?php
class Util
{
    const FIREFOX_UA = 'Mozilla/5.0 (X11; Linux x86_64; rv:25.0) Gecko/20100101 Firefox/25.0';

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

    public static function ip_only()
    {
        if(func_num_args() == 0)
        {
            $IPs = json_decode(Db::getConfig('allow_ips'));
        }
        else
        {
            $IPs = func_get_args();
        }
        $remote_ip = $_SERVER['REMOTE_ADDR'];
        $ok = false;
        for($i = 0;$i < count($IPs);$i++)
        {
            if(self::ipCIDRCheck($remote_ip, $IPs[$i]))
            {
                self::redirectHttps();
                $ok = true;
            }
        }
        if(!$ok)
        {
            throw new Exception("IP {$remote_ip} forbidden");
        }
    }

    // Reference: http://php.net/manual/en/ref.network.php#74656
    public static function ipCIDRCheck ($IP, $CIDR)
    {
        $components = explode ("/", $CIDR);
        $net = $components[0];
        if(count($components) == 2)
        {
            $mask = $components[1];
        }
        else if(count($components) == 1)
        {
            $mask = 32;
        }
        
        $ip_net = ip2long ($net);
        $ip_mask = ~((1 << (32 - $mask)) - 1);

        $ip_ip = ip2long ($IP);

        $ip_ip_net = $ip_ip & $ip_mask;

        return ($ip_ip_net == $ip_net);
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
        // H contains the leading zero
        return date('Y/m/d H:i:s', $timestamp);
    }

    public static function tryParseJson($str)
    {
        if(is_string($str))
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
        else
        {
            return $str;
        }
    }

    public static function getPageUrl($strip_fields = array())
    {
        $url = 'https://'.$_SERVER['SERVER_NAME'].$_SERVER['REQUEST_URI'];
        if(count($strip_fields) != 0)
        {
            $urlC = parse_url($url); // url Components
            if(isset($urlC['query']))
            {
                parse_str($urlC['query'], $param);
                for($i = 0;$i < count($strip_fields);$i++)
                {
                    unset($param[$strip_fields[$i]]);
                }
                $url = $urlC['scheme'].'://'.$urlC['host'].$urlC['path'];
                if(count($param) != 0)
                {
                    $url .= '?'.http_build_query($param);
                }
            }
        }
        return $url;
    }

    public static function checkPHP()
    {
        // check PHP version
        if(PHP_MAJOR_VERSION < 5 || PHP_MINOR_VERSION < 3)
        {
            echo 'Require PHP 5.3 or higher.';
            exit(0);
        }

        // check extensions
        $required = array('curl', 'PDO', 'pdo_mysql', 'openssl', 'mbstring', 'iconv');
        $loaded = get_loaded_extensions();
        $diff = array_diff($required, $loaded);
        if(count($diff) != 0)
        {
            foreach($diff as $ext)
            {
                echo "Extension {$ext} required.\n";
            }
            exit(0);
        }
    }

    public static function not_empty($str)
    {
        // facebook doesn't accept \xA0 (\xC2\xA0 in utf-8)
        // but some utf-8 chars ended with \xA0, so not use trim()
        return trim($str) != '' && $str !== "\xC2\xA0";
    }

    public static function handleException(Exception $e, &$output, $needTrace = true)
    {
        // basic parameters
        $errClass = get_class($e);
        $output['err_class'] = $errClass;
        $output['code'] = $e->getCode();
        $output['time'] = Util::timestr();
        $output['error'] = Util::tryParseJson($e->getMessage());
        if(is_array($output['error']))
        {
            if(!isset($output['additional_info']))
            {
                $output['additional_info'] = array();
            }
            if(isset($output['error']['source']))
            {
                $sourceName = $output['error']['source'];
                $output['additional_info'][$sourceName] = $output['error'];
                $errorMsg = '';
                if(isset($output['error']['error']))
                {
                    $errorMsg = $output['error']['error'];
                }
                $output['error'] = 'Error from component '.$sourceName;
                if($errorMsg != '')
                {
                    $output['error'] .= ': '.$errorMsg;
                }
            }
            else
            {
                $output['additional_info'][] = $output['error'];
                $output['error'] = 'Unexpected error occurred!';
            }
        }
        if($errClass == 'ErrorException')
        {
            $response_error['severity'] = Util::getSeverityStr($e->getSeverity());
        }

        if($needTrace)
        {
            $trace = $e->getTrace();
            $classNames = array();
            foreach($trace as &$item)
            {
                // not set in error handler
                if(isset($item['file']))
                {
                    $item['file'] = basename($item['file']);
                }
                // determine which class cause the error
                if(isset($item['class']))
                {
                    if($item['class'] == 'Util' && $item['function'] == 'errorHandler')
                    {
                        continue;
                    }
                    $classNames[] = $item['class'];
                }
            }
            $output['trace'] = $trace;
        }

        for($i = 0;$i < count($classNames);$i++)
        {
            if(method_exists($classNames[$i], 'report_fields'))
            {
                // some tricks required to use call_user_func with reference values
                // http://stackoverflow.com/questions/295016
                $fReportFields = array($classNames[$i], 'report_fields');
                call_user_func_array($fReportFields, array(&$output));
            }
        }
    }
}
?>
