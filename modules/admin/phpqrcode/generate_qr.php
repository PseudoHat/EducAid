<?php
// Error reporting for debugging (disable in production)
error_reporting(E_ALL);
ini_set('display_errors', 0); // Don't display errors in image output
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/debug.txt');

// 0) Normalize input
$data = isset($_GET['data']) ? (string)$_GET['data'] : '';
if ($data !== '' && strlen($data) > 512) { $data = substr($data, 0, 512); }
if ($data === '') {
    http_response_code(400);
    header('Content-Type: text/plain; charset=UTF-8');
    echo 'Missing data parameter';
    exit;
}

// Common no-cache headers
$nocache = function(){
    header('Cache-Control: no-cache, no-store, must-revalidate');
    header('Pragma: no-cache');
    header('Expires: 0');
};

// 1) Preferred: chillerlan/php-qrcode (no GD required, SVG)
$autoload = realpath(__DIR__ . '/../../../vendor/autoload.php');
if ($autoload && file_exists($autoload)) {
    require_once $autoload;
    if (class_exists(\chillerlan\QRCode\QRCode::class)) {
        try {
            $nocache();
            header('Content-Type: image/svg+xml');
            $opts = new \chillerlan\QRCode\QROptions([
                'version' => 5,
                'eccLevel' => \chillerlan\QRCode\QRCode::ECC_L,
                'outputType' => \chillerlan\QRCode\QRCode::OUTPUT_MARKUP_SVG,
                'scale' => 4,
                'addQuietzone' => true,
            ]);
            echo (new \chillerlan\QRCode\QRCode($opts))->render($data);
            exit;
        } catch (\Throwable $e) {
            error_log('[QR] chillerlan generation error: ' . $e->getMessage());
            // fall through
        }
    }
}

// 2) Legacy library (requires GD)
$qrlib_path = __DIR__ . '/phpqrcode/qrlib.php';
if (file_exists($qrlib_path) && extension_loaded('gd') && function_exists('imagepng')) {
    require_once $qrlib_path;
    try {
        $nocache();
        header('Content-Type: image/png');
        QRcode::png($data, false, QR_ECLEVEL_L, 4, 2);
        exit;
    } catch (\Throwable $e) {
        error_log('[QR] legacy PHPQRCode error: ' . $e->getMessage());
        // fall through
    }
}

// 3) External fallback (keeps UX working)
$size = '250x250';
$fallback = 'https://api.qrserver.com/v1/create-qr-code/?size=' . $size . '&data=' . urlencode($data);
$nocache();
header('Location: ' . $fallback, true, 302);
error_log('[QR] external fallback used');
exit;
