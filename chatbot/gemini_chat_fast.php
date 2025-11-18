<?php
/**
 * Fast Gemini Chatbot - Single Model Only
 * Uses gemini-1.5-flash (fastest and most reliable)
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Headers: Content-Type');

error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/chatbot_errors.log');

// Load environment
$envBootstrap = __DIR__ . '/../config/env.php';
if(is_file($envBootstrap)){
  require_once $envBootstrap;
}

$API_KEY = getenv('GEMINI_API_KEY') ?: '';
if($API_KEY === ''){
  error_log('[FastChatbot] Missing API key');
  http_response_code(500);
  echo json_encode(['error' => 'API key missing']);
  exit;
}

// Get user message
$input = json_decode(file_get_contents('php://input'), true);
$userMessage = trim($input['message'] ?? '');

if ($userMessage === '') {
  http_response_code(400);
  echo json_encode(['error' => 'Empty message']);
  exit;
}

// Try multiple models as fallbacks (in order of preference)
$models = [
  'gemini-2.5-flash',      // Newest and fastest
  'gemini-2.0-flash',      // Stable alternative
  'gemini-2.0-flash-001',  // Another stable option
];

$selectedModel = null;
foreach ($models as $tryModel) {
  $selectedModel = $tryModel;
  break; // Use first available model
}

$model = $selectedModel;
$url = 'https://generativelanguage.googleapis.com/v1beta/models/' . $model . ':generateContent?key=' . urlencode($API_KEY);

// Optimized prompt
$prompt = "You are EducAid Assistant for the scholarship program in General Trias, Cavite.\n\n" .
          "Your role:\n" .
          "- Answer questions about eligibility, requirements, documents, application process, and deadlines\n" .
          "- Help with general student concerns, academic guidance, and university/scholarship information\n" .
          "- Be conversational, helpful, and friendly for casual chat or greetings\n" .
          "- Keep responses concise (2-3 sentences for simple questions)\n\n" .
          "Important eligibility requirement:\n" .
          "- Students must maintain at least 75% or higher in their grades to qualify for the scholarship. We believe in your potential and are here to support your educational journey every step of the way.\n\n" .
          "Student message: " . $userMessage;

// Payload with optimized generation config
$payload = [
    'contents' => [[
        'role' => 'user',
        'parts' => [['text' => $prompt]]
    ]],
    'generationConfig' => [
        'temperature' => 0.7,
        'topK' => 20,
        'topP' => 0.9,
        'maxOutputTokens' => 512,
        'candidateCount' => 1
    ]
];

$startTime = microtime(true);

// Retry logic with model fallback
$maxRetries = 3;
$retryCount = 0;
$modelAttempts = 0;
$httpCode = 0;
$response = null;
$curlError = null;

while ($modelAttempts < count($models)) {
  $currentModel = $models[$modelAttempts];
  $currentUrl = 'https://generativelanguage.googleapis.com/v1beta/models/' . $currentModel . ':generateContent?key=' . urlencode($API_KEY);
  
  $retryCount = 0;
  while ($retryCount <= $maxRetries) {
    $ch = curl_init($currentUrl);
    curl_setopt_array($ch, [
      CURLOPT_POST => true,
      CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
      CURLOPT_POSTFIELDS => json_encode($payload),
      CURLOPT_RETURNTRANSFER => true,
      CURLOPT_CONNECTTIMEOUT => 5,
      CURLOPT_TIMEOUT => 20,
      CURLOPT_SSL_VERIFYPEER => true,
      CURLOPT_SSL_VERIFYHOST => 2,
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);
    
    // Success
    if ($httpCode === 200) {
      $model = $currentModel; // Update to successful model
      break 2; // Exit both loops
    }
    
    // Non-retryable error
    if ($httpCode !== 429) {
      break;
    }
    
    // Rate limit - retry with longer backoff
    if ($httpCode === 429 && $retryCount < $maxRetries) {
      $retryCount++;
      $backoffSeconds = min(10, pow(2, $retryCount)); // 2s, 4s, 8s (cap at 10s)
      error_log("[FastChatbot] Model: {$currentModel} - Rate limit (429) - Retry {$retryCount}/{$maxRetries} after {$backoffSeconds}s");
      sleep($backoffSeconds);
    } else {
      break;
    }
  }
  
  // If we got a 200, we're done
  if ($httpCode === 200) {
    break;
  }
  
  // Try next model
  $modelAttempts++;
  if ($modelAttempts < count($models)) {
    error_log("[FastChatbot] Switching to fallback model: {$models[$modelAttempts]}");
    sleep(1); // Brief pause before trying next model
  }
}

$endTime = microtime(true);
$responseTime = round(($endTime - $startTime) * 1000);

// Log response time for monitoring
error_log("[FastChatbot] Response time: {$responseTime}ms | HTTP: {$httpCode} | Model: {$model} | Retries: {$retryCount}");

// Handle cURL errors
if ($curlError) {
  error_log("[FastChatbot] cURL error: {$curlError}");
  http_response_code(500);
  echo json_encode([
    'error' => 'Connection error',
    'detail' => 'Failed to connect to AI service',
    'response_time' => $responseTime
  ]);
  exit;
}

// Handle non-200 responses
if ($httpCode !== 200) {
  $errorData = json_decode($response, true);
  $errorMessage = 'API error';
  $userMessage = "HTTP {$httpCode}";
  
  // Handle rate limiting specifically
  if ($httpCode === 429) {
    $userMessage = "I'm experiencing high demand right now. I've tried multiple times but couldn't connect. Please try again in 1-2 minutes.";
    error_log("[FastChatbot] Rate limit (429) after {$retryCount} retries | Response: " . substr($response, 0, 200));
  } 
  // Handle quota exceeded
  else if ($httpCode === 403 && isset($errorData['error']['message']) && strpos($errorData['error']['message'], 'quota') !== false) {
    $userMessage = "The AI service daily quota has been reached. Please try again tomorrow or contact support.";
    error_log("[FastChatbot] Quota exceeded (403) | Response: " . substr($response, 0, 200));
  }
  // Handle invalid API key
  else if ($httpCode === 400 && isset($errorData['error']['message']) && strpos($errorData['error']['message'], 'API key') !== false) {
    $userMessage = "There's a configuration issue with the AI service. Please contact support.";
    error_log("[FastChatbot] Invalid API key (400) | Response: " . substr($response, 0, 200));
  }
  // Generic error
  else {
    error_log("[FastChatbot] HTTP {$httpCode} | Response: " . substr($response, 0, 200));
  }
  
  http_response_code(200); // Send 200 so frontend displays the user-friendly message
  echo json_encode([
    'reply' => $userMessage,
    'error_type' => $httpCode === 429 ? 'rate_limit' : 'api_error',
    'response_time_ms' => $responseTime,
    'success' => false
  ]);
  exit;
}

// Parse response
$data = json_decode($response, true);

if (!isset($data['candidates'][0]['content']['parts'][0]['text'])) {
  error_log("[FastChatbot] Invalid response structure: " . substr($response, 0, 200));
  http_response_code(500);
  echo json_encode([
    'error' => 'Invalid response',
    'detail' => 'Unexpected API response format',
    'response_time' => $responseTime
  ]);
  exit;
}

// Success! Return the response
$reply = trim($data['candidates'][0]['content']['parts'][0]['text']);

echo json_encode([
  'reply' => $reply,
  'model' => $model,
  'response_time_ms' => $responseTime,
  'success' => true
]);
