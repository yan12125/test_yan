<?php
require_once 'common_inc.php';
ip_only('127.0.0.1');

$result = null;
$pre = false;
$q = '';
if(isset($_POST['q']))
{
    $q = $_POST['q'];
    $stmt = $db->query($q);
    if(!$stmt)
    {
        print_r($db->errorInfo());
        exit(0);
    }
    $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
}
?>
<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<title>sql.php</title>
<script src="/HTML/library/jquery.js"></script>
<script>
</script>
<style>
.result
{
    width: 100%;
    border-collapse: collapse;
}

.result td
{
    border-width: 1px;
    border-style: solid;
    border-color: black;
    word-break: break-all;
}
</style>
</head>
<body>
    <form action="#" method="POST">
        Query: <input type="text" name="q" value="<?php echo $q; ?>">
        <input type="submit" value="Submit">
    </form>
    <?php
        if(!is_null($result))
        {
            if(count($result) == 0)
            {
                echo 'No results.<br>';
            }
            else
            {
                $columns = array_keys($result[0]);
                echo '<table class="result"><tr>';
                for($i = 0;$i < count($columns);$i++)
                {
                    echo '<td>'.$columns[$i]."</td>\n";
                }
                echo "</tr>\n";
                for($i = 0;$i < count($result);$i++)
                {
                    echo '<tr>';
                    foreach($result[$i] as $value)
                    {
                        echo '<td>'.$value.'</td>';
                    }
                    echo "</tr>\n";
                }
                echo '</table>';
            }
        }
    ?>
</body>
</html>
