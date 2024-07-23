<?php
@session_start();

if (isset($_SESSION['neoCMSSess'])) {

    @session_name($_SESSION['neoCMSSess']);
    @session_start();

    if (isset($_SESSION['neoCMSUserid'])) {

        $user = &$_SESSION['neoCMSUserid'];
        $admin = &$_SESSION['neoCMSIsadmin'];
        $sess = &$_SESSION['neoCMSSess'];

    } else header("Location:./login.php");
} else header("Location:./login.php");

$page = (isset($_SESSION['neoCMSCurpage'])) ? $_SESSION['neoCMSCurpage'] : "../";

?>

<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN""http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <meta name="robots" content="noindex"/>
    <link rel="shortcut icon" href="images/icon.png"/>
    <link rel="apple-touch-icon-precomposed" href="images/apple-touch-icon-precomposed.png"/>
    <title>neoCMS</title>

    <script type="text/javascript" src="js/lib/jquery.js"></script>
    <script type="text/javascript" src="js/lib/jquery-ui.js"></script>
    <script type="text/javascript" src="js/tiny_mce/tiny_mce.js"></script>

    <script type="text/javascript" src="js/codemirror/js/codemirror.js"></script>

    <script type="text/javascript" src="js/main.js"></script>
    <script type="text/javascript" src="js/ui.js"></script>

    <link href="css/main.css" rel="stylesheet" type="text/css"/>
    <link href="css/ui.css" rel="stylesheet" type="text/css"/>

</head>
<body>

<div id="neoCMSFrameShim"></div>

<div id="neoCMSTopBanner" class="neoCMSTopBanner">
    <div class="neoCMSHeader">
        <ul class="neoCMSTopNav">
            <li id="neoCMSUndo"><a title="Cannot Undo at this time" href="javascript:">Undo</a></li>
            <li id="neoCMSRedo"><a title="Cannot Redo at this time" href="javascript:">Redo</a></li>
            <li id="neoCMSCxl"><a title="Cannot Cancel All at this time" href="javascript:">Cancel All</a></li>
            <li id="neoCMSRestore"><a title="Restore to the last published version" href="javascript:">Restore</a>&middot;
            </li>
            <li id="neoCMSPublish"><a title="You must make edits before publishing" href="javascript:">Publish</a></li>
        </ul>

        <h1><a id="neoCMSHome" href="javascript:" title="Go to your homepage">neocms</a></h1>

        <ul class="neoCMSInfo">
            <li id="neoCMSLogout"><a title="Logout of neocms" href="javascript:">logout</a>&middot;</li>
            <?php if ($admin == 'Y') : ?>
                <li id="neoCMSDashboard"><a title="Change your preferences, edit users, or enter your FTP info"
                                            href="javascript:">settings</a>&middot;
                </li><?php endif; ?>
            <li><a class="neoCMSFBrowse" rel="#neoCMSPages" title="Choose a page to edit" href="javascript:">pages</a>
            </li>
        </ul>
    </div>
</div>

<iframe id="neoCMSPageSrc" name="neoCMSPageSrc" marginheight="0" align="top" frameborder="0" src="<?php echo $page; ?>"
        application="yes"></iframe>

</body>
</html>