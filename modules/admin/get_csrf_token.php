<?php
/**
 * CSRF Token Generator Endpoint
 * 
 * Returns a fresh CSRF token for the specified action.
 * Used by AJAX calls to refresh tokens before form submission.
 */

session_start();

// Require authentication
if (!isset($_SESSION['admin_username'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

require_once __DIR__ . '/../../includes/CSRFProtection.php';

// Get the action parameter
$action = $_GET['action'] ?? '';

// Validate action
$validActions = [
    'approve_applicant',
    'reject_applicant', 
    'override_applicant',
    'archive_student',
    'reject_documents',
    'csv_migration',
    'distribution_control'  // Added for graduating students archive modal
];

if (!in_array($action, $validActions)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Invalid action']);
    exit;
}

// Reuse the latest existing token for this action when available to avoid unnecessary rotation
$token = null;
if (isset($_SESSION['csrf_tokens'][$action])) {
    $stored = $_SESSION['csrf_tokens'][$action];
    if (is_array($stored) && !empty($stored)) {
        // Return the most recent token without generating a new one
        $token = end($stored);
    } elseif (is_string($stored) && $stored !== '') {
        $token = $stored;
    }
}

if (!$token) {
    // No existing token found; generate a new one
    $token = CSRFProtection::generateToken($action);
}

// Debug logging (safe: only prefix)
error_log(sprintf('CSRF: get_csrf_token return for %s -> %s...', $action, substr($token, 0, 16)));

// Return the token
header('Content-Type: application/json');
echo json_encode([
    'success' => true,
    'token' => $token,
    'action' => $action
]);
