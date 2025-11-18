<?php
// Error reporting for debugging (disable in production)
error_reporting(E_ALL);
ini_set('display_errors', 0); // Don't display errors in image output
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/debug.txt');

// Include QR library
$qrlib_path = __DIR__ . '/phpqrcode/qrlib.php';
if (!file_exists($qrlib_path)) {
    error_log("[QR] Library not found at: $qrlib_path");
    http_response_code(500);
    header('Content-Type: text/plain; charset=UTF-8');
    echo "QR generation error: library missing";
    exit;
}

require_once($qrlib_path);

// Check GD availability (phpqrcode relies on GD image functions)
if (!extension_loaded('gd') || !function_exists('imagepng')) {
    error_log('[QR] GD extension missing');
    http_response_code(500);
    header('Content-Type: text/plain; charset=UTF-8');
    echo "QR generation error: PHP GD extension is not installed";
    exit;
}

if (isset($_GET['data']) && $_GET['data'] !== '') {
    try {
        $data = (string)$_GET['data'];
        // Basic input length guard
        if (strlen($data) > 256) {
            throw new Exception('Data too long');
        }

        header('Content-Type: image/png');
        header('Cache-Control: no-cache, no-store, must-revalidate');
        header('Pragma: no-cache');
        header('Expires: 0');

        // Generate QR code directly to output (no file caching)
        QRcode::png($data, false, QR_ECLEVEL_L, 4, 2);
        exit;
    } catch (Exception $e) {
        error_log('[QR] Generation Error: ' . $e->getMessage());
        http_response_code(500);
        header('Content-Type: text/plain; charset=UTF-8');
        echo 'QR generation failed: ' . $e->getMessage();
        exit;
    }
}

// Fallback: blank image with message
$im = imagecreatetruecolor(250, 250);
$bg = imagecolorallocate($im, 240, 240, 240);
$text = imagecolorallocate($im, 100, 100, 100);
imagefill($im, 0, 0, $bg);
imagestring($im, 3, 70, 115, "No QR Data", $text);
header('Content-Type: image/png');
imagepng($im);
imagedestroy($im);
exit;
