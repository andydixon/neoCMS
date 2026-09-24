<?php

namespace NeoCMS;

/** Resolves public page URIs to filesystem paths inside the document root, rejecting anything that escapes it. */
final class PagePaths
{
    public function __construct(private string $documentRoot, private int $maxManagedPages, private int $maxScannedEntries)
    {
    }

    /** Resolve an existing public URI to a canonical, in-root HTML file path. */
    public function existing(string $uri): string
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
            throw new PageNotFoundException('Invalid page path');
        }
        return $real;
    }

    /** Build a safe path for a page that may not exist yet. */
    public function newPath(string $uri): string
    {
        $path = $this->documentRoot . ltrim($uri, '/');
        if (!str_starts_with($path, $this->documentRoot)) {
            throw new \RuntimeException('Invalid page path');
        }
        $this->assertNoSymlinks($path);
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
    public function assertNoSymlinks(string $path): void
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

    /** Canonicalise a new page URI, append .html when absent, and enforce safe characters. */
    public function normaliseNewUri(string $uri): string
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

    /** Canonicalise a request URI and reject traversal or null-byte input. */
    public function normaliseUri(string $uri): string
    {
        $path = parse_url($uri, PHP_URL_PATH) ?: '/';
        $path = '/' . ltrim(str_replace('\\', '/', $path), '/');
        if (str_contains($path, '..') || str_contains($path, "\0")) {
            throw new \RuntimeException('Invalid URI');
        }
        return $path;
    }

    /** Accept an absolute HTTP(S) link or normalise a local site path. */
    public function normaliseLink(string $url): string
    {
        if (preg_match('#^https?://#i', $url)) {
            return filter_var($url, FILTER_SANITIZE_URL);
        }
        return $this->normaliseUri($url);
    }

    /** Return every public .html or .htm file while excluding the CMS application itself. */
    public function files(): array
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

    /** Convert an absolute page path back into its public, slash-separated URI. */
    public function uriFor(string $path): string
    {
        return '/' . str_replace(DIRECTORY_SEPARATOR, '/', substr($path, strlen($this->documentRoot)));
    }
    /**
     * Whether a page's local asset reference points at a real file inside the site.
     *
     * @return bool|null null for external or non-file references (http:, //, data:, #anchor), which are not checked.
     */
    public function refExists(string $pagePath, string $ref, string $basePath = ''): ?bool
    {
        $ref = trim($ref);
        if ($ref === '' || preg_match('#^([a-z][a-z0-9+.-]*:|//|\#)#i', $ref)) {
            return null;
        }
        $ref = rawurldecode((string) preg_replace('/[?#].*$/s', '', $ref));
        if ($ref === '' || str_contains($ref, "\0")) {
            return $ref === '' ? null : false;
        }
        if ($ref[0] === '/') {
            // The upload endpoint writes URLs with the site's URL prefix; strip it to get a path inside the site.
            if ($basePath !== '' && str_starts_with($ref, $basePath . '/')) {
                $ref = substr($ref, strlen($basePath));
            }
            $candidate = $this->documentRoot . ltrim($ref, '/');
        } else {
            $candidate = dirname($pagePath) . DIRECTORY_SEPARATOR . $ref;
        }
        $real = realpath($candidate);
        return $real !== false && str_starts_with($real, $this->documentRoot) && is_file($real);
    }
}
