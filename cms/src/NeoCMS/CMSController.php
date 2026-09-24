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

    /** Account registry (config accounts plus CMS-managed accounts). */
    private UserStore $users;

    /** Build the controller services and normalise all configured filesystem paths. */
    public function __construct(array $config)
    {
        $this->config = $config;
        $this->authentication = new Authentication(...UserStore::authArgs($config));
        $this->logger = new Logger($config['audit'] ?? true, $config['security'] ?? []);
        // A custom data directory is primarily useful for tests and hardened deployments.
        $dataDirectory = $config['dataDirectory'] ?? (__DIR__ . '/../../data');
        $this->store = new FileStore((string) $dataDirectory);
        $this->users = new UserStore($this->store, $config);
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
        $this->storeDraft($uri, $content);
        $this->activity('Saved draft', $uri);
        $this->respond(['message' => 'Draft saved', 'updated' => date(DATE_ATOM)]);
    }

    /** Write a private draft (within the draft quota) and record it in the drafts index. */
    private function storeDraft(string $uri, string $content): void
    {
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
        $this->assertPublishable($uri);
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
        // Pages an administrator has marked as templates are offered too.
        foreach (array_keys($this->store->read('pagetemplates')) as $uri) {
            try {
                $html = (string) file_get_contents($this->paths->existing($uri));
            } catch (PageNotFoundException) {
                continue;
            }
            $templates[] = ['id' => 'page:' . $uri, 'name' => $this->extractTitle($html) ?: $uri, 'detail' => $uri];
        }
        $this->respond($templates);
    }

    /** Delete a template file, or stop using a page as a template (the page itself is kept); administrators only. */
    private function deleteTemplateAction(): void
    {
        $this->requirePost('manage');
        $template = $this->requiredPost('template');
        if (str_starts_with($template, 'page:')) {
            $uri = $this->paths->normaliseUri(substr($template, 5));
            $this->store->update('pagetemplates', function (array $marked) use ($uri) {
                unset($marked[$uri]);
                return $marked;
            });
            $this->activity('Unmarked page as template', $uri);
            $this->respond(['message' => 'Page is no longer a template']);
            return;
        }
        $file = basename($template);
        $path = realpath($this->templatesDir . $file);
        if (!$path || !str_starts_with($path, $this->templatesDir) || !preg_match('/\.html?$/i', $file) || !unlink($path)) {
            throw new \RuntimeException('Template not found');
        }
        $this->activity('Deleted template', $file);
        $this->respond(['message' => 'Template deleted']);
    }

    /** Mark a page as a template for New Page, or remove the mark; administrators only. */
    private function setPageTemplateAction(): void
    {
        $this->requirePost('manage');
        $uri = $this->paths->normaliseUri($this->requiredPost('uri'));
        $this->paths->existing($uri);
        $on = ($_POST['value'] ?? '') === '1';
        $this->store->update('pagetemplates', function (array $marked) use ($uri, $on) {
            if ($on) {
                $marked[$uri] = ['user' => $this->user(), 'created' => date(DATE_ATOM)];
            } else {
                unset($marked[$uri]);
            }
            return $marked;
        });
        $this->activity($on ? 'Marked page as template' : 'Unmarked page as template', $uri);
        $this->respond(['message' => $on ? 'Template created. It is now available in New Page.' : 'Page is no longer a template']);
    }

    /**
     * Start a new page from a template. Nothing is written to the site: the page exists only as a private draft
     * and a pending record until it is first published, so it is not visible on the front end before then.
     */
    private function newPageAction(): void
    {
        $this->requirePost('manage');
        $name = mb_substr(trim((string) preg_replace('/\s+/u', ' ', $this->requiredPost('name'))), 0, 80);
        if ($name === '') {
            throw new \RuntimeException('A page name is required');
        }
        $template = $this->requiredPost('template');
        if (str_starts_with($template, 'page:')) {
            // A page marked as a template: it must still be marked, and still exist.
            $templateUri = $this->paths->normaliseUri(substr($template, 5));
            if (!isset($this->store->read('pagetemplates')[$templateUri])) {
                throw new \RuntimeException('Template not found');
            }
            $source = $this->paths->existing($templateUri);
        } else {
            $template = basename($template);
            $source = realpath($this->templatesDir . $template);
            if (!$source || !str_starts_with($source, $this->templatesDir)) {
                throw new \RuntimeException('Template not found');
            }
        }
        $menu = trim((string) ($_POST['menu'] ?? ''));
        if ($menu !== '' && !isset($this->store->read('menus')[$menu])) {
            throw new \RuntimeException('Navigation group not found');
        }
        if (count($this->paths->files()) + count($this->store->read('newpages')) >= $this->maxManagedPages) {
            throw new \RuntimeException('Managed page limit has been reached');
        }
        $uri = $this->uniqueUri($name);
        $html = (string) file_get_contents($source);
        $safeName = htmlspecialchars($name, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $html = (string) preg_replace_callback('#(<title\b[^>]*>).*?(</title>)#is', fn(array $m) => $m[1] . $safeName . $m[2], $html, 1);
        $this->storeDraft($uri, $html);
        $entry = ['title' => $name, 'menu' => $menu, 'template' => $template, 'user' => $this->user(), 'created' => date(DATE_ATOM)];
        $this->store->update('newpages', function (array $pending) use ($uri, $entry) {
            $pending[$uri] = $entry;
            return $pending;
        });
        $this->activity('Created page (unpublished)', $uri);
        $this->respond(['message' => 'Page created as a draft. It is not visible on the site until you publish it.', 'url' => $uri]);
    }

    /** A filename derived from the page name (about-us.html), numbered when a page or pending page already uses it. */
    private function uniqueUri(string $name): string
    {
        $ascii = function_exists('iconv') ? (string) @iconv('UTF-8', 'ASCII//TRANSLIT', $name) : $name;
        $slug = trim((string) preg_replace('/[^a-z0-9]+/', '-', strtolower($ascii)), '-') ?: 'page';
        $pending = $this->store->read('newpages');
        for ($n = 1; $n < 1000; $n++) {
            $uri = $this->paths->normaliseNewUri('/' . $slug . ($n > 1 ? '-' . $n : '') . '.html');
            if (!isset($pending[$uri]) && !file_exists($this->paths->newPath($uri))) {
                return $uri;
            }
        }
        throw new \RuntimeException('Could not find a free filename for this page');
    }

    /** The pending record for a page that has been created but never published, or null. */
    private function pendingPage(string $uri): ?array
    {
        return $this->store->read('newpages')[$this->paths->normaliseUri($uri)] ?? null;
    }

    /** Whether a page can be published or scheduled: it exists, or it is a new page waiting for its first publication. */
    private function assertPublishable(string $uri): void
    {
        try {
            $this->paths->existing($uri);
        } catch (PageNotFoundException $exception) {
            if (!$this->pendingPage($uri)) {
                throw $exception;
            }
        }
    }

    /** Throw away a new page that was never published: its pending record, draft and any scheduled publication. */
    private function discardNewPageAction(): void
    {
        $this->requirePost('manage');
        $uri = $this->paths->normaliseUri($this->requiredPost('uri'));
        if (!$this->pendingPage($uri)) {
            throw new \RuntimeException('Unpublished page not found');
        }
        $this->store->update('newpages', function (array $pending) use ($uri) {
            unset($pending[$uri]);
            return $pending;
        });
        $this->deleteDraft($uri);
        $staged = [];
        $this->store->update('schedules', function (array $jobs) use ($uri, &$staged) {
            foreach ($jobs as $id => $job) {
                if (($job['uri'] ?? '') === $uri) {
                    $staged[] = (string) $id;
                    unset($jobs[$id]);
                }
            }
            return $jobs;
        });
        foreach ($staged as $id) {
            @unlink($this->store->directory('scheduled') . basename($id) . '.html');
        }
        $this->activity('Discarded unpublished page', $uri);
        $this->respond(['message' => 'Unpublished page discarded']);
    }

    /** After a new page's first publication: add its link to the chosen menu on every page, then forget the pending record. */
    private function finishNewPage(string $uri): void
    {
        $uri = $this->paths->normaliseUri($uri);
        $pending = $this->pendingPage($uri);
        if (!$pending) {
            return;
        }
        try {
            $menu = (string) ($pending['menu'] ?? '');
            $items = null;
            if ($menu !== '') {
                $url = (string) ($this->config['basePath'] ?? '') . $uri;
                $this->store->update('menus', function (array $menus) use ($menu, $url, $pending, &$items) {
                    if (!isset($menus[$menu])) {
                        return $menus;
                    }
                    $menus[$menu]['items'] = $menus[$menu]['items'] ?? [];
                    if (!in_array($url, array_column($menus[$menu]['items'], 'url'), true)) {
                        $menus[$menu]['items'][] = ['label' => (string) $pending['title'], 'url' => $url, 'parent' => ''];
                        $menus[$menu]['updated'] = date(DATE_ATOM);
                    }
                    $items = $menus[$menu]['items'];
                    return $menus;
                });
                if ($items !== null) {
                    $this->propagateMenu($menu, $items);
                    $this->activity('Added page to menu', $uri . ' -> ' . $menu);
                }
            }
        } catch (\Throwable $exception) {
            // The page is already live; a menu problem is reported in the log rather than failing the publish.
            $this->logger->write("Adding {$uri} to its menu failed: {$exception->getMessage()}", $this->user());
        }
        $this->store->update('newpages', function (array $all) use ($uri) {
            unset($all[$uri]);
            return $all;
        });
        $this->deleteDraft($uri);
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
            $this->store->update('pagetemplates', function (array $marked) use ($sourceUri) {
                unset($marked[$sourceUri]);
                return $marked;
            });
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
        if ($operation === 'rename') {
            $this->store->update('pagetemplates', function (array $marked) use ($sourceUri, $targetUri) {
                if (isset($marked[$sourceUri])) {
                    $marked[$targetUri] = $marked[$sourceUri];
                    unset($marked[$sourceUri]);
                }
                return $marked;
            });
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
        foreach ($this->store->read('newpages') as $uri => $entry) {
            $pages[] = ['name' => $uri, 'url' => $uri, 'title' => (string) ($entry['title'] ?? ''), 'modified' => $entry['created'] ?? date(DATE_ATOM), 'draft' => true, 'pending' => true];
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
        $allNavs = !empty($_REQUEST['allNavs']);
        $all = ['content' => true, 'images' => true, 'seo' => true, 'menus' => true, 'allNavs' => $allNavs];
        $pages = [];
        $usedUploads = [];
        foreach ($uris as $uri) {
            try {
                $path = $this->paths->existing($uri);
            } catch (PageNotFoundException) {
                continue;
            }
            $html = (string) file_get_contents($path);
            $a = SiteAnalyser::analyse($html, $this->editableClass, $allNavs);
            $broken = [];
            foreach ($a['refs'] as $ref) {
                if ($this->paths->refExists($path, $ref['url'], $basePath) === false) {
                    $broken[] = $ref['url'];
                }
            }
            if (preg_match_all('#/uploads/(' . MediaTypes::nameRegex() . ')#', $html, $matches)) {
                array_push($usedUploads, ...$matches[1]);
            }
            $plan = SiteAnalyser::apply($html, $this->editableClass, $all);
            $pages[] = [
                'uri' => $this->paths->uriFor($path), 'title' => $a['title'], 'editableRegions' => $a['existingRegions'], 'proposed' => $a['proposed'],
                'images' => count($a['images']), 'missingAlt' => count(array_filter($a['images'], fn($i) => !$i['hasAlt'])),
                'navs' => $a['navs'], 'broken' => $broken, 'seoMissing' => array_keys(array_filter($a['seo'], fn($present) => !$present)),
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
        $flags = ['content' => !empty($options['content']), 'images' => !empty($options['images']), 'seo' => !empty($options['seo']), 'menus' => !empty($options['menus']), 'allNavs' => !empty($options['allNavs'])];
        if (!array_filter($flags)) {
            throw new \RuntimeException('Choose at least one kind of change');
        }
        $pages = [];
        $seed = [];
        $basePath = (string) ($this->config['basePath'] ?? '');
        $count = $this->rewritePages(function (string $html, string $uri) use ($flags, &$pages, &$seed, $basePath) {
            $result = SiteAnalyser::apply($html, $this->editableClass, $flags);
            if ($result) {
                $pages[$uri] = $result['changes'];
                // The first page seen supplies a menu's items; links become site paths so they work from any folder.
                foreach ($result['menus'] as $name => $links) {
                    $seed[$name] ??= array_map(function (array $link) use ($uri, $basePath) {
                        $path = ContentDom::resolveLink($link['url'], $uri);
                        return ['label' => $link['label'], 'url' => $path === null ? $link['url'] : $basePath . $path, 'parent' => $link['parent']];
                    }, $links);
                }
            }
            return $result['html'] ?? null;
        }, 'Before auto-tagging', $uris);
        if ($seed) {
            // A menu someone has already saved is never replaced by a scan.
            $this->store->update('menus', function (array $menus) use ($seed) {
                foreach ($seed as $name => $items) {
                    $menus[$name] ??= ['items' => $items, 'updated' => date(DATE_ATOM)];
                }
                return $menus;
            });
            $this->activity('Detected navigation menus', implode(', ', array_keys($seed)));
        }
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

    /** Remove a saved menu. Pages keep the navigation they already contain; only the stored definition goes. */
    private function deleteMenuAction(): void
    {
        $this->requirePost('manage');
        $name = preg_replace('/[^a-zA-Z0-9_-]/', '', $this->requiredPost('name'));
        $found = false;
        $this->store->update('menus', function (array $menus) use ($name, &$found) {
            $found = isset($menus[$name]);
            unset($menus[$name]);
            return $menus;
        });
        if (!$found) {
            throw new \RuntimeException('Menu not found');
        }
        $this->activity('Deleted menu', $name);
        $this->respond(['message' => "Menu '{$name}' deleted. Pages keep their current navigation."]);
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
        // Menus come from navigation in the site's pages; the CMS edits and renames them but never creates them.
        if (!isset($this->store->read('menus')[$name])) {
            throw new \RuntimeException("Menu not found. New menus are added to the pages by the web developer, then found with the navigation scan.");
        }
        $title = mb_substr(trim((string) ($_POST['title'] ?? '')), 0, 60);
        $this->store->update('menus', function (array $menus) use ($name, $clean, $title) {
            $entry = ['items' => $clean, 'updated' => date(DATE_ATOM)] + $menus[$name];
            if ($title !== '') {
                $entry['title'] = $title;
            } else {
                unset($entry['title']);
            }
            $menus[$name] = $entry;
            return $menus;
        });
        $html = ContentDom::renderMenu($name, $clean);
        $updated = $this->propagateMenu($name, $clean);
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
                'category' => MediaTypes::categoryForName(basename($file)), 'ext' => strtolower(pathinfo($file, PATHINFO_EXTENSION)),
                'original' => $metadata[basename($file)]['original'] ?? '',
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
            $metadata[$name] = $entry + ($metadata[$name] ?? []);
            return $metadata;
        });
        $this->activity(MediaTypes::categoryForName($name) === 'imagery' ? 'Updated image alt text' : 'Updated media description', $name);
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
        foreach ([(string) ($this->config['dataDirectory'] ?? (__DIR__ . '/../../data')), dirname(__DIR__, 3) . '/uploads'] as $directory) {
            if (!is_dir($directory) || !is_writable($directory)) {
                $problems[] = $directory . ' is not writable';
            }
        }
        $notices = [];
        $dataPath = realpath($this->config['dataDirectory'] ?? (__DIR__ . '/../../data'));
        $sitePath = realpath((string) ($this->config['siteRoot'] ?? dirname(__DIR__, 3)));
        if ($this->authentication->can('manage') && $dataPath !== false && $sitePath !== false
            && str_starts_with(str_replace('\\', '/', $dataPath) . '/', rtrim(str_replace('\\', '/', $sitePath), '/') . '/')) {
            $notices[] = 'Account and page data is stored inside the web root (cms/data). For a live site, set dataDirectory in config.local.php to a folder outside the web root so it can never be served, even if the web server ignores the .htaccess rules.';
        }
        $this->respond([
            'notices' => $notices,
            'deleted' => $this->authentication->can('publish') ? $this->deletedPages() : [],
            'user' => $this->user(), 'role' => $this->authentication->getRole(),
            'permissions' => ['draft' => $this->authentication->can('draft'), 'publish' => $this->authentication->can('publish'), 'schedule' => $this->authentication->can('schedule'), 'manage' => $this->authentication->can('manage')],
            'drafts' => $drafts, 'schedules' => $schedules, 'activity' => $activity, 'problems' => $problems,
        ]);
    }

    /** Pages whose newest revision is a "Before delete" snapshot and whose file is gone, newest deletion first. */
    private function deletedPages(): array
    {
        $latest = [];
        foreach ($this->store->read('revisions') as $revision) {
            $uri = $revision['uri'] ?? '';
            if (!isset($latest[$uri]) || self::revisionTime($revision) > self::revisionTime($latest[$uri])) {
                $latest[$uri] = $revision;
            }
        }
        $deleted = [];
        foreach ($latest as $uri => $revision) {
            if (($revision['reason'] ?? '') !== 'Before delete') {
                continue;
            }
            try {
                $this->paths->existing($uri);
            } catch (PageNotFoundException) {
                $deleted[] = $revision;
            } catch (\Throwable) {
            }
        }
        usort($deleted, fn(array $a, array $b) => self::revisionTime($b) <=> self::revisionTime($a));
        return $deleted;
    }

    /** The signed-in user's account, the role descriptions, and (administrators only) every account. Never includes hashes or tokens. */
    private function usersAction(): void
    {
        $me = $this->user();
        $profile = $this->users->profile($me);
        $this->respond([
            'me' => ['username' => $me, 'name' => $profile['name'], 'email' => $profile['email'], 'role' => $this->authentication->getRole(), 'managed' => $profile['managed']],
            'roles' => UserStore::ROLE_INFO,
            'users' => $this->authentication->can('manage') ? $this->users->accounts() : [],
        ]);
    }

    /** Update the signed-in user's own display name and email; changing the email needs the current password. */
    private function saveProfileAction(): void
    {
        $this->requirePost();
        $me = $this->user();
        $current = $this->users->profile($me);
        $name = $this->requiredPost('name');
        $email = is_string($_POST['email'] ?? null) ? trim($_POST['email']) : '';
        if (strtolower($email) !== strtolower($current['email'])) {
            $this->confirmPassword('current_password');
        }
        $this->users->setProfile($me, $name, $email);
        $this->activity('Updated own account details', $me);
        $this->respond(['message' => 'Account updated', 'name' => $this->users->profile($me)['name']]);
    }

    /** Change the signed-in user's own password. The current password is required and other sessions end. */
    private function changePasswordAction(): void
    {
        $this->requirePost();
        $me = $this->user();
        if ($this->users->isManaged($me)) {
            throw new \RuntimeException('This password is managed in config.local.php');
        }
        $new = is_string($_POST['new_password'] ?? null) ? $_POST['new_password'] : '';
        if (!hash_equals($new, is_string($_POST['confirm_password'] ?? null) ? $_POST['confirm_password'] : "\0")) {
            throw new \RuntimeException('The new password and confirmation do not match');
        }
        $this->confirmPassword('current_password');
        $hash = $this->users->setPassword($me, $new);
        $this->authentication->renewSession($hash);
        $this->activity('Changed own password', $me);
        $this->respond(['message' => 'Password changed. Your other sessions have been signed out.']);
    }

    /** Add a CMS account with a password, or edit one (blank password keeps it). Administrators only, with password confirmation. */
    private function saveUserAction(): void
    {
        $this->requirePost('manage');
        $this->confirmPassword('confirm_password');
        $existing = is_string($_POST['existing'] ?? null) ? trim($_POST['existing']) : '';
        $name = $this->requiredPost('name');
        $email = is_string($_POST['email'] ?? null) ? $_POST['email'] : '';
        $role = $this->requiredPost('role');
        $password = is_string($_POST['password'] ?? null) ? $_POST['password'] : '';
        if ($existing === '') {
            $username = $this->requiredPost('username');
            $this->users->create($username, $name, $email, $role, $password);
            $this->activity('Added user', $username . ' (' . $role . ')');
            $this->respond(['message' => 'User added']);
            return;
        }
        $this->refuseSelf($existing);
        $before = null;
        foreach ($this->users->accounts() as $account) {
            if ($account['username'] === $existing) {
                $before = $account;
            }
        }
        $this->users->update($existing, $name, $email, $role, $password);
        $this->activity('Updated user', $existing);
        if ($before && $before['role'] !== $role) {
            $this->activity('Changed user role', $existing . ': ' . $before['role'] . ' -> ' . $role);
        }
        if ($password !== '') {
            $this->activity('Reset user password', $existing);
        }
        $this->respond(['message' => 'User updated']);
    }

    /** Create or re-issue a one-time invitation link (shown once; only its hash is stored). */
    private function inviteUserAction(): void
    {
        $this->requirePost('manage');
        $this->confirmPassword('confirm_password');
        $existing = is_string($_POST['existing'] ?? null) ? trim($_POST['existing']) : '';
        [$username, $token] = $this->users->invite($this->requiredPost('name'), $this->requiredPost('email'), $this->requiredPost('role'), $existing);
        $this->activity($existing === '' ? 'Invited user' : 'Re-issued invitation', $username);
        $this->respond([
            'message' => 'Invitation created. Copy the link now; it is shown only once and expires in 7 days.',
            'link' => (string) ($this->config['basePath'] ?? '') . '/cms/login/?invite=' . $token,
            'username' => $username,
        ]);
    }

    /** Block or unblock a CMS account; a blocked account is signed out on its next request. */
    private function blockUserAction(): void
    {
        $this->requirePost('manage');
        $this->confirmPassword('confirm_password');
        $username = $this->requiredPost('username');
        $this->refuseSelf($username);
        $blocked = ($_POST['blocked'] ?? '') === '1';
        $this->users->setBlocked($username, $blocked);
        $this->activity($blocked ? 'Blocked user' : 'Unblocked user', $username);
        $this->respond(['message' => $blocked ? 'User blocked' : 'User unblocked']);
    }

    /** Delete a CMS account. */
    private function deleteUserAction(): void
    {
        $this->requirePost('manage');
        $this->confirmPassword('confirm_password');
        $username = $this->requiredPost('username');
        $this->refuseSelf($username);
        $this->users->delete($username);
        $this->activity('Deleted user', $username);
        $this->respond(['message' => 'User deleted']);
    }

    /** Stop administrators locking themselves out through the account-management actions. */
    private function refuseSelf(string $username): void
    {
        if ($username === $this->user()) {
            throw new \RuntimeException('You cannot do that to your own account. Use My account for your own details.');
        }
    }

    /** Require the signed-in user's own password before a sensitive account action; failures are rate limited and audited. */
    private function confirmPassword(string $field): void
    {
        $address = (string) ($_SERVER['REMOTE_ADDR'] ?? 'unknown');
        $key = 'reauth:' . $this->user();
        $limiter = new LoginRateLimiter((string) ($this->config['dataDirectory'] ?? (__DIR__ . '/../../data')), $this->config['security'] ?? []);
        if (($wait = $limiter->retryAfter($address, $key)) > 0) {
            throw new \RuntimeException("Too many incorrect passwords. Try again in {$wait} seconds.");
        }
        $password = is_string($_POST[$field] ?? null) ? $_POST[$field] : '';
        if ($password === '' || strlen($password) > 4096 || !$this->authentication->verifyCurrentPassword($password)) {
            $limiter->recordFailure($address, $key);
            $this->activity('Password confirmation failed', 'users');
            throw new \RuntimeException('Your password is incorrect');
        }
        $limiter->clear($address, $key);
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
        $isNew = false;
        try {
            $path = $this->paths->existing($uri);
        } catch (PageNotFoundException $exception) {
            // A new page has no file until its first publication.
            if (!$this->pendingPage($uri)) {
                throw $exception;
            }
            if (count($this->paths->files()) >= $this->maxManagedPages) {
                throw new \RuntimeException('Managed page limit has been reached');
            }
            $path = $this->paths->newPath($this->paths->normaliseNewUri($uri));
            $this->ensureParentDirectory($path);
            $this->paths->assertNoSymlinks($path);
            $isNew = true;
        }
        $old = $isNew ? '' : (string) file_get_contents($path);
        if (!$isNew && $old !== $content) {
            $this->createRevision($uri, $old, $reason);
        }
        $this->atomicWrite($path, $content);
        $this->captureSharedBlocks($content);
        $this->activity($reason, $uri);
        if ($isNew) {
            $this->finishNewPage($uri);
        }
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
    private function propagateMenu(string $name, array $items): int
    {
        $basePath = (string) ($this->config['basePath'] ?? '');
        return $this->rewritePages(
            fn(string $html, string $uri) => ContentDom::withMenu($html, $name, $items, $uri, $basePath),
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
            if (preg_match_all('#/uploads/' . MediaTypes::nameRegex() . '#', $html, $matches)) {
                foreach ($matches[0] as $url) {
                    $uses[$url] = ($uses[$url] ?? 0) + 1;
                }
            }
        }
        return $uses;
    }

    /** Validate that a filename belongs to the generated media namespace created by uploads. */
    private function managedMediaName(string $name): string
    {
        $name = basename($name);
        if (!$this->isManagedMediaName($name)) {
            throw new \RuntimeException('Invalid media filename');
        }
        return $name;
    }

    /** Return whether a filename is a NeoCMS-generated media name with an allowed extension. */
    private function isManagedMediaName(string $name): bool
    {
        return MediaTypes::isManagedName($name);
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
