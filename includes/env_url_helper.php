<?php
/**
 * Environment URL Helper
 * Provides functions to generate environment-aware absolute URLs.
 * Localhost (development) expects application under /EducAid, hosted (e.g. Railway) root deployed.
 */
function getUnifiedLoginUrl(): string {
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $isLocal = stripos($host, 'localhost') !== false || preg_match('/^127\.0\.0\.1/', $host);
    $prefix = $isLocal ? '/EducAid' : '';
    return $scheme . '://' . $host . $prefix . '/unified_login.php';
}

/**
 * Build absolute URL from relative path using same environment rules.
 */
function buildAbsoluteUrl(string $relative): string {
    if (preg_match('#^https?://#i', $relative)) return $relative; // already absolute
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $isLocal = stripos($host, 'localhost') !== false || preg_match('/^127\.0\.0\.1/', $host);
    $prefix = $isLocal ? '/EducAid/' : '/';
    $normalized = ltrim($relative, '/');
    return $scheme . '://' . $host . $prefix . $normalized;
}
