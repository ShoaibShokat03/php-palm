<?php

namespace App\Core;

use DateTime;
use Exception;

/**
 * Enhanced File-Based Logger with Daily Rotation
 * PSR-3 compliant logging with multiple levels and file storage
 */
class Logger
{
    // Log levels (PSR-3 compliant)
    const DEBUG = 'DEBUG';
    const INFO = 'INFO';
    const NOTICE = 'NOTICE';
    const WARNING = 'WARNING';
    const ERROR = 'ERROR';
    const CRITICAL = 'CRITICAL';
    const ALERT = 'ALERT';
    const EMERGENCY = 'EMERGENCY';

    protected string $logPath;
    protected string $logLevel;
    protected int $maxFiles;
    protected int $maxFileSize;

    private static ?Logger $instance = null;

    public function __construct()
    {
        $this->logPath = $this->getBasePath() . '/storage/logs';
        $this->logLevel = $_ENV['LOG_LEVEL'] ?? 'DEBUG';
        $this->maxFiles = (int)($_ENV['LOG_MAX_FILES'] ?? 30); // Keep 30 days by default
        $this->maxFileSize = (int)($_ENV['LOG_MAX_SIZE'] ?? 10485760); // 10MB default

        $this->ensureLogDirectoryExists();
    }

    /**
     * Get singleton instance
     */
    public static function getInstance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Get base path of application
     */
    protected function getBasePath(): string
    {
        return dirname(dirname(__DIR__));
    }

    /**
     * Ensure log directory exists
     */
    protected function ensureLogDirectoryExists(): void
    {
        if (!is_dir($this->logPath)) {
            mkdir($this->logPath, 0755, true);
        }
    }

    /**
     * Get log filename for today
     */
    protected function getLogFilename(): string
    {
        $date = date('Y-m-d');
        return "{$this->logPath}/{$date}.log";
    }

    /**
     * Check if log level should be logged
     */
    protected function shouldLog(string $level): bool
    {
        $levels = [
            'DEBUG' => 0,
            'INFO' => 1,
            'NOTICE' => 2,
            'WARNING' => 3,
            'ERROR' => 4,
            'CRITICAL' => 5,
            'ALERT' => 6,
            'EMERGENCY' => 7,
        ];

        $currentLevel = $levels[$this->logLevel] ?? 0;
        $messageLevel = $levels[$level] ?? 0;

        return $messageLevel >= $currentLevel;
    }

    /**
     * Write log entry to file
     */
    protected function write(string $level, string $message, array $context = []): void
    {
        if (!$this->shouldLog($level)) {
            return;
        }

        $filename = $this->getLogFilename();
        $timestamp = date('Y-m-d H:i:s');

        // Format the message
        $logEntry = $this->formatLogEntry($timestamp, $level, $message, $context);

        // Write to file
        try {
            file_put_contents($filename, $logEntry . PHP_EOL, FILE_APPEND | LOCK_EX);

            // Check file size and rotate if needed
            $this->checkFileSize($filename);

            // Clean old log files
            $this->cleanOldLogs();
        } catch (Exception $e) {
            // Silently fail - don't break app if logging fails
            error_log("Logger failed: " . $e->getMessage());
        }
    }

    /**
     * Format log entry
     */
    protected function formatLogEntry(string $timestamp, string $level, string $message, array $context): string
    {
        $logEntry = "[{$timestamp}] {$level}: {$message}";

        // Add context if provided
        if (!empty($context)) {
            $contextStr = json_encode($context, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
            $logEntry .= " | Context: {$contextStr}";
        }

        // Add request info if available
        if (isset($_SERVER['REQUEST_URI'])) {
            $method = $_SERVER['REQUEST_METHOD'] ?? 'CLI';
            $uri = $_SERVER['REQUEST_URI'] ?? 'N/A';
            $logEntry .= " | Request: {$method} {$uri}";
        }

        return $logEntry;
    }

    /**
     * Check file size and rotate if too large
     */
    protected function checkFileSize(string $filename): void
    {
        if (file_exists($filename) && filesize($filename) > $this->maxFileSize) {
            $newFilename = $filename . '.' . time() . '.old';
            rename($filename, $newFilename);
        }
    }

    /**
     * Clean old log files (keep only last N days)
     */
    protected function cleanOldLogs(): void
    {
        $files = glob($this->logPath . '/*.log');
        if (count($files) > $this->maxFiles) {
            // Sort files by modification time
            usort($files, function ($a, $b) {
                return filemtime($a) - filemtime($b);
            });

            // Delete oldest files
            $filesToDelete = array_slice($files, 0, count($files) - $this->maxFiles);
            foreach ($filesToDelete as $file) {
                @unlink($file);
            }
        }
    }

    /**
     * Clear all log files
     */
    public function clearLogs(): int
    {
        $files = glob($this->logPath . '/*.log*');
        $count = 0;

        foreach ($files as $file) {
            if (is_file($file)) {
                @unlink($file);
                $count++;
            }
        }

        return $count;
    }

    /**
     * Get recent log entries
     */
    public function getRecentLogs(int $lines = 100): array
    {
        $filename = $this->getLogFilename();

        if (!file_exists($filename)) {
            return [];
        }

        $file = new \SplFileObject($filename);
        $file->seek(PHP_INT_MAX);
        $totalLines = $file->key();

        $startLine = max(0, $totalLines - $lines);

        $logs = [];
        $file->seek($startLine);
        while (!$file->eof()) {
            $line = trim($file->current());
            if ($line) {
                $logs[] = $line;
            }
            $file->next();
        }

        return $logs;
    }

    // PSR-3 compliant logging methods

    public function emergency(string $message, array $context = []): void
    {
        $this->write(self::EMERGENCY, $message, $context);
    }

    public function alert(string $message, array $context = []): void
    {
        $this->write(self::ALERT, $message, $context);
    }

    public function critical(string $message, array $context = []): void
    {
        $this->write(self::CRITICAL, $message, $context);
    }

    public function error(string $message, array $context = []): void
    {
        $this->write(self::ERROR, $message, $context);
    }

    public function warning(string $message, array $context = []): void
    {
        $this->write(self::WARNING, $message, $context);
    }

    public function notice(string $message, array $context = []): void
    {
        $this->write(self::NOTICE, $message, $context);
    }

    public function info(string $message, array $context = []): void
    {
        $this->write(self::INFO, $message, $context);
    }

    public function debug(string $message, array $context = []): void
    {
        $this->write(self::DEBUG, $message, $context);
    }

    /**
     * Generic log method
     */
    public function log(string $level, string $message, array $context = []): void
    {
        $this->write($level, $message, $context);
    }

    // Convenience methods

    /**
     * Log database query
     */
    public function query(string $sql, array $bindings = [], float $duration = null): void
    {
        $context = ['bindings' => $bindings];
        if ($duration !== null) {
            $context['duration'] = round($duration, 4) . 's';
        }

        $this->debug("Query: {$sql}", $context);
    }

    /**
     * Log SQL error
     */
    public function sqlError(string $sql, string $error, array $bindings = []): void
    {
        $this->error("SQL Error: {$error}", [
            'sql' => $sql,
            'bindings' => $bindings
        ]);
    }

    /**
     * Log exception
     */
    public function exception(\Throwable $e, array $context = []): void
    {
        $this->error($e->getMessage(), array_merge([
            'exception' => get_class($e),
            'file' => $e->getFile(),
            'line' => $e->getLine(),
            'trace' => $e->getTraceAsString()
        ], $context));
    }

    /**
     * Log performance metric
     */
    public function performance(string $operation, float $duration, array $context = []): void
    {
        $this->info("Performance: {$operation} took " . round($duration, 4) . "s", $context);
    }

    // Static helper methods for easy access

    public static function emergencyStatic(string $message, array $context = []): void
    {
        self::getInstance()->emergency($message, $context);
    }

    public static function alertStatic(string $message, array $context = []): void
    {
        self::getInstance()->alert($message, $context);
    }

    public static function criticalStatic(string $message, array $context = []): void
    {
        self::getInstance()->critical($message, $context);
    }

    public static function errorStatic(string $message, array $context = []): void
    {
        self::getInstance()->error($message, $context);
    }

    public static function warningStatic(string $message, array $context = []): void
    {
        self::getInstance()->warning($message, $context);
    }

    public static function warnStatic(string $message, array $context = []): void
    {
        self::getInstance()->warning($message, $context);
    }

    public static function noticeStatic(string $message, array $context = []): void
    {
        self::getInstance()->notice($message, $context);
    }

    public static function infoStatic(string $message, array $context = []): void
    {
        self::getInstance()->info($message, $context);
    }

    public static function debugStatic(string $message, array $context = []): void
    {
        self::getInstance()->debug($message, $context);
    }
}
