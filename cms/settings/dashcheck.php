<?php

@session_start();

if (isset($_SESSION['neoCMSSess']) && isset($_SESSION['neoCMSUserid'])) {

    session_name($_SESSION['neoCMSSess']);
    @session_start();

    $user = &$_SESSION['neoCMSUserid'];
    $admin = &$_SESSION['neoCMSIsadmin'];
    $sess = &$_SESSION['neoCMSSess'];

    if ($admin != 'Y') header("Location:../index.php");

} else header("Location:../login.php");

if (file_exists('../../_neoCMSupdate000.php')) {

    include_once('../core/ftpstart.php');
    include_once('../core/urldissect.php');

    if ($ftpconn) {

        $updatepath = urldissect($_SERVER['SCRIPT_NAME'], false, 3);
        ftp_delete($ftpconn, $ftpwd . $updatepath . '_neoCMSupdate000.php');
        ftp_close($ftpconn);

    } else {

        unlink('../../_neoCMSupdate000.php');

    }

}

?>