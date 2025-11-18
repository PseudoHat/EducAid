<?php
// Test script to verify Gemini API key is working
header('Content-Type: application/json');

// Load environment
require_once __DIR__ . '/../config/env.php';

$API_KEY = getenv('GEMINI_API_KEY') ?: '';

if ($API_KEY === '') {
    echo json_encode([
        'status' => 'error',
        'message' => 'API key not found in environment',
        'check' => 'Make sure GEMINI_API_KEY is set in config/.env'
    ]);
    exit;
}

// Show masked API key (first 8 and last 4 characters)
$keyLength = strlen($API_KEY);
$maskedKey = $keyLength > 12 
    ? substr($API_KEY, 0, 8) . '...' . substr($API_KEY, -4)
    : 'Key too short';

// Test API key by calling models endpoint (doesn't count against quota)
$url = 'https://generativelanguage.googleapis.com/v1/models?key=' . urlencode($API_KEY);

$ch = curl_init($url);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT => 10,
    CURLOPT_SSL_VERIFYPEER => true,
    CURLOPT_SSL_VERIFYHOST => 2
]);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlError = curl_error($ch);
curl_close($ch);

$result = [
    'status' => 'unknown',
    'api_key_masked' => $maskedKey,
    'http_code' => $httpCode,
    'curl_error' => $curlError ?: null
];

if ($curlError) {
    $result['status'] = 'error';
    $result['message'] = 'Network error: ' . $curlError;
} elseif ($httpCode === 200) {
    $data = json_decode($response, true);
    $modelCount = isset($data['models']) ? count($data['models']) : 0;
    $result['status'] = 'success';
    $result['message'] = 'API key is valid and working!';
    $result['models_available'] = $modelCount;
    
    // List some models
    if ($modelCount > 0) {
        $modelNames = [];
        foreach (array_slice($data['models'], 0, 5) as $model) {
            if (isset($model['name'])) {
                $modelNames[] = str_replace('models/', '', $model['name']);
            }
        }
        $result['sample_models'] = $modelNames;
    }
} elseif ($httpCode === 400) {
    $result['status'] = 'error';
    $result['message'] = 'Invalid API key - Please check your GEMINI_API_KEY in config/.env';
    $result['response_snippet'] = substr($response, 0, 200);
} elseif ($httpCode === 429) {
    $result['status'] = 'rate_limited';
    $result['message'] = 'API key is valid but rate limited. Wait 60 seconds and try again.';
} elseif ($httpCode === 403) {
    $result['status'] = 'error';
    $result['message'] = 'API key is restricted or quota exceeded. Check your Google Cloud console.';
    $result['response_snippet'] = substr($response, 0, 200);
} else {
    $result['status'] = 'error';
    $result['message'] = 'Unexpected HTTP code: ' . $httpCode;
    $result['response_snippet'] = substr($response, 0, 200);
}

echo json_encode($result, JSON_PRETTY_PRINT);
?>
