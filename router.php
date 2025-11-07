<?php
/**
 * Router Entry Point for PHP Built-in Server
 * 
 * This is a minimal entry point required for PHP's built-in server.
 * The actual routing logic is in core/AppRouter.php for better security.
 * 
 * This file MUST remain in the root directory for Railway/production deployment.
 * Usage: php -S 0.0.0.0:8080 router.php
 */

// Define constant to allow core router to execute
define('ROUTER_ENTRY', true);

// Delegate all routing logic to the core router
require_once __DIR__ . '/core/AppRouter.php';
return true;
?>
