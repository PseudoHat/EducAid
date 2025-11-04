<?php
/**
 * Router for PHP Built-in Server
 * Serves static files correctly and routes PHP requests
 */

$requestUri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$requestedFile = __DIR__ . '/website' . $requestUri;

// If requesting root, serve index
if ($requestUri === '/' || $requestUri === '') {
    require __DIR__ . '/website/index.php';
    return true;
}

// If file exists and is not a PHP file, serve it as-is
if (file_exists($requestedFile) && !is_dir($requestedFile)) {
    // Let PHP's built-in server handle static files
    return false;
}

// Check if PHP file exists in website directory
$phpFile = __DIR__ . '/website' . $requestUri;
if (file_exists($phpFile) && pathinfo($phpFile, PATHINFO_EXTENSION) === 'php') {
    require $phpFile;
    return true;
}

// 404
http_response_code(404);
echo '404 Not Found';
return true;
?>
