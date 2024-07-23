<?php

$_users=[];
error_reporting(E_ALL & ~E_NOTICE);
@session_start();

if (isset($_SESSION['neoCMSSess']) && strstr($_SESSION['neoCMSSess'], 'neoCMSsession')) {

    include_once('createsession.php');

    if ($_POST) {

        include_once('getpath.php');

        $logingets = 'neoCMSUsername=' . urlencode($_POST['neoCMSUsername']) . '&neoCMSPassword=' . urlencode($_POST['neoCMSPassword']) . '&neoCMSSess=' . urlencode($_SESSION['neoCMSSess']) . '&neoCMSPath=' . urlencode($path);

        include_once('../config.php');

        if (@$_users[$_POST['neoCMSUsername']]["password"] == $_POST['neoCMSPassword']) {
            $_SESSION['neoCMSUserid'] = $_POST['neoCMSUsername'];
            $_SESSION['neoCMSUserurl'] = $_SERVER['HTTP_HOST'];
            $_SESSION['neoCMSIsadmin'] = $_users[$_POST['neoCMSUsername']]["isAdmin"];
        }

        include_once('sessionConfiguration.php');

        if (isset($_SESSION['neoCMSUserid']) && $_SESSION['neoCMSUserid'] != '') {
            $script = '<script type="text/javascript">window.top.window.location="../";</script>';
            echo $script;
        } else {
        $script = '<script type="text/javascript">window.top.window.loginIncorr("Incorrect Username or Password.");</script>';
        echo $script;
    }
}

} else {

    $script = '<script type="text/javascript">window.top.window.loginIncorr("There was an error creating a session.");</script>';
    echo $script;

}
