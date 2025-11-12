<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Navbar Branding Demo</title>
    <link rel="stylesheet" href="assets/css/bootstrap.min.css">
    <link rel="stylesheet" href="assets/css/bootstrap-icons.css">
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            padding: 2rem;
            background: #f8f9fa;
        }
        .demo-card {
            background: white;
            border-radius: 12px;
            padding: 2rem;
            margin-bottom: 2rem;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }
        .navbar-preview {
            border: 2px solid #dee2e6;
            border-radius: 8px;
            padding: 1rem;
            background: white;
            margin: 1rem 0;
        }
        .brand-text {
            font-size: 1.25rem;
            font-weight: 600;
            color: #2e7d32;
        }
        .comparison {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 2rem;
            margin-top: 2rem;
        }
        .badge-new {
            background: #10b981;
            color: white;
            padding: 0.25rem 0.75rem;
            border-radius: 4px;
            font-size: 0.75rem;
            font-weight: 600;
        }
        .badge-old {
            background: #ef4444;
            color: white;
            padding: 0.25rem 0.75rem;
            border-radius: 4px;
            font-size: 0.75rem;
            font-weight: 600;
        }
        .icon-check {
            color: #10b981;
            font-size: 1.5rem;
        }
        .icon-x {
            color: #ef4444;
            font-size: 1.5rem;
        }
        @media (max-width: 768px) {
            .comparison {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <?php
    require_once __DIR__ . '/config/database.php';
    
    // Fetch current settings
    $result = pg_query_params(
        $connection,
        "SELECT system_name, municipality_name FROM theme_settings WHERE municipality_id = $1 AND is_active = TRUE LIMIT 1",
        [1]
    );
    
    $system_name = 'EducAid';
    $municipality_name = 'City of General Trias';
    
    if ($result && pg_num_rows($result) > 0) {
        $theme_data = pg_fetch_assoc($result);
        if (!empty($theme_data['system_name'])) {
            $system_name = $theme_data['system_name'];
        }
        if (!empty($theme_data['municipality_name'])) {
            $municipality_name = $theme_data['municipality_name'];
        }
        pg_free_result($result);
    }
    
    $brand_text = $system_name . ' • ' . $municipality_name;
    pg_close($connection);
    ?>

    <div class="container">
        <h1 class="mb-4">
            <i class="bi bi-layout-text-window"></i>
            Navbar Branding System - Demo
        </h1>
        
        <div class="demo-card">
            <h2 class="mb-3">Current Configuration</h2>
            <div class="alert alert-success">
                <i class="bi bi-check-circle-fill"></i>
                <strong>System is now live with dynamic branding!</strong>
            </div>
            
            <table class="table table-bordered">
                <tr>
                    <th width="30%">System Name</th>
                    <td><code><?= htmlspecialchars($system_name) ?></code></td>
                </tr>
                <tr>
                    <th>Municipality Name</th>
                    <td><code><?= htmlspecialchars($municipality_name) ?></code></td>
                </tr>
                <tr>
                    <th>Combined Brand Text</th>
                    <td><strong class="brand-text"><?= htmlspecialchars($brand_text) ?></strong></td>
                </tr>
            </table>
        </div>

        <div class="comparison">
            <div class="demo-card">
                <h3 class="d-flex align-items-center gap-2 mb-3">
                    <i class="icon-x bi bi-x-circle-fill"></i>
                    Before Update
                    <span class="badge-old">OLD</span>
                </h3>
                
                <div class="navbar-preview">
                    <div class="d-flex align-items-center gap-2">
                        <img src="assets/images/educaid-logo.png" alt="Logo" style="height: 40px;">
                        <span class="brand-text">EducAid • City of General Trias</span>
                    </div>
                </div>
                
                <ul class="list-unstyled mt-3">
                    <li><i class="bi bi-x-circle text-danger"></i> Hardcoded in PHP</li>
                    <li><i class="bi bi-x-circle text-danger"></i> Required code changes to update</li>
                    <li><i class="bi bi-x-circle text-danger"></i> System Name field unused</li>
                    <li><i class="bi bi-x-circle text-danger"></i> Municipality Name field unused</li>
                </ul>
            </div>

            <div class="demo-card">
                <h3 class="d-flex align-items-center gap-2 mb-3">
                    <i class="icon-check bi bi-check-circle-fill"></i>
                    After Update
                    <span class="badge-new">NEW</span>
                </h3>
                
                <div class="navbar-preview">
                    <div class="d-flex align-items-center gap-2">
                        <img src="assets/images/educaid-logo.png" alt="Logo" style="height: 40px;">
                        <span class="brand-text"><?= htmlspecialchars($brand_text) ?></span>
                    </div>
                </div>
                
                <ul class="list-unstyled mt-3">
                    <li><i class="bi bi-check-circle text-success"></i> Fetched from database</li>
                    <li><i class="bi bi-check-circle text-success"></i> Editable via admin panel</li>
                    <li><i class="bi bi-check-circle text-success"></i> System Name field active</li>
                    <li><i class="bi bi-check-circle text-success"></i> Municipality Name field active</li>
                    <li><i class="bi bi-check-circle text-success"></i> Changes reflect immediately</li>
                </ul>
            </div>
        </div>

        <div class="demo-card">
            <h3 class="mb-3"><i class="bi bi-gear-fill"></i> How to Update</h3>
            <ol>
                <li>Navigate to <strong>Admin Panel</strong> → <strong>Website CMS</strong> → <strong>Topbar Settings</strong></li>
                <li>Edit the <strong>System Name</strong> field (e.g., "EducAid")</li>
                <li>Edit the <strong>Municipality Name</strong> field (e.g., "City of General Trias")</li>
                <li>Click <strong>Save Changes</strong></li>
                <li>Visit any website page to see the updated branding in the navbar</li>
            </ol>
            
            <div class="alert alert-info mt-3">
                <i class="bi bi-lightbulb-fill"></i>
                <strong>Pro Tip:</strong> The format is automatically set as <code>System Name • Municipality Name</code> with a bullet separator.
            </div>
        </div>

        <div class="demo-card">
            <h3 class="mb-3"><i class="bi bi-code-square"></i> Technical Implementation</h3>
            <p>The navbar now queries the database on every page load:</p>
            <pre class="bg-light p-3 rounded"><code>SELECT system_name, municipality_name 
FROM theme_settings 
WHERE municipality_id = 1 AND is_active = TRUE 
LIMIT 1;</code></pre>
            
            <p class="mt-3">The fetched values are combined and displayed in the navigation bar brand section.</p>
        </div>

        <div class="demo-card">
            <h3 class="mb-3"><i class="bi bi-box-arrow-up-right"></i> Live Demo Links</h3>
            <div class="d-flex gap-2 flex-wrap">
                <a href="website/landingpage.php" class="btn btn-success">
                    <i class="bi bi-house-fill"></i> View Landing Page
                </a>
                <a href="modules/admin/topbar_settings.php" class="btn btn-primary">
                    <i class="bi bi-gear-fill"></i> Edit Topbar Settings
                </a>
                <a href="website/about.php" class="btn btn-info">
                    <i class="bi bi-info-circle-fill"></i> View About Page
                </a>
            </div>
        </div>
    </div>

    <script src="assets/js/bootstrap.bundle.min.js"></script>
</body>
</html>
