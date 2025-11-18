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

// Require year level update before accessing this page
include __DIR__ . '/../../includes/require_year_level_update.php';

// PHPMailer setup
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
require __DIR__ . '/../../phpmailer/vendor/autoload.php';

// --------- Handle AJAX OTP Requests -----------
// Email Change OTP
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax'])) {
    header('Content-Type: application/json');
    error_log("AJAX OTP Request - Action: " . $_POST['ajax']);
    
    // --- OTP Send ---
    if ($_POST['ajax'] === 'send_otp' && isset($_POST['new_email'])) {
        $newEmail = filter_var($_POST['new_email'], FILTER_SANITIZE_EMAIL);
        error_log("Send OTP - Email: " . $newEmail);
        
        if (!filter_var($newEmail, FILTER_VALIDATE_EMAIL)) {
            error_log("Send OTP - Invalid email format");
            echo json_encode(['status' => 'error', 'message' => 'Invalid email format.']);
            exit;
        }
        // Check if email is used by an admin
        $adminCheck = pg_query_params($connection, "SELECT 1 FROM admins WHERE email = $1", [$newEmail]);
        if (pg_num_rows($adminCheck) > 0) {
            error_log("Send OTP - Email already in use by admin");
            echo json_encode(['status' => 'error', 'message' => 'This email is already in use.']);
            exit;
        }
        // Check if email already used by another active student (exclude current)
        $res = pg_query_params($connection, "SELECT 1 FROM students WHERE email = $1 AND student_id != $2 AND is_archived = FALSE", [$newEmail, $student_id]);
        if (pg_num_rows($res) > 0) {
            error_log("Send OTP - Email already registered by another student");
            echo json_encode(['status' => 'error', 'message' => 'This email is already registered by another active student.']);
            exit;
        }
        $otp = rand(100000, 999999);
        $_SESSION['profile_otp'] = $otp;
        $_SESSION['profile_otp_email'] = $newEmail;
        $_SESSION['profile_otp_time'] = time();
        
        error_log("Send OTP - Generated OTP: " . $otp);

        // PHPMailer send
        $mail = new PHPMailer(true);
        try {
            $mail->isSMTP();
            $mail->Host       = 'smtp.gmail.com';
            $mail->SMTPAuth   = true;
            $mail->Username   = 'dilucayaka02@gmail.com'; // CHANGE
            $mail->Password   = 'jlld eygl hksj flvg';    // CHANGE
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port       = 587;
            $mail->setFrom('dilucayaka02@gmail.com', 'EducAid');
            $mail->addAddress($newEmail);
            $mail->isHTML(true);
            $mail->Subject = 'EducAid Email Change OTP';
            $mail->Body    = "Your One-Time Password (OTP) for updating your EducAid email is: <strong>$otp</strong><br><br>This OTP is valid for 40 seconds.";
            $mail->AltBody = "Your OTP for updating your EducAid email is: $otp. This OTP is valid for 40 seconds.";
            $mail->send();
            error_log("Send OTP - Email sent successfully");
            echo json_encode(['status' => 'success', 'message' => 'OTP sent! Please check your email.']);
        } catch (Exception $e) {
            error_log("Send OTP - Mail error: " . $e->getMessage());
            echo json_encode(['status' => 'error', 'message' => 'Failed to send OTP. Please try again.']);
        }
        exit;
    }

    // --- OTP Verify ---
    if ($_POST['ajax'] === 'verify_otp' && isset($_POST['otp']) && isset($_POST['new_email'])) {
        $enteredOtp = filter_var($_POST['otp'], FILTER_SANITIZE_NUMBER_INT);
        $email = filter_var($_POST['new_email'], FILTER_SANITIZE_EMAIL);
        
        error_log("Verify OTP - Entered OTP: " . $enteredOtp . ", Email: " . $email);

        if (!isset($_SESSION['profile_otp'], $_SESSION['profile_otp_email'], $_SESSION['profile_otp_time'])) {
            error_log("Verify OTP - No session data");
            echo json_encode(['status' => 'error', 'message' => 'No OTP sent or session expired.']);
            exit;
        }
        
        error_log("Verify OTP - Session OTP: " . $_SESSION['profile_otp'] . ", Session Email: " . $_SESSION['profile_otp_email']);
        
        if ($_SESSION['profile_otp_email'] !== $email) {
            error_log("Verify OTP - Email mismatch");
            echo json_encode(['status' => 'error', 'message' => 'Email mismatch.']);
            exit;
        }
        if ((time() - $_SESSION['profile_otp_time']) > 40) {
            error_log("Verify OTP - OTP expired");
            unset($_SESSION['profile_otp'], $_SESSION['profile_otp_email'], $_SESSION['profile_otp_time'], $_SESSION['profile_otp_verified']);
            echo json_encode(['status' => 'error', 'message' => 'OTP expired. Please resend.']);
            exit;
        }
        if ((int)$enteredOtp === (int)$_SESSION['profile_otp']) {
            error_log("Verify OTP - Success");
            $_SESSION['profile_otp_verified'] = true;
            echo json_encode(['status' => 'success', 'message' => 'OTP verified!']);
            unset($_SESSION['profile_otp'], $_SESSION['profile_otp_time']);
        } else {
            error_log("Verify OTP - Invalid OTP");
            $_SESSION['profile_otp_verified'] = false;
            echo json_encode(['status' => 'error', 'message' => 'Invalid OTP.']);
        }
        exit;
    }
}

// --------- Handle AJAX OTP for Change Password -----------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax_pwd'])) {
    header('Content-Type: application/json');
    // --- OTP Send ---
    if ($_POST['ajax_pwd'] === 'send_otp_pwd') {
        $currentPwd = $_POST['current_password'] ?? '';
        // Fetch hashed password
        $pwdRes = pg_query($connection, "SELECT password FROM students WHERE student_id = '" . pg_escape_string($connection, $student_id) . "'");
        $pwdRow = pg_fetch_assoc($pwdRes);

        // Current password must be correct!
        if (!$pwdRow || empty($currentPwd) || !password_verify($currentPwd, $pwdRow['password'])) {
            echo json_encode(['status' => 'error', 'message' => 'Current password is incorrect.', 'target' => 'currentPwdInput']);
            exit;
        }

        // New password (sent by JS) is not same as current
        if (isset($_POST['new_password']) && $currentPwd === $_POST['new_password']) {
            echo json_encode(['status' => 'error', 'message' => 'The password is already in use.', 'target' => 'newPwdInput']);
            exit;
        }

        // Email
        $stuRes = pg_query($connection, "SELECT email FROM students WHERE student_id = '" . pg_escape_string($connection, $student_id) . "'");
        $stu = pg_fetch_assoc($stuRes);
        $email = $stu['email'];
        if (!$email) {
            echo json_encode(['status' => 'error', 'message' => 'No email found.']);
            exit;
        }
        $otp = rand(100000, 999999);
        $_SESSION['change_pwd_otp'] = $otp;
        $_SESSION['change_pwd_otp_time'] = time();

        $mail = new PHPMailer(true);
        try {
            $mail->isSMTP();
            $mail->Host       = 'smtp.gmail.com';
            $mail->SMTPAuth   = true;
            $mail->Username   = 'dilucayaka02@gmail.com'; // CHANGE
            $mail->Password   = 'jlld eygl hksj flvg';    // CHANGE
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port       = 587;
            $mail->setFrom('dilucayaka02@gmail.com', 'EducAid');
            $mail->addAddress($email);
            $mail->isHTML(true);
            $mail->Subject = 'EducAid Change Password OTP';
            $mail->Body    = "Your One-Time Password (OTP) for changing your EducAid password is: <strong>$otp</strong><br><br>This OTP is valid for 40 seconds.";
            $mail->AltBody = "Your OTP for changing your EducAid password is: $otp. This OTP is valid for 40 seconds.";
            $mail->send();
            echo json_encode(['status' => 'success', 'message' => 'OTP sent! Please check your email.']);
        } catch (Exception $e) {
            echo json_encode(['status' => 'error', 'message' => 'Failed to send OTP. Please try again.']);
        }
        exit;
    }

    // --- OTP Verify ---
    if ($_POST['ajax_pwd'] === 'verify_otp_pwd' && isset($_POST['otp'])) {
        $enteredOtp = filter_var($_POST['otp'], FILTER_SANITIZE_NUMBER_INT);
        if (!isset($_SESSION['change_pwd_otp'], $_SESSION['change_pwd_otp_time'])) {
            echo json_encode(['status' => 'error', 'message' => 'No OTP sent or session expired.', 'target' => 'otpPwdInput']);
            exit;
        }
        if ((time() - $_SESSION['change_pwd_otp_time']) > 40) {
            unset($_SESSION['change_pwd_otp'], $_SESSION['change_pwd_otp_time'], $_SESSION['change_pwd_otp_verified']);
            echo json_encode(['status' => 'error', 'message' => 'OTP expired. Please resend.', 'target' => 'otpPwdInput']);
            exit;
        }
        if ((int)$enteredOtp === (int)$_SESSION['change_pwd_otp']) {
            $_SESSION['change_pwd_otp_verified'] = true;
            echo json_encode(['status' => 'success', 'message' => 'OTP verified!']);
            unset($_SESSION['change_pwd_otp'], $_SESSION['change_pwd_otp_time']);
        } else {
            $_SESSION['change_pwd_otp_verified'] = false;
            echo json_encode(['status' => 'error', 'message' => 'Invalid OTP.', 'target' => 'otpPwdInput']);
        }
        exit;
    }
}

// --------- Handle Profile Update (Email) ----------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_email'])) {
    $newEmail = filter_var($_POST['new_email'], FILTER_SANITIZE_EMAIL);

    // Require OTP verification for email change
    if (!isset($_SESSION['profile_otp_verified']) || $_SESSION['profile_otp_verified'] !== true || $_SESSION['profile_otp_email'] !== $newEmail) {
        $_SESSION['profile_flash'] = 'Please complete OTP verification for this email.';
        $_SESSION['profile_flash_type'] = 'error';
        header("Location: student_settings.php");
        exit;
    }

    if (filter_var($newEmail, FILTER_VALIDATE_EMAIL)) {
        // Check if email is used by an admin
        $adminCheck = pg_query_params($connection, "SELECT 1 FROM admins WHERE email = $1", [$newEmail]);
        if (pg_num_rows($adminCheck) > 0) {
            $_SESSION['profile_flash'] = 'This email is already in use.';
            $_SESSION['profile_flash_type'] = 'error';
            unset($_SESSION['profile_otp_email'], $_SESSION['profile_otp_verified']);
            header("Location: student_settings.php");
            exit;
        }
        
        try {
            $updateResult = pg_query($connection, "UPDATE students SET email = '" . pg_escape_string($connection, $newEmail) . "' WHERE student_id = '" . pg_escape_string($connection, $student_id) . "'");
            
            if (!$updateResult) {
                error_log("Email update failed for student $student_id: " . pg_last_error($connection));
                $_SESSION['profile_flash'] = 'Failed to update email. Please try again.';
                $_SESSION['profile_flash_type'] = 'error';
                unset($_SESSION['profile_otp_email'], $_SESSION['profile_otp_verified']);
                header("Location: student_settings.php");
                exit;
            }
            
            $affectedRows = pg_affected_rows($updateResult);
            if ($affectedRows === 0) {
                error_log("Email update affected 0 rows for student $student_id");
                $_SESSION['profile_flash'] = 'Failed to update email. Student not found.';
                $_SESSION['profile_flash_type'] = 'error';
                unset($_SESSION['profile_otp_email'], $_SESSION['profile_otp_verified']);
                header("Location: student_settings.php");
                exit;
            }
            
            $msg = 'Your email has been changed to ' . $newEmail . '.';
            pg_query($connection, "INSERT INTO notifications (student_id, message) VALUES ('" . pg_escape_string($connection, $student_id) . "', '" . pg_escape_string($connection, $msg) . "')");
            $nameRes = pg_query($connection, "SELECT first_name, last_name FROM students WHERE student_id = '" . pg_escape_string($connection, $student_id) . "'");
            $nm = pg_fetch_assoc($nameRes);
            $adminMsg = $nm['first_name'] . ' ' . $nm['last_name'] . ' (' . $student_id . ') updated email to ' . $newEmail . '.';
            pg_query($connection, "INSERT INTO admin_notifications (message) VALUES ('" . pg_escape_string($connection,$adminMsg) . "')");
            $_SESSION['profile_flash'] = 'Email updated successfully.';
            $_SESSION['profile_flash_type'] = 'success';
            unset($_SESSION['profile_otp_email'], $_SESSION['profile_otp_verified']);
        } catch (Exception $e) {
            // Catch database constraint violations
            if (strpos($e->getMessage(), 'unique') !== false || strpos($e->getMessage(), 'duplicate') !== false) {
                $_SESSION['profile_flash'] = 'This email is already registered by another active student.';
            } else {
                $_SESSION['profile_flash'] = 'Failed to update email. Please try again.';
            }
            $_SESSION['profile_flash_type'] = 'error';
        }
    }
    header("Location: student_settings.php"); exit;
}

// --------- Handle Mobile Number Update ----------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_mobile'])) {
    $newMobile = preg_replace('/\D/', '', $_POST['new_mobile']);
    if ($newMobile) {
        // Check if mobile already used by another active student (exclude current)
        $checkRes = pg_query_params($connection, "SELECT 1 FROM students WHERE mobile = $1 AND student_id != $2 AND is_archived = FALSE", [$newMobile, $student_id]);
        if (pg_num_rows($checkRes) > 0) {
            $_SESSION['profile_flash'] = 'This mobile number is already registered by another active student.';
            $_SESSION['profile_flash_type'] = 'error';
            header("Location: student_settings.php");
            exit;
        }

        try {
            pg_query($connection, "UPDATE students SET mobile = '" . pg_escape_string($connection, $newMobile) . "' WHERE student_id = '" . pg_escape_string($connection, $student_id) . "'");
            $msg = 'Your mobile number has been changed to ' . $newMobile . '.';
            pg_query($connection, "INSERT INTO notifications (student_id, message) VALUES ('" . pg_escape_string($connection, $student_id) . "', '" . pg_escape_string($connection, $msg) . "')");
            $nameRes = pg_query($connection, "SELECT first_name, last_name FROM students WHERE student_id = '" . pg_escape_string($connection, $student_id) . "'");
            $nm = pg_fetch_assoc($nameRes);
            $adminMsg = $nm['first_name'] . ' ' . $nm['last_name'] . ' (' . $student_id . ') updated mobile to ' . $newMobile . '.';
            pg_query($connection, "INSERT INTO admin_notifications (message) VALUES ('" . pg_escape_string($connection,$adminMsg) . "')");
            $_SESSION['profile_flash'] = 'Mobile number updated successfully.';
            $_SESSION['profile_flash_type'] = 'success';
        } catch (Exception $e) {
            // Catch database constraint violations
            if (strpos($e->getMessage(), 'unique') !== false || strpos($e->getMessage(), 'duplicate') !== false) {
                $_SESSION['profile_flash'] = 'This mobile number is already registered by another active student.';
            } else {
                $_SESSION['profile_flash'] = 'Failed to update mobile number. Please try again.';
            }
            $_SESSION['profile_flash_type'] = 'error';
        }
    }
    header("Location: student_settings.php"); exit;
}

// --------- Handle Update Mother's Maiden Name Submission ----------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_mothers_maiden_name'])) {
    $mothers_maiden_name = trim($_POST['mothers_maiden_name'] ?? '');
    
    // Validate input
    if (empty($mothers_maiden_name)) {
        $_SESSION['profile_flash'] = 'Mother\'s maiden name cannot be empty.';
        $_SESSION['profile_flash_type'] = 'error';
        header("Location: student_settings.php");
        exit;
    }
    
    // Validate format (only letters, spaces, hyphens, apostrophes)
    if (!preg_match("/^[A-Za-zÑñ\s\-\']+$/", $mothers_maiden_name)) {
        $_SESSION['profile_flash'] = 'Mother\'s maiden name can only contain letters, spaces, hyphens, and apostrophes.';
        $_SESSION['profile_flash_type'] = 'error';
        header("Location: student_settings.php");
        exit;
    }
    
    // Capitalize properly
    $mothers_maiden_name = ucwords(strtolower($mothers_maiden_name));
    
    // Check if same as student's surname (requires admin review)
    $studentRes = pg_query_params($connection, "SELECT last_name FROM students WHERE student_id = $1", [$student_id]);
    $studentRow = pg_fetch_assoc($studentRes);
    $admin_review_required = false;
    
    if ($studentRow && strcasecmp($mothers_maiden_name, $studentRow['last_name']) === 0) {
        $admin_review_required = true;
    }
    
    // Update the database
    $updateQuery = "UPDATE students SET mothers_maiden_name = $1, admin_review_required = $2 WHERE student_id = $3";
    $result = pg_query_params($connection, $updateQuery, [$mothers_maiden_name, $admin_review_required ? 'true' : 'false', $student_id]);
    
    if ($result) {
        $successMsg = $admin_review_required 
            ? 'Mother\'s maiden name updated successfully. Note: Your profile has been flagged for admin review since the maiden name matches your surname.'
            : 'Mother\'s maiden name updated successfully.';
        
        $_SESSION['profile_flash'] = $successMsg;
        $_SESSION['profile_flash_type'] = 'success';
        
        // Send notification
        $notifMsg = 'Your mother\'s maiden name has been updated in your profile.';
        pg_query_params($connection, "INSERT INTO notifications (student_id, message) VALUES ($1, $2)", [$student_id, $notifMsg]);
    } else {
        $_SESSION['profile_flash'] = 'Failed to update mother\'s maiden name. Please try again.';
        $_SESSION['profile_flash_type'] = 'error';
    }
    
    header("Location: student_settings.php#account");
    exit;
}

// --------- Handle Change Password Submission ----------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_password'])) {
    $currentPwd = $_POST['current_password'] ?? '';
    $newPwd = $_POST['new_password'] ?? '';
    $confirmPwd = $_POST['confirm_password'] ?? '';

    // Must verify OTP
    if (!isset($_SESSION['change_pwd_otp_verified']) || $_SESSION['change_pwd_otp_verified'] !== true) {
        $_SESSION['profile_flash'] = 'Please complete OTP verification before changing your password.';
        $_SESSION['profile_flash_type'] = 'error';
        header("Location: student_settings.php");
        exit;
    }

    // Validate passwords
    if (strlen($newPwd) < 12) {
        $_SESSION['profile_flash'] = 'Password must be at least 12 characters.';
        $_SESSION['profile_flash_type'] = 'error';
        header("Location: student_settings.php");
        exit;
    }
    // Check password complexity
    if (!preg_match('/[A-Z]/', $newPwd) || !preg_match('/[a-z]/', $newPwd) || 
        !preg_match('/[0-9]/', $newPwd) || !preg_match('/[^A-Za-z0-9]/', $newPwd)) {
        $_SESSION['profile_flash'] = 'Password must contain uppercase, lowercase, numbers, and special characters.';
        $_SESSION['profile_flash_type'] = 'error';
        header("Location: student_settings.php");
        exit;
    }
    if ($newPwd !== $confirmPwd) {
        $_SESSION['profile_flash'] = 'Passwords do not match.';
        $_SESSION['profile_flash_type'] = 'error';
        header("Location: student_settings.php");
        exit;
    }
    if ($currentPwd === $newPwd) {
        $_SESSION['profile_flash'] = 'The password is already in use.';
        $_SESSION['profile_flash_type'] = 'error';
        header("Location: student_settings.php");
        exit;
    }

    // Check old password
    $pwdRes = pg_query($connection, "SELECT password FROM students WHERE student_id = '" . pg_escape_string($connection, $student_id) . "'");
    $pwdRow = pg_fetch_assoc($pwdRes);
    if (!$pwdRow || !password_verify($currentPwd, $pwdRow['password'])) {
        $_SESSION['profile_flash'] = 'Current password is incorrect.';
        $_SESSION['profile_flash_type'] = 'error';
        header("Location: student_settings.php");
        exit;
    }

    $hashed = password_hash($newPwd, PASSWORD_ARGON2ID);
    pg_query($connection, "UPDATE students SET password = '" . pg_escape_string($connection, $hashed) . "' WHERE student_id = '" . pg_escape_string($connection, $student_id) . "'");
    $msg = 'Your password was changed successfully.';
    pg_query($connection, "INSERT INTO notifications (student_id, message) VALUES ('" . pg_escape_string($connection, $student_id) . "', '" . pg_escape_string($connection, $msg) . "')");
    $_SESSION['profile_flash'] = 'Password changed successfully.';
    $_SESSION['profile_flash_type'] = 'success';
    unset($_SESSION['change_pwd_otp_verified']);
    header("Location: student_settings.php");
    exit;
}

// Fetch student data
$stuRes = pg_query($connection, "SELECT first_name, middle_name, last_name, mothers_maiden_name, bdate, email, mobile FROM students WHERE student_id = '" . pg_escape_string($connection, $student_id) . "'");
$student = pg_fetch_assoc($stuRes);

// Get student info for header dropdown
$student_info_query = "SELECT first_name, last_name FROM students WHERE student_id = $1";
$student_info_result = pg_query_params($connection, $student_info_query, [$student_id]);
$student_info = pg_fetch_assoc($student_info_result);

// Flash message
$flash = $_SESSION['profile_flash'] ?? '';
$flash_type = $_SESSION['profile_flash_type'] ?? '';
unset($_SESSION['profile_flash'], $_SESSION['profile_flash_type']);
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Settings - EducAid</title>
  
  <!-- Critical CSS for FOUC prevention -->
  <style>
    body { opacity: 0; transition: opacity 0.3s ease; background: #f5f5f5; }
    body.ready { opacity: 1; }
    body:not(.ready) .sidebar { visibility: hidden; }
  </style>
  
  <link href="../../assets/css/bootstrap.min.css" rel="stylesheet" />
  <link href="../../assets/css/bootstrap-icons.css" rel="stylesheet" />
  <link rel="stylesheet" href="../../assets/css/student/homepage.css" />
  <link rel="stylesheet" href="../../assets/css/student/sidebar.css" />
  <link rel="stylesheet" href="../../assets/css/student/accessibility.css" />
  <script src="../../assets/js/student/accessibility.js"></script>
  <style>
    .verified-indicator { color: #28a745; font-weight: bold; }
    .form-error { 
      color: #dc3545; 
      font-size: 0.875rem; 
      font-weight: 500;
      padding: 0.5rem 0.75rem;
      background: #f8d7da;
      border-radius: 6px;
      border-left: 3px solid #dc3545;
    }
    .form-success { 
      color: #28a745; 
      font-size: 0.875rem; 
      font-weight: 500;
      padding: 0.5rem 0.75rem;
      background: #d4edda;
      border-radius: 6px;
      border-left: 3px solid #28a745;
    }
    .home-section { padding-top: 0 !important; }
    /* Hide inline error badges inside password modal (progress bar already provides feedback) */
    #passwordModal .form-error { display: none !important; }
    .home-section > .main-header:first-child { margin-top: 0 !important; }
    
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
      scroll-margin-top: 100px;
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
    
    /* Settings Header */
    .settings-header-old {
      background: white;
      border-bottom: 1px solid #e9ecef;
      padding: 1.5rem 0;
      margin-bottom: 2rem;
    }
    
    .settings-header h1 {
      color: #1a202c;
      font-weight: 700;
      font-size: 2rem;
      margin: 0;
    }
    
    .settings-header p {
      color: #718096;
      margin: 0.5rem 0 0 0;
      font-size: 1.1rem;
    }
    
    .back-btn {
      background: #f7fafc;
      border: 1px solid #e2e8f0;
      color: #4a5568;
      padding: 0.5rem 1rem;
      border-radius: 8px;
      text-decoration: none;
      display: inline-flex;
      align-items: center;
      gap: 0.5rem;
      font-weight: 500;
      transition: all 0.2s ease;
    }
    
    .back-btn:hover {
      background: #edf2f7;
      color: #2d3748;
      text-decoration: none;
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
    
    .settings-section-header {
      background: #f7fafc;
      padding: 1.5rem;
      border-bottom: 1px solid #e2e8f0;
    }
    
    .settings-section-header h3 {
      color: #2d3748;
      font-weight: 600;
      font-size: 1.25rem;
      margin: 0;
      display: flex;
      align-items: center;
      gap: 0.75rem;
    }
    
    .settings-section-header p {
      color: #718096;
      margin: 0.5rem 0 0 0;
      font-size: 0.95rem;
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
      transition: all 0.2s ease;
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
    
    .btn-setting-danger {
      background: #e53e3e;
      color: white;
      border-color: #e53e3e;
    }
    
    .btn-setting-danger:hover {
      background: #c53030;
      border-color: #c53030;
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
    
    /* Modal Improvements */
    .modal-content {
      border-radius: 16px;
      border: none;
      box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
    }
    
    .modal-header {
      background: #f7fafc;
      border-bottom: 1px solid #e2e8f0;
      border-radius: 16px 16px 0 0;
      padding: 1.5rem 2rem;
    }
    
    .modal-title {
      font-weight: 600;
      color: #2d3748;
      font-size: 1.25rem;
    }
    
    .modal-body {
      padding: 2rem;
    }
    
    .modal-footer {
      border-top: 1px solid #e2e8f0;
      padding: 1.5rem 2rem;
      background: #f7fafc;
      border-radius: 0 0 16px 16px;
    }
    
    /* Ensure modals appear above sidebar/backdrops */
    .modal { z-index: 1100 !important; position: fixed; }
    .modal-backdrop { z-index: 1095 !important; }

    /* When any Bootstrap modal is open, neutralize potential stacking-context creators */
    body.modal-open .home-section { transform: none !important; filter: none !important; }
    body.modal-open .home-section * { transform: none !important; }

    /* Prevent the student sidebar overlay from intercepting clicks while a modal is open */
    body.modal-open .sidebar-backdrop { pointer-events: none !important; opacity: 0 !important; z-index: 0 !important; }
    
    /* Mobile Responsiveness: Compact modal styling (match manage_applicants) */
    @media (max-width: 767px) {
      .modal-dialog { 
        max-width: 90% !important; 
        margin: 1.5rem auto !important; 
      }
      
      .modal-content { 
        height: auto !important; 
        max-height: 85vh !important; 
        border-radius: 1rem !important; 
        display: flex; 
        flex-direction: column; 
      }
      
      .modal-header { 
        padding: 0.75rem 1rem; 
        position: sticky; 
        top: 0; 
        background: #f8f9fa; 
        z-index: 1; 
        border-radius: 1rem 1rem 0 0; 
        border-bottom: 1px solid #e0e0e0; 
      }
      
      .modal-body { 
        padding: 0.75rem 1rem; 
        overflow-y: auto; 
        max-height: calc(85vh - 140px); 
        flex: 1; 
      }
      
      .modal-footer { 
        padding: 0.75rem 1rem; 
        position: sticky; 
        bottom: 0; 
        background: #fff; 
        z-index: 1; 
        border-top: 1px solid #e0e0e0; 
        flex-shrink: 0; 
      }
      
      .modal-title { 
        font-size: 1rem; 
        line-height: 1.3; 
      }
      
      .modal-title i { 
        font-size: 0.9rem; 
      }
      
      .alert {
        padding: 0.5rem 0.75rem;
        margin-bottom: 0.75rem;
        font-size: 0.85rem;
      }
      
      .form-label {
        font-size: 0.9rem;
        margin-bottom: 0.4rem;
      }
      
      .form-control {
        padding: 0.6rem 0.75rem;
        font-size: 0.9rem;
      }
      
      .btn {
        padding: 0.5rem 0.75rem;
        font-size: 0.9rem;
      }
    }
    
    /* Form Styling */
    .form-control {
      border-radius: 8px;
      border: 1px solid #e2e8f0;
      padding: 0.75rem 1rem;
      font-size: 0.95rem;
      transition: all 0.2s ease;
    }
    
    .form-control:focus {
      border-color: #4299e1;
      box-shadow: 0 0 0 3px rgba(66, 153, 225, 0.1);
    }
    
    .form-label {
      font-weight: 600;
      color: #2d3748;
      margin-bottom: 0.5rem;
    }
    
    .alert {
      border-radius: 12px;
      padding: 1rem 1.25rem;
      margin-bottom: 1.5rem;
      border: none;
    }
    
    .alert-success {
      background: #c6f6d5;
      color: #22543d;
    }
    
    .alert-danger {
      background: #fed7d7;
      color: #742a2a;
    }
    
    .alert-info {
      background: #bee3f8;
      color: #2a4365;
    }
    
    .alert-warning {
      background: #faf089;
      color: #744210;
    }
    
    /* Responsive */
    @media (max-width: 768px) {
      .settings-header {
        padding: 1rem 0;
      }
      
      .settings-header h1 {
        font-size: 1.5rem;
      }
      
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

    /* Active Sessions Styling */
    .section-header {
      background: #f7fafc;
      padding: 1.5rem;
      border-bottom: 1px solid #e2e8f0;
    }

    .section-header h2 {
      color: #2d3748;
      font-weight: 600;
      font-size: 1.25rem;
      margin: 0;
      display: flex;
      align-items: center;
    }

    .section-header p {
      color: #718096;
      margin: 0.5rem 0 0 0;
      font-size: 0.95rem;
    }

    .section-content {
      padding: 1.5rem;
    }

    .active-sessions-list {
      display: flex;
      flex-direction: column;
      gap: 1rem;
    }

    .session-item {
      display: flex;
      align-items: center;
      gap: 1rem;
      padding: 1rem;
      border: 1px solid #e2e8f0;
      border-radius: 10px;
      background: #ffffff;
      transition: all 0.2s ease;
    }

    .session-item:hover {
      border-color: #cbd5e0;
      box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
    }

    .session-item.current-session {
      background: #f0fdf4;
      border-color: #86efac;
    }

    .session-icon {
      flex-shrink: 0;
      width: 48px;
      height: 48px;
      display: flex;
      align-items: center;
      justify-content: center;
      background: #f7fafc;
      border-radius: 10px;
      color: #4a5568;
      font-size: 1.5rem;
    }

    .current-session .session-icon {
      background: #dcfce7;
      color: #16a34a;
    }

    .session-details {
      flex: 1;
      min-width: 0;
    }

    .session-device {
      color: #2d3748;
      font-size: 0.95rem;
      margin-bottom: 0.25rem;
      display: flex;
      align-items: center;
      flex-wrap: wrap;
      gap: 0.5rem;
    }

    .session-meta {
      font-size: 0.85rem;
      color: #718096;
    }

    .session-action {
      flex-shrink: 0;
    }

    @media (max-width: 576px) {
      .section-content {
        padding: 1rem;
      }

      .session-item {
        flex-direction: column;
        align-items: flex-start;
        gap: 0.75rem;
      }

      .session-icon {
        width: 40px;
        height: 40px;
        font-size: 1.25rem;
      }

      .session-device {
        font-size: 0.9rem;
      }

      .session-meta {
        font-size: 0.8rem;
      }

      .session-action {
        width: 100%;
      }

      .session-action .btn {
        width: 100%;
      }
    }

    /* Login History Styling */
    .login-history-list {
      display: flex;
      flex-direction: column;
      gap: 0.75rem;
    }

    .history-item {
      display: flex;
      align-items: flex-start;
      gap: 1rem;
      padding: 1rem;
      border: 1px solid #e2e8f0;
      border-radius: 8px;
      background: #ffffff;
      transition: all 0.2s ease;
    }

    .history-item:hover {
      border-color: #cbd5e0;
      box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
    }

    .history-item.failed {
      background: #fef2f2;
      border-color: #fecaca;
    }

    .history-icon {
      flex-shrink: 0;
      font-size: 1.5rem;
      padding-top: 0.25rem;
    }

    .history-details {
      flex: 1;
      min-width: 0;
    }

    .history-status {
      color: #2d3748;
      font-size: 0.95rem;
      margin-bottom: 0.25rem;
      display: flex;
      align-items: center;
      flex-wrap: wrap;
    }

    .history-meta {
      font-size: 0.85rem;
      color: #718096;
      line-height: 1.5;
    }

    @media (max-width: 576px) {
      .history-item {
        padding: 0.75rem;
      }

      .history-icon {
        font-size: 1.25rem;
      }

      .history-status {
        font-size: 0.9rem;
      }

      .history-meta {
        font-size: 0.8rem;
      }

      .history-meta .mx-2 {
        display: none;
      }

      .history-meta small {
        display: flex;
        flex-direction: column;
        gap: 0.25rem;
      }
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
      background: #000 !important;
      border-color: #FFFF00 !important;
      color: #FFFF00 !important;
    }

    /* Inline settings nav items - high contrast overrides */
    html.high-contrast .settings-nav-item {
      background: #000 !important;
      color: #FFFF00 !important;
      border: 2px solid transparent !important;
    }

    html.high-contrast .settings-nav-item:hover,
    html.high-contrast .settings-nav-item:focus-visible {
      background: #1a1a00 !important;
      color: #FFFF00 !important;
      border-color: #FFFF00 !important;
      outline: 3px solid #FFFF00 !important;
      outline-offset: 2px !important;
    }

    html.high-contrast .settings-nav-item.active,
    html.high-contrast .settings-nav-item.active:hover {
      background: #1a1a00 !important;
      color: #FFFF00 !important;
      border: 2px solid #FFFF00 !important;
      font-weight: 700 !important;
    }

    html.high-contrast .session-item,
    html.high-contrast .history-item {
      background: #222 !important;
      border-color: #555 !important;
      color: #fff !important;
    }

    html.high-contrast .btn {
      border: 2px solid #fff !important;
      font-weight: 600 !important;
    }

    /* Reduce Animations */
    html.reduce-animations *,
    html.reduce-animations *::before,
    html.reduce-animations *::after {
      animation-duration: 0.01ms !important;
      animation-iteration-count: 1 !important;
      transition-duration: 0.01ms !important;
      scroll-behavior: auto !important;
    }

    /* Toggle Switch Styling */
    .form-check-input:checked {
      background-color: #4299e1;
      border-color: #4299e1;
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
        <div class="settings-header mb-4 d-flex justify-content-between align-items-center">
          <div>
            <h1 class="mb-1">Settings</h1>
          </div>
          <a href="student_homepage.php" class="back-btn">
            <i class="bi bi-arrow-left me-2"></i>Back to Profile
          </a>
        </div>

        <!-- Flash Messages -->
        <?php if ($flash): ?>
          <div class="alert alert-<?php echo $flash_type === 'success' ? 'success' : 'danger'; ?> alert-dismissible fade show" role="alert">
            <i class="bi bi-<?php echo $flash_type === 'success' ? 'check-circle' : 'exclamation-triangle'; ?> me-2"></i>
            <?php echo htmlspecialchars($flash); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
          </div>
        <?php endif; ?>

        <!-- YouTube-style Layout: Sidebar + Content -->
        <div class="row g-4">
          <!-- Settings Navigation Sidebar -->
          <?php include __DIR__ . '/../../includes/student/settings_sidebar.php'; ?>

          <!-- Settings Content -->
          <div class="col-12 col-lg-9">
            <!-- Account Information Section -->
            <div class="settings-content-section" id="account">
              <h2 class="section-title">Account</h2>
              <p class="section-description">Your basic account details and contact information</p>
              
              <div class="settings-section">
                <div class="settings-section-body">
                  <!-- Account Information -->
                  <div class="setting-item">
                    <div class="setting-info">
                      <div class="setting-label">Full Name</div>
                      <div class="setting-value"><?php echo htmlspecialchars($student['last_name'] . ', ' . $student['first_name'] . ' ' . $student['middle_name']); ?></div>
                      <div class="setting-description">Your registered name with the institution</div>
                    </div>
                    <div class="setting-actions">
                      <span class="text-muted small">Read-only</span>
                    </div>
                  </div>
                  
                  <div class="setting-item <?php echo empty($student['mothers_maiden_name']) ? 'border-warning' : ''; ?>">
                    <div class="setting-info">
                      <div class="setting-label">
                        Mother's Maiden Name 
                        <?php if (empty($student['mothers_maiden_name'])): ?>
                          <span class="badge bg-warning text-dark ms-2">Required</span>
                        <?php endif; ?>
                      </div>
                      <div class="setting-value">
                        <?php if (!empty($student['mothers_maiden_name'])): ?>
                          <?php echo htmlspecialchars($student['mothers_maiden_name']); ?>
                        <?php else: ?>
                          <span class="text-muted">Not provided</span>
                        <?php endif; ?>
                      </div>
                      <div class="setting-description">
                        <i class="bi bi-shield-check me-1"></i>
                        Mother's surname before marriage - helps prevent household duplicate registrations
                      </div>
                    </div>
                    <div class="setting-actions">
                      <button type="button" class="btn btn-sm <?php echo empty($student['mothers_maiden_name']) ? 'btn-warning' : 'btn-outline-primary'; ?>" 
                              data-bs-toggle="modal" data-bs-target="#updateMothersMaidenNameModal">
                        <i class="bi bi-pencil me-1"></i><?php echo empty($student['mothers_maiden_name']) ? 'Add' : 'Edit'; ?>
                      </button>
                    </div>
                  </div>
                  
                  <div class="setting-item">
                    <div class="setting-info">
                      <div class="setting-label">Date of Birth</div>
                      <div class="setting-value"><?php echo htmlspecialchars(date('F j, Y', strtotime($student['bdate']))); ?></div>
                      <div class="setting-description">Your birth date as registered</div>
                    </div>
                    <div class="setting-actions">
                      <span class="text-muted small">Read-only</span>
                    </div>
                  </div>
                  
                  <div class="setting-item">
                    <div class="setting-info">
                      <div class="setting-label">Student ID</div>
                      <div class="setting-value"><?php echo htmlspecialchars($student_id); ?></div>
                      <div class="setting-description">Your unique student identification number</div>
                    </div>
                    <div class="setting-actions">
                      <span class="text-muted small">Read-only</span>
                    </div>
                  </div>

                  <!-- Contact Information (combined in Account section) -->
                  <div class="setting-item" id="email">
                    <div class="setting-info">
                      <div class="setting-label">Email Address</div>
                      <div class="setting-value"><?php echo htmlspecialchars($student['email']); ?></div>
                      <div class="setting-description">Used for notifications and account recovery</div>
                    </div>
                    <div class="setting-actions">
                      <button class="btn btn-setting btn-setting-primary" data-bs-toggle="modal" data-bs-target="#emailModal">
                        <i class="bi bi-pencil me-1"></i>Change Email
                      </button>
                    </div>
                  </div>
                  
                  <div class="setting-item" id="mobile">
                    <div class="setting-info">
                      <div class="setting-label">Mobile Number</div>
                      <div class="setting-value"><?php echo htmlspecialchars($student['mobile']); ?></div>
                      <div class="setting-description">For SMS notifications and contact purposes</div>
                    </div>
                    <div class="setting-actions">
                      <button class="btn btn-setting btn-setting-primary" data-bs-toggle="modal" data-bs-target="#mobileModal">
                        <i class="bi bi-pencil me-1"></i>Change Number
                      </button>
                    </div>
                  </div>
                </div>
              </div>
            </div>

            <!-- Security & Privacy Section moved to dedicated page: modules/student/security_privacy.php -->
            <div class="settings-content-section">
              <h2 class="section-title">Security & Privacy</h2>
              <p class="section-description">This section has been moved to a dedicated page for clarity. <a href="security_privacy.php">Open Security & Privacy</a></p>
            </div>

            <!-- Privacy & Data and Notification Preferences sections removed per user request -->

            <!-- Modals (same as before but updated redirects) -->
            <!-- Email Modal with OTP -->
            <div class="modal fade" id="emailModal" tabindex="-1" data-bs-backdrop="static" data-bs-keyboard="false">
              <div class="modal-dialog modal-dialog-centered">
                <form id="emailUpdateForm" method="POST" class="modal-content">
                  <div class="modal-header">
                <h5 class="modal-title">
                  <i class="bi bi-envelope me-2"></i>Update Email Address
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
              </div>
              <div class="modal-body">
                <div class="mb-3">
                  <label class="form-label">New Email Address</label>
                  <input type="email" name="new_email" id="newEmailInput" class="form-control" 
                         placeholder="Enter your new email address" required>
                  <div id="emailOtpStatus" class="mt-2"></div>
                </div>
                <div id="otpSection" style="display:none;">
                  <div class="alert alert-info">
                    <i class="bi bi-info-circle me-2"></i>
                    We've sent a verification code to your new email address. Please check your inbox.
                  </div>
                  <div class="mb-3 position-relative">
                    <label class="form-label">Enter Verification Code</label>
                    <input type="text" id="otpInput" class="form-control" maxlength="6" 
                           placeholder="6-digit code" autocomplete="off">
                    <span id="otpInputError" class="form-error position-absolute" style="right:15px;top:35px;"></span>
                  </div>
                  <button type="button" class="btn btn-info w-100 mb-2" id="verifyOtpBtn">
                    <i class="bi bi-check2-circle me-2"></i>Verify Code
                  </button>
                  <div id="otpTimer" class="text-danger mt-2"></div>
                  <button type="button" class="btn btn-link w-100" id="resendOtpBtn" style="display:none;">
                    <i class="bi bi-arrow-clockwise me-2"></i>Resend Code
                  </button>
                </div>
              </div>
              <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" id="sendOtpBtn" class="btn btn-primary">
                  <i class="bi bi-send me-2"></i>Send Verification Code
                </button>
                <button type="submit" name="update_email" id="saveEmailBtn" class="btn btn-success" style="display:none;">
                  <i class="bi bi-save me-2"></i>Update Email
                </button>
              </div>
            </form>
          </div>
        </div>

        <!-- Mobile Modal -->
        <div class="modal fade" id="mobileModal" tabindex="-1" data-bs-backdrop="static" data-bs-keyboard="false">
          <div class="modal-dialog modal-dialog-centered">
            <form method="POST" class="modal-content">
              <div class="modal-header">
                <h5 class="modal-title">
                  <i class="bi bi-phone me-2"></i>Update Mobile Number
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
              </div>
              <div class="modal-body">
                <div class="mb-3">
                  <label class="form-label">Mobile Number</label>
                  <input type="text" name="new_mobile" class="form-control" 
                         value="<?php echo htmlspecialchars($student['mobile']); ?>" 
                         placeholder="Enter your mobile number" required>
                  <div class="form-text">
                    <i class="bi bi-info-circle me-1"></i>
                    Please enter your complete mobile number including area code.
                  </div>
                </div>
              </div>
              <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" name="update_mobile" class="btn btn-primary" 
                        onclick="return confirm('Are you sure you want to update your mobile number?');">
                  <i class="bi bi-save me-2"></i>Update Mobile
                </button>
              </div>
            </form>
          </div>
        </div>

        <!-- Change Password Modal with OTP -->
        <div class="modal fade" id="passwordModal" tabindex="-1" data-bs-backdrop="static" data-bs-keyboard="false">
          <div class="modal-dialog modal-dialog-centered">
            <form id="passwordUpdateForm" method="POST" class="modal-content" autocomplete="off">
              <div class="modal-header">
                <h5 class="modal-title">
                  <i class="bi bi-key me-2"></i>Change Password
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
              </div>
              <div class="modal-body">
                <div class="alert alert-info mb-3">
                  <i class="bi bi-shield-check me-2"></i>
                  <strong>Password Requirements:</strong>
                  <ul class="mb-0 mt-2" style="font-size: 0.9rem;">
                    <li>At least 12 characters long</li>
                    <li>Contains uppercase and lowercase letters</li>
                    <li>Contains numbers</li>
                    <li>Contains special characters (!@#$%^&*)</li>
                    <li>Cannot reuse your current password</li>
                  </ul>
                </div>
                
                <div class="mb-3 position-relative">
                  <label class="form-label">Current Password</label>
                  <div class="input-group">
                    <input type="password" name="current_password" id="currentPassword" 
                           class="form-control" placeholder="Enter your current password" required minlength="8">
                    <button class="btn btn-outline-secondary" type="button" onclick="togglePasswordVisibility('currentPassword', 'currentPwdIcon')">
                      <i class="bi bi-eye" id="currentPwdIcon"></i>
                    </button>
                  </div>
                  <span id="currentPwdError" class="form-error position-absolute" style="right:15px;top:35px;"></span>
                </div>
                
                <div class="mb-3 position-relative">
                  <label class="form-label">New Password</label>
                  <div class="input-group">
                    <input type="password" name="new_password" id="newPassword" 
                           class="form-control" placeholder="Enter your new password" required minlength="12">
                    <button class="btn btn-outline-secondary" type="button" onclick="togglePasswordVisibility('newPassword', 'newPwdIcon')">
                      <i class="bi bi-eye" id="newPwdIcon"></i>
                    </button>
                  </div>
                  
                  <!-- Password Strength Indicator -->
                  <div class="mt-2">
                    <div class="d-flex justify-content-between align-items-center mb-1">
                      <small class="text-muted">Password Strength:</small>
                      <small id="strengthText" class="fw-bold"></small>
                    </div>
                    <div class="progress" style="height: 8px;">
                      <div id="strengthBar" class="progress-bar" role="progressbar" 
                           style="width: 0%; transition: all 0.3s ease;" 
                           aria-valuenow="0" aria-valuemin="0" aria-valuemax="100"></div>
                    </div>
                    <small id="strengthFeedback" class="text-muted d-block mt-1"></small>
                  </div>
                  <span id="newPwdError" class="form-error position-absolute" style="right:15px;top:35px;"></span>
                </div>
                
                <div class="mb-3 position-relative">
                  <label class="form-label">Confirm New Password</label>
                  <div class="input-group">
                    <input type="password" name="confirm_password" id="confirmPassword" 
                           class="form-control" placeholder="Confirm your new password" required minlength="12">
                    <button class="btn btn-outline-secondary" type="button" onclick="togglePasswordVisibility('confirmPassword', 'confirmPwdIcon')">
                      <i class="bi bi-eye" id="confirmPwdIcon"></i>
                    </button>
                  </div>
                  <small id="passwordMatchText" class="d-block mt-1"></small>
                  <span id="confirmPwdError" class="form-error position-absolute" style="right:15px;top:35px;"></span>
                </div>
                
                <div id="otpPwdSection" style="display:none;">
                  <div class="alert alert-info">
                    <i class="bi bi-info-circle me-2"></i>
                    We've sent a verification code to your email address for additional security.
                  </div>
                  <div class="mb-3 position-relative">
                    <label class="form-label">Enter Verification Code</label>
                    <input type="text" id="otpPwdInput" class="form-control" maxlength="6" 
                           placeholder="6-digit code" autocomplete="off">
                    <span id="otpPwdError" class="form-error position-absolute" style="right:15px;top:35px;"></span>
                  </div>
                  <button type="button" class="btn btn-info w-100 mb-2" id="verifyOtpPwdBtn">
                    <i class="bi bi-check2-circle me-2"></i>Verify Code
                  </button>
                  <div id="otpPwdTimer" class="text-danger mt-2"></div>
                  <button type="button" class="btn btn-link w-100" id="resendOtpPwdBtn" style="display:none;">
                    <i class="bi bi-arrow-clockwise me-2"></i>Resend Code
                  </button>
                </div>
              </div>
              <div class="modal-footer">
                <span id="otpPwdStatus" class="ms-2"></span>
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" id="sendOtpPwdBtn" class="btn btn-primary">
                  <i class="bi bi-send me-2"></i>Send Verification Code
                </button>
                <button type="submit" name="update_password" id="savePwdBtn" class="btn btn-success" style="display:none;">
                  <i class="bi bi-save me-2"></i>Change Password
                </button>
              </div>
            </form>
          </div>
        </div>
        
        <!-- Update Mother's Maiden Name Modal -->
        <div class="modal fade" id="updateMothersMaidenNameModal" tabindex="-1" data-bs-backdrop="static" data-bs-keyboard="false">
          <div class="modal-dialog modal-dialog-centered">
            <form id="mothersMaidenNameForm" method="POST" class="modal-content">
              <div class="modal-header">
                <h5 class="modal-title">
                  <i class="bi bi-person-hearts me-2"></i><?php echo empty($student['mothers_maiden_name']) ? 'Add' : 'Update'; ?> Mother's Maiden Name
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
              </div>
              <div class="modal-body">
                <div class="alert alert-info">
                  <i class="bi bi-info-circle me-2"></i>
                  <strong>Why we need this:</strong> Your mother's maiden name (her surname before marriage) helps us prevent duplicate household registrations and ensures only one student per household receives assistance.
                </div>
                
                <div class="mb-3">
                  <label class="form-label">Mother's Maiden Name <span class="text-danger">*</span></label>
                  <input 
                    type="text" 
                    class="form-control" 
                    name="mothers_maiden_name" 
                    id="mothersMaidenNameInput"
                    value="<?php echo htmlspecialchars($student['mothers_maiden_name'] ?? ''); ?>"
                    placeholder="Enter mother's surname before marriage"
                    required
                    maxlength="100"
                    pattern="[A-Za-zÑñ\s\-\']+"
                    title="Only letters, spaces, hyphens, and apostrophes allowed"
                  >
                  <div class="form-text">
                    <i class="bi bi-lightbulb me-1"></i>
                    Example: If your mother's full name before marriage was "Maria Santos", enter "Santos"
                  </div>
                </div>
                
                <div class="alert alert-warning" id="sameSurnameWarning" style="display: none;">
                  <i class="bi bi-exclamation-triangle me-2"></i>
                  <strong>Notice:</strong> The maiden name matches your surname. This may require admin verification.
                </div>
              </div>
              <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                  Cancel
                </button>
                <button type="submit" name="update_mothers_maiden_name" class="btn btn-primary">
                  <i class="bi bi-save me-2"></i>Save
                </button>
              </div>
            </form>
          </div>
        </div>
      </div>
    </section>

          </div>
        </div>
      </div>
    </section>

  <!-- Export Data Confirmation Modal -->
  <div class="modal fade" id="exportConfirmModal" tabindex="-1" data-bs-backdrop="static" data-bs-keyboard="false">
    <div class="modal-dialog modal-dialog-centered">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title">
            <i class="bi bi-cloud-arrow-down me-2"></i>Request Data Export
          </h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <div class="alert alert-info">
            <i class="bi bi-info-circle me-2"></i>
            <strong>What data will be exported?</strong>
          </div>
          
          <p class="mb-3">Your export will include:</p>
          <ul class="mb-3">
            <li><i class="bi bi-check-circle text-success me-2"></i>Personal information (name, contact details)</li>
            <li><i class="bi bi-check-circle text-success me-2"></i>Application status and history</li>
            <li><i class="bi bi-check-circle text-success me-2"></i>Uploaded documents</li>
            <li><i class="bi bi-check-circle text-success me-2"></i>Communication records</li>
            <li><i class="bi bi-check-circle text-success me-2"></i>Account activity logs</li>
          </ul>

          <div class="alert alert-warning">
            <i class="bi bi-clock-history me-2"></i>
            <strong>Processing time:</strong> Your export will be prepared in the background. 
            This may take a few minutes depending on the amount of data. You'll be able to 
            download it once it's ready.
          </div>

          <div class="alert alert-success">
            <i class="bi bi-shield-check me-2"></i>
            <strong>Security:</strong> Your data export will be securely encrypted and will 
            expire after 7 days for your protection.
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
            <i class="bi bi-x-circle me-2"></i>Cancel
          </button>
          <button type="button" id="confirmExportBtn" class="btn btn-primary">
            <i class="bi bi-cloud-arrow-down me-2"></i>Confirm & Export
          </button>
        </div>
      </div>
    </div>
  </div>

  <script src="../../assets/js/bootstrap.bundle.min.js"></script>
  <script src="../../assets/js/student/sidebar.js"></script>
  <script src="../../assets/js/shared/password_strength_validator.js"></script>
  <script src="../../assets/js/student/student_profile.js"></script>
  
  <script>
    // Move modals to <body> on open to avoid stacking-context issues from ancestors
    document.addEventListener('DOMContentLoaded', function() {
      document.querySelectorAll('.modal').forEach(function(modalEl) {
        modalEl.addEventListener('show.bs.modal', function() {
          if (this.parentElement !== document.body) {
            document.body.appendChild(this);
          }
        });
      });
    });

    // Smooth scroll and active navigation highlighting
    document.addEventListener('DOMContentLoaded', function() {
      const navItems = document.querySelectorAll('.settings-nav-item[href^="#"]'); // Only hash links
      const sections = document.querySelectorAll('.settings-content-section');

      // Handle navigation clicks (only for hash links)
      navItems.forEach(item => {
        item.addEventListener('click', function(e) {
          e.preventDefault();
          const targetId = this.getAttribute('href').substring(1);
          const targetSection = document.getElementById(targetId);
          
          // Remove active class from all nav items
          navItems.forEach(nav => nav.classList.remove('active'));
          // Add active class to clicked item
          this.classList.add('active');
          
          // Smooth scroll to section
          if (targetSection) {
            targetSection.scrollIntoView({ 
              behavior: 'smooth', 
              block: 'start'
            });
            // Update URL
            history.pushState(null, null, '#' + targetId);
          }
        });
      });

      // Highlight nav on scroll (intersection observer)
      const observerOptions = {
        rootMargin: '-100px 0px -50% 0px',
        threshold: 0
      };

      const observer = new IntersectionObserver((entries) => {
        entries.forEach(entry => {
          if (entry.isIntersecting) {
            navItems.forEach(item => {
              item.classList.remove('active');
              if (item.getAttribute('href') === '#' + entry.target.id) {
                item.classList.add('active');
              }
            });
          }
        });
      }, observerOptions);

      sections.forEach(section => observer.observe(section));

      // Handle initial hash
      const hash = window.location.hash;
      if (hash) {
        const targetSection = document.querySelector(hash);
        const targetNav = document.querySelector(`.settings-nav-item[href="${hash}"]`);
        if (targetSection && targetNav) {
          navItems.forEach(nav => nav.classList.remove('active'));
          targetNav.classList.add('active');
          setTimeout(() => {
            targetSection.scrollIntoView({ behavior: 'smooth', block: 'start' });
          }, 100);
        }
      }
    });

    // Initialize Privacy & Data export controls if present
    document.addEventListener('DOMContentLoaded', function() {
      const statusEl = document.getElementById('exportStatus');
      const btn = document.getElementById('requestExportBtn');
      const dlWrap = document.getElementById('downloadContainer');
      const dlLink = document.getElementById('downloadLink');
      const fileMeta = document.getElementById('fileMeta');

      if (!statusEl || !btn) return; // Section not on page

      const exportModalEl = document.getElementById('exportConfirmModal');
      const confirmBtn = document.getElementById('confirmExportBtn');
      
      if (!exportModalEl || !confirmBtn) {
        console.error('Export modal or confirm button not found');
        return;
      }
      
      const exportModal = new bootstrap.Modal(exportModalEl);

      async function fetchStatus() {
        try {
          console.log('Fetching export status...');
          const res = await fetch('../../api/student/export_status.php', { credentials: 'include' });
          console.log('Response status:', res.status);
          
          if (!res.ok) {
            const text = await res.text();
            console.error('Export status API error:', text);
            statusEl.textContent = `Unable to fetch export status (${res.status}).`;
            return;
          }
          
          const data = await res.json();
          console.log('Export status data:', data);
          
          if (!data.success) { 
            statusEl.textContent = 'Unable to fetch export status: ' + (data.error || 'Unknown error');
            console.error('Export status failed:', data.error);
            return;
          }
          
          if (!data.exists) { 
            statusEl.textContent = 'No export requested yet.'; 
            dlWrap.classList.add('d-none'); 
            return; 
          }

          statusEl.textContent = `Status: ${data.status}` + (data.processed_at ? ` • Processed: ${new Date(data.processed_at).toLocaleString()}` : '');
          if (data.status === 'ready' && data.download_url) {
            dlLink.href = data.download_url;
            dlWrap.classList.remove('d-none');
            if (data.file_size_bytes) {
              const mb = (data.file_size_bytes / (1024*1024)).toFixed(2);
              fileMeta.textContent = `(~${mb} MB) • Expires: ${data.expires_at ? new Date(data.expires_at).toLocaleString() : ''}`;
            } else {
              fileMeta.textContent = '';
            }
          } else {
            dlWrap.classList.add('d-none');
          }
        } catch (e) {
          console.error('Error fetching export status:', e);
          statusEl.textContent = 'Error fetching export status: ' + e.message;
        }
      }

      async function processExport() {
        confirmBtn.disabled = true; 
        const original = confirmBtn.innerHTML; 
        confirmBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Processing…';
        
        try {
          const res = await fetch('../../api/student/request_data_export.php', { method: 'POST', credentials: 'include' });
          const data = await res.json();
          if (!data.success) { 
            statusEl.textContent = 'Export request failed.'; 
          } else {
            statusEl.textContent = 'Export request submitted successfully. Processing...';
          }
          await fetchStatus();
          exportModal.hide();
        } catch (e) {
          console.error('Export request error:', e);
          statusEl.textContent = 'Export request failed: ' + e.message;
        } finally { 
          confirmBtn.disabled = false; 
          confirmBtn.innerHTML = original; 
        }
      }

      // Open modal when "Request Export" is clicked
      btn.addEventListener('click', () => {
        console.log('Request Export clicked, opening modal...');
        exportModal.show();
      });

      // Handle confirmation in modal
      confirmBtn.addEventListener('click', processExport);

      // Initial status
      fetchStatus();
    });

    // Notification Preferences: load and save (simplified - frequency only)
    document.addEventListener('DOMContentLoaded', function() {
      const freqImmediate = document.getElementById('freqImmediate');
      const freqDaily = document.getElementById('freqDaily');
      const saveBtn = document.getElementById('saveNotifPrefsBtn');

      async function loadPrefs() {
        try {
          const res = await fetch('../../api/student/get_notification_preferences.php', { credentials: 'include' });
          const data = await res.json();
          if (!data.success) return;
          const p = data.preferences;
          // Set frequency radio buttons
          if (p.email_frequency === 'daily') {
            freqDaily.checked = true;
          } else {
            freqImmediate.checked = true;
          }
        } catch (e) { 
          // Default to immediate if fetch fails
          freqImmediate.checked = true;
        }
      }

      async function savePrefs() {
        const frequency = freqDaily.checked ? 'daily' : 'immediate';
        const payload = {
          email_enabled: true, // Always enabled
          email_frequency: frequency
        };
        
        saveBtn.disabled = true; 
        const original = saveBtn.innerHTML; 
        saveBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Saving…';
        
        try {
          const res = await fetch('../../api/student/save_notification_preferences.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            credentials: 'include',
            body: JSON.stringify(payload)
          });
          const out = await res.json();
          // Lightweight feedback via alert() to avoid extra toasts framework
          if (out.success) {
            alert('Email notification preferences saved successfully!');
          } else {
            alert('Failed to save preferences. Please try again.');
          }
        } catch (e) {
          alert('Failed to save preferences. Please check your connection and try again.');
        } finally { 
          saveBtn.disabled = false; 
          saveBtn.innerHTML = original; 
        }
      }

      if (freqImmediate && freqDaily && saveBtn) {
        loadPrefs();
        saveBtn.addEventListener('click', savePrefs);
      }
    });
    
    // Mother's Maiden Name validation - warn if matches student surname
    document.addEventListener('DOMContentLoaded', function() {
      const maidenNameInput = document.getElementById('mothersMaidenNameInput');
      const sameNameWarning = document.getElementById('sameSurnameWarning');
      const studentLastName = '<?php echo addslashes($student['last_name']); ?>';
      
      if (maidenNameInput && sameNameWarning) {
        maidenNameInput.addEventListener('input', function() {
          const enteredName = this.value.trim().toLowerCase();
          const surname = studentLastName.toLowerCase();
          
          if (enteredName === surname) {
            sameNameWarning.style.display = 'block';
          } else {
            sameNameWarning.style.display = 'none';
          }
        });
        
        // Check on load if already has value
        const currentValue = maidenNameInput.value.trim().toLowerCase();
        if (currentValue === studentLastName.toLowerCase()) {
          sameNameWarning.style.display = 'block';
        }
      }
    });
  </script>
  
  <!-- Anti-FOUC Script -->
  <script>
    (function() {
      document.body.classList.add('ready');
      window.addEventListener('pageshow', function(event) {
        if (event.persisted) {
          document.body.classList.add('ready');
        }
      });
    })();
  </script>
</body>
</html>
