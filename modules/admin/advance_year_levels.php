<?php
// AJAX endpoint for preview - handle BEFORE any other output
if (isset($_GET['action']) && $_GET['action'] === 'preview') {
    // Start session and load dependencies
    if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
    include __DIR__ . '/../../config/database.php';
    
    // Clear any output that might have occurred
    if (ob_get_level()) ob_end_clean();
    ob_start();
    
    // Check authentication
    if (!isset($_SESSION['admin_username'])) {
        ob_clean();
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'error' => 'Not authenticated']);
        exit;
    }
    
    // Execute query
    $result = pg_query($connection, "SELECT * FROM preview_year_level_advancement()");
    
    // Clear buffer and send JSON
    ob_clean();
    header('Content-Type: application/json');
    
    if ($result && pg_num_rows($result) > 0) {
        $row = pg_fetch_assoc($result);
        
        echo json_encode([
            'success' => true,
            'summary' => json_decode($row['summary'], true),
            'students_advancing' => json_decode($row['students_advancing'], true),
            'students_graduating' => json_decode($row['students_graduating'], true),
            'warnings' => json_decode($row['warnings'], true),
            'can_advance' => $row['can_advance'] === 't',
            'blocking_reasons' => $row['blocking_reasons'] ? 
                json_decode(str_replace(['{', '}'], ['[', ']'], $row['blocking_reasons']), true) : []
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'error' => 'Failed to generate preview: ' . pg_last_error($connection)
        ]);
    }
    exit;
}

// AJAX endpoint for execution - handle BEFORE any other output
if (isset($_POST['action']) && $_POST['action'] === 'execute') {
    // Start session and load dependencies
    if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
    include __DIR__ . '/../../config/database.php';
    
    // Clear any output that might have occurred
    if (ob_get_level()) ob_end_clean();
    ob_start();
    
    // Check authentication
    if (!isset($_SESSION['admin_username'])) {
        ob_clean();
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'error' => 'Not authenticated']);
        exit;
    }
    
    $admin_id = $_SESSION['admin_id'] ?? null;
    $admin_password = trim($_POST['admin_password'] ?? '');
    $notes = trim($_POST['notes'] ?? '');
    
    // Clear buffer and send JSON
    ob_clean();
    header('Content-Type: application/json');
    
    if (!$admin_id) {
        echo json_encode([
            'success' => false,
            'error' => 'Admin ID not found in session'
        ]);
        exit;
    }
    
    // Verify admin password
    if (empty($admin_password)) {
        echo json_encode([
            'success' => false,
            'error' => 'Admin password is required for this critical operation'
        ]);
        exit;
    }
    
    // Get admin credentials and verify password
    $admin_query = "SELECT password FROM admins WHERE admin_id = $1";
    $admin_result = pg_query_params($connection, $admin_query, [$admin_id]);
    
    if (!$admin_result || pg_num_rows($admin_result) === 0) {
        echo json_encode([
            'success' => false,
            'error' => 'Admin account not found'
        ]);
        exit;
    }
    
    $admin_data = pg_fetch_assoc($admin_result);
    
    // Verify password (assuming bcrypt/password_hash)
    if (!password_verify($admin_password, $admin_data['password'])) {
        echo json_encode([
            'success' => false,
            'error' => 'Invalid admin password. Please try again.'
        ]);
        exit;
    }
    
    // Execute the year advancement
    $query = "SELECT * FROM execute_year_level_advancement($1, $2)";
    $result = pg_query_params($connection, $query, [$admin_id, $notes]);
    
    if ($result && pg_num_rows($result) > 0) {
        $row = pg_fetch_assoc($result);
        
        echo json_encode([
            'success' => $row['success'] === 't',
            'message' => $row['message'],
            'students_advanced' => (int)$row['students_advanced'],
            'students_graduated' => (int)$row['students_graduated'],
            'execution_log' => json_decode($row['execution_log'], true)
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'error' => 'Failed to execute advancement: ' . pg_last_error($connection)
        ]);
    }
    exit;
}

// Regular page load - start output buffering
ob_start();

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
include __DIR__ . '/../../config/database.php';

$page_title = 'Advance Year Levels';
$extra_css = [];
include __DIR__ . '/../../includes/admin/admin_head.php';
?>
<style>
    .status-banner {
        border-radius: 8px;
        padding: 20px;
        margin-bottom: 25px;
        border-left: 5px solid;
    }
    
    .status-banner.ready {
        background: linear-gradient(135deg, #d4edda 0%, #c3e6cb 100%);
        border-color: #28a745;
    }
    
    .status-banner.warning {
        background: linear-gradient(135deg, #fff3cd 0%, #ffeaa7 100%);
        border-color: #ffc107;
    }
    
    .status-banner.blocked {
        background: linear-gradient(135deg, #f8d7da 0%, #f5c6cb 100%);
        border-color: #dc3545;
    }
    
    .status-icon {
        font-size: 48px;
        margin-right: 20px;
    }
    
    .summary-card {
        background: white;
        border-radius: 8px;
        padding: 20px;
        margin-bottom: 20px;
        box-shadow: 0 2px 4px rgba(0,0,0,0.1);
    }
    
    .summary-card h4 {
        margin-top: 0;
        color: #333;
        border-bottom: 2px solid #007bff;
        padding-bottom: 10px;
        margin-bottom: 15px;
    }
    
    .stat-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 15px;
        margin-top: 20px;
    }
    
    .stat-box {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
        padding: 20px;
        border-radius: 8px;
        text-align: center;
    }
    
    .stat-box.advancing {
        background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
    }
    
    .stat-box.graduating {
        background: linear-gradient(135deg, #43e97b 0%, #38f9d7 100%);
    }
    
    .stat-box.warning {
        background: linear-gradient(135deg, #fa709a 0%, #fee140 100%);
    }
    
    .stat-number {
        font-size: 36px;
        font-weight: bold;
        margin-bottom: 5px;
    }
    
    .stat-label {
        font-size: 14px;
        opacity: 0.9;
    }
    
    .student-table {
        width: 100%;
        border-collapse: collapse;
        margin-top: 15px;
    }
    
    .student-table th {
        background: #f8f9fa;
        padding: 12px;
        text-align: left;
        border-bottom: 2px solid #dee2e6;
        font-weight: 600;
    }
    
    .student-table td {
        padding: 10px 12px;
        border-bottom: 1px solid #dee2e6;
    }
    
    .student-table tr:hover {
        background: #f8f9fa;
    }
    
    .badge-next-status {
        display: inline-block;
        padding: 4px 12px;
        border-radius: 20px;
        font-size: 12px;
        font-weight: 600;
    }
    
    .badge-advancing {
        background: #cfe2ff;
        color: #084298;
    }
    
    .badge-graduating {
        background: #d1e7dd;
        color: #0a3622;
    }
    
    .collapsible-section {
        margin-bottom: 20px;
    }
    
    .section-header {
        background: #f8f9fa;
        padding: 15px;
        border-radius: 8px;
        cursor: pointer;
        display: flex;
        justify-content: space-between;
        align-items: center;
        transition: background 0.2s;
    }
    
    .section-header:hover {
        background: #e9ecef;
    }
    
    .section-header h5 {
        margin: 0;
        display: flex;
        align-items: center;
        gap: 10px;
    }
    
    .section-content {
        display: none;
        padding: 15px;
        border: 1px solid #dee2e6;
        border-top: none;
        border-radius: 0 0 8px 8px;
    }
    
    .section-content.active {
        display: block;
    }
    
    .toggle-icon {
        transition: transform 0.3s;
    }
    
    .toggle-icon.rotated {
        transform: rotate(180deg);
    }
    
    .modal {
        display: none;
        position: fixed;
        z-index: 9999;
        left: 0;
        top: 0;
        width: 100%;
        height: 100%;
        overflow: auto;
        background-color: rgba(0,0,0,0.5);
    }
    
    .modal-content {
        background-color: #fefefe;
        margin: 2% auto;
        padding: 0;
        border-radius: 8px;
        width: 95%;
        max-width: 1400px;
        max-height: 90vh;
        overflow-y: auto;
        position: relative;
        z-index: 10000;
    }
    
    .modal-header {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
        padding: 20px 30px;
        border-radius: 8px 8px 0 0;
        display: flex;
        justify-content: space-between;
        align-items: center;
    }
    
    .modal-body {
        padding: 30px;
    }
    
    .modal-footer {
        background: #f8f9fa;
        padding: 25px 30px;
        border-radius: 0 0 8px 8px;
        display: flex;
        flex-direction: column;
        gap: 20px;
    }
    
    .confirmation-section {
        background: #fff3cd;
        border: 2px solid #ffc107;
        border-radius: 8px;
        padding: 15px 20px;
        display: flex;
        align-items: center;
        gap: 12px;
    }
    
    .confirmation-section input[type="checkbox"] {
        width: 20px;
        height: 20px;
        cursor: pointer;
    }
    
    .confirmation-section label {
        margin: 0;
        font-weight: 600;
        color: #856404;
        cursor: pointer;
        user-select: none;
    }
    
    .password-confirmation {
        background: #f8d7da;
        border: 2px solid #dc3545;
        border-radius: 8px;
        padding: 15px 20px;
    }
    
    .password-confirmation label {
        display: block;
        font-weight: 600;
        color: #721c24;
        margin-bottom: 10px;
    }
    
    .password-confirmation input[type="password"] {
        width: 100%;
        padding: 10px 15px;
        border: 2px solid #dc3545;
        border-radius: 6px;
        font-size: 14px;
        transition: border-color 0.2s;
    }
    
    .password-confirmation input[type="password"]:focus {
        outline: none;
        border-color: #bd2130;
        box-shadow: 0 0 0 3px rgba(220, 53, 69, 0.1);
    }
    
    .password-confirmation small {
        display: block;
        color: #721c24;
        margin-top: 8px;
        font-style: italic;
    }
    
    .modal-actions {
        display: flex;
        justify-content: flex-end;
        gap: 10px;
    }
    
    .close {
        color: white;
        font-size: 32px;
        font-weight: bold;
        cursor: pointer;
        line-height: 1;
    }
    
    .close:hover {
        opacity: 0.8;
    }
    
    .btn-primary-lg {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
        border: none;
        padding: 15px 40px;
        font-size: 16px;
        font-weight: 600;
        border-radius: 8px;
        cursor: pointer;
        transition: transform 0.2s, box-shadow 0.2s;
    }
    
    .btn-primary-lg:hover:not(:disabled) {
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(102, 126, 234, 0.4);
    }
    
    .btn-primary-lg:disabled {
        opacity: 0.6;
        cursor: not-allowed;
    }
    
    .blocking-reasons {
        background: #fff3cd;
        border: 1px solid #ffc107;
        border-radius: 8px;
        padding: 15px;
        margin-bottom: 20px;
    }
    
    .blocking-reasons ul {
        margin: 10px 0 0 0;
        padding-left: 20px;
    }
    
    .blocking-reasons li {
        margin-bottom: 8px;
        color: #856404;
    }
</style>
</head>
<body>
<?php include __DIR__ . '/../../includes/admin/admin_topbar.php'; ?>
<div id="wrapper" class="admin-wrapper">
    <?php include __DIR__ . '/../../includes/admin/admin_sidebar.php'; ?>
    <?php include __DIR__ . '/../../includes/admin/admin_header.php'; ?>

    <section class="home-section" id="mainContent">
        <div class="container-fluid py-4 px-4">
            <div class="section-header mb-4">
                <h2 class="fw-bold text-primary">
                    <i class="bi bi-arrow-up-circle"></i> Advance Year Levels
                </h2>
                <p class="text-muted">Promote students to the next year level and graduate eligible students</p>
            </div>

                <!-- Status Banner (will be populated by JavaScript) -->
                <div id="statusBanner" class="status-banner" style="display: none;">
                    <div class="d-flex align-items-center">
                        <div class="status-icon" id="statusIcon"></div>
                        <div class="flex-grow-1">
                            <h4 id="statusTitle" class="mb-2"></h4>
                            <div id="statusMessage"></div>
                        </div>
                    </div>
                </div>

                <!-- Blocking Reasons -->
                <div id="blockingReasons" class="blocking-reasons" style="display: none;">
                    <h5><i class="bi bi-exclamation-triangle"></i> Cannot Advance Year Levels</h5>
                    <ul id="blockingList"></ul>
                </div>

                <!-- Summary Cards -->
                <div class="row" id="summarySection" style="display: none;">
                    <div class="col-md-12">
                        <div class="summary-card">
                            <h4><i class="bi bi-pie-chart"></i> Advancement Overview</h4>
                            
                            <div class="stat-grid">
                                <div class="stat-box">
                                    <div class="stat-number" id="totalStudents">0</div>
                                    <div class="stat-label">Total Students</div>
                                </div>
                                <div class="stat-box advancing">
                                    <div class="stat-number" id="totalAdvancing">0</div>
                                    <div class="stat-label">Advancing to Next Year</div>
                                </div>
                                <div class="stat-box graduating">
                                    <div class="stat-number" id="totalGraduating">0</div>
                                    <div class="stat-label">Graduating (Auto-Archived)</div>
                                </div>
                                <div class="stat-box warning" id="warningsBox" style="display: none;">
                                    <div class="stat-number" id="totalWarnings">0</div>
                                    <div class="stat-label">Warnings</div>
                                </div>
                            </div>

                            <div class="mt-4">
                                <h5>Breakdown by Year Level</h5>
                                <div class="row mt-3">
                                    <div class="col-md-3">
                                        <strong>1st → 2nd Year:</strong> <span id="count_1_to_2">0</span>
                                    </div>
                                    <div class="col-md-3">
                                        <strong>2nd → 3rd Year:</strong> <span id="count_2_to_3">0</span>
                                    </div>
                                    <div class="col-md-3">
                                        <strong>3rd → 4th Year:</strong> <span id="count_3_to_4">0</span>
                                    </div>
                                    <div class="col-md-3">
                                        <strong>4th → 5th Year:</strong> <span id="count_4_to_5">0</span>
                                    </div>
                                </div>
                                <div class="row mt-2">
                                    <div class="col-md-3">
                                        <strong>Graduating (4th Year):</strong> <span id="count_grad_4">0</span>
                                    </div>
                                    <div class="col-md-3">
                                        <strong>Graduating (5th Year):</strong> <span id="count_grad_5">0</span>
                                    </div>
                                    <div class="col-md-3" id="noCourseCount" style="display: none;">
                                        <strong class="text-warning">No Course Mapping:</strong> <span id="count_no_course">0</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Action Buttons -->
                <div class="text-center my-4">
                    <button id="btnPreview" class="btn-primary-lg">
                        <i class="bi bi-eye"></i> Preview Year Advancement
                    </button>
                </div>

                <!-- Loading Indicator -->
                <div id="loadingIndicator" class="text-center my-4" style="display: none;">
                    <div class="spinner-border text-primary" role="status">
                        <span class="visually-hidden">Loading...</span>
                    </div>
                    <p class="mt-2">Loading advancement preview...</p>
                </div>
        </div>
    </section>

    <!-- Preview Modal -->
    <div id="previewModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3><i class="bi bi-list-check"></i> Year Level Advancement Preview</h3>
                <span class="close">&times;</span>
            </div>
            <div class="modal-body" id="modalBody">
                <!-- Content will be populated by JavaScript -->
            </div>
            <div class="modal-footer">
                <div class="confirmation-section">
                    <input type="checkbox" id="confirmCheckbox">
                    <label for="confirmCheckbox">
                        <i class="bi bi-exclamation-triangle-fill"></i>
                        I understand that graduating students will be automatically archived and this action cannot be easily undone
                    </label>
                </div>
                <div class="password-confirmation">
                    <label for="adminPassword">
                        <i class="bi bi-shield-lock-fill"></i> Confirm Your Admin Password
                    </label>
                    <input 
                        type="password" 
                        id="adminPassword" 
                        placeholder="Enter your admin password to proceed"
                        autocomplete="current-password"
                    >
                    <small><i class="bi bi-info-circle"></i> This is a critical operation requiring password verification</small>
                </div>
                <div class="modal-actions">
                    <button class="btn btn-secondary btn-lg" id="btnCloseModal">
                        <i class="bi bi-x-circle"></i> Cancel
                    </button>
                    <button class="btn btn-success btn-lg" id="btnExecute" disabled>
                        <i class="bi bi-check-circle"></i> Execute Year Advancement
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script>
        let previewData = null;
        
        // Load preview on page load
        document.addEventListener('DOMContentLoaded', function() {
            loadPreview();
            
            document.getElementById('btnPreview').addEventListener('click', showModal);
            
            // Enable execute button only when both checkbox is checked AND password is entered
            const confirmCheckbox = document.getElementById('confirmCheckbox');
            const adminPassword = document.getElementById('adminPassword');
            const btnExecute = document.getElementById('btnExecute');
            
            function updateExecuteButton() {
                btnExecute.disabled = !(confirmCheckbox.checked && adminPassword.value.trim().length > 0);
            }
            
            confirmCheckbox.addEventListener('change', updateExecuteButton);
            adminPassword.addEventListener('input', updateExecuteButton);
            
            // Close modal
            document.querySelector('.close').addEventListener('click', closeModal);
            document.getElementById('btnCloseModal').addEventListener('click', closeModal);
            
            window.onclick = function(event) {
                const modal = document.getElementById('previewModal');
                if (event.target == modal) {
                    closeModal();
                }
            };
        });
        
        function loadPreview() {
            document.getElementById('loadingIndicator').style.display = 'block';
            
            fetch('?action=preview')
                .then(response => response.json())
                .then(data => {
                    document.getElementById('loadingIndicator').style.display = 'none';
                    
                    if (data.success) {
                        previewData = data;
                        displayPreview(data);
                    } else {
                        alert('Error loading preview: ' + data.error);
                    }
                })
                .catch(error => {
                    document.getElementById('loadingIndicator').style.display = 'none';
                    alert('Error: ' + error.message);
                });
        }
        
        function displayPreview(data) {
            const summary = data.summary;
            const canAdvance = data.can_advance;
            const blockingReasons = data.blocking_reasons;
            
            // Update status banner
            const banner = document.getElementById('statusBanner');
            const icon = document.getElementById('statusIcon');
            const title = document.getElementById('statusTitle');
            const message = document.getElementById('statusMessage');
            
            banner.style.display = 'block';
            
            if (canAdvance) {
                banner.className = 'status-banner ready';
                icon.innerHTML = '✅';
                title.textContent = `Ready to Advance: ${summary.current_academic_year} → ${summary.next_academic_year}`;
                message.innerHTML = `
                    <p><strong>Both semester distributions completed</strong></p>
                    <p>✓ 1st Semester: Finalized<br>✓ 2nd Semester: Finalized</p>
                `;
                document.getElementById('btnPreview').disabled = false;
            } else {
                banner.className = 'status-banner blocked';
                icon.innerHTML = '⚠️';
                title.textContent = 'Cannot Advance Year Levels';
                message.innerHTML = '<p>Please resolve the issues below before advancing.</p>';
                document.getElementById('btnPreview').disabled = true;
                
                // Show blocking reasons
                const blockingDiv = document.getElementById('blockingReasons');
                const blockingList = document.getElementById('blockingList');
                blockingDiv.style.display = 'block';
                blockingList.innerHTML = blockingReasons.map(reason => `<li>${reason}</li>`).join('');
            }
            
            // Update summary stats
            document.getElementById('summarySection').style.display = 'block';
            document.getElementById('totalStudents').textContent = summary.total_students;
            document.getElementById('totalAdvancing').textContent = summary.total_advancing;
            document.getElementById('totalGraduating').textContent = summary.total_graduating;
            
            // Update breakdown
            const breakdown = summary.breakdown;
            document.getElementById('count_1_to_2').textContent = breakdown.advancing_1st_to_2nd;
            document.getElementById('count_2_to_3').textContent = breakdown.advancing_2nd_to_3rd;
            document.getElementById('count_3_to_4').textContent = breakdown.advancing_3rd_to_4th;
            document.getElementById('count_4_to_5').textContent = breakdown.advancing_4th_to_5th;
            document.getElementById('count_grad_4').textContent = breakdown.graduating_4th_year;
            document.getElementById('count_grad_5').textContent = breakdown.graduating_5th_year;
            
            // Course mapping check removed - no longer applicable
            
            // Show warnings if any
            if (data.warnings.length > 0) {
                document.getElementById('warningsBox').style.display = 'block';
                document.getElementById('totalWarnings').textContent = data.warnings.length;
            }
        }
        
        function showModal() {
            if (!previewData) return;
            
            const modalBody = document.getElementById('modalBody');
            const advancing = previewData.students_advancing;
            const graduating = previewData.students_graduating;
            
            let html = '<div class="collapsible-section">';
            
            // Graduating Students
            html += buildStudentSection('Graduating - 4th Year (4-year programs)', graduating['4th_year'], true);
            html += buildStudentSection('Graduating - 5th Year', graduating['5th_year'], true);
            
            // Advancing Students
            html += buildStudentSection('Advancing: 1st → 2nd Year', advancing['1st_to_2nd'], false);
            html += buildStudentSection('Advancing: 2nd → 3rd Year', advancing['2nd_to_3rd'], false);
            html += buildStudentSection('Advancing: 3rd → 4th Year', advancing['3rd_to_4th'], false);
            html += buildStudentSection('Advancing: 4th → 5th Year (5-year programs)', advancing['4th_to_5th'], false);
            
            // Course mapping check removed - no longer applicable
            
            html += '</div>';
            
            modalBody.innerHTML = html;
            
            // Add click handlers for collapsible sections
            document.querySelectorAll('.section-header').forEach(header => {
                header.addEventListener('click', function() {
                    const content = this.nextElementSibling;
                    const icon = this.querySelector('.toggle-icon');
                    
                    content.classList.toggle('active');
                    icon.classList.toggle('rotated');
                });
            });
            
            document.getElementById('previewModal').style.display = 'block';
        }
        
        function buildStudentSection(title, students, isGraduating) {
            if (!students || students.length === 0) return '';
            
            let html = `
                <div class="mb-3">
                    <div class="section-header">
                        <h5>
                            ${isGraduating ? '🎓' : '📚'} ${title}
                            <span class="badge bg-primary">${students.length}</span>
                        </h5>
                        <span class="toggle-icon">▼</span>
                    </div>
                    <div class="section-content">
                        <table class="student-table">
                            <thead>
                                <tr>
                                    <th>Student ID</th>
                                    <th>Name</th>
                                    <th>Course</th>
                                    <th>University</th>
                                    <th>Program Duration</th>
                                    <th>Next Status</th>
                                </tr>
                            </thead>
                            <tbody>
            `;
            
            students.forEach(student => {
                html += `
                    <tr>
                        <td>${student.student_id}</td>
                        <td>${student.name}</td>
                        <td>${student.course || 'N/A'}</td>
                        <td>${student.university || 'N/A'}</td>
                        <td>${student.program_duration ? student.program_duration + ' years' : 'Unknown'}</td>
                        <td>
                            <span class="badge-next-status ${isGraduating ? 'badge-graduating' : 'badge-advancing'}">
                                ${student.next_status}
                            </span>
                        </td>
                    </tr>
                `;
            });
            
            html += `
                            </tbody>
                        </table>
                    </div>
                </div>
            `;
            
            return html;
        }
        
        function closeModal() {
            document.getElementById('previewModal').style.display = 'none';
            document.getElementById('confirmCheckbox').checked = false;
            document.getElementById('adminPassword').value = '';
            document.getElementById('btnExecute').disabled = true;
        }
        
        // Execute year advancement
        document.getElementById('btnExecute').addEventListener('click', function() {
            const adminPassword = document.getElementById('adminPassword').value;
            
            if (!adminPassword) {
                alert('⚠️ Please enter your admin password to proceed.');
                return;
            }
            
            if (!confirm('⚠️ FINAL CONFIRMATION\n\nThis will:\n• Advance ALL active students to the next year level\n• Automatically ARCHIVE graduating students\n• This action CANNOT be easily undone\n\nAre you absolutely sure you want to proceed?')) {
                return;
            }
            
            const btn = this;
            const originalText = btn.innerHTML;
            
            // Disable button and show loading
            btn.disabled = true;
            btn.innerHTML = '<i class="bi bi-hourglass-split"></i> Executing...';
            
            // Prepare form data
            const formData = new FormData();
            formData.append('action', 'execute');
            formData.append('admin_password', adminPassword);
            formData.append('notes', prompt('Optional: Add notes for this advancement (e.g., "End of Academic Year 2024-2025")') || '');
            
            // Execute
            fetch('', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Show success message
                    const successMsg = `✅ SUCCESS!\n\n${data.message}\n\nStudents Advanced: ${data.students_advanced}\nStudents Graduated: ${data.students_graduated}`;
                    
                    // If students were graduated, compress their files
                    if (data.students_graduated > 0) {
                        alert(successMsg + '\n\n⏳ Now compressing archived student files...');
                        
                        // Call compression endpoint
                        fetch('compress_archived_students.php', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json'
                            },
                            body: JSON.stringify({
                                // Let it auto-detect all archived students
                            })
                        })
                        .then(resp => resp.json())
                        .then(compressData => {
                            if (compressData.success) {
                                alert(`📦 File Compression Complete!\n\n` +
                                      `Students Processed: ${compressData.compressed}/${compressData.total_students}\n` +
                                      `Files Archived: ${compressData.total_files}\n` +
                                      `Space Saved: ${compressData.space_saved_mb} MB\n\n` +
                                      `Files are stored in: assets/uploads/archived_students/`);
                            } else {
                                alert(`⚠️ File compression completed with issues:\n${compressData.message}`);
                            }
                            
                            // Close modal and reload
                            closeModal();
                            window.location.reload();
                        })
                        .catch(compressError => {
                            console.error('Compression error:', compressError);
                            alert('⚠️ Year advancement successful, but file compression encountered an error.\nFiles can be compressed manually later.');
                            closeModal();
                            window.location.reload();
                        });
                    } else {
                        // No graduates, just show success and reload
                        alert(successMsg);
                        closeModal();
                        window.location.reload();
                    }
                } else {
                    alert(`❌ ERROR\n\n${data.error || data.message}`);
                    btn.disabled = false;
                    btn.innerHTML = originalText;
                }
            })
            .catch(error => {
                alert('Error executing advancement: ' + error.message);
                btn.disabled = false;
                btn.innerHTML = originalText;
            });
        });
    </script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="../../assets/js/admin/sidebar.js"></script>
</body>
</html>

<?php pg_close($connection); ?>
