<?php include_once('dashcheck.php');
include_once('../core/ftpvars.php');
?>

<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Strict//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>neoCMS Settings :: FTP Info</title>
    <link href="../css/main.css" rel="stylesheet" type="text/css"/>
    <link href="../css/ui.css" rel="stylesheet" type="text/css"/>
    <script type="text/javascript" src="../js/lib/jquery.js"></script>
    <script type="text/javascript" src="../js/main.js"></script>
    <script type="text/javascript" src="../js/dash.js"></script>
</head>
<body class="neoCMSFTP">

<?php include 'dashnav.php'; ?>

<form id="neoCMSFTPForm" class="neoCMSAcctForm neoCMSAcctDisplay neoCMSAcctEditing" action="../core/ftpcreate.php"
      method="post" target="neoCMSSaveFTPFrame">
    <div class="neoCMSDashLeft">

        <p class="FTPtext"><strong>Please Note:</strong> FTP information is not required for most installations of
            neocms. If you are encountering file permission issues, save your FTP info here and try publishing/uploading
            again. neocms will write your FTP information to an unreadable file on this server.</p>

        <div class="neoCMSDashElement">

            <h3>Your FTP Info</h3>
            <ul>
                <li class="neoCMSFTPServerLi"><label>Server</label><input class="neoCMSFTPServer neoCMSUnlocked"
                                                                          name="ftps" type="text"
                                                                          value="<?php if ($ftps) echo $ftps; ?>"/><span>Please enter your server &ndash; no &ldquo;ftp.&rdquo;</span>
                </li>
                <li class="neoCMSFTPUserLi"><label>FTP Username</label><input class="neoCMSUnlocked" name="ftpu"
                                                                              type="text"
                                                                              value="<?php if ($ftpu) echo $ftpu; ?>"/><span>Please your FTP username</span>
                </li>
                <li class="neoCMSFTPPassLi"><label>FTP Password</label><input class="neoCMSUnlocked" name="ftpp"
                                                                              type="password" value=""/><span>Please enter your FTP password</span>
                </li>
                <li class="neoCMSFTPPathLi"><label>Domain Root</label><input class="neoCMSUnlocked" name="ftpwd"
                                                                             type="text"
                                                                             value="<?php if ($ftpwd) echo $ftpwd; ?>"/><span>Please enter the path to your site</span>
                </li>
            </ul>
        </div>
        <h2 class="neoCMSPrefSave"><a id="neoCMSFTPBtn" href="javascript:">save</a><a id="neoCMSFTPClearBtn"
                                                                                      href="javascript:">Clear all FTP
                info</a></h2>
    </div>
</form>

<?php include 'dashfoot.php'; ?>
      