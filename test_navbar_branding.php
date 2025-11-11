<?php
// Test script to verify navbar branding from theme_settings
require_once __DIR__ . '/config/database.php';

echo "<h2>Testing Navbar Branding</h2>";

// Fetch theme settings
$result = pg_query_params(
    $connection,
    "SELECT system_name, municipality_name FROM theme_settings WHERE municipality_id = $1 AND is_active = TRUE LIMIT 1",
    [1]
);

if ($result && pg_num_rows($result) > 0) {
    $theme_data = pg_fetch_assoc($result);
    echo "<h3>Current Theme Settings:</h3>";
    echo "<p><strong>System Name:</strong> " . htmlspecialchars($theme_data['system_name'] ?? 'NULL') . "</p>";
    echo "<p><strong>Municipality Name:</strong> " . htmlspecialchars($theme_data['municipality_name'] ?? 'NULL') . "</p>";
    echo "<p><strong>Brand Text (Combined):</strong> " . htmlspecialchars($theme_data['system_name']) . " • " . htmlspecialchars($theme_data['municipality_name']) . "</p>";
    
    pg_free_result($result);
} else {
    echo "<p style='color: red;'>No theme settings found in database!</p>";
    echo "<p>Using fallback values: EducAid • City of General Trias</p>";
}

// Test the navbar include
echo "<hr><h3>Testing Navbar Include:</h3>";
include __DIR__ . '/includes/website/navbar.php';
echo "<p>Navbar brand text should be: <strong>" . htmlspecialchars($brand_config['name']) . "</strong></p>";

pg_close($connection);
?>
