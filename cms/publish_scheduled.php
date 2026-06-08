<?php
/**
 * Command-line worker for scheduled publications.
 *
 * Run this file from cron once per minute. It is intentionally unavailable over HTTP: scheduled
 * publishing is useful, while a public button marked "publish everything due" is rather less so.
 */

// Load the same configuration used by the interactive CMS.
require_once __DIR__ . '/config.php';

// Register the small project autoloader because NeoCMS deliberately has no Composer dependency.
spl_autoload_register(function ($class) {
    $classPath = str_replace('\\', DIRECTORY_SEPARATOR, $class);
    require_once __DIR__ . "/src/{$classPath}.php";
});

// Refuse web requests even if the web server accidentally exposes this script.
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

// Cron may omit DOCUMENT_ROOT, so derive it from the CMS directory when necessary.
if (empty($_SERVER['DOCUMENT_ROOT'])) {
    $_SERVER['DOCUMENT_ROOT'] = dirname(__DIR__);
}

// Publish every due job and print a concise result suitable for a cron log.
$controller = new NeoCMS\CMSController($config ?? []);
$count = $controller->publishScheduled();
echo "Published {$count} scheduled page(s).\n";
