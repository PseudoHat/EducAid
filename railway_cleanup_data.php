<?php
/**
 * ============================================================================
 * RAILWAY DATA CLEANUP SCRIPT
 * ============================================================================
 * 
 * This script removes ALL student and distribution data from Railway database:
 * - Student accounts and records
 * - Distribution snapshots and records
 * - Student notifications
 * - QR logs
 * - Documents
 * - All related student data
 * 
 * ⚠️ CRITICAL WARNING: This is a DESTRUCTIVE operation!
 * - All student data will be permanently deleted
 * - All distribution data will be removed
 * - This action CANNOT be undone
 * 
 * Access: https://your-railway-app.railway.app/railway_cleanup_data.php?confirm=YES_DELETE_ALL
 * ============================================================================
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);
set_time_limit(600); // 10 minutes max

require_once __DIR__ . '/config/database.php';

// Check if running on Railway
$isRailway = getenv('RAILWAY_ENVIRONMENT') || getenv('DATABASE_PUBLIC_URL');

// Security check - require confirmation
$confirm = isset($_GET['confirm']) && $_GET['confirm'] === 'YES_DELETE_ALL';

// HTML header
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Railway Data Cleanup - EducAid</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: #333;
            padding: 20px;
            line-height: 1.6;
            min-height: 100vh;
        }
        .container {
            max-width: 1000px;
            margin: 0 auto;
            background: white;
            border-radius: 15px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            overflow: hidden;
        }
        .header {
            background: linear-gradient(135deg, #dc3545 0%, #c82333 100%);
            color: white;
            padding: 30px;
            text-align: center;
        }
        .header h1 {
            font-size: 2.5em;
            margin-bottom: 10px;
            text-shadow: 2px 2px 4px rgba(0,0,0,0.2);
        }
        .subtitle {
            font-size: 1.1em;
            opacity: 0.95;
        }
        .content {
            padding: 40px;
        }
        .warning-box, .danger-box {
            border-radius: 10px;
            padding: 25px;
            margin-bottom: 30px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
        }
        .warning-box {
            background: #fff3cd;
            border: 3px solid #ffc107;
        }
        .danger-box {
            background: #f8d7da;
            border: 3px solid #dc3545;
        }
        .danger-box h2, .warning-box h2 {
            margin-bottom: 15px;
            font-size: 1.5em;
        }
        .danger-box h2 { color: #721c24; }
        .warning-box h2 { color: #856404; }
        .danger-box ul, .warning-box ul {
            margin-left: 25px;
            margin-top: 10px;
        }
        .danger-box li, .warning-box li {
            margin: 8px 0;
            font-weight: bold;
        }
        .section {
            margin-bottom: 30px;
        }
        .section h3 {
            font-size: 1.4em;
            margin-bottom: 15px;
            color: #333;
            border-bottom: 2px solid #e0e0e0;
            padding-bottom: 10px;
        }
        .stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin: 20px 0;
        }
        .stat-box {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 20px;
            border-radius: 10px;
            text-align: center;
            box-shadow: 0 4px 10px rgba(0,0,0,0.2);
        }
        .stat-number {
            font-size: 2.5em;
            font-weight: bold;
            margin-bottom: 5px;
        }
        .stat-label {
            font-size: 0.9em;
            opacity: 0.9;
        }
        .step {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 15px;
            border-left: 4px solid #28a745;
        }
        .step.error {
            border-left-color: #dc3545;
            background: #fff5f5;
        }
        .step-title {
            font-weight: bold;
            font-size: 1.1em;
            margin-bottom: 8px;
        }
        .step-content {
            color: #666;
            line-height: 1.5;
        }
        .success { color: #28a745; }
        .error { color: #dc3545; }
        .btn {
            padding: 12px 30px;
            font-size: 1.1em;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
            transition: all 0.3s;
            font-weight: bold;
        }
        .btn-danger {
            background: linear-gradient(135deg, #dc3545 0%, #c82333 100%);
            color: white;
            box-shadow: 0 4px 15px rgba(220, 53, 69, 0.3);
        }
        .btn-danger:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(220, 53, 69, 0.4);
        }
        .btn-secondary {
            background: #6c757d;
            color: white;
        }
        .btn-secondary:hover {
            background: #5a6268;
        }
        .confirmation-form {
            background: #f8d7da;
            padding: 30px;
            border-radius: 10px;
            border: 3px solid #dc3545;
            margin-top: 20px;
        }
        .confirmation-input {
            width: 100%;
            padding: 12px;
            font-size: 1em;
            border: 2px solid #dc3545;
            border-radius: 5px;
            margin: 10px 0;
        }
        .code {
            background: #2d2d2d;
            color: #f8f8f2;
            padding: 10px 15px;
            border-radius: 5px;
            font-family: 'Courier New', monospace;
            display: inline-block;
            margin: 10px 0;
        }
        .icon {
            display: inline-block;
            margin-right: 8px;
        }
        .env-badge {
            display: inline-block;
            padding: 5px 15px;
            border-radius: 20px;
            font-weight: bold;
            margin-bottom: 15px;
        }
        .env-railway {
            background: #0069ff;
            color: white;
        }
        .env-local {
            background: #ffc107;
            color: #333;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1><span class="icon">🗑️</span>Railway Data Cleanup</h1>
            <p class="subtitle">Complete removal of student and distribution data</p>
        </div>
        <div class="content">
<?php

// Show environment
echo '<div style="text-align: center; margin-bottom: 20px;">';
if ($isRailway) {
    echo '<span class="env-badge env-railway">🚂 Running on Railway</span>';
} else {
    echo '<span class="env-badge env-local">💻 Running Locally</span>';
}
echo '</div>';

if (!$confirm) {
    // ============================================================================
    // STEP 0: Display Warning and Current Status
    // ============================================================================
    
    echo '<div class="danger-box">';
    echo '<h2><span class="icon">⚠️</span>CRITICAL WARNING - READ CAREFULLY</h2>';
    echo '<p style="font-size: 1.1em; margin-bottom: 15px;"><strong>This script will permanently delete:</strong></p>';
    echo '<ul>';
    echo '<li>ALL student accounts and login credentials</li>';
    echo '<li>ALL student personal information and profiles</li>';
    echo '<li>ALL student notifications and messages</li>';
    echo '<li>ALL distribution snapshots and records</li>';
    echo '<li>ALL distribution student records</li>';
    echo '<li>ALL student applications and documents</li>';
    echo '<li>ALL QR code scan logs</li>';
    echo '<li>ALL enrollment forms and uploaded files</li>';
    echo '</ul>';
    echo '<p style="margin-top: 20px; font-size: 1.2em; color: #721c24;"><strong>⚠️ THIS ACTION CANNOT BE UNDONE!</strong></p>';
    echo '</div>';
    
    // Check current status before deletion
    echo '<div class="section">';
    echo '<h3><span class="icon">📊</span>Current Database Status</h3>';
    
    $stats = [
        'students' => 0,
        'notifications' => 0,
        'qr_logs' => 0,
        'documents' => 0,
        'distribution_records' => 0,
        'student_snapshots' => 0,
        'distribution_snapshots' => 0,
        'schedules' => 0
    ];
    
    // Count records
    $queries = [
        'students' => "SELECT COUNT(*) as count FROM students",
        'notifications' => "SELECT COUNT(*) as count FROM student_notifications",
        'qr_logs' => "SELECT COUNT(*) as count FROM qr_logs",
        'documents' => "SELECT COUNT(*) as count FROM documents",
        'distribution_records' => "SELECT COUNT(*) as count FROM distribution_student_records",
        'student_snapshots' => "SELECT COUNT(*) as count FROM distribution_student_snapshot",
        'distribution_snapshots' => "SELECT COUNT(*) as count FROM distribution_snapshots",
        'schedules' => "SELECT COUNT(*) as count FROM schedules",
        'signup_slots' => "SELECT COUNT(*) as count FROM signup_slots"
    ];
    
    foreach ($queries as $key => $query) {
        $result = @pg_query($connection, $query);
        if ($result) {
            $row = pg_fetch_assoc($result);
            $stats[$key] = $row['count'] ?? 0;
        }
    }
    
    echo '<div class="stats">';
    echo '<div class="stat-box">';
    echo '<div class="stat-number">' . $stats['students'] . '</div>';
    echo '<div class="stat-label">Student Accounts</div>';
    echo '</div>';
    
    echo '<div class="stat-box">';
    echo '<div class="stat-number">' . $stats['notifications'] . '</div>';
    echo '<div class="stat-label">Notifications</div>';
    echo '</div>';
    
    echo '<div class="stat-box">';
    echo '<div class="stat-number">' . $stats['qr_logs'] . '</div>';
    echo '<div class="stat-label">QR Logs</div>';
    echo '</div>';
    
    echo '<div class="stat-box">';
    echo '<div class="stat-number">' . $stats['documents'] . '</div>';
    echo '<div class="stat-label">Documents</div>';
    echo '</div>';
    
    echo '<div class="stat-box">';
    echo '<div class="stat-number">' . $stats['schedules'] . '</div>';
    echo '<div class="stat-label">Schedules</div>';
    echo '</div>';

    echo '<div class="stat-box">';
    echo '<div class="stat-number">' . $stats['distribution_records'] . '</div>';
    echo '<div class="stat-label">Distribution Records</div>';
    echo '</div>';
    
    echo '<div class="stat-box">';
    echo '<div class="stat-number">' . $stats['student_snapshots'] . '</div>';
    echo '<div class="stat-label">Student Snapshots</div>';
    echo '</div>';
    
    echo '<div class="stat-box">';
    echo '<div class="stat-number">' . $stats['distribution_snapshots'] . '</div>';
    echo '<div class="stat-label">Distribution Snapshots</div>';
    echo '</div>';
    
    echo '<div class="stat-box">';
    echo '<div class="stat-number">' . $stats['signup_slots'] . '</div>';
    echo '<div class="stat-label">Signup Slots</div>';
    echo '</div>';
    echo '</div>';
    
    echo '</div>';
    
    // Confirmation Form
    echo '<div class="section">';
    echo '<h3><span class="icon">🔒</span>Confirmation Required</h3>';
    
    $totalRecords = array_sum($stats);
    
    if ($totalRecords === 0) {
        echo '<div class="step">';
        echo '<div class="step-title"><span class="success">✓</span> No Data Found</div>';
        echo '<div class="step-content">The database is already clean. No student or distribution records to delete.</div>';
        echo '</div>';
    } else {
        echo '<div class="confirmation-form">';
        echo '<p style="font-size: 1.1em; margin-bottom: 15px;"><strong>To proceed with deletion:</strong></p>';
        echo '<ol style="margin-left: 20px; margin-bottom: 20px;">';
        echo '<li>Understand that this will delete <strong>' . number_format($totalRecords) . ' total records</strong></li>';
        echo '<li>Confirm you have a database backup (if needed)</li>';
        if ($isRailway) {
            echo '<li>⚠️ This will affect your LIVE Railway database</li>';
        }
        echo '<li>Add <code>?confirm=YES_DELETE_ALL</code> to the URL</li>';
        echo '</ol>';
        
        echo '<p style="margin-bottom: 10px;"><strong>URL to execute deletion:</strong></p>';
        $currentUrl = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://$_SERVER[HTTP_HOST]$_SERVER[REQUEST_URI]";
        $deleteUrl = $currentUrl . '?confirm=YES_DELETE_ALL';
        echo '<div class="code" style="word-break: break-all;">' . htmlspecialchars($deleteUrl) . '</div>';
        
        echo '<p style="margin-top: 20px; font-size: 0.9em; color: #721c24;">';
        echo '<strong>⚠️ Copy the URL above and paste it in your browser to execute the deletion.</strong>';
        echo '</p>';
        echo '</div>';
    }
    
    echo '</div>';
    
} else {
    // ============================================================================
    // EXECUTE DELETION
    // ============================================================================
    
    echo '<div class="section">';
    echo '<h3><span class="icon">🔄</span>Executing Deletion Process</h3>';
    
    if ($isRailway) {
        echo '<div class="warning-box">';
        echo '<h2><span class="icon">🚂</span>Railway Database Cleanup In Progress</h2>';
        echo '<p>Deleting data from your live Railway PostgreSQL database...</p>';
        echo '</div>';
    }
    
    try {
        // Deletion steps in proper order to respect foreign key constraints
        $deletionSteps = [
            [
                'name' => 'Delete Distribution Student Snapshots',
                'query' => 'DELETE FROM distribution_student_snapshot',
                'description' => 'Remove all historical student distribution snapshots'
            ],
            [
                'name' => 'Delete Distribution Student Records',
                'query' => 'DELETE FROM distribution_student_records',
                'description' => 'Remove all distribution tracking records'
            ],
            [
                'name' => 'Delete Distribution Snapshots',
                'query' => 'DELETE FROM distribution_snapshots',
                'description' => 'Remove all distribution snapshot records'
            ],
            [
                'name' => 'Delete Student Notifications',
                'query' => 'DELETE FROM student_notifications',
                'description' => 'Remove all student notification records'
            ],
            [
                'name' => 'Delete QR Code Logs',
                'query' => 'DELETE FROM qr_logs',
                'description' => 'Remove all QR code scan logs for students'
            ],
            [
                'name' => 'Delete Student Documents',
                'query' => 'DELETE FROM documents',
                'description' => 'Remove all uploaded document records'
            ],
            [
                'name' => 'Delete Household Block Attempts',
                'query' => 'DELETE FROM household_block_attempts',
                'description' => 'Remove all household blocking records'
            ],
            [
                'name' => 'Delete Admin Blacklist Verifications',
                'query' => 'DELETE FROM admin_blacklist_verifications',
                'description' => 'Remove all admin blacklist verification records'
            ],
            [
                'name' => 'Delete Blacklisted Students',
                'query' => 'DELETE FROM blacklisted_students',
                'description' => 'Remove all blacklist records'
            ],
            [
                'name' => 'Delete Student Notification Preferences',
                'query' => 'DELETE FROM student_notification_preferences',
                'description' => 'Remove all notification preference settings'
            ],
            [
                'name' => 'Delete Student Status History',
                'query' => 'DELETE FROM student_status_history',
                'description' => 'Remove all student status change history records'
            ],
            [
                'name' => 'Delete Student Data Export Requests',
                'query' => 'DELETE FROM student_data_export_requests',
                'description' => 'Remove all data export request records'
            ],
            [
                'name' => 'Delete School Student ID Audit',
                'query' => 'DELETE FROM school_student_id_audit',
                'description' => 'Remove all school student ID audit logs'
            ],
            [
                'name' => 'Delete School Student IDs',
                'query' => 'DELETE FROM school_student_ids',
                'description' => 'Remove all school student ID mappings'
            ],
            [
                'name' => 'Delete QR Codes',
                'query' => 'DELETE FROM qr_codes',
                'description' => 'Remove all student QR codes (must be deleted before students)'
            ],
            [
                'name' => 'Delete Student Active Sessions',
                'query' => 'DELETE FROM student_active_sessions',
                'description' => 'Remove all active session records'
            ],
            [
                'name' => 'Delete Student Login History',
                'query' => 'DELETE FROM student_login_history',
                'description' => 'Remove all login history records'
            ],
            [
                'name' => 'Delete Student Accounts',
                'query' => 'DELETE FROM students',
                'description' => 'Remove all student account records'
            ],
            [
                'name' => 'Delete Schedules',
                'query' => 'DELETE FROM schedules',
                'description' => 'Remove all schedule records'
            ],
            [
                'name' => 'Delete Signup Slots',
                'query' => 'DELETE FROM signup_slots',
                'description' => 'Remove all signup slot records (must be deleted after students)'
            ]
        ];
        
        $totalDeleted = 0;
        
        foreach ($deletionSteps as $step) {
            // Execute each deletion
            $result = @pg_query($connection, $step['query']);
            
            if ($result) {
                $affected = pg_affected_rows($result);
                $totalDeleted += $affected;
                
                echo '<div class="step">';
                echo '<div class="step-title"><span class="success">✓</span> ' . $step['name'] . '</div>';
                echo '<div class="step-content">';
                echo 'Records deleted: <strong>' . number_format($affected) . '</strong><br>';
                echo $step['description'];
                echo '</div>';
                echo '</div>';
            } else {
                $error = pg_last_error($connection);
                echo '<div class="step error">';
                echo '<div class="step-title"><span class="error">✗</span> Failed: ' . $step['name'] . '</div>';
                echo '<div class="step-content">';
                echo 'Error: ' . htmlspecialchars($error);
                echo '</div>';
                echo '</div>';
                
                // Continue with other steps even if one fails
            }
            
            // Small delay to prevent overwhelming the database
            usleep(100000); // 0.1 second
        }
        
        // Reset sequences
        echo '<div class="section">';
        echo '<h3><span class="icon">🔧</span>Resetting Sequences</h3>';
        
        $sequences = [
            'students_student_id_seq' => 'students',
            'student_notifications_notification_id_seq' => 'student_notifications',
            'documents_document_id_seq' => 'documents',
            'signup_slots_slot_id_seq' => 'signup_slots'
        ];
        
        foreach ($sequences as $seq => $table) {
            $resetQuery = "SELECT setval('$seq', 1, false)";
            $result = @pg_query($connection, $resetQuery);
            
            if ($result) {
                echo '<div class="step">';
                echo '<div class="step-title"><span class="success">✓</span> Reset ' . $seq . '</div>';
                echo '<div class="step-content">Sequence reset to start from 1</div>';
                echo '</div>';
            }
        }
        
        echo '</div>';

        // Reset municipal_settings.json (schedule flags)
        echo '<div class="section">';
        echo '<h3><span class="icon">🧹</span>Reset Municipal Settings (Schedule)</h3>';
        $settingsPath = __DIR__ . '/data/municipal_settings.json';
        $settings = [];
        $settingsExists = file_exists($settingsPath);
        if ($settingsExists) {
            $decoded = json_decode(@file_get_contents($settingsPath), true);
            if (is_array($decoded)) { $settings = $decoded; }
        }
        $settings['schedule_published'] = false;
        if (isset($settings['schedule_meta'])) { unset($settings['schedule_meta']); }
        $encoded = json_encode($settings, JSON_PRETTY_PRINT);
        if ($encoded !== false && @file_put_contents($settingsPath, $encoded) !== false) {
            echo '<div class="step">';
            echo '<div class="step-title"><span class="success">✓</span> Municipal settings updated</div>';
            echo '<div class="step-content">Schedule flags reset (schedule_published=false, schedule_meta removed) in <code>data/municipal_settings.json</code>.</div>';
            echo '</div>';
        } else {
            echo '<div class="step error">';
            echo '<div class="step-title"><span class="error">✗</span> Failed to update municipal settings</div>';
            echo '<div class="step-content">Could not write to <code>data/municipal_settings.json</code>. Check file permissions and path.</div>';
            echo '</div>';
        }
        echo '</div>';
        
        // Success summary
        echo '<div class="section">';
        echo '<div class="step" style="border-left-color: #28a745; background: #d4edda;">';
        echo '<div class="step-title" style="color: #155724; font-size: 1.3em;">';
        echo '<span class="success">✅</span> Cleanup Complete!';
        echo '</div>';
        echo '<div class="step-content" style="color: #155724; font-size: 1.1em;">';
        echo 'Successfully deleted <strong>' . number_format($totalDeleted) . '</strong> total records from your ';
        echo $isRailway ? 'Railway' : 'local';
        echo ' database.';
        echo '</div>';
        echo '</div>';
        echo '</div>';
        
    } catch (Exception $e) {
        echo '<div class="step error">';
        echo '<div class="step-title"><span class="error">✗</span> Cleanup Failed</div>';
        echo '<div class="step-content">';
        echo 'Error: ' . htmlspecialchars($e->getMessage());
        echo '</div>';
        echo '</div>';
    }
}

?>
        </div>
    </div>
</body>
</html>
