<?php
/**
 * View Graduating Students
 * Shows list of students marked as graduating from previous academic year
 */
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (!isset($_SESSION['admin_username'])) {
    header("Location: ../../unified_login.php");
    exit;
}

include __DIR__ . '/../../config/database.php';
include __DIR__ . '/../../includes/permissions.php';

// Check if user is super admin
$admin_role = getCurrentAdminRole($connection);
if ($admin_role !== 'super_admin') {
    header("Location: dashboard.php");
    exit;
}

// Get graduating students from session (if set by distribution control)
$graduates = $_SESSION['pending_graduates'] ?? [];

// If no graduates in session, fetch from database
// Get ALL graduating students from previous academic years (not just immediate previous)
if (empty($graduates)) {
    $current_year_query = pg_query($connection, "SELECT value FROM config WHERE key = 'current_academic_year'");
    if ($current_year_query) {
        $year_row = pg_fetch_assoc($current_year_query);
        $current_academic_year = $year_row['value'] ?? '';
        
        // Fetch ALL graduating students from years BEFORE the current academic year
        if (!empty($current_academic_year)) {
            $graduates_query = pg_query_params($connection,
                "SELECT student_id, first_name, last_name, current_year_level, status_academic_year, 
                        email, municipality_id, school_id, status, last_status_update
                 FROM students
                 WHERE is_graduating = TRUE
                   AND status_academic_year < $1
                   AND status IN ('active', 'applicant')
                   AND (is_archived = FALSE OR is_archived IS NULL)
                 ORDER BY status_academic_year DESC, current_year_level, last_name",
                [$current_academic_year]
            );
            
            if ($graduates_query) {
                while ($grad = pg_fetch_assoc($graduates_query)) {
                    $graduates[] = $grad;
                }
            }
        }
    }
}

$page_title = 'Graduating Students';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $page_title ?> - EducAid</title>
    <link href="../../assets/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        body {
            background: #f8f9fa;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        .page-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 2rem;
            margin-bottom: 2rem;
            border-radius: 0 0 15px 15px;
        }
        .student-card {
            background: white;
            border-radius: 10px;
            padding: 1.5rem;
            margin-bottom: 1rem;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            transition: all 0.3s ease;
        }
        .student-card:hover {
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
            transform: translateY(-2px);
        }
        .year-badge {
            display: inline-block;
            padding: 0.5rem 1rem;
            border-radius: 20px;
            font-weight: 600;
            font-size: 0.9rem;
        }
        .year-4th { background: #fef3c7; color: #92400e; }
        .year-5th { background: #dbeafe; color: #1e40af; }
        .status-badge {
            padding: 0.25rem 0.75rem;
            border-radius: 15px;
            font-size: 0.85rem;
            font-weight: 500;
        }
        .status-active { background: #d1fae5; color: #065f46; }
        .status-applicant { background: #fef3c7; color: #92400e; }
    </style>
</head>
<body>
    <div class="page-header">
        <div class="container">
            <h1 class="mb-2">
                <i class="bi bi-mortarboard-fill me-2"></i>
                Graduating Students Review
            </h1>
            <p class="mb-0">Students who marked themselves as graduating in the previous academic year</p>
        </div>
    </div>
    
    <div class="container mb-5">
        <?php if (empty($graduates)): ?>
            <div class="alert alert-info">
                <i class="bi bi-info-circle me-2"></i>
                <strong>No graduating students found.</strong><br>
                There are no students marked as graduating from the previous academic year who are still active in the system.
            </div>
        <?php else: ?>
            <div class="alert alert-warning mb-4">
                <i class="bi bi-exclamation-triangle-fill me-2"></i>
                <strong>Found <?= count($graduates) ?> graduating student(s)</strong><br>
                These students marked themselves as graduating and should be reviewed before starting the new distribution.
            </div>
            
            <!-- Summary Statistics -->
            <div class="row mb-4">
                <div class="col-md-4">
                    <div class="card text-center border-0 shadow-sm">
                        <div class="card-body">
                            <h3 class="mb-0 text-primary"><?= count($graduates) ?></h3>
                            <small class="text-muted">Total Graduates</small>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card text-center border-0 shadow-sm">
                        <div class="card-body">
                            <h3 class="mb-0 text-success"><?= count(array_filter($graduates, fn($g) => $g['status'] === 'active')) ?></h3>
                            <small class="text-muted">Active Status</small>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card text-center border-0 shadow-sm">
                        <div class="card-body">
                            <h3 class="mb-0 text-warning"><?= count(array_filter($graduates, fn($g) => $g['status'] === 'applicant')) ?></h3>
                            <small class="text-muted">Applicant Status</small>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Student Cards -->
            <div class="row">
                <?php foreach ($graduates as $student): ?>
                    <div class="col-lg-6 mb-3">
                        <div class="student-card">
                            <div class="d-flex justify-content-between align-items-start mb-3">
                                <div>
                                    <h5 class="mb-1">
                                        <i class="bi bi-person-fill text-primary me-2"></i>
                                        <?= htmlspecialchars($student['first_name'] . ' ' . $student['last_name']) ?>
                                    </h5>
                                    <small class="text-muted"><?= htmlspecialchars($student['student_id']) ?></small>
                                </div>
                                <span class="year-badge <?= $student['current_year_level'] === '4th Year' ? 'year-4th' : 'year-5th' ?>">
                                    <?= htmlspecialchars($student['current_year_level']) ?>
                                </span>
                            </div>
                            
                            <div class="row g-2 mb-3">
                                <div class="col-6">
                                    <small class="text-muted d-block">Academic Year</small>
                                    <strong><?= htmlspecialchars($student['status_academic_year']) ?></strong>
                                </div>
                                <div class="col-6">
                                    <small class="text-muted d-block">Status</small>
                                    <span class="status-badge status-<?= strtolower($student['status']) ?>">
                                        <?= ucfirst(htmlspecialchars($student['status'])) ?>
                                    </span>
                                </div>
                            </div>
                            
                            <div class="mb-2">
                                <small class="text-muted">
                                    <i class="bi bi-envelope me-1"></i>
                                    <?= htmlspecialchars($student['email'] ?? 'No email') ?>
                                </small>
                            </div>
                            
                            <?php if (!empty($student['last_status_update'])): ?>
                            <div>
                                <small class="text-muted">
                                    <i class="bi bi-clock me-1"></i>
                                    Last updated: <?= date('M j, Y g:i A', strtotime($student['last_status_update'])) ?>
                                </small>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
        
        <div class="text-center mt-4">
            <button type="button" class="btn btn-secondary" onclick="window.close()">
                <i class="bi bi-x-circle me-2"></i>Close Window
            </button>
        </div>
    </div>
    
    <script src="../../assets/js/bootstrap.bundle.min.js"></script>
</body>
</html>
