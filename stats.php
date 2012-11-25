<?php
$useFB = false;
require_once 'common_inc.php';

function stats($action, $value)
{
    $ret = false;
    switch($action)
    {
    case 'success':
    case 'timed_out':
        if($value <= 999 && $value >=1)
        {
            mysql_query('update statistics set N=N+1 where type="'.$action.'" and length='.$value);
            if(mysql_errno()!=0)
            {
                throw new Exception(mysql_error());
            }
            $ret = true;
        }
        break;
    }
    return $ret;
}
