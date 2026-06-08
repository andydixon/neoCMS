<?php
/**
 * Dependency-free NeoCMS integration suite.
 *
 * The suite creates an isolated website and data directory, exercises controller actions directly,
 * and removes every temporary artefact afterwards. Production content is therefore spared the
 * indignity of becoming a test fixture.
 */

declare(strict_types=1);

// Resolve first-party classes from the project source tree.
spl_autoload_register(function (string $class): void {
    $prefix = 'NeoCMS\\';
    if (!str_starts_with($class, $prefix)) {
        return;
    }
    require_once __DIR__ . '/../cms/src/NeoCMS/' . substr($class, strlen($prefix)) . '.php';
});

/** Recursively remove a temporary test tree, processing children before their parents. */
function removeTree(string $path): void
{
    if (!is_dir($path)) {
        return;
    }
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST
    );
    foreach ($iterator as $item) {
        ($item->isDir() && !$item->isLink()) ? rmdir($item->getPathname()) : unlink($item->getPathname());
    }
    rmdir($path);
}

/** Raise an immediately useful test failure when a behavioural expectation is false. */
function assertTrue(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

/** Assert that an operation is rejected with an application-level runtime exception. */
function assertRejected(callable $operation, string $message): void
{
    try {
        $operation();
    } catch (RuntimeException $exception) {
        return;
    }
    throw new RuntimeException($message);
}

// Build isolated public and metadata roots for this test run.
$root = sys_get_temp_dir() . '/neocms-test-' . bin2hex(random_bytes(5));
$data = $root . '-data';
$outside = $root . '-outside';
mkdir($root, 0755, true);
mkdir($outside, 0755, true);

// The primary fixture uses a non-default editable class plus shared and menu regions.
$page = '<!DOCTYPE html><html><head><title>Test</title></head><body>'
    . '<main class="cms-content">Original</main>'
    . '<footer class="cms-content" data-neo-shared="footer">Old footer</footer>'
    . '<nav data-neo-menu="main"><ul><li>Old</li></ul></nav>'
    . '</body></html>';
file_put_contents($root . '/index.html', $page);
file_put_contents($root . '/ignored.html', '<div class="editable">Wrong configured class</div>');

// Seed an authenticated administrator session without submitting real credentials.
$_SERVER['DOCUMENT_ROOT'] = $root;
session_id('neocms-test-' . bin2hex(random_bytes(5)));
session_start();
$_SESSION = [
    'loggedIn' => true,
    'loggedInUser' => 'tester',
    'role' => 'administrator',
    'csrfToken' => 'test-token',
    'authenticatedAt' => time(),
    'lastActivityAt' => time(),
    'lastRegeneratedAt' => time(),
];

// Point the controller at temporary storage and the deliberately overridden editable class.
$config = [
    'authentication' => ['tester' => password_hash('unused', PASSWORD_DEFAULT)],
    'roles' => ['tester' => 'administrator'],
    'audit' => false,
    'editableClass' => 'cms-content',
    'dataDirectory' => $data,
    'security' => [
        'maxContentBytes' => 4096,
        'maxRequestBytes' => 8192,
        'maxRevisionsPerPage' => 2,
        'maxRevisionsTotal' => 4,
        'maxRevisionBytes' => 32768,
        'maxSchedules' => 5,
        'maxScheduledBytes' => 32768,
        'maxDraftBytes' => 32768,
    ],
];

/**
 * Simulate one controller request and return its decoded successful JSON response.
 * Errors are promoted to exceptions so the test stops at the first broken contract.
 */
$request = function (string $action, array $parameters = [], string $method = 'GET') use ($config): array {
    $_SERVER['REQUEST_METHOD'] = $method;
    $_GET = $method === 'GET' ? array_merge(['action' => $action], $parameters) : [];
    $_POST = $method === 'POST' ? array_merge(['action' => $action, 'csrf_token' => 'test-token'], $parameters) : [];
    $_REQUEST = array_merge($_GET, $_POST);
    http_response_code(200);
    ob_start();
    (new NeoCMS\CMSController($config))->handleRequest();
    $body = (string) ob_get_clean();
    $response = json_decode($body, true);
    if (!is_array($response)) {
        throw new RuntimeException('Invalid JSON response: ' . $body);
    }
    if (isset($response['error'])) {
        throw new RuntimeException($action . ' failed: ' . $response['error']);
    }
    return $response;
};

try {
    // The administration preview must retain DOM access while refusing all page script execution.
    $adminShell = (string) file_get_contents(__DIR__ . '/../cms/index.php');
    assertTrue(str_contains($adminShell, 'sandbox="allow-same-origin"'), 'Administration preview is not sandboxed');
    assertTrue(!str_contains($adminShell, 'allow-scripts'), 'Administration preview permits page scripts');
    assertTrue(!str_contains($adminShell, 'bootstrap.min'), 'End-of-life Bootstrap assets remain enabled');
    assertTrue(str_contains($adminShell, 'jquery-4.0.0.min.js') && str_contains($adminShell, 'integrity="sha384-'), 'Pinned jQuery assets are missing SRI');
    $tinyMce = (string) file_get_contents(__DIR__ . '/../cms/tinymce/tinymce.min.js');
    assertTrue(str_contains($tinyMce, 'majorVersion:"8",minorVersion:"6.0"'), 'TinyMCE is not the audited 8.6.0 release');
    $defaultConfig = (string) file_get_contents(__DIR__ . '/../cms/config.php');
    assertTrue(!str_contains($defaultConfig, 'change-this-password'), 'Known default password remains in configuration');

    // Authentication must reject plaintext credentials and default missing roles to editor.
    $authentication = new NeoCMS\Authentication(['legacy' => 'plaintext'], ['legacy' => 'administrator']);
    assertTrue(!$authentication->login('legacy', 'plaintext'), 'Plaintext credentials were accepted');
    $limitedHash = password_hash('secret', PASSWORD_DEFAULT);
    $authentication = new NeoCMS\Authentication(['limited' => $limitedHash], ['limited' => 'administrator']);
    assertTrue($authentication->login('limited', 'secret'), 'A valid password hash was rejected');
    $demoted = new NeoCMS\Authentication(['limited' => $limitedHash], ['limited' => 'editor']);
    assertTrue($demoted->getRole() === 'editor', 'Role demotion did not affect the active session');
    $rotated = new NeoCMS\Authentication(['limited' => password_hash('new-secret', PASSWORD_DEFAULT)], ['limited' => 'editor']);
    assertTrue(!$rotated->isLoggedIn(), 'Password rotation did not revoke the active session');

    $authentication = new NeoCMS\Authentication(['limited' => $limitedHash]);
    assertTrue($authentication->login('limited', 'secret'), 'A valid hash could not establish a second session');
    assertTrue($authentication->getRole() === 'editor', 'Missing role assignment did not fail to editor');

    // Idle sessions must lose authenticated state before another privileged request can use them.
    $_SESSION = [
        'loggedIn' => true,
        'loggedInUser' => 'tester',
        'role' => 'administrator',
        'csrfToken' => 'test-token',
        'authenticatedAt' => time() - 1000,
        'lastActivityAt' => time() - 1000,
        'lastRegeneratedAt' => time() - 1000,
    ];
    $expired = new NeoCMS\Authentication([], [], ['idleTimeout' => 300, 'absoluteTimeout' => 600]);
    assertTrue(!$expired->isLoggedIn(), 'Expired session remained authenticated');

    // Restore the administrator session used by controller integration tests.
    $_SESSION = [
        'loggedIn' => true,
        'loggedInUser' => 'tester',
        'role' => 'administrator',
        'csrfToken' => 'test-token',
        'authenticatedAt' => time(),
        'lastActivityAt' => time(),
        'lastRegeneratedAt' => time(),
    ];

    // Repeated denied logins must trigger a bounded lockout and be clearable after success.
    $limiter = new NeoCMS\LoginRateLimiter($data . '/rate-limit', [
        'loginMaxAttempts' => 2,
        'loginMaxAddressAttempts' => 10,
        'loginLockoutSeconds' => 60,
    ]);
    $limiter->recordFailure('192.0.2.10', 'tester');
    $limiter->recordFailure('192.0.2.10', 'tester');
    assertTrue($limiter->retryAfter('192.0.2.10', 'tester') > 0, 'Login throttling did not activate');
    $limiter->clear('192.0.2.10', 'tester');
    assertTrue($limiter->retryAfter('192.0.2.10', 'tester') === 0, 'Login throttling did not clear');

    // Page discovery must honour editableClass and ignore the default marker when overridden.
    $pages = $request('getPages');
    assertTrue(count($pages) === 1 && $pages[0]['url'] === '/index.html', 'Configured editable class was not respected');

    // Recursive discovery and new-page creation must not follow symlinks outside the document root.
    file_put_contents($outside . '/secret.html', '<main class="cms-content">Outside</main>');
    if (function_exists('symlink') && @symlink($outside . '/secret.html', $root . '/leak.html')) {
        assertTrue(count($request('getPages')) === 1, 'Page discovery followed an external file symlink');
    }
    if (function_exists('symlink') && @symlink($outside, $root . '/linked')) {
        assertRejected(
            fn() => $request('newPage', ['template' => 'example-template.html', 'filename' => '/linked/escaped.html'], 'POST'),
            'New-page creation followed an external directory symlink'
        );
    }

    // Drafts must remain private and leave the public HTML untouched.
    $draftContent = str_replace('Original', 'Draft', $page);
    $request('saveDraft', ['uri' => '/index.html', 'content' => $draftContent], 'POST');
    $draft = $request('getDraft', ['uri' => '/index.html']);
    assertTrue($draft['exists'] && str_contains($draft['content'], 'Draft'), 'Draft was not saved');
    assertTrue(str_contains((string) file_get_contents($root . '/index.html'), 'Original'), 'Draft changed the public page');

    // Publication must replace public HTML, clear its draft, and retain a revision.
    $published = str_replace('Original', 'Published', $page);
    $request('save', ['uri' => '/index.html', 'content' => $published], 'POST');
    assertTrue(str_contains((string) file_get_contents($root . '/index.html'), 'Published'), 'Page was not published');
    assertTrue(!$request('getDraft', ['uri' => '/index.html'])['exists'], 'Published draft was not cleared');
    $revisions = $request('revisions', ['uri' => '/index.html']);
    assertTrue(count($revisions) >= 1, 'Publishing did not create a revision');
    assertRejected(
        fn() => $request('saveDraft', ['uri' => '/index.html', 'content' => str_repeat('x', 5000)], 'POST'),
        'Oversized page content was accepted'
    );

    // Administrator page operations must support duplicate, rename, delete, and restoration.
    $request('page', ['operation' => 'duplicate', 'uri' => '/index.html', 'target' => '/copy.html'], 'POST');
    $request('page', ['operation' => 'rename', 'uri' => '/copy.html', 'target' => '/renamed.html'], 'POST');
    assertTrue(is_file($root . '/renamed.html'), 'Duplicate or rename failed');
    $request('page', ['operation' => 'delete', 'uri' => '/renamed.html'], 'POST');
    assertTrue(!is_file($root . '/renamed.html'), 'Page delete failed');
    $deletedRevisions = $request('revisions', ['uri' => '/renamed.html']);
    $request('restoreRevision', ['id' => $deletedRevisions[0]['id']], 'POST');
    assertTrue(is_file($root . '/renamed.html'), 'Deleted page revision was not restored');

    // Shared content must propagate ordinary forgiving HTML fragments to marked pages.
    $request('saveShared', ['key' => 'footer', 'content' => '<strong>Shared<br>footer</strong>'], 'POST');
    assertTrue(str_contains((string) file_get_contents($root . '/index.html'), '<strong>Shared<br>footer</strong>'), 'Shared HTML was not propagated');

    // Nested menus must render and replace existing generated navigation regions.
    $items = json_encode([
        ['label' => 'Home', 'url' => '/', 'parent' => ''],
        ['label' => 'Team', 'url' => '/team.html', 'parent' => 'Home'],
    ]);
    $request('saveMenu', ['name' => 'main', 'items' => $items], 'POST');
    $menuPage = (string) file_get_contents($root . '/index.html');
    assertTrue(str_contains($menuPage, 'href="/team.html"') && str_contains($menuPage, 'data-neo-menu="main"'), 'Menu was not propagated');

    // Future schedules must appear on the dashboard and be cancellable.
    $schedule = $request('schedule', [
        'uri' => '/index.html',
        'content' => str_replace('Published', 'Scheduled', $published),
        'publish_at' => date(DATE_ATOM, time() + 3600),
    ], 'POST');
    $dashboard = $request('dashboard');
    assertTrue(isset($dashboard['schedules'][$schedule['id']]), 'Schedule was not visible on the dashboard');
    $request('cancelSchedule', ['id' => $schedule['id']], 'POST');
    assertTrue(!isset($request('dashboard')['schedules'][$schedule['id']]), 'Schedule was not cancelled');
    assertRejected(
        fn() => $request('schedule', ['uri' => '/missing.html', 'content' => $published, 'publish_at' => date(DATE_ATOM, time() + 3600)], 'POST'),
        'A schedule targeting a missing page was accepted'
    );
    assertRejected(
        fn() => $request('deleteMedia', ['name' => 'index.php'], 'POST'),
        'Media deletion accepted a non-managed filename'
    );

    // Force a queued job into the past to verify the unattended publishing worker path.
    $due = $request('schedule', [
        'uri' => '/index.html',
        'content' => str_replace('Published', 'Scheduled', $published),
        'publish_at' => date(DATE_ATOM, time() + 3600),
    ], 'POST');
    $schedulePath = $data . '/schedules.json';
    $scheduleData = json_decode((string) file_get_contents($schedulePath), true);
    $scheduleData[$due['id']]['publish_at'] = date(DATE_ATOM, time() - 60);
    file_put_contents($schedulePath, json_encode($scheduleData, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n");
    $publishedCount = (new NeoCMS\CMSController($config))->publishScheduled();
    assertTrue($publishedCount === 1, 'Due schedule was not processed');
    assertTrue(str_contains((string) file_get_contents($root . '/index.html'), 'Scheduled'), 'Scheduled content was not published');

    // A page removed before its due time must mark only that job failed, not block the whole API.
    $failed = $request('schedule', [
        'uri' => '/renamed.html',
        'content' => $published,
        'publish_at' => date(DATE_ATOM, time() + 3600),
    ], 'POST');
    unlink($root . '/renamed.html');
    $scheduleData = json_decode((string) file_get_contents($schedulePath), true);
    $scheduleData[$failed['id']]['publish_at'] = date(DATE_ATOM, time() - 60);
    file_put_contents($schedulePath, json_encode($scheduleData, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n");
    assertTrue((new NeoCMS\CMSController($config))->publishScheduled() === 0, 'Invalid scheduled target was reported as published');
    $dashboardAfterFailure = $request('dashboard');
    assertTrue(($dashboardAfterFailure['schedules'][$failed['id']]['status'] ?? '') === 'failed', 'Failed schedule was not isolated');

    // Revision retention limits must cap both per-page and total private storage metadata.
    $revisionIndex = json_decode((string) file_get_contents($data . '/revisions.json'), true);
    assertTrue(is_array($revisionIndex) && count($revisionIndex) <= 4, 'Global revision retention limit was exceeded');
    $indexRevisionCount = count(array_filter($revisionIndex, static fn(array $revision): bool => ($revision['uri'] ?? '') === '/index.html'));
    assertTrue($indexRevisionCount <= 2, 'Per-page revision retention limit was exceeded');

    // Private CMS state should not be readable by unrelated operating-system users.
    assertTrue((fileperms($data) & 0777) === 0700, 'CMS data directory permissions are too broad');
    assertTrue((fileperms($schedulePath) & 0777) === 0600, 'CMS metadata file permissions are too broad');

    echo "All NeoCMS integration tests passed.\n";
} finally {
    // Cleanup runs after both success and failure, leaving /tmp as tidy as we found it.
    removeTree($root);
    removeTree($data);
    removeTree($outside);
}
