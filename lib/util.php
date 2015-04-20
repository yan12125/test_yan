<?php
class Util
{
    const FIREFOX_UA = 'Mozilla/5.0 (X11; Linux x86_64; rv:27.0) Gecko/20100101 Firefox/27.0';
    const CHROME_UA =  'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/33.0.1750.146 Safari/537.36';
    public static $debug = false;
    public static $locales = array('en_US', 'zh_TW');
    private static $jsonDecoder = null;

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

    public static function remote_ip()
    {
        if(php_sapi_name() == 'cli')
        {
            $remote_ip = '127.0.0.1';
        }
        else
        {
            $remote_ip = $_SERVER['REMOTE_ADDR'];
        }
        return $remote_ip;
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

    public static function ip_only($allow_http = true)
    {
        if(func_num_args() == 0)
        {
            $IPs = json_decode(Db::getConfig('allow_ips'));
        }
        else
        {
            $IPs = func_get_args();
        }
        $ok = false;
        $remote_ip = self::remote_ip();
        for($i = 0;$i < count($IPs);$i++)
        {
            if(self::ipCIDRCheck($remote_ip, $IPs[$i]))
            {
                if(!$allow_http)
                {
                    self::redirectHttps();
                }
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

    public static function redirectHttps()
    {
        if(php_sapi_name() == 'cli')
        {
            return;
        }
        if(!isset($_SERVER['HTTPS']) || $_SERVER['HTTPS'] !== 'on')
        {
            $host = isset($_SERVER['HTTP_HOST'])?$_SERVER['HTTP_HOST']:Config::getParam('hostname');
            $url = 'https://'.$host.$_SERVER['REQUEST_URI'];
            header('Location: '.$url);
            exit(0);
        }
    }

    public static function errorHandler($severity, $message, $file, $line, $context)
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

    public static function decodeJsonLoose($string)
    {
        if(is_null(self::$jsonDecoder))
        {
            self::$jsonDecoder = new Services_JSON(SERVICES_JSON_LOOSE_TYPE);
        }
        return self::$jsonDecoder->decode($string);
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
        $requiredVersion = 5.5;
        if(PHP_MAJOR_VERSION + 0.1 * PHP_MINOR_VERSION < $requiredVersion)
        {
            echo "Require PHP {$requiredVersion} or higher.";
            exit(0);
        }

        // check extensions
        $required = array('curl', 'PDO', 'pdo_mysql', 'openssl', 'mbstring', 'iconv');
        $something_missing = false;
        foreach($required as $ext)
        {
            if(!extension_loaded($ext))
            {
                echo "Extension {$ext} required.\n";
                $something_missing = true;
            }
        }
        if($something_missing)
        {
            exit(0);
        }
    }

    public static function not_empty($str)
    {
        // facebook doesn't accept \xA0 (\xC2\xA0 in utf-8)
        // but some utf-8 chars ended with \xA0, so not use trim()
        return trim($str) != '' && $str !== "\xC2\xA0";
    }

    /**
     * Reference: http://stackoverflow.com/questions/173400
     */
    public static function isAssoc($arr)
    {
        return array_keys($arr) !== range(0, count($arr) - 1);
    }

    public static function escapeUtf8($str, $double_escape = false)
    {
        $retval = json_encode($str);
        if($double_escape)
        {
            $retval = str_replace("\\", "\\\\", $retval);
        }
        return substr($retval, 1, -1);
    }

    public static function jsonEncode($arr, $flags = JSON_UNESCAPED_UNICODE)
    {
        $str = json_encode($arr, $flags);
        if($str === false)
        {
            throw new JsonEncodeFailure($arr);
        }
        return $str;
    }

    public static function xmlNodesRand($nodes)
    {
        return $nodes[rand(0, $nodes->count() - 1)];
    }

    public static function array_rand_item(array $arr)
    {
        return $arr[array_rand($arr)];
    }

    public static function isSubFile($filePath, $dirPath)
    {
        $dirPath = realpath($dirPath);
        do
        {
            $filePath = dirname($filePath);
            if($filePath == $dirPath)
            {
                return true;
            }
        } while($filePath != '/');
        return false;
    }

    public static function getCallerNameAndLine($offset = 0)
    {
        $backtrace = debug_backtrace();
        $function = '__main__'; // well, a python style
        if(count($backtrace) > $offset + 1) // +1 for this function
        {
            $prev_call = $backtrace[$offset + 1];
            $function = $prev_call['function'];
            if(isset($prev_call['class']))
            {
                $function = $prev_call['class'] . '::' . $function;
            }
        }
        if(count($backtrace) > $offset)
        {
            $cur_call = $backtrace[$offset];
            $function .= ':' . $cur_call['line'];
        }
        return $function;
    }

    public static function getWebSocketPort()
    {
        self::ip_only();
        return array('port' => Config::getParam('wsPort'));
    }

    public static function printMemoryUsage()
    {
        if(php_sapi_name() == 'cli')
        {
            print('At '.self::getCallerNameAndLine(1).', memory usage='.memory_get_usage()."\n");
        }
    }

    // $ch is a Curl instance
    public static function curlErrorHandler($ch)
    {
        throw new Exception($ch->error);
    }

    public static function handleException(Exception $e, &$output, $needContext = true)
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
        else if($errClass == 'JsonEncodeFailure')
        {
            $response_error['data'] = $e->getData();
        }

        $trace = $e->getTrace();
        $classNames = array();
        foreach($trace as $key => &$item)
        {
            if(isset($item['file']))
            {
                $path = dirname($item['file']);
                if(strncmp($path, APP_ROOT, strlen(APP_ROOT)) != 0)
                {
                    $newItem = array(
                        'file' => $item['file'], 
                        'line' => $item['line'], 
                        'function' => $item['function']
                    );
                    if(isset($item['class']))
                    {
                        $newItem['class'] = $item['class'];
                    }
                    $trace[$key] = $newItem;
                    continue;
                }
            }
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
                    if(!$needContext)
                    {
                        $item['args'][4] = null;
                    }
                    continue;
                }
                $classNames[] = $item['class'];
            }
        }

        $output['trace'] = $trace;

        for($i = 0;$i < count($classNames);$i++)
        {
            if(method_exists($classNames[$i], 'report_fields'))
            {
                // some tricks required to use call_user_func with reference values
                // http://stackoverflow.com/questions/295016
                $fReportFields = array($classNames[$i], 'report_fields');
                call_user_func_array($fReportFields, array(&$output, $e));
            }
        }

        $previous = $e->getPrevious();
        if($previous != null)
        {
            $output['previous'] = array();
            self::handleException($previous, $output['previous'], $needContext);
        }
    }
}
?>
