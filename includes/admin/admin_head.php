<?php
// Unified head include for admin pages
// Usage:
//   $page_title = 'Dashboard'; // optional
//   $extra_css = ['../../assets/css/admin/manage_applicants.css']; // optional array of extra CSS hrefs
// Then: include __DIR__ . '/../../includes/admin/admin_head.php';

// Load security headers first (if not already loaded)
if (!defined('SECURITY_HEADERS_LOADED')) {
    require_once __DIR__ . '/../../config/security_headers.php';
}

if (!isset($page_title) || trim($page_title) === '') {
    $page_title = 'Admin';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title><?= htmlspecialchars($page_title) ?> - EducAid Admin</title>
  <link rel="stylesheet" href="../../assets/css/bootstrap.min.css" />
  <link rel="stylesheet" href="../../assets/css/bootstrap-icons.css" />
  <link rel="stylesheet" href="../../assets/css/admin/homepage.css" />
  <link rel="stylesheet" href="../../assets/css/admin/sidebar.css" />
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600&display=swap" rel="stylesheet" />
<?php if (!empty($extra_css) && is_array($extra_css)): foreach ($extra_css as $cssFile): ?>
  <link rel="stylesheet" href="<?= htmlspecialchars($cssFile) ?>" />
<?php endforeach; endif; ?>
  <!-- Admin JavaScript -->
  <script src="../../assets/js/admin/sidebar.js"></script>
  <script src="../../assets/js/admin/notification_bell.js"></script>
  
<?php if (isset($GLOBALS['session_timeout_status']) && $GLOBALS['session_timeout_status']['status'] === 'active'): ?>
  <!-- Session Timeout Warning System -->
  <link rel="stylesheet" href="../../assets/css/session-timeout-warning.css">
  <script>
    window.sessionTimeoutConfig = {
      idle_timeout_minutes: <?= $GLOBALS['session_timeout_status']['idle_timeout_seconds'] / 60 ?>,
      absolute_timeout_hours: <?= $GLOBALS['session_timeout_status']['absolute_timeout_seconds'] / 3600 ?>,
      warning_before_logout_seconds: <?= $GLOBALS['session_timeout_status']['warning_threshold'] ?>,
      enabled: true
    };
  </script>
  <script src="../../assets/js/session-timeout-warning.js"></script>
<?php endif; ?>
</head>
