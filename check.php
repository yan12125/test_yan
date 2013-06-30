#!/usr/bin/env php
<?php
if(php_sapi_name() !== 'cli')
{
    exit(0);
}

$skipPattern = array('/3rdparty/', '/test/');

function checkDirCommand($path)
{
    global $skipPattern;
    $command = '';
    $dir = opendir($path);
    while(($file = readdir($dir)) !== false)
    {
        if($file == '.' || $file == '..')
        {
            continue;
        }
        $filename = $path.DIRECTORY_SEPARATOR.$file;
        if(is_dir($filename))
        {
            $command .= checkDirCommand($filename);
        }

        $skip = false;
        for($i = 0;$i < count($skipPattern);$i++)
        {
            if(preg_match($skipPattern[$i], $filename))
            {
                $skip = true;
            }
        }
        if($skip)
        {
            continue;
        }

        if(preg_match('/\.php$/', $filename))
        {
            $command .= 'php -l '.$filename." 2>&1 &\n";
        }
    }
    return $command;
}

$result = explode("\n", shell_exec(checkDirCommand('.')));
function filter_no_syntax_error($str)
{
    return (strpos($str, 'No syntax errors detected') === false) && !empty($str);
}
$result = array_filter($result, 'filter_no_syntax_error');
if(!empty($result))
{
    echo implode("\n", $result)."\n";
}
else
{
    echo "No syntax errors detected.\n";
}
?>
