<?php

error_reporting(E_ALL & ~E_NOTICE);
@session_start();

if (isset($_SESSION['neoCMSSess']) && strstr($_SESSION['neoCMSSess'], 'neoCMSsession')) {

    include_once('createsession.php');
    $loginproxy = 'https://www.neogate.com/licensing/login-serv.php?';

    if ($_POST) {

        include_once('getpath.php');

        $logingets = 'neoCMSUsername=' . urlencode($_POST['neoCMSUsername']) . '&neoCMSPassword=' . urlencode($_POST['neoCMSPassword']) . '&neoCMSSess=' . urlencode($_SESSION['neoCMSSess']) . '&neoCMSPath=' . urlencode($path);

        header("Location:$loginproxy$logingets");

    } elseif (isset($_GET['neoCMSUsername']) && isset($_GET['neoCMSSess']) && $_GET['neoCMSSess'] == $_SESSION['neoCMSSess'] && isset($_GET['neoCMSURL']) && isset($_GET['isAdmin'])) {


        $_SESSION['neoCMSUserid'] = $_GET['neoCMSUsername'];
        $_SESSION['neoCMSUserurl'] = $_GET['neoCMSURL'];
        $_SESSION['neoCMSIsadmin'] = $_GET['isAdmin'];

        include_once('getsaveprefs.php');

        if (isset($_SESSION['neoCMSUserid']) && $_SESSION['neoCMSUserid'] != '') {
            $script = '<script type="text/javascript">window.top.window.location="../";</script>';
            echo $script;
        }

    } elseif (isset($_GET['error'])) {

        if ($_GET['error'] == 'login') $message = "Incorrect username or password";
        elseif ($_GET['error'] == 'domain') $message = "Domain not registered. Please contact.";

        $script = '<script type="text/javascript">window.top.window.loginIncorr("' . $message . '");</script>';
        echo $script;
    }

} else {

    $script = '<script type="text/javascript">window.top.window.loginIncorr("There was an error creating a session.");</script>';
    echo $script;

}

?>
