<?php
/**
 * App Directory Access Control Configuration
 * 
 * Controls access to the /app/ directory
 * Only developers should have access to this directory in production
 * 
 * USAGE:
 * - Set 'restrict_access' => false to disable access restriction
 * - Add developer IPs to 'allowed_ips' array
 * - Set 'allow_in_dev' => true to allow all access in development mode
 * - Use '*' in allowed_ips to allow all IPs (not recommended for production)
 * 
 * EXAMPLES:
 * 
 * 1. Disable restriction completely:
 *    'restrict_access' => false,
 * 
 * 2. Allow only specific IPs:
 *    'allowed_ips' => ['127.0.0.1', '192.168.1.100', '203.0.113.45'],
 * 
 * 3. Allow all IPs (not recommended):
 *    'allowed_ips' => ['*'],
 * 
 * 4. Custom error message:
 *    'error_message' => 'This area is restricted. Please contact the administrator.',
 */

return [
    // Enable/disable app directory access restriction
    'restrict_access' => true,
    
    // Allowed IP addresses (empty array = block all when restrict_access is true)
    // Add developer IPs here, e.g., ['127.0.0.1', '192.168.1.100']
    // Use '*' to allow all IPs (not recommended for production)
    'allowed_ips' => [
        '127.0.0.1',
        '::1', // IPv6 localhost
    ],
    
    // Allow access in development environment regardless of IP
    // When true, all IPs can access /app/ if APP_ENV is 'development' or 'dev'
    'allow_in_dev' => true,
    
    // Custom error message (optional, leave null for default)
    // null = uses default message: "Access to /app/ directory is restricted to developers only."
    'error_message' => null,
    
    // HTTP status code for blocked requests
    // Common codes: 403 (Forbidden), 404 (Not Found - hides existence), 401 (Unauthorized)
    'error_code' => 403,
];

