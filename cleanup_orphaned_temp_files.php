<?php
/**
 * Cleanup Orphaned Temp Files
 * 
 * This script should be run periodically (e.g., daily via cron job)
 * to clean up temp files from:
 * 1. Abandoned registrations (no student record exists)
 * 2. Already processed registrations (student status is no longer "under_registration")
 * 3. Very old files (older than 7 days regardless of status)
 * 
 * IMPORTANT: Do NOT delete files from pending registrations waiting for admin review!
 */

require_once __DIR__ . '/config/database.php';

echo "=== Starting Temp Files Cleanup ===\n";
echo "Time: " . date('Y-m-d H:i:s') . "\n\n";

$tempDirs = [
    __DIR__ . '/assets/uploads/temp/enrollment_forms/',
    __DIR__ . '/assets/uploads/temp/id_pictures/',
    __DIR__ . '/assets/uploads/temp/letter_mayor/',
    __DIR__ . '/assets/uploads/temp/indigency/',
    __DIR__ . '/assets/uploads/temp/grades/'
];

$deletedCount = 0;
$deletedSize = 0;
$keptCount = 0;

foreach ($tempDirs as $dir) {
    if (!is_dir($dir)) {
        echo "Directory not found: $dir\n";
        continue;
    }
    
    echo "\nChecking: " . basename(dirname($dir)) . "/" . basename($dir) . "\n";
    
    $files = glob($dir . '*');
    foreach ($files as $file) {
        if (!is_file($file)) continue;
        
        $fileName = basename($file);
        $fileAge = time() - filemtime($file);
        $fileAgeHours = round($fileAge / 3600, 1);
        $fileSize = filesize($file);
        
        // Skip .gitkeep files
        if ($fileName === '.gitkeep') continue;
        
        // Extract student ID from filename (format: STUDENTID_document_timestamp.ext)
        $studentId = null;
        if (preg_match('/^([A-Z]+-\d{4}-\d+-[A-Z0-9]+)_/', $fileName, $matches)) {
            $studentId = $matches[1];
        }
        
        $shouldDelete = false;
        $reason = '';
        
        // Rule 1: Files older than 7 days - always delete
        if ($fileAge > (7 * 24 * 3600)) {
            $shouldDelete = true;
            $reason = "Older than 7 days";
        }
        // Rule 2: Files older than 48 hours with no student ID pattern
        elseif ($fileAge > (48 * 3600) && !$studentId) {
            $shouldDelete = true;
            $reason = "Old file with no student ID";
        }
        // Rule 3: Check if student exists and their status
        elseif ($studentId && $fileAge > (24 * 3600)) {
            // Check student status in database
            $stmt = pg_prepare($connection, "check_student", 
                "SELECT status FROM students WHERE student_id = $1");
            $result = pg_execute($connection, "check_student", [$studentId]);
            
            if ($result && pg_num_rows($result) > 0) {
                $student = pg_fetch_assoc($result);
                $status = $student['status'];
                
                // Delete if student is no longer under_registration (already processed)
                if ($status !== 'under_registration' && $status !== 'applicant') {
                    $shouldDelete = true;
                    $reason = "Student status: $status (processed)";
                }
                else {
                    // Keep files for pending registrations
                    $keptCount++;
                    echo "  ✓ KEEP: $fileName (student: $studentId, status: $status, age: {$fileAgeHours}h)\n";
                }
            } else {
                // No student found - file is orphaned
                $shouldDelete = true;
                $reason = "No student record found";
            }
        }
        else {
            // File is recent enough, keep it
            $keptCount++;
        }
        
        // Delete if flagged
        if ($shouldDelete) {
            if (@unlink($file)) {
                $deletedCount++;
                $deletedSize += $fileSize;
                echo "  ✗ DELETED: $fileName (age: {$fileAgeHours}h, size: " . 
                     round($fileSize/1024, 1) . "KB, reason: $reason)\n";
            } else {
                echo "  ⚠ FAILED to delete: $fileName\n";
            }
        }
    }
}

$deletedSizeMB = round($deletedSize / 1024 / 1024, 2);

echo "\n=== Cleanup Summary ===\n";
echo "Files deleted: $deletedCount\n";
echo "Space freed: {$deletedSizeMB} MB\n";
echo "Files kept: $keptCount\n";
echo "Completed: " . date('Y-m-d H:i:s') . "\n";

// Log to file
$logFile = __DIR__ . '/logs/temp_cleanup.log';
$logDir = dirname($logFile);
if (!is_dir($logDir)) {
    mkdir($logDir, 0755, true);
}

$logEntry = sprintf(
    "[%s] Deleted: %d files, Freed: %s MB, Kept: %d files\n",
    date('Y-m-d H:i:s'),
    $deletedCount,
    $deletedSizeMB,
    $keptCount
);

file_put_contents($logFile, $logEntry, FILE_APPEND);
