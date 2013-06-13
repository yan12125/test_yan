<?php
class Stats
{
    protected static function postRate()
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

    public static function runningState()
    {
        $stmt = Db::query('select sum(count) from users');
        $num = $stmt->fetch(PDO::FETCH_NUM);
        $stmt = Db::query('select access_token from users where status="started" limit 1');
        $token = $stmt->fetch(PDO::FETCH_NUM);
        $stmt = Db::query('select count(uid) from users where status="started"');
        $userCount = $stmt->fetch(PDO::FETCH_NUM);
        $totalCount = 0;
        if(!is_null($token))
        {
            $totalCount = Fb::getCount($token[0]);
        }
        return array(
            array('name' => '每天發文數', 'value' => self::postRate()), 
            array('name' => '洗版人數', 'value' => number_format($userCount[0])), 
            array('name' => 'App洗版數', 'value' => number_format($num[0])), 
            array('name' => '總留言數', 'value' => number_format($totalCount))
        );
    }

    protected static function addResult($status, $length)
    {
        if($length <= 999 && $length >=0)
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

    public static function unexpected()
    {
        return self::addResult('unexpected', 0);
    }

    public static function report($page, $rows)
    {
        if($rows <= 0)
        {
            throw new Exception('Invalid number of rows.');
        }
        $data = array();
        $unexpected = 0;
        $total = array('success' => 0, 'timed_out' => 0);
        $stmt = Db::query('select * from statistics');
        while(($row = $stmt->fetch(PDO::FETCH_ASSOC)) !== false)
        {
            $length = $row['length'];
            switch($row['type'])
            {
                case 'timed_out':
                case 'success':
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
                        $total[$row['type']] += $row['N'];
                    }
                    break;
                case 'unexpected':
                    $unexpected = $row['N'];
                    break;
            }
        }
        array_unshift($data, array('length' => 'unexpected', 'success' => 0, 'timed_out' => $unexpected));
        array_unshift($data, array('length' => '合計', 'success' => $total['success'], 'timed_out' => $total['timed_out']));
        foreach($data as &$row)
        {
            if($row['success']+$row['timed_out']>0)
            {
                $ratio = $row['success']/($row['success']+$row['timed_out']);
                $row['ratio'] = round(100*$ratio, 2).'%';
            }
            else
            {
                $row['ratio'] = '0.00%';
            }
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
?>
