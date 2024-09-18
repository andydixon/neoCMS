<?php
require_once "../neoCMSCore.php";
require_once "../init.php";

switch ($_REQUEST["action"]) {
    case "save":
        if (!empty($_POST['uri']) && !empty($_POST['content'])) {
            $_SESSION['core']->saveContent($_POST['uri'], $_POST['content']);
            die("Page Updated.");
        } else {
            die('Unable to save page');
        }
        break;
}