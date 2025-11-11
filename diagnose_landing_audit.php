<?php
// Diagnostic script to check landing_content_audit table
require_once __DIR__ . '/config/database.php';

echo "<h2>Landing Content Audit Diagnostics</h2>";

// Check if table exists
$check_table = @pg_query($connection, "
    SELECT EXISTS (
        SELECT FROM information_schema.tables 
        WHERE table_schema = 'public' 
        AND table_name = 'landing_content_audit'
    )
");

if ($check_table) {
    $row = pg_fetch_assoc($check_table);
    if ($row['exists'] === 't') {
        echo "<p>✅ Table 'landing_content_audit' EXISTS</p>";
        
        // Check table structure
        echo "<h3>Table Structure:</h3>";
        $columns = @pg_query($connection, "
            SELECT column_name, data_type 
            FROM information_schema.columns 
            WHERE table_name = 'landing_content_audit'
            ORDER BY ordinal_position
        ");
        
        if ($columns) {
            echo "<ul>";
            while ($col = pg_fetch_assoc($columns)) {
                echo "<li>{$col['column_name']} ({$col['data_type']})</li>";
            }
            echo "</ul>";
        }
        
        // Count total records
        $count = @pg_query($connection, "SELECT COUNT(*) as total FROM landing_content_audit");
        if ($count) {
            $total = pg_fetch_assoc($count)['total'];
            echo "<p>📊 Total records: <strong>{$total}</strong></p>";
        }
        
        // Count by municipality_id
        $by_muni = @pg_query($connection, "
            SELECT municipality_id, COUNT(*) as count 
            FROM landing_content_audit 
            GROUP BY municipality_id 
            ORDER BY municipality_id
        ");
        
        if ($by_muni) {
            echo "<h3>Records by Municipality ID:</h3>";
            echo "<ul>";
            while ($row = pg_fetch_assoc($by_muni)) {
                echo "<li>Municipality ID {$row['municipality_id']}: {$row['count']} records</li>";
            }
            echo "</ul>";
        }
        
        // Show recent records
        $recent = @pg_query($connection, "
            SELECT audit_id, municipality_id, block_key, action_type, created_at
            FROM landing_content_audit 
            ORDER BY audit_id DESC 
            LIMIT 10
        ");
        
        if ($recent) {
            echo "<h3>10 Most Recent Records:</h3>";
            echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
            echo "<tr><th>Audit ID</th><th>Muni ID</th><th>Block Key</th><th>Action</th><th>Created At</th></tr>";
            while ($row = pg_fetch_assoc($recent)) {
                echo "<tr>";
                echo "<td>{$row['audit_id']}</td>";
                echo "<td>{$row['municipality_id']}</td>";
                echo "<td>{$row['block_key']}</td>";
                echo "<td>{$row['action_type']}</td>";
                echo "<td>{$row['created_at']}</td>";
                echo "</tr>";
            }
            echo "</table>";
        }
        
        // Check if any records for municipality_id=1
        $muni_1 = @pg_query($connection, "
            SELECT COUNT(*) as count 
            FROM landing_content_audit 
            WHERE municipality_id = 1
        ");
        
        if ($muni_1) {
            $count_1 = pg_fetch_assoc($muni_1)['count'];
            echo "<p>📋 Records for municipality_id=1: <strong>{$count_1}</strong></p>";
            
            if ($count_1 == 0) {
                echo "<p style='color: red;'>⚠️ <strong>WARNING:</strong> No records found for municipality_id=1!</p>";
                echo "<p>This is why the History button shows no data.</p>";
            }
        }
        
    } else {
        echo "<p>❌ Table 'landing_content_audit' DOES NOT EXIST</p>";
        echo "<p>Run the table creation script to fix this.</p>";
    }
} else {
    echo "<p>❌ Error checking table existence: " . pg_last_error($connection) . "</p>";
}
?>
