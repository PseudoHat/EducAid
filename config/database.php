<?php
// Database configuration now sourced from environment variables (.env)
// Load environment first (skip if file missing, e.g., on Railway)
if (file_exists(__DIR__ . '/env.php')) {
    require_once __DIR__ . '/env.php';
}

// Check if pgsql extension is available
if (!function_exists('pg_connect')) {
    error_log('FATAL: PostgreSQL extension (pgsql) is not installed. Enable ext-pgsql in php.ini or install php-pgsql package.');
    http_response_code(500);
    die('Server error: PostgreSQL extension not available. Contact administrator.');
}

// Read DATABASE_PUBLIC_URL from Railway, then fall back to individual env vars or local defaults
$databaseUrl = getenv('DATABASE_PUBLIC_URL');

// Debug: log if DATABASE_PUBLIC_URL was found
if ($databaseUrl) {
    error_log('DATABASE_PUBLIC_URL found, parsing connection string');
    // Parse DATABASE_PUBLIC_URL: postgresql://user:pass@host:port/dbname
    $parts = parse_url($databaseUrl);
    $dbHost = $parts['host'] ?? 'localhost';
    $dbPort = $parts['port'] ?? 5432;
    $dbName = ltrim($parts['path'] ?? '/railway', '/');
    $dbUser = $parts['user'] ?? 'postgres';
    $dbPass = $parts['pass'] ?? '';
} else {
    error_log('DATABASE_PUBLIC_URL not found, using fallback/local config');
    // Fallback: individual env vars or local defaults
    $dbHost = getenv('DB_HOST') ?: 'localhost';
    $dbName = getenv('DB_NAME') ?: 'educaid';
    $dbUser = getenv('DB_USER') ?: 'postgres';
    $dbPass = getenv('DB_PASSWORD') ?: 'postgres_dev_2025'; // Default for development
    $dbPort = getenv('DB_PORT') ?: '5432';
}

// Add connection timeout to prevent 30-second hangs
$connString = sprintf(
    'host=%s port=%s dbname=%s user=%s password=%s connect_timeout=10',
    $dbHost,
    $dbPort,
    $dbName,
    $dbUser,
    $dbPass
);

error_log(sprintf('Attempting DB connection: host=%s port=%s dbname=%s user=%s', $dbHost, $dbPort, $dbName, $dbUser));
$connection = @pg_connect($connString);
if (!$connection) {
    error_log(sprintf('Database connection failed: host=%s port=%s dbname=%s user=%s', $dbHost, $dbPort, $dbName, $dbUser));
    error_log('Last PostgreSQL error: ' . (function_exists('pg_last_error') ? pg_last_error() : 'N/A'));
    // Set connection to null explicitly for safety checks
    $connection = null;
    http_response_code(500);
    die('Database connection failed. Check server logs.');
}

// Log successful connection (no sensitive data)
error_log(sprintf('Database connected: host=%s port=%s dbname=%s', $dbHost, $dbPort, $dbName));
