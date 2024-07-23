<?php

//error_reporting (E_ALL & ~E_NOTICE);
@session_start();

if (isset($_SESSION['neoCMSSess']) && strstr($_SESSION['neoCMSSess'], 'neoCMSsession')) {

    include_once('urldissect.php');
    include_once('ftpstart.php');
    include_once('createsession.php');

    if (!empty($_POST) && isset($_SESSION['neoCMSUserid'])) {

        if ($_GET['type'] == 'img') {

            $file = $_FILES['neoCMSImgUpload'];

            $uploaddir = ($_POST['neoCMSImgFolder']) ? '../..' . $_POST['neoCMSImgFolder'] : '../../';
            $uploaddir = ($_SESSION['neoCMSImagePath']) ? '../../' . $_SESSION['neoCMSImagePath'] : $uploaddir;
            $uploadfile = $uploaddir . basename($file['name']);

            if (!@file_exists($uploadfile) || $_GET['over']) {

                $fileName = $file['name'];
                $fileSize = $file['size'];
                $alert = "<strong>Error:</strong> There was an error uploading your image. Please try again.";
                list($fileWidth, $fileHeight) = getimagesize($file['tmp_name']);

                if (preg_match('/(.jpg|.png|.gif)$/i', $fileName) && $fileSize < 5000000) {

                    if (move_uploaded_file($file['tmp_name'], $uploadfile)) {
                        $alert = "Your file was successfully uploaded.";
                    } elseif ($ftpconn) {

                        $path = urldissect($_SERVER['SCRIPT_NAME'], false, 3);
                        $uploadfile = str_ireplace('../../', '', $uploadfile);

                        $remotefile = $ftpwd . $path . $uploadfile;
                        $tempfile = fopen($file['tmp_name'], 'r');

                        if (ftp_fput($ftpconn, $remotefile, $tempfile, FTP_BINARY)) $alert = "Your file was successfully uploaded.";

                    }

                } else {
                    $alert = "<strong>Error:</strong> You may only upload <strong>.jpg</strong>, <strong>.gif</strong>, or <strong>.png</strong> image files, under <strong>5.0 MB</strong> in size.";
                    $fileName = "";
                }

                $script = '<script language="javascript" type="text/javascript">window.top.window.imageInfo("' . $fileSize . '", "' . $fileWidth . '", "' . $fileHeight . '", "' . $alert . '", "' . $fileName . '", "' . str_replace('../../', '', $uploaddir) . '" );</script>';
                echo $script;

            } else {

                $script = '<script language="javascript" type="text/javascript">window.top.window.imageOverwritePrompt();</script>';
                echo $script;

            }

        } else {

            $file = $_FILES['neoCMSUpload'];

            $uploaddir = ($_POST['neoCMSFolder']) ? '../..' . $_POST['neoCMSFolder'] : '../../';
            $uploaddir = ($_SESSION['neoCMSFilePath']) ? '../../' . $_SESSION['neoCMSFilePath'] : $uploaddir;
            $uploadfile = $uploaddir . basename($file['name']);

            if (!@file_exists($uploadfile) || $_GET['over']) {

                $fileName = $file['name'];
                $fileSize = $file['size'];
                $alert = "<strong>Error:</strong> There was an error uploading your image. Please try again.";

                if (!preg_match('/(\.html|\.php|\.js|\.css|\.htm|\.shtml|\.asp|\.cfm|\.phtml)$/i', $fileName) && $fileSize < 100000000) {

                    if ($ftpconn) {

                        $path = urldissect($_SERVER['SCRIPT_NAME'], false, 3);
                        $uploadfile = str_ireplace('../../', '', $uploadfile);

                        $remotefile = $ftpwd . $path . $uploadfile;
                        $tempfile = fopen($file['tmp_name'], 'r');

                        if (ftp_fput($ftpconn, $remotefile, $tempfile, FTP_BINARY)) $alert = "Your file was successfully uploaded.";
                        else {
                            $alert = "<strong>Error:</strong> You do not have permission to upload files to this folder.";
                            $script = '<script language="javascript" type="text/javascript">window.top.window.fileInfo("", "' . $alert . '", "", "" );</script>';
                            echo $script;
                        }

                    } elseif (move_uploaded_file($file['tmp_name'], $uploadfile)) {
                        $alert = "Your file was successfully uploaded.";
                    } else {
                        $alert = "<strong>Error:</strong> You do not have permission to upload files to this folder.";
                        $fileName = "";
                    }
                } else {
                    $alert = "<strong>Error:</strong> You may not upload <strong>.html</strong>, <strong>.htm</strong>, <strong>.php</strong>, <strong>.js</strong>, or <strong>.css</strong> files, under <strong>100 MB</strong> in size.";
                    $fileName = "";
                }

                $script = '<script language="javascript" type="text/javascript">window.top.window.fileInfo("' . $fileSize . '", "' . $alert . '", "' . $fileName . '", "' . str_replace('../../', '', $uploaddir) . '" );</script>';
                echo $script;

            } else {

                $script = '<script language="javascript" type="text/javascript">window.top.window.filePrompt();</script>';
                echo $script;

            }

        }

        if ($ftpconn) ftp_close($ftpconn);

    }

}

?>