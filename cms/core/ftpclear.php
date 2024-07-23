<?php

error_reporting(E_ALL & ~E_NOTICE);
@session_start();

if (isset($_SESSION['neoCMSSess']) && strstr($_SESSION['neoCMSSess'], 'neoCMSsession')) {

    include_once('urldissect.php');
    include_once('createsession.php');

    if (isset($_SESSION['neoCMSUserid']) && isset($_SESSION['neoCMSIsadmin']) && $_SESSION['neoCMSIsadmin'] == 'Y') {

        $ftpconn = false;

        $ftpvars = "<?php\n\n";
        $ftpvars .= "\t" . '$ftpu = ' . "false;\n";
        $ftpvars .= "\t" . '$ftpp = ' . "false;\n";
        $ftpvars .= "\t" . '$ftps = ' . "false;\n";
        $ftpvars .= "\t" . '$ftpwd = ' . "false;\n\n";
        $ftpvars .= '?>';

        $fftp = '';

        include_once('ftpvars.php');
        include_once('ftpconnect.php');

        ftpconnect();

        if ($ftpconn) {

            $ftpdoc = tmpfile();
            if (!$ftpdoc) {
                $ftpdocp = tempnam(dirname(__FILE__) . '/../settings/tmp', 'Ftpdoc');
                chmod($ftpdocp, 0777);
                $ftpdoc = fopen($ftpdocp, 'w+');
            }

            fwrite($ftpdoc, $ftpvars);
            rewind($ftpdoc);

            $path = urldissect($_SERVER['SCRIPT_NAME'], false, 1);

            $remotefile = $ftpwd . $path . 'ftpvars.php';

            if (ftp_fput($ftpconn, $remotefile, $ftpdoc, FTP_BINARY)) {

                $getmessage = "Your FTP information has been successfully cleared.";
                $savescript = '<script type="text/javascript">window.top.window.userBar("' . $getmessage . '");</script>';
                echo $savescript;

            } else {
                $getmessage = '<strong>Error:</strong> neocms could not find this page: <em>' . $remotefile . '</em>';
                $savescript = '<script type="text/javascript">window.top.window.userBar("' . $getmessage . '");</script>';
                echo $savescript;
            }

            fclose($ftpdoc);
            if (isset($ftpdocp)) unlink($ftpdocp);
        }

        echo '<script type="text/javascript">window.top.window.clearFTPInfo();window.top.window.removeFrames();</script>';

    }

}

?>