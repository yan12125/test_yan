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
    if(isset($_POST['pre']))
    {
        $pre = true;
    }
}
?>
<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<title>sql.php</title>
<script src="/HTML/library/jquery.js"></script>
<script>
<?php
if($pre)
{
?>
$(document).on('ready', function(e) {
    $('[name="pre"]')[0].checked = true;
});
<?php
}
?>
</script>
</head>
<body>
    <form action="#" method="POST">
        Query: <input type="text" name="q" value="<?php echo $q; ?>">
        <input type="checkbox" name="pre">Pre
        <input type="submit" value="Submit">
    </form>
    <?php
        if(!is_null($result))
        {
            if($pre)
            {
                echo "<pre>\n";
            }
            print_r($result);
            if($pre)
            {
                echo "\n</pre>\n";
            }
        }
    ?>
</body>
</html>
