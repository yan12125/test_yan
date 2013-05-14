<?php
require 'common_inc.php';

class Stats
{
    public static function postRate()
    {
        $stmt = Db::query('select interval_min,interval_max,groups from users where status="started"');
        $result = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $rate = 0;
        for($i = 0;$i < count($result);$i++)
        {
            $gids = explode('_', $result[$i]['groups']);
            for($j = 0;$j < count($gids);$j++)
            {
                $interval = Users::adjustedInterval($result[$i], $gids[$j]);
                $averageInterval = ($interval['max']+$interval['min'])/2;
                $rate += (86400/$averageInterval)/count($gids);
            }
        }
        return round($rate, 2);
    }

    protected static function addResult($status, $length)
    {
        if($length <= 999 && $length >=1)
        {
            $stmt = Db::prepare('update statistics set N=N+1 where type=? and length=?');
            if(!$stmt->execute(array($status, $length)))
            {
                throw new Exception(Db::getErr());
            }
            return true;
        }
        return false;
    }

    public static function success($length)
    {
        return self::addResult('success', $length);
    }

    public static function timedOut($length)
    {
        return self::addResult('timed_out', $length);
    }

    public static function report($page, $rows)
    {
        if($rows <= 0)
        {
            throw new Exception('Invalid number of rows.');
        }
        $data = array();
        $stmt = Db::query('select * from statistics where type="success" or type="timed_out"');
        while(($row = $stmt->fetch(PDO::FETCH_ASSOC)) !== false)
        {
            $length = $row['length'];
            if($row['N'] > 0)
            {
                if(!isset($data[$length]))
                {
                    $data[$length] = array(
                        'length' => $length, 
                        'success' => 0, 
                        'timed_out' => 0
                    );
                }
                $data[$length][$row['type']] = $row['N'];
            }
        }
        foreach($data as &$row)
        {
            $ratio = $row['success']/($row['success']+$row['timed_out']);
            $row['ratio'] = round(100*$ratio, 2).'%';
        }
        $N = count($data);
        $nPages = ceil($N/$rows);
        if($page > $nPages || $page <= 0)
        {
            throw new Exception('Invalid page number.');
        }
        $partial_data = array_slice(array_values($data), ($page - 1) * $rows, $rows);
        return array('total' => $nPages, 'records' => $N, 'rows' => $partial_data);
    }
}

if(checkAction(__FILE__))
{
    try
    {
        switch($_POST['action'])
        {
            case 'report':
                checkPOST(array('page', 'rows'));
                header('Content-type: application/json;charset=UTF-8');
                echo json_encode(Stats::report($_POST['page'], $_POST['rows']));
                break;
        }
    }
    catch(Exception $e)
    {
        echo json_encode(array('error' => $e->getMessage()));
    }
}
?>
