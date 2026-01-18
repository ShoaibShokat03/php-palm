<?php

namespace App\Core\Security;

/**
 * JWT (JSON Web Token) Authentication
 * 
 * Features:
 * - HS256/HS384/HS512 signing
 * - Token generation with custom claims
 * - Token validation with signature verification
 * - Automatic expiration checking
 * - Refresh token support
 * - Token blacklisting for logout
 * 
 * Usage:
 *   // Generate token
 *   $token = JwtAuth::generate(['user_id' => 1, 'role' => 'admin']);
 *   
 *   // Validate token
 *   $payload = JwtAuth::validate($token);
 *   if ($payload) {
 *       $userId = $payload['user_id'];
 *   }
 *   
 *   // Refresh token
 *   $newToken = JwtAuth::refresh($token);
 *   
 *   // Invalidate token (logout)
 *   JwtAuth::invalidate($token);
 * 
 * @package PhpPalm\Security
 */
class JwtAuth
{
    /**
     * Default algorithm for signing
     */
    protected static string $algorithm = 'HS256';

    /**
     * Algorithm to hash function mapping
     */
    protected static array $algorithms = [
        'HS256' => 'sha256',
        'HS384' => 'sha384',
        'HS512' => 'sha512',
    ];

    /**
     * Default token expiration in seconds (1 hour)
     */
    protected static int $expiration = 3600;

    /**
     * Refresh token expiration in seconds (7 days)
     */
    protected static int $refreshExpiration = 604800;

    /**
     * Token blacklist (for invalidated tokens)
     * In production, use Redis or database
     */
    protected static array $blacklist = [];

    /**
     * Secret key cache
     */
    protected static ?string $secretKey = null;

    /**
     * Get the secret key for signing
     */
    protected static function getSecretKey(): string
    {
        if (self::$secretKey !== null) {
            return self::$secretKey;
        }

        $key = $_ENV['JWT_SECRET'] ?? $_ENV['APP_KEY'] ?? null;

        if (empty($key)) {
            $key = hash('sha256', 'php_palm_jwt_secret_change_in_production');
            error_log('[SECURITY WARNING] Using default JWT secret. Set JWT_SECRET in production.');
        }

        self::$secretKey = $key;
        return self::$secretKey;
    }

    /**
     * Generate a JWT token
     * 
     * @param array $payload Custom claims (user_id, role, etc.)
     * @param int|null $expiration Expiration in seconds (null = default)
     * @return string JWT token
     */
    public static function generate(array $payload, ?int $expiration = null): string
    {
        $expiration = $expiration ?? self::$expiration;
        $now = time();

        // Standard JWT claims
        $claims = [
            'iat' => $now,                    // Issued at
            'exp' => $now + $expiration,      // Expiration
            'nbf' => $now,                    // Not before
            'jti' => bin2hex(random_bytes(16)), // JWT ID (unique identifier)
        ];

        // Add issuer if configured
        if (!empty($_ENV['APP_URL'])) {
            $claims['iss'] = $_ENV['APP_URL'];
        }

        // Merge custom payload
        $payload = array_merge($claims, $payload);

        return self::encode($payload);
    }

    /**
     * Generate a refresh token (longer expiration)
     */
    public static function generateRefresh(array $payload): string
    {
        $payload['type'] = 'refresh';
        return self::generate($payload, self::$refreshExpiration);
    }

    /**
     * Validate and decode a JWT token
     * 
     * @param string $token JWT token
     * @return array|null Decoded payload or null if invalid
     */
    public static function validate(string $token): ?array
    {
        try {
            // Check blacklist
            if (self::isBlacklisted($token)) {
                return null;
            }

            $payload = self::decode($token);

            if ($payload === null) {
                return null;
            }

            // Check expiration
            if (isset($payload['exp']) && $payload['exp'] < time()) {
                return null;
            }

            // Check not before
            if (isset($payload['nbf']) && $payload['nbf'] > time()) {
                return null;
            }

            return $payload;
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Refresh a token (generate new token with same claims)
     * 
     * @param string $token Current valid token
     * @return string|null New token or null if current is invalid
     */
    public static function refresh(string $token): ?string
    {
        $payload = self::validate($token);

        if ($payload === null) {
            return null;
        }

        // Remove standard claims that will be regenerated
        unset($payload['iat'], $payload['exp'], $payload['nbf'], $payload['jti']);

        // Invalidate old token
        self::invalidate($token);

        // Generate new token
        return self::generate($payload);
    }

    /**
     * Invalidate a token (add to blacklist)
     * 
     * @param string $token Token to invalidate
     */
    public static function invalidate(string $token): void
    {
        $payload = self::decode($token);

        if ($payload && isset($payload['jti'])) {
            // Store with expiration for cleanup
            self::$blacklist[$payload['jti']] = $payload['exp'] ?? (time() + 3600);

            // Clean up expired entries
            self::cleanBlacklist();
        }
    }

    /**
     * Check if a token is blacklisted
     */
    protected static function isBlacklisted(string $token): bool
    {
        $payload = self::decode($token);

        if ($payload && isset($payload['jti'])) {
            return isset(self::$blacklist[$payload['jti']]);
        }

        return false;
    }

    /**
     * Clean up expired entries from blacklist
     */
    protected static function cleanBlacklist(): void
    {
        $now = time();
        self::$blacklist = array_filter(
            self::$blacklist,
            fn($exp) => $exp > $now
        );
    }

    /**
     * Encode payload to JWT
     */
    protected static function encode(array $payload): string
    {
        $header = [
            'typ' => 'JWT',
            'alg' => self::$algorithm,
        ];

        $segments = [
            self::base64UrlEncode(json_encode($header)),
            self::base64UrlEncode(json_encode($payload)),
        ];

        $signingInput = implode('.', $segments);
        $signature = self::sign($signingInput);

        $segments[] = self::base64UrlEncode($signature);

        return implode('.', $segments);
    }

    /**
     * Decode JWT to payload
     */
    protected static function decode(string $token): ?array
    {
        $parts = explode('.', $token);

        if (count($parts) !== 3) {
            return null;
        }

        [$headerB64, $payloadB64, $signatureB64] = $parts;

        // Verify signature
        $signingInput = $headerB64 . '.' . $payloadB64;
        $signature = self::base64UrlDecode($signatureB64);

        if (!self::verify($signingInput, $signature)) {
            return null;
        }

        // Decode payload
        $payload = json_decode(self::base64UrlDecode($payloadB64), true);

        return is_array($payload) ? $payload : null;
    }

    /**
     * Sign the input string
     */
    protected static function sign(string $input): string
    {
        $algorithm = self::$algorithms[self::$algorithm] ?? 'sha256';
        return hash_hmac($algorithm, $input, self::getSecretKey(), true);
    }

    /**
     * Verify signature
     */
    protected static function verify(string $input, string $signature): bool
    {
        $expected = self::sign($input);
        return hash_equals($expected, $signature);
    }

    /**
     * Base64 URL-safe encode
     */
    protected static function base64UrlEncode(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    /**
     * Base64 URL-safe decode
     */
    protected static function base64UrlDecode(string $data): string
    {
        $remainder = strlen($data) % 4;
        if ($remainder) {
            $data .= str_repeat('=', 4 - $remainder);
        }
        return base64_decode(strtr($data, '-_', '+/'));
    }

    /**
     * Set the signing algorithm
     */
    public static function setAlgorithm(string $algorithm): void
    {
        if (!isset(self::$algorithms[$algorithm])) {
            throw new \InvalidArgumentException("Unsupported algorithm: {$algorithm}");
        }
        self::$algorithm = $algorithm;
    }

    /**
     * Set default expiration time
     */
    public static function setExpiration(int $seconds): void
    {
        self::$expiration = $seconds;
    }

    /**
     * Set refresh token expiration time
     */
    public static function setRefreshExpiration(int $seconds): void
    {
        self::$refreshExpiration = $seconds;
    }

    /**
     * Get token from Authorization header
     * Supports: Bearer token, JWT token
     */
    public static function fromHeader(): ?string
    {
        $header = $_SERVER['HTTP_AUTHORIZATION'] ?? '';

        // Bearer token
        if (preg_match('/^Bearer\s+(.+)$/i', $header, $matches)) {
            return $matches[1];
        }

        // Direct JWT
        if (preg_match('/^JWT\s+(.+)$/i', $header, $matches)) {
            return $matches[1];
        }

        return null;
    }

    /**
     * Get token from request (header, query, or cookie)
     */
    public static function fromRequest(): ?string
    {
        // Try Authorization header first
        $token = self::fromHeader();
        if ($token) {
            return $token;
        }

        // Try query parameter
        if (!empty($_GET['token'])) {
            return $_GET['token'];
        }

        // Try cookie
        if (!empty($_COOKIE['jwt_token'])) {
            return $_COOKIE['jwt_token'];
        }

        return null;
    }

    /**
     * Validate token from request and return payload
     */
    public static function user(): ?array
    {
        $token = self::fromRequest();

        if (!$token) {
            return null;
        }

        return self::validate($token);
    }

    /**
     * Check if user is authenticated
     */
    public static function check(): bool
    {
        return self::user() !== null;
    }

    /**
     * Get a specific claim from the current token
     */
    public static function claim(string $key, mixed $default = null): mixed
    {
        $user = self::user();
        return $user[$key] ?? $default;
    }

    /**
     * Clear secret key cache
     */
    public static function clearCache(): void
    {
        self::$secretKey = null;
        self::$blacklist = [];
    }
}
