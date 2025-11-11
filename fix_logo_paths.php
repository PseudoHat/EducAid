<?php
/**
 * Fix Logo Paths - Update Database to Match Current File Locations
 * Run this on Railway to switch between /app/assets and /mnt/assets
 */

require_once __DIR__ . '/config/database.php';

echo "<!DOCTYPE html>
<html lang='en'>
<head>
    <meta charset='UTF-8'>
    <meta name='viewport' content='width=device-width, initial-scale=1.0'>
    <title>Fix Municipality Logo Paths</title>
    <style>
        body { font-family: 'Segoe UI', Tahoma, sans-serif; padding: 2rem; background: #f8f9fa; }
        .container { max-width: 1000px; margin: 0 auto; background: white; padding: 2rem; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        h1 { color: #2d3748; }
        .info { background: #f0f9ff; padding: 1rem; border-left: 4px solid #3b82f6; margin: 1rem 0; }
        .success { background: #d1fae5; padding: 1rem; border-left: 4px solid #10b981; margin: 1rem 0; }
        .error { background: #fee; padding: 1rem; border-left: 4px solid #dc2626; margin: 1rem 0; }
        .warning { background: #fef3cd; padding: 1rem; border-left: 4px solid #f59e0b; margin: 1rem 0; }
        .btn { background: #3b82f6; color: white; border: none; padding: 0.75rem 1.5rem; border-radius: 4px; font-size: 1rem; cursor: pointer; margin: 0.5rem; text-decoration: none; display: inline-block; }
        .btn-success { background: #10b981; }
        .btn-warning { background: #f59e0b; }
        table { width: 100%; border-collapse: collapse; margin: 1rem 0; font-size: 0.875rem; }
        th, td { padding: 0.5rem; text-align: left; border-bottom: 1px solid #e5e7eb; }
        th { background: #f3f4f6; font-weight: 600; }
        code { background: #f3f4f6; padding: 0.2rem 0.4rem; border-radius: 3px; }
    </style>
</head>
<body>
<div class='container'>";

echo "<h1>🔧 Fix Municipality Logo Paths</h1>";

$isRailway = (bool) getenv('RAILWAY_ENVIRONMENT');
echo "<div class='" . ($isRailway ? 'info' : 'warning') . "'>";
echo "<p><strong>Environment:</strong> " . ($isRailway ? "🚂 Railway" : "💻 Localhost") . "</p>";
echo "<p><strong>Script Path:</strong> <code>" . __DIR__ . "</code></p>";
echo "</div>";

// Check where files actually exist
$appPath = __DIR__ . '/assets/City Logos';
$mntPath = __DIR__ . '/mnt/assets/City Logos';

$appExists = is_dir($appPath);
$mntExists = is_dir($mntPath);

$appFiles = $appExists ? count(array_filter(scandir($appPath), function($f) { 
    return preg_match('/\.(png|jpg|jpeg|gif|svg)$/i', $f); 
})) : 0;

$mntFiles = $mntExists ? count(array_filter(scandir($mntPath), function($f) { 
    return preg_match('/\.(png|jpg|jpeg|gif|svg)$/i', $f); 
})) : 0;

echo "<h2>📁 File System Status</h2>";
echo "<table>";
echo "<thead><tr><th>Location</th><th>Path</th><th>Exists?</th><th>Logo Files</th><th>Type</th></tr></thead>";
echo "<tbody>";
echo "<tr>";
echo "<td><strong>/app/assets/</strong></td>";
echo "<td><code>$appPath</code></td>";
echo "<td>" . ($appExists ? '✅ Yes' : '❌ No') . "</td>";
echo "<td>" . ($appFiles > 0 ? "✅ $appFiles files" : '❌ 0 files') . "</td>";
echo "<td><span style='color:#dc2626;'>⚠️ Ephemeral (wiped on deploy)</span></td>";
echo "</tr>";
echo "<tr>";
echo "<td><strong>/mnt/assets/</strong></td>";
echo "<td><code>$mntPath</code></td>";
echo "<td>" . ($mntExists ? '✅ Yes' : '❌ No') . "</td>";
echo "<td>" . ($mntFiles > 0 ? "✅ $mntFiles files" : '❌ 0 files') . "</td>";
echo "<td><span style='color:#10b981;'>✅ Persistent (survives deploy)</span></td>";
echo "</tr>";
echo "</tbody></table>";

// Get current database state
$result = pg_query($connection, "
    SELECT municipality_id, name, preset_logo_image, use_custom_logo
    FROM municipalities 
    WHERE preset_logo_image IS NOT NULL
    ORDER BY municipality_id
");

$dbPaths = [];
while ($row = pg_fetch_assoc($result)) {
    $dbPaths[] = $row;
}
pg_free_result($result);

echo "<h2>🗄️ Current Database Paths</h2>";
echo "<table>";
echo "<thead><tr><th>Municipality</th><th>Current Path</th><th>Using</th></tr></thead>";
echo "<tbody>";

$usingApp = 0;
$usingMnt = 0;

foreach ($dbPaths as $row) {
    if ($row['use_custom_logo'] === 't') continue; // Skip custom logos
    
    if (strpos($row['preset_logo_image'], '/mnt/') === 0) {
        $usingMnt++;
    } elseif (strpos($row['preset_logo_image'], '/app/') === 0 || 
              strpos($row['preset_logo_image'], '/assets/') === 0) {
        $usingApp++;
    }
    
    echo "<tr>";
    echo "<td>{$row['name']}</td>";
    echo "<td><small><code>" . htmlspecialchars($row['preset_logo_image']) . "</code></small></td>";
    
    if (strpos($row['preset_logo_image'], '/mnt/') === 0) {
        echo "<td><span style='color:#10b981;'>✅ /mnt/ (persistent)</span></td>";
    } else {
        echo "<td><span style='color:#dc2626;'>⚠️ /app/ (ephemeral)</span></td>";
    }
    echo "</tr>";
}
echo "</tbody></table>";

echo "<div class='info'>";
echo "<p>📊 <strong>Summary:</strong> $usingApp using /app/, $usingMnt using /mnt/</p>";
echo "</div>";

// Handle fix action
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];
    
    echo "<hr><h2>🔄 Applying Fix...</h2>";
    
    pg_query($connection, "BEGIN");
    
    if ($action === 'use_app') {
        // Update all paths to use /app/assets/
        $updateQuery = "
            UPDATE municipalities 
            SET preset_logo_image = REPLACE(preset_logo_image, '/mnt/assets/', '/app/assets/')
            WHERE preset_logo_image LIKE '/mnt/assets/%'
        ";
        
        $result = pg_query($connection, $updateQuery);
        $affected = pg_affected_rows($result);
        
        if ($result) {
            pg_query($connection, "COMMIT");
            echo "<div class='success'>";
            echo "<p>✅ <strong>Updated $affected municipalities to use /app/assets/</strong></p>";
            echo "<p>⚠️ <strong>Warning:</strong> These logos will be lost on next Railway deploy!</p>";
            echo "<p>💡 <strong>Recommendation:</strong> Copy logos to /mnt/ for permanent storage.</p>";
            echo "</div>";
        } else {
            pg_query($connection, "ROLLBACK");
            echo "<div class='error'><p>❌ Failed: " . pg_last_error($connection) . "</p></div>";
        }
        
    } elseif ($action === 'use_mnt') {
        // Update all paths to use /mnt/assets/
        $updateQuery = "
            UPDATE municipalities 
            SET preset_logo_image = REPLACE(preset_logo_image, '/app/assets/', '/mnt/assets/')
            WHERE preset_logo_image LIKE '/app/assets/%' OR preset_logo_image LIKE '/assets/%'
        ";
        
        $result = pg_query($connection, $updateQuery);
        $affected = pg_affected_rows($result);
        
        if ($result) {
            pg_query($connection, "COMMIT");
            echo "<div class='success'>";
            echo "<p>✅ <strong>Updated $affected municipalities to use /mnt/assets/</strong></p>";
            
            if ($mntFiles === 0) {
                echo "<p>⚠️ <strong>Warning:</strong> No files found in /mnt/assets/City Logos/</p>";
                echo "<p>💡 <strong>Next step:</strong> Upload logos to /mnt/ using upload tool.</p>";
            } else {
                echo "<p>✅ Logos should now work from persistent storage!</p>";
            }
            echo "</div>";
        } else {
            pg_query($connection, "ROLLBACK");
            echo "<div class='error'><p>❌ Failed: " . pg_last_error($connection) . "</p></div>";
        }
        
    } elseif ($action === 'fix_gentri') {
        // Fix General Trias custom logo issue
        $updateQuery = "
            UPDATE municipalities 
            SET use_custom_logo = FALSE,
                preset_logo_image = '/app/assets/City Logos/General_Trias_City_Logo.png'
            WHERE name = 'City of General Trias'
        ";
        
        $result = pg_query($connection, $updateQuery);
        
        if ($result) {
            pg_query($connection, "COMMIT");
            echo "<div class='success'>";
            echo "<p>✅ <strong>Fixed General Trias!</strong> Now using preset logo from /app/assets/</p>";
            echo "</div>";
        } else {
            pg_query($connection, "ROLLBACK");
            echo "<div class='error'><p>❌ Failed: " . pg_last_error($connection) . "</p></div>";
        }
    }
    
    echo "<p><a href='debug_municipality_logos.php' class='btn'>🔍 View Debug Page</a></p>";
    echo "<p><a href='fix_logo_paths.php' class='btn'>🔄 Refresh This Page</a></p>";
    
} else {
    // Show options
    echo "<h2>🛠️ Fix Options</h2>";
    
    echo "<div class='warning'>";
    echo "<h4>⚡ Quick Fix (Temporary)</h4>";
    echo "<p>Use logos from <code>/app/assets/City Logos/</code> (current location with files)</p>";
    echo "<p><strong>Pros:</strong> Works immediately, all 23 logos are there</p>";
    echo "<p><strong>Cons:</strong> ⚠️ Lost on next Railway deploy</p>";
    echo "<form method='post' style='display:inline;'>";
    echo "<button type='submit' name='action' value='use_app' class='btn btn-warning'>";
    echo "⚡ Use /app/assets/ (Quick Fix)";
    echo "</button>";
    echo "</form>";
    echo "</div>";
    
    echo "<div class='info'>";
    echo "<h4>✅ Permanent Fix (Recommended)</h4>";
    echo "<p>Use logos from <code>/mnt/assets/City Logos/</code> (persistent volume)</p>";
    echo "<p><strong>Pros:</strong> ✅ Persists across deploys, permanent solution</p>";
    echo "<p><strong>Cons:</strong> " . ($mntFiles === 0 ? "⚠️ Need to upload 23 files first" : "✅ Files already there!") . "</p>";
    
    if ($mntFiles === 0) {
        echo "<p><a href='upload_logos_to_railway.php' class='btn btn-success'>📤 Upload Logos to /mnt/</a></p>";
        echo "<p><em>After uploading, come back here and click the button below:</em></p>";
    }
    
    echo "<form method='post' style='display:inline;'>";
    echo "<button type='submit' name='action' value='use_mnt' class='btn btn-success'>";
    echo "✅ Use /mnt/assets/ (Permanent)";
    echo "</button>";
    echo "</form>";
    echo "</div>";
    
    echo "<div class='error'>";
    echo "<h4>🔧 Fix General Trias Specifically</h4>";
    echo "<p>General Trias has a broken custom logo. Switch it back to preset.</p>";
    echo "<form method='post' style='display:inline;'>";
    echo "<button type='submit' name='action' value='fix_gentri' class='btn'>";
    echo "🔧 Fix General Trias Logo";
    echo "</button>";
    echo "</form>";
    echo "</div>";
}

echo "</div></body></html>";
?>
