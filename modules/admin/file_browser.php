<?php
/**
 * Railway Volume File Browser
 * Allows admins to view and manage files in the Railway volume or local uploads directory
 */

// Start session only if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../../config/database.php';

// Admin authentication check
if (!isset($_SESSION['admin_id'])) {
    header('Location: ../../unified_login.php');
    exit;
}

// Check admin role
$admin_role = 'sub_admin';
if (function_exists('getCurrentAdminRole')) {
    $admin_role = getCurrentAdminRole($connection);
} elseif (isset($_SESSION['admin_role'])) {
    $admin_role = $_SESSION['admin_role'];
}

// Only super_admin can access file browser
if ($admin_role !== 'super_admin') {
    $_SESSION['error'] = 'Access denied. Only super administrators can access the file browser.';
    header('Location: homepage.php');
    exit;
}

// Determine base directory (Railway volume or local)
$isRailway = file_exists('/mnt/assets/uploads/');

// Railway environment check (for debugging)
if (isset($_GET['env_check'])) {
    echo "<pre>";
    echo "Environment Check:\n";
    echo "===================\n";
    echo "Railway Volume Exists: " . (file_exists('/mnt/assets/uploads/') ? 'YES' : 'NO') . "\n";
    echo "Is Railway: " . ($isRailway ? 'YES' : 'NO') . "\n";
    echo "PHP Version: " . phpversion() . "\n";
    echo "OS: " . PHP_OS . "\n";
    echo "Directory Separator: " . DIRECTORY_SEPARATOR . "\n";
    echo "Base Dir: " . ($isRailway ? '/mnt/assets/uploads/' : __DIR__ . '/../../assets/uploads/') . "\n";
    echo "\nDirectory Contents:\n";
    if ($isRailway && is_dir('/mnt/assets/uploads/')) {
        print_r(scandir('/mnt/assets/uploads/'));
    }
    echo "</pre>";
    exit;
}

// Debug mode: Force Railway path for testing (remove in production)
if (isset($_GET['debug_railway']) && $_GET['debug_railway'] === '1') {
    $isRailway = true;
    $_SESSION['debug_railway_mode'] = true;
}

// Set base directory with proper trailing slash
$baseDir = $isRailway ? '/mnt/assets/uploads/' : __DIR__ . '/../../assets/uploads/';

// Ensure base directory exists and get its real path
if (!is_dir($baseDir)) {
    $_SESSION['error'] = 'Upload directory not found. Please contact system administrator.';
    header('Location: homepage.php');
    exit;
}

$baseRealPath = realpath($baseDir);

// Get and normalize current path
$currentPath = $_GET['path'] ?? '';

// Normalize path separators for cross-platform compatibility (Railway uses /, Windows uses \)
$currentPath = str_replace('\\', '/', $currentPath);
$currentPath = trim($currentPath, '/');

// Build full path - use DIRECTORY_SEPARATOR for system compatibility
if ($currentPath) {
    // Convert forward slashes to system-appropriate separator
    $systemPath = str_replace('/', DIRECTORY_SEPARATOR, $currentPath);
    $fullPath = realpath($baseDir . $systemPath);
} else {
    $fullPath = realpath($baseDir);
}

// Security: Prevent directory traversal attacks
if ($fullPath === false || strpos($fullPath, $baseRealPath) !== 0) {
    $_SESSION['error'] = 'Invalid path or directory does not exist.';
    header('Location: file_browser.php');
    exit;
}

// Handle folder creation
if (isset($_POST['create_folder']) && isset($_POST['folder_name'])) {
    $folderName = trim($_POST['folder_name']);
    
    // Validate folder name
    if (empty($folderName)) {
        $_SESSION['error'] = 'Folder name cannot be empty.';
    } elseif (preg_match('/[^a-zA-Z0-9_\-]/', $folderName)) {
        $_SESSION['error'] = 'Folder name can only contain letters, numbers, underscores, and hyphens.';
    } else {
        $newFolderPath = $fullPath . DIRECTORY_SEPARATOR . $folderName;
        
        // Check if folder already exists
        if (file_exists($newFolderPath)) {
            $_SESSION['error'] = 'A folder with this name already exists.';
        } else {
            // Create folder with proper permissions
            if (mkdir($newFolderPath, 0755, true)) {
                $_SESSION['success'] = 'Folder "' . htmlspecialchars($folderName) . '" created successfully.';
            } else {
                $_SESSION['error'] = 'Failed to create folder. Please check permissions.';
            }
        }
    }
    
    // Redirect to refresh the page
    header('Location: file_browser.php?path=' . urlencode($currentPath));
    exit;
}

// Handle folder deletion
if (isset($_POST['delete_folder']) && isset($_POST['folder_path'])) {
    $folderToDelete = trim($_POST['folder_path']);
    
    // Normalize the path
    $folderToDelete = str_replace('\\', '/', $folderToDelete);
    
    // Build full system path
    $folderSystemPath = $baseDir . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $folderToDelete);
    $folderRealPath = realpath($folderSystemPath);
    
    // Security check: ensure the folder is within the base directory
    if ($folderRealPath === false || strpos($folderRealPath, $baseRealPath) !== 0) {
        $_SESSION['error'] = 'Invalid folder path.';
    } elseif (!is_dir($folderRealPath)) {
        $_SESSION['error'] = 'Folder does not exist.';
    } else {
        // Check if folder is empty
        $files = array_diff(scandir($folderRealPath), ['.', '..']);
        
        if (count($files) > 0) {
            $_SESSION['error'] = 'Cannot delete folder: It contains ' . count($files) . ' item(s). Please remove all contents first.';
        } else {
            // Delete the empty folder
            if (rmdir($folderRealPath)) {
                $_SESSION['success'] = 'Folder deleted successfully.';
            } else {
                $_SESSION['error'] = 'Failed to delete folder. Check permissions.';
            }
        }
    }
    
    // Redirect to refresh the page
    header('Location: file_browser.php?path=' . urlencode($currentPath));
    exit;
}

// Handle file download
if (isset($_GET['download']) && $_GET['download'] === '1') {
    // Rebuild download path the same way
    if ($currentPath) {
        $systemPath = str_replace('/', DIRECTORY_SEPARATOR, $currentPath);
        $downloadPath = realpath($baseDir . $systemPath);
    } else {
        $downloadPath = false; // Can't download root directory
    }
    
    // Security check
    if ($downloadPath === false || strpos($downloadPath, $baseRealPath) !== 0 || !is_file($downloadPath)) {
        $_SESSION['error'] = 'Invalid file path.';
        header('Location: file_browser.php');
        exit;
    }
    
    // Stream file download with proper headers
    $filename = basename($downloadPath);
    $filesize = filesize($downloadPath);
    $mimetype = mime_content_type($downloadPath) ?: 'application/octet-stream';
    
    header('Content-Type: ' . $mimetype);
    header('Content-Disposition: attachment; filename="' . addslashes($filename) . '"');
    header('Content-Length: ' . $filesize);
    header('Cache-Control: no-cache, must-revalidate');
    header('Pragma: no-cache');
    header('Expires: 0');
    
    // Output file
    readfile($downloadPath);
    exit;
}

// Get directory contents
$items = [];
$totalSize = 0;
$fileCount = 0;
$dirCount = 0;

if (is_dir($fullPath)) {
    $files = scandir($fullPath);
    foreach ($files as $file) {
        if ($file === '.' || $file === '..') continue;
        
        $itemPath = $fullPath . DIRECTORY_SEPARATOR . $file;
        
        // Build relative path properly
        $relativePath = $currentPath ? $currentPath . '/' . $file : $file;
        
        $isDir = is_dir($itemPath);
        $size = is_file($itemPath) ? filesize($itemPath) : 0;
        
        if ($isDir) {
            $dirCount++;
        } else {
            $fileCount++;
            $totalSize += $size;
        }
        
        $items[] = [
            'name' => $file,
            'path' => $relativePath,
            'type' => $isDir ? 'dir' : 'file',
            'size' => $size,
            'modified' => filemtime($itemPath)
        ];
    }
    
    // Sort: directories first, then alphabetically
    usort($items, function($a, $b) {
        if ($a['type'] !== $b['type']) {
            return $a['type'] === 'dir' ? -1 : 1;
        }
        return strcasecmp($a['name'], $b['name']);
    });
}

// Debug info (remove in production)
if (isset($_GET['debug'])) {
    echo "<pre>";
    echo "Base Dir: " . $baseDir . "\n";
    echo "Current Path: " . $currentPath . "\n";
    echo "Full Path: " . $fullPath . "\n";
    echo "Is Dir: " . (is_dir($fullPath) ? 'Yes' : 'No') . "\n";
    echo "Items Count: " . count($items) . "\n";
    echo "File Count: " . $fileCount . "\n";
    echo "Dir Count: " . $dirCount . "\n";
    echo "\nItems:\n";
    print_r($items);
    echo "</pre>";
    exit;
}

// Page title
$pageTitle = 'Railway Volume Browser';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=5.0, user-scalable=yes">
    <title><?php echo $pageTitle; ?> - EducAid</title>
    
    <!-- Bootstrap CSS -->
    <link href="../../assets/css/bootstrap.min.css" rel="stylesheet">
    <link href="../../assets/css/bootstrap-icons.css" rel="stylesheet">
    
    <!-- Admin Styles -->
    <link rel="stylesheet" href="../../assets/css/admin/admin_global.css">
    <link rel="stylesheet" href="../../assets/css/admin/sidebar.css">
    <link rel="stylesheet" href="../../assets/css/admin/topbar.css">
    
    <style>
        /* Admin Header Positioning */
        .admin-main-header {
            position: fixed;
            top: 38px; /* Below topbar */
            left: 250px; /* Aligned with sidebar */
            right: 0;
            z-index: 1030;
            transition: left 0.3s ease;
        }
        
        /* When sidebar is closed */
        .sidebar.close ~ .admin-main-header {
            left: 70px;
        }
        
        /* Ensure home-section aligns properly with sidebar */
        .home-section {
            position: relative;
            min-height: 100vh;
            left: 250px;
            width: calc(100% - 250px);
            transition: all 0.3s ease;
            background: #f8f9fa;
            margin-top: calc(38px + 60px); /* Topbar + Header */
        }
        
        .sidebar.close ~ .home-section {
            left: 70px;
            width: calc(100% - 70px);
        }
        
        @media (max-width: 768px) {
            .admin-main-header {
                left: 0 !important;
                transition: none;
            }
            
            .home-section {
                left: 0 !important;
                width: 100% !important;
                transition: none;
            }
        }
        
        .file-browser-container {
            background: #fff;
            border-radius: 12px;
            padding: 24px;
            box-shadow: 0 2px 12px rgba(0,0,0,0.08);
        }
        
        .breadcrumb-path {
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
            padding: 16px 20px;
            border-radius: 10px;
            margin-bottom: 24px;
            border: 1px solid #dee2e6;
            overflow-x: auto;
        }
        
        .breadcrumb {
            margin-bottom: 0;
            background: transparent;
            padding: 0;
            flex-wrap: nowrap;
            white-space: nowrap;
        }
        
        .breadcrumb-item {
            white-space: nowrap;
        }
        
        .breadcrumb-item a {
            color: #0d6efd;
            text-decoration: none;
            font-weight: 500;
        }
        
        .breadcrumb-item a:hover {
            text-decoration: underline;
        }
        
        .stats-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 16px 20px;
            border-radius: 10px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 16px;
        }
        
        .stats-card i {
            font-size: 2.5rem;
            opacity: 0.9;
        }
        
        .stats-content {
            flex: 1;
        }
        
        .stats-content small {
            opacity: 0.9;
            font-size: 0.85rem;
        }
        
        .stats-content strong {
            font-size: 1.1rem;
        }
        
        .file-list-header {
            background: #f8f9fa;
            padding: 12px 16px;
            border-radius: 8px 8px 0 0;
            border: 1px solid #dee2e6;
            border-bottom: none;
            font-weight: 600;
            color: #495057;
            font-size: 0.9rem;
        }
        
        .file-item {
            padding: 14px 16px;
            border: 1px solid #dee2e6;
            border-top: none;
            transition: all 0.2s;
            background: white;
        }
        
        .file-item:last-child {
            border-radius: 0 0 8px 8px;
        }
        
        .file-item:hover {
            background: #f8f9fa;
        }
        
        .file-icon {
            font-size: 1.8rem;
            margin-right: 12px;
            width: 32px;
            text-align: center;
            flex-shrink: 0;
        }
        
        .file-name {
            flex: 1;
            min-width: 0;
        }
        
        .file-name a,
        .file-name span {
            color: #212529;
            text-decoration: none;
            font-weight: 500;
            display: block;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }
        
        .file-name a:hover {
            color: #0d6efd;
            text-decoration: underline;
        }
        
        .file-meta {
            display: flex;
            align-items: center;
            gap: 16px;
            color: #6c757d;
            font-size: 0.9rem;
        }
        
        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: #6c757d;
        }
        
        .empty-state i {
            font-size: 4rem;
            opacity: 0.3;
            margin-bottom: 16px;
        }
        
        .btn-action {
            padding: 6px 12px;
            font-size: 0.875rem;
            border-radius: 6px;
            white-space: nowrap;
        }
        
        .location-badge {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 6px 12px;
            border-radius: 6px;
            font-size: 0.85rem;
            font-weight: 600;
            white-space: nowrap;
        }
        
        .location-badge.railway {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }
        
        .location-badge.local {
            background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
            color: white;
        }
        
        /* Mobile Responsive Styles */
        @media (max-width: 768px) {
            .file-browser-container {
                padding: 16px;
                border-radius: 8px;
            }
            
            .breadcrumb-path {
                padding: 12px 16px;
                margin-bottom: 16px;
            }
            
            .breadcrumb {
                font-size: 0.85rem;
            }
            
            .stats-card {
                flex-direction: row;
                padding: 12px 16px;
            }
            
            .stats-card i {
                font-size: 2rem;
            }
            
            .stats-content .d-flex {
                flex-direction: column !important;
                gap: 8px !important;
            }
            
            .stats-content small {
                font-size: 0.75rem;
            }
            
            .stats-content strong {
                font-size: 1rem;
            }
            
            .file-list-header {
                display: none; /* Hide desktop header on mobile */
            }
            
            .file-item {
                padding: 12px;
                margin-bottom: 8px;
                border-radius: 8px !important;
                border: 1px solid #dee2e6 !important;
            }
            
            .file-item:hover {
                transform: none; /* Disable transform on mobile */
            }
            
            .file-icon {
                font-size: 1.5rem;
                width: 28px;
                margin-right: 8px;
            }
            
            /* Mobile card-style layout */
            .file-item .row {
                gap: 8px;
            }
            
            /* Improve touch target sizes */
            .btn-action {
                padding: 8px 12px;
                font-size: 0.9rem;
                min-width: 44px;
                min-height: 44px;
                display: inline-flex;
                align-items: center;
                justify-content: center;
            }
            
            .location-badge {
                font-size: 0.75rem;
                padding: 4px 8px;
            }
            
            /* Better text wrapping on mobile */
            .file-name a,
            .file-name span {
                white-space: normal;
                word-break: break-word;
            }
        }
        
        @media (max-width: 576px) {
            .container-fluid {
                padding-left: 12px;
                padding-right: 12px;
            }
            
            .file-browser-container {
                padding: 12px;
            }
            
            .page-header-actions {
                flex-direction: column;
                align-items: stretch !important;
                gap: 8px;
            }
            
            .page-header-actions .btn,
            .page-header-actions .location-badge {
                width: 100%;
                justify-content: center;
            }
            
            .file-name a,
            .file-name span {
                font-size: 0.9rem;
            }
            
            h2 {
                font-size: 1.5rem;
            }
            
            h2 i {
                font-size: 1.3rem;
            }
        }
    </style>
</head>
<body class="admin-body">
    
    <?php 
    // Include admin header (contains hamburger menu)
    include_once __DIR__ . '/../../includes/admin/admin_header.php'; 
    ?>
    
    <?php 
    // Include admin topbar
    include_once __DIR__ . '/../../includes/admin/admin_topbar.php'; 
    ?>
    
    <?php 
    // Include admin sidebar
    include_once __DIR__ . '/../../includes/admin/admin_sidebar.php'; 
    ?>
    
    <div class="home-section" id="mainContent">
        <div class="container-fluid py-4">
            
            <!-- Page Header -->
            <div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center mb-4 gap-3">
                <div>
                    <h2 class="mb-1">
                        <i class="bi bi-folder2-open me-2 text-primary"></i>
                        <?php echo $pageTitle; ?>
                    </h2>
                    <p class="text-muted mb-0 small">Browse and manage uploaded files</p>
                </div>
                <div class="d-flex flex-column flex-sm-row gap-2 page-header-actions w-100 w-md-auto">
                    <span class="location-badge <?php echo $isRailway ? 'railway' : 'local'; ?>">
                        <i class="bi bi-<?php echo $isRailway ? 'cloud-check' : 'hdd'; ?>"></i>
                        <?php echo $isRailway ? 'Railway Volume' : 'Local Storage'; ?>
                    </span>
                    <a href="storage_dashboard.php" class="btn btn-outline-secondary">
                        <i class="bi bi-bar-chart me-2"></i>Storage Dashboard
                    </a>
                </div>
            </div>
            
            <!-- Alerts -->
            <?php if (isset($_SESSION['success'])): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <i class="bi bi-check-circle me-2"></i>
                    <?php 
                    echo htmlspecialchars($_SESSION['success']); 
                    unset($_SESSION['success']);
                    ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>
            
            <?php if (isset($_SESSION['error'])): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <i class="bi bi-exclamation-triangle me-2"></i>
                    <?php 
                    echo htmlspecialchars($_SESSION['error']); 
                    unset($_SESSION['error']);
                    ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>
            
            <div class="file-browser-container">
                
                <!-- Breadcrumb Navigation -->
                <div class="breadcrumb-path">
                    <nav aria-label="breadcrumb">
                        <ol class="breadcrumb mb-0">
                            <li class="breadcrumb-item">
                                <a href="?path=">
                                    <i class="bi bi-house-fill me-1"></i>
                                    <?php echo $isRailway ? '/mnt/assets/uploads' : '/assets/uploads'; ?>
                                </a>
                            </li>
                            <?php
                            $pathParts = array_filter(explode('/', $currentPath));
                            $accumulated = '';
                            foreach ($pathParts as $part) {
                                $accumulated .= ($accumulated ? '/' : '') . $part;
                                echo '<li class="breadcrumb-item">';
                                echo '<a href="?path=' . urlencode($accumulated) . '">' . htmlspecialchars($part) . '</a>';
                                echo '</li>';
                            }
                            ?>
                        </ol>
                    </nav>
                </div>
                
                <!-- Statistics Card -->
                <div class="stats-card">
                    <i class="bi bi-folder-fill"></i>
                    <div class="stats-content">
                        <div class="d-flex gap-4 flex-wrap">
                            <div>
                                <small class="d-block">Total Items</small>
                                <strong><?php echo number_format(count($items)); ?></strong>
                            </div>
                            <div>
                                <small class="d-block">Folders</small>
                                <strong><?php echo number_format($dirCount); ?></strong>
                            </div>
                            <div>
                                <small class="d-block">Files</small>
                                <strong><?php echo number_format($fileCount); ?></strong>
                            </div>
                            <div>
                                <small class="d-block">Total Size</small>
                                <strong><?php echo number_format($totalSize / 1024 / 1024, 2); ?> MB</strong>
                            </div>
                            <?php if (!empty($currentPath)): ?>
                            <div class="ms-auto">
                                <a href="?path=" class="btn btn-sm btn-light">
                                    <i class="bi bi-arrow-left me-1"></i>Back to Root
                                </a>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                
                <!-- Action Bar -->
                <div class="mb-3 d-flex gap-2 flex-wrap">
                    <button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#createFolderModal">
                        <i class="bi bi-folder-plus me-2"></i>Create Folder
                    </button>
                </div>
                
                <!-- File List Header (Desktop Only) -->
                <div class="file-list-header d-none d-md-block">
                    <div class="row align-items-center">
                        <div class="col-md-5">Name</div>
                        <div class="col-md-2 text-center">Type</div>
                        <div class="col-md-2 text-end">Size</div>
                        <div class="col-md-2 text-center">Modified</div>
                        <div class="col-md-1 text-center">Action</div>
                    </div>
                </div>
                
                <!-- File List -->
                <?php if (empty($items)): ?>
                    <div class="file-item">
                        <div class="empty-state">
                            <i class="bi bi-folder-x"></i>
                            <p class="mb-0">No files or folders found</p>
                        </div>
                    </div>
                <?php else: ?>
                    <?php foreach ($items as $item): ?>
                        <div class="file-item">
                            <!-- Desktop Layout -->
                            <div class="row align-items-center d-none d-md-flex">
                                <div class="col-md-5">
                                    <div class="d-flex align-items-center">
                                        <?php if ($item['type'] === 'dir'): ?>
                                            <i class="bi bi-folder-fill text-warning file-icon"></i>
                                            <div class="file-name">
                                                <a href="?path=<?php echo urlencode($item['path']); ?>">
                                                    <?php echo htmlspecialchars($item['name']); ?>
                                                </a>
                                            </div>
                                        <?php else: ?>
                                            <?php
                                            $ext = strtolower(pathinfo($item['name'], PATHINFO_EXTENSION));
                                            $iconMap = [
                                                'jpg' => 'bi-file-image text-primary',
                                                'jpeg' => 'bi-file-image text-primary',
                                                'png' => 'bi-file-image text-primary',
                                                'gif' => 'bi-file-image text-primary',
                                                'pdf' => 'bi-file-pdf text-danger',
                                                'txt' => 'bi-file-text text-secondary',
                                                'json' => 'bi-file-code text-info',
                                                'tsv' => 'bi-file-spreadsheet text-success',
                                                'csv' => 'bi-file-spreadsheet text-success',
                                                'zip' => 'bi-file-zip text-warning',
                                            ];
                                            $iconClass = $iconMap[$ext] ?? 'bi-file-earmark text-secondary';
                                            ?>
                                            <i class="bi <?php echo $iconClass; ?> file-icon"></i>
                                            <div class="file-name">
                                                <span><?php echo htmlspecialchars($item['name']); ?></span>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                
                                <div class="col-md-2 text-center">
                                    <?php if ($item['type'] === 'dir'): ?>
                                        <span class="badge bg-warning">Folder</span>
                                    <?php else: ?>
                                        <span class="badge bg-secondary"><?php echo strtoupper($ext ?? 'file'); ?></span>
                                    <?php endif; ?>
                                </div>
                                
                                <div class="col-md-2 text-end">
                                    <?php if ($item['type'] === 'file'): ?>
                                        <small class="text-muted">
                                            <?php 
                                            if ($item['size'] < 1024) {
                                                echo $item['size'] . ' B';
                                            } elseif ($item['size'] < 1024 * 1024) {
                                                echo number_format($item['size'] / 1024, 2) . ' KB';
                                            } else {
                                                echo number_format($item['size'] / 1024 / 1024, 2) . ' MB';
                                            }
                                            ?>
                                        </small>
                                    <?php else: ?>
                                        <small class="text-muted">—</small>
                                    <?php endif; ?>
                                </div>
                                
                                <div class="col-md-2 text-center">
                                    <small class="text-muted" title="<?php echo date('Y-m-d H:i:s', $item['modified']); ?>">
                                        <?php echo date('M d, Y', $item['modified']); ?>
                                    </small>
                                </div>
                                
                                <div class="col-md-1 text-center">
                                    <?php if ($item['type'] === 'file'): ?>
                                        <a href="?path=<?php echo urlencode($item['path']); ?>&download=1" 
                                           class="btn btn-sm btn-outline-primary btn-action"
                                           title="Download file">
                                            <i class="bi bi-download"></i>
                                        </a>
                                    <?php else: ?>
                                        <div class="d-flex gap-1 justify-content-center">
                                            <a href="?path=<?php echo urlencode($item['path']); ?>" 
                                               class="btn btn-sm btn-outline-secondary btn-action"
                                               title="Open folder">
                                                <i class="bi bi-folder-symlink"></i>
                                            </a>
                                            <button type="button"
                                                    class="btn btn-sm btn-outline-danger btn-action"
                                                    onclick="confirmDeleteFolder('<?php echo htmlspecialchars($item['name'], ENT_QUOTES); ?>', '<?php echo htmlspecialchars($item['path'], ENT_QUOTES); ?>')"
                                                    title="Delete folder">
                                                <i class="bi bi-trash"></i>
                                            </button>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                            
                            <!-- Mobile Layout -->
                            <div class="d-md-none">
                                <div class="d-flex align-items-start gap-2">
                                    <?php if ($item['type'] === 'dir'): ?>
                                        <i class="bi bi-folder-fill text-warning file-icon"></i>
                                        <div class="flex-grow-1 min-w-0">
                                            <div class="file-name mb-1">
                                                <a href="?path=<?php echo urlencode($item['path']); ?>">
                                                    <?php echo htmlspecialchars($item['name']); ?>
                                                </a>
                                            </div>
                                            <div class="d-flex align-items-center gap-2 flex-wrap">
                                                <span class="badge bg-warning">Folder</span>
                                                <small class="text-muted">
                                                    <?php echo date('M d, Y', $item['modified']); ?>
                                                </small>
                                            </div>
                                        </div>
                                        <div class="d-flex gap-1">
                                            <a href="?path=<?php echo urlencode($item['path']); ?>" 
                                               class="btn btn-sm btn-outline-secondary btn-action"
                                               title="Open">
                                                <i class="bi bi-folder-symlink"></i>
                                            </a>
                                            <button type="button"
                                                    class="btn btn-sm btn-outline-danger btn-action"
                                                    onclick="confirmDeleteFolder('<?php echo htmlspecialchars($item['name'], ENT_QUOTES); ?>', '<?php echo htmlspecialchars($item['path'], ENT_QUOTES); ?>')"
                                                    title="Delete">
                                                <i class="bi bi-trash"></i>
                                            </button>
                                        </div>
                                    <?php else: ?>
                                        <?php
                                        $ext = strtolower(pathinfo($item['name'], PATHINFO_EXTENSION));
                                        $iconMap = [
                                            'jpg' => 'bi-file-image text-primary',
                                            'jpeg' => 'bi-file-image text-primary',
                                            'png' => 'bi-file-image text-primary',
                                            'gif' => 'bi-file-image text-primary',
                                            'pdf' => 'bi-file-pdf text-danger',
                                            'txt' => 'bi-file-text text-secondary',
                                            'json' => 'bi-file-code text-info',
                                            'tsv' => 'bi-file-spreadsheet text-success',
                                            'csv' => 'bi-file-spreadsheet text-success',
                                            'zip' => 'bi-file-zip text-warning',
                                        ];
                                        $iconClass = $iconMap[$ext] ?? 'bi-file-earmark text-secondary';
                                        ?>
                                        <i class="bi <?php echo $iconClass; ?> file-icon"></i>
                                        <div class="flex-grow-1 min-w-0">
                                            <div class="file-name mb-1">
                                                <span><?php echo htmlspecialchars($item['name']); ?></span>
                                            </div>
                                            <div class="d-flex align-items-center gap-2 flex-wrap">
                                                <span class="badge bg-secondary"><?php echo strtoupper($ext ?? 'file'); ?></span>
                                                <small class="text-muted">
                                                    <?php 
                                                    if ($item['size'] < 1024) {
                                                        echo $item['size'] . ' B';
                                                    } elseif ($item['size'] < 1024 * 1024) {
                                                        echo number_format($item['size'] / 1024, 1) . ' KB';
                                                    } else {
                                                        echo number_format($item['size'] / 1024 / 1024, 1) . ' MB';
                                                    }
                                                    ?>
                                                </small>
                                                <small class="text-muted">
                                                    <?php echo date('M d', $item['modified']); ?>
                                                </small>
                                            </div>
                                        </div>
                                        <a href="?path=<?php echo urlencode($item['path']); ?>&download=1" 
                                           class="btn btn-sm btn-outline-primary btn-action"
                                           title="Download">
                                            <i class="bi bi-download"></i>
                                        </a>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
            
        </div>
    </div>
    
    <!-- Create Folder Modal -->
    <div class="modal fade" id="createFolderModal" tabindex="-1" aria-labelledby="createFolderModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST" action="">
                    <div class="modal-header">
                        <h5 class="modal-title" id="createFolderModalLabel">
                            <i class="bi bi-folder-plus me-2"></i>Create New Folder
                        </h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-3">
                            <label for="folder_name" class="form-label">Folder Name</label>
                            <input type="text" 
                                   class="form-control" 
                                   id="folder_name" 
                                   name="folder_name" 
                                   placeholder="Enter folder name"
                                   pattern="[a-zA-Z0-9_\-]+"
                                   title="Only letters, numbers, underscores, and hyphens allowed"
                                   required>
                            <div class="form-text">
                                Only letters, numbers, underscores (_), and hyphens (-) are allowed.
                            </div>
                        </div>
                        <div class="alert alert-info mb-0">
                            <i class="bi bi-info-circle me-2"></i>
                            <strong>Current location:</strong>
                            <code><?php echo $isRailway ? '/mnt/assets/uploads' : '/assets/uploads'; ?><?php echo $currentPath ? '/' . htmlspecialchars($currentPath) : ''; ?></code>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" name="create_folder" class="btn btn-primary">
                            <i class="bi bi-folder-plus me-2"></i>Create Folder
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <!-- Delete Folder Modal -->
    <div class="modal fade" id="deleteFolderModal" tabindex="-1" aria-labelledby="deleteFolderModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST" action="" id="deleteFolderForm">
                    <div class="modal-header bg-danger text-white">
                        <h5 class="modal-title" id="deleteFolderModalLabel">
                            <i class="bi bi-trash me-2"></i>Delete Folder
                        </h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <div class="alert alert-warning">
                            <i class="bi bi-exclamation-triangle me-2"></i>
                            <strong>Warning:</strong> This action cannot be undone!
                        </div>
                        <p class="mb-3">Are you sure you want to delete the folder <strong id="folderNameDisplay"></strong>?</p>
                        <div class="alert alert-danger mb-0" id="folderNotEmptyWarning" style="display: none;">
                            <i class="bi bi-x-circle me-2"></i>
                            <strong>Cannot Delete:</strong> This folder contains files or subfolders. Please remove all contents before deleting.
                        </div>
                        <input type="hidden" name="folder_path" id="folder_path_input">
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" name="delete_folder" class="btn btn-danger" id="confirmDeleteBtn">
                            <i class="bi bi-trash me-2"></i>Delete Folder
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <!-- Bootstrap Bundle with Popper -->
    <script src="../../assets/js/bootstrap.bundle.min.js"></script>
    
    <script>
    // Function to check if folder is empty and show confirmation modal
    function confirmDeleteFolder(folderName, folderPath) {
        const modal = new bootstrap.Modal(document.getElementById('deleteFolderModal'));
        const folderNameDisplay = document.getElementById('folderNameDisplay');
        const folderPathInput = document.getElementById('folder_path_input');
        const warningDiv = document.getElementById('folderNotEmptyWarning');
        const confirmBtn = document.getElementById('confirmDeleteBtn');
        
        // Set folder name and path
        folderNameDisplay.textContent = folderName;
        folderPathInput.value = folderPath;
        
        // Check if folder has contents by making an AJAX call
        fetch('?path=' + encodeURIComponent(folderPath))
            .then(response => response.text())
            .then(html => {
                // Simple check: if the HTML contains "No files or folders found", it's empty
                const isEmpty = html.includes('No files or folders found');
                
                if (!isEmpty) {
                    // Show warning and disable delete button
                    warningDiv.style.display = 'block';
                    confirmBtn.disabled = true;
                } else {
                    // Hide warning and enable delete button
                    warningDiv.style.display = 'none';
                    confirmBtn.disabled = false;
                }
            })
            .catch(error => {
                console.error('Error checking folder contents:', error);
                // On error, show warning to be safe
                warningDiv.style.display = 'block';
                confirmBtn.disabled = true;
            });
        
        // Show the modal
        modal.show();
    }
    </script>
    
    <!-- Admin Sidebar Script (matches other admin pages) -->
    <script src="../../assets/js/admin/sidebar.js"></script>
    
</body>
</html>
