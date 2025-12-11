<?php

namespace Frontend\Palm;

use App\Core\Security\Session;

// Ensure Session class is loaded
if (!class_exists(Session::class)) {
    throw new \RuntimeException('Session class not found. Please ensure App\Core\Security\Session is available.');
}

/**
 * Google OAuth Authentication
 * 
 * Easy-to-use Google authentication for PHP Palm
 */
class GoogleAuth
{
    protected static ?string $clientId = null;
    protected static ?string $clientSecret = null;
    protected static ?string $redirectUri = null;
    protected static ?string $stateSecret = null;
    
    protected const AUTH_URL = 'https://accounts.google.com/o/oauth2/v2/auth';
    protected const TOKEN_URL = 'https://oauth2.googleapis.com/token';
    protected const USERINFO_URL = 'https://www.googleapis.com/oauth2/v2/userinfo';
    
    protected const SESSION_KEY = 'google_auth_user';
    protected const STATE_KEY = 'google_auth_state';
    
    /**
     * Initialize Google Auth with credentials
     */
    public static function init(string $clientId, string $clientSecret, string $redirectUri, ?string $stateSecret = null): void
    {
        self::$clientId = $clientId;
        self::$clientSecret = $clientSecret;
        self::$redirectUri = $redirectUri;
        self::$stateSecret = $stateSecret ?? bin2hex(random_bytes(16));
        
        Session::start();
    }
    
    /**
     * Initialize from environment variables
     */
    public static function initFromEnv(): void
    {
        $clientId = $_ENV['GOOGLE_CLIENT_ID'] ?? getenv('GOOGLE_CLIENT_ID');
        $clientSecret = $_ENV['GOOGLE_CLIENT_SECRET'] ?? getenv('GOOGLE_CLIENT_SECRET');
        $redirectUri = $_ENV['GOOGLE_REDIRECT_URI'] ?? getenv('GOOGLE_REDIRECT_URI');
        
        if (empty($clientId) || empty($clientSecret) || empty($redirectUri)) {
            throw new \RuntimeException('Google OAuth credentials not found in environment. Please set GOOGLE_CLIENT_ID, GOOGLE_CLIENT_SECRET, and GOOGLE_REDIRECT_URI.');
        }
        
        self::init($clientId, $clientSecret, $redirectUri);
    }
    
    /**
     * Get authorization URL
     */
    public static function getAuthUrl(?array $scopes = null, ?string $state = null): string
    {
        if (self::$clientId === null) {
            self::initFromEnv();
        }
        
        $scopes = $scopes ?? ['openid', 'email', 'profile'];
        $state = $state ?? self::generateState();
        
        // Store state in session for validation
        Session::set(self::STATE_KEY, $state);
        
        $params = [
            'client_id' => self::$clientId,
            'redirect_uri' => self::$redirectUri,
            'response_type' => 'code',
            'scope' => implode(' ', $scopes),
            'state' => $state,
            'access_type' => 'offline',
            'prompt' => 'consent',
        ];
        
        return self::AUTH_URL . '?' . http_build_query($params);
    }
    
    /**
     * Handle OAuth callback
     */
    public static function handleCallback(?string $code = null, ?string $state = null): array
    {
        if (self::$clientId === null) {
            self::initFromEnv();
        }
        
        // Validate state
        $storedState = Session::get(self::STATE_KEY);
        if ($state === null || $state !== $storedState) {
            throw new \RuntimeException('Invalid state parameter. Possible CSRF attack.');
        }
        
        // Clear state
        Session::remove(self::STATE_KEY);
        
        // Get authorization code
        $code = $code ?? $_GET['code'] ?? null;
        if (empty($code)) {
            throw new \RuntimeException('Authorization code not provided.');
        }
        
        // Exchange code for token
        $tokenData = self::getAccessToken($code);
        
        // Get user info
        $userInfo = self::getUserInfo($tokenData['access_token']);
        
        // Store user in session
        Session::set(self::SESSION_KEY, [
            'id' => $userInfo['id'],
            'email' => $userInfo['email'],
            'name' => $userInfo['name'] ?? '',
            'picture' => $userInfo['picture'] ?? '',
            'verified_email' => $userInfo['verified_email'] ?? false,
            'access_token' => $tokenData['access_token'],
            'refresh_token' => $tokenData['refresh_token'] ?? null,
            'expires_at' => time() + ($tokenData['expires_in'] ?? 3600),
        ]);
        
        return Session::get(self::SESSION_KEY);
    }
    
    /**
     * Exchange authorization code for access token
     */
    protected static function getAccessToken(string $code): array
    {
        $data = [
            'code' => $code,
            'client_id' => self::$clientId,
            'client_secret' => self::$clientSecret,
            'redirect_uri' => self::$redirectUri,
            'grant_type' => 'authorization_code',
        ];
        
        $ch = curl_init(self::TOKEN_URL);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => http_build_query($data),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/x-www-form-urlencoded',
            ],
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode !== 200) {
            $error = json_decode($response, true);
            throw new \RuntimeException('Failed to get access token: ' . ($error['error_description'] ?? 'Unknown error'));
        }
        
        return json_decode($response, true);
    }
    
    /**
     * Get user info from Google
     */
    protected static function getUserInfo(string $accessToken): array
    {
        $ch = curl_init(self::USERINFO_URL);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . $accessToken,
            ],
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode !== 200) {
            throw new \RuntimeException('Failed to get user info');
        }
        
        return json_decode($response, true);
    }
    
    /**
     * Check if user is authenticated
     */
    public static function check(): bool
    {
        Session::start();
        $user = Session::get(self::SESSION_KEY);
        
        if ($user === null) {
            return false;
        }
        
        // Check if token is expired
        if (isset($user['expires_at']) && $user['expires_at'] < time()) {
            // Try to refresh token if refresh_token is available
            if (!empty($user['refresh_token'])) {
                try {
                    self::refreshToken($user['refresh_token']);
                    return true;
                } catch (\Exception $e) {
                    self::logout();
                    return false;
                }
            } else {
                self::logout();
                return false;
            }
        }
        
        return true;
    }
    
    /**
     * Get authenticated user
     */
    public static function user(): ?array
    {
        if (!self::check()) {
            return null;
        }
        
        $user = Session::get(self::SESSION_KEY);
        // Don't expose tokens
        unset($user['access_token'], $user['refresh_token']);
        
        return $user;
    }
    
    /**
     * Get user ID
     */
    public static function id(): ?string
    {
        $user = self::user();
        return $user['id'] ?? null;
    }
    
    /**
     * Get user email
     */
    public static function email(): ?string
    {
        $user = self::user();
        return $user['email'] ?? null;
    }
    
    /**
     * Get user name
     */
    public static function name(): ?string
    {
        $user = self::user();
        return $user['name'] ?? null;
    }
    
    /**
     * Get user picture
     */
    public static function picture(): ?string
    {
        $user = self::user();
        return $user['picture'] ?? null;
    }
    
    /**
     * Refresh access token
     */
    protected static function refreshToken(string $refreshToken): void
    {
        $data = [
            'client_id' => self::$clientId,
            'client_secret' => self::$clientSecret,
            'refresh_token' => $refreshToken,
            'grant_type' => 'refresh_token',
        ];
        
        $ch = curl_init(self::TOKEN_URL);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => http_build_query($data),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/x-www-form-urlencoded',
            ],
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode !== 200) {
            throw new \RuntimeException('Failed to refresh token');
        }
        
        $tokenData = json_decode($response, true);
        $user = Session::get(self::SESSION_KEY);
        
        // Update tokens
        $user['access_token'] = $tokenData['access_token'];
        $user['expires_at'] = time() + ($tokenData['expires_in'] ?? 3600);
        
        Session::set(self::SESSION_KEY, $user);
    }
    
    /**
     * Logout user
     */
    public static function logout(): void
    {
        Session::remove(self::SESSION_KEY);
        Session::remove(self::STATE_KEY);
    }
    
    /**
     * Generate state token
     */
    protected static function generateState(): string
    {
        return bin2hex(random_bytes(16));
    }
    
    /**
     * Redirect to Google login
     */
    public static function redirect(): void
    {
        header('Location: ' . self::getAuthUrl());
        exit;
    }
}

