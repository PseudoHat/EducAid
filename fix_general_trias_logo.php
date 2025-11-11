<?php
/**
 * Fix General Trias Logo - Switch from broken custom to working preset
 */

require_once __DIR__ . '/config/database.php';

echo "<h1>🔧 Fixing General Trias Logo</h1>";

// Find General Trias municipality
$result = pg_query($connection, "
    SELECT municipality_id, name, slug, 
           preset_logo_image, custom_logo_image, use_custom_logo
    FROM municipalities 
    WHERE name ILIKE '%general%trias%'
");

if (!$result || pg_num_rows($result) === 0) {
    echo "<p>❌ General Trias municipality not found in database!</p>";
    exit;
}

$municipality = pg_fetch_assoc($result);
pg_free_result($result);

echo "<h2>Current State</h2>";
echo "<table border='1' cellpadding='10' style='border-collapse: collapse;'>";
echo "<tr><th>Field</th><th>Value</th></tr>";
echo "<tr><td><strong>Municipality ID</strong></td><td>{$municipality['municipality_id']}</td></tr>";
echo "<tr><td><strong>Name</strong></td><td>{$municipality['name']}</td></tr>";
echo "<tr><td><strong>Slug</strong></td><td>{$municipality['slug']}</td></tr>";
echo "<tr><td><strong>Preset Logo</strong></td><td>" . ($municipality['preset_logo_image'] ?: '<em>null</em>') . "</td></tr>";
echo "<tr><td><strong>Custom Logo</strong></td><td>" . ($municipality['custom_logo_image'] ?: '<em>null</em>') . "</td></tr>";
echo "<tr><td><strong>Use Custom?</strong></td><td>" . ($municipality['use_custom_logo'] === 't' ? '✅ YES' : '❌ NO') . "</td></tr>";
echo "</table>";

// Check if preset logo file exists
$presetLogoPath = __DIR__ . '/assets/City Logos/General_Trias_City_Logo.png';
$presetExists = file_exists($presetLogoPath);

echo "<h2>Logo File Status</h2>";
echo "<ul>";
echo "<li><strong>Preset Logo File:</strong> " . ($presetExists ? "✅ EXISTS ($presetLogoPath)" : "❌ NOT FOUND") . "</li>";

if ($municipality['custom_logo_image']) {
    $customLogoPath = __DIR__ . $municipality['custom_logo_image'];
    $customExists = file_exists($customLogoPath);
    echo "<li><strong>Custom Logo File:</strong> " . ($customExists ? "✅ EXISTS ($customLogoPath)" : "❌ NOT FOUND ($customLogoPath)") . "</li>";
}
echo "</ul>";

// Determine what to do
echo "<h2>🔍 Diagnosis</h2>";

if ($municipality['use_custom_logo'] === 't' && !empty($municipality['custom_logo_image'])) {
    $customPath = __DIR__ . $municipality['custom_logo_image'];
    if (!file_exists($customPath)) {
        echo "<div style='background:#fee;padding:1rem;border-left:4px solid #c00;'>";
        echo "<p>❌ <strong>Problem Found:</strong> Using custom logo but file doesn't exist!</p>";
        echo "<p><code>{$municipality['custom_logo_image']}</code></p>";
        echo "</div>";
    }
} elseif (empty($municipality['preset_logo_image'])) {
    echo "<div style='background:#fef3cd;padding:1rem;border-left:4px solid #f90;'>";
    echo "<p>⚠️ <strong>Problem:</strong> No preset logo path set in database!</p>";
    echo "</div>";
}

// Offer fix
echo "<h2>💡 Solution</h2>";

if (!$presetExists) {
    echo "<div style='background:#fef3cd;padding:1rem;border-left:4px solid #f90;'>";
    echo "<p>⚠️ Preset logo file not found at: <code>$presetLogoPath</code></p>";
    echo "<p>Please ensure the file exists before proceeding.</p>";
    echo "</div>";
} else {
    echo "<form method='post' style='background:#f0f9ff;padding:1.5rem;border-left:4px solid #3b82f6;'>";
    echo "<p><strong>Recommended Action:</strong></p>";
    echo "<ol>";
    echo "<li>Set preset logo path to: <code>/assets/City Logos/General_Trias_City_Logo.png</code></li>";
    echo "<li>Switch to using preset logo (disable custom)</li>";
    echo "<li>Keep custom logo record for reference</li>";
    echo "</ol>";
    
    echo "<button type='submit' name='fix' value='preset' style='background:#3b82f6;color:white;border:none;padding:0.75rem 1.5rem;border-radius:4px;font-size:1rem;cursor:pointer;'>";
    echo "✅ Fix Now - Use Preset Logo";
    echo "</button>";
    
    echo "<button type='submit' name='fix' value='clear_custom' style='background:#dc2626;color:white;border:none;padding:0.75rem 1.5rem;border-radius:4px;font-size:1rem;cursor:pointer;margin-left:1rem;'>";
    echo "🗑️ Clear Custom Logo (Use Preset)";
    echo "</button>";
    echo "</form>";
}

// Handle fix action
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['fix'])) {
    $action = $_POST['fix'];
    
    echo "<hr><h2>🔧 Applying Fix...</h2>";
    
    if ($action === 'preset') {
        // Update to use preset logo
        $updateQuery = "
            UPDATE municipalities 
            SET preset_logo_image = '/assets/City Logos/General_Trias_City_Logo.png',
                use_custom_logo = FALSE,
                updated_at = NOW()
            WHERE municipality_id = $1
        ";
        
        $updateResult = pg_query_params($connection, $updateQuery, [$municipality['municipality_id']]);
        
        if ($updateResult) {
            echo "<div style='background:#d1fae5;padding:1rem;border-left:4px solid #10b981;'>";
            echo "<p>✅ <strong>SUCCESS!</strong> General Trias now uses preset logo.</p>";
            echo "<p>Preview: <img src='/assets/City Logos/General_Trias_City_Logo.png' style='max-width:200px;max-height:100px;' /></p>";
            echo "<p><a href='debug_municipality_logos.php' style='color:#3b82f6;'>← Back to Debug Page</a></p>";
            echo "</div>";
        } else {
            echo "<div style='background:#fee;padding:1rem;border-left:4px solid #c00;'>";
            echo "<p>❌ <strong>Failed to update database:</strong> " . pg_last_error($connection) . "</p>";
            echo "</div>";
        }
        
    } elseif ($action === 'clear_custom') {
        // Clear custom logo entirely
        $updateQuery = "
            UPDATE municipalities 
            SET preset_logo_image = '/assets/City Logos/General_Trias_City_Logo.png',
                custom_logo_image = NULL,
                use_custom_logo = FALSE,
                updated_at = NOW()
            WHERE municipality_id = $1
        ";
        
        $updateResult = pg_query_params($connection, $updateQuery, [$municipality['municipality_id']]);
        
        if ($updateResult) {
            echo "<div style='background:#d1fae5;padding:1rem;border-left:4px solid #10b981;'>";
            echo "<p>✅ <strong>SUCCESS!</strong> Custom logo cleared. Now using preset logo.</p>";
            echo "<p>Preview: <img src='/assets/City Logos/General_Trias_City_Logo.png' style='max-width:200px;max-height:100px;' /></p>";
            echo "<p><a href='debug_municipality_logos.php' style='color:#3b82f6;'>← Back to Debug Page</a></p>";
            echo "</div>";
        } else {
            echo "<div style='background:#fee;padding:1rem;border-left:4px solid #c00;'>";
            echo "<p>❌ <strong>Failed to update database:</strong> " . pg_last_error($connection) . "</p>";
            echo "</div>";
        }
    }
}
?>
