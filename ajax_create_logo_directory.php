<?php
ob_start();
error_reporting(E_ALL & ~E_NOTICE & ~E_WARNING);
ini_set('display_errors', '0');
/**
 * AJAX: Create Logo Directory in Railway Volume
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

$input = json_decode(file_get_contents('php://input'), true);
$targetPath = $input['path'] ?? null;

if (!$targetPath) {
    echo json_encode(['success' => false, 'message' => 'No path specified']);
    exit;
}

// Create directory
if (is_dir($targetPath)) {
    echo json_encode([
        'success' => true,
        'message' => 'Directory already exists',
        'path' => $targetPath,
        'writable' => is_writable($targetPath)
    ]);
    exit;
}

if (mkdir($targetPath, 0755, true)) {
    echo json_encode([
        'success' => true,
        'message' => 'Directory created successfully',
        'path' => $targetPath,
        'writable' => is_writable($targetPath)
    ]);
} else {
    echo json_encode([
        'success' => false,
        'message' => 'Failed to create directory. Check permissions.'
    ]);
}
?>
