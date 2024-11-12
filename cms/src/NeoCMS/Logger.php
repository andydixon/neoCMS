<?php
namespace NeoCMS;

class Logger
{
    private $auditEnabled;
    private $logsDir;

    public function __construct($auditEnabled)
    {
        $this->auditEnabled = $auditEnabled;

        $this->logsDir = __DIR__ . '/../../logs';
        $this->logsDir = rtrim($this->logsDir, '/\\') . DIRECTORY_SEPARATOR;

        // Ensure the logs directory exists
        if (!is_dir($this->logsDir)) {
            if (!mkdir($this->logsDir, 0755, true)) {
                throw new \Exception('Failed to create logs directory: ' . $this->logsDir);
            }
        }
    }

    /**
     * Write an entry to the log
     * @param $message
     * @param $user
     * @return void
     */
    public function write($message, $user): void
    {
        if (!$this->auditEnabled) {
            return;
        }

        $logFile = $this->logsDir . date('Y-m-d') . '-audit.txt';
        $entry = sprintf(
            "[%s]\tUser: %s\t%s\n",
            date('Y-m-d H:i:s'),
            $user,
            trim($message)
        );

        file_put_contents($logFile, $entry, FILE_APPEND);
    }
}
