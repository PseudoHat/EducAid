<?php
// Check municipality logos in database
require_once 'config/database.php';

$query = "SELECT municipality_id, name, preset_logo_image FROM municipalities ORDER BY name";
$result = pg_query($connection, $query);

echo "<h2>Municipality Logo Configuration</h2>";
echo "<table border='1' cellpadding='5'>";
echo "<tr><th>ID</th><th>Municipality Name</th><th>Logo Path</th><th>File Exists?</th><th>Preview</th></tr>";

while ($row = pg_fetch_assoc($result)) {
    $logoPath = $row['preset_logo_image'];
    $fileExists = !empty($logoPath) && file_exists(__DIR__ . '/' . $logoPath);
    
    echo "<tr>";
    echo "<td>" . htmlspecialchars($row['municipality_id']) . "</td>";
    echo "<td><strong>" . htmlspecialchars($row['name']) . "</strong></td>";
    echo "<td><code>" . htmlspecialchars($logoPath ?: 'Not set') . "</code></td>";
    echo "<td>" . ($fileExists ? '<span style="color:green;">✓ Yes</span>' : '<span style="color:red;">✗ No</span>') . "</td>";
    echo "<td>";
    if ($fileExists) {
        echo "<img src='" . htmlspecialchars($logoPath) . "' style='max-height:50px;' />";
    } else {
        echo "-";
    }
    echo "</td>";
    echo "</tr>";
}

echo "</table>";

echo "<h3>Logo Usage in Reports:</h3>";
echo "<ul>";
echo "<li><strong>PDF Reports:</strong> Logo appears in the header (top-left, 20x20mm)</li>";
echo "<li><strong>Excel Reports:</strong> Currently shows text header only (logo could be added)</li>";
echo "<li><strong>Dynamic Selection:</strong> Logo changes automatically based on the admin's municipality or the selected filter</li>";
echo "</ul>";
?>
