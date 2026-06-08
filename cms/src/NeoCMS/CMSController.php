<?php

namespace NeoCMS;

/**
 * Handles every authenticated NeoCMS API action and filesystem content operation.
 *
 * Action methods are deliberately private: requests enter through handleRequest(), which applies
 * authentication, scheduled-job processing, error handling, and consistent JSON responses first.
 */
final class CMSController
{
    /** Complete site configuration supplied by cms/config.php. */
    private array $config;
    /** Session authentication and role-capability service. */
    private Authentication $authentication;
    /** Append-only operational audit logger. */
    private Logger $logger;
    /** JSON metadata and managed-file storage service. */
    private FileStore $store;
    /** Absolute directory containing page templates. */
    private string $templatesDir;
    /** Canonical public document root, ending with a directory separator. */
    private string $documentRoot;
    /** Validated CSS class used to discover and bind editable content regions. */
    private string $editableClass;
    /** Maximum complete HTML document size accepted from an authenticated author. */
    private int $maxContentBytes;
    /** Maximum encoded HTTP request size accepted by the controller. */
    private int $maxRequestBytes;
    /** Safety ceiling for recursive public-page scans. */
    private int $maxManagedPages;
    /** Safety ceiling for all directory entries visited during recursive page scans. */
    private int $maxScannedEntries;
    /** Maximum aggregate private draft storage. */
    private int $maxDraftBytes;
    /** Maximum number of queued or failed scheduled publications. */
    private int $maxSchedules;
    /** Maximum aggregate staged-publication storage. */
    private int $maxScheduledBytes;
    /** Maximum revisions retained for one page. */
    private int $maxRevisionsPerPage;
    /** Maximum revisions retained across the site. */
    private int $maxRevisionsTotal;
    /** Maximum aggregate revision storage. */
    private int $maxRevisionBytes;

    /** Build the controller services and normalise all configured filesystem paths. */
    public function __construct(array $config)
    {
        $this->config = $config;
        $this->authentication = new Authentication($config['authentication'] ?? [], $config['roles'] ?? [], $config['security'] ?? []);
        $this->logger = new Logger($config['audit'] ?? true, $config['security'] ?? []);
        // A custom data directory is primarily useful for tests and hardened deployments.
        $dataDirectory = $config['dataDirectory'] ?? (__DIR__ . '/../../data');
        $this->store = new FileStore((string) $dataDirectory);
        $templatesDir = realpath(__DIR__ . '/../../templates');
        $documentRoot = realpath((string) ($_SERVER['DOCUMENT_ROOT'] ?? ''));
        if ($templatesDir === false || $documentRoot === false) {
            throw new \RuntimeException('CMS filesystem paths are unavailable');
        }
        $this->templatesDir = rtrim($templatesDir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
        $this->documentRoot = rtrim($documentRoot, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
        $security = is_array($config['security'] ?? null) ? $config['security'] : [];
        $this->maxContentBytes = max(1024, (int) ($security['maxContentBytes'] ?? 5 * 1024 * 1024));
        $this->maxRequestBytes = max($this->maxContentBytes, (int) ($security['maxRequestBytes'] ?? 6 * 1024 * 1024));
        $this->maxManagedPages = max(1, (int) ($security['maxManagedPages'] ?? 5000));
        $this->maxScannedEntries = max($this->maxManagedPages, (int) ($security['maxScannedEntries'] ?? 20000));
        $this->maxDraftBytes = max($this->maxContentBytes, (int) ($security['maxDraftBytes'] ?? 250 * 1024 * 1024));
        $this->maxSchedules = max(1, (int) ($security['maxSchedules'] ?? 100));
        $this->maxScheduledBytes = max($this->maxContentBytes, (int) ($security['maxScheduledBytes'] ?? 250 * 1024 * 1024));
        $this->maxRevisionsPerPage = max(1, (int) ($security['maxRevisionsPerPage'] ?? 50));
        $this->maxRevisionsTotal = max($this->maxRevisionsPerPage, (int) ($security['maxRevisionsTotal'] ?? 2000));
        $this->maxRevisionBytes = max($this->maxContentBytes, (int) ($security['maxRevisionBytes'] ?? 500 * 1024 * 1024));
        // Reject selectors that could escape the intended single-class query.
        $editableClass = $config['editableClass'] ?? 'editable';
        $this->editableClass = is_string($editableClass) && preg_match('/^[a-zA-Z_][a-zA-Z0-9_-]*$/', $editableClass)
            ? $editableClass
            : 'editable';
    }

    /**
     * Authenticate, dispatch, and serialise one HTTP API request.
     *
     * Due scheduled jobs are processed opportunistically on every API request as a useful backup
     * to cron. The CLI worker remains the reliable option for quiet sites enjoying a day off.
     */
    public function handleRequest(): void
    {
        if ((int) ($_SERVER['CONTENT_LENGTH'] ?? 0) > $this->maxRequestBytes) {
            $this->respond(['error' => 'Request body is too large'], 413);
            return;
        }
        $action = $_REQUEST['action'] ?? '';
        if (!$this->authentication->isLoggedIn()) {
            $this->respond(['error' => 'Not authenticated'], 401);
            return;
        }

        try {
            $this->publishDueJobs();
            // Action names map to private methods such as saveDraftAction().
            $method = $action . 'Action';
            if ($action === '' || !method_exists($this, $method)) {
                $this->respond(['error' => 'Unknown action'], 404);
                return;
            }
            $this->$method();
        } catch (\RuntimeException | \InvalidArgumentException $exception) {
            $this->logger->write("Controller action {$action} failed: {$exception->getMessage()}", $this->user());
            $this->respond(['error' => $exception->getMessage()], 400);
        } catch (\Throwable $exception) {
            $this->logger->write("Controller action {$action} failed unexpectedly: {$exception->getMessage()}", $this->user());
            $this->respond(['error' => 'Request failed'], 500);
        }
    }

    /** Process all due scheduled jobs from the command-line worker. */
    public function publishScheduled(): int
    {
        return $this->publishDueJobs();
    }

    /** Publish the submitted page, retain its previous revision, and clear its draft. */
    private function saveAction(): void
    {
        $this->requirePost('publish');
        $uri = $this->requiredPost('uri');
        $content = $this->requiredContentPost('content');
        $this->publishContent($uri, $content, 'Published page');
        $this->deleteDraft($uri);
        $this->respond(['message' => 'Page has been published', 'destination' => $uri]);
    }

    /** Save private draft HTML without changing the publicly served page. */
    private function saveDraftAction(): void
    {
        $this->requirePost('draft');
        $uri = $this->requiredPost('uri');
        $content = $this->requiredContentPost('content');
        $draftName = hash('sha256', $this->normaliseUri($uri)) . '.html';
        $draftPath = $this->store->directory('drafts') . $draftName;
        $draftBytes = $this->directoryBytes('drafts') - (is_file($draftPath) ? (int) filesize($draftPath) : 0);
        if ($draftBytes + strlen($content) > $this->maxDraftBytes) {
            throw new \RuntimeException('Draft storage quota has been reached');
        }
        $this->store->writePrivateFile('drafts', $draftName, $content);
        // Draft filenames are URI hashes, while this index keeps their human-readable metadata.
        $drafts = $this->store->read('drafts');
        $drafts[$this->normaliseUri($uri)] = [
            'updated' => date(DATE_ATOM),
            'user' => $this->user(),
        ];
        $this->store->write('drafts', $drafts);
        $this->activity('Saved draft', $uri);
        $this->respond(['message' => 'Draft saved', 'updated' => date(DATE_ATOM)]);
    }

    /** Return the saved draft for one URI, if present. */
    private function getDraftAction(): void
    {
        $uri = $this->requiredRequest('uri');
        $path = $this->draftPath($uri);
        $this->respond(['exists' => is_file($path), 'content' => is_file($path) ? file_get_contents($path) : null]);
    }

    /** Queue submitted HTML for publication at a future absolute timestamp. */
    private function scheduleAction(): void
    {
        $this->requirePost('schedule');
        $uri = $this->requiredPost('uri');
        $content = $this->requiredContentPost('content');
        $this->existingPagePath($uri);
        $publishAt = new \DateTimeImmutable($this->requiredPost('publish_at'));
        if ($publishAt <= new \DateTimeImmutable()) {
            throw new \RuntimeException('Publish time must be in the future');
        }
        // Store bulky HTML separately so the JSON schedule index remains easy to inspect.
        $id = bin2hex(random_bytes(10));
        $jobs = $this->store->read('schedules');
        if (count($jobs) >= $this->maxSchedules || $this->directoryBytes('scheduled') + strlen($content) > $this->maxScheduledBytes) {
            throw new \RuntimeException('Scheduled publication quota has been reached');
        }
        $this->store->writePrivateFile('scheduled', $id . '.html', $content);
        $jobs[$id] = ['uri' => $this->normaliseUri($uri), 'publish_at' => $publishAt->format(DATE_ATOM), 'user' => $this->user()];
        $this->store->write('schedules', $jobs);
        $this->activity('Scheduled publication', $uri);
        $this->respond(['message' => 'Page scheduled', 'id' => $id, 'publish_at' => $publishAt->format(DATE_ATOM)]);
    }

    /** Cancel one scheduled publication and remove its staged HTML file. */
    private function cancelScheduleAction(): void
    {
        $this->requirePost('schedule');
        $id = $this->requiredPost('id');
        $jobs = $this->store->read('schedules');
        unset($jobs[$id]);
        @unlink($this->store->directory('scheduled') . basename($id) . '.html');
        $this->store->write('schedules', $jobs);
        $this->respond(['message' => 'Schedule cancelled']);
    }

    /** List usable HTML templates for the new-page dialogue. */
    private function getTemplatesAction(): void
    {
        $templates = [];
        foreach (array_diff(scandir($this->templatesDir), ['.', '..']) as $file) {
            if (preg_match('/\.html?$/i', $file)) {
                $templates[] = ['id' => $file, 'name' => pathinfo($file, PATHINFO_FILENAME)];
            }
        }
        $this->respond($templates);
    }

    /** Create a new page from a selected template; administrators only. */
    private function newPageAction(): void
    {
        $this->requirePost('manage');
        $uri = $this->normaliseNewPageUri($this->requiredPost('filename'));
        $template = basename($this->requiredPost('template'));
        $source = realpath($this->templatesDir . $template);
        if (!$source || !str_starts_with($source, $this->templatesDir)) {
            throw new \RuntimeException('Template not found');
        }
        if (count($this->pageFiles()) >= $this->maxManagedPages) {
            throw new \RuntimeException('Managed page limit has been reached');
        }
        $destination = $this->newPagePath($uri);
        if (file_exists($destination)) {
            throw new \RuntimeException('Page already exists');
        }
        $this->ensureParentDirectory($destination);
        $this->assertNoSymlinkComponents($destination);
        if (!copy($source, $destination)) {
            throw new \RuntimeException('Template copy failed');
        }
        $this->activity('Created page', $uri);
        $this->respond(['message' => 'Page created', 'url' => $uri]);
    }

    /** Rename, duplicate, or revision-then-delete an existing managed page. */
    private function pageAction(): void
    {
        $this->requirePost('manage');
        $operation = $this->requiredPost('operation');
        $sourceUri = $this->normaliseUri($this->requiredPost('uri'));
        $source = $this->existingPagePath($sourceUri);

        // Deletion gets its own branch because it has no target URI.
        if ($operation === 'delete') {
            $this->createRevision($sourceUri, (string) file_get_contents($source), 'Before delete');
            if (!unlink($source)) {
                throw new \RuntimeException('Unable to delete page');
            }
            $this->activity('Deleted page', $sourceUri);
            $this->respond(['message' => 'Page deleted']);
            return;
        }

        $targetUri = $this->normaliseNewPageUri($this->requiredPost('target'));
        $target = $this->newPagePath($targetUri);
        if (file_exists($target)) {
            throw new \RuntimeException('Target page already exists');
        }
        $this->ensureParentDirectory($target);
        $this->assertNoSymlinkComponents($target);
        $ok = $operation === 'rename' ? rename($source, $target) : ($operation === 'duplicate' && copy($source, $target));
        if (!$ok) {
            throw new \RuntimeException('Page operation failed');
        }
        $this->activity(ucfirst($operation) . 'd page', $sourceUri . ' -> ' . $targetUri);
        $this->respond(['message' => 'Page ' . $operation . 'd', 'url' => $targetUri]);
    }

    /** Discover HTML pages containing the configured editable class. */
    private function getPagesAction(): void
    {
        $drafts = $this->store->read('drafts');
        $pages = [];
        foreach ($this->pageFiles() as $path) {
            $uri = '/' . str_replace(DIRECTORY_SEPARATOR, '/', substr($path, strlen($this->documentRoot)));
            $html = (string) file_get_contents($path);
            if (!$this->hasEditableClass($html)) {
                continue;
            }
            $pages[] = [
                'name' => $uri,
                'url' => $uri,
                'title' => $this->extractTitle($html),
                'modified' => date(DATE_ATOM, filemtime($path)),
                'draft' => isset($drafts[$uri]),
            ];
        }
        usort($pages, fn(array $a, array $b) => strcmp($a['name'], $b['name']));
        $this->respond($pages);
    }

    /** List newest-first revisions belonging to one page URI. */
    private function revisionsAction(): void
    {
        $uri = $this->normaliseUri($this->requiredRequest('uri'));
        $items = array_values(array_filter($this->store->read('revisions'), fn(array $item) => $item['uri'] === $uri));
        usort($items, fn(array $a, array $b) => strcmp($b['created'], $a['created']));
        $this->respond($items);
    }

    /** Restore a revision, recreating a deleted page when necessary. */
    private function restoreRevisionAction(): void
    {
        $this->requirePost('publish');
        $id = basename($this->requiredPost('id'));
        $index = $this->store->read('revisions');
        $revision = $index[$id] ?? null;
        $path = $this->store->directory('revisions') . $id . '.html';
        if (!$revision || !is_file($path)) {
            throw new \RuntimeException('Revision not found');
        }
        $content = (string) file_get_contents($path);
        try {
            $this->publishContent($revision['uri'], $content, 'Restored revision');
        } catch (\RuntimeException $exception) {
            // A missing target is expected when restoring the revision of a deleted page.
            if ($exception->getMessage() !== 'Invalid page path') {
                throw $exception;
            }
            $destination = $this->newPagePath($this->normaliseNewPageUri($revision['uri']));
            $this->ensureParentDirectory($destination);
            $this->assertNoSymlinkComponents($destination);
            if (file_put_contents($destination, $content, LOCK_EX) === false) {
                throw new \RuntimeException('Unable to restore deleted page');
            }
            $this->activity('Restored deleted page', $revision['uri']);
        }
        $this->respond(['message' => 'Revision restored', 'url' => $revision['uri']]);
    }

    /** Return the complete shared-content registry. */
    private function sharedAction(): void
    {
        $this->respond($this->store->read('shared'));
    }

    /** Save a shared block and propagate it to every marked public page. */
    private function saveSharedAction(): void
    {
        $this->requirePost('manage');
        $key = preg_replace('/[^a-zA-Z0-9_-]/', '', $this->requiredPost('key'));
        $content = $this->limitedPost('content', $this->maxContentBytes);
        if ($key === '') {
            throw new \RuntimeException('Shared block name is required');
        }
        $shared = $this->store->read('shared');
        $shared[$key] = ['content' => $content, 'updated' => date(DATE_ATOM), 'user' => $this->user()];
        $this->store->write('shared', $shared);
        $updated = $this->propagateSharedBlock($key, $content);
        $this->activity('Updated shared block', $key);
        $this->respond(['message' => "Shared block updated on {$updated} page(s)", 'updated_pages' => $updated]);
    }

    /** Return all configured navigation menus. */
    private function menusAction(): void
    {
        $this->respond($this->store->read('menus'));
    }

    /** Validate, save, render, and propagate a named navigation menu. */
    private function saveMenuAction(): void
    {
        $this->requirePost('manage');
        $name = preg_replace('/[^a-zA-Z0-9_-]/', '', $this->requiredPost('name'));
        $items = json_decode($this->limitedPost('items', 1024 * 1024), true);
        if ($name === '' || !is_array($items)) {
            throw new \RuntimeException('A menu name and items are required');
        }
        $clean = [];
        // Ignore malformed rows rather than storing menu entries that cannot produce a link.
        foreach ($items as $item) {
            if (!is_array($item) || empty($item['url'])) {
                continue;
            }
            $clean[] = ['label' => trim((string) ($item['label'] ?? $item['url'])), 'url' => $this->normaliseLink((string) $item['url']), 'parent' => trim((string) ($item['parent'] ?? ''))];
        }
        $menus = $this->store->read('menus');
        $menus[$name] = ['items' => $clean, 'updated' => date(DATE_ATOM)];
        $this->store->write('menus', $menus);
        $html = $this->renderMenu($name, $clean);
        $updated = $this->propagateMenu($name, $html);
        $this->activity('Updated menu', $name);
        $this->respond(['message' => "Menu saved and updated on {$updated} page(s)", 'html' => $html, 'updated_pages' => $updated]);
    }

    /** List uploaded files with metadata, byte size, modification time, and page-use count. */
    private function mediaAction(): void
    {
        $metadata = $this->store->read('media');
        $usage = $this->mediaUsageCounts();
        $uploadDir = dirname(__DIR__, 3) . '/uploads/';
        $items = [];
        // index.php guards the directory and is infrastructure rather than editorial media.
        foreach (glob($uploadDir . '*') ?: [] as $file) {
            if (!is_file($file) || !$this->isManagedMediaName(basename($file))) {
                continue;
            }
            $url = '/uploads/' . basename($file);
            $items[] = [
                'name' => basename($file), 'url' => $url, 'size' => filesize($file),
                'modified' => date(DATE_ATOM, filemtime($file)), 'alt' => $metadata[basename($file)]['alt'] ?? '',
                'uses' => $usage[$url] ?? 0,
            ];
        }
        usort($items, fn(array $a, array $b) => strcmp($b['modified'], $a['modified']));
        $this->respond($items);
    }

    /** Update the reusable alternative-text metadata for one uploaded image. */
    private function updateMediaAction(): void
    {
        $this->requirePost('upload');
        $name = $this->managedMediaName($this->requiredPost('name'));
        $metadata = $this->store->read('media');
        $alt = is_string($_POST['alt'] ?? null) ? trim($_POST['alt']) : '';
        $metadata[$name] = ['alt' => substr(preg_replace('/[\x00-\x1F\x7F]/u', '', $alt) ?? '', 0, 500)];
        $this->store->write('media', $metadata);
        $this->respond(['message' => 'Media details saved']);
    }

    /** Delete an uploaded file after an administrator confirms the operation in the UI. */
    private function deleteMediaAction(): void
    {
        $this->requirePost('manage');
        $name = $this->managedMediaName($this->requiredPost('name'));
        $path = dirname(__DIR__, 3) . '/uploads/' . $name;
        if (!is_file($path) || !unlink($path)) {
            throw new \RuntimeException('Unable to delete media');
        }
        $metadata = $this->store->read('media');
        unset($metadata[$name]);
        $this->store->write('media', $metadata);
        $this->activity('Deleted media', $name);
        $this->respond(['message' => 'Media deleted']);
    }

    /** Return permissions, pending work, recent activity, and writable-directory warnings. */
    private function dashboardAction(): void
    {
        $drafts = $this->store->read('drafts');
        $schedules = $this->store->read('schedules');
        $activity = array_slice(array_reverse($this->store->read('activity')), 0, 20);
        $problems = [];
        foreach ([__DIR__ . '/../../data', dirname(__DIR__, 3) . '/uploads'] as $directory) {
            if (!is_dir($directory) || !is_writable($directory)) {
                $problems[] = $directory . ' is not writable';
            }
        }
        $this->respond([
            'user' => $this->user(), 'role' => $this->authentication->getRole(),
            'permissions' => ['draft' => $this->authentication->can('draft'), 'publish' => $this->authentication->can('publish'), 'schedule' => $this->authentication->can('schedule'), 'manage' => $this->authentication->can('manage')],
            'drafts' => $drafts, 'schedules' => $schedules, 'activity' => $activity, 'problems' => $problems,
        ]);
    }

    /** End the authenticated session after CSRF validation. */
    private function logoutAction(): void
    {
        $this->requirePost();
        $this->authentication->logout();
        $this->respond(['message' => 'Logged out']);
    }

    /**
     * Atomically replace a public page, preserving its previous contents as a revision.
     *
     * @param string $uri Public page URI.
     * @param string $content Complete replacement HTML document.
     * @param string $reason Human-readable activity and revision reason.
     */
    private function publishContent(string $uri, string $content, string $reason): void
    {
        $path = $this->existingPagePath($uri);
        $old = (string) file_get_contents($path);
        if ($old !== $content) {
            $this->createRevision($uri, $old, $reason);
        }
        // Write beside the destination and rename to avoid serving a partially written document.
        $temporary = $path . '.neo-' . bin2hex(random_bytes(5));
        if (file_put_contents($temporary, $content, LOCK_EX) === false || !rename($temporary, $path)) {
            @unlink($temporary);
            throw new \RuntimeException('Failed to publish page');
        }
        $this->captureSharedBlocks($content);
        $this->activity($reason, $uri);
    }

    /** Save an immutable HTML snapshot and add its searchable metadata to the revision index. */
    private function createRevision(string $uri, string $content, string $reason): void
    {
        $id = date('YmdHis') . '-' . bin2hex(random_bytes(5));
        $this->store->writePrivateFile('revisions', $id . '.html', $content);
        $index = $this->store->read('revisions');
        $index[$id] = ['id' => $id, 'uri' => $this->normaliseUri($uri), 'created' => date(DATE_ATOM), 'user' => $this->user(), 'reason' => $reason];
        $remove = $this->pruneRevisions($index);
        $this->store->write('revisions', $index);
        $directory = $this->store->directory('revisions');
        foreach ($remove as $removeId) {
            @unlink($directory . basename($removeId) . '.html');
        }
    }

    /** Apply per-page, global-count, and aggregate-byte retention limits to revisions. */
    private function pruneRevisions(array &$index): array
    {
        uasort($index, static fn(array $a, array $b): int => strcmp((string) ($b['created'] ?? ''), (string) ($a['created'] ?? '')));
        $perPage = [];
        $kept = 0;
        $bytes = 0;
        $remove = [];
        $directory = $this->store->directory('revisions');

        foreach ($index as $id => $revision) {
            $uri = (string) ($revision['uri'] ?? '');
            $perPage[$uri] = ($perPage[$uri] ?? 0) + 1;
            $path = $directory . basename((string) $id) . '.html';
            $size = is_file($path) ? (int) filesize($path) : 0;
            $overPage = $perPage[$uri] > $this->maxRevisionsPerPage;
            $overCount = $kept >= $this->maxRevisionsTotal;
            $overBytes = $bytes + $size > $this->maxRevisionBytes;
            if ($overPage || $overCount || $overBytes || !is_file($path)) {
                $remove[] = (string) $id;
                continue;
            }
            $kept++;
            $bytes += $size;
        }

        foreach ($remove as $id) {
            unset($index[$id]);
        }
        return $remove;
    }

    /** Sum regular file sizes within one private data subdirectory. */
    private function directoryBytes(string $name): int
    {
        $bytes = 0;
        foreach (glob($this->store->directory($name) . '*') ?: [] as $path) {
            if (is_file($path)) {
                $bytes += (int) filesize($path);
            }
        }
        return $bytes;
    }

    /**
     * Publish every job whose timestamp is due and remove completed schedule artefacts.
     *
     * @return int Number of pages successfully published during this pass.
     */
    private function publishDueJobs(): int
    {
        $lockPath = $this->store->directory('locks') . 'scheduled.lock';
        $lock = fopen($lockPath, 'c+');
        if ($lock === false || !flock($lock, LOCK_EX)) {
            if (is_resource($lock)) {
                fclose($lock);
            }
            throw new \RuntimeException('Unable to lock scheduled publications');
        }
        @chmod($lockPath, 0600);

        try {
            $jobs = $this->store->read('schedules');
            $changed = false;
            $published = 0;
            foreach ($jobs as $id => &$job) {
                if (($job['status'] ?? 'pending') !== 'pending' || strtotime($job['publish_at'] ?? '') > time()) {
                    continue;
                }
                $path = $this->store->directory('scheduled') . basename((string) $id) . '.html';
                try {
                    if (!is_file($path)) {
                        throw new \RuntimeException('Staged publication content is missing');
                    }
                    $this->publishContent((string) ($job['uri'] ?? ''), (string) file_get_contents($path), 'Scheduled publication');
                    unlink($path);
                    unset($jobs[$id]);
                    $published++;
                } catch (\Throwable $exception) {
                    $job['status'] = 'failed';
                    $job['failed_at'] = date(DATE_ATOM);
                    $job['error'] = 'Scheduled publication failed';
                    $this->logger->write("Scheduled publication {$id} failed: {$exception->getMessage()}", (string) ($job['user'] ?? 'scheduler'));
                }
                $changed = true;
            }
            unset($job);
            // The index is written once after the loop, avoiding needless churn for several due jobs.
            if ($changed) {
                $this->store->write('schedules', $jobs);
            }
            return $published;
        } finally {
            flock($lock, LOCK_UN);
            fclose($lock);
        }
    }

    /** Capture shared-region values from a newly published page into the central registry. */
    private function captureSharedBlocks(string $html): void
    {
        $dom = new \DOMDocument();
        @$dom->loadHTML($html, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        $xpath = new \DOMXPath($dom);
        $shared = $this->store->read('shared');
        foreach ($xpath->query('//*[@data-neo-shared]') as $node) {
            $key = $node->getAttribute('data-neo-shared');
            if ($key !== '') {
                $shared[$key] = ['content' => $this->innerHtml($node), 'updated' => date(DATE_ATOM), 'user' => $this->user()];
            }
        }
        $this->store->write('shared', $shared);
    }

    /**
     * Replace matching shared regions across public pages.
     *
     * Every affected page receives a revision first, because global edits should remain globally
     * reversible rather than becoming an exciting lesson in backups.
     *
     * @return int Number of changed pages.
     */
    private function propagateSharedBlock(string $key, string $content): int
    {
        $count = 0;
        foreach ($this->pageFiles() as $path) {
            $html = (string) file_get_contents($path);
            if (!str_contains($html, 'data-neo-shared')) {
                continue;
            }
            $dom = new \DOMDocument();
            @$dom->loadHTML($html, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
            $xpath = new \DOMXPath($dom);
            $nodes = $xpath->query('//*[@data-neo-shared="' . $key . '"]');
            if ($nodes->length === 0) {
                continue;
            }
            $this->createRevision($this->uriForPath($path), $html, 'Before shared content update');
            foreach ($nodes as $node) {
                $this->replaceInnerHtml($dom, $node, $content);
            }
            file_put_contents($path, $dom->saveHTML(), LOCK_EX);
            $count++;
        }
        return $count;
    }

    /** Replace matching generated menus across public pages, revisioning each page first. */
    private function propagateMenu(string $name, string $menuHtml): int
    {
        $count = 0;
        foreach ($this->pageFiles() as $path) {
            $html = (string) file_get_contents($path);
            if (!str_contains($html, 'data-neo-menu')) {
                continue;
            }
            $dom = new \DOMDocument();
            @$dom->loadHTML($html, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
            $xpath = new \DOMXPath($dom);
            $nodes = $xpath->query('//*[@data-neo-menu="' . $name . '"]');
            if ($nodes->length === 0) {
                continue;
            }
            // Parse the rendered menu once, then import a fresh deep copy for each destination node.
            $menuDom = new \DOMDocument();
            @$menuDom->loadHTML($menuHtml, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
            $sourceNav = $menuDom->getElementsByTagName('nav')->item(0);
            if (!$sourceNav) {
                continue;
            }
            $this->createRevision($this->uriForPath($path), $html, 'Before menu update');
            foreach (iterator_to_array($nodes) as $node) {
                $replacement = $dom->importNode($sourceNav, true);
                $node->parentNode->replaceChild($replacement, $node);
            }
            file_put_contents($path, $dom->saveHTML(), LOCK_EX);
            $count++;
        }
        return $count;
    }

    /**
     * Replace a DOM node's children with an HTML fragment.
     *
     * DOMDocument parses forgiving HTML here rather than strict XML, allowing ordinary authoring
     * fragments such as <br> without demanding that editors suddenly become XML librarians.
     */
    private function replaceInnerHtml(\DOMDocument $dom, \DOMNode $node, string $html): void
    {
        while ($node->firstChild) {
            $node->removeChild($node->firstChild);
        }
        $temporary = new \DOMDocument();
        @$temporary->loadHTML('<div id="neo-fragment">' . $html . '</div>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        $wrapper = $temporary->getElementById('neo-fragment');
        if (!$wrapper) {
            $node->appendChild($dom->createTextNode($html));
            return;
        }
        foreach (iterator_to_array($wrapper->childNodes) as $child) {
            $node->appendChild($dom->importNode($child, true));
        }
    }

    /** Return every public .html or .htm file while excluding the CMS application itself. */
    private function pageFiles(): array
    {
        $files = [];
        $scanned = 0;
        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($this->documentRoot, \FilesystemIterator::SKIP_DOTS));
        foreach ($iterator as $file) {
            $scanned++;
            if ($scanned > $this->maxScannedEntries) {
                throw new \RuntimeException('Document root scan limit exceeded');
            }
            if (count($files) >= $this->maxManagedPages) {
                throw new \RuntimeException('Managed page limit exceeded');
            }
            if ($file->isLink()) {
                continue;
            }
            $path = $file->getRealPath();
            if (!$file->isFile() || $path === false || !str_starts_with($path, $this->documentRoot) || !preg_match('/\.html?$/i', $path)) {
                continue;
            }
            if (str_starts_with($path, $this->documentRoot . 'cms' . DIRECTORY_SEPARATOR)) {
                continue;
            }
            $files[] = $path;
        }
        return $files;
    }

    /** Resolve an existing public URI to a canonical, in-root HTML file path. */
    private function existingPagePath(string $uri): string
    {
        $uri = $this->normaliseUri($uri);
        $candidate = $this->documentRoot . ltrim($uri, '/');
        if (is_dir($candidate)) {
            $candidate = rtrim($candidate, '/\\') . '/index.html';
        } elseif (!preg_match('/\.html?$/i', $candidate)) {
            $candidate .= '.html';
        }
        $real = realpath($candidate);
        if (!$real || !str_starts_with($real, $this->documentRoot) || str_starts_with($real, $this->documentRoot . 'cms' . DIRECTORY_SEPARATOR) || !preg_match('/\.html?$/i', $real)) {
            throw new \RuntimeException('Invalid page path');
        }
        return $real;
    }

    /** Build a safe path for a page that may not exist yet. */
    private function newPagePath(string $uri): string
    {
        $path = $this->documentRoot . ltrim($uri, '/');
        if (!str_starts_with($path, $this->documentRoot)) {
            throw new \RuntimeException('Invalid page path');
        }
        $this->assertNoSymlinkComponents($path);
        $parent = realpath(dirname($path));
        if ($parent && !str_starts_with($parent . DIRECTORY_SEPARATOR, $this->documentRoot)) {
            throw new \RuntimeException('Invalid page path');
        }
        if (str_starts_with($path, $this->documentRoot . 'cms' . DIRECTORY_SEPARATOR)) {
            throw new \RuntimeException('CMS files cannot be managed as pages');
        }
        return $path;
    }

    /** Reject page targets whose existing path components contain symbolic links. */
    private function assertNoSymlinkComponents(string $path): void
    {
        $relative = substr($path, strlen($this->documentRoot));
        $current = rtrim($this->documentRoot, DIRECTORY_SEPARATOR);
        foreach (explode('/', str_replace('\\', '/', $relative)) as $component) {
            if ($component === '') {
                continue;
            }
            $current .= DIRECTORY_SEPARATOR . $component;
            if (is_link($current)) {
                throw new \RuntimeException('Symbolic links are not permitted in managed page paths');
            }
            if (file_exists($current)) {
                $real = realpath($current);
                if ($real === false || !str_starts_with($real . (is_dir($real) ? DIRECTORY_SEPARATOR : ''), $this->documentRoot)) {
                    throw new \RuntimeException('Invalid page path');
                }
            }
        }
    }

    /** Canonicalise a request URI and reject traversal or null-byte input. */
    private function normaliseUri(string $uri): string
    {
        $path = parse_url($uri, PHP_URL_PATH) ?: '/';
        $path = '/' . ltrim(str_replace('\\', '/', $path), '/');
        if (str_contains($path, '..') || str_contains($path, "\0")) {
            throw new \RuntimeException('Invalid URI');
        }
        return $path;
    }

    /** Canonicalise a new page URI, append .html when absent, and enforce safe characters. */
    private function normaliseNewPageUri(string $uri): string
    {
        $uri = $this->normaliseUri($uri);
        if (!preg_match('/\.html?$/i', $uri)) {
            $uri .= '.html';
        }
        if (!preg_match('#^/[a-zA-Z0-9_./-]+\.html?$#', $uri)) {
            throw new \RuntimeException('Invalid page name');
        }
        return $uri;
    }

    /** Accept an absolute HTTP(S) link or normalise a local site path. */
    private function normaliseLink(string $url): string
    {
        if (preg_match('#^https?://#i', $url)) {
            return filter_var($url, FILTER_SANITIZE_URL);
        }
        return $this->normaliseUri($url);
    }

    /** Derive the private draft filename from a stable hash of its public URI. */
    private function draftPath(string $uri): string
    {
        return $this->store->directory('drafts') . hash('sha256', $this->normaliseUri($uri)) . '.html';
    }

    /** Remove a page's draft HTML and its dashboard metadata entry. */
    private function deleteDraft(string $uri): void
    {
        @unlink($this->draftPath($uri));
        $drafts = $this->store->read('drafts');
        unset($drafts[$this->normaliseUri($uri)]);
        $this->store->write('drafts', $drafts);
    }

    /** Count all managed upload references in one pass across the public pages. */
    private function mediaUsageCounts(): array
    {
        $uses = [];
        foreach ($this->pageFiles() as $path) {
            $html = (string) file_get_contents($path);
            if (preg_match_all('#/uploads/[a-f0-9]{32}\.(?:jpg|png|gif|webp)#', $html, $matches)) {
                foreach ($matches[0] as $url) {
                    $uses[$url] = ($uses[$url] ?? 0) + 1;
                }
            }
        }
        return $uses;
    }

    /** Validate that a filename belongs to the randomised image namespace created by uploads. */
    private function managedMediaName(string $name): string
    {
        $name = basename($name);
        if (!$this->isManagedMediaName($name)) {
            throw new \RuntimeException('Invalid media filename');
        }
        return $name;
    }

    /** Return whether a filename is a random NeoCMS image name with an allowed extension. */
    private function isManagedMediaName(string $name): bool
    {
        return preg_match('/^[a-f0-9]{32}\.(?:jpg|png|gif|webp)$/', $name) === 1;
    }

    /**
     * Render a nested, escaped navigation list from flat parent-labelled items.
     * Circular parent references are stopped by the ancestor list rather than pursued forever.
     */
    private function renderMenu(string $name, array $items): string
    {
        $children = [];
        foreach ($items as $item) {
            $children[$item['parent'] ?? ''][] = $item;
        }
        $render = function (string $parent, array $ancestors = []) use (&$render, $children): string {
            if (empty($children[$parent])) {
                return '';
            }
            $html = '<ul>';
            foreach ($children[$parent] as $item) {
                $label = htmlspecialchars($item['label'], ENT_QUOTES);
                $url = htmlspecialchars($item['url'], ENT_QUOTES);
                $nested = in_array($item['label'], $ancestors, true) ? '' : $render($item['label'], array_merge($ancestors, [$item['label']]));
                $html .= '<li><a href="' . $url . '">' . $label . '</a>' . $nested . '</li>';
            }
            return $html . '</ul>';
        };
        return '<nav data-neo-menu="' . htmlspecialchars($name, ENT_QUOTES) . '">' . $render('') . '</nav>';
    }

    /** Extract a plain-text document title for the page picker. */
    private function extractTitle(string $html): string
    {
        return preg_match('/<title[^>]*>(.*?)<\/title>/is', $html, $match) ? trim(strip_tags($match[1])) : 'Untitled';
    }

    /** Convert an absolute page path back into its public, slash-separated URI. */
    private function uriForPath(string $path): string
    {
        return '/' . str_replace(DIRECTORY_SEPARATOR, '/', substr($path, strlen($this->documentRoot)));
    }

    /** Determine whether a document contains at least one exactly matching editable class. */
    private function hasEditableClass(string $html): bool
    {
        $dom = new \DOMDocument();
        @$dom->loadHTML($html);
        $xpath = new \DOMXPath($dom);
        $class = $this->editableClass;
        // Class-token matching avoids treating "editable-extra" as though it were "editable".
        $query = '//*[contains(concat(" ", normalize-space(@class), " "), " ' . $class . ' ")]';
        return $xpath->query($query)->length > 0;
    }

    /** Serialise only the children of a DOM node, excluding the node's own wrapper tag. */
    private function innerHtml(\DOMNode $node): string
    {
        $html = '';
        foreach ($node->childNodes as $child) {
            $html .= $node->ownerDocument->saveHTML($child);
        }
        return $html;
    }

    /** Create a new page's parent directories when they do not already exist. */
    private function ensureParentDirectory(string $path): void
    {
        $directory = dirname($path);
        if (!is_dir($directory) && !mkdir($directory, 0755, true) && !is_dir($directory)) {
            throw new \RuntimeException('Unable to create page directory');
        }
    }

    /**
     * Enforce POST, CSRF, and optional role-capability requirements for a mutating action.
     *
     * Read-only actions still require authentication through handleRequest(), but do not need a
     * CSRF token because they cannot alter server state.
     */
    private function requirePost(?string $capability = null): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            throw new \RuntimeException('POST required');
        }
        if (!$this->authentication->isValidCsrfToken($_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? null)) {
            throw new \RuntimeException('Invalid CSRF token');
        }
        if ($capability && !$this->authentication->can($capability)) {
            throw new \RuntimeException('Your role cannot perform this action');
        }
    }

    /** Return a required string POST field or raise a client-facing validation error. */
    private function requiredPost(string $key): string
    {
        $value = $_POST[$key] ?? '';
        if (!is_string($value) || $value === '') {
            throw new \RuntimeException("{$key} is required");
        }
        return $value;
    }

    /** Return a required complete-document field after enforcing the configured byte limit. */
    private function requiredContentPost(string $key): string
    {
        $value = $this->requiredPost($key);
        if (strlen($value) > $this->maxContentBytes) {
            throw new \RuntimeException('Content exceeds the configured size limit');
        }
        return $value;
    }

    /** Return an optional string POST field after enforcing an action-specific byte limit. */
    private function limitedPost(string $key, int $maxBytes): string
    {
        $value = $_POST[$key] ?? '';
        if (!is_string($value)) {
            throw new \RuntimeException("{$key} must be a string");
        }
        if (strlen($value) > $maxBytes) {
            throw new \RuntimeException("{$key} exceeds the configured size limit");
        }
        return $value;
    }

    /** Return a required string field from either query or form input. */
    private function requiredRequest(string $key): string
    {
        $value = $_REQUEST[$key] ?? '';
        if (!is_string($value) || $value === '') {
            throw new \RuntimeException("{$key} is required");
        }
        return $value;
    }

    /** Add a bounded dashboard activity entry and mirror it to the audit log. */
    private function activity(string $action, string $target): void
    {
        $entries = $this->store->read('activity');
        $entries[] = ['created' => date(DATE_ATOM), 'user' => $this->user(), 'action' => $action, 'target' => $target];
        // Retaining the latest 250 entries keeps the dashboard useful without growing forever.
        $this->store->write('activity', array_slice($entries, -250));
        $this->logger->write($action . ': ' . $target, $this->user());
    }

    /** Return the authenticated username used for metadata and audit attribution. */
    private function user(): string
    {
        return $this->authentication->getLoggedInUser();
    }

    /** Emit one JSON response with defensive content-sniffing protection. */
    private function respond($data, int $status = 200): void
    {
        http_response_code($status);
        SecurityHeaders::json(isset($this->config['security']['cookieSecure']) ? (bool) $this->config['security']['cookieSecure'] : null);
        echo json_encode($data, JSON_UNESCAPED_SLASHES);
    }
}
