<?php

error_reporting(E_ALL & ~E_NOTICE);
@session_start();

if (isset($_SESSION['neoCMSSess']) && strstr($_SESSION['neoCMSSess'], 'neoCMSsession')) {

    include_once('createsession.php');
    if ($_POST['page']) $_SESSION['neoCMSCurpage'] = $_POST['page'];

}

?>