<?php
/**
 * AJAX: Upload Logo File to Railway Volume
 */

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/permissions.php';

header('Content-Type: application/json');

// Security check
if (!isset($_SESSION['admin_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$adminRole = getCurrentAdminRole($connection);
if ($adminRole !== 'super_admin') {
    echo json_encode(['success' => false, 'message' => 'Super Admin only']);
    exit;
}

if (!isset($_FILES['logo']) || $_FILES['logo']['error'] !== UPLOAD_ERR_OK) {
    echo json_encode(['success' => false, 'message' => 'No file uploaded or upload error']);
    exit;
}

$targetPath = $_POST['targetPath'] ?? null;
if (!$targetPath) {
    echo json_encode(['success' => false, 'message' => 'No target path specified']);
    exit;
}

if (!is_dir($targetPath)) {
    echo json_encode(['success' => false, 'message' => 'Target directory does not exist']);
    exit;
}

$file = $_FILES['logo'];
$filename = basename($file['name']);

// Validate file type
$allowedExtensions = ['png', 'jpg', 'jpeg', 'gif', 'webp', 'svg'];
$ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));

if (!in_array($ext, $allowedExtensions)) {
    echo json_encode(['success' => false, 'message' => 'Invalid file type. Only images allowed.']);
    exit;
}

// Validate MIME type
$finfo = finfo_open(FILEINFO_MIME_TYPE);
$mimeType = finfo_file($finfo, $file['tmp_name']);
finfo_close($finfo);

$allowedMimes = ['image/png', 'image/jpeg', 'image/gif', 'image/webp', 'image/svg+xml'];
if (!in_array($mimeType, $allowedMimes)) {
    echo json_encode(['success' => false, 'message' => 'Invalid file type detected.']);
    exit;
}

// Validate file size (max 10MB for logos)
if ($file['size'] > 10 * 1024 * 1024) {
    echo json_encode(['success' => false, 'message' => 'File size exceeds 10MB limit']);
    exit;
}

$targetFile = $targetPath . '/' . $filename;

// Move file
if (move_uploaded_file($file['tmp_name'], $targetFile)) {
    chmod($targetFile, 0644);
    
    echo json_encode([
        'success' => true,
        'message' => 'Uploaded successfully',
        'filename' => $filename,
        'path' => $targetFile,
        'size' => $file['size']
    ]);
} else {
    echo json_encode(['success' => false, 'message' => 'Failed to move uploaded file']);
}
?>
