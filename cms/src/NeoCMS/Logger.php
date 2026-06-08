<?php

namespace NeoCMS;

/** Writes simple, daily, append-only audit logs for operational traceability. */
class Logger
{
    /** Whether audit writes are enabled by configuration. */
    private $auditEnabled;

    /** Absolute directory containing the dated audit files. */
    private $logsDir;

    /** Maximum size of one active daily log before it is rotated. */
    private $maxFileBytes;

    /** Number of days for which audit logs are retained. */
    private $retentionDays;

    /**
     * Configure logging and ensure its destination exists.
     *
     * @param mixed $auditEnabled Truthy values enable audit output.
     * @param array<string, mixed> $security Security limits shared with the rest of the CMS.
     */
    public function __construct($auditEnabled, array $security = [])
    {
        $this->auditEnabled = $auditEnabled;

        // Conservative defaults prevent a forgotten audit directory from becoming its own archive service.
        $this->maxFileBytes = max(1024, (int) ($security['auditMaxFileBytes'] ?? 10 * 1024 * 1024));
        $this->retentionDays = max(1, (int) ($security['auditRetentionDays'] ?? 90));

        $this->logsDir = __DIR__ . '/../../logs';
        $this->logsDir = rtrim($this->logsDir, '/\\') . DIRECTORY_SEPARATOR;

        // Create the directory lazily so a fresh installation needs no ceremonial folder setup.
        if (!is_dir($this->logsDir)) {
            if (!mkdir($this->logsDir, 0700, true)) {
                throw new \Exception('Failed to create logs directory: ' . $this->logsDir);
            }
        }
        @chmod($this->logsDir, 0700);

        if ($this->auditEnabled) {
            $this->pruneExpiredLogs();
        }
    }

    /**
     * Append a timestamped user action to today's audit file.
     *
     * @param mixed $message Human-readable action description.
     * @param mixed $user Username responsible for the action.
     */
    public function write($message, $user): void
    {
        if (!$this->auditEnabled) {
            return;
        }

        // One file per day keeps inspection and rotation pleasantly unsurprising.
        $logFile = $this->logsDir . date('Y-m-d') . '-audit.txt';
        $this->rotateFullLog($logFile);
        $entry = sprintf(
            "[%s]\tUser: %s\t%s\n",
            date('Y-m-d H:i:s'),
            $this->normaliseLogField($user),
            $this->normaliseLogField($message)
        );

        if (file_put_contents($logFile, $entry, FILE_APPEND | LOCK_EX) === false) {
            throw new \RuntimeException('Unable to write audit log');
        }
        @chmod($logFile, 0600);
    }

    /**
     * Move a full daily log aside before appending another entry.
     *
     * @param string $logFile Absolute path to today's active log.
     */
    private function rotateFullLog(string $logFile): void
    {
        clearstatcache(true, $logFile);
        if (!is_file($logFile) || (int) filesize($logFile) < $this->maxFileBytes) {
            return;
        }

        $rotated = $this->logsDir . date('Y-m-d-His') . '-' . bin2hex(random_bytes(3)) . '-audit.txt';
        if (!rename($logFile, $rotated)) {
            throw new \RuntimeException('Unable to rotate audit log');
        }
        @chmod($rotated, 0600);
    }

    /** Remove audit files older than the configured retention period. */
    private function pruneExpiredLogs(): void
    {
        $cutoff = time() - ($this->retentionDays * 86400);
        $logFiles = glob($this->logsDir . '*-audit.txt') ?: [];

        foreach ($logFiles as $logFile) {
            if (is_file($logFile) && (int) filemtime($logFile) < $cutoff && !unlink($logFile)) {
                throw new \RuntimeException('Unable to remove expired audit log');
            }
        }
    }

    /**
     * Remove control characters that could forge extra audit entries or columns.
     *
     * @param mixed $value Value destined for one tab-separated log field.
     */
    private function normaliseLogField($value): string
    {
        $value = trim((string) $value);
        return str_replace(["\r", "\n", "\t"], [' ', ' ', ' '], $value);
    }
}
