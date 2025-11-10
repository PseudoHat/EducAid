<?php
require_once __DIR__ . '/../../includes/CSRFProtection.php';
require_once __DIR__ . '/../../services/StudentArchivalService.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (!isset($_SESSION['admin_username'])) {
    header("Location: ../../unified_login.php");
    exit;
}
include __DIR__ . '/../../config/database.php';

// Initialize services
$archivalService = new StudentArchivalService($connection);

// --- Inline API handlers ---
if (isset($_GET['api'])) {
    header('Content-Type: application/json; charset=utf-8');
    if (!isset($_SESSION['admin_username'])) {
        http_response_code(403);
        echo json_encode(['error' => 'Access denied']);
        exit;
    }

    $api = $_GET['api'];

    // Helper: check table exists
    $tableExists = function($table) use ($connection) {
        $chk = @pg_query($connection, "SELECT to_regclass('public." . pg_escape_string($table) . "') AS reg");
        if (!$chk) return false;
        $reg = pg_fetch_result($chk, 0, 'reg');
        pg_free_result($chk);
        return !empty($reg);
    };

    if ($api === 'schools') {
        if (!$tableExists('schools')) { echo json_encode([]); exit; }
        $res = @pg_query($connection, "SELECT school_id, name FROM schools ORDER BY name LIMIT 1000");
        $rows = $res ? pg_fetch_all($res) : [];
        echo json_encode($rows ?: []);
        exit;
    }

    if ($api === 'municipalities') {
        if (!$tableExists('municipalities')) { echo json_encode([]); exit; }
        $res = @pg_query($connection, "SELECT municipality_id, name FROM municipalities ORDER BY name LIMIT 1000");
        $rows = $res ? pg_fetch_all($res) : [];
        echo json_encode($rows ?: []);
        exit;
    }

    if ($api === 'barangays') {
        if (!$tableExists('barangays')) { echo json_encode([]); exit; }
        $res = @pg_query($connection, "SELECT barangay_id, name FROM barangays ORDER BY name LIMIT 1000");
        $rows = $res ? pg_fetch_all($res) : [];
        echo json_encode($rows ?: []);
        exit;
    }

    if ($api === 'badge_count') {
        // Return the number of surname groups with more than one member (unresolved households)
        if (!$tableExists('students')) { echo json_encode(['count' => 0]); exit; }
        // Count surname groups where household_verified is FALSE or NULL
        $countRes = @pg_query($connection, "
            SELECT COUNT(*) AS c 
            FROM (
                SELECT LOWER(last_name) 
                FROM students 
                WHERE (household_verified IS NULL OR household_verified = FALSE)
                  AND is_archived = FALSE
                GROUP BY LOWER(last_name) 
                HAVING COUNT(*) > 1
            ) t
        ");
        $count = 0;
        if ($countRes) {
            $count = (int) pg_fetch_result($countRes, 0, 'c');
            pg_free_result($countRes);
        }
        echo json_encode(['count' => $count]);
        exit;
    }

    if ($api === 'rows') {
        // Collect and sanitize filters
        $surname = trim((string)($_GET['surname'] ?? ''));
        $school = trim((string)($_GET['school'] ?? ''));
        $municipality = trim((string)($_GET['municipality'] ?? ''));
        $barangay = trim((string)($_GET['barangay'] ?? ''));
        $status = trim((string)($_GET['status'] ?? ''));
        $household_status = trim((string)($_GET['household_status'] ?? 'unresolved')); // New filter
        $date_from = trim((string)($_GET['date_from'] ?? ''));
        $date_to = trim((string)($_GET['date_to'] ?? ''));
        $page = max(1, (int)($_GET['page'] ?? 1));
        $per_page = (int)($_GET['per_page'] ?? 20);
        if ($per_page <= 0) $per_page = 20;
        if ($per_page > 500) $per_page = 500;
        $offset = ($page - 1) * $per_page;

        // detect auxiliary tables
        $has_schools = $tableExists('schools');
        $has_municipalities = $tableExists('municipalities');
        $has_barangays = $tableExists('barangays');
        $has_year_levels = $tableExists('year_levels');

        $selectSchool = $has_schools ? "COALESCE(s.name,'') AS school" : "'' AS school";
        $selectMunicipality = $has_municipalities ? "COALESCE(m.name,'') AS municipality" : "'' AS municipality";
        $selectBarangay = $has_barangays ? "COALESCE(b.name,'') AS barangay" : "'' AS barangay";
        $selectYearLevel = $has_year_levels ? "COALESCE(yl.name,'') AS year_level" : "'' AS year_level";
        $joinSchool = $has_schools ? " LEFT JOIN schools s ON s.school_id = f.school_id" : "";
        $joinMunicipality = $has_municipalities ? " LEFT JOIN municipalities m ON m.municipality_id = f.municipality_id" : "";
        $joinBarangay = $has_barangays ? " LEFT JOIN barangays b ON b.barangay_id = f.barangay_id" : "";
        $joinYearLevel = $has_year_levels ? " LEFT JOIN year_levels yl ON yl.year_level_id = f.year_level_id" : "";

        // Build WHERE clause with parameterized values
        $where = ["is_archived = FALSE"]; // Only show non-archived students (no alias in CTE)
        $params = [];

        // Household status filter
        if ($household_status === 'unresolved') {
            $where[] = "(household_verified IS NULL OR household_verified = FALSE)";
        } elseif ($household_status === 'resolved') {
            $where[] = "household_verified = TRUE";
        }

        if ($surname !== '') {
            $params[] = '%' . mb_strtolower($surname) . '%';
            $where[] = "LOWER(last_name) LIKE $" . count($params);
        }
        if ($school !== '') {
            $params[] = $school;
            $where[] = "school_id = $" . count($params);
        }
        if ($municipality !== '') {
            $params[] = $municipality;
            $where[] = "municipality_id = $" . count($params);
        }
        if ($barangay !== '') {
            $params[] = $barangay;
            $where[] = "barangay_id = $" . count($params);
        }
        if ($status !== '') {
            $params[] = $status;
            $where[] = "status = $" . count($params);
        }
        if ($date_from !== '') {
            $params[] = $date_from;
            $where[] = "application_date >= $" . count($params);
        }
        if ($date_to !== '') {
            $params[] = $date_to;
            $where[] = "application_date <= $" . count($params);
        }

        $whereSql = 'WHERE ' . implode(' AND ', $where);

        // Count total matching duplicate surnames
        $countSql = "
            WITH filtered AS (
                SELECT * FROM students $whereSql
            ), 
            surname_counts AS (
                SELECT LOWER(last_name) AS ln 
                FROM filtered 
                GROUP BY LOWER(last_name) 
                HAVING COUNT(*) > 1
            ) 
            SELECT COUNT(*) AS total 
            FROM filtered f 
            JOIN surname_counts sc ON LOWER(f.last_name) = sc.ln
        ";
        $countRes = @pg_query_params($connection, $countSql, $params);
        $total = 0;
        if ($countRes) {
            $total = (int)pg_fetch_result($countRes, 0, 'total');
            pg_free_result($countRes);
        }

        // Fetch paginated rows with household information
        $params_for_rows = $params;
        $params_for_rows[] = $per_page;
        $params_for_rows[] = $offset;
        $limitPlaceholder = '$' . (count($params_for_rows) - 1);
        $offsetPlaceholder = '$' . (count($params_for_rows));

        $rowsSql = "
            WITH filtered AS (
                SELECT * FROM students $whereSql
            ), 
            surname_counts AS (
                SELECT LOWER(last_name) AS ln 
                FROM filtered 
                GROUP BY LOWER(last_name) 
                HAVING COUNT(*) > 1
            ) 
            SELECT 
                f.student_id, 
                f.first_name, 
                f.last_name, 
                $selectSchool, 
                $selectMunicipality, 
                $selectBarangay, 
                f.mobile, 
                f.email,
                f.household_verified,
                f.household_primary,
                f.household_group_id,
                $selectYearLevel,
                f.status
            FROM filtered f 
            JOIN surname_counts sc ON LOWER(f.last_name) = sc.ln 
            $joinSchool 
            $joinMunicipality 
            $joinBarangay 
            $joinYearLevel
            ORDER BY LOWER(f.last_name) ASC, f.household_primary DESC NULLS LAST, LOWER(f.first_name) ASC 
            LIMIT $limitPlaceholder OFFSET $offsetPlaceholder
        ";

        $rowsRes = @pg_query_params($connection, $rowsSql, $params_for_rows);
        $rows = $rowsRes ? pg_fetch_all($rowsRes) : [];

        // CSV export
        if (isset($_GET['csv']) && $_GET['csv']) {
            header('Content-Type: text/csv; charset=utf-8');
            header('Content-Disposition: attachment; filename="household_duplicates.csv"');
            $out = fopen('php://output', 'w');
            fputcsv($out, ['First Name','Surname','School','Municipality','Barangay','Phone','Email','Year Level','Status','Household Status','Primary']);
            if ($rows) {
                foreach ($rows as $r) {
                    $householdStatus = ($r['household_verified'] ?? 'f') === 't' ? 'Resolved' : 'Unresolved';
                    $isPrimary = ($r['household_primary'] ?? 'f') === 't' ? 'Yes' : 'No';
                    fputcsv($out, [
                        $r['first_name'] ?? '', 
                        $r['last_name'] ?? '', 
                        $r['school'] ?? '', 
                        $r['municipality'] ?? '', 
                        $r['barangay'] ?? '', 
                        $r['mobile'] ?? '', 
                        $r['email'] ?? '',
                        $r['year_level'] ?? '',
                        $r['status'] ?? '',
                        $householdStatus,
                        $isPrimary
                    ]);
                }
            }
            exit;
        }

        // Return JSON
        echo json_encode([
            'total' => $total,
            'page' => $page,
            'per_page' => $per_page,
            'rows' => $rows ?: [],
        ]);
        exit;
    }

    // Mark as primary recipient
    if ($api === 'mark_primary') {
        CSRFProtection::validateToken('household_mark_primary', $_POST['csrf_token'] ?? '');
        
        $student_id = trim((string)($_POST['student_id'] ?? ''));
        $last_name = trim((string)($_POST['last_name'] ?? ''));
        
        if (!$student_id || !$last_name) {
            echo json_encode(['success' => false, 'error' => 'Missing student ID or surname']);
            exit;
        }

        try {
            pg_query($connection, "BEGIN");

            // Get all students with this surname
            $surnameStudents = pg_query_params(
                $connection,
                "SELECT student_id FROM students WHERE LOWER(last_name) = LOWER($1) AND is_archived = FALSE",
                [$last_name]
            );

            if (!$surnameStudents) {
                throw new Exception('Failed to fetch surname group');
            }

            // Clear all primary flags for this surname group
            pg_query_params(
                $connection,
                "UPDATE students SET household_primary = FALSE WHERE LOWER(last_name) = LOWER($1) AND is_archived = FALSE",
                [$last_name]
            );

            // Set this student as primary
            $result = pg_query_params(
                $connection,
                "UPDATE students SET household_primary = TRUE WHERE student_id = $1",
                [$student_id]
            );

            if (!$result) {
                throw new Exception('Failed to mark as primary');
            }

            pg_query($connection, "COMMIT");
            echo json_encode(['success' => true, 'message' => 'Primary recipient marked successfully']);
        } catch (Exception $e) {
            pg_query($connection, "ROLLBACK");
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }

    // Archive household duplicates
    if ($api === 'archive_duplicates') {
        CSRFProtection::validateToken('household_archive_duplicates', $_POST['csrf_token'] ?? '');
        
        $primary_id = trim((string)($_POST['primary_id'] ?? ''));
        $duplicate_ids = $_POST['duplicate_ids'] ?? [];
        $admin_id = $_SESSION['admin_id'] ?? null;

        if (!$primary_id || empty($duplicate_ids) || !$admin_id) {
            echo json_encode(['success' => false, 'error' => 'Missing required parameters']);
            exit;
        }

        try {
            $archived_count = 0;
            $household_group_id = null;

            foreach ($duplicate_ids as $duplicate_id) {
                $duplicate_id = trim($duplicate_id);
                if ($duplicate_id === $primary_id) {
                    continue; // Skip the primary recipient
                }

                $result = $archivalService->archiveHouseholdDuplicate(
                    $duplicate_id,
                    $primary_id,
                    $admin_id,
                    $household_group_id
                );

                if ($result['success']) {
                    if (!$household_group_id) {
                        $household_group_id = $result['household_group_id'] ?? null;
                    }
                    $archived_count++;
                }
            }

            // Mark the surname group as verified
            if ($archived_count > 0) {
                pg_query_params(
                    $connection,
                    "UPDATE students SET household_verified = TRUE WHERE student_id = $1",
                    [$primary_id]
                );
            }

            echo json_encode([
                'success' => true, 
                'message' => "Successfully archived {$archived_count} duplicate(s)",
                'archived_count' => $archived_count
            ]);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }

    // Get household members
    if ($api === 'household_members') {
        $student_id = trim((string)($_GET['student_id'] ?? ''));
        
        if (!$student_id) {
            echo json_encode(['success' => false, 'error' => 'Missing student ID']);
            exit;
        }

        try {
            $members = $archivalService->getHouseholdMembers($student_id);
            echo json_encode(['success' => true, 'members' => $members]);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }

    // Mark as different household
    if ($api === 'mark_different_household') {
        CSRFProtection::validateToken('household_different', $_POST['csrf_token'] ?? '');
        
        $student_ids = $_POST['student_ids'] ?? [];
        $last_name = trim((string)($_POST['last_name'] ?? ''));
        
        if (empty($student_ids) || !$last_name) {
            echo json_encode(['success' => false, 'error' => 'Missing required parameters']);
            exit;
        }

        try {
            pg_query($connection, "BEGIN");

            // Clear household flags and mark as verified for selected students
            foreach ($student_ids as $student_id) {
                $student_id = trim($student_id);
                
                // Clear household flags and mark as verified (different household)
                $result = pg_query_params(
                    $connection,
                    "UPDATE students 
                     SET household_verified = TRUE,
                         household_primary = FALSE,
                         household_group_id = NULL
                     WHERE student_id = $1 AND LOWER(last_name) = LOWER($2)",
                    [$student_id, $last_name]
                );

                if (!$result) {
                    throw new Exception('Failed to update student: ' . $student_id);
                }
            }

            pg_query($connection, "COMMIT");
            echo json_encode([
                'success' => true, 
                'message' => count($student_ids) . ' student(s) marked as different household(s)'
            ]);
        } catch (Exception $e) {
            pg_query($connection, "ROLLBACK");
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }

    // Unmark as different household (restore to unresolved)
    if ($api === 'unmark_different_household') {
        CSRFProtection::validateToken('household_unmark', $_POST['csrf_token'] ?? '');
        
        $student_id = trim((string)($_POST['student_id'] ?? ''));
        $last_name = trim((string)($_POST['last_name'] ?? ''));
        
        if (!$student_id || !$last_name) {
            echo json_encode(['success' => false, 'error' => 'Missing required parameters']);
            exit;
        }

        try {
            pg_query($connection, "BEGIN");

            // Restore to unresolved state
            $result = pg_query_params(
                $connection,
                "UPDATE students 
                 SET household_verified = FALSE,
                     household_primary = FALSE,
                     household_group_id = NULL
                 WHERE student_id = $1 AND LOWER(last_name) = LOWER($2)",
                [$student_id, $last_name]
            );

            if (!$result) {
                throw new Exception('Failed to restore student to unresolved');
            }

            pg_query($connection, "COMMIT");
            echo json_encode([
                'success' => true, 
                'message' => 'Student restored to unresolved household group'
            ]);
        } catch (Exception $e) {
            pg_query($connection, "ROLLBACK");
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }

    http_response_code(404);
    echo json_encode(['error' => 'Unknown API endpoint']);
    exit;
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Household Duplicates - Admin</title>
    <link href="../../assets/css/bootstrap.min.css" rel="stylesheet">
    <link href="../../assets/css/bootstrap-icons.css" rel="stylesheet">
    <link href="../../assets/css/admin/homepage.css" rel="stylesheet">
    <link href="../../assets/css/admin/sidebar.css" rel="stylesheet">
    <script src="../../assets/js/admin/sidebar.js" defer></script>
    <script src="../../assets/js/admin/notification_bell.js" defer></script>
    <style>
        .table-fixed { width:100%; table-layout:fixed }
        .table-fixed td { overflow:hidden; text-overflow:ellipsis; white-space:nowrap }
        .table-container table, .table-container th, .table-container td { font-size: 0.92rem; }
        .table-container { overflow-x:auto; -webkit-overflow-scrolling:touch; }
        .table-container table { min-width:1200px; }

        @media (max-width: 1400px) {
            .table-container table { min-width:1100px; }
        }
        @media (max-width: 1200px) {
            .table-container table { min-width:900px; }
        }
        @media (max-width: 992px) {
            .table-container table { min-width:700px; }
            .table-fixed { table-layout:auto; }
            .table-fixed td { white-space:normal; }
        }
        @media (max-width: 576px) {
            .table-container table { min-width:560px; }
        }

        .table-container::after {
            content: '';
            display: block;
            height: 6px;
            margin-top: -6px;
            pointer-events: none;
            background: linear-gradient(90deg, rgba(0,0,0,0.06), rgba(0,0,0,0));
        }

        @media (min-width: 768px) {
            .table-container table { min-width: max-content; }
            #resultsTable th:nth-child(8),
            #resultsTable td:nth-child(8) {
                min-width: 420px;
                white-space: nowrap;
            }
        }

        #resultsTable td { position: relative; }
        .copied-badge { 
            position: absolute; 
            right: 8px; 
            top: 50%; 
            transform: translateY(-50%); 
            background: rgba(0,0,0,0.85); 
            color: #fff; 
            padding: 2px 6px; 
            border-radius: 4px; 
            font-size: 12px; 
            opacity: 0; 
            transition: opacity .16s ease; 
            pointer-events: none; 
        }
        .copied-badge.show { opacity: 1; }

        /* Filter section styling */
        .filter-section {
            background: white;
            border: 1px solid #dee2e6;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 0 10px rgba(0,0,0,0.1);
        }

        /* Household grouping styles - CLEAN */
        .surname-group {
            border-left: 4px solid #0d6efd;
            background: #f8f9fa;
        }
        .surname-group-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            font-weight: 600;
            border: none;
        }
        .surname-group-header td {
            padding: 0.75rem !important;
        }
        .primary-recipient {
            background: #d1e7dd !important;
            border-left: 4px solid #198754;
        }
        .unresolved-household {
            background: #fff3cd !important;
            border-left: 4px solid #ffc107;
        }
        .resolved-household {
            background: #d1ecf1 !important;
            border-left: 4px solid #0dcaf0;
        }
        
        .action-buttons {
            display: flex;
            gap: 0.35rem;
            flex-wrap: wrap;
            align-items: center;
        }
        .btn-xs {
            padding: 0.25rem 0.5rem;
            font-size: 0.75rem;
            line-height: 1.2;
        }

        /* Status badges */
        .badge-household-verified {
            background: #198754;
        }
        .badge-household-unverified {
            background: #ffc107;
            color: #000;
        }
        .badge-primary {
            background: #0d6efd;
        }
        
        /* Table styling - Match review_registrations */
        .table-responsive {
            box-shadow: 0 0 10px rgba(0,0,0,0.1);
            border-radius: 8px;
            overflow: hidden;
        }
        #resultsTable thead th {
            background: #495057;
            color: white;
            border: none;
            font-weight: 600;
        }
        #resultsTable tbody tr:hover:not(.surname-group-header) {
            background-color: #f8f9fa;
        }
        
        /* Quick actions section */
        .quick-actions {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 20px;
            border-radius: 12px;
            margin-bottom: 20px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }
        .quick-actions h5 {
            color: white;
            margin-bottom: 5px;
        }
        .quick-actions small {
            color: rgba(255, 255, 255, 0.8);
        }
        
        /* Responsive adjustments */
        @media (max-width: 991.98px) {
            .container-fluid {
                margin-left: 0 !important;
            }
        }
    </style>
</head>
<body>
    <?php include_once __DIR__ . '/../../includes/admin/admin_topbar.php'; ?>
    <div id="wrapper" class="admin-wrapper">
        <?php include_once __DIR__ . '/../../includes/admin/admin_sidebar.php'; ?>
        <?php include_once __DIR__ . '/../../includes/admin/admin_header.php'; ?>
        
        <section class="home-section" id="mainContent">
            <div class="container-fluid py-4 px-4">
                <!-- Page Header - Match review_registrations style -->
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <div>
                        <h1 class="fw-bold mb-1">Household Duplicates Management</h1>
                        <p class="text-muted mb-0">Identify and resolve household duplicate registrations to ensure accurate distribution.</p>
                    </div>
                    <div class="text-end">
                        <span class="badge bg-warning fs-6" id="totalBadge">Loading...</span>
                    </div>
                </div>

                <!-- Help Button -->
                <div class="mb-3">
                    <button class="btn btn-outline-info btn-sm" type="button" data-bs-toggle="collapse" data-bs-target="#helpInstructions" aria-expanded="false" aria-controls="helpInstructions">
                        <i class="bi bi-question-circle me-1"></i> Show Instructions
                    </button>
                </div>

                <!-- Collapsible Instructions -->
                <div class="collapse" id="helpInstructions">
                    <div class="alert alert-info mb-3">
                        <h6 class="alert-heading">
                            <i class="bi bi-lightbulb me-2"></i>How to Resolve Household Duplicates
                        </h6>
                        <ol class="mb-0 small">
                            <li><strong>Review:</strong> Students with the same surname who may be siblings/family members</li>
                            <li><strong>Mark Primary:</strong> Select one student as the primary recipient (usually the oldest or first to register)</li>
                            <li><strong>Archive Duplicates:</strong> Archive the remaining family members to prevent duplicate distributions</li>
                            <li><strong>Verification:</strong> Once resolved, the group will be marked as "Verified" ✓</li>
                        </ol>
                    </div>
                </div>

                <!-- Filters Section -->
                <div class="filter-section">
                    <form id="filterForm" class="row g-3">
                        <div class="col-md-3">
                            <label class="form-label">Surname</label>
                            <input name="surname" id="surname" class="form-control" placeholder="Search by surname...">
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">School</label>
                            <select id="school" name="school" class="form-select">
                                <option value="">All Schools</option>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">Municipality</label>
                            <select id="municipality" name="municipality" class="form-select">
                                <option value="">All Municipalities</option>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">Barangay</label>
                            <select id="barangay" name="barangay" class="form-select">
                                <option value="">All Barangays</option>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Household Status</label>
                            <select id="household_status" name="household_status" class="form-select">
                                <option value="">All Statuses</option>
                                <option value="unresolved" selected>Unresolved Only</option>
                                <option value="resolved">Resolved Only</option>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">Student Status</label>
                            <select id="status" name="status" class="form-select">
                                <option value="">All</option>
                                <option value="applicant">Applicant</option>
                                <option value="pending">Pending</option>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">Date From</label>
                            <input type="date" id="date_from" name="date_from" class="form-control">
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">Date To</label>
                            <input type="date" id="date_to" name="date_to" class="form-control">
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">&nbsp;</label>
                            <div class="d-flex gap-2">
                                <button id="applyBtn" type="button" class="btn btn-primary">Filter</button>
                                <button id="exportCsvBtn" type="button" class="btn btn-outline-secondary" title="Export to CSV">
                                    <i class="bi bi-download"></i>
                                </button>
                            </div>
                        </div>
                    </form>
                </div>

                <!-- Quick Stats Section -->
                <div class="quick-actions">
                    <div class="d-flex align-items-center justify-content-between">
                        <div>
                            <h5 class="mb-1"><i class="bi bi-bar-chart me-2"></i>Household Statistics</h5>
                            <small id="quickStatsText">Review and resolve duplicate household registrations</small>
                        </div>
                        <div id="summary" class="d-flex gap-2 flex-wrap"></div>
                    </div>
                </div>

                <!-- Per Page Selection -->
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <div class="text-muted" id="paginationInfo">
                        Showing results
                    </div>
                    <div>
                        <label class="me-2">Rows per page:</label>
                        <select id="per_page" class="form-select form-select-sm d-inline-block" style="width: auto;">
                            <option>20</option>
                            <option>50</option>
                            <option>100</option>
                        </select>
                    </div>
                </div>

                <!-- Results Table -->
                <div class="table-responsive">
                <table class="table table-striped table-hover table-fixed" id="resultsTable">
                    <thead class="table-dark">
                        <tr>
                            <th style="width: 40px;">
                                <input type="checkbox" id="selectAll" class="form-check-input" title="Select all for bulk actions">
                            </th>
                            <th>First Name</th>
                            <th>Surname</th>
                            <th>School</th>
                            <th>Municipality</th>
                            <th>Barangay</th>
                            <th>Year Level</th>
                            <th>Phone</th>
                            <th>Email</th>
                            <th>Status</th>
                            <th>Household</th>
                            <th style="min-width: 200px;">Actions</th>
                        </tr>
                    </thead>
                    <tbody></tbody>
                </table>
                </div>
            </div>

            <!-- Pagination -->
            <div class="d-flex justify-content-center mt-4">
                <nav>
                    <ul class="pagination" id="pagination"></ul>
                </nav>
            </div>
            </div>
        </section>
    </div>

    <!-- Confirm Archive Modal -->
    <div class="modal fade" id="confirmArchiveModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Confirm Archive Duplicates</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p>You are about to archive <strong id="archiveCount">0</strong> duplicate student(s).</p>
                    <p>Primary recipient: <strong id="primaryName"></strong></p>
                    <p class="text-danger">This action will:</p>
                    <ul>
                        <li>Archive the selected duplicate students</li>
                        <li>Link them to the primary recipient's household group</li>
                        <li>Compress their documents to the archived folder</li>
                        <li>Mark the household as verified</li>
                    </ul>
                    <p>Are you sure you want to continue?</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-danger" id="confirmArchiveBtn">Archive Duplicates</button>
                </div>
            </div>
        </div>
    </div>

    <!-- View Household Group Modal -->
    <div class="modal fade" id="viewGroupModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="bi bi-people-fill me-2"></i>Household Members
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body" id="householdMembersContent">
                    <div class="text-center py-4">
                        <div class="spinner-border text-primary" role="status">
                            <span class="visually-hidden">Loading...</span>
                        </div>
                        <p class="mt-2">Loading household members...</p>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    <script src="../../assets/js/bootstrap.bundle.min.js"></script>
    <script>
        const endpoint = 'household_duplicates.php';
        const csrfToken = '<?php echo CSRFProtection::generateToken('household_actions'); ?>';
        const csrfTokenUnmark = '<?php echo CSRFProtection::generateToken('household_unmark'); ?>';
        let currentData = null;

        // Load filter options
        async function loadSelects(){
            const [schools, municipalities, barangays] = await Promise.all([
                fetch(endpoint + '?api=schools').then(r=>r.json()).catch(()=>[]),
                fetch(endpoint + '?api=municipalities').then(r=>r.json()).catch(()=>[]),
                fetch(endpoint + '?api=barangays').then(r=>r.json()).catch(()=>[])
            ]);

            const schoolEl = document.getElementById('school');
            if (Array.isArray(schools)) schools.forEach(s=>{
                const o=document.createElement('option'); 
                o.value=s.school_id; 
                o.textContent=s.name; 
                schoolEl.appendChild(o);
            });

            const muniEl = document.getElementById('municipality');
            if (Array.isArray(municipalities)) municipalities.forEach(m=>{
                const o=document.createElement('option'); 
                o.value=m.municipality_id; 
                o.textContent=m.name; 
                muniEl.appendChild(o);
            });

            const barangayEl = document.getElementById('barangay');
            if (Array.isArray(barangays)) barangays.forEach(b=>{
                const o=document.createElement('option'); 
                o.value=b.barangay_id; 
                o.textContent=b.name; 
                barangayEl.appendChild(o);
            });
        }

        function readFilters(){
            const f = new FormData(document.getElementById('filterForm'));
            return Object.fromEntries(f.entries());
        }

        async function fetchData(page=1){
            const perPage = document.getElementById('per_page').value || 20;
            const filters = readFilters();
            filters.page = page; 
            filters.per_page = perPage;
            const params = new URLSearchParams(filters);
            const res = await fetch(endpoint + '?api=rows&' + params.toString());
            const text = await res.text();
            let data;
            try {
                data = JSON.parse(text);
            } catch (err) {
                console.error('Invalid JSON response:', text);
                document.getElementById('summary').innerHTML = '<span class="badge bg-danger">Server error (check console)</span>';
                return;
            }
            currentData = data;
            renderTable(data);
        }

        function renderTable(data){
            const tbody = document.querySelector('#resultsTable tbody');
            tbody.innerHTML='';
            
            const total = data.total || 0;
            const surnameGroups = groupBySurname(data.rows || []);
            const groupCount = Object.keys(surnameGroups).length;
            
            // Count unresolved groups
            const unresolvedCount = Object.values(surnameGroups).filter(students => 
                !students.every(s => (s.household_verified ?? 'f') === 't')
            ).length;
            
            const resolvedCount = groupCount - unresolvedCount;
            
            // Update top badge
            document.getElementById('totalBadge').textContent = `${unresolvedCount} Unresolved`;
            
            // Update summary badges
            document.getElementById('summary').innerHTML = `
                <span class="badge bg-primary px-3 py-2">
                    ${total} Students
                </span>
                <span class="badge bg-info text-dark px-3 py-2">
                    ${groupCount} Groups
                </span>
                <span class="badge bg-warning text-dark px-3 py-2">
                    ${unresolvedCount} Unresolved
                </span>
                <span class="badge bg-success px-3 py-2">
                    ${resolvedCount} Resolved
                </span>
            `;
            
            // Update quick stats text
            document.getElementById('quickStatsText').textContent = 
                `${unresolvedCount} household groups require review • ${resolvedCount} already resolved`;
            
            // Update pagination info
            const page = data.page || 1;
            const perPage = data.per_page || 20;
            const start = total > 0 ? ((page - 1) * perPage) + 1 : 0;
            const end = Math.min(page * perPage, total);
            document.getElementById('paginationInfo').textContent = 
                `Showing ${start}-${end} of ${total} students in ${groupCount} groups`;

            // Render grouped by surname
            for (const [surname, students] of Object.entries(surnameGroups)) {
                // Check if this group has a primary recipient
                const hasPrimary = students.some(s => (s.household_primary ?? 'f') === 't');
                const isVerified = students.every(s => (s.household_verified ?? 'f') === 't');

                // Group header row
                const headerRow = document.createElement('tr');
                headerRow.className = 'surname-group-header';
                headerRow.innerHTML = `
                    <td colspan="12">
                        <div class="d-flex justify-content-between align-items-center">
                            <span>
                                <i class="bi bi-people-fill me-2"></i>
                                <strong>${escapeHtml(surname)}</strong> 
                                <span class="badge bg-secondary ms-2">${students.length} members</span>
                                ${isVerified ? '<span class="badge badge-household-verified ms-2"><i class="bi bi-check-circle me-1"></i>Verified</span>' : '<span class="badge badge-household-unverified ms-2"><i class="bi bi-exclamation-triangle me-1"></i>Unresolved</span>'}
                            </span>
                            ${!isVerified && students.length > 1 ? `
                                <button class="btn btn-sm btn-success resolve-group-btn" data-surname="${escapeHtml(surname)}">
                                    <i class="bi bi-check-circle me-1"></i>Resolve Group
                                </button>
                            ` : ''}
                        </div>
                    </td>
                `;
                tbody.appendChild(headerRow);

                // Render each student in the group
                students.forEach(s => {
                    const tr = document.createElement('tr');
                    const isPrimary = (s.household_primary ?? 'f') === 't';
                    const isVerified = (s.household_verified ?? 'f') === 't';
                    
                    if (isPrimary) {
                        tr.className = 'primary-recipient';
                    } else if (!isVerified) {
                        tr.className = 'unresolved-household';
                    }

                    tr.innerHTML = `
                        <td>
                            <input type="checkbox" class="form-check-input student-checkbox" 
                                   data-student-id="${escapeHtml(s.student_id)}" 
                                   data-surname="${escapeHtml(s.last_name)}"
                                   ${isPrimary ? 'disabled title="Primary recipient cannot be selected"' : ''}>
                        </td>
                        <td>${escapeHtml(s.first_name)}</td>
                        <td>${escapeHtml(s.last_name)}</td>
                        <td>${escapeHtml(s.school)}</td>
                        <td>${escapeHtml(s.municipality)}</td>
                        <td>${escapeHtml(s.barangay)}</td>
                        <td>${escapeHtml(s.year_level || '')}</td>
                        <td>${escapeHtml(s.mobile)}</td>
                        <td data-email="${escapeHtml(s.email)}" title="${escapeHtml(s.email)}" class="email-cell">${escapeHtml(s.email)}</td>
                        <td><span class="badge bg-secondary">${escapeHtml(s.status)}</span></td>
                        <td>
                            ${isPrimary ? '<span class="badge badge-primary"><i class="bi bi-star-fill me-1"></i>Primary</span>' : ''}
                            ${isVerified ? '<span class="badge badge-household-verified">Verified</span>' : ''}
                        </td>
                        <td>
                            <div class="action-buttons">
                                ${!isPrimary ? `
                                    <button class="btn btn-xs btn-primary mark-primary-btn" 
                                            data-student-id="${escapeHtml(s.student_id)}"
                                            data-name="${escapeHtml(s.first_name + ' ' + s.last_name)}"
                                            data-surname="${escapeHtml(s.last_name)}"
                                            title="Mark as primary recipient">
                                        <i class="bi bi-star"></i> Mark Primary
                                    </button>
                                ` : ''}
                                ${!isVerified ? `
                                    <button class="btn btn-xs btn-warning mark-different-btn"
                                            data-student-id="${escapeHtml(s.student_id)}"
                                            data-name="${escapeHtml(s.first_name + ' ' + s.last_name)}"
                                            data-surname="${escapeHtml(s.last_name)}"
                                            title="Mark as different household (not related)">
                                        <i class="bi bi-x-circle"></i> Different Household
                                    </button>
                                ` : ''}
                                ${isVerified && !isPrimary ? `
                                    <button class="btn btn-xs btn-success restore-to-group-btn"
                                            data-student-id="${escapeHtml(s.student_id)}"
                                            data-name="${escapeHtml(s.first_name + ' ' + s.last_name)}"
                                            data-surname="${escapeHtml(s.last_name)}"
                                            title="Restore to unresolved household group">
                                        <i class="bi bi-arrow-counterclockwise"></i> Restore to Group
                                    </button>
                                ` : ''}
                                ${!isVerified ? `
                                    <button class="btn btn-xs btn-info view-group-btn"
                                            data-student-id="${escapeHtml(s.student_id)}"
                                            title="View all household members">
                                        <i class="bi bi-eye"></i> View Group
                                    </button>
                                ` : ''}
                            </div>
                        </td>
                    `;
                    tbody.appendChild(tr);

                    // Email copy-on-click
                    const emailCell = tr.querySelector('.email-cell');
                    if (emailCell) {
                        emailCell.addEventListener('click', async (e) => {
                            const val = emailCell.getAttribute('data-email') || emailCell.textContent;
                            try {
                                await navigator.clipboard.writeText(val);
                            } catch (err) {
                                const ta = document.createElement('textarea');
                                ta.value = val;
                                document.body.appendChild(ta);
                                ta.select();
                                document.execCommand('copy');
                                document.body.removeChild(ta);
                            }

                            let badge = emailCell.querySelector('.copied-badge');
                            if (!badge) {
                                badge = document.createElement('span');
                                badge.className = 'copied-badge';
                                badge.textContent = 'Copied';
                                emailCell.appendChild(badge);
                            }
                            badge.classList.add('show');
                            setTimeout(()=> badge.classList.remove('show'), 1200);
                        });
                    }
                });
            }

            // Attach event listeners
            attachEventListeners();

            // Pagination
            renderPagination(data);
        }

        function groupBySurname(rows) {
            const groups = {};
            rows.forEach(r => {
                const surname = (r.last_name || '').toLowerCase();
                if (!groups[surname]) {
                    groups[surname] = [];
                }
                groups[surname].push(r);
            });
            return groups;
        }

        function renderPagination(data) {
            const pag = document.getElementById('pagination');
            pag.innerHTML='';
            const pages = Math.max(1, Math.ceil((data.total||0)/(data.per_page||20)));
            for(let i=1; i<=pages; i++){
                const li=document.createElement('li');
                li.className='page-item'+(i===data.page?' active':'');
                li.innerHTML=`<a class="page-link" href="#">${i}</a>`;
                li.onclick=(e)=>{e.preventDefault(); fetchData(i)};
                pag.appendChild(li);
            }
        }

        function attachEventListeners() {
            // Mark Primary buttons
            document.querySelectorAll('.mark-primary-btn').forEach(btn => {
                btn.addEventListener('click', async function() {
                    const studentId = this.dataset.studentId;
                    const name = this.dataset.name;
                    const surname = this.dataset.surname;

                    if (!confirm(`Mark "${name}" as the primary recipient for the ${surname} household?`)) {
                        return;
                    }

                    const formData = new FormData();
                    formData.append('csrf_token', csrfToken);
                    formData.append('student_id', studentId);
                    formData.append('last_name', surname);

                    try {
                        const res = await fetch(endpoint + '?api=mark_primary', {
                            method: 'POST',
                            body: formData
                        });
                        const result = await res.json();
                        
                        if (result.success) {
                            showAlert('success', result.message || 'Primary recipient marked successfully');
                            fetchData(currentData.page);
                        } else {
                            showAlert('danger', result.error || 'Failed to mark as primary');
                        }
                    } catch (err) {
                        showAlert('danger', 'Network error: ' + err.message);
                    }
                });
            });

            // Resolve Group buttons
            document.querySelectorAll('.resolve-group-btn').forEach(btn => {
                btn.addEventListener('click', function() {
                    const surname = this.dataset.surname;
                    resolveGroup(surname);
                });
            });

            // View Group buttons
            document.querySelectorAll('.view-group-btn').forEach(btn => {
                btn.addEventListener('click', async function() {
                    const studentId = this.dataset.studentId;
                    await viewHouseholdGroup(studentId);
                });
            });

            // Mark as Different Household buttons
            document.querySelectorAll('.mark-different-btn').forEach(btn => {
                btn.addEventListener('click', async function() {
                    const studentId = this.dataset.studentId;
                    const name = this.dataset.name;
                    const surname = this.dataset.surname;

                    if (!confirm(`Mark "${name}" as a DIFFERENT household?\n\nThis means they are NOT related to the other students with surname "${surname}".\n\nThis will:\n- Mark them as verified (resolved)\n- Remove them from this duplicate group\n- They will NOT be archived`)) {
                        return;
                    }

                    const formData = new FormData();
                    formData.append('csrf_token', csrfToken);
                    formData.append('student_ids[]', studentId);
                    formData.append('last_name', surname);

                    try {
                        const res = await fetch(endpoint + '?api=mark_different_household', {
                            method: 'POST',
                            body: formData
                        });
                        const result = await res.json();
                        
                        if (result.success) {
                            showAlert('success', result.message || 'Student marked as different household');
                            fetchData(currentData.page);
                        } else {
                            showAlert('danger', result.error || 'Failed to mark as different household');
                        }
                    } catch (err) {
                        showAlert('danger', 'Network error: ' + err.message);
                    }
                });
            });

            // Restore to Group buttons
            document.querySelectorAll('.restore-to-group-btn').forEach(btn => {
                btn.addEventListener('click', async function() {
                    const studentId = this.dataset.studentId;
                    const name = this.dataset.name;
                    const surname = this.dataset.surname;

                    if (!confirm(`Restore "${name}" back to the household group?\n\nThis will mark them as UNRESOLVED again and link them back with other students with surname "${surname}".\n\nYou'll be able to:\n- Mark them as primary recipient\n- Archive them as household duplicates`)) {
                        return;
                    }

                    const formData = new FormData();
                    formData.append('csrf_token', csrfTokenUnmark);
                    formData.append('student_id', studentId);
                    formData.append('last_name', surname);

                    try {
                        const res = await fetch(endpoint + '?api=unmark_different_household', {
                            method: 'POST',
                            body: formData
                        });
                        const result = await res.json();
                        
                        if (result.success) {
                            showAlert('success', result.message || 'Student restored to household group');
                            fetchData(currentData.page);
                        } else {
                            showAlert('danger', result.error || 'Failed to restore student');
                        }
                    } catch (err) {
                        showAlert('danger', 'Network error: ' + err.message);
                    }
                });
            });
        }

        async function viewHouseholdGroup(studentId) {
            // Show modal
            const modal = new bootstrap.Modal(document.getElementById('viewGroupModal'));
            const content = document.getElementById('householdMembersContent');
            
            // Reset content to loading state
            content.innerHTML = `
                <div class="text-center py-4">
                    <div class="spinner-border text-primary" role="status">
                        <span class="visually-hidden">Loading...</span>
                    </div>
                    <p class="mt-2">Loading household members...</p>
                </div>
            `;
            
            modal.show();

            try {
                // Fetch household members
                const res = await fetch(endpoint + '?api=household_members&student_id=' + encodeURIComponent(studentId));
                const result = await res.json();

                if (!result.success) {
                    throw new Error(result.error || 'Failed to fetch household members');
                }

                const members = result.members || [];

                if (members.length === 0) {
                    content.innerHTML = `
                        <div class="alert alert-info">
                            <i class="bi bi-info-circle me-2"></i>
                            No household members found. This student hasn't been linked to a household group yet.
                        </div>
                    `;
                    return;
                }

                // Find the current student
                const currentStudent = members.find(m => m.student_id === studentId);
                const currentSurname = currentStudent ? currentStudent.last_name : '';

                // Render members table
                let html = `
                    <div class="mb-3">
                        <h6 class="text-muted">
                            <i class="bi bi-people me-1"></i>
                            ${escapeHtml(currentSurname)} Household Group
                            <span class="badge bg-secondary ms-2">${members.length} member${members.length !== 1 ? 's' : ''}</span>
                        </h6>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-sm table-hover">
                            <thead class="table-light">
                                <tr>
                                    <th>Name</th>
                                    <th>Student ID</th>
                                    <th>Status</th>
                                    <th>Archived</th>
                                    <th>Primary</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                `;

                members.forEach(member => {
                    const fullName = `${member.first_name} ${member.middle_name || ''} ${member.last_name}`.trim();
                    const isArchived = (member.is_archived ?? 'f') === 't';
                    const isPrimary = (member.household_primary ?? 'f') === 't';
                    const isCurrent = member.student_id === studentId;

                    html += `
                        <tr class="${isCurrent ? 'table-active' : ''} ${isPrimary ? 'table-success' : ''}">
                            <td>
                                <strong>${escapeHtml(fullName)}</strong>
                                ${isCurrent ? '<span class="badge bg-info ms-2">You selected this</span>' : ''}
                            </td>
                            <td><code>${escapeHtml(member.student_id)}</code></td>
                            <td><span class="badge bg-secondary">${escapeHtml(member.status)}</span></td>
                            <td>
                                ${isArchived ? 
                                    '<span class="badge bg-warning text-dark"><i class="bi bi-archive me-1"></i>Archived</span>' : 
                                    '<span class="badge bg-success"><i class="bi bi-check-circle me-1"></i>Active</span>'
                                }
                            </td>
                            <td>
                                ${isPrimary ? 
                                    '<span class="badge bg-primary"><i class="bi bi-star-fill me-1"></i>Primary</span>' : 
                                    '<span class="text-muted">-</span>'
                                }
                            </td>
                            <td>
                                ${!isArchived ? 
                                    `<a href="manage_applicants.php?student_id=${encodeURIComponent(member.student_id)}" 
                                       class="btn btn-xs btn-outline-primary" target="_blank" title="View student details">
                                        <i class="bi bi-eye"></i>
                                    </a>` : 
                                    `<a href="archived_students.php" 
                                       class="btn btn-xs btn-outline-secondary" target="_blank" title="View in archived students">
                                        <i class="bi bi-archive"></i>
                                    </a>`
                                }
                            </td>
                        </tr>
                    `;
                });

                html += `
                            </tbody>
                        </table>
                    </div>
                `;

                // Add household group info if available
                if (currentStudent && currentStudent.household_group_id) {
                    html += `
                        <div class="mt-3 p-2 bg-light rounded">
                            <small class="text-muted">
                                <i class="bi bi-info-circle me-1"></i>
                                Household Group ID: <code>${escapeHtml(currentStudent.household_group_id)}</code>
                            </small>
                        </div>
                    `;
                }

                content.innerHTML = html;

            } catch (err) {
                content.innerHTML = `
                    <div class="alert alert-danger">
                        <i class="bi bi-exclamation-triangle me-2"></i>
                        Error loading household members: ${escapeHtml(err.message)}
                    </div>
                `;
            }
        }

        async function resolveGroup(surname) {
            // Find all students with this surname in current data
            const students = (currentData.rows || []).filter(s => 
                s.last_name.toLowerCase() === surname.toLowerCase()
            );

            if (students.length < 2) {
                showAlert('warning', 'This group has only one member. Nothing to resolve.');
                return;
            }

            // Check if primary is already marked
            const primary = students.find(s => (s.household_primary ?? 'f') === 't');
            
            if (!primary) {
                showAlert('warning', 'Please mark a primary recipient first before archiving duplicates.');
                return;
            }

            // Get all non-primary students
            const duplicates = students.filter(s => (s.household_primary ?? 'f') !== 't');

            if (duplicates.length === 0) {
                showAlert('info', 'No duplicates to archive.');
                return;
            }

            // Show confirmation modal
            document.getElementById('archiveCount').textContent = duplicates.length;
            document.getElementById('primaryName').textContent = `${primary.first_name} ${primary.last_name}`;

            const modal = new bootstrap.Modal(document.getElementById('confirmArchiveModal'));
            modal.show();

            // Set up confirm button
            document.getElementById('confirmArchiveBtn').onclick = async function() {
                modal.hide();
                await archiveDuplicates(primary.student_id, duplicates.map(d => d.student_id));
            };
        }

        async function archiveDuplicates(primaryId, duplicateIds) {
            const formData = new FormData();
            formData.append('csrf_token', csrfToken);
            formData.append('primary_id', primaryId);
            duplicateIds.forEach(id => formData.append('duplicate_ids[]', id));

            try {
                const res = await fetch(endpoint + '?api=archive_duplicates', {
                    method: 'POST',
                    body: formData
                });
                const result = await res.json();

                if (result.success) {
                    showAlert('success', result.message || 'Duplicates archived successfully');
                    fetchData(currentData.page);
                } else {
                    showAlert('danger', result.error || 'Failed to archive duplicates');
                }
            } catch (err) {
                showAlert('danger', 'Network error: ' + err.message);
            }
        }

        function showAlert(type, message) {
            const alertDiv = document.createElement('div');
            alertDiv.className = `alert alert-${type} alert-dismissible fade show position-fixed top-0 start-50 translate-middle-x mt-3`;
            alertDiv.style.zIndex = '9999';
            alertDiv.innerHTML = `
                ${message}
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            `;
            document.body.appendChild(alertDiv);
            setTimeout(() => alertDiv.remove(), 5000);
        }

        function escapeHtml(s){ 
            return (s||'').toString()
                .replace(/&/g,'&amp;')
                .replace(/</g,'&lt;')
                .replace(/>/g,'&gt;')
                .replace(/"/g,'&quot;')
                .replace(/'/g,'&#039;'); 
        }

        // Event listeners
        document.getElementById('applyBtn').addEventListener('click', function(e){ 
            e.preventDefault(); 
            fetchData(1); 
        });

        document.getElementById('per_page').addEventListener('change', ()=>fetchData(1));

        document.getElementById('exportCsvBtn').addEventListener('click', function(e){ 
            e.preventDefault(); 
            const f=readFilters(); 
            f.csv=1; 
            const p=new URLSearchParams(f); 
            window.location = endpoint + '?api=rows&' + p.toString(); 
        });

        // Select All checkbox
        document.getElementById('selectAll').addEventListener('change', function() {
            const checkboxes = document.querySelectorAll('.student-checkbox:not(:disabled)');
            checkboxes.forEach(cb => cb.checked = this.checked);
        });

        // Initialize
        loadSelects().then(()=>fetchData(1));
    </script>
</body>
</html>
