<?php
require_once "../config.php";

spl_autoload_register(function ($class) {
    $classPath = str_replace('\\', DIRECTORY_SEPARATOR, $class);
    require_once "../src/{$classPath}.php";
});

use NeoCMS\CMSController;

// Instantiate the controller with necessary dependencies
$controller = new CMSController($config ?? []);
$controller->handleRequest();