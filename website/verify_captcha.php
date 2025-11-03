<?php
/**
 * CAPTCHA Verification Handler for Security Page
 *
 * Uses curl to POST to Google's siteverify endpoint and logs the verification response
 * for debugging so we can see why verification fails (invalid-secret, invalid-domain, etc.).
 */

// Include reCAPTCHA configuration
require_once __DIR__ . '/../config/recaptcha_v2_config.php';

// Start session
session_start();

// Set JSON response header
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

// Get reCAPTCHA response
$recaptchaResponse = $_POST['g-recaptcha-response'] ?? '';

if (empty($recaptchaResponse)) {
    echo json_encode(['success' => false, 'message' => 'Please complete the CAPTCHA verification']);
    exit;
}

// Basic config check
if (!defined('RECAPTCHA_V2_SECRET_KEY') || RECAPTCHA_V2_SECRET_KEY === 'D' || RECAPTCHA_V2_SECRET_KEY === '') {
    // Helpful debug message if secret not set
    error_log('reCAPTCHA secret key not configured or using placeholder. Set RECAPTCHA_V2_SECRET_KEY in environment.');
    echo json_encode(['success' => false, 'message' => 'Server not configured for reCAPTCHA verification.']);
    exit;
}

// Prepare POST to Google's verify endpoint using curl (more robust than file_get_contents)
$verifyUrl = 'https://www.google.com/recaptcha/api/siteverify';
$postFields = http_build_query([
    'secret' => RECAPTCHA_V2_SECRET_KEY,
    'response' => $recaptchaResponse,
    'remoteip' => $_SERVER['REMOTE_ADDR'] ?? ''
]);

$ch = curl_init($verifyUrl);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, $postFields);
curl_setopt($ch, CURLOPT_TIMEOUT, 10);

$verifyResponse = curl_exec($ch);
$curlErr = curl_error($ch);
$httpStatus = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

$debugLogDir = __DIR__ . '/../data';
if (!is_dir($debugLogDir)) {
    @mkdir($debugLogDir, 0755, true);
}
$debugLogFile = $debugLogDir . '/security_verifications_debug.log';

// Log the raw response for debugging
$logEntry = date('c') . " | remote_ip=" . ($_SERVER['REMOTE_ADDR'] ?? 'unknown') . " | http_status=$httpStatus" . PHP_EOL;
if ($curlErr) {
    $logEntry .= "curl_error=" . $curlErr . PHP_EOL;
}
$logEntry .= "response_raw=" . ($verifyResponse ?? 'NULL') . PHP_EOL . str_repeat('-', 80) . PHP_EOL;
file_put_contents($debugLogFile, $logEntry, FILE_APPEND | LOCK_EX);

if ($curlErr || !$verifyResponse) {
    echo json_encode(['success' => false, 'message' => 'Unable to verify CAPTCHA (network error).']);
    exit;
}

$verifyResult = json_decode($verifyResponse, true);
if (!is_array($verifyResult)) {
    echo json_encode(['success' => false, 'message' => 'Invalid response from verification server.']);
    exit;
}

// If verification failed, include Google's error codes in response for debugging
if (empty($verifyResult['success']) || $verifyResult['success'] !== true) {
    $errors = $verifyResult['error-codes'] ?? [];
    $msg = 'CAPTCHA verification failed. ' . (!empty($errors) ? 'Errors: ' . implode(', ', $errors) : 'No details.');
    echo json_encode(['success' => false, 'message' => $msg, 'debug' => $verifyResult]);
    exit;
}

// Success — set session and log a concise entry
$_SESSION['captcha_verified'] = true;
$_SESSION['captcha_verified_time'] = time();

$logFile = __DIR__ . '/../data/security_verifications.log';
$logEntry = date('Y-m-d H:i:s') . " - IP: " . ($_SERVER['REMOTE_ADDR'] ?? 'unknown') . " - UA: " . ($_SERVER['HTTP_USER_AGENT'] ?? 'unknown') . PHP_EOL;
file_put_contents($logFile, $logEntry, FILE_APPEND | LOCK_EX);

echo json_encode([
    'success' => true,
    'message' => 'Verification successful. Redirecting to EducAid...'
]);

?>