<?php
/**
 * Railway File System Diagnostic
 * Access this via: https://your-railway-url.railway.app/check_railway_files.php
 */

header('Content-Type: text/plain');

echo "=== RAILWAY FILE SYSTEM DIAGNOSTIC ===\n";
echo "Time: " . date('Y-m-d H:i:s') . "\n";
echo "Server: " . gethostname() . "\n\n";

$basePath = __DIR__ . '/assets/uploads/';

echo "Base Path: $basePath\n";
echo "Base Path Exists: " . (is_dir($basePath) ? 'YES' : 'NO') . "\n";
echo "Base Path Writable: " . (is_writable($basePath) ? 'YES' : 'NO') . "\n\n";

$folders = [
    'temp/enrollment_forms',
    'temp/id_pictures',
    'temp/letter_mayor',
    'temp/indigency',
    'temp/grades',
    'student/enrollment_forms',
    'student/id_pictures',
    'student/letter_mayor',
    'student/indigency',
    'student/grades'
];

foreach ($folders as $folder) {
    $fullPath = $basePath . $folder;
    
    echo "=== $folder ===\n";
    echo "Path: $fullPath\n";
    echo "Exists: " . (is_dir($fullPath) ? 'YES' : 'NO') . "\n";
    
    if (is_dir($fullPath)) {
        echo "Writable: " . (is_writable($fullPath) ? 'YES' : 'NO') . "\n";
        
        $files = scandir($fullPath);
        $fileCount = 0;
        $totalSize = 0;
        
        echo "Files:\n";
        foreach ($files as $file) {
            if ($file === '.' || $file === '..') continue;
            
            $filePath = $fullPath . '/' . $file;
            
            if (is_file($filePath)) {
                $size = filesize($filePath);
                $modified = date('Y-m-d H:i:s', filemtime($filePath));
                $permissions = substr(sprintf('%o', fileperms($filePath)), -4);
                
                echo "  - $file\n";
                echo "    Size: " . number_format($size) . " bytes\n";
                echo "    Modified: $modified\n";
                echo "    Permissions: $permissions\n";
                
                $fileCount++;
                $totalSize += $size;
            } elseif (is_dir($filePath)) {
                echo "  [DIR] $file/\n";
            }
        }
        
        if ($fileCount === 0) {
            echo "  (empty - no files)\n";
        } else {
            echo "Total: $fileCount files, " . number_format($totalSize / 1024, 2) . " KB\n";
        }
    } else {
        echo "Directory does not exist!\n";
    }
    
    echo "\n";
}

// Check if running on Railway
echo "=== ENVIRONMENT INFO ===\n";
echo "RAILWAY_ENVIRONMENT: " . ($_ENV['RAILWAY_ENVIRONMENT'] ?? 'Not set') . "\n";
echo "RAILWAY_SERVICE_NAME: " . ($_ENV['RAILWAY_SERVICE_NAME'] ?? 'Not set') . "\n";
echo "RAILWAY_VOLUME_MOUNT_PATH: " . ($_ENV['RAILWAY_VOLUME_MOUNT_PATH'] ?? 'Not set') . "\n";

// Check disk space
$diskFree = disk_free_space($basePath);
$diskTotal = disk_total_space($basePath);
$diskUsed = $diskTotal - $diskFree;
$diskUsedPercent = ($diskUsed / $diskTotal) * 100;

echo "\n=== DISK SPACE ===\n";
echo "Total: " . number_format($diskTotal / 1024 / 1024 / 1024, 2) . " GB\n";
echo "Used: " . number_format($diskUsed / 1024 / 1024 / 1024, 2) . " GB\n";
echo "Free: " . number_format($diskFree / 1024 / 1024 / 1024, 2) . " GB\n";
echo "Used: " . number_format($diskUsedPercent, 1) . "%\n";

echo "\n=== MOUNT INFO ===\n";
if (function_exists('shell_exec')) {
    echo "Mount points:\n";
    $mounts = @shell_exec('mount | grep uploads');
    echo $mounts ?: "  (Unable to get mount info)\n";
} else {
    echo "shell_exec disabled\n";
}

echo "\n=== DIAGNOSTIC COMPLETE ===\n";
