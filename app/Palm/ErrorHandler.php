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
        // E_STRICT is deprecated in PHP 8.4+, use E_ALL only
        set_error_handler([self::class, 'handleError'], E_ALL);

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

        // Ignore "headers already sent" and session configuration warnings in worker mode
        if (
            defined('PALM_WORKER') &&
            (str_contains($message, 'headers already sent') ||
                str_contains($message, 'Session cannot be started') ||
                str_contains($message, 'ini settings cannot be changed'))
        ) {
            return true; // Skip further handling
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

        // Get code context for better debugging
        $contextHtml = '';
        if (file_exists($error['file'])) {
            $context = self::getErrorContext($error['file'], $error['line'], 7);
            if ($context) {
                $contextHtml = '<div class="palm-code-context"><h3>📝 Code Context</h3><div class="code-lines">';
                foreach ($context as $lineNum => $lineContent) {
                    $isErrorLine = $lineNum === $error['line'];
                    $lineClass = $isErrorLine ? 'error-line' : '';
                    $marker = $isErrorLine ? '→' : ' ';
                    $contextHtml .= sprintf(
                        '<div class="code-line %s"><span class="line-number">%s %d</span><span class="line-content">%s</span></div>',
                        $lineClass,
                        $marker,
                        $lineNum,
                        htmlspecialchars($lineContent, ENT_QUOTES, 'UTF-8')
                    );
                }
                $contextHtml .= '</div></div>';
            }
        }

        $traceHtml = '';
        if (!empty($error['trace'])) {
            $traceHtml = '<div class="palm-error-trace"><h3>📚 Stack Trace</h3><ol>';
            foreach (array_slice($error['trace'], 0, 10) as $index => $trace) {
                $traceFile = htmlspecialchars(self::formatFilePath($trace['file'] ?? 'unknown'), ENT_QUOTES, 'UTF-8');
                $traceLine = $trace['line'] ?? 0;
                $traceFunction = htmlspecialchars($trace['function'] ?? 'unknown', ENT_QUOTES, 'UTF-8');
                $traceClass = htmlspecialchars($trace['class'] ?? '', ENT_QUOTES, 'UTF-8');

                $traceHtml .= '<li>';
                $traceHtml .= '<div class="trace-location">' . $traceFile . ':' . $traceLine . '</div>';
                if ($traceClass) {
                    $traceHtml .= '<div class="trace-function"><code>' . $traceClass . '::' . $traceFunction . '()</code></div>';
                } else {
                    $traceHtml .= '<div class="trace-function"><code>' . $traceFunction . '()</code></div>';
                }
                $traceHtml .= '</li>';
            }
            $traceHtml .= '</ol></div>';
        }

        return <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Palm Error – {$severity}</title>
    <style>
        * {margin: 0; padding: 0; box-sizing: border-box;}
        
        :root {
            --color-primary: #10b981;
            --color-primary-dark: #059669;
            --color-secondary: #f59e0b;
            --color-accent: #06b6d4;
            --color-bg: #f0fdf4;
            --color-bg-alt: #dcfce7;
            --color-surface: #ffffff;
            --color-border: #d1fae5;
            --color-text: #064e3b;
            --color-text-light: #047857;
            --shadow-lg: 0 10px 15px -3px rgb(16 185 129 / 0.1);
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
            background: var(--color-bg);
            min-height: 100vh;
            padding: 40px 20px;
            color: var(--color-text);
            line-height: 1.6;
        }
        
        .palm-error-container {
            max-width: 1000px;
            margin: 0 auto;
            background: var(--color-surface);
            border-radius: 12px;
            border: 1px solid var(--color-border);
            overflow: hidden;
            box-shadow: var(--shadow-lg);
        }
        
        .palm-error-header {
            background: linear-gradient(135deg, var(--color-primary) 0%, var(--color-secondary) 100%);
            color: white;
            padding: 20px 30px;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        }
        
        .palm-error-header h1 {
            font-size: 24px;
            font-weight: 800;
            margin-bottom: 0;
            display: flex;
            align-items: center;
            gap: 12px;
            letter-spacing: -0.02em;
        }
        
        .palm-error-header .severity {
            background: rgba(255, 255, 255, 0.2);
            backdrop-filter: blur(10px);
            padding: 6px 16px;
            border-radius: 99px;
            font-size: 14px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            border: 1px solid rgba(255, 255, 255, 0.3);
        }
        
        .palm-error-content {
            padding: 30px;
        }
        
        .error-message {
            background: #fffafa;
            border-left: 4px solid #f87171;
            padding: 16px 20px;
            border-radius: 8px;
            margin-bottom: 25px;
            font-size: 15px;
            line-height: 1.6;
            color: #991b1b;
            box-shadow: 0 2px 4px rgba(0,0,0,0.02);
        }
        
        .error-location {
            background: var(--color-bg-alt);
            border: 1px solid var(--color-border);
            padding: 16px 20px;
            border-radius: 8px;
            margin-bottom: 25px;
        }
        
        .error-location h3 {
            color: var(--color-primary-dark);
            font-size: 13px;
            margin-bottom: 10px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }
        
        .error-location code {
            background: white;
            padding: 6px 12px;
            border-radius: 6px;
            font-family: 'JetBrains Mono', 'Fira Code', 'Monaco', monospace;
            font-size: 14px;
            color: var(--color-text);
            display: inline-block;
            border: 1px solid var(--color-border);
        }
        
        .palm-code-context {
            margin-bottom: 25px;
            background: #064e3b;
            border-radius: 10px;
            overflow: hidden;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
        }
        
        .palm-code-context h3 {
            background: rgba(255, 255, 255, 0.1);
            color: white;
            padding: 12px 20px;
            font-size: 13px;
            font-weight: 600;
            border-bottom: 1px solid rgba(255, 255, 255, 0.05);
        }
        
        .code-lines {
            padding: 15px 0;
        }
        
        .code-line {
            display: flex;
            padding: 4px 20px;
            font-family: 'JetBrains Mono', 'Fira Code', 'Monaco', monospace;
            font-size: 13px;
            line-height: 1.6;
            color: #a7f3d0;
        }
        
        .code-line.error-line {
            background: rgba(248, 113, 113, 0.2);
            border-left: 4px solid #f87171;
        }
        
        .code-line .line-number {
            color: #34d399;
            min-width: 60px;
            user-select: none;
            margin-right: 20px;
            opacity: 0.6;
        }
        
        .code-line.error-line .line-number {
            color: #fca5a5;
            font-weight: 700;
            opacity: 1;
        }
        
        .code-line .line-content {
            color: #ecfdf5;
            white-space: pre;
        }
        
        .palm-error-trace {
            background: var(--color-bg);
            border: 1px solid var(--color-border);
            border-radius: 8px;
            padding: 20px;
        }
        
        .palm-error-trace h3 {
            color: var(--color-primary-dark);
            font-size: 13px;
            margin-bottom: 15px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }
        
        .palm-error-trace ol {
            margin-left: 20px;
            list-style: none;
            counter-reset: trace-counter;
        }
        
        .palm-error-trace li {
            margin: 10px 0;
            padding: 12px 15px;
            background: white;
            border-radius: 8px;
            border: 1px solid var(--color-border);
            position: relative;
            counter-increment: trace-counter;
            box-shadow: 0 1px 2px rgba(0,0,0,0.02);
        }

        .palm-error-trace li::before {
            content: counter(trace-counter);
            position: absolute;
            left: -35px;
            top: 50%;
            transform: translateY(-50%);
            width: 24px;
            height: 24px;
            background: var(--color-primary-light);
            color: var(--color-text);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 12px;
            font-weight: 800;
        }
        
        .trace-location {
            color: var(--color-text-light);
            font-size: 13px;
            margin-bottom: 8px;
            font-family: 'JetBrains Mono', 'Fira Code', 'Monaco', monospace;
            word-break: break-all;
        }
        
        .trace-function code {
            background: var(--color-bg-alt);
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 13px;
            color: var(--color-primary-dark);
            font-weight: 700;
            border: 1px solid var(--color-border);
        }
        
        .palm-footer {
            background: linear-gradient(to right, var(--color-bg-alt), var(--color-bg));
            padding: 15px 30px;
            text-align: center;
            color: var(--color-text-light);
            font-size: 13px;
            border-top: 1px solid var(--color-border);
            font-weight: 500;
        }
        
        .palm-footer strong {
            color: var(--color-primary-dark);
        }
    </style>
</head>
<body>
    <div class="palm-error-container">
        <div class="palm-error-header">
            <h1>
                🌴 Palm Debugger
                <span class="severity">{$severity}</span>
            </h1>
        </div>
        
        <div class="palm-error-content">
            <div class="error-message">
                <strong>Error:</strong> {$message}
            </div>
            
            <div class="error-location">
                <h3>📍 Error Location</h3>
                <code>{$file}:{$line}</code>
            </div>
            
            {$contextHtml}
            
            {$traceHtml}
        </div>
        
        <div class="palm-footer">
            <p>Built with ❤️ using <strong>PHP Palm</strong> Debugger · Modern PHP Framework</p>
        </div>
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
            // E_STRICT is deprecated in PHP 8.4+, removed from mapping
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
