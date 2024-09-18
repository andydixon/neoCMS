<?php
require_once "neoCMSCore.php";
require_once "init.php";
?>
<!DOCTYPE html>
<html>
<head>
    <title>NeoCMS</title>
    <!-- Include jQuery -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <!-- Include TinyMCE -->
    <script src="/cms/tinymce/tinymce.min.js"></script>
    <!-- Include Bootstrap for modal -->
    <link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/3.4.1/css/bootstrap.min.css">

    <!-- Include editor styles -->
    <link rel="stylesheet" href="/cms/css/editor.css">

    <!-- Include Bootstrap JS -->
    <script src="https://maxcdn.bootstrapcdn.com/bootstrap/3.4.1/js/bootstrap.min.js"></script>
</head>
<body>

<div class="pageContainer">
    <div class="controls">
        <div class="logo"></div>
        <div class="buttonContainer">
            <ul>
                <li><a href="#" id="selectPage" class="blueButton">Select Page</a></li>
                <li><a href="#" id="newPage" class="blueButton">New Page</a></li>
                <li><a href="#" id="savePage" class="greenButton">Save Changes</a></li>
            </ul>
        </div>
        <div class="loggedInDetails">
            Logged in as: <?php echo $_SESSION["core"]->getLoggedinUser(); ?>
        </div>
    </div>
    <iframe id="frameContainer" src="welcome.html" class="frame"></iframe>
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

<script src="/cms/js/cms.js"></script>

</body>
</html>
