<?php
/**
 * Debug Municipality Logo Display Issues
 * Run this to check why logos aren't showing
 */

require_once __DIR__ . '/config/database.php';

echo "<h1>🔍 Municipality Logo Debug</h1>";

// Check if logos directory exists
$logoDir = __DIR__ . '/assets/City Logos';
echo "<h2>Directory Check</h2>";
echo "<p><strong>Path:</strong> <code>$logoDir</code></p>";
echo "<p><strong>Exists:</strong> " . (is_dir($logoDir) ? '✅ YES' : '❌ NO') . "</p>";

if (is_dir($logoDir)) {
    echo "<p><strong>Readable:</strong> " . (is_readable($logoDir) ? '✅ YES' : '❌ NO') . "</p>";
    echo "<p><strong>Permissions:</strong> " . substr(sprintf('%o', fileperms($logoDir)), -4) . "</p>";
    
    // List files
    $files = scandir($logoDir);
    $imageFiles = array_filter($files, function($file) {
        return preg_match('/\.(png|jpg|jpeg|gif|svg)$/i', $file);
    });
    
    echo "<h3>Logo Files Found: " . count($imageFiles) . "</h3>";
    echo "<ul>";
    foreach ($imageFiles as $file) {
        $fullPath = $logoDir . '/' . $file;
        $size = filesize($fullPath);
        $readable = is_readable($fullPath) ? '✅' : '❌';
        echo "<li>$readable <code>$file</code> (" . number_format($size) . " bytes)</li>";
    }
    echo "</ul>";
}

// Check database logo paths
echo "<h2>Database Logo Paths</h2>";
$result = pg_query($connection, 
    "SELECT municipality_id, name, preset_logo_image, custom_logo_image, use_custom_logo 
     FROM municipalities 
     WHERE preset_logo_image IS NOT NULL OR custom_logo_image IS NOT NULL
     ORDER BY name"
);

if ($result && pg_num_rows($result) > 0) {
    echo "<table border='1' cellpadding='10' style='border-collapse: collapse; width: 100%;'>";
    echo "<tr><th>Municipality</th><th>Preset Logo</th><th>Custom Logo</th><th>Use Custom</th><th>Status</th><th>Preview</th></tr>";
    
    while ($row = pg_fetch_assoc($result)) {
        $activeLogo = $row['use_custom_logo'] === 't' && $row['custom_logo_image'] 
            ? $row['custom_logo_image'] 
            : $row['preset_logo_image'];
        
        echo "<tr>";
        echo "<td><strong>{$row['name']}</strong></td>";
        echo "<td><small>" . htmlspecialchars($row['preset_logo_image'] ?? 'null') . "</small></td>";
        echo "<td><small>" . htmlspecialchars($row['custom_logo_image'] ?? 'null') . "</small></td>";
        echo "<td>" . ($row['use_custom_logo'] === 't' ? 'Custom' : 'Preset') . "</td>";
        
        // Check if file exists
        if ($activeLogo) {
            // Handle different path formats
            if (preg_match('#^data:image/#', $activeLogo)) {
                echo "<td>✅ Base64 Data URI</td>";
                echo "<td><img src='$activeLogo' style='max-width:64px;max-height:64px;' /></td>";
            } elseif (preg_match('#^https?://#', $activeLogo)) {
                echo "<td>🌐 External URL</td>";
                echo "<td><img src='$activeLogo' style='max-width:64px;max-height:64px;' onerror=\"this.parentElement.innerHTML='❌ Failed'\"/></td>";
            } else {
                $fullPath = __DIR__ . $activeLogo;
                $exists = file_exists($fullPath);
                $readable = $exists && is_readable($fullPath);
                
                if ($exists && $readable) {
                    echo "<td>✅ File OK</td>";
                    // Build proper web path
                    $webPath = str_replace('\\', '/', $activeLogo);
                    $webPath = implode('/', array_map('rawurlencode', explode('/', $webPath)));
                    echo "<td><img src='$webPath' style='max-width:64px;max-height:64px;' onerror=\"this.parentElement.innerHTML='❌ Load Failed'\"/></td>";
                } else {
                    echo "<td>❌ File " . ($exists ? 'not readable' : 'not found') . "</td>";
                    echo "<td>-</td>";
                }
            }
        } else {
            echo "<td>⚠️ No logo set</td>";
            echo "<td>-</td>";
        }
        
        echo "</tr>";
    }
    
    echo "</table>";
} else {
    echo "<p>No municipalities with logos found in database.</p>";
}

// Check Railway environment
echo "<h2>Environment Check</h2>";
echo "<p><strong>Is Railway:</strong> " . (getenv('RAILWAY_ENVIRONMENT') ? '✅ YES' : '❌ NO (Localhost)') . "</p>";
echo "<p><strong>Document Root:</strong> <code>" . $_SERVER['DOCUMENT_ROOT'] . "</code></p>";
echo "<p><strong>Script Path:</strong> <code>" . __DIR__ . "</code></p>";

// Recommendations
echo "<h2>📋 Recommendations</h2>";
echo "<div style='background:#f0f9ff;padding:1rem;border-left:4px solid #3b82f6;'>";

if (!is_dir($logoDir)) {
    echo "<p>⚠️ <strong>Logos directory doesn't exist!</strong></p>";
    echo "<p>Create it with: <code>mkdir -p 'assets/City Logos'</code></p>";
} elseif (count($imageFiles ?? []) === 0) {
    echo "<p>⚠️ <strong>No logo files found in directory!</strong></p>";
    echo "<p>Upload logo files to: <code>$logoDir</code></p>";
}

if (getenv('RAILWAY_ENVIRONMENT')) {
    echo "<p>🚂 <strong>Running on Railway</strong></p>";
    echo "<p>Consider using a mounted volume for persistent logo storage:</p>";
    echo "<ol>";
    echo "<li>Add Volume in Railway: Mount path <code>/app/mnt</code></li>";
    echo "<li>Store logos in <code>/mnt/municipality_logos/</code></li>";
    echo "<li>Logos will persist across deploys</li>";
    echo "</ol>";
    echo "<p>See <code>LOGO_STORAGE_SOLUTION.md</code> for details.</p>";
} else {
    echo "<p>💻 <strong>Running on Localhost</strong></p>";
    echo "<p>Logos should work from <code>/assets/City Logos/</code> directory.</p>";
}

echo "</div>";

?>
