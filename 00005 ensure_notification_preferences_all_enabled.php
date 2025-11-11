<?php
/**
 * Migration Script: Ensure All Notification Preferences Are Enabled
 * 
 * Date: November 12, 2025
 * 
 * Purpose: After switching to frequency-only preference system, ensure all
 * existing student preference rows have:
 * - email_enabled = TRUE
 * - All type columns (email_announcement, email_error, etc.) = TRUE
 * - email_frequency defaults to 'immediate'
 * 
 * This ensures no student is accidentally blocked from receiving critical notifications.
 */

require_once __DIR__ . '/config/database.php';

echo "=== Notification Preferences Migration ===\n";
echo "Date: " . date('Y-m-d H:i:s') . "\n\n";

// Step 1: Update all existing preference rows
echo "Step 1: Updating existing preference rows...\n";
$updateSql = "UPDATE student_notification_preferences 
              SET email_enabled = true,
                  email_announcement = true,
                  email_document = true,
                  email_schedule = true,
                  email_warning = true,
                  email_error = true,
                  email_success = true,
                  email_system = true,
                  email_info = true,
                  email_frequency = COALESCE(email_frequency, 'immediate')";

$result = pg_query($connection, $updateSql);
if ($result) {
    $affected = pg_affected_rows($result);
    echo "✓ Updated $affected existing preference rows\n";
} else {
    echo "✗ Failed to update existing rows: " . pg_last_error($connection) . "\n";
    exit(1);
}

// Step 2: Create preference rows for students who don't have one
echo "\nStep 2: Creating preferences for students without settings...\n";
$insertSql = "INSERT INTO student_notification_preferences 
              (student_id, email_enabled, email_frequency, 
               email_announcement, email_document, email_schedule, email_warning,
               email_error, email_success, email_system, email_info)
              SELECT student_id, true, 'immediate',
                     true, true, true, true, true, true, true, true
              FROM students
              WHERE student_id NOT IN (
                  SELECT student_id FROM student_notification_preferences
              )";

$result = pg_query($connection, $insertSql);
if ($result) {
    $created = pg_affected_rows($result);
    echo "✓ Created $created new preference rows\n";
} else {
    echo "✗ Failed to create new rows: " . pg_last_error($connection) . "\n";
    exit(1);
}

// Step 3: Verify all students have preferences
echo "\nStep 3: Verifying coverage...\n";
$verifySql = "SELECT 
                  (SELECT COUNT(*) FROM students) as total_students,
                  (SELECT COUNT(*) FROM student_notification_preferences) as total_prefs,
                  (SELECT COUNT(*) FROM student_notification_preferences WHERE email_enabled = true) as enabled_count,
                  (SELECT COUNT(*) FROM student_notification_preferences WHERE 
                   email_warning = true AND email_error = true) as critical_enabled";

$result = pg_query($connection, $verifySql);
if ($result) {
    $stats = pg_fetch_assoc($result);
    echo "Total students: " . $stats['total_students'] . "\n";
    echo "Total preferences: " . $stats['total_prefs'] . "\n";
    echo "Email enabled: " . $stats['enabled_count'] . "\n";
    echo "Critical types enabled: " . $stats['critical_enabled'] . "\n";
    
    if ($stats['total_students'] == $stats['total_prefs'] && 
        $stats['total_prefs'] == $stats['enabled_count'] &&
        $stats['enabled_count'] == $stats['critical_enabled']) {
        echo "\n✓ All students have complete notification preferences!\n";
    } else {
        echo "\n⚠ Warning: Some discrepancies found. Please review.\n";
    }
} else {
    echo "✗ Failed to verify: " . pg_last_error($connection) . "\n";
}

echo "\n=== Migration Complete ===\n";
echo "All notification preferences are now enabled by default.\n";
echo "Students can only adjust email frequency (immediate vs daily digest).\n";
echo "Critical notifications (errors, warnings) always send immediately.\n";
?>
