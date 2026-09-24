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
    /** Dashboard activity feed and audit-log writer. */
    private Activity $activity;
    /** Absolute directory containing page templates. */
    private string $templatesDir;
    /** Public-page URI/path resolution confined to the document root. */
    private PagePaths $paths;
    /** Validated CSS class used to discover and bind editable content regions. */
    private string $editableClass;
    /** Maximum complete HTML document size accepted from an authenticated author. */
    private int $maxContentBytes;
    /** Maximum encoded HTTP request size accepted by the controller. */
    private int $maxRequestBytes;
    /** Safety ceiling for recursive public-page scans. */
    private int $maxManagedPages;
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
        $this->activity = new Activity($this->store, $this->logger);
        $templatesDir = realpath(__DIR__ . '/../../templates');
        $documentRoot = realpath((string) ($config['siteRoot'] ?? $_SERVER['DOCUMENT_ROOT'] ?? ''));
        if ($templatesDir === false || $documentRoot === false) {
            throw new \RuntimeException('CMS filesystem paths are unavailable');
        }
        $this->templatesDir = rtrim($templatesDir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
        $security = is_array($config['security'] ?? null) ? $config['security'] : [];
        $this->maxContentBytes = max(1024, (int) ($security['maxContentBytes'] ?? 5 * 1024 * 1024));
        $this->maxRequestBytes = max($this->maxContentBytes, (int) ($security['maxRequestBytes'] ?? 6 * 1024 * 1024));
        $this->maxManagedPages = max(1, (int) ($security['maxManagedPages'] ?? 5000));
        $this->paths = new PagePaths(
            rtrim($documentRoot, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR,
            $this->maxManagedPages,
            max($this->maxManagedPages, (int) ($security['maxScannedEntries'] ?? 20000))
        );
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
            // A failure here must not break unrelated requests; the cron worker will retry.
            try {
                $this->publishDueJobs();
            } catch (\Throwable $exception) {
                $this->logger->write("Opportunistic schedule run failed: {$exception->getMessage()}", $this->user());
            }
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
        $draftName = hash('sha256', $this->paths->normaliseUri($uri)) . '.html';
        $draftPath = $this->store->directory('drafts') . $draftName;
        $draftBytes = $this->directoryBytes('drafts') - (is_file($draftPath) ? (int) filesize($draftPath) : 0);
        if ($draftBytes + strlen($content) > $this->maxDraftBytes) {
            throw new \RuntimeException('Draft storage quota has been reached');
        }
        $this->store->writePrivateFile('drafts', $draftName, $content);
        // Draft filenames are URI hashes, while this index keeps their human-readable metadata.
        $draftUri = $this->paths->normaliseUri($uri);
        $entry = ['updated' => date(DATE_ATOM), 'user' => $this->user()];
        $this->store->update('drafts', function (array $drafts) use ($draftUri, $entry) {
            $drafts[$draftUri] = $entry;
            return $drafts;
        });
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
        $this->paths->existing($uri);
        $publishAt = new \DateTimeImmutable($this->requiredPost('publish_at'));
        if ($publishAt <= new \DateTimeImmutable()) {
            throw new \RuntimeException('Publish time must be in the future');
        }
        // Store bulky HTML separately so the JSON schedule index remains easy to inspect.
        $id = bin2hex(random_bytes(10));
        $this->store->update('schedules', function (array $jobs) use ($id, $uri, $content, $publishAt) {
            if (count($jobs) >= $this->maxSchedules || $this->directoryBytes('scheduled') + strlen($content) > $this->maxScheduledBytes) {
                throw new \RuntimeException('Scheduled publication quota has been reached');
            }
            $this->store->writePrivateFile('scheduled', $id . '.html', $content);
            $jobs[$id] = ['uri' => $this->paths->normaliseUri($uri), 'publish_at' => $publishAt->format(DATE_ATOM), 'user' => $this->user()];
            return $jobs;
        });
        $this->activity('Scheduled publication', $uri);
        $this->respond(['message' => 'Page scheduled', 'id' => $id, 'publish_at' => $publishAt->format(DATE_ATOM)]);
    }

    /** Cancel one scheduled publication and remove its staged HTML file. */
    private function cancelScheduleAction(): void
    {
        $this->requirePost('schedule');
        $id = $this->requiredPost('id');
        $removed = null;
        $this->store->update('schedules', function (array $jobs) use ($id, &$removed) {
            $removed = $jobs[$id] ?? null;
            unset($jobs[$id]);
            return $jobs;
        });
        @unlink($this->store->directory('scheduled') . basename($id) . '.html');
        if ($removed) {
            $this->activity('Cancelled scheduled publication', ($removed['uri'] ?? '') . ' (was due ' . ($removed['publish_at'] ?? '?') . ', scheduled by ' . ($removed['user'] ?? 'unknown') . ')');
        }
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
        $uri = $this->paths->normaliseNewUri($this->requiredPost('filename'));
        $template = basename($this->requiredPost('template'));
        $source = realpath($this->templatesDir . $template);
        if (!$source || !str_starts_with($source, $this->templatesDir)) {
            throw new \RuntimeException('Template not found');
        }
        if (count($this->paths->files()) >= $this->maxManagedPages) {
            throw new \RuntimeException('Managed page limit has been reached');
        }
        $destination = $this->paths->newPath($uri);
        if (file_exists($destination)) {
            throw new \RuntimeException('Page already exists');
        }
        $this->ensureParentDirectory($destination);
        $this->paths->assertNoSymlinks($destination);
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
        $sourceUri = $this->paths->normaliseUri($this->requiredPost('uri'));
        $source = $this->paths->existing($sourceUri);

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

        $targetUri = $this->paths->normaliseNewUri($this->requiredPost('target'));
        $target = $this->paths->newPath($targetUri);
        if (file_exists($target)) {
            throw new \RuntimeException('Target page already exists');
        }
        $this->ensureParentDirectory($target);
        $this->paths->assertNoSymlinks($target);
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
        foreach ($this->paths->files() as $path) {
            $uri = $this->paths->uriFor($path);
            $html = (string) file_get_contents($path);
            if (!ContentDom::hasClass($html, $this->editableClass)) {
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
        $uri = $this->paths->normaliseUri($this->requiredRequest('uri'));
        $items = array_values(array_filter($this->store->read('revisions'), fn(array $item) => $item['uri'] === $uri));
        usort($items, fn(array $a, array $b) => self::revisionTime($b) <=> self::revisionTime($a));
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
        } catch (PageNotFoundException) {
            // A missing target is expected when restoring the revision of a deleted page.
            $destination = $this->paths->newPath($this->paths->normaliseNewUri($revision['uri']));
            $this->ensureParentDirectory($destination);
            $this->paths->assertNoSymlinks($destination);
            if (file_put_contents($destination, $content, LOCK_EX) === false) {
                throw new \RuntimeException('Unable to restore deleted page');
            }
            $this->activity('Restored deleted page', $revision['uri']);
        }
        $this->respond(['message' => 'Revision restored', 'url' => $revision['uri']]);
    }

    /** List every managed page and uploaded image so the browser can analyse them in small, countable batches. */
    private function listSitePagesAction(): void
    {
        $this->requireCapability('manage');
        $uploads = [];
        foreach (glob(dirname(__DIR__, 3) . '/uploads/*') ?: [] as $file) {
            if (is_file($file) && $this->isManagedMediaName(basename($file))) {
                $uploads[] = basename($file);
            }
        }
        $pages = array_map(fn(string $path) => $this->paths->uriFor($path), $this->paths->files());
        sort($pages);
        $this->respond(['pages' => $pages, 'uploads' => $uploads]);
    }

    /** Report what a site scan would change for one batch of pages: regions, images, references, and SEO gaps. */
    private function analyseSitePagesAction(): void
    {
        $this->requireCapability('manage');
        $uris = $this->uriBatch($this->requiredRequest('uris'));
        $basePath = (string) ($this->config['basePath'] ?? '');
        $all = ['content' => true, 'images' => true, 'seo' => true];
        $pages = [];
        $usedUploads = [];
        foreach ($uris as $uri) {
            try {
                $path = $this->paths->existing($uri);
            } catch (PageNotFoundException) {
                continue;
            }
            $html = (string) file_get_contents($path);
            $a = SiteAnalyser::analyse($html, $this->editableClass);
            $broken = [];
            foreach ($a['refs'] as $ref) {
                if ($this->paths->refExists($path, $ref['url'], $basePath) === false) {
                    $broken[] = $ref['url'];
                }
            }
            if (preg_match_all('#/uploads/([a-f0-9]{32}\.(?:jpg|png|gif|webp))#', $html, $matches)) {
                array_push($usedUploads, ...$matches[1]);
            }
            $plan = SiteAnalyser::apply($html, $this->editableClass, $all);
            $pages[] = [
                'uri' => $this->paths->uriFor($path), 'title' => $a['title'], 'editableRegions' => $a['existingRegions'], 'proposed' => $a['proposed'],
                'images' => count($a['images']), 'missingAlt' => count(array_filter($a['images'], fn($i) => !$i['hasAlt'])),
                'broken' => $broken, 'seoMissing' => array_keys(array_filter($a['seo'], fn($present) => !$present)),
                'changes' => $plan['changes'] ?? [],
            ];
        }
        $this->respond(['pages' => $pages, 'usedUploads' => array_values(array_unique($usedUploads))]);
    }

    /** Decode a JSON list of page URIs, normalising each and bounding the batch size. */
    private function uriBatch(string $json): array
    {
        $uris = json_decode($json, true);
        if (!is_array($uris) || !$uris) {
            throw new \RuntimeException('Select at least one page');
        }
        if (count($uris) > 100) {
            throw new \RuntimeException('Too many pages in one request');
        }
        return array_values(array_unique(array_map(fn($uri) => $this->paths->normaliseUri((string) $uri), $uris)));
    }
    /** Add editing markers (content regions, image markers, missing SEO tags) to the selected pages. */
    private function applySiteTaggingAction(): void
    {
        $this->requirePost('manage');
        $uris = $this->uriBatch($this->limitedPost('uris', 1024 * 1024));
        $options = json_decode($this->limitedPost('options', 4096), true);
        if (!is_array($options)) {
            throw new \RuntimeException('Choose at least one kind of change');
        }
        $flags = ['content' => !empty($options['content']), 'images' => !empty($options['images']), 'seo' => !empty($options['seo'])];
        if (!array_filter($flags)) {
            throw new \RuntimeException('Choose at least one kind of change');
        }
        $pages = [];
        $count = $this->rewritePages(function (string $html, string $uri) use ($flags, &$pages) {
            $result = SiteAnalyser::apply($html, $this->editableClass, $flags);
            if ($result) {
                $pages[$uri] = $result['changes'];
            }
            return $result['html'] ?? null;
        }, 'Before auto-tagging', $uris);
        $this->activity('Auto-tagged pages', $count . ' page(s)');
        $this->respond(['message' => "Updated {$count} page(s). Each has a revision to restore.", 'updated_pages' => $count, 'pages' => $pages]);
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
        $entry = ['content' => $content, 'updated' => date(DATE_ATOM), 'user' => $this->user()];
        $this->store->update('shared', function (array $shared) use ($key, $entry) {
            $shared[$key] = $entry;
            return $shared;
        });
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
            $clean[] = ['label' => trim((string) ($item['label'] ?? $item['url'])), 'url' => $this->paths->normaliseLink((string) $item['url']), 'parent' => trim((string) ($item['parent'] ?? ''))];
        }
        $this->store->update('menus', function (array $menus) use ($name, $clean) {
            $menus[$name] = ['items' => $clean, 'updated' => date(DATE_ATOM)];
            return $menus;
        });
        $html = ContentDom::renderMenu($name, $clean);
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
                'name' => basename($file), 'url' => ($this->config['basePath'] ?? '') . $url, 'size' => filesize($file),
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
        $alt = is_string($_POST['alt'] ?? null) ? trim($_POST['alt']) : '';
        $entry = ['alt' => substr(preg_replace('/[\x00-\x1F\x7F]/u', '', $alt) ?? '', 0, 500)];
        $this->store->update('media', function (array $metadata) use ($name, $entry) {
            $metadata[$name] = $entry;
            return $metadata;
        });
        $this->activity('Updated image alt text', $name);
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
        $this->store->update('media', function (array $metadata) use ($name) {
            unset($metadata[$name]);
            return $metadata;
        });
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
        $path = $this->paths->existing($uri);
        $old = (string) file_get_contents($path);
        if ($old !== $content) {
            $this->createRevision($uri, $old, $reason);
        }
        $this->atomicWrite($path, $content);
        $this->captureSharedBlocks($content);
        $this->activity($reason, $uri);
    }

    /** Write beside the destination and rename, so a public page is never served half-written. */
    private function atomicWrite(string $path, string $content): void
    {
        $temporary = $path . '.neo-' . bin2hex(random_bytes(5));
        if (file_put_contents($temporary, $content, LOCK_EX) === false || !rename($temporary, $path)) {
            @unlink($temporary);
            throw new \RuntimeException('Failed to publish page');
        }
    }

    /** Save an immutable HTML snapshot and add its searchable metadata to the revision index. */
    private function createRevision(string $uri, string $content, string $reason): void
    {
        $id = date('YmdHis') . '-' . bin2hex(random_bytes(5));
        $this->store->writePrivateFile('revisions', $id . '.html', $content);
        $entry = ['id' => $id, 'uri' => $this->paths->normaliseUri($uri), 'created' => date(DATE_ATOM), 'ts' => microtime(true), 'user' => $this->user(), 'reason' => $reason];
        $remove = [];
        $this->store->update('revisions', function (array $index) use ($id, $entry, &$remove) {
            $index[$id] = $entry;
            $remove = $this->pruneRevisions($index);
            return $index;
        });
        $directory = $this->store->directory('revisions');
        foreach ($remove as $removeId) {
            @unlink($directory . basename($removeId) . '.html');
        }
    }

    /** Sortable creation time of a revision index entry. */
    private static function revisionTime(array $revision): float
    {
        return (float) ($revision['ts'] ?? strtotime((string) ($revision['created'] ?? '')));
    }

    /** Apply per-page, global-count, and aggregate-byte retention limits to revisions. */
    private function pruneRevisions(array &$index): array
    {
        // Newest first by microsecond 'ts'; 'created' has one-second resolution, so sorting on it alone could prune
        // a revision made in the same second as older ones. Entries written before 'ts' existed fall back to 'created'.
        uasort($index, static fn(array $a, array $b): int => self::revisionTime($b) <=> self::revisionTime($a));
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
        $isDue = static fn(array $job): bool => ($job['status'] ?? 'pending') === 'pending' && strtotime($job['publish_at'] ?? '') <= time();
        // Cheap unlocked check first: most requests have nothing due, so they should not contend for the lock.
        if (!array_filter($this->store->read('schedules'), $isDue)) {
            return 0;
        }

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
            $published = [];
            $failed = [];
            foreach (array_filter($this->store->read('schedules'), $isDue) as $id => $job) {
                $path = $this->store->directory('scheduled') . basename((string) $id) . '.html';
                try {
                    if (!is_file($path)) {
                        throw new \RuntimeException('Staged publication content is missing');
                    }
                    $this->publishContent((string) ($job['uri'] ?? ''), (string) file_get_contents($path), 'Scheduled publication');
                    unlink($path);
                    $published[] = $id;
                } catch (\Throwable $exception) {
                    $failed[] = $id;
                    $this->activity('Scheduled publication failed', ($job['uri'] ?? '') . ': ' . $exception->getMessage(), (string) ($job['user'] ?? 'scheduler'));
                }
            }
            // Apply outcomes to a fresh read so jobs scheduled or cancelled meanwhile are not lost.
            $this->store->update('schedules', function (array $jobs) use ($published, $failed) {
                foreach ($published as $id) {
                    unset($jobs[$id]);
                }
                foreach ($failed as $id) {
                    if (isset($jobs[$id])) {
                        $jobs[$id]['status'] = 'failed';
                        $jobs[$id]['failed_at'] = date(DATE_ATOM);
                        $jobs[$id]['error'] = 'Scheduled publication failed';
                    }
                }
                return $jobs;
            });
            return count($published);
        } finally {
            flock($lock, LOCK_UN);
            fclose($lock);
        }
    }

    /** Capture shared-region values from a newly published page into the central registry. */
    private function captureSharedBlocks(string $html): void
    {
        $found = [];
        foreach (ContentDom::sharedBlocks($html) as $key => $content) {
            $found[$key] = ['content' => $content, 'updated' => date(DATE_ATOM), 'user' => $this->user()];
        }
        if ($found) {
            $this->store->update('shared', fn(array $shared) => array_replace($shared, $found));
        }
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
        return $this->rewritePages(
            fn(string $html) => ContentDom::withShared($html, $key, $content),
            'Before shared content update'
        );
    }

    /** Replace matching generated menus across public pages, revisioning each page first. */
    private function propagateMenu(string $name, string $menuHtml): int
    {
        return $this->rewritePages(
            fn(string $html) => ContentDom::withMenu($html, $name, $menuHtml),
            'Before menu update'
        );
    }

    /**
     * Apply a page transform site-wide: revision each changed page, then replace it atomically.
     *
     * @param callable $transform Receives page HTML and URI; returns new HTML, or null to leave the page alone.
     * @param array|null $onlyUris Restrict the pass to these page URIs; null means every page.
     * @return int Number of changed pages.
     */
    private function rewritePages(callable $transform, string $reason, ?array $onlyUris = null): int
    {
        $count = 0;
        // A URI list is resolved one page at a time, so a small batch does not rescan the whole document root.
        if ($onlyUris !== null) {
            $paths = [];
            foreach ($onlyUris as $listed) {
                try {
                    $paths[] = $this->paths->existing($listed);
                } catch (PageNotFoundException) {
                    // A page deleted since it was listed is simply skipped.
                }
            }
        }
        foreach ($onlyUris !== null ? $paths : $this->paths->files() as $path) {
            $uri = $this->paths->uriFor($path);
            $html = (string) file_get_contents($path);
            $changed = $transform($html, $uri);
            if ($changed === null) {
                continue;
            }
            $this->createRevision($this->paths->uriFor($path), $html, $reason);
            $this->atomicWrite($path, $changed);
            $count++;
        }
        return $count;
    }

    /** Derive the private draft filename from a stable hash of its public URI. */
    private function draftPath(string $uri): string
    {
        return $this->store->directory('drafts') . hash('sha256', $this->paths->normaliseUri($uri)) . '.html';
    }

    /** Remove a page's draft HTML and its dashboard metadata entry. */
    private function deleteDraft(string $uri): void
    {
        @unlink($this->draftPath($uri));
        $draftUri = $this->paths->normaliseUri($uri);
        $this->store->update('drafts', function (array $drafts) use ($draftUri) {
            unset($drafts[$draftUri]);
            return $drafts;
        });
    }

    /** Count all managed upload references in one pass across the public pages. */
    private function mediaUsageCounts(): array
    {
        $uses = [];
        foreach ($this->paths->files() as $path) {
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

    /** Extract a plain-text document title for the page picker. */
    private function extractTitle(string $html): string
    {
        return preg_match('/<title[^>]*>(.*?)<\/title>/is', $html, $match) ? trim(html_entity_decode(strip_tags($match[1]), ENT_QUOTES | ENT_HTML5, 'UTF-8')) : 'Untitled';
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
        if ($capability) {
            $this->requireCapability($capability);
        }
    }

    /** Refuse the request unless the current role includes the capability. */
    private function requireCapability(string $capability): void
    {
        if (!$this->authentication->can($capability)) {
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
    private function activity(string $action, string $target, ?string $user = null): void
    {
        $this->activity->record($user ?? $this->user(), $action, $target);
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
