<?php
/**
 * Household Blocked Registrations Log
 * View all household duplicate registration attempts that were blocked
 * Administrators can review, override, and manage blocked attempts
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['admin_username'])) {
    header("Location: ../../unified_login.php");
    exit;
}

include __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/CSRFProtection.php';

// Helper function for JSON response
function json_response($data) {
    header('Content-Type: application/json');
    echo json_encode($data);
    exit;
}

// API endpoint for badge count (for sidebar)
if (isset($_GET['api']) && $_GET['api'] === 'badge_count') {
    $countRes = @pg_query($connection, "SELECT COUNT(*) FROM household_block_attempts WHERE admin_override = FALSE");
    $count = 0;
    if ($countRes) {
        $count = (int) pg_fetch_result($countRes, 0, 0);
        pg_free_result($countRes);
    }
    json_response(['count' => $count]);
}

// Handle POST actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validate CSRF token
    $token = $_POST['csrf_token'] ?? '';
    if (!CSRFProtection::validateToken('household_blocks', $token)) {
        json_response(['success' => false, 'message' => 'Security validation failed']);
    }
    
    // Override and allow registration
    if (isset($_POST['action']) && $_POST['action'] === 'override') {
        $attemptId = intval($_POST['attempt_id'] ?? 0);
        $reason = trim($_POST['reason'] ?? '');
        
        if ($attemptId === 0 || empty($reason)) {
            json_response(['success' => false, 'message' => 'Invalid request']);
        }
        
        // Generate bypass token
        $bypassToken = bin2hex(random_bytes(32));
        $expiresAt = date('Y-m-d H:i:s', strtotime('+24 hours'));
        
        // Update the block attempt
        $updateQuery = "UPDATE household_block_attempts 
                        SET admin_override = TRUE,
                            override_reason = $1,
                            override_by_admin_id = $2,
                            override_at = NOW(),
                            bypass_token = $3,
                            bypass_token_expires_at = $4
                        WHERE attempt_id = $5";
        
        $adminId = $_SESSION['admin_id'] ?? null;
        $result = pg_query_params($connection, $updateQuery, [
            $reason,
            $adminId,
            $bypassToken,
            $expiresAt,
            $attemptId
        ]);
        
        if ($result) {
            // Get attempt details for email
            $detailsQuery = "SELECT attempted_email, attempted_first_name FROM household_block_attempts WHERE attempt_id = $1";
            $detailsResult = pg_query_params($connection, $detailsQuery, [$attemptId]);
            $details = pg_fetch_assoc($detailsResult);
            
            // Generate bypass URL
            $bypassUrl = 'https://' . $_SERVER['HTTP_HOST'] . '/modules/student/student_register.php?bypass_token=' . $bypassToken;
            
            json_response([
                'success' => true,
                'message' => 'Override approved successfully',
                'bypass_url' => $bypassUrl,
                'expires_at' => $expiresAt,
                'email' => $details['attempted_email'] ?? ''
            ]);
        } else {
            json_response(['success' => false, 'message' => 'Database error']);
        }
    }
}

// Fetch blocked attempts with filters
$page = max(1, intval($_GET['page'] ?? 1));
$perPage = 20;
$offset = ($page - 1) * $perPage;

$whereConditions = ['TRUE'];
$params = [];
$paramIndex = 1;

// Filters
$barangayFilter = trim($_GET['barangay'] ?? '');
$overrideFilter = trim($_GET['override_status'] ?? '');
$dateFrom = trim($_GET['date_from'] ?? '');
$dateTo = trim($_GET['date_to'] ?? '');

if (!empty($barangayFilter)) {
    $whereConditions[] = "barangay_entered ILIKE $" . $paramIndex;
    $params[] = "%$barangayFilter%";
    $paramIndex++;
}

if ($overrideFilter === 'overridden') {
    $whereConditions[] = "admin_override = TRUE";
} elseif ($overrideFilter === 'blocked') {
    $whereConditions[] = "admin_override = FALSE";
}

if (!empty($dateFrom)) {
    $whereConditions[] = "blocked_at >= $" . $paramIndex;
    $params[] = $dateFrom . ' 00:00:00';
    $paramIndex++;
}

if (!empty($dateTo)) {
    $whereConditions[] = "blocked_at <= $" . $paramIndex;
    $params[] = $dateTo . ' 23:59:59';
    $paramIndex++;
}

$whereClause = implode(' AND ', $whereConditions);

// Get total count
$countQuery = "SELECT COUNT(*) as total FROM household_block_attempts WHERE $whereClause";
$countResult = pg_query_params($connection, $countQuery, $params);
$totalRecords = pg_fetch_result($countResult, 0, 'total');
$totalPages = ceil($totalRecords / $perPage);

// Get records
$params[] = $perPage;
$params[] = $offset;
$paramCount = count($params);

$query = "SELECT 
    hba.attempt_id,
    hba.attempted_first_name,
    hba.attempted_last_name,
    hba.attempted_email,
    hba.attempted_mobile,
    hba.mothers_maiden_name_entered,
    hba.barangay_entered,
    hba.blocked_at,
    hba.match_type,
    hba.similarity_score,
    hba.admin_override,
    hba.override_reason,
    hba.override_at,
    hba.bypass_token_used,
    s.first_name as existing_first_name,
    s.last_name as existing_last_name,
    s.student_id as existing_student_id,
    CONCAT(a.first_name, ' ', a.last_name) as override_by_name
FROM household_block_attempts hba
LEFT JOIN students s ON hba.blocked_by_student_id = s.student_id
LEFT JOIN admins a ON hba.override_by_admin_id = a.admin_id
WHERE $whereClause
ORDER BY hba.blocked_at DESC
LIMIT $" . ($paramCount - 1) . " OFFSET $" . $paramCount;

$result = pg_query_params($connection, $query, $params);
$records = [];
while ($row = pg_fetch_assoc($result)) {
    $records[] = $row;
}

// Get statistics
$statsQuery = "SELECT 
    COUNT(*) as total_blocks,
    COUNT(CASE WHEN admin_override = TRUE THEN 1 END) as overridden,
    COUNT(CASE WHEN admin_override = FALSE THEN 1 END) as active_blocks,
    COUNT(CASE WHEN blocked_at >= CURRENT_DATE - INTERVAL '7 days' THEN 1 END) as blocks_last_7d,
    COUNT(CASE WHEN blocked_at >= CURRENT_DATE - INTERVAL '30 days' THEN 1 END) as blocks_last_30d
FROM household_block_attempts";
$statsResult = pg_query($connection, $statsQuery);
$stats = pg_fetch_assoc($statsResult);

// Get barangays for filter
$barangaysQuery = "SELECT DISTINCT barangay_entered FROM household_block_attempts ORDER BY barangay_entered";
$barangaysResult = pg_query($connection, $barangaysQuery);
$barangays = [];
while ($row = pg_fetch_assoc($barangaysResult)) {
    $barangays[] = $row['barangay_entered'];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Household Blocked Registrations</title>
    <link rel="stylesheet" href="../../assets/css/bootstrap.min.css" />
    <link rel="stylesheet" href="../../assets/css/bootstrap-icons.css" />
    <link rel="stylesheet" href="../../assets/css/admin/sidebar.css" />
    <link rel="stylesheet" href="../../assets/css/admin/homepage.css" />
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        /* Modern Enhanced UI Styling */
        body {
            background: linear-gradient(135deg, #f5f7fa 0%, #e8ecf1 100%);
            font-family: 'Poppins', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
        }
        
        /* Statistics Cards Enhancement */
        .stats-card {
            border: none;
            border-radius: 12px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.07), 0 1px 3px rgba(0,0,0,0.06);
            transition: all 0.3s ease;
            overflow: hidden;
            position: relative;
        }
        
        .stats-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
        }
        
        .stats-card.danger::before { background: linear-gradient(90deg, #dc3545 0%, #c82333 100%); }
        .stats-card.warning::before { background: linear-gradient(90deg, #ffc107 0%, #e0a800 100%); }
        .stats-card.success::before { background: linear-gradient(90deg, #28a745 0%, #218838 100%); }
        .stats-card.info::before { background: linear-gradient(90deg, #17a2b8 0%, #138496 100%); }
        
        .stats-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 15px rgba(0,0,0,0.1), 0 3px 6px rgba(0,0,0,0.08);
        }
        
        .stats-card .card-body {
            padding: 1.5rem;
        }
        
        .stats-card h3 {
            font-weight: 700;
            font-size: 2rem;
            margin-bottom: 0;
            background: linear-gradient(135deg, #1e293b 0%, #475569 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }
        
        .stats-card .stat-icon {
            width: 60px;
            height: 60px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.8rem;
            transition: all 0.3s ease;
        }
        
        .stats-card.danger .stat-icon {
            background: linear-gradient(135deg, #fee2e2 0%, #fecaca 100%);
            color: #dc3545;
        }
        
        .stats-card.warning .stat-icon {
            background: linear-gradient(135deg, #fef3c7 0%, #fde68a 100%);
            color: #ffc107;
        }
        
        .stats-card.success .stat-icon {
            background: linear-gradient(135deg, #d1f4dd 0%, #a7f3d0 100%);
            color: #28a745;
        }
        
        .stats-card.info .stat-icon {
            background: linear-gradient(135deg, #dbeafe 0%, #bfdbfe 100%);
            color: #17a2b8;
        }
        
        .stats-card:hover .stat-icon {
            transform: scale(1.1) rotate(5deg);
        }
        
        /* Card Enhancements */
        .card {
            border: none;
            border-radius: 12px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.07), 0 1px 3px rgba(0,0,0,0.06);
            transition: all 0.3s ease;
            background: white;
        }
        
        .card:hover {
            box-shadow: 0 8px 15px rgba(0,0,0,0.1), 0 3px 6px rgba(0,0,0,0.08);
        }
        
        .card-header {
            background: linear-gradient(135deg, #f8fafc 0%, #e2e8f0 100%);
            border-bottom: 2px solid #e2e8f0;
            border-radius: 12px 12px 0 0 !important;
            padding: 1.25rem 1.5rem;
        }
        
        .card-header h5 {
            color: #1e293b;
            font-weight: 700;
            margin: 0;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .card-header h5 i {
            color: #2e7d32;
            font-size: 1.3rem;
        }
        
        .card-body {
            padding: 1.5rem;
        }
        
        /* Form Controls Enhancement */
        .form-label {
            font-weight: 600;
            color: #334155;
            margin-bottom: 0.5rem;
            font-size: 0.95rem;
        }
        
        .form-control, .form-select {
            border: 2px solid #e2e8f0;
            border-radius: 8px;
            padding: 0.65rem 1rem;
            transition: all 0.2s ease;
        }
        
        .form-control:focus, .form-select:focus {
            border-color: #2e7d32;
            box-shadow: 0 0 0 0.25rem rgba(46, 125, 50, 0.15);
            background-color: #f8fffe;
        }
        
        .form-control:hover, .form-select:hover {
            border-color: #cbd5e1;
        }
        
        /* Button Enhancements */
        .btn {
            border-radius: 8px;
            padding: 0.65rem 1.5rem;
            font-weight: 600;
            transition: all 0.3s ease;
            border: none;
        }
        
        .btn-primary {
            background: linear-gradient(135deg, #2e7d32 0%, #1b5e20 100%);
            box-shadow: 0 4px 10px rgba(46, 125, 50, 0.3);
        }
        
        .btn-primary:hover {
            background: linear-gradient(135deg, #1b5e20 0%, #2e7d32 100%);
            box-shadow: 0 6px 15px rgba(46, 125, 50, 0.4);
            transform: translateY(-2px);
        }
        
        .btn-warning {
            background: linear-gradient(135deg, #ffc107 0%, #e0a800 100%);
            box-shadow: 0 4px 10px rgba(255, 193, 7, 0.3);
            color: #000;
        }
        
        .btn-warning:hover {
            background: linear-gradient(135deg, #e0a800 0%, #ffc107 100%);
            box-shadow: 0 6px 15px rgba(255, 193, 7, 0.4);
            transform: translateY(-2px);
            color: #000;
        }
        
        .btn-outline-secondary {
            border: 2px solid #cbd5e1;
            color: #475569;
            background: white;
        }
        
        .btn-outline-secondary:hover {
            background: #f8fafc;
            border-color: #94a3b8;
            color: #334155;
            transform: translateX(-3px);
        }
        
        .btn-sm {
            padding: 0.45rem 1rem;
            font-size: 0.875rem;
        }
        
        /* Table Enhancements */
        .table-responsive {
            border-radius: 8px;
            overflow: hidden;
        }
        
        .table {
            margin-bottom: 0;
        }
        
        .table thead {
            background: linear-gradient(135deg, #f8fafc 0%, #e2e8f0 100%);
        }
        
        .table thead th {
            font-weight: 700;
            color: #1e293b;
            border-bottom: 2px solid #cbd5e1;
            padding: 1rem;
            font-size: 0.875rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .table tbody tr {
            transition: all 0.2s ease;
        }
        
        .table-hover tbody tr:hover {
            background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%);
            transform: scale(1.01);
            box-shadow: 0 2px 8px rgba(0,0,0,0.05);
        }
        
        .table tbody td {
            padding: 1rem;
            vertical-align: middle;
            border-bottom: 1px solid #f1f5f9;
        }
        
        /* Badge Enhancements */
        .badge {
            padding: 0.5rem 0.85rem;
            font-weight: 600;
            border-radius: 6px;
            font-size: 0.75rem;
            display: inline-flex;
            align-items: center;
            gap: 0.3rem;
        }
        
        .badge-override {
            font-size: 0.75rem;
        }
        
        .badge.bg-danger {
            background: linear-gradient(135deg, #dc3545 0%, #c82333 100%) !important;
            box-shadow: 0 2px 4px rgba(220, 53, 69, 0.3);
        }
        
        .badge.bg-warning {
            background: linear-gradient(135deg, #ffc107 0%, #e0a800 100%) !important;
            color: #000 !important;
            box-shadow: 0 2px 4px rgba(255, 193, 7, 0.3);
        }
        
        .badge.bg-success {
            background: linear-gradient(135deg, #28a745 0%, #218838 100%) !important;
            box-shadow: 0 2px 4px rgba(40, 167, 69, 0.3);
        }
        
        .badge.bg-info {
            background: linear-gradient(135deg, #17a2b8 0%, #138496 100%) !important;
            box-shadow: 0 2px 4px rgba(23, 162, 184, 0.3);
        }
        
        .badge.bg-secondary {
            background: linear-gradient(135deg, #6c757d 0%, #5a6268 100%) !important;
            box-shadow: 0 2px 4px rgba(108, 117, 125, 0.3);
        }
        
        /* Alert Enhancements */
        .alert {
            border-radius: 10px;
            border: none;
            padding: 1rem 1.25rem;
            box-shadow: 0 4px 10px rgba(0,0,0,0.08);
            animation: slideInRight 0.4s ease;
        }
        
        @keyframes slideInRight {
            from {
                opacity: 0;
                transform: translateX(20px);
            }
            to {
                opacity: 1;
                transform: translateX(0);
            }
        }
        
        .alert-info {
            background: linear-gradient(135deg, #dbeafe 0%, #bfdbfe 100%);
            color: #1e40af;
        }
        
        /* Page Header Enhancement */
        h1 {
            color: #1e293b;
            font-weight: 700;
            font-size: 2rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        h1 i {
            color: #dc3545;
            font-size: 1.8rem;
        }
        
        .text-muted {
            color: #64748b !important;
            font-size: 1.05rem;
        }
        
        /* Pagination Enhancement */
        .pagination {
            gap: 0.5rem;
        }
        
        .page-item .page-link {
            border-radius: 8px;
            border: 2px solid #e2e8f0;
            color: #475569;
            padding: 0.5rem 1rem;
            font-weight: 600;
            transition: all 0.2s ease;
        }
        
        .page-item .page-link:hover {
            background: #f8fafc;
            border-color: #2e7d32;
            color: #2e7d32;
            transform: translateY(-2px);
            box-shadow: 0 2px 6px rgba(0,0,0,0.1);
        }
        
        .page-item.active .page-link {
            background: linear-gradient(135deg, #2e7d32 0%, #1b5e20 100%);
            border-color: #2e7d32;
            box-shadow: 0 4px 10px rgba(46, 125, 50, 0.3);
        }
        
        /* Container Enhancement */
        .container-fluid {
            max-width: 1400px;
            margin: 0 auto;
        }
        
        /* SweetAlert2 Custom Styling */
        .swal2-popup {
            border-radius: 12px !important;
            box-shadow: 0 10px 40px rgba(0,0,0,0.15) !important;
        }
        
        .swal2-title {
            color: #1e293b !important;
            font-weight: 700 !important;
        }
        
        .swal2-confirm {
            background: linear-gradient(135deg, #ffc107 0%, #e0a800 100%) !important;
            box-shadow: 0 4px 10px rgba(255, 193, 7, 0.3) !important;
            border-radius: 8px !important;
            padding: 0.65rem 1.5rem !important;
        }
        
        .swal2-cancel {
            background: #6c757d !important;
            border-radius: 8px !important;
            padding: 0.65rem 1.5rem !important;
        }
        
        /* Responsive */
        @media (max-width: 991.98px) {
            .stats-card h3 {
                font-size: 1.5rem;
            }
            
            h1 {
                font-size: 1.5rem;
            }
        }
        
        /* Smooth Scroll */
        html {
            scroll-behavior: smooth;
        }
    </style>
</head>
<body>
    <?php include __DIR__ . '/../../includes/admin/admin_topbar.php'; ?>
    
    <div id="wrapper" class="admin-wrapper">
        <?php include __DIR__ . '/../../includes/admin/admin_sidebar.php'; ?>
        <?php include __DIR__ . '/../../includes/admin/admin_header.php'; ?>
        
        <section class="home-section" id="mainContent">
            <div class="container-fluid py-4">
                <!-- Page Header -->
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <div>
                        <h1 class="mb-2">
                            <i class="bi bi-shield-x"></i>
                            Household Blocked Registrations
                        </h1>
                        <p class="text-muted mb-0">View and manage registration attempts blocked by household duplicate prevention</p>
                    </div>
                    <a href="homepage.php" class="btn btn-outline-secondary d-flex align-items-center gap-2">
                        <i class="bi bi-arrow-left"></i>
                        <span>Back to Dashboard</span>
                    </a>
                </div>

                <!-- Statistics Cards -->
                <div class="row g-4 mb-4">
                    <div class="col-md-3">
                        <div class="card stats-card danger">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <p class="text-muted mb-1 fw-semibold">Total Blocks</p>
                                        <h3><?= $stats['total_blocks'] ?></h3>
                                    </div>
                                    <div class="stat-icon">
                                        <i class="bi bi-shield-x"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card stats-card warning">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <p class="text-muted mb-1 fw-semibold">Active Blocks</p>
                                        <h3><?= $stats['active_blocks'] ?></h3>
                                    </div>
                                    <div class="stat-icon">
                                        <i class="bi bi-exclamation-triangle"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card stats-card success">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <p class="text-muted mb-1 fw-semibold">Overridden</p>
                                        <h3><?= $stats['overridden'] ?></h3>
                                    </div>
                                    <div class="stat-icon">
                                        <i class="bi bi-check-circle"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card stats-card info">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <p class="text-muted mb-1 fw-semibold">Last 30 Days</p>
                                        <h3><?= $stats['blocks_last_30d'] ?></h3>
                                    </div>
                                    <div class="stat-icon">
                                        <i class="bi bi-calendar3"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Filters -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h5>
                            <i class="bi bi-funnel-fill"></i>
                            Filter Options
                        </h5>
                    </div>
                    <div class="card-body">
                        <form method="GET" class="row g-3">
                            <div class="col-md-3">
                                <label class="form-label"><i class="bi bi-geo-alt me-1"></i>Barangay</label>
                                <select name="barangay" class="form-select">
                                    <option value="">All Barangays</option>
                                    <?php foreach ($barangays as $brgy): ?>
                                        <option value="<?= htmlspecialchars($brgy) ?>" <?= $barangayFilter === $brgy ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($brgy) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <label class="form-label"><i class="bi bi-toggle2-on me-1"></i>Status</label>
                                <select name="override_status" class="form-select">
                                    <option value="">All</option>
                                    <option value="blocked" <?= $overrideFilter === 'blocked' ? 'selected' : '' ?>>Blocked</option>
                                    <option value="overridden" <?= $overrideFilter === 'overridden' ? 'selected' : '' ?>>Overridden</option>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <label class="form-label"><i class="bi bi-calendar-date me-1"></i>Date From</label>
                                <input type="date" name="date_from" class="form-control" value="<?= htmlspecialchars($dateFrom) ?>">
                            </div>
                            <div class="col-md-2">
                                <label class="form-label"><i class="bi bi-calendar-check me-1"></i>Date To</label>
                                <input type="date" name="date_to" class="form-control" value="<?= htmlspecialchars($dateTo) ?>">
                            </div>
                            <div class="col-md-3 d-flex align-items-end gap-2">
                                <button type="submit" class="btn btn-primary flex-grow-1">
                                    <i class="bi bi-funnel me-1"></i>Apply Filters
                                </button>
                                <a href="household_blocked_registrations.php" class="btn btn-outline-secondary">
                                    <i class="bi bi-x-circle"></i>
                                </a>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Blocked Attempts Table -->
                <div class="card">
                    <div class="card-header">
                        <h5>
                            <i class="bi bi-table"></i>
                            Blocked Registration Attempts
                            <span class="badge bg-danger ms-2"><?= count($records) ?> Records</span>
                        </h5>
                    </div>
                    <div class="card-body">
                        <?php if (empty($records)): ?>
                            <div class="alert alert-info">
                                <i class="bi bi-info-circle me-2"></i>No blocked registration attempts found matching your criteria.
                            </div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead>
                                        <tr>
                                            <th><i class="bi bi-clock me-1"></i>Date/Time</th>
                                            <th><i class="bi bi-person me-1"></i>Attempted Student</th>
                                            <th><i class="bi bi-person-heart me-1"></i>Mother's Maiden Name</th>
                                            <th><i class="bi bi-geo-alt me-1"></i>Barangay</th>
                                            <th><i class="bi bi-shield-check me-1"></i>Blocked By (Existing)</th>
                                            <th><i class="bi bi-diagram-3 me-1"></i>Match Type</th>
                                            <th><i class="bi bi-toggle2-on me-1"></i>Status</th>
                                            <th><i class="bi bi-tools me-1"></i>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($records as $record): ?>
                                            <tr>
                                                <td>
                                                    <div class="fw-semibold"><?= date('M d, Y', strtotime($record['blocked_at'])) ?></div>
                                                    <small class="text-muted"><?= date('h:i A', strtotime($record['blocked_at'])) ?></small>
                                                </td>
                                                <td>
                                                    <div class="fw-bold"><?= htmlspecialchars($record['attempted_first_name'] . ' ' . $record['attempted_last_name']) ?></div>
                                                    <small class="text-muted d-block"><i class="bi bi-envelope me-1"></i><?= htmlspecialchars($record['attempted_email']) ?></small>
                                                    <small class="text-muted"><i class="bi bi-telephone me-1"></i><?= htmlspecialchars($record['attempted_mobile']) ?></small>
                                                </td>
                                                <td class="fw-semibold"><?= htmlspecialchars($record['mothers_maiden_name_entered']) ?></td>
                                                <td>
                                                    <span class="badge bg-secondary" style="font-size: 0.85rem;">
                                                        <?= htmlspecialchars($record['barangay_entered']) ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <div class="fw-bold text-primary"><?= htmlspecialchars($record['existing_first_name'] . ' ' . $record['existing_last_name']) ?></div>
                                                    <small class="text-muted"><i class="bi bi-hash me-1"></i><?= htmlspecialchars($record['existing_student_id']) ?></small>
                                                </td>
                                                <td>
                                                    <?php if ($record['match_type'] === 'exact'): ?>
                                                        <span class="badge bg-danger">
                                                            <i class="bi bi-exclamation-diamond-fill me-1"></i>Exact Match
                                                        </span>
                                                    <?php elseif ($record['match_type'] === 'fuzzy'): ?>
                                                        <span class="badge bg-warning text-dark">
                                                            <i class="bi bi-graph-up me-1"></i>Fuzzy (~<?= round($record['similarity_score'] * 100) ?>%)
                                                        </span>
                                                    <?php else: ?>
                                                        <span class="badge bg-info">
                                                            <i class="bi bi-check2-square me-1"></i>User Confirmed
                                                        </span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <?php if ($record['admin_override'] == 't'): ?>
                                                        <span class="badge bg-success badge-override">
                                                            <i class="bi bi-check-circle-fill me-1"></i>Overridden
                                                        </span>
                                                        <div class="mt-1"><small class="text-muted"><?= date('M d, Y', strtotime($record['override_at'])) ?></small></div>
                                                        <div><small class="text-muted"><i class="bi bi-person me-1"></i><?= htmlspecialchars($record['override_by_name']) ?></small></div>
                                                        <?php if ($record['bypass_token_used'] == 't'): ?>
                                                            <span class="badge bg-secondary mt-1">
                                                                <i class="bi bi-key-fill me-1"></i>Token Used
                                                            </span>
                                                        <?php endif; ?>
                                                    <?php else: ?>
                                                        <span class="badge bg-danger badge-override">
                                                            <i class="bi bi-x-circle-fill me-1"></i>Blocked
                                                        </span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <?php if ($record['admin_override'] != 't'): ?>
                                                        <button class="btn btn-sm btn-warning" 
                                                                onclick="showOverrideModal(<?= $record['attempt_id'] ?>, '<?= htmlspecialchars($record['attempted_first_name'] . ' ' . $record['attempted_last_name'], ENT_QUOTES) ?>')">
                                                            <i class="bi bi-unlock-fill me-1"></i>Override
                                                        </button>
                                                    <?php else: ?>
                                                        <button class="btn btn-sm btn-secondary" disabled>
                                                            <i class="bi bi-check-lg me-1"></i>Resolved
                                                        </button>
                                                    <?php endif; ?>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>

                            <!-- Pagination -->
                            <?php if ($totalPages > 1): ?>
                                <nav class="mt-4">
                                    <ul class="pagination justify-content-center">
                                        <?php if ($page > 1): ?>
                                            <li class="page-item">
                                                <a class="page-link" href="?page=<?= $page - 1 ?>&<?= http_build_query(array_filter($_GET, fn($k) => $k !== 'page', ARRAY_FILTER_USE_KEY)) ?>">
                                                    Previous
                                                </a>
                                            </li>
                                        <?php endif; ?>
                                        
                                        <?php for ($i = max(1, $page - 2); $i <= min($totalPages, $page + 2); $i++): ?>
                                            <li class="page-item <?= $i === $page ? 'active' : '' ?>">
                                                <a class="page-link" href="?page=<?= $i ?>&<?= http_build_query(array_filter($_GET, fn($k) => $k !== 'page', ARRAY_FILTER_USE_KEY)) ?>">
                                                    <?= $i ?>
                                                </a>
                                            </li>
                                        <?php endfor; ?>
                                        
                                        <?php if ($page < $totalPages): ?>
                                            <li class="page-item">
                                                <a class="page-link" href="?page=<?= $page + 1 ?>&<?= http_build_query(array_filter($_GET, fn($k) => $k !== 'page', ARRAY_FILTER_USE_KEY)) ?>">
                                                    Next
                                                </a>
                                            </li>
                                        <?php endif; ?>
                                    </ul>
                                </nav>
                            <?php endif; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </section>
    </div>

    <script src="../../assets/js/bootstrap.bundle.min.js"></script>
    <script>
        const csrfToken = '<?= CSRFProtection::generateToken('household_blocks') ?>';

        function showOverrideModal(attemptId, studentName) {
            Swal.fire({
                title: 'Override Household Block',
                html: `
                    <div class="text-start">
                        <p>You are about to allow <strong>${studentName}</strong> to register despite household duplicate detection.</p>
                        <p class="text-muted">Please provide a reason for this override:</p>
                        <textarea id="overrideReason" class="form-control" rows="4" 
                                  placeholder="e.g., Verified different household, Data entry error, etc."></textarea>
                    </div>
                `,
                icon: 'warning',
                showCancelButton: true,
                confirmButtonText: 'Approve Override',
                confirmButtonColor: '#ffc107',
                cancelButtonText: 'Cancel',
                preConfirm: () => {
                    const reason = document.getElementById('overrideReason').value.trim();
                    if (!reason) {
                        Swal.showValidationMessage('Please provide a reason for the override');
                        return false;
                    }
                    return reason;
                }
            }).then((result) => {
                if (result.isConfirmed) {
                    processOverride(attemptId, result.value);
                }
            });
        }

        async function processOverride(attemptId, reason) {
            try {
                const formData = new FormData();
                formData.append('action', 'override');
                formData.append('attempt_id', attemptId);
                formData.append('reason', reason);
                formData.append('csrf_token', csrfToken);

                const response = await fetch('household_blocked_registrations.php', {
                    method: 'POST',
                    body: formData
                });

                const data = await response.json();

                if (data.success) {
                    await Swal.fire({
                        icon: 'success',
                        title: 'Override Approved',
                        html: `
                            <div class="text-start">
                                <p>The household block has been overridden. A one-time registration bypass link has been generated:</p>
                                <div class="alert alert-info">
                                    <small><strong>Bypass URL:</strong></small><br>
                                    <input type="text" class="form-control form-control-sm mt-1" value="${data.bypass_url}" readonly 
                                           onclick="this.select()">
                                </div>
                                <p><small class="text-muted">
                                    <i class="bi bi-clock me-1"></i>Expires: ${new Date(data.expires_at).toLocaleString()}<br>
                                    <i class="bi bi-envelope me-1"></i>Send to: ${data.email}
                                </small></p>
                                <p class="text-warning"><small>
                                    <i class="bi bi-exclamation-triangle me-1"></i>
                                    This link can only be used once and expires in 24 hours.
                                </small></p>
                            </div>
                        `,
                        confirmButtonText: 'OK'
                    });
                    location.reload();
                } else {
                    Swal.fire({
                        icon: 'error',
                        title: 'Error',
                        text: data.message || 'Failed to process override'
                    });
                }
            } catch (error) {
                Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: 'An error occurred while processing the override'
                });
            }
        }
    </script>
</body>
</html>
