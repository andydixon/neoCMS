<?php include_once('dashcheck.php'); ?>

    <!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Strict//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd">
    <html xmlns="http://www.w3.org/1999/xhtml">
    <head>
        <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
        <title>neoCMS Settings :: Preferences</title>
        <link href="../css/main.css" rel="stylesheet" type="text/css"/>
        <link href="../css/ui.css" rel="stylesheet" type="text/css"/>
        <script type="text/javascript" src="../js/lib/jquery.js"></script>
        <script type="text/javascript" src="../js/main.js"></script>
        <script type="text/javascript" src="../js/dash.js"></script>
    </head>
<body class="neoCMSPref">

<?php include 'dashnav.php'; ?>

    <form id="neoCMSPrefsForm" class="neoCMSPrefForm" action="../core/sessionConfiguration.php" method="post"
          target="neoCMSSavePrefsFrame">
        <div class="neoCMSDashLeft">
            <div id="neoCMSPathElement" class="neoCMSDashElement">
                <h3>Your neocms file path</h3>
                <ul>
                    <li id="neoCMSPath" class="neoCMSPath"></li>
                </ul>
                <p>This is where your neocms files are located. This is also the path you&rsquo;ll follow to login to
                    your site&rsquo;s neocms account to begin editing your site content. <em><strong>Note:</strong>
                        neocms discovers this based on what you name your neocms folder.</em></p>
            </div>
            <div class="neoCMSDashElement">
                <h3>HTML Editing</h3>
                <label class="neoCMSPrefHtmlOn"><input id="htmltoggleon" name="htmltoggle" type="radio" value="Y"/>
                    on</label> <label class="neoCMSPrefHtmlOff"><input id="htmltoggleoff" name="htmltoggle" type="radio"
                                                                       value="N"/> off</label>
                <p>Set this preference to &ldquo;on&rdquo; if you want users to be able to directly edit the HTML
                    markup, or &ldquo;off&rdquo; if you do not want users to be able to edit the HTML.</p>
            </div>
            <div class="neoCMSDashElement">
                <h3>Exclude Folders</h3>
                <p>Enter a comma separated list of folder names or paths you do not want listed in the neocms file
                    browser. Do not use a &ldquo;/&rdquo; at the end fo the path. <em>Ex: &ldquo;wordpress, .svn,
                        core/important&rdquo;</em></p>
                <ul>
                    <li><label>Folders </label><input id="neoCMSExFolders" class="neoCMSUnlocked" name="exFolders"
                                                      type="text" value=""/></li>
                </ul>
            </div>
            <div class="neoCMSDashElement">
                <h3>File Uploads</h3>
                <label class="neoCMSPrefHtmlOn"><input id="filetoggleon" name="filetoggle" type="radio" value="Y"/>
                    on</label> <label class="neoCMSPrefHtmlOff"><input id="filetoggleoff" name="filetoggle" type="radio"
                                                                       value="N"/> off</label>
                <p>Set this preference to &ldquo;on&rdquo; if you want users to be able to upload files, or &ldquo;off&rdquo;
                    if you do not want users to upload files. There is a 100MB limit on all uploads.</p>
                <ul>
                    <li class="neoCMSFilePath">
                        <p>To specify a file folder, enter the relative path here. Leave it blank if you want neocms to
                            auto-discover folders.</p>
                        <label>Default File Path </label><input id="neoCMSFilePath" class="neoCMSUnlocked"
                                                                name="filePath" type="text" value=""/>
                    </li>
                </ul>
            </div>
            <div class="neoCMSDashElement">
                <h3>Image Uploads</h3>

                <p>To specify an image folder, enter the relative path here. Leave it blank if you want neocms to
                    auto-discover folders.</p>

                <ul>
                    <li><label>Default Image Path </label><input id="neoCMSImagePath" class="neoCMSUnlocked"
                                                                 name="imagePath" type="text" value=""/></li>
                </ul>
            </div>
            <h2 class="neoCMSPrefSave"><a id="neoCMSPrefBtn" href="javascript:">save</a></h2>
        </div>
    </form>

<?php include 'dashfoot.php'; ?>