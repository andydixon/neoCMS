<?php
require_once "config.php";
// Autoload classes (if not using Composer)
spl_autoload_register(function ($class) {
    $classPath = str_replace('\\', DIRECTORY_SEPARATOR, $class);
    require_once "./src/{$classPath}.php";
});

use NeoCMS\Authentication;

// Instantiate the Authentication class
$authentication = new Authentication($config['authentication']??[]);

// Check if the user is logged in
if (!$authentication->isLoggedIn()) {
    // Redirect to the login page
    header("Location: /cms/login/");
    exit;
}
?>

<!DOCTYPE html>
<html lang="en-gb">
<head>
    <title>NeoCMS</title>
    <!-- Include jQuery -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://code.jquery.com/ui/1.14.0/jquery-ui.min.js" crossorigin="anonymous"></script>
    <!-- Include TinyMCE -->
    <script src="/cms/tinymce/tinymce.min.js"></script>
    <!-- Include Bootstrap for modal -->
    <link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/3.4.1/css/bootstrap.min.css">

    <!-- Include JqueryUI CSS -->
    <link rel="stylesheet" href="https://code.jquery.com/ui/1.14.0/themes/base/jquery-ui.css">

    <!-- Include editor styles -->
    <link rel="stylesheet" href="/cms/css/editor.css">

    <!-- Include Bootstrap JS -->
    <script src="https://maxcdn.bootstrapcdn.com/bootstrap/3.4.1/js/bootstrap.min.js"></script>
</head>
<body>
<div id="message-bar" style="display:none;"></div>
<div class="pageContainer">
    <div class="controls">
        <div class="logo"></div>
       <?php if($config['showFullUrl']) echo '<div id="urlbox"></div>';?>
        <div class="buttonContainer">
            <ul>
                <li><a href="#" id="selectPage" class="blueButton">Select Page</a></li>
                <li><a href="#" id="newPage" class="blueButton">New Page</a></li>
                <li><a href="#" id="savePage" class="greenButton">Save Changes</a></li>
            </ul>
        </div>
        <div class="loggedInDetails">
            Logged in as: <?php echo $authentication->getLoggedinUser(); ?><br />
            <?php
            if ( ! is_writable("logs/")) {
                echo "<span class='redText'>Make /cms/logs/ writeable!</span>";
            }
            ?>
        </div>
    </div>
    <iframe id="frameContainer" src="<?php echo $config['skipWelcomePage'] ? "/" : "welcome.html"; ?>" class="frame"></iframe>
</div>
<!-- Modal -->
<div id="editModal" class="modal fade" role="dialog">
    <div class="modal-dialog modal-lg">

        <!-- Modal content-->
        <div class="modal-content">
            <div class="modal-header">
                <h4 class="modal-title">Edit Content</h4>
            </div>
            <div class="modal-body">
                <textarea id="editor"></textarea>
            </div>
            <div class="modal-footer">
                <button id="saveBtn" class="btn btn-primary">Save</button>
                <button type="button" class="btn btn-default" data-dismiss="modal">Close</button>
            </div>
        </div>

    </div>
</div>

<!--New Page modal -->
<div id="newPageDialog" title="Create a New Page">
    <div class="newpage-content">
        <form id="newPageForm">
            <div id="radioList">
                <!-- Radio buttons will be populated here -->
            </div>
            <label for="filename">New Filename:</label>
            <input type="text" id="filename" name="filename" placeholder="Enter new filename" required>

            <button type="submit">Submit</button>
        </form>
    </div>
</div>

<!-- Page list modal -->
<div id="fileListDialog" title="Select an Existing Page">
    <div class="filelist-content">
        <ul id="fileList">
            <!-- File names will be populated here -->
        </ul>
    </div>
</div>

<script src="/cms/js/cms.js"></script>

</body>
</html>
