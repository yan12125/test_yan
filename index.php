<?php
require 'common_inc.php';
$loginUrl = Config::getParam('rootUrl').'ui/index.php';
header('Location: '.$loginUrl);
?>
