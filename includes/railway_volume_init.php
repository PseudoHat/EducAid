<?php
/**
 * Railway Volume Auto-Initialization
 * Automatically creates symlink on first request if needed
 * Include this at the top of entry point files
 */

// Only run on Railway (when volume exists)
if (file_exists('/mnt/assets/uploads/')) {
    $symlinkPath = '/app/assets/uploads';
    $volumePath = '/mnt/assets/uploads';
    
    // Check if symlink exists and is correct
    $needsSetup = false;
    
    if (!file_exists($symlinkPath)) {
        $needsSetup = true;
    } elseif (!is_link($symlinkPath)) {
        // It's a regular directory, not a symlink - needs to be replaced
        $needsSetup = true;
    } elseif (is_link($symlinkPath) && readlink($symlinkPath) !== $volumePath) {
        // Symlink exists but points to wrong location
        $needsSetup = true;
    }
    
    if ($needsSetup) {
        // Log the setup attempt
        error_log("Railway volume auto-setup: Creating symlink...");
        
        // Create parent directory if needed
        if (!is_dir('/app/assets')) {
            @mkdir('/app/assets', 0755, true);
        }
        
        // AGGRESSIVELY remove existing file/directory/symlink
        if (file_exists($symlinkPath) || is_link($symlinkPath)) {
            if (is_link($symlinkPath)) {
                @unlink($symlinkPath);
            } else {
                // It's a directory - use shell command for forceful removal
                $output = [];
                $return = 0;
                @exec('rm -rf ' . escapeshellarg($symlinkPath) . ' 2>&1', $output, $return);
                if ($return !== 0) {
                    error_log("Railway volume auto-setup: Failed to remove directory: " . implode("\n", $output));
                    // Try PHP removal as fallback
                    @chmod($symlinkPath, 0777);
                    @rmdir($symlinkPath);
                }
            }
        }
        
        // Create symlink
        if (@symlink($volumePath, $symlinkPath)) {
            error_log("Railway volume auto-setup: ✓ Symlink created successfully");
            // Verify it worked
            if (is_link($symlinkPath) && readlink($symlinkPath) === $volumePath) {
                error_log("Railway volume auto-setup: ✓ Symlink verified: " . readlink($symlinkPath));
            }
        } else {
            // Try shell command as fallback
            $output = [];
            $return = 0;
            @exec('ln -sf ' . escapeshellarg($volumePath) . ' ' . escapeshellarg($symlinkPath) . ' 2>&1', $output, $return);
            if ($return === 0) {
                error_log("Railway volume auto-setup: ✓ Symlink created via shell command");
            } else {
                error_log("Railway volume auto-setup: ✗ Failed to create symlink: " . implode("\n", $output));
            }
        }
    }
}
