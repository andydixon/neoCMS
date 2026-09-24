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
    assertTrue(in_array('/renamed.html', array_column($request('dashboard')['deleted'], 'uri'), true), 'Deleted page was not listed on the dashboard');
    $deletedRevisions = $request('revisions', ['uri' => '/renamed.html']);
    $request('restoreRevision', ['id' => $deletedRevisions[0]['id']], 'POST');
    assertTrue(is_file($root . '/renamed.html'), 'Deleted page revision was not restored');
    assertTrue(!in_array('/renamed.html', array_column($request('dashboard')['deleted'], 'uri'), true), 'Recovered page is still listed as deleted');

    // Shared content must propagate ordinary forgiving HTML fragments to marked pages.
    $request('saveShared', ['key' => 'footer', 'content' => '<strong>Shared<br>footer</strong>'], 'POST');
    assertTrue(str_contains((string) file_get_contents($root . '/index.html'), '<strong>Shared<br>footer</strong>'), 'Shared HTML was not propagated');

    // Non-ASCII text must survive propagation unchanged (libxml assumes Latin-1 without a hint).
    $request('saveShared', ['key' => 'footer', 'content' => '<em>Café – 日本語</em>'], 'POST');
    // libxml may write entities (&eacute;); they must decode back to the original text, not to Latin-1 mojibake.
    assertTrue(str_contains(html_entity_decode((string) file_get_contents($root . '/index.html'), ENT_QUOTES, 'UTF-8'), '<em>Café – 日本語</em>'), 'Non-ASCII shared content was mangled');

    // Nested menus must render and replace existing generated navigation regions.
    $items = json_encode([
        ['label' => 'Home', 'url' => '/', 'parent' => ''],
        ['label' => 'Team', 'url' => '/team.html', 'parent' => 'Home'],
    ]);
    (new NeoCMS\FileStore($data))->write('menus', ['main' => ['items' => [], 'updated' => date(DATE_ATOM)]]);
    assertRejected(fn() => $request('saveMenu', ['name' => 'brand-new', 'items' => $items], 'POST'), 'The CMS created a menu that does not exist');
    $request('saveMenu', ['name' => 'main', 'title' => 'Main navigation', 'items' => $items], 'POST');
    assertTrue(($request('menus')['main']['title'] ?? '') === 'Main navigation' && !isset($request('menus')['brand-new']), 'Menu was not renamed in place');
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
    $afterCancel = $request('dashboard');
    assertTrue(!isset($afterCancel['schedules'][$schedule['id']]), 'Schedule was not cancelled');
    assertTrue(in_array('Cancelled scheduled publication', array_column($afterCancel['activity'], 'action'), true), 'Cancelling a schedule was not recorded in the activity log');
    $request('cancelSchedule', ['id' => $schedule['id']], 'POST'); // cancelling twice must not log a second event
    assertTrue(count(array_filter($request('dashboard')['activity'], fn($e) => $e['action'] === 'Cancelled scheduled publication')) === 1, 'A schedule that no longer exists was logged as cancelled');

    // Alt-text edits and uploads are recorded too.
    $request('updateMedia', ['name' => str_repeat('a', 32) . '.png', 'alt' => 'A test image'], 'POST');
    assertTrue(in_array('Updated image alt text', array_column($request('dashboard')['activity'], 'action'), true), 'Alt text change was not recorded');
    $uploadActivityDir = sys_get_temp_dir() . '/neocms-activity-' . bin2hex(random_bytes(4));
    (new NeoCMS\Activity(new NeoCMS\FileStore($uploadActivityDir), new NeoCMS\Logger(false, [])))->record('tester', 'Uploaded image', 'x.png (5 KB)');
    $recorded = (new NeoCMS\FileStore($uploadActivityDir))->read('activity');
    assertTrue(count($recorded) === 1 && $recorded[0]['action'] === 'Uploaded image' && $recorded[0]['user'] === 'tester', 'Upload activity entry was not written');
    removeTree($uploadActivityDir);
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
    assertTrue(in_array('Scheduled publication failed', array_column($dashboardAfterFailure['activity'], 'action'), true), 'A failed scheduled publication was not shown in the activity feed');

    // Revision retention limits must cap both per-page and total private storage metadata.
    $revisionIndex = json_decode((string) file_get_contents($data . '/revisions.json'), true);
    assertTrue(is_array($revisionIndex) && count($revisionIndex) <= 4, 'Global revision retention limit was exceeded');
    $indexRevisionCount = count(array_filter($revisionIndex, static fn(array $revision): bool => ($revision['uri'] ?? '') === '/index.html'));
    assertTrue($indexRevisionCount <= 2, 'Per-page revision retention limit was exceeded');

    // Private CMS state should not be readable by unrelated operating-system users.
    // ---- Site scan: byte-exact tagging of content containers, images, and SEO tags ----
    $all = ['content' => true, 'images' => true, 'seo' => true];
    $scanPage = "<!DOCTYPE html>\r\n<html><head>\r\n  <meta charset=\"utf-8\">\r\n</head>\r\n<body>\r\n"
        . "<header class='top'><nav><a href=\"/\">Home</a></nav></header>\r\n"
        . "<script>var s = '<main>';</script>\r\n"
        . "<main class=\"wide\">\r\n<h1>Café – Über uns</h1>\r\n<p>Wir sind ein kleines Team mit grossen Ideen.</p>\r\n<img src=\"/photo.jpg\" alt=\"\">\r\n</main>\r\n"
        . "<footer><p>Footer text that is long enough to count as text.</p></footer>\r\n"
        . "<img src=\"/logo.png\">\r\n<img src=\"x.gif\" width=\"1\" height=\"1\">\r\n</body></html>";
    $tagged = NeoCMS\SiteAnalyser::apply($scanPage, 'cms-content', $all);
    assertTrue($tagged !== null, 'Site scan found nothing to tag');
    $out = $tagged['html'];
    assertTrue(str_contains($out, '<main class="wide cms-content">'), 'Existing class attribute was not merged');
    assertTrue(!str_contains($out, "<header class='top cms-content'"), 'Header was tagged as content');
    assertTrue(str_contains($out, "var s = '<main>';"), 'Script content was altered');
    assertTrue(substr_count($out, 'data-neo-image') === 1 && str_contains($out, '<img data-neo-image src="/logo.png">'), 'Only the image outside the region should be tagged');
    assertTrue(str_contains($out, '<title>Café – Über uns</title>') && str_contains($out, 'name="description"'), 'Missing SEO tags were not inserted');
    assertTrue(substr_count($out, "\n") === substr_count($out, "\r\n"), 'Line endings became mixed');
    assertTrue(NeoCMS\SiteAnalyser::apply($out, 'cms-content', $all) === null, 'Second site scan was not idempotent');
    // Removing exactly what was inserted must give back the original bytes.
    $restored = str_replace([' cms-content"', ' data-neo-image'], ['"', ''], $out);
    $restored = preg_replace('#^ {4}<(title|meta (name="description"|property="og:[a-z]+")).*\r\n#m', '', $restored);
    assertTrue($restored === $scanPage, 'Site scan changed bytes other than the inserted markers');
    // Existing SEO tags are never overwritten; a page with no safe container is reported, not guessed.
    $seoPage = '<html><head><title>Mine</title><meta name="description" content="Keep me"></head><body><main><h1>H</h1></main></body></html>';
    $seoOut = NeoCMS\SiteAnalyser::apply($seoPage, 'cms-content', ['seo' => true]);
    assertTrue($seoOut === null || (str_contains($seoOut['html'], '<title>Mine</title>') && str_contains($seoOut['html'], 'content="Keep me"') && substr_count($seoOut['html'], 'name="description"') === 1), 'Existing SEO tags were overwritten');
    assertTrue(NeoCMS\SiteAnalyser::apply('<body><div>' . str_repeat('text ', 30) . '</div></body>', 'cms-content', ['content' => true]) === null, 'A page without a safe container was tagged');

    // Photo placeholders (role="img" boxes) outside a region are marked; icons, real images, and in-region boxes are not.
    $placeholderPage = '<body><div class="portrait" role="img" aria-label="Photo placeholder"><svg role="img"></svg><span>Your photo here</span></div>'
        . '<div role="img" aria-label="has one"><img src="/a.jpg" alt=""></div><svg role="img"></svg><span role="img" aria-hidden="true"></span>'
        . '<main><div role="img" aria-label="inside"></div></main></body>';
    $placeholderOut = NeoCMS\SiteAnalyser::apply($placeholderPage, 'cms-content', ['images' => true]);
    assertTrue($placeholderOut !== null && substr_count($placeholderOut['html'], 'data-neo-image') === 3, 'Images-only pass should mark the portrait, the real image, and the placeholder in <main>');
    assertTrue(str_contains($placeholderOut['html'], '<div data-neo-image class="portrait" role="img"'), 'Photo placeholder was not marked');
    assertTrue(str_contains($placeholderOut['html'], '<img data-neo-image src="/a.jpg"'), 'Real image was not marked');
    $regionOut = NeoCMS\SiteAnalyser::apply($placeholderPage, 'cms-content', ['content' => true, 'images' => true]);
    assertTrue(substr_count($regionOut['html'], 'data-neo-image') === 2 && !str_contains($regionOut['html'], '<div data-neo-image role="img" aria-label="inside"'), 'A placeholder inside a newly editable region was marked');

    // Controller: analyse reports pages; apply writes them, keeps a revision, and needs the manage capability.
    file_put_contents($root . '/scan-a.html', $scanPage);
    file_put_contents($root . '/scan-b.html', $scanPage);
    $listing = $request('listSitePages');
    assertTrue(in_array('/scan-a.html', $listing['pages'], true) && in_array('/scan-b.html', $listing['pages'], true), 'Page listing missed the sample pages');
    $analysis = $request('analyseSitePages', ['uris' => json_encode(['/scan-a.html', '/scan-b.html', '/no-such-page.html'])]);
    $byUri = array_column($analysis['pages'], null, 'uri');
    assertTrue(count($byUri) === 2, 'A page that does not exist should be skipped, not fail the batch');
    assertTrue(isset($byUri['/scan-a.html']) && $byUri['/scan-a.html']['proposed'] === ['main'], 'Analysis did not propose the main region');
    assertTrue(in_array('/logo.png', $byUri['/scan-a.html']['broken'], true), 'Missing local image was not reported as broken');
    assertRejected(fn() => $request('analyseSitePages', ['uris' => json_encode(array_map(fn($i) => "/p{$i}.html", range(1, 101)))]), 'An oversized batch was accepted');
    $asRole = function (string $role, string $action, array $parameters) use ($config): array {
        $cfg = $config;
        $cfg['roles']['tester'] = $role;
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_POST = array_merge(['action' => $action, 'csrf_token' => 'test-token'], $parameters);
        $_GET = [];
        $_REQUEST = $_POST;
        ob_start();
        (new NeoCMS\CMSController($cfg))->handleRequest();
        return json_decode((string) ob_get_clean(), true) ?? [];
    };
    foreach (['listSitePages' => [], 'analyseSitePages' => ['uris' => json_encode(['/scan-a.html'])]] as $readAction => $readParams) {
        assertTrue(isset($asRole('publisher', $readAction, $readParams)['error']), "A non-administrator could run {$readAction}");
    }
    $before = file_get_contents($root . '/scan-b.html');
    $denied = $asRole('publisher', 'applySiteTagging', ['uris' => json_encode(['/scan-b.html']), 'options' => json_encode($all)]);
    assertTrue(isset($denied['error']) && file_get_contents($root . '/scan-b.html') === $before, 'A non-administrator could auto-tag pages');
    $traversal = $asRole('administrator', 'applySiteTagging', ['uris' => json_encode(['/scan-a.html', '../etc/passwd']), 'options' => json_encode($all)]);
    assertTrue(isset($traversal['error']) && !str_contains((string) file_get_contents($root . '/scan-a.html'), 'cms-content'), 'A traversal URI was not rejected before any page changed');
    $result = $request('applySiteTagging', ['uris' => json_encode(['/scan-a.html']), 'options' => json_encode($all)], 'POST');
    assertTrue($result['updated_pages'] === 1, 'Only the selected page should have been updated');
    assertTrue(str_contains((string) file_get_contents($root . '/scan-a.html'), 'cms-content') && file_get_contents($root . '/scan-b.html') === $before, 'Unselected page changed or selected page was not tagged');
    $revisions = $request('revisions', ['uri' => '/scan-a.html']);
    assertTrue(count($revisions) >= 1 && $revisions[0]['reason'] === 'Before auto-tagging', 'No revision was kept before auto-tagging: ' . json_encode($revisions));
    unlink($root . '/scan-a.html');
    unlink($root . '/scan-b.html');

    // Navigation detection seeds the menus store, tags the nav in place, and later saves keep its attributes.
    $navPage = fn(string $cur) => '<!doctype html><html><head><title>T</title></head><body><header><nav class="site-nav" aria-label="Main"><ul><li><a href="a.html"' . ($cur === 'a' ? ' aria-current="page"' : '') . '>A</a></li><li><a href="b.html"' . ($cur === 'b' ? ' aria-current="page"' : '') . '>B</a></li></ul></nav></header><main><p>' . str_repeat('word ', 20) . '</p></main></body></html>';
    file_put_contents($root . '/nav-a.html', $navPage('a'));
    file_put_contents($root . '/nav-b.html', $navPage('b'));
    $navUris = json_encode(['/nav-a.html', '/nav-b.html']);
    $seen = $request('analyseSitePages', ['uris' => $navUris])['pages'];
    assertTrue($seen[0]['navs'][0]['menu'] === 'primary' && $seen[0]['navs'][0]['links'] === 2, 'Primary navigation was not detected');
    $request('applySiteTagging', ['uris' => $navUris, 'options' => json_encode(['menus' => true])], 'POST');
    $menus = $request('menus');
    assertTrue(isset($menus['primary']) && count($menus['primary']['items']) === 2 && $menus['primary']['items'][0]['url'] === '/a.html', 'Detected menu was not seeded: ' . json_encode($menus));
    assertTrue(str_contains((string) file_get_contents($root . '/nav-b.html'), '<nav data-neo-menu="primary" class="site-nav" aria-label="Main">'), 'Nav was not marked in place');
    assertTrue(!str_contains(json_encode(array_column($request('analyseSitePages', ['uris' => $navUris])['pages'], 'changes')), 'navigation'), 'Menu detection is not settled on the second run');
    $request('saveMenu', ['name' => 'primary', 'items' => json_encode([['label' => 'A', 'url' => '/a.html'], ['label' => 'B', 'url' => '/b.html'], ['label' => 'C', 'url' => '/c.html']])], 'POST');
    $navB = (string) file_get_contents($root . '/nav-b.html');
    assertTrue(str_contains($navB, 'class="site-nav" aria-label="Main"') && str_contains($navB, '<a href="/c.html">C</a>') && substr_count($navB, 'aria-current') === 0, 'Saved menu lost the nav attributes or wrongly marked the current page');
    $request('deleteMenu', ['name' => 'primary'], 'POST');
    assertTrue(!isset($request('menus')['primary']) && str_contains((string) file_get_contents($root . '/nav-b.html'), '/c.html'), 'Menu was not deleted, or pages lost their navigation');
    assertTrue(isset($asRole('publisher', 'deleteMenu', ['name' => 'secondary'])['error']), 'A non-administrator could delete a menu');
    // Scan-for-navigation mode also exposes navs that are not header/footer menus.
    file_put_contents($root . '/nav-c.html', '<html><head><title>T</title></head><body><nav aria-label="Side bar"><ul><li><a href="/x.html">X</a></li></ul></nav><p>hi</p></body></html>');
    $plain = $request('analyseSitePages', ['uris' => json_encode(['/nav-c.html'])])['pages'][0];
    $wide = $request('analyseSitePages', ['uris' => json_encode(['/nav-c.html']), 'allNavs' => 1])['pages'][0];
    assertTrue($plain['navs'] === [] && $wide['navs'][0]['menu'] === 'side-bar' && !$wide['navs'][0]['tagged'], 'allNavs did not expose an unclassified nav');
    $request('applySiteTagging', ['uris' => json_encode(['/nav-c.html']), 'options' => json_encode(['menus' => true, 'allNavs' => true])], 'POST');
    assertTrue(isset($request('menus')['side-bar']) && str_contains((string) file_get_contents($root . '/nav-c.html'), 'data-neo-menu="side-bar"'), 'Unclassified nav was not exposed as a menu');
    unlink($root . '/nav-c.html');
    unlink($root . '/nav-a.html');
    unlink($root . '/nav-b.html');

    // A new page stays private (no file, not in its menu) until it is first published.
    $mainBefore = count($request('menus')['main']['items']);
    $created = $request('newPage', ['template' => 'example-template.html', 'name' => 'About Us!', 'menu' => 'main'], 'POST');
    assertTrue($created['url'] === '/about-us.html' && !file_exists($root . '/about-us.html'), 'New page was made public before publication');
    assertTrue($request('newPage', ['template' => 'example-template.html', 'name' => 'About us', 'menu' => ''], 'POST')['url'] === '/about-us-2.html', 'Duplicate name did not get a numbered filename');
    assertTrue($request('newPage', ['template' => 'example-template.html', 'name' => '???', 'menu' => ''], 'POST')['url'] === '/page.html', 'Symbol-only name did not fall back to page.html');
    assertRejected(fn() => $request('newPage', ['template' => 'example-template.html', 'name' => 'X', 'menu' => 'nope'], 'POST'), 'Unknown navigation group was accepted');
    $listed = array_values(array_filter($request('getPages'), fn($p) => $p['url'] === '/about-us.html'));
    assertTrue(count($listed) === 1 && !empty($listed[0]['pending']), 'Pending page was not listed as unpublished');
    $newDraft = $request('getDraft', ['uri' => '/about-us.html']);
    assertTrue($newDraft['exists'] && str_contains($newDraft['content'], '<title>About Us!</title>'), 'New page draft is missing or untitled');
    assertTrue(count($request('menus')['main']['items']) === $mainBefore && !str_contains((string) file_get_contents($root . '/index.html'), 'about-us.html'), 'Link appeared in the menu before publication');
    $request('save', ['uri' => '/about-us.html', 'content' => $newDraft['content']], 'POST');
    assertTrue(file_exists($root . '/about-us.html'), 'First publication did not create the page');
    assertTrue(in_array('/about-us.html', array_column($request('menus')['main']['items'], 'url'), true) && str_contains((string) file_get_contents($root . '/index.html'), 'href="/about-us.html"'), 'Published page was not added to its menu');
    $published = array_values(array_filter($request('getPages'), fn($p) => $p['url'] === '/about-us.html'));
    assertTrue(!array_filter($published, fn($p) => !empty($p['pending'])), 'Published page is still marked unpublished');
    $request('schedule', ['uri' => '/about-us-2.html', 'content' => $newDraft['content'], 'publish_at' => (new DateTimeImmutable('+1 day'))->format(DATE_ATOM)], 'POST');
    $request('discardNewPage', ['uri' => '/about-us-2.html'], 'POST');
    $request('discardNewPage', ['uri' => '/page.html'], 'POST');
    assertTrue(!file_exists($root . '/about-us-2.html') && !in_array('/about-us-2.html', array_column($request('dashboard')['schedules'], 'uri'), true) && !array_filter($request('getPages'), fn($p) => !empty($p['pending'])), 'Discard left a schedule or pending page behind');
    unlink($root . '/about-us.html');

    // A page marked as a template is offered by New Page and copied (title replaced) into a private draft.
    assertRejected(fn() => $request('newPage', ['template' => 'page:/index.html', 'name' => 'Nope', 'menu' => ''], 'POST'), 'An unmarked page was usable as a template');
    $request('setPageTemplate', ['uri' => '/index.html', 'value' => '1'], 'POST');
    assertTrue(in_array('page:/index.html', array_column($request('getTemplates'), 'id'), true), 'Marked page is not offered as a template');
    $fromPage = $request('newPage', ['template' => 'page:/index.html', 'name' => 'From Page', 'menu' => ''], 'POST');
    $copied = $request('getDraft', ['uri' => $fromPage['url']])['content'];
    assertTrue(str_contains($copied, 'cms-content') && str_contains($copied, '<title>From Page</title>') && !file_exists($root . $fromPage['url']), 'Page template was not copied into a private draft');
    $request('discardNewPage', ['uri' => $fromPage['url']], 'POST');
    $request('setPageTemplate', ['uri' => '/index.html', 'value' => '0'], 'POST');
    assertTrue(!in_array('page:/index.html', array_column($request('getTemplates'), 'id'), true), 'Unmarked page is still offered as a template');
    $request('setPageTemplate', ['uri' => '/index.html', 'value' => '1'], 'POST');
    $request('deleteTemplate', ['template' => 'page:/index.html'], 'POST');
    assertTrue(!in_array('page:/index.html', array_column($request('getTemplates'), 'id'), true) && file_exists($root . '/index.html'), 'Deleting a page template removed the page or left it listed');
    assertRejected(fn() => $request('deleteTemplate', ['template' => '../config.php'], 'POST'), 'A non-template file could be deleted');
    assertRejected(fn() => $request('deleteTemplate', ['template' => 'missing.html'], 'POST'), 'A missing template file was reported deleted');

    // Parallel read-modify-write must not lose updates: 6 processes x 20 increments = 120.
    $counterDir = sys_get_temp_dir() . '/neocms-counter-' . bin2hex(random_bytes(4));
    $worker = $counterDir . '.php';
    file_put_contents($worker, '<?php require ' . var_export(__DIR__ . '/../cms/src/NeoCMS/FileStore.php', true)
        . '; $s = new NeoCMS\FileStore($argv[1]); for ($i = 0; $i < 20; $i++) { $s->update("counter", fn($d) => ["n" => ($d["n"] ?? 0) + 1]); }');
    $procs = [];
    for ($i = 0; $i < 6; $i++) {
        $procs[] = proc_open([PHP_BINARY, $worker, $counterDir], [], $pipes);
    }
    foreach ($procs as $proc) {
        proc_close($proc);
    }
    $counted = (new NeoCMS\FileStore($counterDir))->read('counter')['n'] ?? 0;
    unlink($worker);
    removeTree($counterDir);
    assertTrue($counted === 120, "FileStore::update lost updates under concurrency ({$counted}/120)");

    // ---- Users: roles, self-service, administrator management, invitations, and account protection ----
    $as = function (string $user, string $action, array $params = [], string $method = 'POST') use ($config): array {
        $saved = $_SESSION;
        $_SESSION = ['loggedIn' => true, 'loggedInUser' => $user, 'csrfToken' => 'test-token', 'authenticatedAt' => time(), 'lastActivityAt' => time(), 'lastRegeneratedAt' => time()];
        $_SERVER['REQUEST_METHOD'] = $method;
        $_POST = $method === 'POST' ? array_merge(['action' => $action, 'csrf_token' => 'test-token'], $params) : [];
        $_GET = $method === 'GET' ? array_merge(['action' => $action], $params) : [];
        $_REQUEST = array_merge($_GET, $_POST);
        ob_start();
        (new NeoCMS\CMSController($config))->handleRequest();
        $body = (string) ob_get_clean();
        $_SESSION = $saved;
        return (json_decode($body, true) ?? []) + ['_raw' => $body];
    };
    $ok = fn(array $r, string $why) => assertTrue(!isset($r['error']), $why . ' (' . ($r['error'] ?? '') . ')');
    $err = fn(array $r, string $why) => assertTrue(isset($r['error']), $why);
    $store = new NeoCMS\UserStore(new NeoCMS\FileStore($data), $config);
    $pw = 'unused';

    // Two roles: the old publisher role is an editor (can publish, cannot manage).
    $alias = new NeoCMS\Authentication(['x' => $limitedHash], ['x' => 'publisher']);
    $alias->login('x', 'secret');
    assertTrue($alias->getRole() === 'editor' && $alias->can('publish') && $alias->can('schedule') && !$alias->can('manage'), 'Publisher did not map to a publishing editor');

    $list = $as('tester', 'users', [], 'GET');
    $ok($list, 'users list failed');
    assertTrue($list['me']['managed'] === true && $list['me']['role'] === 'administrator' && array_keys($list['roles']) === ['editor', 'administrator'], 'Users response is missing the config account or roles');
    assertTrue(!str_contains($list['_raw'], 'hash') && !str_contains($list['_raw'], '$2y$'), 'A password hash leaked into the users response');

    // Sensitive admin actions need the administrator's own password.
    $newUser = ['name' => 'Ed Example', 'email' => 'Ed@Example.com', 'role' => 'editor', 'username' => 'ed1', 'password' => 'correct horse 1'];
    $err($as('tester', 'saveUser', $newUser), 'Adding a user worked without password confirmation');
    $err($as('tester', 'saveUser', $newUser + ['confirm_password' => 'wrong']), 'Adding a user worked with a wrong confirmation password');
    $ok($as('tester', 'saveUser', $newUser + ['confirm_password' => $pw]), 'Adding a user failed');
    $accounts = array_column($as('tester', 'users', [], 'GET')['users'], null, 'username');
    assertTrue(($accounts['ed1']['email'] ?? '') === 'ed@example.com' && $accounts['ed1']['status'] === 'active' && !isset($accounts['ed1']['hash']), 'New account was not listed correctly');
    assertTrue(str_starts_with((string) json_decode((string) file_get_contents($data . '/users.json'), true)['users']['ed1']['hash'], '$2y$'), 'Password was not stored as a hash');

    // Validation: duplicates, weak or over-long passwords, password equal to the username.
    $err($as('tester', 'saveUser', ['username' => 'ED1', 'name' => 'Dup', 'email' => 'other@example.com', 'role' => 'editor', 'password' => 'correct horse 2', 'confirm_password' => $pw]), 'A case-variant duplicate username was accepted');
    $err($as('tester', 'saveUser', ['username' => 'ed2', 'name' => 'Dup', 'email' => 'ED@example.com', 'role' => 'editor', 'password' => 'correct horse 2', 'confirm_password' => $pw]), 'A duplicate email was accepted');
    $err($as('tester', 'saveUser', ['username' => 'ed2', 'name' => 'Short', 'email' => '', 'role' => 'editor', 'password' => 'elevenchars', 'confirm_password' => $pw]), 'An 11-character password was accepted');
    $err($as('tester', 'saveUser', ['username' => 'ed2', 'name' => 'Long', 'email' => '', 'role' => 'editor', 'password' => str_repeat('a', 73), 'confirm_password' => $pw]), 'A 73-byte password was accepted');
    $err($as('tester', 'saveUser', ['username' => 'samepassword1', 'name' => 'Same', 'email' => '', 'role' => 'editor', 'password' => 'samepassword1', 'confirm_password' => $pw]), 'A password equal to the username was accepted');
    $err($as('tester', 'saveUser', ['username' => 'ed3', 'name' => 'Bad role', 'email' => '', 'role' => 'root', 'password' => 'correct horse 3', 'confirm_password' => $pw]), 'An unknown role was accepted');
    $err($as('tester', 'saveUser', ['username' => 'tester', 'name' => 'Shadow', 'email' => '', 'role' => 'editor', 'password' => 'correct horse 4', 'confirm_password' => $pw]), 'A CMS account could shadow a config username');

    // An editor can publish but cannot manage users, and sees only their own account.
    $perms = $as('ed1', 'dashboard', [], 'GET')['permissions'];
    assertTrue($perms['publish'] === true && $perms['schedule'] === true && $perms['manage'] === false, 'Editor permissions are wrong');
    $mine = $as('ed1', 'users', [], 'GET');
    assertTrue($mine['users'] === [] && $mine['me']['username'] === 'ed1' && $mine['me']['role'] === 'editor', 'An editor could list other accounts');
    foreach (['saveUser' => $newUser + ['username' => 'ed9', 'confirm_password' => 'correct horse 1'], 'inviteUser' => ['name' => 'X', 'email' => 'x@example.com', 'role' => 'editor', 'confirm_password' => 'correct horse 1'], 'blockUser' => ['username' => 'tester', 'blocked' => '1', 'confirm_password' => 'correct horse 1'], 'deleteUser' => ['username' => 'tester', 'confirm_password' => 'correct horse 1']] as $action => $params) {
        assertTrue(($as('ed1', $action, $params)['error'] ?? '') === 'Your role cannot perform this action', "An editor could run {$action}");
    }

    // Self-service: display name freely, email and password need the current password.
    $ok($as('ed1', 'saveProfile', ['name' => 'Edward Example', 'email' => 'ed@example.com']), 'Editor could not change their display name');
    $err($as('ed1', 'saveProfile', ['name' => 'Edward', 'email' => 'new@example.com']), 'Email changed without the current password');
    $ok($as('ed1', 'saveProfile', ['name' => 'Edward', 'email' => 'new@example.com', 'current_password' => 'correct horse 1']), 'Email change with the current password failed');
    $as('ed1', 'saveProfile', ['name' => 'Edward', 'email' => 'new@example.com', 'username' => 'tester']);
    assertTrue($store->profile('tester')['name'] === '' && $store->profile('ed1')['name'] === 'Edward', 'A posted username redirected a self-service profile edit');
    $err($as('ed1', 'changePassword', ['current_password' => 'correct horse 1', 'new_password' => 'brand new pass 2', 'confirm_password' => 'different pass 2']), 'Mismatched confirmation was accepted');
    $err($as('ed1', 'changePassword', ['current_password' => 'wrong', 'new_password' => 'brand new pass 2', 'confirm_password' => 'brand new pass 2']), 'Password changed with a wrong current password');
    $ok($as('ed1', 'changePassword', ['current_password' => 'correct horse 1', 'new_password' => 'brand new pass 2', 'confirm_password' => 'brand new pass 2']), 'Password change failed');
    [$credentials] = $store->forAuth();
    assertTrue(password_verify('brand new pass 2', $credentials['ed1']) && !password_verify('correct horse 1', $credentials['ed1']), 'New password did not replace the old one');

    // Config accounts are locked: profile overlay only, never credentials.
    $ok($as('tester', 'saveProfile', ['name' => 'Root Admin', 'email' => '']), 'Config admin could not set a display name');
    assertTrue($store->profile('tester')['name'] === 'Root Admin', 'Config account profile overlay was not stored');
    assertTrue(($as('tester', 'changePassword', ['current_password' => $pw, 'new_password' => 'brand new pass 2', 'confirm_password' => 'brand new pass 2'])['error'] ?? '') === 'This password is managed in config.local.php', 'A config account password was changed in the UI');
    $two = new NeoCMS\UserStore(new NeoCMS\FileStore($data . '-two'), ['authentication' => ['a' => $limitedHash, 'b' => $limitedHash], 'roles' => ['a' => 'administrator', 'b' => 'administrator']]);
    foreach ([fn() => $two->update('b', 'B', '', 'editor'), fn() => $two->setBlocked('b', true), fn() => $two->delete('b'), fn() => $two->create('B', 'B', '', 'editor', 'correct horse 5')] as $attempt) {
        assertRejected($attempt, 'A config account was modified through the store');
    }
    removeTree($data . '-two');

    // Administrators cannot lock themselves out.
    foreach (['blockUser' => ['username' => 'tester', 'blocked' => '1'], 'deleteUser' => ['username' => 'tester'], 'saveUser' => ['existing' => 'tester', 'name' => 'T', 'email' => '', 'role' => 'editor']] as $action => $params) {
        $err($as('tester', $action, $params + ['confirm_password' => $pw]), "An administrator could run {$action} on themselves");
    }

    // Edit, role change, block, unblock, delete; a blocked or deleted user loses access on the next request.
    $ok($as('tester', 'saveUser', ['existing' => 'ed1', 'name' => 'Edward', 'email' => 'new@example.com', 'role' => 'administrator', 'password' => '', 'confirm_password' => $pw]), 'Editing a user failed');
    assertTrue($as('ed1', 'dashboard', [], 'GET')['permissions']['manage'] === true, 'Role change did not apply to the next request');
    $ok($as('tester', 'saveUser', ['existing' => 'ed1', 'name' => 'Edward', 'email' => 'new@example.com', 'role' => 'editor', 'password' => '', 'confirm_password' => $pw]), 'Demoting a user failed');
    $ok($as('tester', 'blockUser', ['username' => 'ed1', 'blocked' => '1', 'confirm_password' => $pw]), 'Blocking failed');
    assertTrue(isset($as('ed1', 'dashboard', [], 'GET')['error']), 'A blocked user kept access');
    $ok($as('tester', 'blockUser', ['username' => 'ed1', 'blocked' => '0', 'confirm_password' => $pw]), 'Unblocking failed');
    assertTrue(isset($as('ed1', 'dashboard', [], 'GET')['permissions']), 'An unblocked user could not use the CMS');
    $ok($as('tester', 'deleteUser', ['username' => 'ed1', 'confirm_password' => $pw]), 'Deleting failed');
    assertTrue(isset($as('ed1', 'dashboard', [], 'GET')['error']), 'A deleted user kept access');

    // Invitations: single use, expiring, re-issuable, stored only as a hash.
    $invite = $as('tester', 'inviteUser', ['name' => 'New Person', 'email' => 'New.Person@example.com', 'role' => 'editor', 'confirm_password' => $pw]);
    $ok($invite, 'Inviting failed');
    preg_match('/invite=([a-f0-9]{64})$/', $invite['link'], $m);
    $token = $m[1] ?? '';
    assertTrue($token !== '' && $invite['username'] === 'new.person', 'Invite link or generated username is wrong');
    assertTrue(!str_contains((string) file_get_contents($data . '/users.json'), $token), 'The invite token was stored in plain text');
    $pending = array_column($as('tester', 'users', [], 'GET')['users'], null, 'username')['new.person'];
    assertTrue($pending['status'] === 'invited' && !str_contains(json_encode($pending), $token), 'Pending invitation is listed wrongly');
    assertTrue(!isset($store->forAuth()[0]['new.person']), 'An invited account could sign in before accepting');
    assertRejected(fn() => $store->acceptInvite(str_repeat('0', 64), 'correct horse 6'), 'A wrong invite token was accepted');
    assertRejected(fn() => $store->acceptInvite($token, 'short'), 'A weak password was accepted on an invite');
    $reissued = $as('tester', 'inviteUser', ['existing' => 'new.person', 'name' => 'New Person', 'email' => 'new.person@example.com', 'role' => 'editor', 'confirm_password' => $pw]);
    preg_match('/invite=([a-f0-9]{64})$/', $reissued['link'], $m2);
    assertRejected(fn() => $store->acceptInvite($token, 'correct horse 6'), 'A superseded invite token still worked');
    assertTrue($store->acceptInvite($m2[1], 'correct horse 6') === 'new.person', 'A valid invitation was not accepted');
    assertRejected(fn() => $store->acceptInvite($m2[1], 'correct horse 7'), 'An invite token could be used twice');
    assertTrue(password_verify('correct horse 6', $store->forAuth()[0]['new.person']), 'The invitee password was not set');
    $expired = $as('tester', 'inviteUser', ['name' => 'Late', 'email' => 'late@example.com', 'role' => 'editor', 'confirm_password' => $pw]);
    preg_match('/invite=([a-f0-9]{64})$/', $expired['link'], $m3);
    (new NeoCMS\FileStore($data))->update('users', function (array $d) { $d['users']['late']['invite']['expires'] = time() - 5; return $d; });
    assertRejected(fn() => $store->acceptInvite($m3[1], 'correct horse 8'), 'An expired invitation was accepted');
    foreach (['new.person', 'late'] as $cleanup) {
        $as('tester', 'deleteUser', ['username' => $cleanup, 'confirm_password' => $pw]);
    }

    // Wrong confirmation passwords are rate limited, and every account event is audited without secrets.
    for ($i = 0; $i < 6; $i++) {
        $locked = $as('tester', 'deleteUser', ['username' => 'nobody', 'confirm_password' => 'wrong-' . $i]);
    }
    assertTrue(str_contains($locked['error'] ?? '', 'Too many'), 'Repeated wrong confirmation passwords were not rate limited');
    @unlink($data . '/login-attempts.json');
    $activity = json_encode((new NeoCMS\FileStore($data))->read('activity'));
    foreach (['Added user', 'Blocked user', 'Deleted user', 'Invited user', 'Changed own password', 'Password confirmation failed', 'Changed user role'] as $event) {
        assertTrue(str_contains($activity, $event), "Account event not audited: {$event}");
    }
    assertTrue(!str_contains($activity, 'correct horse') && !str_contains($activity, $token), 'A secret reached the activity log');

    // Administrators are told when account data sits inside the web root.
    assertTrue($as('tester', 'dashboard', [], 'GET')['notices'] === [], 'A data directory outside the web root raised a recommendation');
    $insideConfig = $config;
    $insideConfig['dataDirectory'] = $root . '/zz-inside-data';
    mkdir($insideConfig['dataDirectory'], 0755, true);
    $insideConfig['siteRoot'] = $root;
    $_SESSION = ['loggedIn' => true, 'loggedInUser' => 'tester', 'csrfToken' => 'test-token', 'authenticatedAt' => time(), 'lastActivityAt' => time(), 'lastRegeneratedAt' => time()];
    $_SERVER['REQUEST_METHOD'] = 'GET';
    $_GET = ['action' => 'dashboard'];
    $_POST = [];
    $_REQUEST = $_GET;
    ob_start();
    (new NeoCMS\CMSController($insideConfig))->handleRequest();
    $insideDashboard = json_decode((string) ob_get_clean(), true);
    assertTrue(count($insideDashboard['notices'] ?? []) === 1 && str_contains($insideDashboard['notices'][0], 'outside the web root'), 'No recommendation for a data directory inside the web root');
    removeTree($insideConfig['dataDirectory']);

    // ---- Media: categories by extension, and refusal of executables and files that contain code ----
    $mediaDir = sys_get_temp_dir() . '/neocms-media-' . bin2hex(random_bytes(4));
    mkdir($mediaDir, 0755, true);
    $zip = function (array $entries): string {
        $out = '';
        $directory = '';
        foreach ($entries as $name => $data) {
            $offset = strlen($out);
            $crc = crc32($data);
            $out .= "PK\x03\x04" . pack('vvvvvVVVvv', 20, 0, 0, 0, 0, $crc, strlen($data), strlen($data), strlen($name), 0) . $name . $data;
            $directory .= "PK\x01\x02" . pack('vvvvvvVVVvvvvvVV', 20, 20, 0, 0, 0, 0, $crc, strlen($data), strlen($data), strlen($name), 0, 0, 0, 0, 0, $offset) . $name;
        }
        return $out . $directory . "PK\x05\x06" . pack('vvvvVVv', 0, 0, count($entries), count($entries), strlen($directory), strlen($out), 0);
    };
    $write = function (string $name, string $data) use ($mediaDir): string {
        $path = $mediaDir . '/' . bin2hex(random_bytes(3));
        file_put_contents($path, $data);
        return $path;
    };
    $img = imagecreatetruecolor(8, 8);
    ob_start(); imagepng($img); $png = (string) ob_get_clean();
    ob_start(); imagejpeg($img); $jpg = (string) ob_get_clean();
    ob_start(); imagegif($img); $gif = (string) ob_get_clean();
    $wav = 'RIFF' . pack('V', 36 + 8) . 'WAVEfmt ' . pack('VvvVVvv', 16, 1, 1, 8000, 8000, 1, 8) . 'data' . pack('V', 8) . str_repeat("\x80", 8);
    $mp4 = "\0\0\0\x18ftypisom\0\0\2\0isomiso2" . "\0\0\0\x10mdat" . str_repeat("\0", 8);
    $mp3 = "ID3\x03\0\0\0\0\0\0" . str_repeat("\xFF\xFB\x90\x00" . str_repeat("\0", 413), 4);
    $pdf = "%PDF-1.4\n1 0 obj\n<< /Type /Catalog >>\nendobj\ntrailer\n<< /Root 1 0 R >>\n%%EOF\n";
    $docx = $zip(['[Content_Types].xml' => '<Types/>', 'word/document.xml' => '<w:document/>']);
    $odt = $zip(['mimetype' => 'application/vnd.oasis.opendocument.text', 'content.xml' => '<office:document-content/>']);

    // Accepted, and categorised by extension.
    foreach ([
        ['photo.png', $png, 'imagery', 'png'], ['photo.JPG', $jpg, 'imagery', 'jpg'], ['photo.jpeg', $jpg, 'imagery', 'jpg'], ['anim.gif', $gif, 'imagery', 'gif'],
        ['guide.pdf', $pdf, 'documents', 'pdf'], ['letter.docx', $docx, 'documents', 'docx'], ['letter.odt', $odt, 'documents', 'odt'],
        ['notes.txt', "Plain notes\nline two\n", 'documents', 'txt'], ['table.csv', "a,b\n1,2\n", 'documents', 'csv'],
        ['clip.mp4', $mp4, 'video', 'mp4'], ['tone.wav', $wav, 'audio', 'wav'], ['song.mp3', $mp3, 'audio', 'mp3'],
    ] as [$name, $bytes, $category, $extension]) {
        $result = NeoCMS\MediaTypes::inspect($write($name, $bytes), $name);
        assertTrue($result['category'] === $category && $result['ext'] === $extension, "{$name} was not accepted as {$category}");
    }

    // Compressed media contains arbitrary bytes: short sequences such as <%= or <?= must not cause a false refusal.
    $noise = $mp4 . str_repeat('<%=<?=<%@', 50000) . random_bytes(200000);
    assertTrue(NeoCMS\MediaTypes::inspect($write('noise.mp4', $noise), 'noise.mp4')['category'] === 'video', 'Video containing chance byte sequences was refused');
    assertTrue(NeoCMS\MediaTypes::inspect($write('noise.mp3', $mp3 . '<%=<?=' . random_bytes(100000)), 'noise.mp3')['category'] === 'audio', 'Audio containing chance byte sequences was refused');

    // Refused: executables in any disguise, code inside otherwise valid files, macros, scripts, and unlisted types.
    $exe = "MZ\x90\x00\x03\x00\x00\x00" . str_repeat("\0", 200);
    foreach ([
        'setup.exe' => $exe, 'notes.pdf' => $exe, 'photo.jpg' => $exe, 'a.php.jpg' => $png, 'report.exe.pdf' => $pdf, 'shell.php' => '<?php echo 1;', 'run.sh' => "#!/bin/sh\necho hi\n",
        'evil.jpg' => $jpg . '<?php system($_GET["c"]); ?>', 'clip.mp4' => $mp4 . '<?php echo 1; ?>', 'tone.mp3' => $mp3 . '<script>alert(1)</script>',
        'page.txt' => '<script>alert(1)</script>', 'data.csv' => "a,b\n<?= 1 ?>,2\n", 'nul.txt' => "abc\0def",
        'js.pdf' => $pdf . "\n/JavaScript (app.alert(1))", 'launch.pdf' => $pdf . "\n/Launch /Win", 'embedded.pdf' => $pdf . "\n/EmbeddedFile",
        'macro.docx' => $zip(['[Content_Types].xml' => '<Types/>', 'word/document.xml' => '<w:document/>', 'word/vbaProject.bin' => 'x']),
        'ole.docx' => $zip(['[Content_Types].xml' => '<Types/>', 'word/document.xml' => '<w:document/>', 'word/embeddings/oleObject1.bin' => 'x']),
        'fake.docx' => $zip(['readme.txt' => 'hello']), 'basic.odt' => $zip(['mimetype' => 'application/vnd.oasis.opendocument.text', 'Basic/Module1.xba' => 'Sub Main']),
        'exec.odt' => $zip(['mimetype' => 'application/vnd.oasis.opendocument.text', 'content.xml' => '<x/>', 'tool.exe' => 'MZ']),
        'macro.docm' => $docx, 'vector.svg' => '<svg xmlns="http://www.w3.org/2000/svg"/>', 'page.html' => '<html></html>', 'old.doc' => $pdf, 'old.xls' => $pdf,
        'archive.zip' => $docx, 'noext' => $png, 'empty.png' => '', 'wrong.jpg' => $png,
    ] as $name => $bytes) {
        assertRejected(fn() => NeoCMS\MediaTypes::inspect($write($name, $bytes), $name), "{$name} was accepted");
    }
    assertRejected(fn() => NeoCMS\MediaTypes::inspect($write('big.png', $png), 'big.png', ['maxBytes' => 10]), 'An oversize file was accepted');
    assertRejected(fn() => NeoCMS\MediaTypes::inspect($write('wide.png', $png), 'wide.png', ['maxWidth' => 4]), 'An oversize image was accepted');

    // Stored names carry one dot, are safe, and are recognised as managed media; extensions map to the four categories.
    $stored = NeoCMS\MediaTypes::storedName('My Report (Final v2).PDF', 'pdf');
    assertTrue(preg_match('/^my-report-final-v2-[a-f0-9]{8}\.pdf$/', $stored) === 1 && substr_count($stored, '.') === 1 && NeoCMS\MediaTypes::isManagedName($stored), 'Stored media name is wrong: ' . $stored);
    assertTrue(NeoCMS\MediaTypes::storedName('../../etc/passwd.jpg', 'jpg') !== '' && !str_contains(NeoCMS\MediaTypes::storedName('../../a b.jpg', 'jpg'), '/'), 'A path leaked into a stored name');
    assertTrue(NeoCMS\MediaTypes::isManagedName(str_repeat('a', 32) . '.png') && !NeoCMS\MediaTypes::isManagedName('evil.php') && !NeoCMS\MediaTypes::isManagedName('x-12345678.exe') && !NeoCMS\MediaTypes::isManagedName('../x-12345678.pdf'), 'Managed-name check is too permissive');
    assertTrue(!in_array('exe', NeoCMS\MediaTypes::extensions(), true) && array_unique(array_map([NeoCMS\MediaTypes::class, 'categoryForExtension'], NeoCMS\MediaTypes::extensions())) === ['imagery', 'documents', 'video', 'audio'] || count(array_unique(array_map([NeoCMS\MediaTypes::class, 'categoryForExtension'], NeoCMS\MediaTypes::extensions()))) === 4, 'Extension map is wrong');
    removeTree($mediaDir);

    if (DIRECTORY_SEPARATOR === '/') { // chmod is a no-op on Windows
        assertTrue((fileperms($data) & 0777) === 0700, 'CMS data directory permissions are too broad');
        assertTrue((fileperms($schedulePath) & 0777) === 0600, 'CMS metadata file permissions are too broad');
    }

    echo "All NeoCMS integration tests passed.\n";
} finally {
    // Cleanup runs after both success and failure, leaving /tmp as tidy as we found it.
    removeTree($root);
    removeTree($data);
    removeTree($outside);
}
