<?php

@session_start();
include_once("config.php");

if (session_id() != "") {
    if (isset($_SESSION['neoCMSSess']) && isset($_SESSION['neoCMSUserid'])) {
        header("Location:./");
    } else {
        include_once('core/createsession.php');
    }

} else {
    $nosess = true;
}

@session_write_close();

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
    <script type="text/javascript" src="js/main.js"></script>

    <link href="css/main.css" rel="stylesheet" type="text/css"/>
</head>
<body>
<div id="neoCMSPromptWrap">

    <div id="neoCMSLoginWrap" class="neoCMSLoginWrap">

        <h1 class="logo">neoCMS</h1>
        <div id="neoCMSAppInfo" class="neoCMSAppInfo">
            <p><?php echo $versionInfo; ?>><br/>
                Copyright <?php echo @date('Y'); ?> <?php echo $companyName; ?>><br/></p>
            <p class="neoCMSTopBorder">neoCMS is developed by Neogate Technologies.</p>
        </div>
        <div id="neoCMSLoginCont" class="neoCMSLoginCont">
            <form id="neoCMSLoginForm" method="post" action="core/login.php" target="neoCMSLoginIframe">

                <fieldset>
                    <legend>Login</legend>
                    <ol>
                        <li id="neoCMSUsername"><label>Username</label><br/>
                            <input class="neoCMSInput" name="neoCMSUsername" type="text" tabindex="1"/>
                            <p class="error">Required</p></li>
                        <li id="neoCMSPassword"><label>Password</label><br/>
                            <input class="neoCMSInput" name="neoCMSPassword" type="password" tabindex="2"/>
                            <p class="error">Required</p></li>
                        <li id="neoCMSEnter" class="clearfix">
                            <button id="neoCMSLoginBtn" type="submit" class="neoCMSLoginBtn" tabindex="3">login
                            </button>
                            <p><a id="forgotPass" href="javascript:">Forgot your password?</a></p>
                            <p class="error invalid"></p></li>
                    </ol>
                    <div id="forgot" class="forgotLogin clearfix">
                        <p>Submit your username to receive an<br/>email with your new password.</p>
                        <button id="neoCMSSubmitBtn" type="submit" class="neoCMSSubmitBtn" tabindex="3">submit
                        </button>
                        <p class="clearfix"><a id="forgotCancel" href="javascript:">Back to login</a></p>
                        <p class="message"></p>
                    </div>
                </fieldset>

            </form>
        </div>

    </div>

</div>

<iframe id="neoCMSPageSrc" name="neoCMSPageSrc" marginheight="0" align="top" frameborder="0" src="../"
        application="yes"></iframe>

</body>
</html>