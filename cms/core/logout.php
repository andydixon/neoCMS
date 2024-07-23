<?php

error_reporting(E_ALL & ~E_NOTICE);
@session_start();

session_unset($_SESSION);
$_SESSION = array();

session_destroy();
@session_write_close();

exit;

?>