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

// Get student's year level status and migration/reupload flags
$student_info_query = "SELECT current_year_level, is_graduating, status_academic_year, admin_review_required, university_id, mothers_maiden_name, school_student_id, needs_upload FROM students WHERE student_id = $1";
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

// Refined gating to avoid forcing brand-new registrants
// Determine reupload vs new registrant context
$needs_upload_flag = ($student_info['needs_upload'] === 't' || $student_info['needs_upload'] === true || $student_info['needs_upload'] === '1');
$has_prior_year_confirmation = !empty($student_info['status_academic_year']);
$year_changed = $current_academic_year && $has_prior_year_confirmation && $student_info['status_academic_year'] !== $current_academic_year;
$credentials_incomplete_after_confirmation = $has_prior_year_confirmation && ($student_info['is_graduating'] === null || empty($student_info['current_year_level']));
$is_reupload_context = $needs_upload_flag && !$is_migrated;

// Only force on:
// - Migrated profile completion (priority), OR
// - Reupload context with AY change OR incomplete credentials AFTER prior confirmation
$needs_year_level_update = false;
if ($needs_migrated_profile_completion) {
    $needs_year_level_update = true;
} elseif ($is_reupload_context && ($year_changed || $credentials_incomplete_after_confirmation)) {
    $needs_year_level_update = true;
}

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
