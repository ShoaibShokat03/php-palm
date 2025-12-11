<?php

namespace App\Core\Security;

/**
 * Data Encryption Helper
 * 
 * Provides encryption/decryption for sensitive data
 * 
 * Uses AES-256 encryption
 */
class Encryption
{
    protected static string $cipher = 'AES-256-CBC';
    protected static ?string $key = null;

    /**
     * Get encryption key from environment or generate one
     */
    protected static function getKey(): string
    {
        if (self::$key !== null) {
            return self::$key;
        }

        // Get key from environment
        $key = $_ENV['ENCRYPTION_KEY'] ?? null;
        
        if (empty($key)) {
            // Generate a key (should be set in .env in production)
            $key = hash('sha256', 'default_key_change_in_production');
        }
        
        // Ensure key is 32 bytes for AES-256
        if (strlen($key) < 32) {
            $key = hash('sha256', $key);
        }
        
        self::$key = substr($key, 0, 32);
        return self::$key;
    }

    /**
     * Encrypt data
     * 
     * @param string $data Data to encrypt
     * @return string Encrypted data (base64 encoded)
     */
    public static function encrypt(string $data): string
    {
        $key = self::getKey();
        $ivLength = openssl_cipher_iv_length(self::$cipher);
        $iv = openssl_random_pseudo_bytes($ivLength);
        
        $encrypted = openssl_encrypt($data, self::$cipher, $key, 0, $iv);
        
        // Prepend IV to encrypted data
        return base64_encode($iv . $encrypted);
    }

    /**
     * Decrypt data
     * 
     * @param string $encryptedData Encrypted data (base64 encoded)
     * @return string|false Decrypted data or false on failure
     */
    public static function decrypt(string $encryptedData)
    {
        $key = self::getKey();
        $data = base64_decode($encryptedData, true);
        
        if ($data === false) {
            return false;
        }
        
        $ivLength = openssl_cipher_iv_length(self::$cipher);
        $iv = substr($data, 0, $ivLength);
        $encrypted = substr($data, $ivLength);
        
        return openssl_decrypt($encrypted, self::$cipher, $key, 0, $iv);
    }

    /**
     * Hash data (one-way, for passwords, etc.)
     */
    public static function hash(string $data, string $algorithm = PASSWORD_DEFAULT): string
    {
        return password_hash($data, $algorithm);
    }

    /**
     * Verify hash
     */
    public static function verify(string $data, string $hash): bool
    {
        return password_verify($data, $hash);
    }

    /**
     * Generate secure random token
     */
    public static function generateToken(int $length = 32): string
    {
        return bin2hex(random_bytes($length));
    }
}

