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
    'csv_migration'
];

if (!in_array($action, $validActions)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Invalid action']);
    exit;
}

// Generate a fresh token for the specified action
$token = CSRFProtection::generateToken($action);

// Return the token
header('Content-Type: application/json');
echo json_encode([
    'success' => true,
    'token' => $token,
    'action' => $action
]);
