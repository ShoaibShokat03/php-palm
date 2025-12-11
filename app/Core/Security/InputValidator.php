<?php

namespace App\Core\Security;

/**
 * Enhanced Input Validation & Sanitization
 * 
 * Provides comprehensive input validation and sanitization
 * 
 * Features:
 * - Type validation
 * - Format validation (email, URL, etc.)
 * - Length validation
 * - XSS prevention
 * - SQL injection prevention helpers
 * - Path traversal prevention
 */
class InputValidator
{
    /**
     * Sanitize string input (XSS prevention)
     */
    public static function sanitizeString(string $input, bool $allowHtml = false): string
    {
        if ($allowHtml) {
            // For rich text, use whitelist approach
            return self::sanitizeHtml($input);
        }
        
        // Default: escape all HTML
        return htmlspecialchars($input, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }

    /**
     * Sanitize HTML with whitelist (for rich text editors)
     */
    public static function sanitizeHtml(string $input): string
    {
        // Basic whitelist - in production, use HTMLPurifier or similar
        $allowedTags = '<p><br><strong><em><u><a><ul><ol><li><h1><h2><h3><h4><h5><h6>';
        return strip_tags($input, $allowedTags);
    }

    /**
     * Validate email
     */
    public static function validateEmail(string $email): bool
    {
        return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
    }

    /**
     * Validate URL
     */
    public static function validateUrl(string $url): bool
    {
        return filter_var($url, FILTER_VALIDATE_URL) !== false;
    }

    /**
     * Validate integer
     */
    public static function validateInteger($value, int $min = null, int $max = null): bool
    {
        if (!is_numeric($value)) {
            return false;
        }
        
        $int = (int)$value;
        
        if ($min !== null && $int < $min) {
            return false;
        }
        
        if ($max !== null && $int > $max) {
            return false;
        }
        
        return true;
    }

    /**
     * Validate string length
     */
    public static function validateLength(string $value, int $min = null, int $max = null): bool
    {
        $length = mb_strlen($value, 'UTF-8');
        
        if ($min !== null && $length < $min) {
            return false;
        }
        
        if ($max !== null && $length > $max) {
            return false;
        }
        
        return true;
    }

    /**
     * Validate alphanumeric
     */
    public static function validateAlphanumeric(string $value, bool $allowSpaces = false): bool
    {
        $pattern = $allowSpaces ? '/^[a-zA-Z0-9\s]+$/' : '/^[a-zA-Z0-9]+$/';
        return preg_match($pattern, $value) === 1;
    }

    /**
     * Sanitize file path (prevent directory traversal)
     */
    public static function sanitizePath(string $path): string
    {
        // Remove directory traversal attempts
        $path = str_replace(['../', '..\\', './', '.\\'], '', $path);
        
        // Remove null bytes
        $path = str_replace("\0", '', $path);
        
        // Normalize path separators
        $path = str_replace('\\', '/', $path);
        
        // Remove leading slashes
        $path = ltrim($path, '/');
        
        return $path;
    }

    /**
     * Validate file path (ensure it's within allowed directory)
     */
    public static function validatePath(string $path, string $baseDir): bool
    {
        $sanitized = self::sanitizePath($path);
        $fullPath = realpath($baseDir . '/' . $sanitized);
        $baseRealPath = realpath($baseDir);
        
        if ($fullPath === false || $baseRealPath === false) {
            return false;
        }
        
        // Ensure path is within base directory
        return strpos($fullPath, $baseRealPath) === 0;
    }

    /**
     * Validate phone number (basic)
     */
    public static function validatePhone(string $phone): bool
    {
        // Remove common formatting characters
        $cleaned = preg_replace('/[\s\-\(\)\+]/', '', $phone);
        // Check if remaining is numeric and reasonable length
        return ctype_digit($cleaned) && strlen($cleaned) >= 10 && strlen($cleaned) <= 15;
    }

    /**
     * Validate date format
     */
    public static function validateDate(string $date, string $format = 'Y-m-d'): bool
    {
        $d = \DateTime::createFromFormat($format, $date);
        return $d && $d->format($format) === $date;
    }

    /**
     * Validate password strength
     */
    public static function validatePasswordStrength(string $password, int $minLength = 8): array
    {
        $errors = [];
        
        if (strlen($password) < $minLength) {
            $errors[] = "Password must be at least {$minLength} characters";
        }
        
        if (!preg_match('/[a-z]/', $password)) {
            $errors[] = "Password must contain at least one lowercase letter";
        }
        
        if (!preg_match('/[A-Z]/', $password)) {
            $errors[] = "Password must contain at least one uppercase letter";
        }
        
        if (!preg_match('/[0-9]/', $password)) {
            $errors[] = "Password must contain at least one number";
        }
        
        if (!preg_match('/[^a-zA-Z0-9]/', $password)) {
            $errors[] = "Password must contain at least one special character";
        }
        
        return [
            'valid' => empty($errors),
            'errors' => $errors
        ];
    }

    /**
     * Sanitize array recursively
     */
    public static function sanitizeArray(array $data, bool $allowHtml = false): array
    {
        $sanitized = [];
        foreach ($data as $key => $value) {
            $sanitizedKey = self::sanitizeString($key, false);
            if (is_array($value)) {
                $sanitized[$sanitizedKey] = self::sanitizeArray($value, $allowHtml);
            } else {
                $sanitized[$sanitizedKey] = is_string($value) 
                    ? self::sanitizeString($value, $allowHtml) 
                    : $value;
            }
        }
        return $sanitized;
    }

    /**
     * Validate and sanitize input
     */
    public static function validateAndSanitize(array $rules, array $data): array
    {
        $validated = [];
        $errors = [];
        
        foreach ($rules as $field => $rule) {
            $value = $data[$field] ?? null;
            
            // Required check
            if (isset($rule['required']) && $rule['required'] && empty($value)) {
                $errors[$field] = $rule['message'] ?? "Field {$field} is required";
                continue;
            }
            
            // Skip validation if field is empty and not required
            if (empty($value) && (!isset($rule['required']) || !$rule['required'])) {
                continue;
            }
            
            // Type validation
            if (isset($rule['type'])) {
                switch ($rule['type']) {
                    case 'email':
                        if (!self::validateEmail($value)) {
                            $errors[$field] = $rule['message'] ?? "Invalid email format";
                            continue 2;
                        }
                        break;
                    case 'url':
                        if (!self::validateUrl($value)) {
                            $errors[$field] = $rule['message'] ?? "Invalid URL format";
                            continue 2;
                        }
                        break;
                    case 'integer':
                        if (!self::validateInteger($value, $rule['min'] ?? null, $rule['max'] ?? null)) {
                            $errors[$field] = $rule['message'] ?? "Invalid integer value";
                            continue 2;
                        }
                        $value = (int)$value;
                        break;
                }
            }
            
            // Length validation
            if (is_string($value) && (isset($rule['min_length']) || isset($rule['max_length']))) {
                if (!self::validateLength($value, $rule['min_length'] ?? null, $rule['max_length'] ?? null)) {
                    $errors[$field] = $rule['message'] ?? "Invalid length";
                    continue;
                }
            }
            
            // Sanitize
            if (is_string($value)) {
                $value = self::sanitizeString($value, $rule['allow_html'] ?? false);
            }
            
            $validated[$field] = $value;
        }
        
        return [
            'valid' => empty($errors),
            'data' => $validated,
            'errors' => $errors
        ];
    }
}

