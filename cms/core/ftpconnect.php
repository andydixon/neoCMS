<?php

function ftpconnect()
{

    global $fftp, $ftpu, $ftpp, $ftps, $ftpwd, $ftpconn;

    if ($ftpu && $ftpp && $ftps && $ftpwd) {

        if ($ftpwd != '/') $ftpwd = rtrim($ftpwd, '/');

        //start ftp connection

        if (function_exists('ftp_connect')) {

            $ftpconn = ftp_connect($ftps) or die("Couldn't connect to $ftps");
            ftp_login($ftpconn, $ftpu, $ftpp);
            ftp_pasv($ftpconn, true);

        } else {

            $ftpconn = false;

            $getmessage = "<strong>Error:</strong> FTP is not enabled for your install of PHP. <a href='http://www.php.net/manual/en/ftp.setup.php' title='Installing FTP on PHP'>More info here.</a>";
            $savescript = '<script type="text/javascript">window.top.window.userBar("' . $getmessage . '");</script>';
            echo $savescript;

        }

    }

}

?>