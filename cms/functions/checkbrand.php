<?php

error_reporting(E_ALL & ~E_NOTICE);

$doc = file_get_contents("./login.php", true);
$doc = stripslashes($doc);

$cb = true;


?>