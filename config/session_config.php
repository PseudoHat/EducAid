<?php
/**
 * Secure Session Configuration
 * 
 * Configures PHP sessions with security best practices:
 * - HttpOnly flag (prevents JavaScript access)
 * - Secure flag (HTTPS only in production)
 * - SameSite attribute (CSRF protection)
 * - Cookie prefix (__Host- for maximum security)
 * 
 * Must be called BEFORE session_start()
 */

// Prevent direct access
if (!defined('SESSION_CONFIG_LOADED')) {
    define('SESSION_CONFIG_LOADED', true);
} else {
    return; // Already loaded
}

// Detect if we're on HTTPS
$isHttps = (
    (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ||
    (!empty($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] == 443) ||
    (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https') ||
    (!empty($_SERVER['HTTP_X_FORWARDED_SSL']) && $_SERVER['HTTP_X_FORWARDED_SSL'] === 'on')
);

// For local development, you can force HTTPS behavior
// Uncomment this for production:
// $isHttps = true;

/**
 * Configure session cookie parameters
 * PHP 7.3+ supports array syntax with SameSite
 */
if (PHP_VERSION_ID >= 70300) {
    // Modern PHP 7.3+ with SameSite support
    session_set_cookie_params([
        'lifetime' => 0,                    // Session cookie (expires when browser closes)
        'path' => '/',                      // Available throughout entire domain
        'domain' => '',                     // Current domain only
        'secure' => $isHttps,               // Only send over HTTPS (true in production)
        'httponly' => true,                 // Prevent JavaScript access (XSS protection)
        'samesite' => 'Lax'                 // CSRF protection (Lax allows top-level navigation)
    ]);
} else {
    // Fallback for older PHP versions (< 7.3)
    session_set_cookie_params(
        0,          // lifetime
        '/; samesite=Lax',  // path with SameSite
        '',         // domain
        $isHttps,   // secure
        true        // httponly
    );
}

/**
 * Additional session security settings
 */

// Use only cookies for session ID (no URL parameters)
ini_set('session.use_cookies', '1');
ini_set('session.use_only_cookies', '1');

// Prevent session ID in URLs
ini_set('session.use_trans_sid', '0');

// Use strict session ID checking
ini_set('session.use_strict_mode', '1');

// Regenerate session ID to prevent fixation attacks
// Note: Only regenerate on login, not on every request
// This is handled in unified_login.php after successful authentication

// Set session name with __Host- prefix for additional security
// Note: __Host- prefix requires:
// - Secure flag to be true
// - Path to be /
// - No domain attribute
if ($isHttps) {
    // Use __Host- prefix in production (HTTPS only)
    session_name('__Host-PHPSESSID');
} else {
    // For local development without HTTPS, use standard name
    session_name('PHPSESSID');
}

// Session garbage collection
ini_set('session.gc_probability', '1');
ini_set('session.gc_divisor', '100');
ini_set('session.gc_maxlifetime', '28800'); // 8 hours (matches SESSION_ABSOLUTE_TIMEOUT_HOURS * 3600)

// Session entropy (randomness)
ini_set('session.entropy_length', '32');
ini_set('session.entropy_file', '/dev/urandom');

// Use more secure hash algorithm for session IDs
ini_set('session.hash_function', 'sha256');
ini_set('session.hash_bits_per_character', '5');

/**
 * Log configuration for debugging (remove in production)
 */
if (getenv('APP_ENV') !== 'production') {
    error_log("Session Config: secure=" . ($isHttps ? 'true' : 'false') . 
              ", httponly=true, samesite=Lax, name=" . session_name());
}
