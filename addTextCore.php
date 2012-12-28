<?php
$useFB=false;
require_once 'common_inc.php';

try
{
	if(isset($_POST['texts'])&&isset($_POST['title']))
	{
        $title = $_POST['title'];
        $texts = $_POST['texts'];
		$query="INSERT INTO texts (title,handler,text,maxLength,length,N) VALUES (?,NULL,?,?,?,?)";
        $textArr = explode("\n", str_replace("\r\n", "\n", $texts));
        $maxLength = 0;
        $tmpLen = 0;
        foreach($textArr as $line)
        {
            if(($tmpLen = strlen($line)) > $maxLength)
            {
                $maxLength = $tmpLen;
            }
        }
        $stmt = $db->prepare($query);
        if($stmt->execute(array($title, $texts, $maxLength, strlen($texts), count($textArr))) === false)
		{
			echo "PDO execute() failed: ".$db->errorInfo();
		}
		else
		{
            echo "成功增加留言。";
		}
        echo "<br>\n標題＝{$title}<br>\n內容＝<br>\n{$texts}<br>\n";
	}
    else
    {
        echo 'Error: insufficient parameters.';
    }
}
catch(Exception $e)
{
	echo $e->getMessage;
	exit(0);
}
?>
