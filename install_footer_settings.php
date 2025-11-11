<?php
/**
 * Install Footer Settings Table
 * Run this script once to create the footer_settings table
 */

require_once __DIR__ . '/config/database.php';

echo "Installing Footer Settings Table...\n";
echo "=====================================\n\n";

// Read the SQL file
$sqlFile = __DIR__ . '/sql/create_footer_settings.sql';

if (!file_exists($sqlFile)) {
    die("ERROR: SQL file not found at: $sqlFile\n");
}

$sql = file_get_contents($sqlFile);

if ($sql === false) {
    die("ERROR: Could not read SQL file\n");
}

// Execute the SQL
try {
    $result = pg_query($connection, $sql);
    
    if ($result === false) {
        $error = pg_last_error($connection);
        die("ERROR: Failed to create table\n$error\n");
    }
    
    echo "✓ Footer settings table created successfully!\n";
    echo "✓ Default values inserted\n\n";
    
    // Verify the table exists
    $checkQuery = "SELECT COUNT(*) as count FROM footer_settings";
    $checkResult = pg_query($connection, $checkQuery);
    
    if ($checkResult) {
        $row = pg_fetch_assoc($checkResult);
        echo "✓ Verification: Found {$row['count']} row(s) in footer_settings table\n\n";
    }
    
    echo "Installation completed successfully!\n";
    echo "You can now use the Footer Settings page.\n";
    
} catch (Exception $e) {
    die("ERROR: " . $e->getMessage() . "\n");
}

pg_close($connection);
