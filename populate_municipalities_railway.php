<?php
/**
 * Populate Railway Municipalities Table
 * Run this on Railway to insert all 23 municipalities with logos
 */

require_once __DIR__ . '/config/database.php';

echo "<!DOCTYPE html>
<html lang='en'>
<head>
    <meta charset='UTF-8'>
    <meta name='viewport' content='width=device-width, initial-scale=1.0'>
    <title>Populate Municipalities - Railway</title>
    <style>
        body { font-family: 'Segoe UI', Tahoma, sans-serif; padding: 2rem; background: #f8f9fa; }
        .container { max-width: 1200px; margin: 0 auto; background: white; padding: 2rem; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        h1 { color: #2d3748; margin-bottom: 0.5rem; }
        .info { background: #f0f9ff; padding: 1rem; border-left: 4px solid #3b82f6; margin: 1rem 0; }
        .success { background: #d1fae5; padding: 1rem; border-left: 4px solid #10b981; margin: 1rem 0; }
        .error { background: #fee; padding: 1rem; border-left: 4px solid #dc2626; margin: 1rem 0; }
        .warning { background: #fef3cd; padding: 1rem; border-left: 4px solid #f59e0b; margin: 1rem 0; }
        table { width: 100%; border-collapse: collapse; margin: 1rem 0; }
        th, td { padding: 0.75rem; text-align: left; border-bottom: 1px solid #e5e7eb; }
        th { background: #f3f4f6; font-weight: 600; }
        tr:hover { background: #f9fafb; }
        .btn { background: #3b82f6; color: white; border: none; padding: 0.75rem 1.5rem; border-radius: 4px; font-size: 1rem; cursor: pointer; text-decoration: none; display: inline-block; }
        .btn:hover { background: #2563eb; }
        .btn-danger { background: #dc2626; }
        .btn-danger:hover { background: #b91c1c; }
        .status { display: inline-block; padding: 0.25rem 0.75rem; border-radius: 12px; font-size: 0.875rem; font-weight: 500; }
        .status-new { background: #dbeafe; color: #1e40af; }
        .status-updated { background: #fef3c7; color: #92400e; }
        .status-unchanged { background: #e5e7eb; color: #4b5563; }
        .status-error { background: #fee2e2; color: #991b1b; }
        code { background: #f3f4f6; padding: 0.25rem 0.5rem; border-radius: 4px; font-family: 'Courier New', monospace; }
    </style>
</head>
<body>
<div class='container'>";

echo "<h1>🏛️ Populate Municipalities - Railway Database</h1>";

// Check environment
$isRailway = (bool) getenv('RAILWAY_ENVIRONMENT');
echo "<div class='" . ($isRailway ? 'info' : 'warning') . "'>";
echo "<p><strong>Environment:</strong> " . ($isRailway ? "🚂 Railway Production" : "💻 Localhost/Development") . "</p>";
echo "<p><strong>Database:</strong> " . pg_dbname($connection) . "</p>";
echo "<p><strong>Host:</strong> " . pg_host($connection) . "</p>";
echo "</div>";

// Check current state
$countResult = pg_query($connection, "SELECT COUNT(*) as total FROM municipalities");
$currentCount = pg_fetch_assoc($countResult)['total'] ?? 0;
pg_free_result($countResult);

echo "<div class='info'>";
echo "<p>📊 <strong>Current municipalities in database:</strong> $currentCount</p>";
echo "</div>";

// Define all municipalities
$municipalities = [
    // CITIES (IDs 1-8)
    [1, 'City of General Trias', 'general-trias', 'city', 6, '/assets/City Logos/General_Trias_City_Logo.png'],
    [2, 'City of Dasmariñas', 'dasmarinas', 'city', 4, '/assets/City Logos/Dasma_City_Logo.png'],
    [3, 'City of Imus', 'imus', 'city', 3, '/assets/City Logos/Imus_City_Logo.png'],
    [4, 'City of Bacoor', 'bacoor', 'city', 2, '/assets/City Logos/Bacoor_City_Logo.png'],
    [5, 'Cavite City', 'cavite-city', 'city', 1, '/assets/City Logos/Cavite_City_Logo.png'],
    [6, 'Trece Martires City', 'trece-martires', 'city', 7, '/assets/City Logos/Trece_Martires_City_Logo.png'],
    [7, 'Tagaytay City', 'tagaytay', 'city', 8, '/assets/City Logos/Tagaytay_City_Logo.png'],
    [8, 'City of Carmona', 'carmona', 'city', 5, '/assets/City Logos/Carmona_Cavite_Logo.png'],
    
    // MUNICIPALITIES (IDs 101-115)
    [101, 'Kawit', 'kawit', 'municipality', 1, '/assets/City Logos/Kawit_Logo.png'],
    [102, 'Noveleta', 'noveleta', 'municipality', 1, '/assets/City Logos/Noveleta_Logo.png'],
    [103, 'Rosario', 'rosario', 'municipality', 1, '/assets/City Logos/Rosario_Logo.jpg'],
    [104, 'General Mariano Alvarez', 'general-mariano-alvarez', 'municipality', 5, '/assets/City Logos/General_Mariano_Alvarez_Logo.png'],
    [105, 'Silang', 'silang', 'municipality', 5, '/assets/City Logos/Silang_City_Logo.png'],
    [106, 'Amadeo', 'amadeo', 'municipality', 7, '/assets/City Logos/Amadeo_Logo.png'],
    [107, 'Indang', 'indang', 'municipality', 7, '/assets/City Logos/Indang_Logo.png'],
    [108, 'Tanza', 'tanza', 'municipality', 7, '/assets/City Logos/Tanza,_Cavite_Logo.png'],
    [109, 'Alfonso', 'alfonso', 'municipality', 8, '/assets/City Logos/Alfonso_Logo.png'],
    [110, 'General Emilio Aguinaldo', 'general-emilio-aguinaldo', 'municipality', 8, '/assets/City Logos/Gen_Emilio_Aguinaldo_Logo.png'],
    [111, 'Magallanes', 'magallanes', 'municipality', 8, '/assets/City Logos/Magallanes_Logo.png'],
    [112, 'Maragondon', 'maragondon', 'municipality', 8, '/assets/City Logos/Maragondon_Logo.png'],
    [113, 'Mendez-Nuñez', 'mendez-nunez', 'municipality', 8, '/assets/City Logos/Mendez_Logo.png'],
    [114, 'Naic', 'naic', 'municipality', 8, '/assets/City Logos/Naic_Logo.png'],
    [115, 'Ternate', 'ternate', 'municipality', 8, '/assets/City Logos/Ternate_Logo.png'],
];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['populate'])) {
    echo "<hr><h2>🔄 Running Migration...</h2>";
    
    // Start transaction
    pg_query($connection, "BEGIN");
    
    $results = [];
    $inserted = 0;
    $updated = 0;
    $unchanged = 0;
    $errors = 0;
    
    foreach ($municipalities as $muni) {
        list($id, $name, $slug, $type, $district, $logo) = $muni;
        
        // Check if exists
        $checkQuery = "SELECT municipality_id, name, preset_logo_image FROM municipalities WHERE municipality_id = $1";
        $checkResult = pg_query_params($connection, $checkQuery, [$id]);
        $existing = pg_fetch_assoc($checkResult);
        pg_free_result($checkResult);
        
        if (!$existing) {
            // INSERT new municipality
            $insertQuery = "
                INSERT INTO municipalities (municipality_id, name, slug, lgu_type, district_no, preset_logo_image)
                VALUES ($1, $2, $3, $4, $5, $6)
            ";
            $result = pg_query_params($connection, $insertQuery, [$id, $name, $slug, $type, $district, $logo]);
            
            if ($result) {
                $results[] = ['name' => $name, 'status' => 'new', 'message' => 'Inserted successfully'];
                $inserted++;
            } else {
                $results[] = ['name' => $name, 'status' => 'error', 'message' => pg_last_error($connection)];
                $errors++;
            }
            
        } else {
            // UPDATE existing municipality
            if ($existing['name'] !== $name || $existing['preset_logo_image'] !== $logo) {
                $updateQuery = "
                    UPDATE municipalities 
                    SET name = $1, slug = $2, lgu_type = $3, district_no = $4, preset_logo_image = $5
                    WHERE municipality_id = $6
                ";
                $result = pg_query_params($connection, $updateQuery, [$name, $slug, $type, $district, $logo, $id]);
                
                if ($result) {
                    $results[] = ['name' => $name, 'status' => 'updated', 'message' => 'Updated successfully'];
                    $updated++;
                } else {
                    $results[] = ['name' => $name, 'status' => 'error', 'message' => pg_last_error($connection)];
                    $errors++;
                }
            } else {
                $results[] = ['name' => $name, 'status' => 'unchanged', 'message' => 'Already correct'];
                $unchanged++;
            }
        }
    }
    
    if ($errors > 0) {
        pg_query($connection, "ROLLBACK");
        echo "<div class='error'>";
        echo "<p>❌ <strong>Transaction rolled back due to errors.</strong></p>";
        echo "</div>";
    } else {
        pg_query($connection, "COMMIT");
        echo "<div class='success'>";
        echo "<p>✅ <strong>Transaction committed successfully!</strong></p>";
        echo "<p>📊 Summary: <strong>$inserted</strong> inserted, <strong>$updated</strong> updated, <strong>$unchanged</strong> unchanged</p>";
        echo "</div>";
    }
    
    // Display results table
    echo "<h3>📋 Migration Results</h3>";
    echo "<table>";
    echo "<thead><tr><th>Municipality</th><th>Status</th><th>Message</th></tr></thead>";
    echo "<tbody>";
    foreach ($results as $result) {
        $statusClass = 'status-' . $result['status'];
        $statusLabel = ucfirst($result['status']);
        echo "<tr>";
        echo "<td><strong>{$result['name']}</strong></td>";
        echo "<td><span class='status $statusClass'>$statusLabel</span></td>";
        echo "<td>{$result['message']}</td>";
        echo "</tr>";
    }
    echo "</tbody></table>";
    
    echo "<p><a href='debug_municipality_logos.php' class='btn'>🔍 View Logo Debug Page</a></p>";
    
} else {
    // Show preview
    echo "<h2>📋 Preview: 23 Municipalities to Populate</h2>";
    
    echo "<table>";
    echo "<thead><tr><th>ID</th><th>Name</th><th>Type</th><th>District</th><th>Logo Path</th></tr></thead>";
    echo "<tbody>";
    
    foreach ($municipalities as $muni) {
        list($id, $name, $slug, $type, $district, $logo) = $muni;
        echo "<tr>";
        echo "<td><code>$id</code></td>";
        echo "<td><strong>$name</strong></td>";
        echo "<td>" . ucfirst($type) . "</td>";
        echo "<td>District $district</td>";
        echo "<td><small><code>$logo</code></small></td>";
        echo "</tr>";
    }
    
    echo "</tbody></table>";
    
    echo "<div class='warning'>";
    echo "<p>⚠️ <strong>Note:</strong> This will use <code>ON CONFLICT</code> logic (upsert):</p>";
    echo "<ul>";
    echo "<li>If municipality doesn't exist → <strong>INSERT</strong></li>";
    echo "<li>If municipality exists → <strong>UPDATE</strong> name, slug, logo path</li>";
    echo "<li>Existing custom logos will be preserved</li>";
    echo "</ul>";
    echo "</div>";
    
    echo "<form method='post'>";
    echo "<button type='submit' name='populate' value='1' class='btn'>";
    echo "✅ Populate Database (23 municipalities)";
    echo "</button>";
    echo "</form>";
}

echo "</div></body></html>";
?>
