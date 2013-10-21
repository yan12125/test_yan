<?php
class RandomString extends PluginBase
{
    static $lastParam = null;

    public function run($param)
    {
        self::$lastParam = $param;
        $arr = explode(",", $param);
        if(count($arr) != 2)
        {
            throw new Exception("Invalid parameters.");
        }
        $min = intval($arr[0]);
        $max = intval($arr[1]);
        if($min <= 0 || $max <= 0 || $min > $max)
        {
            throw new Exception("Invalid parameters.");
        }
        $ret = "";
        $N = mt_rand($min, $max);
        for($i = 0; $i < $N; $i++)
        {
            if(mt_rand(0, 1) == 1)
            {
                $ret .= $this->getCJKChar();
            }
            else
            {
                $ret .= $this->getAnsiChar();
            }
        }
        return $ret;
    }

    protected function getAnsiChar()
    {
        return chr(mt_rand(0x20, 0x7E));
    }

    protected function getCJKChar()
    {
        // 0x4E00 - 0x9FFF
        // TODO: frequently used characters
        $byte1 = mt_rand(0x4E, 0x9F);
        $byte2 = mt_rand(0x00, 0xFF);
        return iconv('UCS-2BE', 'UTF-8', pack("CC", $byte1, $byte2));
    }

    public function handleException($e)
    {
        return array(
            'source' => 'RandomString', 
            'message' => $e->getMessage(), 
            'n' => self::$lastParam
        );
    }
}
?>
