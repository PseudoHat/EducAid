<?php
/**
 * EducAid Website Entry Point
 * Redirects directly to landing page
 */

// Auto-initialize Railway volume symlink if needed
require_once __DIR__ . '/../includes/railway_volume_init.php';

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Redirect to landing page
header('Location: landingpage.php');
exit;
?>