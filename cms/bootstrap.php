<?php
/** Shared entry-point setup: loads configuration into $config and registers the NeoCMS class autoloader. */

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
