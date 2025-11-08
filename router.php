<?php
/**
 * Router for PHP Built-in Server
 * Serves static files correctly and routes PHP requests
 */

// Load security headers first (before any output)
require_once __DIR__ . '/config/security_headers.php';

// Auto-initialize Railway volume symlink if needed
require_once __DIR__ . '/includes/railway_volume_init.php';

// Start session if not already started (required for timeout middleware)
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Apply session timeout middleware for authenticated pages
// Skip for login/public pages and static assets
$publicPages = ['/unified_login.php', '/website/index.php', '/website/landingpage.php'];
$requestUri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$isStaticAsset = preg_match('/\.(css|js|jpg|jpeg|png|gif|svg|woff|woff2|ttf|eot|ico)$/i', $requestUri);

if (!$isStaticAsset && !in_array($requestUri, $publicPages)) {
    require_once __DIR__ . '/includes/SessionTimeoutMiddleware.php';
    $timeoutMiddleware = new SessionTimeoutMiddleware();
    $timeoutStatus = $timeoutMiddleware->handle();
    
    // Store timeout status in global variable for access in pages
    $GLOBALS['session_timeout_status'] = $timeoutStatus;
}

// Strip /EducAid/ prefix if present (Railway deployment path)
if (strpos($requestUri, '/EducAid/') === 0) {
    $requestUri = substr($requestUri, strlen('/EducAid'));
}

// If requesting root, serve security verification or landing page
if ($requestUri === '/' || $requestUri === '') {
    chdir(__DIR__ . '/website');
    require __DIR__ . '/website/index.php';
    return true;
}

// Check for file in website directory first
$fileInWebsite = __DIR__ . '/website' . $requestUri;

// If it's a PHP file in website directory, execute it
if (file_exists($fileInWebsite) && pathinfo($fileInWebsite, PATHINFO_EXTENSION) === 'php') {
    chdir(__DIR__ . '/website');
    require $fileInWebsite;
    return true;
}

// If it's any other file (CSS, JS, images, etc.) in website or its subdirectories
if (file_exists($fileInWebsite) && !is_dir($fileInWebsite)) {
    // Let PHP's built-in server handle it (serve as-is)
    return false;
}

// Check in modules directory for PHP files
// e.g., /signup_test.php -> modules/admin/signup_test.php
$possibleModulePaths = [
    __DIR__ . '/modules/admin' . $requestUri,
    __DIR__ . '/modules/student' . $requestUri,
    __DIR__ . '/modules/super_admin' . $requestUri,
];

foreach ($possibleModulePaths as $modulePath) {
    if (file_exists($modulePath) && pathinfo($modulePath, PATHINFO_EXTENSION) === 'php') {
        chdir(dirname($modulePath));
        require $modulePath;
        return true;
    }
}

// Check in repo root for PHP files (like unified_login.php, etc.)
$fileInRoot = __DIR__ . $requestUri;
if (file_exists($fileInRoot) && pathinfo($fileInRoot, PATHINFO_EXTENSION) === 'php') {
    chdir(__DIR__);
    require $fileInRoot;
    return true;
}

// Also check in repo root for other assets
if (file_exists($fileInRoot) && !is_dir($fileInRoot)) {
    return false;
}

// 404 for everything else
http_response_code(404);
echo '404 Not Found: ' . htmlspecialchars($requestUri);
return true;
?>
