<?php
/**
 * Quick Symlink Status Checker
 * Visit this page to see symlink status and auto-fix if needed
 */

// Force the auto-initialization
require_once __DIR__ . '/includes/railway_volume_init.php';

header('Content-Type: text/plain');

echo "=== Railway Volume Symlink Status ===\n\n";

$volumePath = '/mnt/assets/uploads';
$symlinkPath = '/app/assets/uploads';

// Check volume
if (file_exists($volumePath)) {
    echo "✓ Railway volume exists: $volumePath\n";
} else {
    echo "✗ Railway volume NOT found: $volumePath\n";
}

echo "\n";

// Check symlink
if (file_exists($symlinkPath)) {
    if (is_link($symlinkPath)) {
        $target = readlink($symlinkPath);
        if ($target === $volumePath) {
            echo "✓ Symlink is CORRECT: $symlinkPath -> $target\n";
        } else {
            echo "⚠ Symlink EXISTS but points to WRONG target: $symlinkPath -> $target\n";
            echo "  Expected: $volumePath\n";
        }
    } else {
        echo "✗ $symlinkPath exists but is NOT a symlink (it's a regular directory/file)\n";
    }
} else {
    echo "✗ Symlink does NOT exist: $symlinkPath\n";
}

echo "\n";

// Test volume access
$testDirs = [
    '/mnt/assets/uploads/temp/Grades',
    '/mnt/assets/uploads/temp/EAF',
    '/app/assets/uploads/temp/Grades',
    '/app/assets/uploads/temp/EAF'
];

echo "Directory Access Test:\n";
foreach ($testDirs as $dir) {
    if (is_dir($dir)) {
        $files = scandir($dir);
        $fileCount = count($files) - 2; // Exclude . and ..
        echo "✓ $dir - accessible ($fileCount files)\n";
    } else {
        echo "✗ $dir - NOT accessible\n";
    }
}

echo "\n=== Test Complete ===\n";
echo "\nIf symlink is not correct, try:\n";
echo "1. Refresh this page (auto-fix should trigger)\n";
echo "2. Visit any other page (router.php will auto-fix)\n";
echo "3. Run: https://your-app.railway.app/setup_railway_volume.php?confirm=yes\n";
