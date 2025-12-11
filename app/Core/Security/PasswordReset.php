<?php

namespace App\Core\Security;

use App\Database\Db;

/**
 * Password Reset Token Management
 * 
 * Manages password reset tokens with expiration and one-time use
 */
class PasswordReset
{
    protected static int $tokenExpiry = 3600; // 1 hour
    protected static string $tableName = 'password_reset_tokens';

    /**
     * Generate password reset token
     */
    public static function generateToken(int $userId): string
    {
        $token = Encryption::generateToken(32);
        $expiresAt = date('Y-m-d H:i:s', time() + self::$tokenExpiry);
        
        $db = new Db();
        $db->connect();
        
        // Delete existing tokens for user
        $db->query("DELETE FROM " . self::$tableName . " WHERE user_id = " . (int)$userId);
        
        // Insert new token
        $tokenHash = hash('sha256', $token);
        $db->query("INSERT INTO " . self::$tableName . " (user_id, token_hash, expires_at, created_at) VALUES (" . 
            (int)$userId . ", '" . $db->escape($tokenHash) . "', '" . $expiresAt . "', NOW())");
        
        return $token;
    }

    /**
     * Validate password reset token
     */
    public static function validateToken(string $token): ?int
    {
        $tokenHash = hash('sha256', $token);
        
        $db = new Db();
        $db->connect();
        
        $result = $db->query("SELECT user_id, expires_at FROM " . self::$tableName . 
            " WHERE token_hash = '" . $db->escape($tokenHash) . "' AND used = 0 LIMIT 1");
        
        if (!$result) {
            return null;
        }
        
        $row = $result->fetch_assoc();
        if (!$row) {
            return null;
        }
        
        // Check if token expired
        if (strtotime($row['expires_at']) < time()) {
            // Delete expired token
            $db->query("DELETE FROM " . self::$tableName . " WHERE token_hash = '" . $db->escape($tokenHash) . "'");
            return null;
        }
        
        return (int)$row['user_id'];
    }

    /**
     * Mark token as used
     */
    public static function markTokenAsUsed(string $token): void
    {
        $tokenHash = hash('sha256', $token);
        
        $db = new Db();
        $db->connect();
        
        $db->query("UPDATE " . self::$tableName . " SET used = 1 WHERE token_hash = '" . $db->escape($tokenHash) . "'");
    }

    /**
     * Clean up expired tokens
     */
    public static function cleanupExpiredTokens(): void
    {
        $db = new Db();
        $db->connect();
        
        $db->query("DELETE FROM " . self::$tableName . " WHERE expires_at < NOW() OR used = 1");
    }

    /**
     * Set token expiry time
     */
    public static function setTokenExpiry(int $seconds): void
    {
        self::$tokenExpiry = $seconds;
    }
}

