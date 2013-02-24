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

?>
