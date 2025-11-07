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
        
        // Remove existing file/directory/symlink
        if (file_exists($symlinkPath) || is_link($symlinkPath)) {
            if (is_link($symlinkPath)) {
                @unlink($symlinkPath);
            } else {
                // It's a directory - try to remove it
                // Don't recursively delete in case there are files we want to keep
                @rmdir($symlinkPath);
            }
        }
        
        // Create symlink
        if (@symlink($volumePath, $symlinkPath)) {
            error_log("Railway volume auto-setup: ✓ Symlink created successfully");
        } else {
            error_log("Railway volume auto-setup: ✗ Failed to create symlink");
        }
    }
}
