<?php

namespace Frontend\Palm;

/**
 * Comprehensive error handler for Palm framework
 * Provides meaningful error messages with file locations and line numbers
 */
class ErrorHandler
{
    protected static bool $initialized = false;
    protected static array $errorLog = [];

    public static function init(): void
    {
        if (self::$initialized) {
            return;
        }

        // Set error handler
        set_error_handler([self::class, 'handleError'], E_ALL | E_STRICT);
        
        // Set exception handler
        set_exception_handler([self::class, 'handleException']);

        // Shutdown handler for fatal errors
        register_shutdown_function([self::class, 'handleShutdown']);

        self::$initialized = true;
    }

    /**
     * Handle PHP errors
     */
    public static function handleError(int $severity, string $message, string $file, int $line): bool
    {
        // Don't handle errors suppressed with @
        if (!(error_reporting() & $severity)) {
            return false;
        }

        $error = [
            'type' => 'error',
            'severity' => self::getSeverityName($severity),
            'message' => $message,
            'file' => self::formatFilePath($file),
            'line' => $line,
            'trace' => self::formatTrace(debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 10)),
            'timestamp' => date('Y-m-d H:i:s'),
        ];

        self::$errorLog[] = $error;

        // Format error for display
        $formatted = self::formatError($error);

        // Log to error log
        error_log("Palm Error: " . $formatted);

        // In development, display helpful error
        if (self::isDevelopment()) {
            self::displayError($error);
        }

        // Don't execute PHP internal error handler for certain errors
        return ($severity === E_WARNING || $severity === E_NOTICE);
    }

    /**
     * Handle uncaught exceptions
     */
    public static function handleException(\Throwable $exception): void
    {
        $error = [
            'type' => 'exception',
            'severity' => 'Exception',
            'message' => $exception->getMessage(),
            'file' => self::formatFilePath($exception->getFile()),
            'line' => $exception->getLine(),
            'trace' => self::formatTrace($exception->getTrace()),
            'class' => get_class($exception),
            'timestamp' => date('Y-m-d H:i:s'),
        ];

        self::$errorLog[] = $error;

        $formatted = self::formatError($error);
        error_log("Palm Exception: " . $formatted);

        if (self::isDevelopment()) {
            self::displayError($error);
        } else {
            http_response_code(500);
            echo json_encode([
                'error' => 'Internal Server Error',
                'message' => 'An error occurred while processing your request.',
            ], JSON_PRETTY_PRINT);
        }

        exit(1);
    }

    /**
     * Handle fatal errors on shutdown
     */
    public static function handleShutdown(): void
    {
        $error = error_get_last();
        if ($error !== null && in_array($error['type'], [E_ERROR, E_CORE_ERROR, E_COMPILE_ERROR, E_PARSE], true)) {
            $errorData = [
                'type' => 'fatal',
                'severity' => 'Fatal Error',
                'message' => $error['message'],
                'file' => self::formatFilePath($error['file']),
                'line' => $error['line'],
                'trace' => [],
                'timestamp' => date('Y-m-d H:i:s'),
            ];

            self::$errorLog[] = $errorData;
            error_log("Palm Fatal Error: " . self::formatError($errorData));

            if (self::isDevelopment()) {
                self::displayError($errorData);
            }
        }
    }

    /**
     * Format error message for logging/display
     */
    protected static function formatError(array $error): string
    {
        $lines = [];
        $lines[] = "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━";
        $lines[] = "🚨 Palm Framework Error";
        $lines[] = "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━";
        $lines[] = "";
        $lines[] = "Type:     {$error['severity']}";
        $lines[] = "Message:  {$error['message']}";
        $lines[] = "File:     {$error['file']}";
        $lines[] = "Line:     {$error['line']}";
        
        // Add context around the error line if possible
        if (isset($error['file']) && file_exists($error['file'])) {
            $context = self::getErrorContext($error['file'], $error['line']);
            if ($context) {
                $lines[] = "";
                $lines[] = "📝 Code Context:";
                foreach ($context as $lineNum => $lineContent) {
                    $marker = $lineNum === $error['line'] ? ' >>> ' : '     ';
                    $lines[] = $marker . $lineNum . ': ' . $lineContent;
                }
            }
        }

        if (isset($error['class'])) {
            $lines[] = "Class:    {$error['class']}";
        }

        $lines[] = "";
        $lines[] = "📍 Location:";
        $lines[] = "   {$error['file']}:{$error['line']}";

        if (!empty($error['trace'])) {
            $lines[] = "";
            $lines[] = "📚 Stack Trace (first 5 entries):";
            foreach (array_slice($error['trace'], 0, 5) as $index => $trace) {
                $file = $trace['file'] ?? 'unknown';
                $line = $trace['line'] ?? 0;
                $function = $trace['function'] ?? 'unknown';
                $class = $trace['class'] ?? '';
                
                $lines[] = "   " . ($index + 1) . ". " . self::formatFilePath($file) . ":$line";
                if ($class) {
                    $lines[] = "      → {$class}::{$function}()";
                } else {
                    $lines[] = "      → {$function}()";
                }
            }
        }

        $lines[] = "";
        $lines[] = "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━";

        return implode("\n", $lines);
    }

    /**
     * Display error in browser (development only)
     */
    protected static function displayError(array $error): void
    {
        // Only display if we're not already in a response
        if (headers_sent()) {
            return;
        }

        $isAjax = isset($_SERVER['HTTP_X_REQUESTED_WITH']) && 
                  strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';

        if ($isAjax) {
            header('Content-Type: application/json');
            echo json_encode([
                'error' => $error['severity'],
                'message' => $error['message'],
                'file' => $error['file'],
                'line' => $error['line'],
                'trace' => array_slice($error['trace'], 0, 3),
            ], JSON_PRETTY_PRINT);
            return;
        }

        // HTML error display
        $html = self::formatErrorHtml($error);
        echo $html;
    }

    /**
     * Format error as HTML for display
     */
    protected static function formatErrorHtml(array $error): string
    {
        $message = htmlspecialchars($error['message'], ENT_QUOTES, 'UTF-8');
        $file = htmlspecialchars($error['file'], ENT_QUOTES, 'UTF-8');
        $line = $error['line'];
        $severity = htmlspecialchars($error['severity'], ENT_QUOTES, 'UTF-8');

        $traceHtml = '';
        if (!empty($error['trace'])) {
            $traceHtml = '<div class="palm-error-trace"><h3>📚 Stack Trace</h3><ol>';
            foreach (array_slice($error['trace'], 0, 10) as $index => $trace) {
                $traceFile = htmlspecialchars($trace['file'] ?? 'unknown', ENT_QUOTES, 'UTF-8');
                $traceLine = $trace['line'] ?? 0;
                $traceFunction = htmlspecialchars($trace['function'] ?? 'unknown', ENT_QUOTES, 'UTF-8');
                $traceClass = htmlspecialchars($trace['class'] ?? '', ENT_QUOTES, 'UTF-8');
                
                $traceHtml .= '<li>';
                $traceHtml .= '<strong>' . self::formatFilePath($traceFile) . ":$traceLine</strong><br>";
                if ($traceClass) {
                    $traceHtml .= '<code>' . $traceClass . '::' . $traceFunction . '()</code>';
                } else {
                    $traceHtml .= '<code>' . $traceFunction . '()</code>';
                }
                $traceHtml .= '</li>';
            }
            $traceHtml .= '</ol></div>';
        }

        return <<<HTML
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Palm Framework Error</title>
    <style>
        body { font-family: 'Monaco', 'Menlo', 'Consolas', monospace; margin: 0; padding: 20px; background: #1e1e1e; color: #d4d4d4; }
        .palm-error { background: #2d2d2d; border: 2px solid #f48771; border-radius: 8px; padding: 20px; margin: 20px 0; }
        .palm-error-header { color: #f48771; font-size: 24px; margin-bottom: 15px; font-weight: bold; }
        .palm-error-message { color: #ce9178; font-size: 16px; margin: 10px 0; padding: 10px; background: #252526; border-left: 3px solid #f48771; }
        .palm-error-location { color: #4ec9b0; margin: 10px 0; }
        .palm-error-location code { color: #569cd6; background: #1e1e1e; padding: 2px 6px; border-radius: 3px; }
        .palm-error-trace { margin-top: 20px; }
        .palm-error-trace h3 { color: #4ec9b0; margin-bottom: 10px; }
        .palm-error-trace ol { margin-left: 20px; }
        .palm-error-trace li { margin: 8px 0; color: #d4d4d4; }
        .palm-error-trace code { color: #dcdcaa; background: #1e1e1e; padding: 2px 6px; border-radius: 3px; }
    </style>
</head>
<body>
    <div class="palm-error">
        <div class="palm-error-header">🚨 Palm Framework Error</div>
        <div class="palm-error-message"><strong>{$severity}:</strong> {$message}</div>
        <div class="palm-error-location">
            📍 <strong>Location:</strong><br>
            <code>{$file}:{$line}</code>
        </div>
        {$traceHtml}
    </div>
</body>
</html>
HTML;
    }

    /**
     * Format file path to be more readable
     */
    protected static function formatFilePath(string $file): string
    {
        // Remove eval()'d code marker and show original file
        if (preg_match("/^(.*)\((\d+)\) : eval\(\)'d code$/", $file, $matches)) {
            return $matches[1] . ' (line ' . $matches[2] . ' via eval)';
        }

        // Make path relative to project root if possible
        $root = $_SERVER['DOCUMENT_ROOT'] ?? __DIR__ . '/../../..';
        if (strpos($file, $root) === 0) {
            return substr($file, strlen($root) + 1);
        }

        return $file;
    }

    /**
     * Format stack trace
     */
    protected static function formatTrace(array $trace): array
    {
        $formatted = [];
        foreach ($trace as $entry) {
            $formatted[] = [
                'file' => $entry['file'] ?? 'unknown',
                'line' => $entry['line'] ?? 0,
                'function' => $entry['function'] ?? 'unknown',
                'class' => $entry['class'] ?? null,
                'type' => $entry['type'] ?? null,
            ];
        }
        return $formatted;
    }

    /**
     * Get severity name from error code
     */
    protected static function getSeverityName(int $severity): string
    {
        $map = [
            E_ERROR => 'Error',
            E_WARNING => 'Warning',
            E_PARSE => 'Parse Error',
            E_NOTICE => 'Notice',
            E_CORE_ERROR => 'Core Error',
            E_CORE_WARNING => 'Core Warning',
            E_COMPILE_ERROR => 'Compile Error',
            E_COMPILE_WARNING => 'Compile Warning',
            E_USER_ERROR => 'User Error',
            E_USER_WARNING => 'User Warning',
            E_USER_NOTICE => 'User Notice',
            E_STRICT => 'Strict',
            E_RECOVERABLE_ERROR => 'Recoverable Error',
            E_DEPRECATED => 'Deprecated',
            E_USER_DEPRECATED => 'User Deprecated',
        ];

        return $map[$severity] ?? 'Unknown Error';
    }

    /**
     * Check if we're in development mode
     */
    protected static function isDevelopment(): bool
    {
        // Check environment variable or use default
        return ($_ENV['DEBUG_MODE'] ?? $_ENV['APP_ENV'] ?? 'development') === 'development';
    }

    /**
     * Get all logged errors
     */
    public static function getErrors(): array
    {
        return self::$errorLog;
    }

    /**
     * Clear error log
     */
    public static function clearErrors(): void
    {
        self::$errorLog = [];
    }

    /**
     * Get code context around error line
     */
    protected static function getErrorContext(string $file, int $line, int $contextLines = 5): ?array
    {
        if (!file_exists($file) || !is_readable($file)) {
            return null;
        }

        $lines = @file($file);
        if ($lines === false) {
            return null;
        }

        $start = max(0, $line - $contextLines - 1);
        $end = min(count($lines), $line + $contextLines);
        $context = [];

        for ($i = $start; $i < $end; $i++) {
            $context[$i + 1] = rtrim($lines[$i]);
        }

        return $context;
    }
}

