<?php
// TEMPORARY DIAGNOSTIC SCRIPT - DELETE AFTER USE
// Access via: https://your-domain.railway.app/check_upload_files.php

// Security: Only allow access from specific IP or add password
$allowed = true; // Change to false and add authentication in production

if (!$allowed) {
    die('Access denied');
}

echo "<h1>Upload Directory Contents</h1>";
echo "<pre>";

$dirs = [
    '/app/assets/uploads/temp/enrollment_forms/',
    '/app/assets/uploads/temp/id_pictures/',
    '/app/assets/uploads/temp/letter_mayor/',
    '/app/assets/uploads/temp/indigency/',
    '/app/assets/uploads/temp/grades/'
];

foreach ($dirs as $dir) {
    echo "\n========================================\n";
    echo "Directory: $dir\n";
    echo "========================================\n";
    
    if (is_dir($dir)) {
        echo "Exists: YES\n";
        echo "Readable: " . (is_readable($dir) ? 'YES' : 'NO') . "\n";
        echo "Writable: " . (is_writable($dir) ? 'YES' : 'NO') . "\n\n";
        
        $files = scandir($dir);
        if ($files) {
            echo "Files:\n";
            foreach ($files as $file) {
                if ($file === '.' || $file === '..') continue;
                
                $fullPath = $dir . $file;
                $size = is_file($fullPath) ? filesize($fullPath) : 0;
                $modified = is_file($fullPath) ? date('Y-m-d H:i:s', filemtime($fullPath)) : 'N/A';
                $type = is_dir($fullPath) ? 'DIR' : 'FILE';
                
                echo sprintf(
                    "  %-8s %-50s %10s bytes  %s\n",
                    $type,
                    $file,
                    number_format($size),
                    $modified
                );
            }
        } else {
            echo "ERROR: Cannot read directory\n";
        }
    } else {
        echo "Exists: NO\n";
    }
}

echo "\n========================================\n";
echo "Volume Mount Check\n";
echo "========================================\n";
exec('df -h | grep uploads', $output);
echo implode("\n", $output) ?: "No volume mount found for uploads";

echo "\n\n========================================\n";
echo "Recent Student from Database\n";
echo "========================================\n";

require 'config/database.php';
$result = pg_query($connection, "
    SELECT d.student_id, d.document_type_code, d.file_path, d.file_name, d.upload_date
    FROM documents d
    ORDER BY d.upload_date DESC
    LIMIT 10
");

if ($result) {
    while ($row = pg_fetch_assoc($result)) {
        echo "Student: {$row['student_id']}\n";
        echo "  Type: {$row['document_type_code']}\n";
        echo "  Path: {$row['file_path']}\n";
        echo "  File: {$row['file_name']}\n";
        echo "  Date: {$row['upload_date']}\n";
        
        // Check if file exists
        $checkPaths = [
            '/app/' . $row['file_path'],
            '/app/' . ltrim($row['file_path'], './'),
            $row['file_path']
        ];
        
        $found = false;
        foreach ($checkPaths as $path) {
            if (file_exists($path)) {
                echo "  EXISTS: YES at $path\n";
                $found = true;
                break;
            }
        }
        if (!$found) {
            echo "  EXISTS: NO (checked " . count($checkPaths) . " paths)\n";
        }
        echo "\n";
    }
} else {
    echo "Database query failed: " . pg_last_error($connection);
}

echo "</pre>";

echo "<hr>";
echo "<p style='color: red;'><strong>⚠️ SECURITY WARNING:</strong> Delete this file after use!</p>";
