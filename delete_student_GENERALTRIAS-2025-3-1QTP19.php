<?php
include __DIR__ . '/config/database.php';

header('Content-Type: text/html; charset=UTF-8');
?>
<!DOCTYPE html>
<html>
<head>
    <title>Delete Student GENERALTRIAS-2025-3-1QTP19</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; background: #f5f5f5; }
        .container { background: white; padding: 20px; border-radius: 8px; max-width: 800px; margin: 0 auto; }
        .success { color: #28a745; padding: 10px; background: #d4edda; border-radius: 4px; margin: 10px 0; }
        .error { color: #dc3545; padding: 10px; background: #f8d7da; border-radius: 4px; margin: 10px 0; }
        .info { color: #0c5460; padding: 10px; background: #d1ecf1; border-radius: 4px; margin: 10px 0; }
        h1 { color: #333; }
        ul { line-height: 1.8; }
    </style>
</head>
<body>
<div class="container">
    <h1>🗑️ Delete Student: GENERALTRIAS-2025-3-1QTP19</h1>
    <p><strong>Student:</strong> Soliman, Rojen</p>

<?php
try {
    if (!$connection) {
        throw new Exception("Database connection not available");
    }
    
    $studentId = 'GENERALTRIAS-2025-3-1QTP19';
    
    // Start transaction
    pg_query($connection, "BEGIN");
    
    echo "<div class='info'><strong>Starting deletion process...</strong></div>";
    
    // 1. Check if student exists
    $checkQuery = "SELECT student_id, first_name, middle_name, last_name, email FROM students WHERE student_id = $1";
    $checkResult = pg_query_params($connection, $checkQuery, [$studentId]);
    
    if (!$checkResult || pg_num_rows($checkResult) == 0) {
        echo "<div class='error'>❌ Student not found: $studentId</div>";
        pg_query($connection, "ROLLBACK");
        exit;
    }
    
    $student = pg_fetch_assoc($checkResult);
    echo "<div class='success'>✅ Found student: {$student['first_name']} {$student['middle_name']} {$student['last_name']} ({$student['email']})</div>";
    
    // 2. Delete documents
    $docsResult = pg_query_params($connection, 
        "DELETE FROM documents WHERE student_id = $1 RETURNING document_id, document_type_code", 
        [$studentId]
    );
    $docsCount = pg_affected_rows($docsResult);
    echo "<div class='success'>✅ Deleted $docsCount document record(s)</div>";
    
    // 3. Delete document archives
    $archivesResult = pg_query_params($connection, 
        "DELETE FROM document_archives WHERE student_id = $1", 
        [$studentId]
    );
    $archivesCount = pg_affected_rows($archivesResult);
    if ($archivesCount > 0) {
        echo "<div class='success'>✅ Deleted $archivesCount document archive record(s)</div>";
    }
    
    // 4. Delete distribution student records
    $distResult = pg_query_params($connection, 
        "DELETE FROM distribution_student_records WHERE student_id = $1", 
        [$studentId]
    );
    $distCount = pg_affected_rows($distResult);
    if ($distCount > 0) {
        echo "<div class='success'>✅ Deleted $distCount distribution record(s)</div>";
    }
    
    // 5. Delete distribution snapshots
    $snapshotResult = pg_query_params($connection, 
        "DELETE FROM distribution_student_snapshot WHERE student_id = $1", 
        [$studentId]
    );
    $snapshotCount = pg_affected_rows($snapshotResult);
    if ($snapshotCount > 0) {
        echo "<div class='success'>✅ Deleted $snapshotCount distribution snapshot(s)</div>";
    }
    
    // 6. Delete notifications
    $notifsResult = pg_query_params($connection, 
        "DELETE FROM notifications WHERE student_id = $1", 
        [$studentId]
    );
    $notifsCount = pg_affected_rows($notifsResult);
    if ($notifsCount > 0) {
        echo "<div class='success'>✅ Deleted $notifsCount notification(s)</div>";
    }
    
    // 7. Delete schedules
    $schedulesResult = pg_query_params($connection, 
        "DELETE FROM schedules WHERE student_id = $1", 
        [$studentId]
    );
    $schedulesCount = pg_affected_rows($schedulesResult);
    if ($schedulesCount > 0) {
        echo "<div class='success'>✅ Deleted $schedulesCount schedule(s)</div>";
    }
    
    // 8. Delete QR codes
    $qrResult = pg_query_params($connection, 
        "DELETE FROM qr_codes WHERE student_id = $1", 
        [$studentId]
    );
    $qrCount = pg_affected_rows($qrResult);
    if ($qrCount > 0) {
        echo "<div class='success'>✅ Deleted $qrCount QR code(s)</div>";
    }
    
    // 9. Delete QR logs
    $qrLogsResult = pg_query_params($connection, 
        "DELETE FROM qr_logs WHERE student_id = $1", 
        [$studentId]
    );
    $qrLogsCount = pg_affected_rows($qrLogsResult);
    if ($qrLogsCount > 0) {
        echo "<div class='success'>✅ Deleted $qrLogsCount QR log(s)</div>";
    }
    
    // 10. Delete school student ID tracking
    $schoolIdResult = pg_query_params($connection, 
        "DELETE FROM school_student_ids WHERE student_id = $1", 
        [$studentId]
    );
    $schoolIdCount = pg_affected_rows($schoolIdResult);
    if ($schoolIdCount > 0) {
        echo "<div class='success'>✅ Deleted $schoolIdCount school student ID record(s)</div>";
    }
    
    // 11. Delete blacklist verifications
    $blacklistResult = pg_query_params($connection, 
        "DELETE FROM admin_blacklist_verifications WHERE student_id = $1", 
        [$studentId]
    );
    $blacklistCount = pg_affected_rows($blacklistResult);
    if ($blacklistCount > 0) {
        echo "<div class='success'>✅ Deleted $blacklistCount blacklist verification(s)</div>";
    }
    
    // 12. Finally, delete the student record
    $studentResult = pg_query_params($connection, 
        "DELETE FROM students WHERE student_id = $1", 
        [$studentId]
    );
    
    if ($studentResult && pg_affected_rows($studentResult) > 0) {
        echo "<div class='success'>✅ Deleted student record: $studentId</div>";
    } else {
        throw new Exception("Failed to delete student record");
    }
    
    // Commit transaction
    pg_query($connection, "COMMIT");
    
    echo "<div class='success'><h3>✅ SUCCESS: Student completely deleted from database</h3></div>";
    
    // Now delete physical files
    echo "<h2>📁 Deleting Physical Files</h2>";
    
    $uploadsPath = __DIR__ . '/uploads/';
    $deletedFiles = 0;
    $failedFiles = [];
    
    // Search for files with student ID in filename
    $pattern = $uploadsPath . '*' . $studentId . '*';
    $files = glob($pattern);
    
    // Also check subdirectories
    $subdirs = ['documents', 'student_pictures', 'temp', 'compressed'];
    foreach ($subdirs as $subdir) {
        $subPattern = $uploadsPath . $subdir . '/*' . $studentId . '*';
        $subFiles = glob($subPattern);
        if ($subFiles) {
            $files = array_merge($files, $subFiles);
        }
    }
    
    if (empty($files)) {
        echo "<div class='info'>ℹ️ No physical files found for this student</div>";
    } else {
        foreach ($files as $file) {
            if (is_file($file)) {
                if (unlink($file)) {
                    $deletedFiles++;
                    echo "<div class='success'>✅ Deleted: " . basename($file) . "</div>";
                } else {
                    $failedFiles[] = $file;
                    echo "<div class='error'>❌ Failed to delete: " . basename($file) . "</div>";
                }
            }
        }
        
        echo "<div class='success'><strong>Deleted $deletedFiles file(s)</strong></div>";
        
        if (!empty($failedFiles)) {
            echo "<div class='error'><strong>Failed to delete " . count($failedFiles) . " file(s)</strong></div>";
        }
    }
    
    echo "<div class='success'><h2>✅ COMPLETE: Student and all associated data removed</h2></div>";
    
} catch (Exception $e) {
    pg_query($connection, "ROLLBACK");
    echo "<div class='error'><h3>❌ ERROR: " . htmlspecialchars($e->getMessage()) . "</h3></div>";
    echo "<div class='error'>Transaction rolled back - no changes made</div>";
}
?>

</div>
</body>
</html>
