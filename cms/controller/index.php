<?php
/**
 * JSON API entry point for authenticated CMS operations.
 *
 * The controller performs authentication, authorisation, CSRF validation, and dispatch. Keeping
 * this file thin makes it harder for an endpoint to wander off and invent its own security rules.
 */

// Load the site-specific credentials, roles, and content settings.
require_once "../config.php";

// Resolve NeoCMS namespaces to the matching class beneath cms/src/.
spl_autoload_register(function ($class) {
    $classPath = str_replace('\\', DIRECTORY_SEPARATOR, $class);
    require_once "../src/{$classPath}.php";
});

use NeoCMS\CMSController;

// Dispatch the current request and allow the controller to emit its JSON response.
$controller = new CMSController($config ?? []);
$controller->handleRequest();
