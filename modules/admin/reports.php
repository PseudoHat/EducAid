<?php
require_once __DIR__ . '/../../includes/CSRFProtection.php';
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['admin_username'])) {
    header('Location: ../../modules/admin/admin_login.php');
    exit;
}

include __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/report_filters.php';
require_once __DIR__ . '/../../includes/report_generator.php';

// Resolve admin context
$adminId = $_SESSION['admin_id'] ?? null;
$adminUsername = $_SESSION['admin_username'] ?? null;
$adminMunicipalityId = null;
$adminRole = 'sub_admin';

if ($adminId) {
    $admRes = pg_query_params($connection, "SELECT municipality_id, role FROM admins WHERE admin_id = $1", [$adminId]);
} elseif ($adminUsername) {
    $admRes = pg_query_params($connection, "SELECT municipality_id, role FROM admins WHERE username = $1", [$adminUsername]);
}

if ($admRes && pg_num_rows($admRes)) {
    $admRow = pg_fetch_assoc($admRes);
    $adminMunicipalityId = $admRow['municipality_id'];
    $adminRole = $admRow['role'];
}

// Generate CSRF token
$csrfToken = CSRFProtection::generateToken('generate_report');

// Get filter options from database
// For super admins: show all options but default to their municipality
// For sub admins: only show their municipality's data
$barangays = pg_query($connection, "SELECT barangay_id, name, municipality_id FROM barangays ORDER BY name");
$municipalities = pg_query($connection, "SELECT municipality_id, name FROM municipalities ORDER BY name");
$yearLevels = pg_query($connection, "SELECT year_level_id, name FROM year_levels ORDER BY name");

if ($adminRole === 'super_admin') {
    // Super admins can see all universities, distributions, and academic years
    $universities = pg_query($connection, "SELECT university_id, name FROM universities ORDER BY name");
    $distributions = pg_query($connection, "SELECT snapshot_id, distribution_id, academic_year, semester, finalized_at FROM distribution_snapshots WHERE finalized_at IS NOT NULL ORDER BY finalized_at DESC");
    $academicYears = pg_query($connection, "SELECT DISTINCT current_academic_year FROM students WHERE current_academic_year IS NOT NULL ORDER BY current_academic_year DESC");
} else {
    // Sub-admins only see data from their municipality
    $universities = pg_query_params($connection, "SELECT DISTINCT u.university_id, u.name FROM universities u INNER JOIN students s ON u.university_id = s.university_id WHERE s.municipality_id = $1 ORDER BY u.name", [$adminMunicipalityId]);
    $distributions = pg_query_params($connection, "SELECT DISTINCT ds.snapshot_id, ds.distribution_id, ds.academic_year, ds.semester, ds.finalized_at FROM distribution_snapshots ds INNER JOIN distribution_lists dl ON ds.snapshot_id = dl.snapshot_id INNER JOIN students s ON dl.student_id = s.student_id WHERE s.municipality_id = $1 AND ds.finalized_at IS NOT NULL ORDER BY ds.finalized_at DESC", [$adminMunicipalityId]);
    $academicYears = pg_query_params($connection, "SELECT DISTINCT current_academic_year FROM students WHERE municipality_id = $1 AND current_academic_year IS NOT NULL ORDER BY current_academic_year DESC", [$adminMunicipalityId]);
}

$pageTitle = "Reports & Analytics";
include __DIR__ . '/../../includes/admin/admin_head.php';
?>
<link rel="stylesheet" href="../../assets/css/admin/reports.css">
<link rel="stylesheet" href="../../assets/css/admin/modern-ui.css">
<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
</head>
<body>
  <?php include __DIR__ . '/../../includes/admin/admin_topbar.php'; ?>
  
  <div id="wrapper" class="admin-wrapper">
    <?php include __DIR__ . '/../../includes/admin/admin_sidebar.php'; ?>
    <?php include __DIR__ . '/../../includes/admin/admin_header.php'; ?>

    <section class="home-section" id="mainContent">
      <div class="container-fluid py-4">
    <!-- Header -->
    <div class="modern-page-header">
        <div class="d-flex justify-content-between align-items-center">
            <div>
                <h1 class="modern-page-title">
                    <i class="bi bi-file-earmark-bar-graph-fill text-gradient"></i>
                    Reports & Analytics
                </h1>
                <p class="modern-page-subtitle">Generate comprehensive reports with advanced filtering options</p>
            </div>
            <div>
                <button class="modern-btn modern-btn-info" onclick="resetFilters()">
                    <i class="bi bi-arrow-clockwise"></i> Reset All Filters
                </button>
            </div>
        </div>
    </div>

    <!-- Filter Panel -->
    <div class="modern-card mb-4">
        <div class="modern-card-header">
            <div class="d-flex justify-content-between align-items-center">
                <h5 class="mb-0">
                    <i class="bi bi-funnel-fill me-2"></i> Report Filters
                </h5>
                <span id="filterBadge" class="modern-badge modern-badge-info">0 filters applied</span>
            </div>
        </div>
        <div class="card-body p-4">
            <form id="reportFiltersForm">
                <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>">
                
                <div class="row g-3">
                    <!-- Student Status -->
                    <div class="col-md-3">
                        <label class="modern-form-label">
                            <i class="bi bi-person-badge"></i> Student Status
                        </label>
                        <select name="status[]" class="form-select modern-form-control multi-select" multiple>
                            <option value="active">Active</option>
                            <option value="applicant">Applicant</option>
                            <option value="under_registration">Under Registration</option>
                            <option value="disabled">Disabled</option>
                        </select>
                        <div class="form-check mt-2">
                            <input class="form-check-input" type="checkbox" name="include_archived" id="includeArchived">
                            <label class="form-check-label small" for="includeArchived">
                                Include Archived Students
                            </label>
                        </div>
                    </div>

                    <!-- Gender -->
                    <div class="col-md-3">
                        <label class="modern-form-label">
                            <i class="bi bi-gender-ambiguous"></i> Gender
                        </label>
                        <select name="gender" class="form-select modern-form-control">
                            <option value="">All</option>
                            <option value="Male">Male</option>
                            <option value="Female">Female</option>
                        </select>
                    </div>

                    <!-- Municipality -->
                    <div class="col-md-3">
                        <label class="modern-form-label">
                            <i class="bi bi-building"></i> Municipality
                        </label>
                        <?php 
                        // Get municipality name for display
                        pg_result_seek($municipalities, 0);
                        $municipalityName = '';
                        while ($m = pg_fetch_assoc($municipalities)) {
                            if ($m['municipality_id'] == $adminMunicipalityId) {
                                $municipalityName = $m['name'];
                                break;
                            }
                        }
                        ?>
                        <input type="text" class="modern-form-control" value="<?php echo htmlspecialchars($municipalityName); ?>" readonly>
                        <input type="hidden" name="municipality_id" value="<?php echo $adminMunicipalityId; ?>" id="municipalityFilterValue">
                        <small class="text-muted">Your assigned municipality</small>
                    </div>

                    <!-- Barangay -->
                    <div class="col-md-3">
                        <label class="modern-form-label">
                            <i class="bi bi-geo-alt"></i> Barangay
                        </label>
                        <select name="barangay_id[]" class="form-select modern-form-control multi-select" multiple id="barangayFilter">
                            <?php 
                            // Reset pointer to read barangays
                            pg_result_seek($barangays, 0);
                            while ($b = pg_fetch_assoc($barangays)): 
                            ?>
                                <option value="<?php echo $b['barangay_id']; ?>" data-municipality="<?php echo $b['municipality_id']; ?>">
                                    <?php echo htmlspecialchars($b['name']); ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>

                    <!-- University -->
                    <div class="col-md-3">
                        <label class="modern-form-label">
                            <i class="bi bi-mortarboard"></i> University
                        </label>
                        <select name="university_id[]" class="form-select modern-form-control multi-select" multiple>
                            <?php while ($u = pg_fetch_assoc($universities)): ?>
                                <option value="<?php echo $u['university_id']; ?>">
                                    <?php echo htmlspecialchars($u['name']); ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>

                    <!-- Year Level -->
                    <div class="col-md-3">
                        <label class="modern-form-label">
                            <i class="bi bi-calendar-range"></i> Year Level
                        </label>
                        <select name="year_level_id[]" class="form-select modern-form-control multi-select" multiple>
                            <?php while ($yl = pg_fetch_assoc($yearLevels)): ?>
                                <option value="<?php echo $yl['year_level_id']; ?>">
                                    <?php echo htmlspecialchars($yl['name']); ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>

                    <!-- Academic Year -->
                    <div class="col-md-3">
                        <label class="modern-form-label">
                            <i class="bi bi-calendar3"></i> Academic Year
                        </label>
                        <select name="academic_year" class="form-select modern-form-control">
                            <option value="">All Years</option>
                            <?php while ($ay = pg_fetch_assoc($academicYears)): ?>
                                <option value="<?php echo htmlspecialchars($ay['current_academic_year']); ?>">
                                    <?php echo htmlspecialchars($ay['current_academic_year']); ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>

                    <!-- Distribution -->
                    <div class="col-md-3">
                        <label class="modern-form-label">
                            <i class="bi bi-box-seam"></i> Distribution
                        </label>
                        <select name="distribution_id" class="form-select modern-form-control">
                            <option value="">All Distributions</option>
                            <?php while ($d = pg_fetch_assoc($distributions)): ?>
                                <option value="<?php echo $d['snapshot_id']; ?>">
                                    <?php 
                                    echo htmlspecialchars($d['distribution_id']) . ' - ' . 
                                         htmlspecialchars($d['academic_year']) . ' ' . 
                                         htmlspecialchars($d['semester']); 
                                    ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>

                    <!-- Date Range -->
                    <div class="col-md-3">
                        <label class="modern-form-label">
                            <i class="bi bi-calendar-check"></i> Registration Date From
                        </label>
                        <input type="date" name="date_from" class="modern-form-control">
                    </div>

                    <div class="col-md-3">
                        <label class="modern-form-label">
                            <i class="bi bi-calendar-x"></i> Registration Date To
                        </label>
                        <input type="date" name="date_to" class="modern-form-control">
                    </div>

                    <!-- Confidence Score Range -->
                    <div class="col-md-3">
                        <label class="modern-form-label">
                            <i class="bi bi-speedometer2"></i> Min Confidence Score
                        </label>
                        <input type="number" name="confidence_min" class="modern-form-control" min="0" max="100" placeholder="0-100">
                    </div>

                    <div class="col-md-3">
                        <label class="modern-form-label">
                            <i class="bi bi-speedometer"></i> Max Confidence Score
                        </label>
                        <input type="number" name="confidence_max" class="modern-form-control" min="0" max="100" placeholder="0-100">
                    </div>
                </div>

                <hr class="my-4">

                <!-- Action Buttons -->
                <div class="d-flex flex-wrap gap-2 justify-content-between align-items-center">
                    <div class="d-flex flex-wrap gap-2">
                        <button type="button" class="btn btn-primary btn-lg" onclick="previewReport()">
                            <i class="bi bi-eye-fill"></i> Preview Report
                        </button>
                        <button type="button" class="btn btn-danger" onclick="exportPDF()">
                            <i class="bi bi-file-pdf-fill"></i> Export PDF
                        </button>
                        <button type="button" class="modern-btn modern-btn-success" onclick="exportExcel()">
                            <i class="bi bi-file-excel-fill"></i> Export Excel
                        </button>
                    </div>
                    <div>
                        <small class="text-muted" id="filterSummary">Select filters and click Preview</small>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <!-- Statistics Overview -->
    <div id="statisticsPanel" class="row g-3 mb-4" style="display: none;">
        <div class="col-md-3">
            <div class="card stat-card bg-gradient-primary text-white">
                <div class="card-body">
                    <div class="d-flex justify-content-between">
                        <div>
                            <h6 class="card-title mb-1">Total Students</h6>
                            <h2 class="mb-0" id="statTotalStudents">0</h2>
                        </div>
                        <div class="stat-icon">
                            <i class="bi bi-people-fill"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card stat-card bg-gradient-success text-white">
                <div class="card-body">
                    <div class="d-flex justify-content-between">
                        <div>
                            <h6 class="card-title mb-1">Male</h6>
                            <h2 class="mb-0" id="statMale">0</h2>
                            <small id="statMalePercent">0%</small>
                        </div>
                        <div class="stat-icon">
                            <i class="bi bi-gender-male"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card stat-card bg-gradient-info text-white">
                <div class="card-body">
                    <div class="d-flex justify-content-between">
                        <div>
                            <h6 class="card-title mb-1">Female</h6>
                            <h2 class="mb-0" id="statFemale">0</h2>
                            <small id="statFemalePercent">0%</small>
                        </div>
                        <div class="stat-icon">
                            <i class="bi bi-gender-female"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card stat-card bg-gradient-warning text-white">
                <div class="card-body">
                    <div class="d-flex justify-content-between">
                        <div>
                            <h6 class="card-title mb-1">Avg Confidence</h6>
                            <h2 class="mb-0" id="statConfidence">0%</h2>
                        </div>
                        <div class="stat-icon">
                            <i class="bi bi-graph-up"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Preview Results -->
    <div id="previewPanel" class="card shadow-sm" style="display: none;">
        <div class="card-header bg-light d-flex justify-content-between align-items-center">
            <h5 class="mb-0">
                <i class="bi bi-table"></i> Report Preview
                <span class="badge bg-primary ms-2" id="previewCount">0 records</span>
            </h5>
            <button class="btn btn-sm btn-outline-secondary" onclick="$('#previewPanel').fadeOut()">
                <i class="bi bi-x-lg"></i> Close
            </button>
        </div>
        <div class="card-body">
            <div class="modern-alert modern-alert-info">
                <i class="bi bi-info-circle"></i>
                <strong>Preview Mode:</strong> Showing up to 50 records. Export to PDF/Excel for complete dataset.
            </div>
            <div class="table-responsive">
                <table class="table table-striped table-hover table-bordered" id="previewTable">
                    <thead class="table-primary">
                        <tr>
                            <th style="width: 5%;">No.</th>
                            <th style="width: 12%;">Student ID</th>
                            <th style="width: 18%;">Name</th>
                            <th style="width: 8%;">Gender</th>
                            <th style="width: 15%;">Barangay</th>
                            <th style="width: 18%;">University</th>
                            <th style="width: 12%;">Year Level</th>
                            <th style="width: 12%;">Status</th>
                        </tr>
                    </thead>
                    <tbody id="previewTableBody">
                        <tr>
                            <td colspan="8" class="text-center text-muted">
                                <i class="bi bi-inbox"></i> No data to display
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

    </section>
  </div>

<script src="https://cdn.jsdelivr.net/npm/jquery@3.7.1/dist/jquery.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
<script src="../../assets/js/admin/reports.js"></script>

</body>
</html>

<?php
// Close database connection
if (isset($connection)) {
    pg_close($connection);
}
?>
