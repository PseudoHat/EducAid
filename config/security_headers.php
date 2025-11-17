<?php
/**
 * Security Headers Configuration
 * 
 * Implements recommended HTTP security headers to protect against:
 * - XSS attacks
 * - Clickjacking
 * - MIME sniffing
 * - Information leakage
 * - Referrer leakage
 * 
 * Include this file at the top of entry points (before any output).
 * Usage: require_once __DIR__ . '/config/security_headers.php';
 */

// Prevent direct access
if (!defined('SECURITY_HEADERS_LOADED')) {
    define('SECURITY_HEADERS_LOADED', true);
} else {
    return; // Already loaded
}

// DISABLED: All security headers are managed by Cloudflare
// If you need to re-enable PHP headers, uncomment the code below

/*
// Only set headers if not already sent
if (!headers_sent()) {
    
    // 1. Strict-Transport-Security (HSTS)
    // Forces HTTPS for 1 year, includes subdomains
    // Prevents SSL stripping attacks
    header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
    
    // 2. Content-Security-Policy (CSP)
    // Prevents XSS by whitelisting allowed content sources
    // Adjust this based on your actual third-party services
    $csp = implode('; ', [
        "default-src 'self'",
        "script-src 'self' 'unsafe-inline' 'unsafe-eval' https://www.google.com https://www.gstatic.com https://cdn.jsdelivr.net",
        "style-src 'self' 'unsafe-inline' https://fonts.googleapis.com https://cdn.jsdelivr.net",
        "font-src 'self' https://fonts.gstatic.com https://cdn.jsdelivr.net data:",
        "img-src 'self' data: https: blob:",
        "connect-src 'self'",
        "frame-src 'self' https://www.google.com",
        "object-src 'none'",
        "base-uri 'self'",
        "form-action 'self'",
        "frame-ancestors 'self'",
        "upgrade-insecure-requests"
    ]);
    header("Content-Security-Policy: {$csp}");
    
    // 3. X-Frame-Options
    // Prevents clickjacking by disallowing embedding in iframes
    // SAMEORIGIN allows embedding on same domain only
    header('X-Frame-Options: SAMEORIGIN');
    
    // 4. X-Content-Type-Options
    // Prevents MIME sniffing attacks
    // Forces browser to respect declared content types
    header('X-Content-Type-Options: nosniff');
    
    // 5. Referrer-Policy
    // Controls how much referrer information is included with requests
    // strict-origin-when-cross-origin = full URL for same origin, origin only for cross-origin
    header('Referrer-Policy: strict-origin-when-cross-origin');
    
    // 6. Permissions-Policy (replaces Feature-Policy)
    // Restricts access to browser features and APIs
    // Denies access to sensitive features unless explicitly needed
    $permissions = implode(', ', [
        'geolocation=()',
        'microphone=()',
        'camera=()',
        'payment=()',
        'usb=()',
        'magnetometer=()',
        'gyroscope=()',
        'accelerometer=()'
    ]);
    header("Permissions-Policy: {$permissions}");
    
    // BONUS: Additional security headers
    
    // X-XSS-Protection (legacy, but still useful for old browsers)
    // Modern CSP is better, but this adds defense in depth
    header('X-XSS-Protection: 1; mode=block');
    
    // X-Permitted-Cross-Domain-Policies
    // Prevents Adobe Flash and PDF from loading data cross-domain
    header('X-Permitted-Cross-Domain-Policies: none');
    
    // Remove server information leakage
    header_remove('X-Powered-By');
    header_remove('Server');
    
    // Cache control for sensitive pages (can be overridden per page)
    // Uncomment if you want to prevent caching of sensitive data by default
    // header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    // header('Pragma: no-cache');
    // header('Expires: 0');
}
*/

/**
 * Helper function to add CSP nonce for inline scripts
 * Call this before your <script> tags and use the nonce in your inline scripts
 * 
 * Usage:
 *   $nonce = generateCSPNonce();
 *   <script nonce="<?= $nonce ?>">...</script>
 */
function generateCSPNonce() {
    static $nonce = null;
    if ($nonce === null) {
        $nonce = base64_encode(random_bytes(16));
        // Update CSP header to include nonce
        if (!headers_sent()) {
            // Note: This is a simplified version. For production, you'd want to
            // regenerate the entire CSP header with the nonce included.
            header("Content-Security-Policy: script-src 'nonce-{$nonce}' 'self' 'unsafe-inline' https://www.google.com https://www.gstatic.com https://cdn.jsdelivr.net; default-src 'self'", true);
        }
    }
    return $nonce;
}

/**
 * Helper to override default headers for specific pages
 * Example: Allow framing for embedded content
 */
function allowFraming($domains = 'self') {
    if (!headers_sent()) {
        if ($domains === '*') {
            header('X-Frame-Options: ALLOWALL', true);
        } else {
            header("X-Frame-Options: ALLOW-FROM {$domains}", true);
        }
    }
}

/**
 * Helper to set public caching headers (for static assets)
 */
function setPublicCache($maxAge = 86400) {
    if (!headers_sent()) {
        header("Cache-Control: public, max-age={$maxAge}, immutable", true);
    }
}

/**
 * Helper to set no-cache headers (for sensitive pages)
 */
function setNoCache() {
    if (!headers_sent()) {
        header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0', true);
        header('Pragma: no-cache', true);
        header('Expires: 0', true);
    }
}