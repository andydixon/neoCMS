<?php
/** Shared entry-point setup: loads configuration into $config and registers the NeoCMS class autoloader. */

// Keep stack traces and filesystem paths out of responses; every engine warning still reaches PHP's own error log.
error_reporting(E_ALL);
ini_set('display_errors', '0');
ini_set('display_startup_errors', '0');
ini_set('log_errors', '1');

require_once __DIR__ . '/config.php';

// URL prefix of the site root: "/neocms" for http://localhost/neocms/, "" for a site at the domain root.
// Derived from this script's URL unless config.local.php sets 'basePath' (e.g. behind a path-rewriting proxy).
$config['basePath'] = rtrim((string) ($config['basePath'] ?? (preg_match('#^(.*)/cms/#', $_SERVER['SCRIPT_NAME'] ?? '', $neoBase) ? $neoBase[1] : '')), '/');
// The site root on disk is always the folder containing cms/, whatever DOCUMENT_ROOT says.
$config['siteRoot'] ??= dirname(__DIR__);
$config['security']['cookiePath'] = $config['basePath'] . '/cms';

spl_autoload_register(function ($class) {
    $classPath = str_replace('\\', DIRECTORY_SEPARATOR, $class);
    require_once __DIR__ . "/src/{$classPath}.php";
});
