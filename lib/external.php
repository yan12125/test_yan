<?php
class External
{
    const EXT_PATH = '3rdparty/';

    protected static $relativePath = '.';

    protected static $list = array(
        'php-sql-parser' => array('path' => 'php-sql-parser/', 'files' => array('php-sql-parser.php')), 
        'facebook' => array('files' => array('facebook.php')), 
        'codemirror' => array('files' => array('codemirror.js', 'codemirror.css', 'sql.js')), 
        'jquery' => array('files' => array('jquery.js')), 
        'jquery-ui' => array('files' => array('jquery-ui.js', 'jquery-ui.css')), 
        'jqGrid' => array('files' => array('jquery.jqGrid.min.js', 'grid.locale-tw.js', 'ui.jqgrid.css')), 
        'validate' => array('files' => array('jquery.validate.js', 'messages_zh_TW.js')), 
        'ajaxq' => array('files' => array('jquery.ajaxq.js')), 
        'simple_html_dom' => array('files' => array('simple_html_dom.php')), 
        'parse_str' => array('files' => array('parse_str.js'))
    );

    protected static function load($type, $names)
    {
        $filenames = array();
        foreach($names as $item)
        {
            if(!isset(self::$list[$item]))
            {
                throw new Exception('Invalid module name '.$item);
            }
            $cur = self::$list[$item];
            $prefix = self::EXT_PATH;
            if(isset($cur['path']))
            {
                $prefix = $prefix.$cur['path'];
            }
            if($type == 'server')
            {
                $prefix = APP_ROOT.$prefix;
            }
            foreach($cur['files'] as $file)
            {
                $filename = $prefix.$file;
                if($type == 'server' && !is_file($filename))
                {
                    throw new Exception($filename.' does not exist!');
                }
                if($type == 'server')
                {
                    Util::addIncludePath($prefix);
                    require_once $filename;
                }
                else
                {
                    array_push($filenames, $filename);
                }
            }
        }
        if($type == 'client')
        {
            return $filenames;
        }
    }

    public static function loadPhp()
    {
        self::load('server', func_get_args());
    }

    public static function loadJsCss()
    {
        $modules = func_get_args();
        array_unshift($modules, 'jquery', 'ajaxq');
        $files = self::load('client', $modules);
        array_push($files, 'ui/util.js', 'ui/common.css');
        $output = '';
        foreach($files as $file)
        {
            $file = self::$relativePath.'/'.$file;
            $ext = pathinfo($file, PATHINFO_EXTENSION);
            switch($ext)
            {
                case 'js':
                    $output .= '<script language="javascript" src="'.$file.'"></script>';
                    break;
                case 'css':
                    $output .= '<link rel="stylesheet" href="'.$file.'">';
                    break;
            }
            $output .= "\n";
        }
        $script = '$(document).on("ready", function(e){ Util.relativePath = "'.self::$relativePath.'"; });';
        $output .= "<script language=\"javascript\">\n".$script."\n</script>\n";
        return $output;
    }

    public static function setRelativePath($path)
    {
        self::$relativePath = $path;
    }
}
?>
