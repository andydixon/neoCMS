<?php

namespace NeoCMS;

/**
 * Persists NeoCMS metadata as human-readable JSON and provides managed subdirectories.
 *
 * Public pages remain ordinary HTML files. This store contains only the supporting cast: drafts,
 * revisions, schedules, menus, media metadata, and activity. No database server need feel left out.
 */
final class FileStore
{
    /** Absolute data-directory path, always ending with the platform separator. */
    private string $root;

    /**
     * Prepare the configured data directory.
     *
     * @param string $root Absolute or project-relative storage path.
     */
    public function __construct(string $root)
    {
        $this->root = rtrim($root, '/\\') . DIRECTORY_SEPARATOR;
        $this->ensureDirectory($this->root);
    }

    /**
     * Read a named JSON document, returning the supplied default when it does not yet exist.
     * Invalid JSON is treated as absent data so callers always receive the promised array shape.
     *
     * @param string $name Logical document name without the .json suffix.
     * @param array $default Value returned for a missing or unreadable document.
     * @return array Decoded document contents.
     */
    public function read(string $name, array $default = []): array
    {
        $path = $this->jsonPath($name);
        if (!is_file($path)) {
            return $default;
        }

        $value = json_decode((string) file_get_contents($path), true);
        return is_array($value) ? $value : $default;
    }

    /**
     * Atomically replace a named JSON document.
     *
     * Data is written to a temporary neighbour and renamed only after the write succeeds. Readers
     * therefore see either the old document or the complete new one, never half a JSON sandwich.
     *
     * @param string $name Logical document name without the .json suffix.
     * @param array $value Serializable document contents.
     */
    public function write(string $name, array $value): void
    {
        $path = $this->jsonPath($name);
        $temporary = $path . '.' . bin2hex(random_bytes(6)) . '.tmp';
        $json = json_encode($value, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        if ($json === false || file_put_contents($temporary, $json . "\n", LOCK_EX) === false) {
            throw new \RuntimeException('Unable to write CMS data');
        }
        @chmod($temporary, 0600);
        if (!rename($temporary, $path)) {
            @unlink($temporary);
            throw new \RuntimeException('Unable to replace CMS data');
        }
        @chmod($path, 0600);
    }

    /** Atomically write a private content file beneath a managed subdirectory. */
    public function writePrivateFile(string $directory, string $filename, string $content): string
    {
        $target = $this->directory($directory) . basename($filename);
        $temporary = $target . '.' . bin2hex(random_bytes(6)) . '.tmp';
        if (file_put_contents($temporary, $content, LOCK_EX) === false) {
            throw new \RuntimeException('Unable to write private CMS file');
        }
        @chmod($temporary, 0600);
        if (!rename($temporary, $target)) {
            @unlink($temporary);
            throw new \RuntimeException('Unable to replace private CMS file');
        }
        @chmod($target, 0600);
        return $target;
    }

    /**
     * Return a managed subdirectory, creating it when first required.
     *
     * @param string $name Relative directory name such as drafts or revisions.
     */
    public function directory(string $name): string
    {
        $path = $this->root . trim($name, '/\\') . DIRECTORY_SEPARATOR;
        $this->ensureDirectory($path);
        return $path;
    }

    /** Convert a logical document name into a safe JSON path beneath the data root. */
    private function jsonPath(string $name): string
    {
        return $this->root . preg_replace('/[^a-zA-Z0-9_.-]/', '', $name) . '.json';
    }

    /** Create a directory recursively or fail with a useful application-level exception. */
    private function ensureDirectory(string $path): void
    {
        if (!is_dir($path) && !mkdir($path, 0700, true) && !is_dir($path)) {
            throw new \RuntimeException('Unable to create CMS data directory');
        }
        @chmod($path, 0700);
    }
}
