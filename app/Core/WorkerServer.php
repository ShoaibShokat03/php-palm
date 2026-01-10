<?php

namespace App\Core;

/**
 * Exception used to simulate exit(); in persistent worker mode
 */
class PalmExitException extends \Exception {}

/**
 * WorkerServer - A persistent PHP server loop for high performance
 * 
 * This server bootstraps the application once and reuses the memory
 * to handle multiple requests, avoiding the per-request overhead.
 */
class WorkerServer
{
    protected string $host;
    protected int $port;
    protected string $baseDir;
    protected $socket;
    protected bool $running = true;

    public function __construct(string $host, int $port, string $baseDir)
    {
        $this->host = $host;
        $this->port = $port;
        $this->baseDir = $baseDir;
    }

    /**
     * Start the persistent worker loop
     */
    public function start(): void
    {
        // 1. Bootstrap the application ONCE
        echo "\033[32m[WORKER] Bootstrapping application...\033[0m\n";

        // Define PALM_WORKER constant to allow app to optimize for persistence
        if (!defined('PALM_WORKER')) {
            define('PALM_WORKER', true);
        }

        // Mock a basic environment for bootstrap
        $_SERVER['SERVER_NAME'] = $this->host;
        $_SERVER['SERVER_PORT'] = (string)$this->port;
        $_SERVER['DOCUMENT_ROOT'] = $this->baseDir;
        $_SERVER['SCRIPT_FILENAME'] = $this->baseDir . '/index.php';
        $_SERVER['SCRIPT_NAME'] = '/index.php';
        $_SERVER['PHP_SELF'] = '/index.php';

        // Initialize Application
        require_once $this->baseDir . '/vendor/autoload.php';
        ApplicationBootstrap::init();
        ApplicationBootstrap::load();

        // 2. Open TCP Socket
        $address = "tcp://{$this->host}:{$this->port}";
        $this->socket = stream_socket_server($address, $errno, $errstr);

        if (!$this->socket) {
            echo "\033[31m[ERROR] Could not start server: $errstr ($errno)\033[0m\n";
            exit(1);
        }

        // Set socket to non-blocking or use timeout
        stream_set_timeout($this->socket, 5);

        echo "\033[32m[WORKER] Server started at http://{$this->host}:{$this->port}\033[0m\n";
        echo "[WORKER] Loading once, handling requests persistently.\n";
        echo "[WORKER] Press Ctrl+C to stop.\n\n";

        // 3. Handle requests
        while ($this->running) {
            $client = @stream_socket_accept($this->socket, 5);
            if ($client) {
                stream_set_timeout($client, 2);
                $this->handleRequest($client);
                @fclose($client);
            }
        }
    }

    /**
     * Handle individual request
     */
    protected function handleRequest($client): void
    {
        // Read request header (basic implementation)
        $header = '';
        $maxHeaderSize = 8192;
        $startTime = microtime(true);

        while (!feof($client)) {
            $line = fgets($client);
            if ($line === false || $line === "\r\n" || $line === "\n") break;
            $header .= $line;
            if (strlen($header) > $maxHeaderSize) break;

            // Safety timeout
            if (microtime(true) - $startTime > 1.0) break;
        }

        if (empty($header)) return;

        // Parse method and URI
        if (preg_match('/^(GET|POST|PUT|DELETE|OPTIONS|PATCH) (.*) HTTP/i', $header, $matches)) {
            $method = $matches[1];
            $uri = $matches[2];

            echo date('[Y-m-d H:i:s]') . " " . str_pad($method, 7) . " {$uri} ... ";

            // Prepare Request Environment
            $this->prepareEnvironment($method, $uri, $header);

            // Capture output
            ob_start();
            try {
                // Dispatch
                $this->dispatch();
                echo "\033[32mDONE\033[0m\n";
            } catch (PalmExitException $e) {
                // Early exit requested, this is normal
                echo "\033[32mEXIT\033[0m\n";
            } catch (\Throwable $th) {
                echo "\033[31mFAIL: " . $th->getMessage() . "\033[0m\n";
                // echo $th->getTraceAsString() . "\n"; // Removed for cleaner output, can be re-enabled for debug
                if (ob_get_level() > 0 && !ob_get_length()) {
                    http_response_code(500);
                    echo json_encode(['status' => 'error', 'message' => 'Internal Server Error']);
                }
            }
            $responseBody = ob_get_clean();

            // Send HTTP response
            $status = http_response_code() ?: 200;
            $statusText = $this->getStatusText($status);

            @fwrite($client, "HTTP/1.1 {$status} {$statusText}\r\n");
            @fwrite($client, "Date: " . gmdate('D, d M Y H:i:s') . " GMT\r\n");
            @fwrite($client, "Server: PHP-Palm-Worker/0.2.0\r\n");

            // Send headers
            foreach (headers_list() as $headerLine) {
                @fwrite($client, "{$headerLine}\r\n");
            }

            // End headers
            @fwrite($client, "Content-Length: " . strlen($responseBody) . "\r\n");
            @fwrite($client, "Connection: close\r\n");
            @fwrite($client, "\r\n");

            // Send body
            @fwrite($client, $responseBody);

            // Clean up for next request
            $this->resetEnvironment();
        }
    }

    protected function prepareEnvironment(string $method, string $uri, string $header): void
    {
        $_SERVER['REQUEST_METHOD'] = $method;
        $_SERVER['REQUEST_URI'] = $uri;

        $parts = parse_url($uri);
        $_SERVER['QUERY_STRING'] = $parts['query'] ?? '';
        parse_str($_SERVER['QUERY_STRING'], $_GET);

        // Basic Cookie Parsing
        $_COOKIE = [];
        if (preg_match('/^Cookie: (.*)$/mi', $header, $cookieMatches)) {
            $cookieLine = trim($cookieMatches[1]);
            $cookies = explode(';', $cookieLine);
            foreach ($cookies as $cookie) {
                $parts = explode('=', trim($cookie), 2);
                if (count($parts) === 2) {
                    $_COOKIE[$parts[0]] = urldecode($parts[1]);
                }
            }
        }

        // Setup initial response state
        http_response_code(200);
        @header_remove(); // Clear headers from previous request
    }

    protected function resetEnvironment(): void
    {
        // Reset globals that might leak
        $_GET = [];
        $_POST = [];
        $_COOKIE = [];

        // Close session to release lock
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_write_close();
        }

        // Reset specific framework states if possible
        // (This is where more advanced reset logic would go)
    }

    protected function dispatch(): void
    {
        // Require the index.php or use internal dispatcher
        // For simplicity, we wrap the index.php logic or call the router directly
        // Here we just include a simplified entry point
        require $this->baseDir . '/index.php';
    }

    protected function getStatusText(int $code): string
    {
        $statusTexts = [
            200 => 'OK',
            404 => 'Not Found',
            500 => 'Internal Server Error',
            // Add more if needed
        ];
        return $statusTexts[$code] ?? 'Unknown';
    }
}
