<?php
// Error reporting for debugging (disable in production)
error_reporting(E_ALL);
ini_set('display_errors', 0); // Don't display errors in image output
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/debug.txt');

// Include QR library
$qrlib_path = __DIR__ . '/phpqrcode/qrlib.php';
if (!file_exists($qrlib_path)) {
    error_log("QR Library not found at: $qrlib_path");
    // Generate error image
    $im = imagecreatetruecolor(250, 250);
    $bg = imagecolorallocate($im, 255, 200, 200);
    $text = imagecolorallocate($im, 200, 0, 0);
    imagefill($im, 0, 0, $bg);
    imagestring($im, 3, 50, 115, "Library Error", $text);
    header('Content-Type: image/png');
    imagepng($im);
    imagedestroy($im);
    exit;
}

require_once($qrlib_path);

if (isset($_GET['data']) && !empty($_GET['data'])) {
    try {
        header('Content-Type: image/png');
        header('Cache-Control: no-cache, no-store, must-revalidate');
        header('Pragma: no-cache');
        header('Expires: 0');
        
        // Generate QR code directly to output (no file caching)
        QRcode::png($_GET['data'], false, QR_ECLEVEL_L, 4, 2);
        exit;
    } catch (Exception $e) {
        error_log("QR Generation Error: " . $e->getMessage());
        // Generate error image
        $im = imagecreatetruecolor(250, 250);
        $bg = imagecolorallocate($im, 255, 200, 200);
        $text = imagecolorallocate($im, 200, 0, 0);
        imagefill($im, 0, 0, $bg);
        imagestring($im, 3, 50, 115, "Generation Error", $text);
        header('Content-Type: image/png');
        imagepng($im);
        imagedestroy($im);
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
