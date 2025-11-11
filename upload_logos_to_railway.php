<?php
/**
 * Upload Logos to Railway Volume via Web Interface
 * This creates /mnt/assets/City Logos/ and uploads logos one by one
 */

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/permissions.php';

// Security check - Super Admin only
if (!isset($_SESSION['admin_id'])) {
    header('Location: unified_login.php');
    exit;
}

$adminRole = getCurrentAdminRole($connection);
if ($adminRole !== 'super_admin') {
    die('Access denied. Super Admin only.');
}

$isRailway = (bool) getenv('RAILWAY_ENVIRONMENT');
$volumePath = $isRailway ? '/mnt/assets/City Logos' : __DIR__ . '/assets/City Logos';

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Upload Municipality Logos to Railway Volume</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <style>
        body { background: #f8f9fa; padding: 2rem 0; }
        .upload-card { margin-bottom: 1rem; }
        .status-badge { font-size: 0.875rem; }
        .preview-img { max-width: 64px; max-height: 64px; object-fit: contain; }
    </style>
</head>
<body>
<div class="container">
    <div class="row">
        <div class="col-12">
            <h1><i class="bi bi-cloud-upload"></i> Upload Municipality Logos to Railway</h1>
            
            <div class="alert <?= $isRailway ? 'alert-info' : 'alert-warning' ?>">
                <strong>Environment:</strong> <?= $isRailway ? '🚂 Railway Production' : '💻 Localhost' ?><br>
                <strong>Target Path:</strong> <code><?= htmlspecialchars($volumePath) ?></code><br>
                <strong>Directory Exists:</strong> <?= is_dir($volumePath) ? '✅ Yes' : '❌ No (will be created)' ?>
            </div>

            <?php if (!$isRailway): ?>
                <div class="alert alert-warning">
                    <i class="bi bi-exclamation-triangle"></i> <strong>Warning:</strong> You're on localhost. 
                    Deploy this file to Railway to upload logos to the volume.
                </div>
            <?php endif; ?>

            <div class="card mb-4">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0"><i class="bi bi-folder-plus"></i> Step 1: Create Directory</h5>
                </div>
                <div class="card-body">
                    <p>First, create the logo directory in the Railway volume.</p>
                    <button id="createDirBtn" class="btn btn-primary">
                        <i class="bi bi-folder-plus"></i> Create /mnt/assets/City Logos/
                    </button>
                    <div id="dirResult" class="mt-3"></div>
                </div>
            </div>

            <div class="card mb-4">
                <div class="card-header bg-success text-white">
                    <h5 class="mb-0"><i class="bi bi-upload"></i> Step 2: Upload Logos</h5>
                </div>
                <div class="card-body">
                    <p>Upload all 23 municipality logo files.</p>
                    
                    <div class="mb-3">
                        <label for="logoFiles" class="form-label">Select Logo Files (23 files):</label>
                        <input type="file" class="form-control" id="logoFiles" multiple accept="image/*">
                        <div class="form-text">
                            Select all files from your local <code>/assets/City Logos/</code> folder.
                            Expected: Alfonso_Logo.png, Amadeo_Logo.png, etc.
                        </div>
                    </div>
                    
                    <button id="uploadBtn" class="btn btn-success" disabled>
                        <i class="bi bi-upload"></i> Upload Selected Files
                    </button>
                    
                    <div id="uploadProgress" class="mt-3"></div>
                    <div id="uploadResults" class="mt-3"></div>
                </div>
            </div>

            <div class="card">
                <div class="card-header bg-info text-white">
                    <h5 class="mb-0"><i class="bi bi-database"></i> Step 3: Update Database</h5>
                </div>
                <div class="card-body">
                    <p>Update database to use the uploaded logos from the volume.</p>
                    <button id="updateDbBtn" class="btn btn-info" disabled>
                        <i class="bi bi-database-fill-check"></i> Update Database Paths
                    </button>
                    <div id="dbResult" class="mt-3"></div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
const isRailway = <?= json_encode($isRailway) ?>;
const volumePath = <?= json_encode($volumePath) ?>;

// Step 1: Create Directory
document.getElementById('createDirBtn').addEventListener('click', async function() {
    const btn = this;
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Creating...';
    
    try {
        const response = await fetch('ajax_create_logo_directory.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({path: volumePath})
        });
        
        const result = await response.json();
        
        const resultDiv = document.getElementById('dirResult');
        if (result.success) {
            resultDiv.innerHTML = `
                <div class="alert alert-success">
                    <i class="bi bi-check-circle"></i> <strong>Success!</strong> ${result.message}
                    <br><small>Path: <code>${result.path}</code></small>
                </div>
            `;
            document.getElementById('uploadBtn').disabled = false;
        } else {
            resultDiv.innerHTML = `
                <div class="alert alert-danger">
                    <i class="bi bi-x-circle"></i> <strong>Error:</strong> ${result.message}
                </div>
            `;
        }
    } catch (error) {
        document.getElementById('dirResult').innerHTML = `
            <div class="alert alert-danger">
                <i class="bi bi-x-circle"></i> <strong>Network Error:</strong> ${error.message}
            </div>
        `;
    } finally {
        btn.disabled = false;
        btn.innerHTML = '<i class="bi bi-folder-plus"></i> Create /mnt/assets/City Logos/';
    }
});

// Step 2: Handle file selection
document.getElementById('logoFiles').addEventListener('change', function(e) {
    const files = e.target.files;
    const uploadBtn = document.getElementById('uploadBtn');
    
    if (files.length > 0) {
        uploadBtn.disabled = false;
        uploadBtn.innerHTML = `<i class="bi bi-upload"></i> Upload ${files.length} Files`;
    } else {
        uploadBtn.disabled = true;
    }
});

// Step 2: Upload Files
document.getElementById('uploadBtn').addEventListener('click', async function() {
    const files = document.getElementById('logoFiles').files;
    if (files.length === 0) {
        alert('Please select files to upload');
        return;
    }
    
    const btn = this;
    btn.disabled = true;
    
    const progressDiv = document.getElementById('uploadProgress');
    const resultsDiv = document.getElementById('uploadResults');
    
    progressDiv.innerHTML = `
        <div class="progress">
            <div id="progressBar" class="progress-bar progress-bar-striped progress-bar-animated" 
                 role="progressbar" style="width: 0%">0 / ${files.length}</div>
        </div>
    `;
    
    resultsDiv.innerHTML = '<h6>Upload Results:</h6>';
    
    let uploaded = 0;
    let failed = 0;
    
    for (let i = 0; i < files.length; i++) {
        const file = files[i];
        const formData = new FormData();
        formData.append('logo', file);
        formData.append('targetPath', volumePath);
        
        try {
            const response = await fetch('ajax_upload_logo_to_volume.php', {
                method: 'POST',
                body: formData
            });
            
            const result = await response.json();
            
            const statusClass = result.success ? 'success' : 'danger';
            const icon = result.success ? 'check-circle-fill' : 'x-circle-fill';
            
            resultsDiv.innerHTML += `
                <div class="alert alert-${statusClass} alert-sm py-2">
                    <i class="bi bi-${icon}"></i> <strong>${file.name}:</strong> ${result.message}
                </div>
            `;
            
            if (result.success) uploaded++;
            else failed++;
            
        } catch (error) {
            resultsDiv.innerHTML += `
                <div class="alert alert-danger alert-sm py-2">
                    <i class="bi bi-x-circle-fill"></i> <strong>${file.name}:</strong> Network error
                </div>
            `;
            failed++;
        }
        
        // Update progress
        const progressPct = Math.round(((i + 1) / files.length) * 100);
        document.getElementById('progressBar').style.width = progressPct + '%';
        document.getElementById('progressBar').textContent = `${i + 1} / ${files.length}`;
    }
    
    progressDiv.innerHTML = `
        <div class="alert alert-info">
            <i class="bi bi-info-circle"></i> <strong>Complete!</strong> 
            ${uploaded} uploaded, ${failed} failed
        </div>
    `;
    
    if (uploaded > 0) {
        document.getElementById('updateDbBtn').disabled = false;
    }
    
    btn.disabled = false;
    btn.innerHTML = '<i class="bi bi-upload"></i> Upload More Files';
});

// Step 3: Update Database
document.getElementById('updateDbBtn').addEventListener('click', async function() {
    const btn = this;
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Updating...';
    
    try {
        const response = await fetch('ajax_update_logo_paths.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({volumePath: volumePath})
        });
        
        const result = await response.json();
        
        const resultDiv = document.getElementById('dbResult');
        if (result.success) {
            resultDiv.innerHTML = `
                <div class="alert alert-success">
                    <i class="bi bi-check-circle"></i> <strong>Success!</strong> ${result.message}
                    <br><small>Updated ${result.updated} municipalities</small>
                </div>
            `;
        } else {
            resultDiv.innerHTML = `
                <div class="alert alert-danger">
                    <i class="bi bi-x-circle"></i> <strong>Error:</strong> ${result.message}
                </div>
            `;
        }
    } catch (error) {
        document.getElementById('dbResult').innerHTML = `
            <div class="alert alert-danger">
                <i class="bi bi-x-circle"></i> <strong>Network Error:</strong> ${error.message}
            </div>
        `;
    } finally {
        btn.disabled = false;
        btn.innerHTML = '<i class="bi bi-database-fill-check"></i> Update Database Paths';
    }
});
</script>
</body>
</html>
