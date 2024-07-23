<?php include_once('dashcheck.php'); ?>

    <!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Strict//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd">
    <html xmlns="http://www.w3.org/1999/xhtml">
    <head>
        <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
        <title>neoCMS Settings :: User Info</title>
        <link href="../css/main.css" rel="stylesheet" type="text/css"/>
        <link href="../css/ui.css" rel="stylesheet" type="text/css"/>
        <script type="text/javascript" src="../js/lib/jquery.js"></script>
        <script type="text/javascript" src="../js/main.js"></script>
        <script type="text/javascript" src="../js/dash.js"></script>
    </head>
<body class="neoCMSAcct">

<?php include 'dashnav.php'; ?>

    <div class="neoCMSDashLeft">
        <div class="neoCMSDashElement">
            <form id="neoCMSAcctFormSuperAdmin" class="neoCMSAcctForm neoCMSAcctDisplay"
                  action="../core/getsaveusers.php" method="post" target="neoCMSSaveUserFrame">
                <h3></h3>
                <ul>
                    <li class="neoCMSAcctUserLi"><label>Username </label><input class="neoCMSLocked neoCMSAcctUser"
                                                                                name="neoCMSAcctUser" type="text"
                                                                                value="" readonly="true"/><input
                                class="neoCMSLocked neoCMSAcctUser-orig" name="neoCMSAcctUser-orig" type="hidden"
                                value="" readonly="true"/></li>
                    <li class="neoCMSAcctPassLi"><label>Password </label><input class="neoCMSLocked neoCMSAcctPass"
                                                                                name="neoCMSAcctPass" type="text"
                                                                                value="TOTALLY SECURE, REALLY"
                                                                                readonly="true"/></li>
                </ul>
                <ul class="neoCMSAcctMeta">
                    <!--<li><label>First &amp; Last Name </label><input class="neoCMSUnlocked" name="neoCMSAcctName" type="text" value="" readonly="true" /> <span class="neoCMSInstructions">For identification only</span></li>-->
                    <li class="neoCMSAcctEmailLi"><label>Email </label><input class="neoCMSLocked neoCMSAcctEmail"
                                                                              name="neoCMSAcctEmail" type="text"
                                                                              value="" readonly="true"/><input
                                class="neoCMSLocked neoCMSAcctEmail-orig" name="neoCMSAcctEmail-orig" type="hidden"
                                value="" readonly="true"/></li>
                </ul>
            </form>
        </div>
        <h4><a id="neoCMSNewUserLink" href="javascript:"
               title="give someone else the ability to edit content on your site">Add New User</a> <span>Only the account owner has administrative privileges</span>
        </h4>
        <div id="neoCMSNewUser" class="neoCMSNewUserElement">
            <form id="neoCMSAcctFormNewUser" class="neoCMSAcctForm neoCMSAcctDisplay"
                  action="../core/getsaveusers.php" method="post" target="neoCMSSaveUserFrame">
                <h3>New User</h3>
                <ul>
                    <li><label>Name </label><input id="neoCMSNewName" class="neoCMSUnlocked neoCMSAcctName"
                                                   name="neoCMSNewAcctName"
                                                   type="text"/><span>Please enter a name</span></li>
                    <li><label>Email </label><input class="neoCMSUnlocked neoCMSAcctEmail" name="neoCMSNewAcctEmail"
                                                    type="text" value=""/><span>Please enter a valid email</span></li>
                    <li><label>Username </label><input class="neoCMSUnlocked neoCMSAcctUser" name="neoCMSNewAcctUser"
                                                       type="text" value=""/><span>Please enter a username</span></li>
                    <li><label>Password </label><input id="neoCMSNewAcctPass" class="neoCMSUnlocked neoCMSAcctPass"
                                                       name="neoCMSNewAcctPass" type="password" value=""/><span>&ldquo;Medium&rdquo; or &ldquo;strong&rdquo; required.</span>
                    </li>
                    <li id="pwStrengthNew" class="neoCMSAcctPassInfo">n/a <span class="advice">&ldquo;Medium&rdquo; or &ldquo;strong&rdquo; required.</span>
                    </li>
                    <li><label>Confirm Password </label><input class="neoCMSUnlocked neoCMSAcctPassCon"
                                                               name="neoCMSNewAcctPassConfirm" type="password"
                                                               value=""/><span>Must match password</span></li>
                    <li class="neoCMSFormSave"><a id="neoCMSNewUserSave" class="userSave" href="javascript:"
                                                  title="save">save</a> <span class="neoCMSCancel">or <a
                                    id="neoCMSNewUserCancel" href="javascript:">cancel</a></span></li>
                </ul>
            </form>
        </div>
    </div>

<?php include 'dashfoot.php'; ?>