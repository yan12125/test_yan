<?php
class External
{
    const EXT_PATH = '3rdparty/';

    protected static $relativePath = '.';

    protected static $list = array(
        'codemirror' => array(
            'path' => 'bower_components/codemirror/', 
            'files' => array('lib/codemirror.js', 'lib/codemirror.css', 'mode/sql/sql.js')
        ), 
        'jquery' => array('files' => array('bower_components/jquery/jquery.min.js')), 
        'jquery-ui' => array(
            'path' => 'bower_components/jquery-ui/', 
            'files' => array('ui/jquery-ui.js', 'themes/base/jquery-ui.css')
         ), 
        'jqGrid' => array(
            'path' => 'bower_components/jqgrid/', 
            'files' => array(
                'js/minified/jquery.jqGrid.min.js', 
                'js/i18n/grid.locale-tw.js', 
                'css/ui.jqgrid.css'
            )
        ), 
        'validate' => array(
            'path' => 'bower_components/jquery.validation/', 
            'files' => array('jquery.validate.js', 'localization/messages_zh_TW.js')
        ), 
        'ajaxq' => array('files' => array('3rdparty/jquery.ajaxq.js')), 
        'phpjs' => array(
            'path' => 'bower_components/phpjs/functions/', 
            'files' => array('strings/sprintf.js', 'strings/parse_str.js')
         )
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
            $prefix = '';
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
