<?php

namespace App\Core\Security;

/**
 * Enterprise-Grade Encryption Service
 * 
 * Features:
 * - AES-256-GCM (authenticated encryption with associated data)
 * - Key rotation with versioning
 * - Secure key derivation using HKDF
 * - Field-level encryption support for models
 * 
 * @package PhpPalm\Security
 */
class Encryption
{
    /**
     * Cipher algorithm - GCM provides authenticated encryption (AEAD)
     * This protects both confidentiality AND integrity in one operation
     */
    protected static string $cipher = 'aes-256-gcm';
    
    /**
     * Fallback cipher for legacy decryption (CBC mode)
     */
    protected static string $legacyCipher = 'aes-256-cbc';
    
    /**
     * Tag length for GCM mode (128 bits = 16 bytes)
     */
    protected static int $tagLength = 16;
    
    /**
     * Current key version (for key rotation)
     */
    protected static int $currentKeyVersion = 1;
    
    /**
     * Cached keys array [version => key]
     */
    protected static array $keys = [];
    
    /**
     * Get encryption key for a specific version
     * Supports key rotation by allowing multiple key versions
     * 
     * @param int $version Key version (0 = current)
     * @return string 32-byte key for AES-256
     */
    protected static function getKey(int $version = 0): string
    {
        if ($version === 0) {
            $version = self::$currentKeyVersion;
        }
        
        if (isset(self::$keys[$version])) {
            return self::$keys[$version];
        }
        
        // Get key from environment (versioned keys: ENCRYPTION_KEY, ENCRYPTION_KEY_2, etc.)
        $envKey = $version === 1 
            ? 'ENCRYPTION_KEY' 
            : "ENCRYPTION_KEY_{$version}";
        
        $key = $_ENV[$envKey] ?? null;
        
        if (empty($key)) {
            if ($version === 1) {
                // Fallback for development only - NEVER use in production
                $key = hash('sha256', 'php_palm_dev_key_change_in_production_' . ($version));
                error_log('[SECURITY WARNING] Using default encryption key. Set ENCRYPTION_KEY in production.');
            } else {
                throw new \RuntimeException("Encryption key version {$version} not found in environment.");
            }
        }
        
        // Derive a proper 256-bit key using HKDF
        self::$keys[$version] = self::deriveKey($key, "encryption_v{$version}");
        
        return self::$keys[$version];
    }
    
    /**
     * Derive a cryptographic key using HKDF (HMAC-based Key Derivation Function)
     * This provides proper key derivation from passwords or raw key material
     * 
     * @param string $inputKey Input key material
     * @param string $context Context string for domain separation
     * @return string 32-byte derived key
     */
    public static function deriveKey(string $inputKey, string $context = ''): string
    {
        // Use HKDF with SHA-256
        // Extract phase: create a pseudorandom key from input
        $salt = hash('sha256', 'php_palm_salt_v1', true);
        $prk = hash_hmac('sha256', $inputKey, $salt, true);
        
        // Expand phase: derive the output key
        $info = $context . "\x00";
        $okm = hash_hmac('sha256', $info . "\x01", $prk, true);
        
        return $okm; // 32 bytes for AES-256
    }
    
    /**
     * Encrypt data using AES-256-GCM (Authenticated Encryption)
     * 
     * Format: version(1) + nonce(12) + tag(16) + ciphertext
     * All encoded as base64 for storage
     * 
     * @param string $data Data to encrypt
     * @param string $aad Additional Authenticated Data (optional, not encrypted but authenticated)
     * @return string Encrypted data (base64 encoded)
     * @throws \RuntimeException If encryption fails
     */
    public static function encrypt(string $data, string $aad = ''): string
    {
        $key = self::getKey();
        $version = self::$currentKeyVersion;
        
        // Generate a random 96-bit (12 byte) nonce for GCM
        // GCM requires a unique nonce for each encryption with the same key
        $nonce = random_bytes(12);
        
        // Encrypt with GCM - this provides both encryption and authentication
        $tag = '';
        $ciphertext = openssl_encrypt(
            $data,
            self::$cipher,
            $key,
            OPENSSL_RAW_DATA,
            $nonce,
            $tag,
            $aad,
            self::$tagLength
        );
        
        if ($ciphertext === false) {
            throw new \RuntimeException('Encryption failed: ' . openssl_error_string());
        }
        
        // Pack: version (1 byte) + nonce (12 bytes) + tag (16 bytes) + ciphertext
        $packed = chr($version) . $nonce . $tag . $ciphertext;
        
        return base64_encode($packed);
    }
    
    /**
     * Decrypt data encrypted with encrypt()
     * Supports legacy CBC mode for backward compatibility
     * 
     * @param string $encryptedData Encrypted data (base64 encoded)
     * @param string $aad Additional Authenticated Data (must match encryption)
     * @return string|false Decrypted data or false on failure
     */
    public static function decrypt(string $encryptedData, string $aad = ''): string|false
    {
        $packed = base64_decode($encryptedData, true);
        
        if ($packed === false || strlen($packed) < 30) {
            // Try legacy format (CBC mode)
            return self::decryptLegacy($encryptedData);
        }
        
        // Extract version
        $version = ord($packed[0]);
        
        // Version 0 or very high version = likely legacy format
        if ($version === 0 || $version > 100) {
            return self::decryptLegacy($encryptedData);
        }
        
        // Get key for this version
        try {
            $key = self::getKey($version);
        } catch (\RuntimeException $e) {
            return false;
        }
        
        // Extract components
        $nonce = substr($packed, 1, 12);
        $tag = substr($packed, 13, self::$tagLength);
        $ciphertext = substr($packed, 13 + self::$tagLength);
        
        // Decrypt with GCM - authentication is automatic
        $decrypted = openssl_decrypt(
            $ciphertext,
            self::$cipher,
            $key,
            OPENSSL_RAW_DATA,
            $nonce,
            $tag,
            $aad
        );
        
        return $decrypted;
    }
    
    /**
     * Decrypt data encrypted with legacy CBC mode
     * For backward compatibility with older encrypted data
     * 
     * @param string $encryptedData Encrypted data (base64 encoded)
     * @return string|false Decrypted data or false on failure
     */
    protected static function decryptLegacy(string $encryptedData): string|false
    {
        $key = self::getKey(1);
        $data = base64_decode($encryptedData, true);
        
        if ($data === false) {
            return false;
        }
        
        $ivLength = openssl_cipher_iv_length(self::$legacyCipher);
        
        if (strlen($data) < $ivLength) {
            return false;
        }
        
        $iv = substr($data, 0, $ivLength);
        $encrypted = substr($data, $ivLength);
        
        // Legacy format stored base64-encoded ciphertext after IV
        $decrypted = openssl_decrypt($encrypted, self::$legacyCipher, $key, 0, $iv);
        
        if ($decrypted === false) {
            // Try with raw data flag
            $decrypted = openssl_decrypt($encrypted, self::$legacyCipher, $key, OPENSSL_RAW_DATA, $iv);
        }
        
        return $decrypted;
    }
    
    /**
     * Re-encrypt data with the current key version
     * Use this for key rotation - decrypt with old key, encrypt with new
     * 
     * @param string $encryptedData Data encrypted with any key version
     * @param string $aad Additional Authenticated Data
     * @return string|false Re-encrypted data with current key, or false on failure
     */
    public static function reencrypt(string $encryptedData, string $aad = ''): string|false
    {
        $decrypted = self::decrypt($encryptedData, $aad);
        
        if ($decrypted === false) {
            return false;
        }
        
        return self::encrypt($decrypted, $aad);
    }
    
    /**
     * Hash data using Argon2id (one-way, for passwords)
     * Argon2id is the winner of the Password Hashing Competition
     * and is resistant to both GPU and side-channel attacks
     * 
     * @param string $data Data to hash (typically password)
     * @param array $options Argon2 options (memory_cost, time_cost, threads)
     * @return string Password hash
     */
    public static function hash(string $data, array $options = []): string
    {
        $defaultOptions = [
            'memory_cost' => PASSWORD_ARGON2_DEFAULT_MEMORY_COST,
            'time_cost' => PASSWORD_ARGON2_DEFAULT_TIME_COST,
            'threads' => PASSWORD_ARGON2_DEFAULT_THREADS,
        ];
        
        $options = array_merge($defaultOptions, $options);
        
        // Use Argon2id if available (PHP 7.3+), fallback to Argon2i, then bcrypt
        if (defined('PASSWORD_ARGON2ID')) {
            return password_hash($data, PASSWORD_ARGON2ID, $options);
        } elseif (defined('PASSWORD_ARGON2I')) {
            return password_hash($data, PASSWORD_ARGON2I, $options);
        }
        
        return password_hash($data, PASSWORD_BCRYPT);
    }
    
    /**
     * Verify a password against a hash
     * 
     * @param string $data Plain text data to verify
     * @param string $hash Hash to verify against
     * @return bool True if matches
     */
    public static function verify(string $data, string $hash): bool
    {
        return password_verify($data, $hash);
    }
    
    /**
     * Check if a hash needs to be rehashed (algorithm upgrade)
     * Call this on login to automatically upgrade password hashes
     * 
     * @param string $hash Existing hash
     * @return bool True if rehashing is recommended
     */
    public static function needsRehash(string $hash): bool
    {
        $algorithm = defined('PASSWORD_ARGON2ID') ? PASSWORD_ARGON2ID : PASSWORD_DEFAULT;
        return password_needs_rehash($hash, $algorithm);
    }
    
    /**
     * Generate a cryptographically secure random token
     * 
     * @param int $length Number of random bytes (output will be 2x length in hex)
     * @return string Hex-encoded random token
     */
    public static function generateToken(int $length = 32): string
    {
        return bin2hex(random_bytes($length));
    }
    
    /**
     * Generate a URL-safe random token (base64url encoding)
     * 
     * @param int $length Number of random bytes
     * @return string URL-safe base64 encoded token
     */
    public static function generateUrlSafeToken(int $length = 32): string
    {
        $bytes = random_bytes($length);
        return rtrim(strtr(base64_encode($bytes), '+/', '-_'), '=');
    }
    
    /**
     * Constant-time string comparison
     * Prevents timing attacks when comparing secrets
     * 
     * @param string $a First string
     * @param string $b Second string
     * @return bool True if equal
     */
    public static function equals(string $a, string $b): bool
    {
        return hash_equals($a, $b);
    }
    
    /**
     * Generate an HMAC signature for data integrity
     * 
     * @param string $data Data to sign
     * @param string|null $key Key to use (null = use encryption key)
     * @return string HMAC signature (hex encoded)
     */
    public static function sign(string $data, ?string $key = null): string
    {
        $key = $key ?? self::deriveKey(self::getKey(), 'hmac_signing');
        return hash_hmac('sha256', $data, $key);
    }
    
    /**
     * Verify an HMAC signature
     * 
     * @param string $data Original data
     * @param string $signature Signature to verify
     * @param string|null $key Key to use (null = use encryption key)
     * @return bool True if signature is valid
     */
    public static function verifySignature(string $data, string $signature, ?string $key = null): bool
    {
        $expected = self::sign($data, $key);
        return self::equals($expected, $signature);
    }
    
    /**
     * Set the current key version for encryption
     * Use this when rotating keys
     * 
     * @param int $version New key version
     */
    public static function setKeyVersion(int $version): void
    {
        self::$currentKeyVersion = $version;
    }
    
    /**
     * Get the current key version
     * 
     * @return int Current key version
     */
    public static function getKeyVersion(): int
    {
        return self::$currentKeyVersion;
    }
    
    /**
     * Clear cached keys (use after key rotation)
     */
    public static function clearKeyCache(): void
    {
        self::$keys = [];
    }
}
