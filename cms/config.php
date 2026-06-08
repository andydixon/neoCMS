<?php
/**
 * NeoCMS site configuration.
 *
 * Keep credentials in the ignored config.local.php override rather than this tracked defaults file.
 * No account is enabled by default; configure a password hash and role before exposing /cms/.
 */

$config = [
    'authentication' => [
        // 'admin' => '$2y$10$replace-with-a-real-password-hash',
    ],
    // Grant each user the least powerful role they need: editor, publisher, or administrator.
    'roles' => [
        // 'admin' => 'administrator',
    ],
    // Session expiry and login throttling. Set cookieSecure to true behind a TLS reverse proxy.
    'security' => [
        'idleTimeout' => 1800,
        'absoluteTimeout' => 43200,
        'cookieSecure' => null,
        'loginWindowSeconds' => 900,
        'loginLockoutSeconds' => 900,
        'loginMaxAttempts' => 5,
        'loginMaxAddressAttempts' => 20,
        'maxContentBytes' => 5 * 1024 * 1024,
        'maxRequestBytes' => 6 * 1024 * 1024,
        'maxManagedPages' => 5000,
        'maxScannedEntries' => 20000,
        'maxDraftBytes' => 250 * 1024 * 1024,
        'maxSchedules' => 100,
        'maxScheduledBytes' => 250 * 1024 * 1024,
        'maxRevisionsPerPage' => 50,
        'maxRevisionsTotal' => 2000,
        'maxRevisionBytes' => 500 * 1024 * 1024,
        'auditMaxFileBytes' => 10 * 1024 * 1024,
        'auditRetentionDays' => 90,
    ],
    // Upload constraints limit both individual image complexity and total disk consumption.
    'uploads' => [
        'maxFileBytes' => 10 * 1024 * 1024,
        'maxWidth' => 8192,
        'maxHeight' => 8192,
        'maxPixels' => 24 * 1024 * 1024,
        'maxFiles' => 2000,
        'maxTotalBytes' => 500 * 1024 * 1024,
    ],
    // Record authentication and content-management activity in daily files under cms/logs/.
    'audit' => true,
    // Skip the built-in welcome page and open the public site immediately after login.
    'skipWelcomePage' => false,
    // Display the path of the page being edited in the administration toolbar.
    'showFullUrl' => true,
    // CSS class used to mark page regions as editable. Use a single class name without a leading dot.
    'editableClass' => 'editable',
];

// Merge deployment credentials and overrides from the untracked local configuration, when present.
$localConfigPath = __DIR__ . '/config.local.php';
if (is_file($localConfigPath)) {
    $localConfig = require $localConfigPath;
    if (!is_array($localConfig)) {
        throw new \RuntimeException('cms/config.local.php must return an array');
    }
    $config = array_replace_recursive($config, $localConfig);
}
