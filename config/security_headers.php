<?php
/**
 * Security Headers Utilities
 * 
 * NOTE: Primary security headers (CSP, HSTS, X-Frame-Options, etc.) are managed by Cloudflare.
 * This file provides helper functions for special cases and localhost development.
 * 
 * Usage: require_once __DIR__ . '/config/security_headers.php';
 */

// Prevent direct access
if (!defined('SECURITY_HEADERS_LOADED')) {
    define('SECURITY_HEADERS_LOADED', true);
} else {
    return; // Already loaded
}

// HYBRID APPROACH: Cloudflare handles some headers, PHP handles CSP and complex policies
// Cloudflare manages: HSTS, TLS, OCSP
// PHP manages: CSP, Permissions-Policy, Referrer-Policy (for fine-grained control)

// Only set headers if not already sent
if (!headers_sent()) {
    
    // 1. Strict-Transport-Security (HSTS)
    // DISABLED - Managed by Cloudflare Transform Rules
    // header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
    
    // 2. Content-Security-Policy (CSP)
    // Prevents XSS by whitelisting allowed content sources
    // Adjust this based on your actual third-party services
    $csp = implode('; ', [
        "default-src 'self'",
        "script-src 'self' 'unsafe-inline' 'unsafe-eval' https://www.google.com https://www.gstatic.com https://cdn.jsdelivr.net https://cdnjs.cloudflare.com https://ajax.cloudflare.com",
        "style-src 'self' 'unsafe-inline' https://fonts.googleapis.com https://cdn.jsdelivr.net https://cdnjs.cloudflare.com",
        "font-src 'self' https://fonts.gstatic.com https://cdn.jsdelivr.net https://cdnjs.cloudflare.com data:",
        "img-src 'self' data: https: blob:",
        "connect-src 'self' https://cloudflareinsights.com",
        "frame-src 'self' https://www.google.com https://challenges.cloudflare.com",
        "object-src 'none'",
        "base-uri 'self'",
        "form-action 'self'",
        "frame-ancestors 'self'",
        "upgrade-insecure-requests"
    ]);
    header("Content-Security-Policy: {$csp}");
    
    // 3. X-Frame-Options
    // Could be managed by Cloudflare, but PHP gives more control
    header('X-Frame-Options: SAMEORIGIN');
    
    // 4. X-Content-Type-Options
    // Simple header, PHP is fine
    header('X-Content-Type-Options: nosniff');
    
    // 5. Referrer-Policy
    // Controls how much referrer information is included with requests
    // strict-origin-when-cross-origin = full URL for same origin, origin only for cross-origin
    header('Referrer-Policy: strict-origin-when-cross-origin');
    
    // 6. Permissions-Policy (replaces Feature-Policy)
    // PHP manages this for per-page control (e.g., camera for QR scanner)
    // Check if ALLOW_CAMERA is defined before this file is included
    $allowCamera = defined('ALLOW_CAMERA') && constant('ALLOW_CAMERA') === true;
    $permissionsDirectives = [
        'geolocation=()',
        'microphone=()',
        $allowCamera ? 'camera=(self)' : 'camera=()',
        'payment=()',
        'usb=()',
        'magnetometer=()',
        'gyroscope=()',
        'accelerometer=()'
    ];
    $permissions = implode(', ', $permissionsDirectives);
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