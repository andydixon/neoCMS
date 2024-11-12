<?php
require_once "../neoCMSCore.php";
require_once "../init.php";

switch ($_REQUEST["action"]) {
    case "save":
        header('Content-type: application/json');
        if (!empty($_POST['uri']) && !empty($_POST['content'])) {
            $filename=$_SESSION['core']->saveContent($_POST['uri'], $_POST['content']);
            echo json_encode(array('message' => 'Page has been saved','destination'=>$filename));
        } else {
            echo json_encode(array('error' => 'Unable to save page'));
        }
        break;

    /**
     * getTemplates AJAX call to get list of files
     */
    case "getTemplates":
        header('Content-type: application/json');
        $files = array_diff(scandir("../templates/"), array('..', '.'));
        $returnArray = [];
        foreach ($files as $file) {
            $returnArray[] = array("id" => $file, "name" => str_replace(array(".html", ".htm"), "", $file));
        }
        echo json_encode($returnArray);
        break;

    /**
     * newPage AJAX call to take a template file and create a new page
     */
    case "newPage":
        header('Content-type: application/json');

        $filename = str_replace("..", "", $_POST['filename']);
        $template = str_replace("..", "", $_POST['template']);
        if (!str_ends_with($filename, ".htm") && !str_ends_with($filename, ".html")) {
            $filename .= ".html";
        }

        // Some vhosts can supply a trailing slash on DOCUMENT_ROOT so tidy it up
        $destination = str_replace("//", "/", $_SERVER['DOCUMENT_ROOT'] . "/" . $filename);


        if (!file_exists("../templates/" . $template)) die(json_encode(array("error" => "Template not found")));
        if (file_exists($destination)) die(json_encode(array("error" => "Page or file already exists")));

        file_put_contents($destination, file_get_contents("../templates/" . $template));
        if (!file_exists($destination)) die(json_encode(array("error" => "Template copy failed")));
        echo json_encode(array("ok" => "DONE_THE_NEEDFUL", "url" => str_replace("//", "/", "/" . $filename)));
        break;

    case "getPages":
        header('Content-type: application/json');
        $it = new RecursiveDirectoryIterator("../../");
        $returnArray = [];
        foreach (new RecursiveIteratorIterator($it) as $file) {
            // Strip document root from URI, so relative
            $file = str_replace($_SERVER["DOCUMENT_ROOT"], "", $file->getRealPath());

            if (!str_starts_with($file, "/cms/") && (str_ends_with($file, ".html") || str_ends_with($file, ".htm")))
                $returnArray[] = array("name" => $file, "url" => $file);
        }
        echo json_encode($returnArray);
        break;

}