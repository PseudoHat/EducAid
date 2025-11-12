<?php
/**
 * Year Level Update Requirement Check
 * This file blocks access to all student pages until year level is updated
 * Include this file at the top of every student page (except upload_document.php)
 */

// Don't run this check on the upload document page itself (that's where they update)
$current_script = basename($_SERVER['PHP_SELF']);
if ($current_script === 'upload_document.php') {
    return;
}

// Don't run on logout or other special pages
$bypass_pages = ['student_logout.php', 'serve_profile_image.php', 'download_qr.php'];
if (in_array($current_script, $bypass_pages)) {
    return;
}

// Ensure session is started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Ensure student is logged in
if (!isset($_SESSION['student_id'])) {
    return; // Let the page's own auth handle this
}

// Get current database connection (should be included by the calling page)
if (!isset($connection)) {
    require_once __DIR__ . '/../config/database.php';
}

$studentId = $_SESSION['student_id'];

// Get student's year level status and migration status
$student_info_query = "SELECT current_year_level, is_graduating, status_academic_year, admin_review_required, university_id, mothers_maiden_name, school_student_id FROM students WHERE student_id = $1";
$student_info_result = pg_query_params($connection, $student_info_query, [$studentId]);
$student_info = pg_fetch_assoc($student_info_result);

if (!$student_info) {
    return; // Student not found, let page handle
}

// Check if this is a migrated student
$is_migrated = ($student_info['admin_review_required'] === 't' || $student_info['admin_review_required'] === true);

// Check if migrated student needs to complete their profile (missing required credentials)
$needs_migrated_profile_completion = $is_migrated && (
    empty($student_info['university_id']) || 
    empty($student_info['mothers_maiden_name']) || 
    empty($student_info['school_student_id'])
);

// Get current active academic year
$current_academic_year = null;

// First try to get from active slot
$current_ay_query = pg_query($connection, "SELECT academic_year FROM signup_slots WHERE is_active = TRUE LIMIT 1");
if ($current_ay_query && pg_num_rows($current_ay_query) > 0) {
    $ay_row = pg_fetch_assoc($current_ay_query);
    $current_academic_year = $ay_row['academic_year'];
}

// If no active slot, check config table for current distribution
if (!$current_academic_year) {
    $config_query = pg_query($connection, "SELECT value FROM config WHERE key = 'current_academic_year'");
    if ($config_query && pg_num_rows($config_query) > 0) {
        $config_row = pg_fetch_assoc($config_query);
        $current_academic_year = $config_row['value'];
    }
}

// Check if student needs to update their year level credentials
// They need to update if:
// 1. They are a migrated student who hasn't completed their profile (PRIORITY), OR
// 2. They don't have year level data at all, OR
// 3. There's an active distribution and their status_academic_year doesn't match the current one
$needs_year_level_update = $needs_migrated_profile_completion ||
                           empty($student_info['current_year_level']) || 
                           empty($student_info['status_academic_year']) || 
                           $student_info['is_graduating'] === null ||
                           ($current_academic_year && $student_info['status_academic_year'] !== $current_academic_year);

// If they need to update, redirect to upload_document.php
// The modal will automatically appear there
if ($needs_year_level_update) {
    // Store the page they were trying to access
    $_SESSION['return_after_year_update'] = $_SERVER['REQUEST_URI'];
    
    // If migrated student needs profile completion, set a flag so the correct modal shows
    if ($needs_migrated_profile_completion) {
        $_SESSION['needs_migrated_profile_completion'] = true;
    }
    
    // Redirect to upload document page (modal will auto-show)
    header("Location: upload_document.php?force_update=1");
    exit;
}
?>
