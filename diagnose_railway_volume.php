<?php
/**
 * Railway Volume Diagnostic Script
 * Run this on Railway to verify volume mount and directory structure
 * Access via: https://your-app.railway.app/diagnose_railway_volume.php
 */

header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html>
<head>
    <title>Railway Volume Diagnostics</title>
    <style>
        body { font-family: monospace; padding: 20px; background: #1e1e1e; color: #d4d4d4; }
        .success { color: #4ec9b0; }
        .error { color: #f48771; }
        .warning { color: #dcdcaa; }
        .info { color: #9cdcfe; }
        h1 { color: #4ec9b0; }
        h2 { color: #569cd6; margin-top: 30px; }
        pre { background: #2d2d2d; padding: 15px; border-radius: 5px; overflow-x: auto; }
        .check { margin: 10px 0; }
        .check::before { content: '→ '; }
    </style>
</head>
<body>
    <h1>🔍 Railway Volume Diagnostics</h1>
    <p>Generated: <?= date('Y-m-d H:i:s T') ?></p>

<?php

// Test 1: Check if Railway volume exists
echo "<h2>1️⃣ Railway Volume Detection</h2>";
$volumePath = '/mnt/assets/uploads';
if (file_exists($volumePath)) {
    echo "<div class='check success'>✅ Railway volume detected at: <strong>$volumePath</strong></div>";
    $isRailway = true;
} else {
    echo "<div class='check error'>❌ Railway volume NOT found at: $volumePath</div>";
    echo "<div class='check warning'>⚠️ Running in local/non-Railway environment</div>";
    $isRailway = false;
}

// Test 2: Check directory structure
echo "<h2>2️⃣ Directory Structure</h2>";
$requiredDirs = [
    '/mnt/assets/uploads/temp/EAF',
    '/mnt/assets/uploads/temp/ID',
    '/mnt/assets/uploads/temp/Letter',
    '/mnt/assets/uploads/temp/Indigency',
    '/mnt/assets/uploads/temp/Grades',
    '/mnt/assets/uploads/student/EAF',
    '/mnt/assets/uploads/student/ID',
    '/mnt/assets/uploads/student/Letter',
    '/mnt/assets/uploads/student/Indigency',
    '/mnt/assets/uploads/student/Grades',
];

$dirStatus = [];
foreach ($requiredDirs as $dir) {
    $exists = is_dir($dir);
    $writable = $exists && is_writable($dir);
    $dirStatus[$dir] = ['exists' => $exists, 'writable' => $writable];
    
    if ($exists && $writable) {
        echo "<div class='check success'>✅ $dir - <strong>OK (writable)</strong></div>";
    } elseif ($exists && !$writable) {
        echo "<div class='check warning'>⚠️ $dir - <strong>EXISTS but NOT writable</strong></div>";
    } else {
        echo "<div class='check error'>❌ $dir - <strong>MISSING</strong></div>";
    }
}

// Test 3: Check symlink
echo "<h2>3️⃣ Symlink Status</h2>";
$symlinkPath = '/app/assets/uploads';
if (is_link($symlinkPath)) {
    $target = readlink($symlinkPath);
    echo "<div class='check success'>✅ Symlink exists: <strong>$symlinkPath</strong></div>";
    echo "<div class='check info'>   → Points to: <strong>$target</strong></div>";
    
    if ($target === $volumePath) {
        echo "<div class='check success'>   → Correct target! ✅</div>";
    } else {
        echo "<div class='check error'>   → Wrong target! Should be: $volumePath ❌</div>";
    }
} elseif (file_exists($symlinkPath)) {
    echo "<div class='check warning'>⚠️ <strong>$symlinkPath</strong> exists but is NOT a symlink</div>";
    echo "<div class='check info'>   → It's a regular directory/file</div>";
} else {
    echo "<div class='check error'>❌ Symlink NOT found at: $symlinkPath</div>";
}

// Test 4: Check permissions
echo "<h2>4️⃣ Permissions</h2>";
if ($isRailway) {
    $perms = fileperms($volumePath);
    $permsOctal = substr(sprintf('%o', $perms), -4);
    echo "<div class='check info'>Volume permissions: <strong>$permsOctal</strong></div>";
    
    if (is_readable($volumePath)) {
        echo "<div class='check success'>✅ Readable</div>";
    } else {
        echo "<div class='check error'>❌ NOT readable</div>";
    }
    
    if (is_writable($volumePath)) {
        echo "<div class='check success'>✅ Writable</div>";
    } else {
        echo "<div class='check error'>❌ NOT writable</div>";
    }
    
    if (is_executable($volumePath)) {
        echo "<div class='check success'>✅ Executable (can access)</div>";
    } else {
        echo "<div class='check error'>❌ NOT executable (cannot access)</div>";
    }
}

// Test 5: Test file write
echo "<h2>5️⃣ Write Test</h2>";
if ($isRailway) {
    $testFile = $volumePath . '/temp/EAF/test_' . time() . '.txt';
    $testContent = 'Railway volume write test - ' . date('Y-m-d H:i:s');
    
    try {
        if (@file_put_contents($testFile, $testContent)) {
            echo "<div class='check success'>✅ Successfully wrote test file: <strong>$testFile</strong></div>";
            
            if (file_exists($testFile)) {
                $readContent = file_get_contents($testFile);
                if ($readContent === $testContent) {
                    echo "<div class='check success'>✅ Successfully read back test file - content matches!</div>";
                } else {
                    echo "<div class='check error'>❌ Content mismatch when reading back</div>";
                }
                
                // Clean up test file
                @unlink($testFile);
                echo "<div class='check info'>🧹 Test file cleaned up</div>";
            }
        } else {
            echo "<div class='check error'>❌ Failed to write test file</div>";
            $error = error_get_last();
            if ($error) {
                echo "<div class='check error'>   Error: " . htmlspecialchars($error['message']) . "</div>";
            }
        }
    } catch (Exception $e) {
        echo "<div class='check error'>❌ Exception: " . htmlspecialchars($e->getMessage()) . "</div>";
    }
}

// Test 6: Check disk space
echo "<h2>6️⃣ Disk Space</h2>";
if ($isRailway) {
    $freeSpace = disk_free_space($volumePath);
    $totalSpace = disk_total_space($volumePath);
    
    if ($freeSpace !== false && $totalSpace !== false) {
        $usedSpace = $totalSpace - $freeSpace;
        $percentUsed = ($usedSpace / $totalSpace) * 100;
        
        echo "<div class='check info'>Total Space: <strong>" . formatBytes($totalSpace) . "</strong></div>";
        echo "<div class='check info'>Used Space: <strong>" . formatBytes($usedSpace) . "</strong> (" . number_format($percentUsed, 2) . "%)</div>";
        echo "<div class='check info'>Free Space: <strong>" . formatBytes($freeSpace) . "</strong></div>";
        
        if ($percentUsed > 90) {
            echo "<div class='check error'>⚠️ Volume is over 90% full!</div>";
        } elseif ($percentUsed > 75) {
            echo "<div class='check warning'>⚠️ Volume is over 75% full</div>";
        } else {
            echo "<div class='check success'>✅ Plenty of space available</div>";
        }
    }
}

// Test 7: List existing files
echo "<h2>7️⃣ Existing Files</h2>";
if ($isRailway) {
    $dirsToCheck = [
        '/mnt/assets/uploads/temp/EAF',
        '/mnt/assets/uploads/temp/ID',
        '/mnt/assets/uploads/temp/Letter',
        '/mnt/assets/uploads/temp/Indigency',
        '/mnt/assets/uploads/temp/Grades',
    ];
    
    $totalFiles = 0;
    foreach ($dirsToCheck as $dir) {
        if (is_dir($dir)) {
            $files = array_diff(scandir($dir), ['.', '..']);
            $fileCount = count($files);
            $totalFiles += $fileCount;
            
            echo "<div class='check info'><strong>" . basename($dir) . "/</strong> - $fileCount file(s)</div>";
            
            if ($fileCount > 0 && $fileCount <= 5) {
                foreach ($files as $file) {
                    $filePath = $dir . '/' . $file;
                    $fileSize = filesize($filePath);
                    $fileDate = date('Y-m-d H:i:s', filemtime($filePath));
                    echo "<div class='check info'>   → $file (" . formatBytes($fileSize) . ") - $fileDate</div>";
                }
            } elseif ($fileCount > 5) {
                echo "<div class='check info'>   → (showing first 5 files)</div>";
                $count = 0;
                foreach ($files as $file) {
                    if ($count++ >= 5) break;
                    $filePath = $dir . '/' . $file;
                    $fileSize = filesize($filePath);
                    $fileDate = date('Y-m-d H:i:s', filemtime($filePath));
                    echo "<div class='check info'>   → $file (" . formatBytes($fileSize) . ") - $fileDate</div>";
                }
            }
        }
    }
    
    echo "<div class='check info'><strong>Total files in temp folders: $totalFiles</strong></div>";
}

// Test 8: PHP Configuration
echo "<h2>8️⃣ PHP Configuration</h2>";
echo "<div class='check info'>PHP Version: <strong>" . PHP_VERSION . "</strong></div>";
echo "<div class='check info'>Max Upload Size: <strong>" . ini_get('upload_max_filesize') . "</strong></div>";
echo "<div class='check info'>Max Post Size: <strong>" . ini_get('post_max_size') . "</strong></div>";
echo "<div class='check info'>Memory Limit: <strong>" . ini_get('memory_limit') . "</strong></div>";
echo "<div class='check info'>Max Execution Time: <strong>" . ini_get('max_execution_time') . "s</strong></div>";

// Test 9: Environment Info
echo "<h2>9️⃣ Environment Info</h2>";
echo "<div class='check info'>Server Software: <strong>" . ($_SERVER['SERVER_SOFTWARE'] ?? 'Unknown') . "</strong></div>";
echo "<div class='check info'>Document Root: <strong>" . ($_SERVER['DOCUMENT_ROOT'] ?? 'Unknown') . "</strong></div>";
echo "<div class='check info'>Script Filename: <strong>" . __FILE__ . "</strong></div>";
echo "<div class='check info'>Current User: <strong>" . get_current_user() . "</strong></div>";
echo "<div class='check info'>Current Directory: <strong>" . getcwd() . "</strong></div>";

// Summary
echo "<h2>📊 Summary</h2>";
$allGood = true;

if (!$isRailway) {
    echo "<div class='check error'>❌ NOT running on Railway (volume not detected)</div>";
    $allGood = false;
} else {
    $allDirsOk = true;
    foreach ($dirStatus as $status) {
        if (!$status['exists'] || !$status['writable']) {
            $allDirsOk = false;
            break;
        }
    }
    
    if ($allDirsOk) {
        echo "<div class='check success'>✅ All directories exist and are writable</div>";
    } else {
        echo "<div class='check error'>❌ Some directories are missing or not writable</div>";
        $allGood = false;
    }
    
    if (is_link($symlinkPath) && readlink($symlinkPath) === $volumePath) {
        echo "<div class='check success'>✅ Symlink is correctly configured</div>";
    } else {
        echo "<div class='check error'>❌ Symlink is missing or misconfigured</div>";
        $allGood = false;
    }
}

if ($allGood && $isRailway) {
    echo "<h2 class='success'>🎉 All Checks Passed! Ready for Testing!</h2>";
    echo "<div class='check success'>Your Railway volume is properly configured and ready to receive uploaded documents.</div>";
} else {
    echo "<h2 class='error'>⚠️ Issues Detected</h2>";
    echo "<div class='check warning'>Please review the issues above. You may need to redeploy or check your Railway configuration.</div>";
}

// Helper function
function formatBytes($bytes, $precision = 2) {
    $units = ['B', 'KB', 'MB', 'GB', 'TB'];
    $bytes = max($bytes, 0);
    $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
    $pow = min($pow, count($units) - 1);
    $bytes /= (1 << (10 * $pow));
    return round($bytes, $precision) . ' ' . $units[$pow];
}

?>

<hr style="margin-top: 40px; border-color: #3c3c3c;">
<p style="color: #858585; font-size: 12px;">
    💡 <strong>Next Steps:</strong><br>
    1. If all checks pass, try registering a test student with documents<br>
    2. Check if files appear in the volume using this diagnostic page<br>
    3. Try viewing the documents in the admin review_registrations page<br>
    4. Delete this diagnostic file after testing for security
</p>
</body>
</html>
