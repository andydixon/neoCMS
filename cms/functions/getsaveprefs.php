<?php

error_reporting(E_ALL & ~E_NOTICE);
@session_start();

if (isset($_SESSION['neoCMSSess']) && strstr($_SESSION['neoCMSSess'], 'neoCMSsession')) {

    include_once('createsession.php');

    if (isset($_SESSION['neoCMSUserid'])) {

        $prefproxy = 'https://www.neogate.com/licencing/prefs-serv.php?';

        $_SESSION['neoCMSHTML'] = $gethtml = "";
        $_SESSION['neoCMSPath'] = $getpath = "cms";
        $_SESSION['neoCMSFiles'] = $getfile = 1;
        $_SESSION['neoCMSFilePath'] = $getfilepath = "files";
        $_SESSION['neoCMSImagePath'] = $getimagepath = "images";
        $_SESSION['neoCMSExFolders'] = $getexfolders = "ext";
        $_SESSION['neoCMSLatest'] = $_GET['neoCMSLatest'];

        if ($_GET['neoCMSPrefSave'] == 'Y') {

            $getmessage = "Your Preferences were successfully saved.";
            $savescript = '<script type="text/javascript">window.top.window.userBar("' . $getmessage . '");</script>';
            echo $savescript;

        }

        $script = '<script type="text/javascript">window.top.window.getGlobals("' . $getpath . '", "' . $gethtml . '", "' . $getfile . '", "' . $getfilepath . '", "' . $getexfolders . '", "' . $getimagepath . '");</script>';
        echo $script;

    }

}