<?php
/** @phpstan-ignore-file */
include __DIR__ . '/../../config/database.php';
if (session_status() === PHP_SESSION_NONE) { session_start(); }
if (!isset($_SESSION['student_username'])) {
    header("Location: ../../unified_login.php");
    exit;
}
$student_id = $_SESSION['student_id'];

// Enforce session timeout via middleware
require_once __DIR__ . '/../../includes/SessionTimeoutMiddleware.php';
$timeoutMiddleware = new SessionTimeoutMiddleware();
$timeoutStatus = $timeoutMiddleware->handle();

// PHPMailer not required here but keep consistent includes if needed

// Get student info for header dropdown
$student_info_query = "SELECT first_name, last_name FROM students WHERE student_id = $1";
$student_info_result = pg_query_params($connection, $student_info_query, [$student_id]);
$student_info = pg_fetch_assoc($student_info_result);

?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Security & Privacy - EducAid</title>
  <link href="../../assets/css/bootstrap.min.css" rel="stylesheet" />
  <link href="../../assets/css/bootstrap-icons.css" rel="stylesheet" />
  <link href="../../assets/css/student/sidebar.css" rel="stylesheet" />
  <link rel="stylesheet" href="../../assets/css/student/accessibility.css" />
  <link rel="stylesheet" href="../../assets/css/student/animations.css" />
  <script src="../../assets/js/student/accessibility.js"></script>
  <script src="../../assets/js/student/animation_utils.js"></script>
  <style>
    body { background: #f7fafc; }
    .home-section { margin-left: 250px; width: calc(100% - 250px); min-height: calc(100vh - var(--topbar-h, 60px)); padding-top: 56px; }
  </style>
</head>
<body>
  <?php include __DIR__ . '/../../includes/student/student_topbar.php'; ?>
  <div id="wrapper" style="padding-top: var(--topbar-h);">
    <?php include __DIR__ . '/../../includes/student/student_sidebar.php'; ?>
    <?php include __DIR__ . '/../../includes/student/student_header.php'; ?>

    <section class="home-section" id="page-content-wrapper">
      <div class="container-fluid py-4 px-4">
        <div class="row">
          <div class="col-12 col-lg-3">
            <?php include __DIR__ . '/../../includes/student/settings_sidebar.php'; ?>
          </div>

          <div class="col-12 col-lg-9">
            <div class="settings-content-section" id="security">
              <h2 class="section-title">Security & Privacy</h2>
              <p class="section-description">Protect your account with strong security settings</p>
              <div class="settings-section">
                <div class="settings-section-body">
                  <div class="setting-item" id="password">
                    <div class="setting-info">
                      <div class="setting-label">Password</div>
                      <div class="setting-value">••••••••••••</div>
                      <div class="setting-description">Last changed: Recently (secure password required)</div>
                    </div>
                    <div class="setting-actions">
                      <button class="btn btn-setting btn-setting-danger" data-bs-toggle="modal" data-bs-target="#passwordModal">
                        <i class="bi bi-key me-1"></i>Change Password
                      </button>
                    </div>
                  </div>
                </div>
              </div>
            </div>

            <!-- Modals needed for security actions -->
            <div class="modal fade" id="passwordModal" tabindex="-1" aria-hidden="true">
              <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content">
                  <div class="modal-header">
                    <h5 class="modal-title">Change Password</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                  </div>
                  <div class="modal-body">
                    <form id="passwordChangeForm">
                      <div class="mb-3">
                        <label class="form-label">Current Password</label>
                        <input type="password" name="current_password" class="form-control" required>
                      </div>
                      <div class="mb-3">
                        <label class="form-label">New Password</label>
                        <input type="password" name="new_password" class="form-control" required>
                      </div>
                      <div class="mb-3">
                        <label class="form-label">Confirm New Password</label>
                        <input type="password" name="confirm_password" class="form-control" required>
                      </div>
                    </form>
                  </div>
                  <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" id="savePasswordBtn" class="btn btn-primary">Save Password</button>
                  </div>
                </div>
              </div>
            </div>

          </div>
        </div>
      </div>
    </section>
  </div>

  <script src="../../assets/js/bootstrap.bundle.min.js"></script>
  <script>
    document.addEventListener('DOMContentLoaded', function() {
      // Hook password save
      document.getElementById('savePasswordBtn')?.addEventListener('click', function() {
        // Minimal client-side check
        const form = document.getElementById('passwordChangeForm');
        const newPass = form.querySelector('input[name="new_password"]').value;
        const confirm = form.querySelector('input[name="confirm_password"]').value;
        if (newPass !== confirm) {
          alert('New password and confirmation do not match.');
          return;
        }
        // Submit normally (implementation can use AJAX if desired)
        form.submit();
      });

      // Apply simple animations if user choice saved
      if (localStorage.getItem('simpleAnimations') === 'true') {
        window.applySimpleAnimations && window.applySimpleAnimations();
      }
    });
  </script>
</body>
</html>
