<?php
/**
 * Save Student Notification Preferences (email-only)
 */
require_once __DIR__ . '/../../config/database.php';
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['student_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}
$student_id = $_SESSION['student_id'];

$input = json_decode(file_get_contents('php://input'), true);
if (!$input) { echo json_encode(['success' => false, 'error' => 'Invalid payload']); exit; }

// Simplified: Only save frequency preference
// Email is always enabled, all types are always enabled
$email_frequency = ($input['email_frequency'] ?? 'immediate') === 'daily' ? 'daily' : 'immediate';

// Ensure row exists
$res = @pg_query_params($connection, "SELECT 1 FROM student_notification_preferences WHERE student_id = $1", [$student_id]);
if (!$res || !pg_fetch_row($res)) {
    @pg_query_params($connection, "INSERT INTO student_notification_preferences (student_id) VALUES ($1)", [$student_id]);
}

// Update: Set email_enabled to TRUE (always on), update frequency, keep all type columns as TRUE
$sql = "UPDATE student_notification_preferences 
        SET email_enabled = true,
            email_frequency = $1,
            email_announcement = true,
            email_document = true,
            email_schedule = true,
            email_warning = true,
            email_error = true,
            email_success = true,
            email_system = true,
            email_info = true
        WHERE student_id = $2";

$ok = @pg_query_params($connection, $sql, [$email_frequency, $student_id]);
echo json_encode(['success' => $ok !== false]);
