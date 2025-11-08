<?php
/**
 * Manual Railway Volume Setup Script
 * Run this ONCE to create directories and symlink
 * Access via: https://your-app.railway.app/setup_railway_volume.php
 */

header('Content-Type: text/html; charset=utf-8');

// Require manual confirmation to prevent accidental runs
$confirmed = isset($_GET['confirm']) && $_GET['confirm'] === 'yes';

?>
<!DOCTYPE html>
<html>
<head>
    <title>Railway Volume Setup</title>
    <style>
        body { font-family: monospace; padding: 20px; background: #1e1e1e; color: #d4d4d4; }
        .success { color: #4ec9b0; }
        .error { color: #f48771; }
        .warning { color: #dcdcaa; }
        .info { color: #9cdcfe; }
        h1 { color: #4ec9b0; }
        pre { background: #2d2d2d; padding: 15px; border-radius: 5px; }
        .log { margin: 10px 0; }
        button { background: #0e639c; color: white; padding: 10px 20px; border: none; border-radius: 5px; cursor: pointer; font-size: 16px; }
        button:hover { background: #1177bb; }
    </style>
</head>
<body>
    <h1>🔧 Railway Volume Setup</h1>
    <p>Generated: <?= date('Y-m-d H:i:s T') ?></p>

<?php

if (!$confirmed) {
    echo '<div class="warning">';
    echo '<h2>⚠️ Confirmation Required</h2>';
    echo '<p>This script will:</p>';
    echo '<ul>';
    echo '<li>Create directory structure in <code>/mnt/assets/uploads/</code></li>';
    echo '<li>Delete <code>/app/assets/uploads</code> if it exists</li>';
    echo '<li>Create symlink from <code>/app/assets/uploads</code> to <code>/mnt/assets/uploads</code></li>';
    echo '<li>Set permissions to 755</li>';
    echo '</ul>';
    echo '<p><strong>Click the button below to proceed:</strong></p>';
    echo '<form method="get">';
    echo '<input type="hidden" name="confirm" value="yes">';
    echo '<button type="submit">🚀 Run Setup</button>';
    echo '</form>';
    echo '</div>';
    exit;
}

echo '<h2>🚀 Running Setup...</h2>';

$logs = [];
$errors = [];
$success = true;

// Step 1: Check if volume exists
$logs[] = ['info', 'Checking for Railway volume...'];
if (!file_exists('/mnt/assets/uploads')) {
    $errors[] = 'Railway volume not found at /mnt/assets/uploads';
    $success = false;
} else {
    $logs[] = ['success', '✅ Railway volume detected at /mnt/assets/uploads'];
}

// Step 2: Create directory structure
if ($success) {
    $logs[] = ['info', 'Creating directory structure...'];
    
    $dirs = [
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
    
    foreach ($dirs as $dir) {
        if (!is_dir($dir)) {
            if (@mkdir($dir, 0755, true)) {
                $logs[] = ['success', "✅ Created: $dir"];
            } else {
                $errors[] = "Failed to create: $dir";
                $success = false;
            }
        } else {
            $logs[] = ['info', "Already exists: $dir"];
        }
    }
    
    // Set permissions
    if (@chmod('/mnt/assets/uploads', 0755)) {
        $logs[] = ['success', '✅ Set permissions to 755 on /mnt/assets/uploads'];
    }
}

// Step 3: Create symlink
if ($success) {
    $logs[] = ['info', 'Setting up symlink...'];
    
    $symlinkPath = '/app/assets/uploads';
    $targetPath = '/mnt/assets/uploads';
    
    // Remove existing directory/symlink
    if (file_exists($symlinkPath)) {
        if (is_link($symlinkPath)) {
            $logs[] = ['info', 'Removing existing symlink...'];
            if (@unlink($symlinkPath)) {
                $logs[] = ['success', '✅ Old symlink removed'];
            } else {
                $errors[] = 'Failed to remove old symlink';
                $success = false;
            }
        } else if (is_dir($symlinkPath)) {
            $logs[] = ['info', 'Removing existing directory...'];
            
            // Try to remove directory (only if empty or we have permission)
            if (@rmdir($symlinkPath)) {
                $logs[] = ['success', '✅ Old directory removed'];
            } else {
                // Try using shell command as fallback
                $output = [];
                $returnVar = 0;
                exec('rm -rf ' . escapeshellarg($symlinkPath) . ' 2>&1', $output, $returnVar);
                
                if ($returnVar === 0) {
                    $logs[] = ['success', '✅ Old directory removed (via shell)'];
                } else {
                    $errors[] = 'Failed to remove old directory: ' . implode("\n", $output);
                    $success = false;
                }
            }
        }
    }
    
    // Create symlink
    if ($success && !file_exists($symlinkPath)) {
        // Ensure parent directory exists
        $parentDir = dirname($symlinkPath);
        if (!is_dir($parentDir)) {
            mkdir($parentDir, 0755, true);
        }
        
        if (@symlink($targetPath, $symlinkPath)) {
            $logs[] = ['success', "✅ Symlink created: $symlinkPath → $targetPath"];
        } else {
            // Try using shell command as fallback
            $output = [];
            $returnVar = 0;
            exec('ln -sf ' . escapeshellarg($targetPath) . ' ' . escapeshellarg($symlinkPath) . ' 2>&1', $output, $returnVar);
            
            if ($returnVar === 0 && is_link($symlinkPath)) {
                $logs[] = ['success', "✅ Symlink created (via shell): $symlinkPath → $targetPath"];
            } else {
                $errors[] = 'Failed to create symlink: ' . implode("\n", $output);
                $success = false;
            }
        }
    }
}

// Step 4: Verify setup
if ($success) {
    $logs[] = ['info', 'Verifying setup...'];
    
    // Test write
    $testFile = '/mnt/assets/uploads/temp/EAF/test_' . time() . '.txt';
    if (@file_put_contents($testFile, 'test')) {
        $logs[] = ['success', '✅ Write test successful'];
        @unlink($testFile);
    } else {
        $errors[] = 'Write test failed';
        $success = false;
    }
    
    // Verify symlink
    if (is_link('/app/assets/uploads')) {
        $target = readlink('/app/assets/uploads');
        if ($target === '/mnt/assets/uploads') {
            $logs[] = ['success', '✅ Symlink verified'];
        } else {
            $errors[] = "Symlink points to wrong target: $target";
            $success = false;
        }
    } else {
        $errors[] = 'Symlink verification failed';
        $success = false;
    }
}

// Display logs
echo '<div style="background: #2d2d2d; padding: 15px; border-radius: 5px; margin: 20px 0;">';
foreach ($logs as $log) {
    $class = $log[0];
    $message = $log[1];
    echo "<div class='log $class'>$message</div>";
}
echo '</div>';

// Display errors if any
if (!empty($errors)) {
    echo '<div style="background: #3c1f1f; padding: 15px; border-radius: 5px; margin: 20px 0;">';
    echo '<h2 class="error">❌ Errors</h2>';
    foreach ($errors as $error) {
        echo "<div class='log error'>$error</div>";
    }
    echo '</div>';
}

// Summary
if ($success) {
    echo '<div class="success">';
    echo '<h2>🎉 Setup Complete!</h2>';
    echo '<p>Railway volume is now properly configured.</p>';
    echo '<p><strong>Next steps:</strong></p>';
    echo '<ol>';
    echo '<li>Run the <a href="/diagnose_railway_volume.php" style="color: #4ec9b0;">diagnostic tool</a> to verify</li>';
    echo '<li>Test student registration with document uploads</li>';
    echo '<li>Delete this setup file for security</li>';
    echo '</ol>';
    echo '</div>';
} else {
    echo '<div class="error">';
    echo '<h2>⚠️ Setup Failed</h2>';
    echo '<p>Some operations failed. You may need to:</p>';
    echo '<ul>';
    echo '<li>Check Railway volume permissions</li>';
    echo '<li>Manually SSH into Railway and run setup commands</li>';
    echo '<li>Contact Railway support if volume is not accessible</li>';
    echo '</ul>';
    echo '</div>';
}

?>

</body>
</html>
