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

// Track session activity
include __DIR__ . '/../../includes/student_session_tracker.php';

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
  <title>Accessibility - EducAid</title>
  <link href="../../assets/css/bootstrap.min.css" rel="stylesheet" />
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css" />
  <link href="../../assets/css/student/sidebar.css" rel="stylesheet" />
  <link rel="stylesheet" href="../../assets/css/student/accessibility.css" />
  <link rel="stylesheet" href="../../assets/css/student/animations.css" />
  <script src="../../assets/js/student/accessibility.js"></script>
  <script src="../../assets/js/student/animation_utils.js"></script>
  <style>
    /* FOUC Prevention */
    body { opacity: 0; transition: opacity 0.3s ease; background: #f7fafc; }
    body.ready { opacity: 1; }
    body:not(.ready) .sidebar { visibility: hidden; }
    
    /* Main Content Area Layout */
    .home-section {
      margin-left: 250px;
      width: calc(100% - 250px);
      min-height: calc(100vh - var(--topbar-h, 60px));
      background: #f7fafc;
      padding-top: 56px; /* Account for fixed header height */
      position: relative;
      z-index: 1;
      box-sizing: border-box;
    }

    .sidebar.close ~ .home-section {
      margin-left: 70px;
      width: calc(100% - 70px);
    }

    @media (max-width: 768px) {
      .home-section {
        margin-left: 0 !important;
        width: 100% !important;
      }
    }

    /* Settings Header */
    .settings-header {
      background: transparent;
      border-bottom: none;
      padding: 0;
      margin-bottom: 2rem;
    }
    
    .settings-header h1 {
      color: #1a202c;
      font-weight: 600;
      font-size: 2rem;
      margin: 0;
    }

    /* YouTube-Style Settings Navigation */
    .settings-nav {
      background: #f7fafc;
      border-radius: 12px;
      padding: 0.5rem;
      border: 1px solid #e2e8f0;
    }

    .settings-nav-item {
      display: flex;
      align-items: center;
      padding: 0.75rem 1rem;
      color: #4a5568;
      text-decoration: none;
      border-radius: 8px;
      font-weight: 500;
      font-size: 0.95rem;
      transition: all 0.2s ease;
      margin-bottom: 0.25rem;
    }

    .settings-nav-item:last-child {
      margin-bottom: 0;
    }

    .settings-nav-item:hover {
      background: #edf2f7;
      color: #2d3748;
      text-decoration: none;
    }

    .settings-nav-item.active {
      background: #4299e1;
      color: white;
    }

    .settings-nav-item.active:hover {
      background: #3182ce;
    }

    /* Settings Content Sections */
    .settings-content-section {
      margin-bottom: 3rem;
    }

    .section-title {
      color: #1a202c;
      font-weight: 600;
      font-size: 1.5rem;
      margin: 0 0 0.5rem 0;
    }

    .section-description {
      color: #718096;
      font-size: 0.95rem;
      margin: 0 0 1.5rem 0;
    }

    /* Settings Section Cards */
    .settings-section {
      background: white;
      border-radius: 12px;
      box-shadow: 0 1px 3px rgba(0,0,0,0.1);
      border: 1px solid #e2e8f0;
      margin-bottom: 2rem;
      overflow: hidden;
    }

    .settings-section-body {
      padding: 2rem;
    }

    .setting-item {
      display: flex;
      justify-content: space-between;
      align-items: center;
      padding: 1.5rem 0;
      border-bottom: 1px solid #f1f5f9;
    }

    .setting-item:last-child {
      border-bottom: none;
      padding-bottom: 0;
    }

    .setting-info {
      flex: 1;
    }

    .setting-label {
      font-weight: 600;
      color: #2d3748;
      font-size: 1rem;
      margin-bottom: 0.25rem;
    }

    .setting-value {
      color: #718096;
      font-size: 0.95rem;
      margin-bottom: 0.25rem;
    }

    .setting-description {
      color: #a0aec0;
      font-size: 0.875rem;
    }

    .setting-actions {
      display: flex;
      gap: 0.75rem;
    }

    .btn-setting {
      padding: 0.5rem 1.25rem;
      border-radius: 8px;
      font-weight: 500;
      font-size: 0.9rem;
      border: 1px solid transparent;
    }

    .btn-setting-primary {
      background: #4299e1;
      color: white;
      border-color: #4299e1;
    }

    .btn-setting-primary:hover {
      background: #3182ce;
      border-color: #3182ce;
      color: white;
    }

    .btn-setting-outline {
      background: transparent;
      color: #4a5568;
      border-color: #e2e8f0;
    }

    .btn-setting-outline:hover {
      background: #f7fafc;
      color: #2d3748;
    }

    /* Toggle Switch Styling */
    .form-check-input:checked {
      background-color: #4299e1;
      border-color: #4299e1;
    }

    /* Accessibility Features CSS */
    /* Text Size Options */
    html.text-small {
      font-size: 14px;
    }

    html.text-normal {
      font-size: 16px;
    }

    html.text-large {
      font-size: 18px;
    }

    /* High Contrast Mode */
    html.high-contrast {
      filter: contrast(1.5);
    }

    html.high-contrast body {
      background: #000 !important;
      color: #fff !important;
    }

    html.high-contrast .settings-section,
    html.high-contrast .content-card,
    html.high-contrast .settings-nav {
      background: #1a1a1a !important;
      border-color: #444 !important;
      color: #fff !important;
    }

    html.high-contrast .btn {
      border: 2px solid #fff !important;
      font-weight: 600 !important;
    }

    /* Reduce Animations - Fully disable all animations while keeping functionality */
    html.reduce-animations *,
    html.reduce-animations *::before,
    html.reduce-animations *::after {
      animation: none !important;
      animation-duration: 0s !important;
      animation-delay: 0s !important;
      animation-iteration-count: 1 !important;
      transition: none !important;
      transition-duration: 0s !important;
      transition-delay: 0s !important;
      transform: none !important;
      scroll-behavior: auto !important;
    }
    
    /* Allow essential interactions without animation */
    html.reduce-animations *:hover,
    html.reduce-animations *:focus,
    html.reduce-animations *:active {
      transition: none !important;
      animation: none !important;
      transform: none !important;
    }

    @media (max-width: 768px) {
      .setting-item {
        flex-direction: column;
        align-items: flex-start;
        gap: 1rem;
      }

      .setting-actions {
        width: 100%;
        justify-content: flex-end;
      }

      .settings-section-body {
        padding: 1.5rem;
      }
    }
  </style>
</head>
<body>
  <!-- Student Topbar -->
  <?php include __DIR__ . '/../../includes/student/student_topbar.php'; ?>

  <div id="wrapper" style="padding-top: var(--topbar-h);">
    <!-- Include Sidebar -->
    <?php include __DIR__ . '/../../includes/student/student_sidebar.php'; ?>
    
    <!-- Student Header -->
    <?php include __DIR__ . '/../../includes/student/student_header.php'; ?>
    
    <!-- Main Content Area -->
    <section class="home-section" id="page-content-wrapper">
      <div class="container-fluid py-4 px-4">
        <!-- Settings Header -->
        <div class="settings-header mb-4">
          <h1 class="mb-1">Settings</h1>
        </div>

        <!-- YouTube-style Layout: Sidebar + Content -->
        <div class="row g-4">
          <!-- Settings Navigation Sidebar -->
          <?php include __DIR__ . '/../../includes/student/settings_sidebar.php'; ?>

          <!-- Main Content -->
          <div class="col-12 col-lg-9">
            <!-- Accessibility Section -->
            <div class="settings-content-section">
              <h2 class="section-title">Accessibility</h2>
              <p class="section-description">Customize your experience for better accessibility</p>
              
              <div class="settings-section">
                <div class="settings-section-body">
                  <!-- Text Size -->
                  <div class="setting-item">
                    <div class="setting-info">
                      <div class="setting-label">Text Size</div>
                      <div class="setting-value">Normal</div>
                      <div class="setting-description">Adjust the size of text throughout the application</div>
                    </div>
                    <div class="setting-actions">
                      <div class="btn-group" role="group">
                        <button type="button" class="btn btn-setting btn-setting-outline" id="textSizeSmall">
                          <i class="bi bi-fonts me-1"></i>Small
                        </button>
                        <button type="button" class="btn btn-setting btn-setting-primary active" id="textSizeNormal">
                          <i class="bi bi-fonts me-1"></i>Normal
                        </button>
                        <button type="button" class="btn btn-setting btn-setting-outline" id="textSizeLarge">
                          <i class="bi bi-fonts me-1"></i>Large
                        </button>
                      </div>
                    </div>
                  </div>

                  

                  <!-- Reduce Animations -->
                  <div class="setting-item">
                    <div class="setting-info">
                      <div class="setting-label">Reduce Animations</div>
                      <div class="setting-value">
                        <span class="badge bg-secondary">Disabled</span>
                      </div>
                      <div class="setting-description">Reduce all animations to minimal effects while keeping all functionality intact</div>
                    </div>
                    <div class="setting-actions">
                      <div class="form-check form-switch">
                        <input class="form-check-input" type="checkbox" role="switch" id="reduceAnimationsToggle" style="width: 3rem; height: 1.5rem; cursor: pointer;">
                        <label class="form-check-label ms-2" for="reduceAnimationsToggle"></label>
                      </div>
                    </div>
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
  <script src="../../assets/js/student/sidebar.js"></script>
  
  <script>
    // Accessibility Features
    document.addEventListener('DOMContentLoaded', function() {
      // Load saved preferences
      const savedTextSize = localStorage.getItem('textSize') || 'normal';
      const savedReduceAnimations = localStorage.getItem('reduceAnimations') === 'true';

      // Apply saved preferences
      applyTextSize(savedTextSize);
      applyReduceAnimations(savedReduceAnimations);

      // Text Size Buttons
      const textSizeButtons = {
        small: document.getElementById('textSizeSmall'),
        normal: document.getElementById('textSizeNormal'),
        large: document.getElementById('textSizeLarge')
      };

      Object.entries(textSizeButtons).forEach(([size, button]) => {
        if (button) {
          button.addEventListener('click', function() {
            // Remove active from all buttons
            Object.values(textSizeButtons).forEach(btn => {
              btn.classList.remove('active', 'btn-setting-primary');
              btn.classList.add('btn-setting-outline');
            });
            // Add active to clicked button
            this.classList.add('active', 'btn-setting-primary');
            this.classList.remove('btn-setting-outline');
            
            // Apply and save
            applyTextSize(size);
            localStorage.setItem('textSize', size);
            
            // Update display value
            const settingValue = this.closest('.setting-item').querySelector('.setting-value');
            settingValue.textContent = size.charAt(0).toUpperCase() + size.slice(1);
          });

          // Set initial active state
          if (size === savedTextSize) {
            button.classList.add('active', 'btn-setting-primary');
            button.classList.remove('btn-setting-outline');
            // Update display value
            const settingValue = button.closest('.setting-item').querySelector('.setting-value');
            settingValue.textContent = size.charAt(0).toUpperCase() + size.slice(1);
          }
        }
      });

      // High Contrast removed per request

      // Reduce Animations Toggle (now includes simple animations)
      const reduceAnimationsToggle = document.getElementById('reduceAnimationsToggle');
      if (reduceAnimationsToggle) {
        reduceAnimationsToggle.checked = savedReduceAnimations;
        // Update initial badge
        const badge = reduceAnimationsToggle.closest('.setting-item').querySelector('.badge');
        badge.textContent = savedReduceAnimations ? 'Enabled' : 'Disabled';
        badge.className = savedReduceAnimations ? 'badge bg-success' : 'badge bg-secondary';
        
        reduceAnimationsToggle.addEventListener('change', function() {
          applyReduceAnimations(this.checked);
          localStorage.setItem('reduceAnimations', this.checked);
          
          // Also apply simple animations when reduce animations is enabled
          if (this.checked) {
            if (window.applySimpleAnimations) {
              window.applySimpleAnimations();
            } else {
              document.documentElement.classList.add('simple-animations');
            }
          } else {
            if (window.removeSimpleAnimations) {
              window.removeSimpleAnimations();
            } else {
              document.documentElement.classList.remove('simple-animations');
            }
          }
          
          // Update badge
          const badge = this.closest('.setting-item').querySelector('.badge');
          badge.textContent = this.checked ? 'Enabled' : 'Disabled';
          badge.className = this.checked ? 'badge bg-success' : 'badge bg-secondary';
        });
      }

      function applyTextSize(size) {
        document.documentElement.classList.remove('text-small', 'text-normal', 'text-large');
        document.documentElement.classList.add('text-' + size);
      }

      // High Contrast function removed

      function applyReduceAnimations(enabled) {
        if (enabled) {
          document.documentElement.classList.add('reduce-animations');
          // Also add simple-animations for maximum animation reduction
          document.documentElement.classList.add('simple-animations');
        } else {
          document.documentElement.classList.remove('reduce-animations');
          document.documentElement.classList.remove('simple-animations');
        }
      }
    });
    
    // FOUC Prevention - show body after load
    document.addEventListener('DOMContentLoaded', function() {
      document.body.classList.add('ready');
    });
  </script>
</body>
</html>
