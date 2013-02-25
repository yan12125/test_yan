<?php
$useFB = false;
require_once 'common_inc.php';

function postRate()
{
    global $db;
    $stmt = $db->query('select interval_min,interval_max from users where status="started"');
    $result = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $rate = 0;
    for($i = 0;$i < count($result);$i++)
    {
        $rate += 86400*2/((float)$result[$i]["interval_max"]+(float)$result[$i]["interval_min"]);
    }
    return round($rate, 2);
}

function stats($action, $value)
{
    global $db;
    $ret = false;
    switch($action)
    {
    case 'success':
    case 'timed_out':
        if($value <= 999 && $value >=1)
        {
            $stmt = $db->prepare('update statistics set N=N+1 where type=? and length=?');
            if(!$stmt->execute(array($action, $value)))
            {
                throw new Exception(getPDOErr($db));
            }
            $ret = true;
        }
        break;
    case 'timedout_success':
        $result = array(
            'success' => array(), 
            'timed_out' => array()
        );
        $res = $db->query('select * from statistics');
        while(($row = $res->fetch(PDO::FETCH_ASSOC)) !== false)
        {
            $result[$row['type']][$row['length']] = $row['N'];
        }
        $ret = $result;
        break;
    }
    return $ret;
}

if(isset($_GET['action']))
{
    if($_GET['action'] === 'timedout_success')
    {
        header('Content-type: text/plain;charset=UTF-8');
        $data = stats($_GET['action'], false);
        $success_total = 0;
        $timed_out_total = 0;
        echo "N    Success    Timed out  Rate\n".
             "------------------------------------\n";
        for($i=0;$i<=999;$i++)
        {
            $success = $data['success'][$i];
            $timed_out = $data['timed_out'][$i];
            $rate = 0;
            if($success+$timed_out != 0)
            {
                $success_total += $success;
                $timed_out_total += $timed_out;
                $rate = round($success/($success+$timed_out)*100, 2);
                echo str_pad($i, 5).str_pad($success, 11).str_pad($timed_out, 11).$rate."%\n";
            }
        }
        echo "------------------------------------\nSum  ".str_pad($success_total, 11).str_pad($timed_out_total, 11);
    }
}
?>
