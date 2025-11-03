<?php
/**
 * Reset Distribution and Students Script
 * 
 * This script will:
 * 1. Reset all students with status 'given' back to 'active' (or 'applicant' if preferred)
 * 2. Reset QR codes from 'Done' back to 'Pending'
 * 3. Delete temporary/incomplete distribution snapshots
 * 4. Delete distribution student records
 * 5. Republish the schedule
 * 6. Reopen signup slots
 * 
 * WARNING: This is a destructive operation. Use only when you need to undo a distribution.
 */

session_start();
require_once __DIR__ . '/config/database.php';

// Check admin authentication
if (!isset($_SESSION['admin_username'])) {
    die("Error: Admin authentication required. Please log in first.");
}

$admin_id = $_SESSION['admin_id'] ?? null;
$admin_username = $_SESSION['admin_username'] ?? 'Unknown';

// Security check: Require password confirmation
$password_confirmed = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['confirm_reset'])) {
    $admin_password = $_POST['admin_password'] ?? '';
    
    if (empty($admin_password)) {
        $error = "Password is required for security confirmation.";
    } else {
        // Verify admin password
        if ($admin_id) {
            $password_check = pg_query_params($connection, "SELECT password FROM admins WHERE admin_id = $1", [$admin_id]);
            if ($password_check && pg_num_rows($password_check) > 0) {
                $admin_data = pg_fetch_assoc($password_check);
                if (password_verify($admin_password, $admin_data['password'])) {
                    $password_confirmed = true;
                } else {
                    $error = "Incorrect password. Please try again.";
                }
            } else {
                $error = "Admin account not found.";
            }
        } else {
            $error = "Admin session invalid.";
        }
    }
}

// Perform reset if password confirmed
if ($password_confirmed) {
    try {
        pg_query($connection, "BEGIN");
        
        echo "<h2>🔄 Distribution Reset Process Started</h2>";
        echo "<pre>";
        
        // Step 1: Get current stats
        echo "\n=== CURRENT STATE ===\n";
        
        $stats_query = "
            SELECT 
                COUNT(*) FILTER (WHERE status = 'given') as given_count,
                COUNT(*) FILTER (WHERE status = 'active') as active_count,
                COUNT(*) FILTER (WHERE status = 'applicant') as applicant_count
            FROM students
        ";
        $stats_result = pg_query($connection, $stats_query);
        $stats = pg_fetch_assoc($stats_result);
        
        echo "Students with 'given' status: {$stats['given_count']}\n";
        echo "Students with 'active' status: {$stats['active_count']}\n";
        echo "Students with 'applicant' status: {$stats['applicant_count']}\n";
        
        $qr_stats_query = "
            SELECT 
                COUNT(*) FILTER (WHERE status = 'Done') as done_count,
                COUNT(*) FILTER (WHERE status = 'Pending') as pending_count
            FROM qr_codes
        ";
        $qr_stats_result = pg_query($connection, $qr_stats_query);
        $qr_stats = pg_fetch_assoc($qr_stats_result);
        
        echo "QR codes with 'Done' status: {$qr_stats['done_count']}\n";
        echo "QR codes with 'Pending' status: {$qr_stats['pending_count']}\n";
        
        // Step 2: Reset student status from 'given' to 'active'
        echo "\n=== STEP 1: Resetting Student Status ===\n";
        
        $reset_students_query = "
            UPDATE students 
            SET status = 'active' 
            WHERE status = 'given'
        ";
        $reset_result = pg_query($connection, $reset_students_query);
        $reset_count = pg_affected_rows($reset_result);
        
        echo "✓ Reset {$reset_count} student(s) from 'given' to 'active'\n";
        
        // Step 3: Reset QR codes from 'Done' to 'Pending'
        echo "\n=== STEP 2: Resetting QR Codes ===\n";
        
        $reset_qr_query = "
            UPDATE qr_codes 
            SET status = 'Pending' 
            WHERE status = 'Done'
        ";
        $reset_qr_result = pg_query($connection, $reset_qr_query);
        $qr_reset_count = pg_affected_rows($reset_qr_result);
        
        echo "✓ Reset {$qr_reset_count} QR code(s) from 'Done' to 'Pending'\n";
        
        // Step 4: Delete distribution student records (for incomplete distributions)
        echo "\n=== STEP 3: Cleaning Distribution Records ===\n";
        
        // Only delete records from snapshots that are NOT finalized
        $delete_records_query = "
            DELETE FROM distribution_student_records 
            WHERE snapshot_id IN (
                SELECT snapshot_id 
                FROM distribution_snapshots 
                WHERE finalized_at IS NULL
            )
        ";
        $delete_records_result = pg_query($connection, $delete_records_query);
        $records_deleted = pg_affected_rows($delete_records_result);
        
        echo "✓ Deleted {$records_deleted} distribution record(s) from incomplete snapshots\n";
        
        // Step 5: Delete incomplete/temporary distribution snapshots
        echo "\n=== STEP 4: Deleting Incomplete Snapshots ===\n";
        
        $delete_snapshots_query = "
            DELETE FROM distribution_snapshots 
            WHERE finalized_at IS NULL
        ";
        $delete_snapshots_result = pg_query($connection, $delete_snapshots_query);
        $snapshots_deleted = pg_affected_rows($delete_snapshots_result);
        
        echo "✓ Deleted {$snapshots_deleted} incomplete distribution snapshot(s)\n";
        
        // Step 6: Clear QR scan logs (optional - keeps history clean)
        echo "\n=== STEP 5: Clearing QR Scan Logs ===\n";
        
        $delete_logs_query = "DELETE FROM qr_logs";
        $delete_logs_result = pg_query($connection, $delete_logs_query);
        $logs_deleted = pg_affected_rows($delete_logs_result);
        
        echo "✓ Cleared {$logs_deleted} QR scan log(s)\n";
        
        // Step 7: Republish the schedule
        echo "\n=== STEP 6: Republishing Schedule ===\n";
        
        $settingsPath = __DIR__ . '/data/municipal_settings.json';
        $settings = file_exists($settingsPath) ? json_decode(file_get_contents($settingsPath), true) : [];
        $settings['schedule_published'] = true;
        file_put_contents($settingsPath, json_encode($settings, JSON_PRETTY_PRINT));
        
        echo "✓ Schedule has been republished\n";
        
        // Step 8: Reopen signup slots (if they were auto-closed)
        echo "\n=== STEP 7: Reopening Signup Slots ===\n";
        
        $reopen_slots_query = "
            UPDATE signup_slots 
            SET is_active = TRUE 
            WHERE is_active = FALSE
        ";
        $reopen_slots_result = pg_query($connection, $reopen_slots_query);
        $slots_reopened = pg_affected_rows($reopen_slots_result);
        
        echo "✓ Reopened {$slots_reopened} signup slot(s)\n";
        
        // Step 9: Log the reset action
        echo "\n=== STEP 8: Logging Reset Action ===\n";
        
        $log_query = "
            INSERT INTO admin_logs (admin_id, action, details, timestamp)
            VALUES ($1, $2, $3, NOW())
        ";
        $log_details = json_encode([
            'action' => 'distribution_reset',
            'students_reset' => $reset_count,
            'qr_codes_reset' => $qr_reset_count,
            'records_deleted' => $records_deleted,
            'snapshots_deleted' => $snapshots_deleted,
            'logs_cleared' => $logs_deleted,
            'schedule_republished' => true,
            'slots_reopened' => $slots_reopened
        ]);
        
        pg_query_params($connection, $log_query, [$admin_id, 'RESET_DISTRIBUTION', $log_details]);
        
        echo "✓ Logged reset action\n";
        
        pg_query($connection, "COMMIT");
        
        echo "\n=== ✅ RESET COMPLETED SUCCESSFULLY ===\n";
        echo "\nSummary:\n";
        echo "- {$reset_count} students reset to 'active'\n";
        echo "- {$qr_reset_count} QR codes reset to 'Pending'\n";
        echo "- {$records_deleted} distribution records deleted\n";
        echo "- {$snapshots_deleted} incomplete snapshots deleted\n";
        echo "- {$logs_deleted} QR logs cleared\n";
        echo "- Schedule republished\n";
        echo "- {$slots_reopened} signup slots reopened\n";
        echo "\n🎉 You can now start the distribution process fresh!\n";
        echo "</pre>";
        
        echo '<br><a href="modules/admin/scan_qr.php" class="btn btn-primary">Go to QR Scanner</a>';
        echo ' <a href="modules/admin/dashboard.php" class="btn btn-secondary">Go to Dashboard</a>';
        
    } catch (Exception $e) {
        pg_query($connection, "ROLLBACK");
        echo "</pre>";
        echo "<div class='alert alert-danger'>Error during reset: " . htmlspecialchars($e->getMessage()) . "</div>";
        error_log("Distribution reset error: " . $e->getMessage());
    }
    
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Distribution - EducAid</title>
    <link href="assets/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            font-family: 'Poppins', sans-serif;
        }
        .reset-container {
            max-width: 600px;
            background: white;
            padding: 40px;
            border-radius: 20px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
        }
        .warning-box {
            background: #fff3cd;
            border-left: 5px solid #ffc107;
            padding: 20px;
            margin: 20px 0;
            border-radius: 5px;
        }
        .danger-box {
            background: #f8d7da;
            border-left: 5px solid #dc3545;
            padding: 20px;
            margin: 20px 0;
            border-radius: 5px;
        }
    </style>
</head>
<body>
    <div class="reset-container">
        <h1 class="text-center mb-4">
            <i class="bi bi-arrow-counterclockwise text-warning"></i>
            Reset Distribution
        </h1>
        
        <?php if (isset($error)): ?>
        <div class="alert alert-danger">
            <i class="bi bi-exclamation-triangle me-2"></i><?= htmlspecialchars($error) ?>
        </div>
        <?php endif; ?>
        
        <div class="danger-box">
            <h5><i class="bi bi-exclamation-triangle-fill text-danger me-2"></i>Destructive Operation</h5>
            <p class="mb-0">This action will completely reset the current distribution session and cannot be undone.</p>
        </div>
        
        <div class="warning-box">
            <h6><i class="bi bi-info-circle me-2"></i>What This Will Do:</h6>
            <ul class="mb-0">
                <li>Reset all students from <strong>'given'</strong> to <strong>'active'</strong></li>
                <li>Reset QR codes from <strong>'Done'</strong> to <strong>'Pending'</strong></li>
                <li>Delete incomplete distribution snapshots</li>
                <li>Clear distribution student records</li>
                <li>Clear QR scan logs</li>
                <li>Republish the distribution schedule</li>
                <li>Reopen signup slots</li>
            </ul>
        </div>
        
        <div class="alert alert-info">
            <i class="bi bi-shield-check me-2"></i>
            <strong>Note:</strong> Only incomplete distributions will be reset. Finalized distributions are preserved for record-keeping.
        </div>
        
        <form method="POST">
            <div class="mb-3">
                <label for="admin_password" class="form-label">
                    <i class="bi bi-key me-2"></i>Confirm Your Admin Password
                </label>
                <input 
                    type="password" 
                    class="form-control" 
                    id="admin_password" 
                    name="admin_password" 
                    required
                    placeholder="Enter your password to confirm"
                >
                <small class="text-muted">Logged in as: <strong><?= htmlspecialchars($admin_username) ?></strong></small>
            </div>
            
            <div class="d-grid gap-2">
                <button type="submit" name="confirm_reset" class="btn btn-danger btn-lg">
                    <i class="bi bi-arrow-counterclockwise me-2"></i>Reset Distribution
                </button>
                <a href="modules/admin/dashboard.php" class="btn btn-secondary">
                    <i class="bi bi-x-circle me-2"></i>Cancel
                </a>
            </div>
        </form>
        
        <div class="mt-4 text-center">
            <small class="text-muted">
                <i class="bi bi-calendar-event me-1"></i>
                <?= date('F j, Y g:i A') ?>
            </small>
        </div>
    </div>
    
    <script src="assets/js/bootstrap.bundle.min.js"></script>
</body>
</html>
