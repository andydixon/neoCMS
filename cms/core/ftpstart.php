<?php

$ftpconn = false;

include_once('ftpvars.php');
include_once('ftpconnect.php');

ftpconnect($ftpu, $ftpp, $ftps, $ftpwd);

?>