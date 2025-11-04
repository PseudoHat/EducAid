<?php
/**
 * Router for PHP Built-in Server
 * Serves static files correctly and routes PHP requests
 */

$requestUri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

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

// Also check in repo root for assets (some may be referenced from root)
$fileInRoot = __DIR__ . $requestUri;
if (file_exists($fileInRoot) && !is_dir($fileInRoot)) {
    return false;
}

// 404 for everything else
http_response_code(404);
echo '404 Not Found: ' . htmlspecialchars($requestUri);
return true;
?>
