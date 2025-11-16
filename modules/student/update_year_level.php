<?php
if (session_status() === PHP_SESSION_NONE) { session_start(); }

require_once __DIR__ . '/../../config/database.php';

// Check if student is logged in
if (!isset($_SESSION['student_id'])) {
    header("Location: ../../unified_login.php");
    exit;
}

$student_id = $_SESSION['student_id'];

// Enforce session timeout via middleware
require_once __DIR__ . '/../../includes/SessionTimeoutMiddleware.php';
$timeoutMiddleware = new SessionTimeoutMiddleware();
$timeoutStatus = $timeoutMiddleware->handle();

$error_message = $_SESSION['error_message'] ?? '';
$success_message = '';
unset($_SESSION['error_message']);

// Get student information
$student_query = pg_query_params($connection,
    "SELECT s.*, 
            s.current_year_level,
            s.is_graduating,
            s.status_academic_year,
            s.last_status_update,
            b.name as barangay_name,
            u.name as university_name,
            yl.name as year_level_name
     FROM students s
     LEFT JOIN barangays b ON s.barangay_id = b.barangay_id
     LEFT JOIN universities u ON s.university_id = u.university_id
     LEFT JOIN year_levels yl ON s.year_level_id = yl.year_level_id
     WHERE s.student_id = $1",
    [$student_id]
);

if (!$student_query || pg_num_rows($student_query) === 0) {
    die("Student not found");
}

$student = pg_fetch_assoc($student_query);

// Get current academic year from database
$current_academic_year = null;
$is_new_academic_year = false;

// First try to get from active slot
$current_ay_query = pg_query($connection, 
    "SELECT academic_year 
     FROM signup_slots 
     WHERE is_active = TRUE 
     LIMIT 1"
);

if ($current_ay_query && pg_num_rows($current_ay_query) > 0) {
    $ay_row = pg_fetch_assoc($current_ay_query);
    $current_academic_year = $ay_row['academic_year'];
}

// If no active slot, check config table for current distribution
if (!$current_academic_year) {
    $config_query = pg_query($connection, "SELECT value FROM config WHERE key = 'current_academic_year'");
    if ($config_query && pg_num_rows($config_query) > 0) {
        $config_row = pg_fetch_assoc($config_query);
        $current_academic_year = $config_row['value'];
    }
}

// Check if this is a new academic year for the student
if ($current_academic_year && !empty($student['status_academic_year']) && $student['status_academic_year'] !== $current_academic_year) {
    $is_new_academic_year = true;
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $year_level = $_POST['year_level'] ?? '';
    $is_graduating = isset($_POST['is_graduating']) ? ($_POST['is_graduating'] === '1') : false;
    $academic_year = $_POST['academic_year'] ?? $current_academic_year;
    
    if (empty($year_level) || empty($academic_year)) {
        $error_message = "Please fill in all required fields.";
    } else {
        // Update student year level credentials
        $update_query = "UPDATE students 
                        SET current_year_level = $1,
                            is_graduating = $2,
                            last_status_update = NOW(),
                            status_academic_year = $3
                        WHERE student_id = $4";
        
        $update_result = pg_query_params($connection, $update_query, [
            $year_level,
            $is_graduating ? 'true' : 'false',
            $academic_year,
            $student_id
        ]);
        
        if ($update_result) {
            // Log the change in student_status_history
            $history_query = "INSERT INTO student_status_history 
                             (student_id, year_level, is_graduating, academic_year, updated_at, update_source, notes)
                             VALUES ($1, $2, $3, $4, NOW(), 'student_self_update', $5)";
            
            pg_query_params($connection, $history_query, [
                $student_id,
                $year_level,
                $is_graduating ? 'true' : 'false',
                $academic_year,
                'Student updated their own year level via update_year_level.php'
            ]);
            
            $success_message = "Your year level information has been updated successfully!";
            
            // Refresh student data
            $student_query = pg_query_params($connection,
                "SELECT current_year_level, is_graduating, status_academic_year 
                 FROM students WHERE student_id = $1",
                [$student_id]
            );
            $student = pg_fetch_assoc($student_query);
            
            // Redirect back to upload documents after 2 seconds
            header("Refresh: 2; url=upload_document.php");
        } else {
            $error_message = "Failed to update year level. Please try again.";
        }
    }
}

// Available year levels
$year_levels = [
    '1st Year',
    '2nd Year',
    '3rd Year',
    '4th Year',
    '5th Year'
];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Update Year Level - EducAid</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            padding: 20px;
        }
        .update-card {
            max-width: 600px;
            margin: 0 auto;
            background: white;
            border-radius: 15px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.3);
            padding: 40px;
        }
        .header-icon {
            font-size: 3rem;
            color: #667eea;
            margin-bottom: 20px;
        }
        .info-box {
            background: #f8f9fa;
            border-left: 4px solid #667eea;
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 5px;
        }
        .graduating-options {
            display: flex;
            gap: 15px;
            margin-top: 10px;
        }
        .graduating-option {
            flex: 1;
            padding: 15px;
            border: 2px solid #dee2e6;
            border-radius: 8px;
            cursor: pointer;
            text-align: center;
            transition: all 0.3s;
        }
        .graduating-option:hover {
            border-color: #667eea;
            background: #f8f9fa;
        }
        .graduating-option input[type="radio"] {
            display: none;
        }
        .graduating-option input[type="radio"]:checked + label {
            color: #667eea;
            font-weight: bold;
        }
        .graduating-option.selected {
            border-color: #667eea;
            background: #e7f1ff;
        }
        .btn-update {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border: none;
            padding: 12px 40px;
            font-weight: bold;
        }
        .btn-update:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(102, 126, 234, 0.4);
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="update-card">
            <div class="text-center">
                <i class="bi bi-person-badge header-icon"></i>
                <h2 class="mb-3">
                    <?php if ($is_new_academic_year): ?>
                        Update Your Year Level for A.Y. <?php echo htmlspecialchars($current_academic_year); ?>
                    <?php else: ?>
                        Update Your Year Level
                    <?php endif; ?>
                </h2>
                <p class="text-muted">
                    <?php if ($is_new_academic_year): ?>
                        A new distribution has started! Your last recorded year level was for A.Y. <strong><?php echo htmlspecialchars($student['status_academic_year']); ?></strong>.
                        Please update your current information for Academic Year <strong><?php echo htmlspecialchars($current_academic_year); ?></strong>.
                    <?php else: ?>
                        Please provide your current year level and graduation status<?php if ($current_academic_year): ?> for Academic Year <?php echo htmlspecialchars($current_academic_year); ?><?php endif; ?>
                    <?php endif; ?>
                </p>
            </div>

            <?php if ($error_message): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <i class="bi bi-exclamation-triangle-fill"></i> <?php echo htmlspecialchars($error_message); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <?php if ($success_message): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <i class="bi bi-check-circle-fill"></i> <?php echo htmlspecialchars($success_message); ?>
                    <p class="mb-0 mt-2"><small>Redirecting to upload documents...</small></p>
                </div>
            <?php endif; ?>

            <div class="info-box">
                <strong><i class="bi bi-info-circle"></i> Why do we need this?</strong>
                <p class="mb-0 mt-2">This information helps us track your academic progress and determine your eligibility for educational assistance in future distributions.</p>
            </div>

            <form method="POST" action="">
                <div class="mb-4">
                    <label for="year_level" class="form-label fw-bold">
                        <i class="bi bi-mortarboard"></i> Current Year Level <span class="text-danger">*</span>
                    </label>
                    <select class="form-select form-select-lg" id="year_level" name="year_level" required>
                        <option value="">-- Select Your Year Level --</option>
                        <?php foreach ($year_levels as $yl): ?>
                            <option value="<?php echo htmlspecialchars($yl); ?>" 
                                <?php echo (isset($student['current_year_level']) && $student['current_year_level'] === $yl) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($yl); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <small class="text-muted">Select the year level you are currently enrolled in</small>
                </div>

                <div class="mb-4">
                    <label class="form-label fw-bold">
                        <i class="bi bi-calendar-check"></i> Graduation Status <span class="text-danger">*</span>
                    </label>
                    <div class="graduating-options">
                        <div class="graduating-option" onclick="selectGraduating(false)">
                            <input type="radio" id="not_graduating" name="is_graduating" value="0" 
                                <?php echo (isset($student['is_graduating']) && $student['is_graduating'] === 'f') ? 'checked' : ''; ?>>
                            <label for="not_graduating" style="cursor: pointer; margin: 0;">
                                <i class="bi bi-arrow-repeat d-block fs-2 mb-2"></i>
                                <strong>Still Continuing</strong>
                                <p class="mb-0 small text-muted">I will continue next year</p>
                            </label>
                        </div>
                        <div class="graduating-option" onclick="selectGraduating(true)">
                            <input type="radio" id="graduating" name="is_graduating" value="1"
                                <?php echo (isset($student['is_graduating']) && $student['is_graduating'] === 't') ? 'checked' : ''; ?>>
                            <label for="graduating" style="cursor: pointer; margin: 0;">
                                <i class="bi bi-trophy d-block fs-2 mb-2"></i>
                                <strong>Graduating</strong>
                                <p class="mb-0 small text-muted">This is my final year</p>
                            </label>
                        </div>
                    </div>
                </div>

                <input type="hidden" name="academic_year" value="<?php echo htmlspecialchars($current_academic_year ?? ''); ?>">

                <div class="d-grid gap-2 mt-4">
                    <button type="submit" class="btn btn-primary btn-lg btn-update">
                        <i class="bi bi-check-circle"></i> Update Year Level
                    </button>
                    <?php if (!empty($student['current_year_level'])): ?>
                        <a href="upload_document.php" class="btn btn-outline-secondary">
                            <i class="bi bi-arrow-left"></i> Back to Upload
                        </a>
                    <?php endif; ?>
                </div>
            </form>

            <?php if (!empty($student['current_year_level'])): ?>
                <div class="mt-4 text-center">
                    <small class="text-muted">
                        Last updated: <?php echo $student['last_status_update'] ? date('F j, Y', strtotime($student['last_status_update'])) : 'Never'; ?>
                    </small>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function selectGraduating(isGraduating) {
            // Remove selected class from all options
            document.querySelectorAll('.graduating-option').forEach(opt => {
                opt.classList.remove('selected');
            });
            
            // Set the radio button
            if (isGraduating) {
                document.getElementById('graduating').checked = true;
                document.getElementById('graduating').parentElement.classList.add('selected');
            } else {
                document.getElementById('not_graduating').checked = true;
                document.getElementById('not_graduating').parentElement.classList.add('selected');
            }
        }
        
        // Set initial selected state
        document.addEventListener('DOMContentLoaded', function() {
            const graduatingRadio = document.getElementById('graduating');
            const notGraduatingRadio = document.getElementById('not_graduating');
            
            if (graduatingRadio.checked) {
                graduatingRadio.parentElement.classList.add('selected');
            } else if (notGraduatingRadio.checked) {
                notGraduatingRadio.parentElement.classList.add('selected');
            }
        });
    </script>
</body>
</html>
