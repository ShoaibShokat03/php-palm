<?php

namespace App\Core;

/**
 * Centralized Logger
 * 
 * Features:
 * - Multiple log levels (debug, info, warn, error)
 * - Log rotation
 * - Performance profiling
 * - Request logging
 */
class Logger
{
    protected static Logger $instance;
    protected string $logDir;
    protected string $level;
    protected array $levels = ['debug', 'info', 'warn', 'error'];
    protected int $maxFileSize = 10485760; // 10MB
    protected int $maxFiles = 5;

    public function __construct(string $logDir = null, string $level = 'info')
    {
        $this->logDir = $logDir ?? (__DIR__ . '/../../storage/logs');
        $this->level = strtolower($level);
        
        if (!is_dir($this->logDir)) {
            mkdir($this->logDir, 0755, true);
        }
    }

    public static function getInstance(): Logger
    {
        if (!isset(self::$instance)) {
            $logDir = $_ENV['LOG_DIR'] ?? null;
            $level = $_ENV['LOG_LEVEL'] ?? 'info';
            self::$instance = new self($logDir, $level);
        }
        return self::$instance;
    }

    /**
     * Log message
     */
    public function log(string $level, string $message, array $context = []): void
    {
        $levelIndex = array_search(strtolower($level), $this->levels);
        $currentLevelIndex = array_search($this->level, $this->levels);

        // Skip if level is below current log level
        if ($levelIndex < $currentLevelIndex) {
            return;
        }

        $logFile = $this->logDir . '/' . date('Y-m-d') . '.log';
        $this->rotateIfNeeded($logFile);

        $logEntry = [
            'timestamp' => date('Y-m-d H:i:s'),
            'level' => strtoupper($level),
            'message' => $message,
            'context' => $context
        ];

        $line = json_encode($logEntry) . "\n";
        file_put_contents($logFile, $line, FILE_APPEND | LOCK_EX);
    }

    /**
     * Log debug message
     */
    public function debug(string $message, array $context = []): void
    {
        $this->log('debug', $message, $context);
    }

    /**
     * Log info message
     */
    public function info(string $message, array $context = []): void
    {
        $this->log('info', $message, $context);
    }

    /**
     * Log warning message
     */
    public function warn(string $message, array $context = []): void
    {
        $this->log('warn', $message, $context);
    }

    /**
     * Log error message
     */
    public function error(string $message, array $context = []): void
    {
        $this->log('error', $message, $context);
    }

    /**
     * Rotate log file if needed
     */
    protected function rotateIfNeeded(string $logFile): void
    {
        if (!file_exists($logFile)) {
            return;
        }

        if (filesize($logFile) >= $this->maxFileSize) {
            // Rotate existing files
            for ($i = $this->maxFiles - 1; $i >= 1; $i--) {
                $oldFile = $logFile . '.' . $i;
                $newFile = $logFile . '.' . ($i + 1);
                
                if (file_exists($oldFile)) {
                    if ($i + 1 <= $this->maxFiles) {
                        rename($oldFile, $newFile);
                    } else {
                        unlink($oldFile);
                    }
                }
            }

            // Move current file
            rename($logFile, $logFile . '.1');
        }
    }

    /**
     * Static helper methods
     */
    public static function debugStatic(string $message, array $context = []): void
    {
        self::getInstance()->debug($message, $context);
    }

    public static function infoStatic(string $message, array $context = []): void
    {
        self::getInstance()->info($message, $context);
    }

    public static function warnStatic(string $message, array $context = []): void
    {
        self::getInstance()->warn($message, $context);
    }

    public static function errorStatic(string $message, array $context = []): void
    {
        self::getInstance()->error($message, $context);
    }
}

