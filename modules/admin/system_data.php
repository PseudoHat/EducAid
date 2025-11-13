<?php
include __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../services/AuditLogger.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['admin_username'])) {
    header("Location: ../../unified_login.php");
    exit;
}

// Initialize AuditLogger
$auditLogger = new AuditLogger($connection);

// Check if current admin is super_admin
$current_admin_role = 'super_admin'; // Default for backward compatibility
if (isset($_SESSION['admin_id'])) {
    $roleQuery = pg_query_params($connection, "SELECT role FROM admins WHERE admin_id = $1", [$_SESSION['admin_id']]);
    $roleData = pg_fetch_assoc($roleQuery);
    $current_admin_role = $roleData['role'] ?? 'super_admin';
}

// Only super_admin can access this page
if ($current_admin_role !== 'super_admin') {
    header("Location: homepage.php");
    exit;
}

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Debug logging
    error_log("POST received: " . print_r($_POST, true));
    
    // Add University
    if (isset($_POST['add_university'])) {
        $name = trim($_POST['university_name']);
        $code = trim(strtoupper($_POST['university_code']));
        
        error_log("Adding university: $name ($code)");
        
        if (!empty($name) && !empty($code)) {
            $insertQuery = "INSERT INTO universities (name, code) VALUES ($1, $2) RETURNING university_id";
            $result = pg_query_params($connection, $insertQuery, [$name, $code]);
            
            if ($result) {
                $new_university = pg_fetch_assoc($result);
                $university_id = $new_university['university_id'];
                
                error_log("University added successfully with ID: $university_id");
                
                $notification_msg = "New university added: " . $name . " (" . $code . ")";
                pg_query_params($connection, "INSERT INTO admin_notifications (message) VALUES ($1)", [$notification_msg]);
                
                // Audit Trail
                $auditLogger->logEvent(
                    'university_added',
                    'system_data',
                    "Added new university: {$name} ({$code})",
                    [
                        'user_id' => $_SESSION['admin_id'] ?? null,
                        'user_type' => 'admin',
                        'username' => $_SESSION['admin_username'] ?? 'Unknown',
                        'affected_table' => 'universities',
                        'affected_record_id' => $university_id,
                        'new_values' => [
                            'university_id' => $university_id,
                            'name' => $name,
                            'code' => $code
                        ],
                        'metadata' => [
                            'action' => 'add',
                            'admin_role' => $current_admin_role
                        ]
                    ]
                );
                
                $success = "University added successfully!";
                
                // Redirect to prevent form resubmission
                header("Location: " . $_SERVER['PHP_SELF'] . "?success=university_added");
                exit;
            } else {
                $db_error = pg_last_error($connection);
                error_log("Failed to add university: $db_error");
                $error = "Failed to add university. Code may already exist. Error: " . $db_error;
            }
        } else {
            $error = "Please fill in all required fields.";
        }
    }
    
    // Edit University
    if (isset($_POST['edit_university'])) {
        $university_id = intval($_POST['university_id']);
        $name = trim($_POST['university_name']);
        $code = trim(strtoupper($_POST['university_code']));
        
        if (!empty($name) && !empty($code)) {
            // Get old values for audit
            $oldQuery = "SELECT name, code FROM universities WHERE university_id = $1";
            $oldResult = pg_query_params($connection, $oldQuery, [$university_id]);
            $oldValues = pg_fetch_assoc($oldResult);
            
            $updateQuery = "UPDATE universities SET name = $1, code = $2 WHERE university_id = $3";
            $result = pg_query_params($connection, $updateQuery, [$name, $code, $university_id]);
            
            if ($result) {
                $notification_msg = "University updated: " . $name . " (" . $code . ")";
                pg_query_params($connection, "INSERT INTO admin_notifications (message) VALUES ($1)", [$notification_msg]);
                
                // Audit Trail
                $auditLogger->logEvent(
                    'university_updated',
                    'system_data',
                    "Updated university (ID: {$university_id}): {$oldValues['name']} → {$name}",
                    [
                        'user_id' => $_SESSION['admin_id'] ?? null,
                        'user_type' => 'admin',
                        'username' => $_SESSION['admin_username'] ?? 'Unknown',
                        'affected_table' => 'universities',
                        'affected_record_id' => $university_id,
                        'old_values' => [
                            'name' => $oldValues['name'],
                            'code' => $oldValues['code']
                        ],
                        'new_values' => [
                            'name' => $name,
                            'code' => $code
                        ],
                        'metadata' => [
                            'action' => 'edit',
                            'admin_role' => $current_admin_role
                        ]
                    ]
                );
                
                $success = "University updated successfully!";
            } else {
                $error = "Failed to update university. Code may already exist.";
            }
        }
    }
    
    // Add Barangay
    if (isset($_POST['add_barangay'])) {
        $name = trim($_POST['barangay_name']);
        $municipality_id = 1; // Default municipality
        
        if (!empty($name)) {
            $insertQuery = "INSERT INTO barangays (municipality_id, name) VALUES ($1, $2) RETURNING barangay_id";
            $result = pg_query_params($connection, $insertQuery, [$municipality_id, $name]);
            
            if ($result) {
                $new_barangay = pg_fetch_assoc($result);
                $barangay_id = $new_barangay['barangay_id'];
                
                $notification_msg = "New barangay added: " . $name;
                pg_query_params($connection, "INSERT INTO admin_notifications (message) VALUES ($1)", [$notification_msg]);
                
                // Audit Trail
                $auditLogger->logEvent(
                    'barangay_added',
                    'system_data',
                    "Added new barangay: {$name}",
                    [
                        'user_id' => $_SESSION['admin_id'] ?? null,
                        'user_type' => 'admin',
                        'username' => $_SESSION['admin_username'] ?? 'Unknown',
                        'affected_table' => 'barangays',
                        'affected_record_id' => $barangay_id,
                        'new_values' => [
                            'barangay_id' => $barangay_id,
                            'name' => $name,
                            'municipality_id' => $municipality_id
                        ],
                        'metadata' => [
                            'action' => 'add',
                            'admin_role' => $current_admin_role
                        ]
                    ]
                );
                
                $success = "Barangay added successfully!";
            } else {
                $error = "Failed to add barangay.";
            }
        }
    }
    
    // Edit Barangay
    if (isset($_POST['edit_barangay'])) {
        $barangay_id = intval($_POST['barangay_id']);
        $name = trim($_POST['barangay_name']);
        
        if (!empty($name)) {
            // Get old values for audit
            $oldQuery = "SELECT name FROM barangays WHERE barangay_id = $1";
            $oldResult = pg_query_params($connection, $oldQuery, [$barangay_id]);
            $oldValues = pg_fetch_assoc($oldResult);
            
            $updateQuery = "UPDATE barangays SET name = $1 WHERE barangay_id = $2";
            $result = pg_query_params($connection, $updateQuery, [$name, $barangay_id]);
            
            if ($result) {
                $notification_msg = "Barangay updated: " . $name;
                pg_query_params($connection, "INSERT INTO admin_notifications (message) VALUES ($1)", [$notification_msg]);
                
                // Audit Trail
                $auditLogger->logEvent(
                    'barangay_updated',
                    'system_data',
                    "Updated barangay (ID: {$barangay_id}): {$oldValues['name']} → {$name}",
                    [
                        'user_id' => $_SESSION['admin_id'] ?? null,
                        'user_type' => 'admin',
                        'username' => $_SESSION['admin_username'] ?? 'Unknown',
                        'affected_table' => 'barangays',
                        'affected_record_id' => $barangay_id,
                        'old_values' => [
                            'name' => $oldValues['name']
                        ],
                        'new_values' => [
                            'name' => $name
                        ],
                        'metadata' => [
                            'action' => 'edit',
                            'admin_role' => $current_admin_role
                        ]
                    ]
                );
                
                $success = "Barangay updated successfully!";
            } else {
                $error = "Failed to update barangay.";
            }
        }
    }
    
    // Delete University
    if (isset($_POST['delete_university'])) {
        $university_id = intval($_POST['university_id']);
        
        // Check if university is being used by students
        $checkQuery = "SELECT COUNT(*) as count FROM students WHERE university_id = $1";
        $checkResult = pg_query_params($connection, $checkQuery, [$university_id]);
        $checkData = pg_fetch_assoc($checkResult);
        
        if ($checkData['count'] > 0) {
            $error = "Cannot delete university. It is currently assigned to " . $checkData['count'] . " student(s).";
        } else {
            // Get university details for audit before deletion
            $getQuery = "SELECT name, code FROM universities WHERE university_id = $1";
            $getResult = pg_query_params($connection, $getQuery, [$university_id]);
            $universityData = pg_fetch_assoc($getResult);
            
            $deleteQuery = "DELETE FROM universities WHERE university_id = $1";
            $result = pg_query_params($connection, $deleteQuery, [$university_id]);
            
            if ($result) {
                $notification_msg = "University deleted: {$universityData['name']} (ID: {$university_id})";
                pg_query_params($connection, "INSERT INTO admin_notifications (message) VALUES ($1)", [$notification_msg]);
                
                // Audit Trail
                $auditLogger->logEvent(
                    'university_deleted',
                    'system_data',
                    "Deleted university: {$universityData['name']} ({$universityData['code']})",
                    [
                        'user_id' => $_SESSION['admin_id'] ?? null,
                        'user_type' => 'admin',
                        'username' => $_SESSION['admin_username'] ?? 'Unknown',
                        'affected_table' => 'universities',
                        'affected_record_id' => $university_id,
                        'old_values' => [
                            'university_id' => $university_id,
                            'name' => $universityData['name'],
                            'code' => $universityData['code']
                        ],
                        'metadata' => [
                            'action' => 'delete',
                            'admin_role' => $current_admin_role,
                            'reason' => 'No students assigned'
                        ]
                    ]
                );
                
                $success = "University deleted successfully!";
            }
        }
    }
    
    // Delete Barangay
    if (isset($_POST['delete_barangay'])) {
        $barangay_id = intval($_POST['barangay_id']);
        
        // Check if barangay is being used by students
        $checkQuery = "SELECT COUNT(*) as count FROM students WHERE barangay_id = $1";
        $checkResult = pg_query_params($connection, $checkQuery, [$barangay_id]);
        $checkData = pg_fetch_assoc($checkResult);
        
        if ($checkData['count'] > 0) {
            $error = "Cannot delete barangay. It is currently assigned to " . $checkData['count'] . " student(s).";
        } else {
            // Get barangay details for audit before deletion
            $getQuery = "SELECT name FROM barangays WHERE barangay_id = $1";
            $getResult = pg_query_params($connection, $getQuery, [$barangay_id]);
            $barangayData = pg_fetch_assoc($getResult);
            
            $deleteQuery = "DELETE FROM barangays WHERE barangay_id = $1";
            $result = pg_query_params($connection, $deleteQuery, [$barangay_id]);
            
            if ($result) {
                $notification_msg = "Barangay deleted: {$barangayData['name']} (ID: {$barangay_id})";
                pg_query_params($connection, "INSERT INTO admin_notifications (message) VALUES ($1)", [$notification_msg]);
                
                // Audit Trail
                $auditLogger->logEvent(
                    'barangay_deleted',
                    'system_data',
                    "Deleted barangay: {$barangayData['name']}",
                    [
                        'user_id' => $_SESSION['admin_id'] ?? null,
                        'user_type' => 'admin',
                        'username' => $_SESSION['admin_username'] ?? 'Unknown',
                        'affected_table' => 'barangays',
                        'affected_record_id' => $barangay_id,
                        'old_values' => [
                            'barangay_id' => $barangay_id,
                            'name' => $barangayData['name']
                        ],
                        'metadata' => [
                            'action' => 'delete',
                            'admin_role' => $current_admin_role,
                            'reason' => 'No students assigned'
                        ]
                    ]
                );
                
                $success = "Barangay deleted successfully!";
            }
        }
    }
}

// Fetch data
$universitiesQuery = "SELECT u.university_id, u.name, u.code, u.created_at, COUNT(s.student_id) as student_count FROM universities u LEFT JOIN students s ON u.university_id = s.university_id GROUP BY u.university_id, u.name, u.code, u.created_at ORDER BY u.name";
$universitiesResult = pg_query($connection, $universitiesQuery);
$universities = pg_fetch_all($universitiesResult) ?: [];

$barangaysQuery = "SELECT b.barangay_id, b.name, COUNT(s.student_id) as student_count FROM barangays b LEFT JOIN students s ON b.barangay_id = s.barangay_id GROUP BY b.barangay_id, b.name ORDER BY b.name";
$barangaysResult = pg_query($connection, $barangaysQuery);
$barangays = pg_fetch_all($barangaysResult) ?: [];

$yearLevelsQuery = "SELECT * FROM year_levels ORDER BY sort_order";
$yearLevelsResult = pg_query($connection, $yearLevelsQuery);
$yearLevels = pg_fetch_all($yearLevelsResult) ?: [];

// Page title for shared admin header/topbar components
$page_title = 'System Data Management';

// Handle success messages from redirects
if (isset($_GET['success'])) {
    switch ($_GET['success']) {
        case 'university_added':
            $success = "University added successfully!";
            break;
        case 'university_updated':
            $success = "University updated successfully!";
            break;
        case 'university_deleted':
            $success = "University deleted successfully!";
            break;
        case 'barangay_added':
            $success = "Barangay added successfully!";
            break;
        case 'barangay_updated':
            $success = "Barangay updated successfully!";
            break;
        case 'barangay_deleted':
            $success = "Barangay deleted successfully!";
            break;
    }
}

if (isset($_GET['error'])) {
    $error = htmlspecialchars($_GET['error']);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>System Data Management</title>
    <link rel="stylesheet" href="../../assets/css/bootstrap.min.css" />
    <link rel="stylesheet" href="../../assets/css/bootstrap-icons.css" />
    <link rel="stylesheet" href="../../assets/css/admin/homepage.css" />
    <link rel="stylesheet" href="../../assets/css/admin/sidebar.css" />
    <link rel="stylesheet" href="../../assets/css/admin/modern-ui.css" />
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600&display=swap" rel="stylesheet" />
</head>
<body>
<div id="wrapper">
    <?php include __DIR__ . '/../../includes/admin/admin_sidebar.php'; ?>
    <div class="sidebar-backdrop d-none" id="sidebar-backdrop"></div>
    <?php // Topbar / header (adds consistency with other admin pages)
          if (file_exists(__DIR__ . '/../../includes/admin/admin_topbar.php')) {
              include __DIR__ . '/../../includes/admin/admin_topbar.php';
          }
          if (file_exists(__DIR__ . '/../../includes/admin/admin_header.php')) {
              include __DIR__ . '/../../includes/admin/admin_header.php';
          }
    ?>
    <section class="home-section" id="mainContent">
        <!-- Removed duplicate burger menu nav (already provided by topbar/header includes) -->
        
        <div class="container-fluid py-4 px-4">
            <h4 class="fw-bold mb-4"><i class="bi bi-database me-2 text-primary"></i>System Data Management</h4>
            
            <?php if (isset($success)): ?>
                <div class="modern-alert modern-alert-success"><?= htmlspecialchars($success) ?></div>
            <?php endif; ?>
            <?php if (isset($error)): ?>
                <div class="modern-alert modern-alert-danger"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>

            <!-- Universities Management -->
            <div class="modern-card mb-4">
                <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
                    <h5 class="mb-0"><i class="bi bi-building me-2"></i>Universities Management</h5>
                    <span class="badge bg-light text-dark"><?= count($universities) ?> universities</span>
                </div>
                <div class="card-body p-4">
                    <!-- Controls Row -->
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addUniversityModal">
                                <i class="bi bi-plus"></i> Add University
                            </button>
                        </div>
                        <div class="col-md-6">
                            <div class="input-group">
                                <input type="text" class="form-control" id="universitySearch" placeholder="Search universities...">
                                <span class="input-group-text"><i class="bi bi-search"></i></span>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Universities List -->
                    <div class="table-responsive">
                        <table class="table modern-table" id="universitiesTable">
                            <thead>
                                <tr>
                                    <th>University Name</th>
                                    <th>Code</th>
                                    <th>Students</th>
                                    <th>Created</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($universities as $university): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($university['name']) ?></td>
                                        <td><span class="modern-badge modern-badge-info"><?= htmlspecialchars($university['code']) ?></span></td>
                                        <td><?= $university['student_count'] ?> students</td>
                                        <td><?= date('M d, Y', strtotime($university['created_at'])) ?></td>
                                        <td>
                                            <button type="button" class="btn btn-sm btn-outline-primary me-1" onclick="showEditUniversityModal(<?= $university['university_id'] ?>, '<?= htmlspecialchars($university['name'], ENT_QUOTES) ?>', '<?= htmlspecialchars($university['code'], ENT_QUOTES) ?>')">
                                                <i class="bi bi-pencil"></i> Edit
                                            </button>
                                            <?php if ($university['student_count'] == 0): ?>
                                                <button type="button" class="btn btn-sm btn-outline-danger" onclick="showDeleteUniversityModal(<?= $university['university_id'] ?>, '<?= htmlspecialchars($university['name'], ENT_QUOTES) ?>')">
                                                    <i class="bi bi-trash"></i> Delete
                                                </button>
                                            <?php else: ?>
                                                <span class="text-muted small">(<?= $university['student_count'] ?> students)</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    
                    <!-- Pagination Controls -->
                    <div class="d-flex justify-content-between align-items-center mt-3">
                        <div>
                            <select id="universitiesPerPage" class="form-select form-select-sm" style="width: auto;">
                                <option value="10">10 per page</option>
                                <option value="25" selected>25 per page</option>
                                <option value="50">50 per page</option>
                                <option value="100">100 per page</option>
                            </select>
                        </div>
                        <div>
                            <span id="universitiesInfo" class="text-muted"></span>
                        </div>
                        <div>
                            <nav>
                                <ul class="pagination pagination-sm mb-0" id="universitiesPagination"></ul>
                            </nav>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Barangays Management (now inside same container to align with Universities) -->
            <div class="modern-card mb-4">
                <div class="card-header bg-success text-white d-flex justify-content-between align-items-center">
                    <h5 class="mb-0"><i class="bi bi-geo-alt me-2"></i>Barangays Management</h5>
                    <span class="badge bg-light text-dark"><?= count($barangays) ?> barangays</span>
                </div>
                <div class="card-body p-4">
                    <!-- Controls Row -->
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <button type="button" class="btn btn-success" data-bs-toggle="modal" data-bs-target="#addBarangayModal">
                                <i class="bi bi-plus"></i> Add Barangay
                            </button>
                        </div>
                        <div class="col-md-6">
                            <div class="input-group">
                                <input type="text" class="form-control" id="barangaySearch" placeholder="Search barangays...">
                                <span class="input-group-text"><i class="bi bi-search"></i></span>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Barangays List -->
                    <div class="table-responsive">
                        <table class="table modern-table" id="barangaysTable">
                            <thead>
                                <tr>
                                    <th>Barangay Name</th>
                                    <th>Students</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($barangays as $barangay): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($barangay['name']) ?></td>
                                        <td><?= $barangay['student_count'] ?> students</td>
                                        <td>
                                            <button type="button" class="btn btn-sm btn-outline-primary me-1" onclick="showEditBarangayModal(<?= $barangay['barangay_id'] ?>, '<?= htmlspecialchars($barangay['name'], ENT_QUOTES) ?>')">
                                                <i class="bi bi-pencil"></i> Edit
                                            </button>
                                            <?php if ($barangay['student_count'] == 0): ?>
                                                <button type="button" class="btn btn-sm btn-outline-danger" onclick="showDeleteBarangayModal(<?= $barangay['barangay_id'] ?>, '<?= htmlspecialchars($barangay['name'], ENT_QUOTES) ?>')">
                                                    <i class="bi bi-trash"></i> Delete
                                                </button>
                                            <?php else: ?>
                                                <span class="text-muted small">(<?= $barangay['student_count'] ?> students)</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    
                    <!-- Pagination Controls -->
                    <div class="d-flex justify-content-between align-items-center mt-3">
                        <div>
                            <select id="barangaysPerPage" class="form-select form-select-sm" style="width: auto;">
                                <option value="10">10 per page</option>
                                <option value="25" selected>25 per page</option>
                                <option value="50">50 per page</option>
                                <option value="100">100 per page</option>
                            </select>
                        </div>
                        <div>
                            <span id="barangaysInfo" class="text-muted"></span>
                        </div>
                        <div>
                            <nav>
                                <ul class="pagination pagination-sm mb-0" id="barangaysPagination"></ul>
                            </nav>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Year Levels (Read-only) -->
            <div class="card mt-5">
                <div class="modern-card-header">
                    <h5 class="mb-0"><i class="bi bi-layers me-2"></i>Year Levels (System Defined)</h5>
                </div>
                <div class="card-body p-4">
                    <div class="table-responsive">
                        <table class="table modern-table">
                            <thead>
                                <tr>
                                    <th>Year Level</th>
                                    <th>Code</th>
                                    <th>Order</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($yearLevels as $yearLevel): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($yearLevel['name']) ?></td>
                                        <td><span class="badge bg-secondary"><?= htmlspecialchars($yearLevel['code']) ?></span></td>
                                        <td><?= $yearLevel['sort_order'] ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <small class="text-muted">Year levels are system-defined and cannot be modified to maintain data integrity.</small>
                </div>
            </div>
        </div><!-- /.container-fluid -->
    </section>
</div>

<!-- Add University Modal -->
<div class="modal fade" id="addUniversityModal" tabindex="-1" aria-labelledby="addUniversityModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content modern-modal-content">
            <div class="modal-header modern-modal-header">
                <h5 class="modal-title" id="addUniversityModalLabel"><i class="bi bi-building me-2"></i>Add New University</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST" id="addUniversityForm">
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="university_name" class="form-label">University Name <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="university_name" name="university_name" required>
                    </div>
                    <div class="mb-3">
                        <label for="university_code" class="form-label">University Code <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="university_code" name="university_code" placeholder="e.g., UST" maxlength="10" required>
                        <small class="text-muted">Short code/abbreviation for the university</small>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="add_university" class="btn btn-primary">
                        <i class="bi bi-plus"></i> Add University
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit University Modal -->
<div class="modal fade" id="editUniversityModal" tabindex="-1" aria-labelledby="editUniversityModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content modern-modal-content">
            <div class="modal-header modern-modal-header">
                <h5 class="modal-title" id="editUniversityModalLabel"><i class="bi bi-pencil me-2"></i>Edit University</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST" id="editUniversityForm">
                <div class="modal-body">
                    <input type="hidden" id="edit_university_id" name="university_id">
                    <div class="mb-3">
                        <label for="edit_university_name" class="form-label">University Name <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="edit_university_name" name="university_name" required>
                    </div>
                    <div class="mb-3">
                        <label for="edit_university_code" class="form-label">University Code <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="edit_university_code" name="university_code" placeholder="e.g., UST" maxlength="10" required>
                        <small class="text-muted">Short code/abbreviation for the university</small>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="edit_university" class="btn btn-primary">
                        <i class="bi bi-save"></i> Save Changes
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Add Barangay Modal -->
<div class="modal fade" id="addBarangayModal" tabindex="-1" aria-labelledby="addBarangayModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content modern-modal-content">
            <div class="modal-header modern-modal-header">
                <h5 class="modal-title" id="addBarangayModalLabel"><i class="bi bi-geo-alt me-2"></i>Add New Barangay</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST" id="addBarangayForm">
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="barangay_name" class="form-label">Barangay Name <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="barangay_name" name="barangay_name" required>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="add_barangay" class="btn btn-success">
                        <i class="bi bi-plus"></i> Add Barangay
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Barangay Modal -->
<div class="modal fade" id="editBarangayModal" tabindex="-1" aria-labelledby="editBarangayModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content modern-modal-content">
            <div class="modal-header modern-modal-header">
                <h5 class="modal-title" id="editBarangayModalLabel"><i class="bi bi-pencil me-2"></i>Edit Barangay</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST" id="editBarangayForm">
                <div class="modal-body">
                    <input type="hidden" id="edit_barangay_id" name="barangay_id">
                    <div class="mb-3">
                        <label for="edit_barangay_name" class="form-label">Barangay Name <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="edit_barangay_name" name="barangay_name" required>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="edit_barangay" class="btn btn-success">
                        <i class="bi bi-save"></i> Save Changes
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Delete University Modal -->
<div class="modal fade" id="deleteUniversityModal" tabindex="-1" aria-labelledby="deleteUniversityModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content modern-modal-content">
            <div class="modal-header modern-modal-header">
                <h5 class="modal-title" id="deleteUniversityModalLabel"><i class="bi bi-exclamation-triangle me-2 text-danger"></i>Confirm Deletion</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST" id="deleteUniversityForm">
                <div class="modal-body">
                    <div class="modern-alert modern-alert-warning">
                        <i class="bi bi-exclamation-triangle me-2"></i>
                        <strong>Warning:</strong> This action cannot be undone.
                    </div>
                    <p>Are you sure you want to delete the university <strong id="deleteUniversityName"></strong>?</p>
                    <input type="hidden" id="deleteUniversityId" name="university_id">
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="delete_university" class="btn btn-danger">
                        <i class="bi bi-trash"></i> Delete University
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Delete Barangay Modal -->
<div class="modal fade" id="deleteBarangayModal" tabindex="-1" aria-labelledby="deleteBarangayModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content modern-modal-content">
            <div class="modal-header modern-modal-header">
                <h5 class="modal-title" id="deleteBarangayModalLabel"><i class="bi bi-exclamation-triangle me-2 text-danger"></i>Confirm Deletion</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST" id="deleteBarangayForm">
                <div class="modal-body">
                    <div class="modern-alert modern-alert-warning">
                        <i class="bi bi-exclamation-triangle me-2"></i>
                        <strong>Warning:</strong> This action cannot be undone.
                    </div>
                    <p>Are you sure you want to delete the barangay <strong id="deleteBarangayName"></strong>?</p>
                    <input type="hidden" id="deleteBarangayId" name="barangay_id">
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="delete_barangay" class="btn btn-danger">
                        <i class="bi bi-trash"></i> Delete Barangay
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="../../assets/js/admin/sidebar.js"></script>

<script>
// Debug form submission
document.addEventListener('DOMContentLoaded', function() {
    const addUniversityForm = document.getElementById('addUniversityForm');
    
    addUniversityForm.addEventListener('submit', function(e) {
        console.log('Form submitted!');
        console.log('Form data:', {
            name: document.getElementById('university_name').value,
            code: document.getElementById('university_code').value
        });
    });
});

// Pagination and Search functionality
class TableManager {
    constructor(tableId, searchId, perPageId, paginationId, infoId) {
        this.table = document.getElementById(tableId);
        this.searchInput = document.getElementById(searchId);
        this.perPageSelect = document.getElementById(perPageId);
        this.pagination = document.getElementById(paginationId);
        this.info = document.getElementById(infoId);
        
        this.rows = Array.from(this.table.querySelectorAll('tbody tr'));
        this.filteredRows = [...this.rows];
        this.currentPage = 1;
        this.perPage = parseInt(this.perPageSelect.value);
        
        this.init();
    }
    
    init() {
        this.searchInput.addEventListener('input', () => this.handleSearch());
        this.perPageSelect.addEventListener('change', () => this.handlePerPageChange());
        this.update();
    }
    
    handleSearch() {
        const query = this.searchInput.value.toLowerCase();
        this.filteredRows = this.rows.filter(row => {
            return row.textContent.toLowerCase().includes(query);
        });
        this.currentPage = 1;
        this.update();
    }
    
    handlePerPageChange() {
        this.perPage = parseInt(this.perPageSelect.value);
        this.currentPage = 1;
        this.update();
    }
    
    update() {
        this.showRows();
        this.updatePagination();
        this.updateInfo();
    }
    
    showRows() {
        // Hide all rows first
        this.rows.forEach(row => row.style.display = 'none');
        
        // Calculate start and end indices
        const start = (this.currentPage - 1) * this.perPage;
        const end = start + this.perPage;
        
        // Show filtered rows for current page
        this.filteredRows.slice(start, end).forEach(row => {
            row.style.display = '';
        });
    }
    
    updatePagination() {
        const totalPages = Math.ceil(this.filteredRows.length / this.perPage);
        this.pagination.innerHTML = '';
        
        if (totalPages <= 1) return;
        
        // Previous button
        const prevLi = document.createElement('li');
        prevLi.className = 'page-item' + (this.currentPage === 1 ? ' disabled' : '');
        prevLi.innerHTML = '<a class="page-link" href="#" data-page="prev">Previous</a>';
        this.pagination.appendChild(prevLi);
        
        // Page numbers
        const startPage = Math.max(1, this.currentPage - 2);
        const endPage = Math.min(totalPages, this.currentPage + 2);
        
        if (startPage > 1) {
            const firstLi = document.createElement('li');
            firstLi.className = 'page-item';
            firstLi.innerHTML = '<a class="page-link" href="#" data-page="1">1</a>';
            this.pagination.appendChild(firstLi);
            
            if (startPage > 2) {
                const ellipsisLi = document.createElement('li');
                ellipsisLi.className = 'page-item disabled';
                ellipsisLi.innerHTML = '<span class="page-link">...</span>';
                this.pagination.appendChild(ellipsisLi);
            }
        }
        
        for (let i = startPage; i <= endPage; i++) {
            const pageLi = document.createElement('li');
            pageLi.className = 'page-item' + (i === this.currentPage ? ' active' : '');
            pageLi.innerHTML = '<a class="page-link" href="#" data-page="' + i + '">' + i + '</a>';
            this.pagination.appendChild(pageLi);
        }
        
        if (endPage < totalPages) {
            if (endPage < totalPages - 1) {
                const ellipsisLi = document.createElement('li');
                ellipsisLi.className = 'page-item disabled';
                ellipsisLi.innerHTML = '<span class="page-link">...</span>';
                this.pagination.appendChild(ellipsisLi);
            }
            
            const lastLi = document.createElement('li');
            lastLi.className = 'page-item';
            lastLi.innerHTML = '<a class="page-link" href="#" data-page="' + totalPages + '">' + totalPages + '</a>';
            this.pagination.appendChild(lastLi);
        }
        
        // Next button
        const nextLi = document.createElement('li');
        nextLi.className = 'page-item' + (this.currentPage === totalPages ? ' disabled' : '');
        nextLi.innerHTML = '<a class="page-link" href="#" data-page="next">Next</a>';
        this.pagination.appendChild(nextLi);
        
        // Add click handlers
        this.pagination.addEventListener('click', (e) => {
            e.preventDefault();
            if (e.target.classList.contains('page-link')) {
                const page = e.target.getAttribute('data-page');
                this.goToPage(page);
            }
        });
    }
    
    goToPage(page) {
        const totalPages = Math.ceil(this.filteredRows.length / this.perPage);
        
        if (page === 'prev' && this.currentPage > 1) {
            this.currentPage--;
        } else if (page === 'next' && this.currentPage < totalPages) {
            this.currentPage++;
        } else if (!isNaN(page)) {
            this.currentPage = parseInt(page);
        }
        
        this.update();
    }
    
    updateInfo() {
        const start = Math.min((this.currentPage - 1) * this.perPage + 1, this.filteredRows.length);
        const end = Math.min(this.currentPage * this.perPage, this.filteredRows.length);
        const total = this.filteredRows.length;
        
        if (total === 0) {
            this.info.textContent = 'No results found';
        } else {
            this.info.textContent = 'Showing ' + start + '-' + end + ' of ' + total + ' entries';
        }
    }
}

// Initialize table managers
document.addEventListener('DOMContentLoaded', function() {
    new TableManager('universitiesTable', 'universitySearch', 'universitiesPerPage', 'universitiesPagination', 'universitiesInfo');
    new TableManager('barangaysTable', 'barangaySearch', 'barangaysPerPage', 'barangaysPagination', 'barangaysInfo');
});

// Functions for delete modals
function showDeleteUniversityModal(universityId, universityName) {
    document.getElementById('deleteUniversityId').value = universityId;
    document.getElementById('deleteUniversityName').textContent = universityName;
    new bootstrap.Modal(document.getElementById('deleteUniversityModal')).show();
}

function showDeleteBarangayModal(barangayId, barangayName) {
    document.getElementById('deleteBarangayId').value = barangayId;
    document.getElementById('deleteBarangayName').textContent = barangayName;
    new bootstrap.Modal(document.getElementById('deleteBarangayModal')).show();
}

// Functions for edit modals
function showEditUniversityModal(universityId, universityName, universityCode) {
    document.getElementById('edit_university_id').value = universityId;
    document.getElementById('edit_university_name').value = universityName;
    document.getElementById('edit_university_code').value = universityCode;
    new bootstrap.Modal(document.getElementById('editUniversityModal')).show();
}

function showEditBarangayModal(barangayId, barangayName) {
    document.getElementById('edit_barangay_id').value = barangayId;
    document.getElementById('edit_barangay_name').value = barangayName;
    new bootstrap.Modal(document.getElementById('editBarangayModal')).show();
}

// Form validation
document.getElementById('addUniversityForm').addEventListener('submit', function(e) {
    const name = document.getElementById('university_name').value.trim();
    const code = document.getElementById('university_code').value.trim();
    
    if (!name || !code) {
        e.preventDefault();
        alert('Please fill in all required fields.');
        return false;
    }
    
    if (code.length > 10) {
        e.preventDefault();
        alert('University code must be 10 characters or less.');
        return false;
    }
});

document.getElementById('editUniversityForm').addEventListener('submit', function(e) {
    const name = document.getElementById('edit_university_name').value.trim();
    const code = document.getElementById('edit_university_code').value.trim();
    
    if (!name || !code) {
        e.preventDefault();
        alert('Please fill in all required fields.');
        return false;
    }
    
    if (code.length > 10) {
        e.preventDefault();
        alert('University code must be 10 characters or less.');
        return false;
    }
});

document.getElementById('addBarangayForm').addEventListener('submit', function(e) {
    const name = document.getElementById('barangay_name').value.trim();
    
    if (!name) {
        e.preventDefault();
        alert('Please enter a barangay name.');
        return false;
    }
});

document.getElementById('editBarangayForm').addEventListener('submit', function(e) {
    const name = document.getElementById('edit_barangay_name').value.trim();
    
    if (!name) {
        e.preventDefault();
        alert('Please enter a barangay name.');
        return false;
    }
});

// Clear form when modal is closed
document.getElementById('addUniversityModal').addEventListener('hidden.bs.modal', function () {
    document.getElementById('addUniversityForm').reset();
});

document.getElementById('editUniversityModal').addEventListener('hidden.bs.modal', function () {
    document.getElementById('editUniversityForm').reset();
});

document.getElementById('addBarangayModal').addEventListener('hidden.bs.modal', function () {
    document.getElementById('addBarangayForm').reset();
});

document.getElementById('editBarangayModal').addEventListener('hidden.bs.modal', function () {
    document.getElementById('editBarangayForm').reset();
});
</script>
</body>
</html>

<?php pg_close($connection); ?>