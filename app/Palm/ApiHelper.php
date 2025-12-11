<?php

namespace Frontend\Palm;

/**
 * API Helper for Views
 * 
 * Provides easy backend API calls from frontend views
 */
class ApiHelper
{
    protected static string $apiBaseUrl = '/api';
    protected static int $timeout = 10;
    protected static array $defaultHeaders = [];

    /**
     * Initialize API helper
     */
    public static function init(string $baseUrl = '/api', int $timeout = 10): void
    {
        self::$apiBaseUrl = rtrim($baseUrl, '/');
        self::$timeout = $timeout;
    }

    /**
     * Call API endpoint
     */
    public static function call(string $endpoint, array $options = []): mixed
    {
        $method = $options['method'] ?? 'GET';
        $data = $options['data'] ?? [];
        $headers = array_merge(self::$defaultHeaders, $options['headers'] ?? []);
        $cache = $options['cache'] ?? null; // TTL in seconds
        $onError = $options['onError'] ?? null;

        // Build URL
        $url = self::$apiBaseUrl . '/' . ltrim($endpoint, '/');

        // Check cache first
        if ($cache !== null && $method === 'GET') {
            $cacheKey = 'api_' . md5($url . serialize($data));
            $cached = ViewCache::get($cacheKey);
            if ($cached !== null) {
                return $cached;
            }
        }

        try {
            // Use cURL for API call
            $ch = curl_init();
            
            curl_setopt_array($ch, [
                CURLOPT_URL => $url,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => self::$timeout,
                CURLOPT_HTTPHEADER => self::buildHeaders($headers),
                CURLOPT_SSL_VERIFYPEER => false, // For development
            ]);

            if ($method === 'POST') {
                curl_setopt($ch, CURLOPT_POST, true);
                curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
            } elseif ($method === 'PUT' || $method === 'PATCH') {
                curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
                curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
            } elseif ($method === 'DELETE') {
                curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'DELETE');
            }

            // Add CSRF token if available
            if (class_exists(\App\Core\Security\CSRF::class)) {
                $csrfToken = \App\Core\Security\CSRF::getToken();
                curl_setopt($ch, CURLOPT_HTTPHEADER, array_merge(
                    self::buildHeaders($headers),
                    ['X-CSRF-Token: ' . $csrfToken]
                ));
            }

            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $error = curl_error($ch);
            curl_close($ch);

            if ($error) {
                throw new \Exception("API request failed: {$error}");
            }

            $result = json_decode($response, true);

            if ($httpCode >= 400) {
                if ($onError !== null && is_callable($onError)) {
                    return $onError($result);
                }
                throw new \Exception("API error: HTTP {$httpCode}");
            }

            // Cache result if cache TTL provided
            if ($cache !== null && $method === 'GET' && $result !== null) {
                $cacheKey = 'api_' . md5($url . serialize($data));
                ViewCache::put($cacheKey, $result, $cache);
            }

            return $result;
        } catch (\Throwable $e) {
            if ($onError !== null && is_callable($onError)) {
                return $onError(['error' => $e->getMessage()]);
            }
            throw $e;
        }
    }

    /**
     * Build HTTP headers array
     */
    protected static function buildHeaders(array $headers): array
    {
        $result = ['Content-Type: application/json'];
        foreach ($headers as $key => $value) {
            $result[] = "{$key}: {$value}";
        }
        return $result;
    }

    /**
     * GET request
     */
    public static function get(string $endpoint, array $options = []): mixed
    {
        return self::call($endpoint, array_merge($options, ['method' => 'GET']));
    }

    /**
     * POST request
     */
    public static function post(string $endpoint, array $data = [], array $options = []): mixed
    {
        return self::call($endpoint, array_merge($options, ['method' => 'POST', 'data' => $data]));
    }

    /**
     * PUT request
     */
    public static function put(string $endpoint, array $data = [], array $options = []): mixed
    {
        return self::call($endpoint, array_merge($options, ['method' => 'PUT', 'data' => $data]));
    }

    /**
     * DELETE request
     */
    public static function delete(string $endpoint, array $options = []): mixed
    {
        return self::call($endpoint, array_merge($options, ['method' => 'DELETE']));
    }
}

