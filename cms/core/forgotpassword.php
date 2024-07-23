<?php

error_reporting(E_ALL & ~E_NOTICE);
@session_start();

if (isset($_SESSION['neoCMSSess']) && strstr($_SESSION['neoCMSSess'], 'neoCMSsession')) {

    include_once('createsession.php');

    $forgotproxy = 'https://www.neogate.com/licencing/forgotpass-serv.php?';

    if ($_POST && $_POST['neoCMSUsername']) {

        include_once('getpath.php');

        $forgotgets = 'neoCMSSess=' . urlencode($_SESSION['neoCMSSess']) . '&neoCMSPath=' . $path . '&neoCMSAcctUser=' . urlencode($_POST['neoCMSUsername']);

        header("Location:$forgotproxy$forgotgets");

    } elseif ($_GET && $_GET['neoCMSSess'] == $_SESSION['neoCMSSess']) {

        $m = $_GET['neoCMSPassMessage'];

        echo $m;

        if ($m == '0') $getmessage = "Your password was reset and an email was sent.";
        elseif ($m == '1') $getmessage = "There is no such user on this domain.";

        $script = '<script type="text/javascript">window.top.window.forgotReset("' . $getmessage . '");</script>';
        echo $script;

    }
}

?>