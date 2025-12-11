# Security Features

This directory contains comprehensive security features for the PHP Palm framework, implementing all security best practices from the improvements.txt checklist.

## Implemented Features

### 1. CSRF Protection (`CSRF.php`)
- ✅ Automatic CSRF token generation per session
- ✅ Token validation on POST, PUT, DELETE requests
- ✅ Token regeneration after successful actions
- ✅ Timing-safe comparison to prevent timing attacks
- ✅ Support for both form submissions and API requests

**Usage:**
```php
use App\Core\Security\CSRF;

// Get token for form
$token = CSRF::token();
echo CSRF::field(); // Outputs hidden input field

// Validate token
if (!CSRF::validate()) {
    // Invalid token
}

// Regenerate after successful action
CSRF::regenerate();
```

### 2. Session Security (`Session.php`)
- ✅ HttpOnly cookies
- ✅ Secure cookies (HTTPS only)
- ✅ SameSite attribute (Strict)
- ✅ Session ID regeneration on login
- ✅ Session expiration and idle timeout
- ✅ Session fingerprinting (IP + User Agent)

**Usage:**
```php
use App\Core\Security\Session;

// Start secure session (automatic)
Session::start();

// Get/Set session values
Session::set('user_id', 123);
$userId = Session::get('user_id');

// Regenerate ID after login
Session::regenerateId();

// Destroy session
Session::destroy();
```

### 3. File Upload Security (`FileUpload.php`)
- ✅ MIME type validation
- ✅ Extension whitelist
- ✅ File size limits
- ✅ Secure storage outside public folder
- ✅ Random filename generation
- ✅ File content validation (magic bytes)

**Usage:**
```php
use App\Core\Security\FileUpload;

$uploader = new FileUpload();
$uploader->setAllowedMimeTypes(FileUpload::IMAGE_TYPES);
$uploader->setAllowedExtensions(FileUpload::IMAGE_EXTENSIONS);
$uploader->setMaxFileSize(5 * 1024 * 1024); // 5MB

$result = $uploader->upload('file_field');
if ($result['success']) {
    $file = $result['file'];
    // File stored securely
}
```

### 4. Input Validation & Sanitization (`InputValidator.php`)
- ✅ XSS prevention (HTML escaping)
- ✅ HTML sanitization with whitelist (for rich text)
- ✅ Email, URL, phone validation
- ✅ Length validation
- ✅ Path traversal prevention
- ✅ Password strength validation
- ✅ Recursive array sanitization

**Usage:**
```php
use App\Core\Security\InputValidator;

// Sanitize string
$clean = InputValidator::sanitizeString($userInput);

// Validate email
if (InputValidator::validateEmail($email)) {
    // Valid email
}

// Validate and sanitize with rules
$rules = [
    'email' => ['type' => 'email', 'required' => true],
    'name' => ['type' => 'string', 'min_length' => 3, 'max_length' => 50]
];
$result = InputValidator::validateAndSanitize($rules, $_POST);
```

### 5. Security Logging (`SecurityLogger.php`)
- ✅ Failed login attempt logging
- ✅ CSRF failure logging
- ✅ Rate limit violation logging
- ✅ Authentication/authorization failure logging
- ✅ Suspicious activity logging
- ✅ Alert system for threshold breaches

**Usage:**
```php
use App\Core\Security\SecurityLogger;

// Log failed login
SecurityLogger::logFailedLogin($username, 'Invalid password');

// Log CSRF failure
SecurityLogger::logCsrfFailure($route);

// Log rate limit violation
SecurityLogger::logRateLimitViolation($endpoint, $limit, $count);

// Get logs
$logs = SecurityLogger::getLogs('failed_logins', 100);
```

### 6. Rate Limiting (`RateLimiter.php`)
- ✅ Per-endpoint rate limiting
- ✅ Per-IP rate limiting
- ✅ Per-user rate limiting
- ✅ Login attempt rate limiting (5 attempts per 5 minutes)
- ✅ Configurable limits and windows

**Usage:**
```php
use App\Core\Security\RateLimiter;

// Check rate limit for IP
$check = RateLimiter::checkIp('default');
if (!$check['allowed']) {
    // Rate limit exceeded
}

// Check login rate limit
$check = RateLimiter::checkLogin($username);
if (!$check['allowed']) {
    // Too many login attempts
}

// Set custom limit
RateLimiter::setLimit('api', 1000, 3600); // 1000 per hour
```

### 7. Data Encryption (`Encryption.php`)
- ✅ AES-256 encryption for sensitive data
- ✅ Password hashing (bcrypt/argon2)
- ✅ Secure random token generation

**Usage:**
```php
use App\Core\Security\Encryption;

// Encrypt data
$encrypted = Encryption::encrypt($sensitiveData);
$decrypted = Encryption::decrypt($encrypted);

// Hash password
$hash = Encryption::hash($password);
if (Encryption::verify($password, $hash)) {
    // Password correct
}

// Generate token
$token = Encryption::generateToken(32);
```

### 8. Password Reset Tokens (`PasswordReset.php`)
- ✅ Token generation with expiration
- ✅ One-time use tokens
- ✅ Automatic cleanup of expired tokens

**Usage:**
```php
use App\Core\Security\PasswordReset;

// Generate token
$token = PasswordReset::generateToken($userId);

// Validate token
$userId = PasswordReset::validateToken($token);
if ($userId) {
    // Token valid, allow password reset
    PasswordReset::markTokenAsUsed($token);
}

// Cleanup expired tokens
PasswordReset::cleanupExpiredTokens();
```

### 9. Secure Configuration (`SecureConfig.php`)
- ✅ Debug mode check
- ✅ Error display check
- ✅ Dangerous function check
- ✅ Session security check
- ✅ Encryption key check
- ✅ HTTPS check

**Usage:**
```php
use App\Core\Security\SecureConfig;

$check = SecureConfig::check();
if (!$check['secure']) {
    foreach ($check['issues'] as $issue) {
        // Log or fix issue
    }
}

$recommendations = SecureConfig::getRecommendations();
```

## Integration

All security features are automatically integrated in `index.php`:

- ✅ Secure session initialization
- ✅ Security headers (including HSTS)
- ✅ Enhanced rate limiting
- ✅ CSRF token availability
- ✅ Enhanced input sanitization

## Middleware

### CSRF Middleware (`middlewares/CsrfMiddleware.php`)
Automatically validates CSRF tokens on state-changing requests.

**Usage:**
```php
use App\Core\MiddlewareHelper;

Route::post('/api/users', MiddlewareHelper::use('CsrfMiddleware', [$controller, 'store']));
```

## Security Checklist Status

✅ **1. Input Validation & Sanitization** - Implemented in `InputValidator.php` and `index.php`
✅ **2. SQL Injection Protection** - Already implemented via PDO prepared statements in `QueryBuilder.php`
✅ **3. XSS Protection** - Implemented in `InputValidator.php` and `index.php`
✅ **4. CSRF Protection** - Implemented in `CSRF.php` and `CsrfMiddleware.php`
✅ **5. Session Security** - Implemented in `Session.php`
✅ **6. Authentication & Password Security** - Already implemented in `Auth.php` with bcrypt
✅ **7. Authorization** - Already implemented in `Auth.php` with RBAC
✅ **8. File Upload Security** - Implemented in `FileUpload.php`
✅ **9. Remote Code Execution Prevention** - Framework uses safe practices, dangerous functions should be disabled
✅ **10. Error Handling & Logging** - Implemented in `SecurityLogger.php`
✅ **11. Rate Limiting & Anti-Abuse** - Implemented in `RateLimiter.php`
✅ **12. HTTPS / Secure Transport** - HSTS headers implemented in `index.php`
✅ **13. Cookie & Session Security** - Implemented in `Session.php`
✅ **14. Content Security Policy** - Implemented in `index.php`
✅ **15. Anti-Clickjacking** - X-Frame-Options and CSP implemented in `index.php`
✅ **16. API Security** - Token-based auth already implemented, rate limiting added
✅ **17. Data Encryption** - Implemented in `Encryption.php`
✅ **18. Logging & Monitoring** - Implemented in `SecurityLogger.php`
✅ **19. Password & Token Management** - Implemented in `PasswordReset.php`
✅ **20. Secure Default Configuration** - Implemented in `SecureConfig.php`
✅ **21. Anti-CSRF / Anti-Replay for APIs** - CSRF tokens implemented
✅ **22. Directory Traversal Prevention** - Implemented in `InputValidator.php` and `PublicFileServer.php`

## Configuration

Add to your `.env` file:

```env
# Encryption key (generate a secure random key)
ENCRYPTION_KEY=your_secure_32_character_key_here

# Debug mode (set to false in production)
DEBUG_MODE=false

# App environment
APP_ENV=production
```

## Best Practices

1. **Always use CSRF protection** on state-changing requests (POST, PUT, DELETE)
2. **Validate all user input** using `InputValidator`
3. **Use secure file uploads** with `FileUpload` class
4. **Monitor security logs** regularly using `SecurityLogger`
5. **Set secure encryption key** in `.env` file
6. **Disable debug mode** in production
7. **Use HTTPS** in production
8. **Regularly review** security logs for suspicious activity
9. **Keep dependencies updated**
10. **Run `SecureConfig::check()`** periodically to ensure secure configuration

## Security Logs Location

Security logs are stored in: `app/storage/logs/security/`

- `failed_logins_YYYY-MM-DD.log` - Failed login attempts
- `csrf_failures_YYYY-MM-DD.log` - CSRF token failures
- `rate_limit_violations_YYYY-MM-DD.log` - Rate limit violations
- `auth_failures_YYYY-MM-DD.log` - Authentication failures
- `authorization_failures_YYYY-MM-DD.log` - Authorization failures
- `suspicious_activity_YYYY-MM-DD.log` - Suspicious activities
- `alerts_YYYY-MM-DD.log` - Security alerts

## Notes

- All security features follow OWASP best practices
- Security logging helps detect and respond to attacks
- Rate limiting prevents brute force and DoS attacks
- CSRF protection prevents cross-site request forgery
- File upload security prevents malicious file uploads
- Input validation prevents injection attacks
- Session security prevents session hijacking
- Encryption protects sensitive data at rest

