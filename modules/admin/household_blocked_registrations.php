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
        .stats-card {
            border-left: 4px solid;
            transition: transform 0.2s;
        }
        .stats-card:hover {
            transform: translateY(-2px);
        }
        .stats-card.danger { border-left-color: #dc3545; }
        .stats-card.warning { border-left-color: #ffc107; }
        .stats-card.success { border-left-color: #28a745; }
        .stats-card.info { border-left-color: #17a2b8; }
        
        .table-hover tbody tr:hover {
            background-color: #f8f9fa;
        }
        .badge-override {
            font-size: 0.75rem;
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
                        <h1 class="mb-1"><i class="bi bi-shield-x me-2"></i>Household Blocked Registrations</h1>
                        <p class="text-muted mb-0">View and manage registration attempts blocked by household duplicate prevention</p>
                    </div>
                    <a href="homepage.php" class="btn btn-outline-secondary">
                        <i class="bi bi-arrow-left me-2"></i>Back to Dashboard
                    </a>
                </div>

                <!-- Statistics Cards -->
                <div class="row g-3 mb-4">
                    <div class="col-md-3">
                        <div class="card stats-card danger">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <p class="text-muted mb-1">Total Blocks</p>
                                        <h3 class="mb-0"><?= $stats['total_blocks'] ?></h3>
                                    </div>
                                    <i class="bi bi-shield-x fs-1 text-danger"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card stats-card warning">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <p class="text-muted mb-1">Active Blocks</p>
                                        <h3 class="mb-0"><?= $stats['active_blocks'] ?></h3>
                                    </div>
                                    <i class="bi bi-exclamation-triangle fs-1 text-warning"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card stats-card success">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <p class="text-muted mb-1">Overridden</p>
                                        <h3 class="mb-0"><?= $stats['overridden'] ?></h3>
                                    </div>
                                    <i class="bi bi-check-circle fs-1 text-success"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card stats-card info">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <p class="text-muted mb-1">Last 30 Days</p>
                                        <h3 class="mb-0"><?= $stats['blocks_last_30d'] ?></h3>
                                    </div>
                                    <i class="bi bi-calendar3 fs-1 text-info"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Filters -->
                <div class="card mb-4">
                    <div class="card-body">
                        <form method="GET" class="row g-3">
                            <div class="col-md-3">
                                <label class="form-label">Barangay</label>
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
                                <label class="form-label">Status</label>
                                <select name="override_status" class="form-select">
                                    <option value="">All</option>
                                    <option value="blocked" <?= $overrideFilter === 'blocked' ? 'selected' : '' ?>>Blocked</option>
                                    <option value="overridden" <?= $overrideFilter === 'overridden' ? 'selected' : '' ?>>Overridden</option>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <label class="form-label">Date From</label>
                                <input type="date" name="date_from" class="form-control" value="<?= htmlspecialchars($dateFrom) ?>">
                            </div>
                            <div class="col-md-2">
                                <label class="form-label">Date To</label>
                                <input type="date" name="date_to" class="form-control" value="<?= htmlspecialchars($dateTo) ?>">
                            </div>
                            <div class="col-md-3 d-flex align-items-end">
                                <button type="submit" class="btn btn-primary me-2">
                                    <i class="bi bi-funnel me-1"></i>Filter
                                </button>
                                <a href="household_blocked_registrations.php" class="btn btn-outline-secondary">
                                    <i class="bi bi-x-circle me-1"></i>Clear
                                </a>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Blocked Attempts Table -->
                <div class="card">
                    <div class="card-header bg-white">
                        <h5 class="mb-0"><i class="bi bi-table me-2"></i>Blocked Registration Attempts</h5>
                    </div>
                    <div class="card-body">
                        <?php if (empty($records)): ?>
                            <div class="alert alert-info">
                                <i class="bi bi-info-circle me-2"></i>No blocked registration attempts found.
                            </div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead class="table-light">
                                        <tr>
                                            <th>Date/Time</th>
                                            <th>Attempted Student</th>
                                            <th>Mother's Maiden Name</th>
                                            <th>Barangay</th>
                                            <th>Blocked By (Existing Student)</th>
                                            <th>Match Type</th>
                                            <th>Status</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($records as $record): ?>
                                            <tr>
                                                <td>
                                                    <small><?= date('M d, Y', strtotime($record['blocked_at'])) ?></small><br>
                                                    <small class="text-muted"><?= date('h:i A', strtotime($record['blocked_at'])) ?></small>
                                                </td>
                                                <td>
                                                    <strong><?= htmlspecialchars($record['attempted_first_name'] . ' ' . $record['attempted_last_name']) ?></strong><br>
                                                    <small class="text-muted"><?= htmlspecialchars($record['attempted_email']) ?></small><br>
                                                    <small class="text-muted"><?= htmlspecialchars($record['attempted_mobile']) ?></small>
                                                </td>
                                                <td><?= htmlspecialchars($record['mothers_maiden_name_entered']) ?></td>
                                                <td><?= htmlspecialchars($record['barangay_entered']) ?></td>
                                                <td>
                                                    <strong><?= htmlspecialchars($record['existing_first_name'] . ' ' . $record['existing_last_name']) ?></strong><br>
                                                    <small class="text-muted"><?= htmlspecialchars($record['existing_student_id']) ?></small>
                                                </td>
                                                <td>
                                                    <?php if ($record['match_type'] === 'exact'): ?>
                                                        <span class="badge bg-danger">Exact Match</span>
                                                    <?php elseif ($record['match_type'] === 'fuzzy'): ?>
                                                        <span class="badge bg-warning text-dark">
                                                            Fuzzy (~<?= round($record['similarity_score'] * 100) ?>%)
                                                        </span>
                                                    <?php else: ?>
                                                        <span class="badge bg-info">User Confirmed</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <?php if ($record['admin_override'] == 't'): ?>
                                                        <span class="badge bg-success badge-override">
                                                            <i class="bi bi-check-circle me-1"></i>Overridden
                                                        </span><br>
                                                        <small class="text-muted"><?= date('M d, Y', strtotime($record['override_at'])) ?></small><br>
                                                        <small class="text-muted">By: <?= htmlspecialchars($record['override_by_name']) ?></small>
                                                        <?php if ($record['bypass_token_used'] == 't'): ?>
                                                            <br><span class="badge bg-secondary mt-1">Token Used</span>
                                                        <?php endif; ?>
                                                    <?php else: ?>
                                                        <span class="badge bg-danger badge-override">
                                                            <i class="bi bi-x-circle me-1"></i>Blocked
                                                        </span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <?php if ($record['admin_override'] != 't'): ?>
                                                        <button class="btn btn-sm btn-warning" 
                                                                onclick="showOverrideModal(<?= $record['attempt_id'] ?>, '<?= htmlspecialchars($record['attempted_first_name'] . ' ' . $record['attempted_last_name'], ENT_QUOTES) ?>')">
                                                            <i class="bi bi-unlock me-1"></i>Override
                                                        </button>
                                                    <?php else: ?>
                                                        <button class="btn btn-sm btn-secondary" disabled>
                                                            <i class="bi bi-check me-1"></i>Resolved
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
