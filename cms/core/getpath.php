<?php

error_reporting(E_ALL & ~E_NOTICE);
@session_start();

if (isset($_SESSION['neoCMSSess']) && strstr($_SESSION['neoCMSSess'], 'neoCMSsession')) {

    include_once('geturi.php');
    include_once('createsession.php');

    $pathproxy = 'https://www.neogate.com/licencing/prefs-serv.php?';
    $scripturl = $_SERVER['HTTP_HOST'] . uri();

    $path = explode('/', $scripturl);
    $path = $path[sizeof($path) - 3];

    if (isset($_SESSION['neoCMSPath']) && $_SESSION['neoCMSPath'] != $path) {

        $pathgets = 'neoCMSUsername=' . urlencode($_SESSION['neoCMSUserid']) . '&neoCMSSess=' . urlencode($_SESSION['neoCMSSess']) . '&neoCMSPath=' . urlencode($_POST['neoCMSHTML']);

        header("Location:$pathproxy$pathgets");

    }

}
