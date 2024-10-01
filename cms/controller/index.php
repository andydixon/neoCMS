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

    /**
     * getTemplates AJAX call to get list of files
     */
    case "getTemplates":
        header('Content-type: application/json');
        $files=array_diff(scandir("../templates/"),array('..', '.'));
        $returnArray = array();
        foreach($files as $file) {
            $returnArray[]=array("id" => $file, "name" => str_replace(array(".htm",".html"), "", $file));
        }
        return json_encode($returnArray);
        break;

    /**
     * newPage AJAX call to take a template file and create a new page
     */
    case "newPage":
        $filename = str_replace("..","",$_POST['filename']);
        $template = str_replace("..","",$_POST['template']);

        if(!str_ends_with($filename, ".htm") && !str_ends_with($filename, ".html")) {
            $filename .= ".html";
        }

        if(!file_exists("../templates/".$template)) return json_encode(array("error" => "Template not found"));
        if(!copy("../templates/".$template,"../../".$filename)) return json_encode(array("error" => "Template copy failed"));
        return json_encode(array("ok" => "DONE_THE_NEEDFUL","url"=>$filename));
        break;
}