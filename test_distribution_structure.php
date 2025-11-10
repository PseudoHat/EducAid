<?php
// Check distribution_snapshots table structure
require_once 'config/database.php';

// Get table structure
$query = "
SELECT 
    column_name, 
    data_type, 
    character_maximum_length,
    is_nullable,
    column_default
FROM information_schema.columns 
WHERE table_name = 'distribution_snapshots' 
ORDER BY ordinal_position";

$result = pg_query($connection, $query);

echo "<h2>Distribution Snapshots Table Structure</h2>";
echo "<table border='1' cellpadding='5'>";
echo "<tr><th>Column Name</th><th>Data Type</th><th>Max Length</th><th>Nullable</th><th>Default</th></tr>";

while ($row = pg_fetch_assoc($result)) {
    echo "<tr>";
    echo "<td><strong>" . htmlspecialchars($row['column_name']) . "</strong></td>";
    echo "<td>" . htmlspecialchars($row['data_type']) . "</td>";
    echo "<td>" . htmlspecialchars($row['character_maximum_length'] ?: '-') . "</td>";
    echo "<td>" . htmlspecialchars($row['is_nullable']) . "</td>";
    echo "<td><code>" . htmlspecialchars($row['column_default'] ?: '-') . "</code></td>";
    echo "</tr>";
}

echo "</table>";

// Sample data from a distribution
echo "<h2>Sample Distribution Data</h2>";
$sampleQuery = "SELECT * FROM distribution_snapshots ORDER BY finalized_at DESC LIMIT 3";
$sampleResult = pg_query($connection, $sampleQuery);

if (pg_num_rows($sampleResult) > 0) {
    echo "<table border='1' cellpadding='5' style='font-size: 12px;'>";
    
    // Get column names
    $firstRow = pg_fetch_assoc($sampleResult);
    echo "<tr>";
    foreach (array_keys($firstRow) as $col) {
        echo "<th>" . htmlspecialchars($col) . "</th>";
    }
    echo "</tr>";
    
    // Reset and display all rows
    pg_result_seek($sampleResult, 0);
    while ($row = pg_fetch_assoc($sampleResult)) {
        echo "<tr>";
        foreach ($row as $value) {
            echo "<td>" . htmlspecialchars($value ?? 'NULL') . "</td>";
        }
        echo "</tr>";
    }
    
    echo "</table>";
} else {
    echo "<p>No distribution data found.</p>";
}
?>
