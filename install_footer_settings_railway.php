<?php
/**
 * Install Footer Settings Table on Railway
 * This script creates the footer_settings table in your Railway database
 */

// Railway database configuration
// Update these with your Railway PostgreSQL credentials
$railway_config = [
    'host' => 'your-railway-host.railway.app',
    'port' => '5432',
    'dbname' => 'railway',
    'user' => 'postgres',
    'password' => 'your-railway-password'
];

// Uncomment and fill in your Railway credentials above, or use environment variables
// If you have environment variables set:
if (getenv('PGHOST')) {
    $railway_config = [
        'host' => getenv('PGHOST'),
        'port' => getenv('PGPORT') ?: '5432',
        'dbname' => getenv('PGDATABASE') ?: 'railway',
        'user' => getenv('PGUSER') ?: 'postgres',
        'password' => getenv('PGPASSWORD')
    ];
}

echo "Installing Footer Settings Table on Railway...\n";
echo "==============================================\n\n";

// Connect to Railway database
$conn_string = sprintf(
    "host=%s port=%s dbname=%s user=%s password=%s",
    $railway_config['host'],
    $railway_config['port'],
    $railway_config['dbname'],
    $railway_config['user'],
    $railway_config['password']
);

$connection = pg_connect($conn_string);

if (!$connection) {
    die("ERROR: Could not connect to Railway database\n" . pg_last_error() . "\n");
}

echo "✓ Connected to Railway database\n\n";

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
    
    // Show the created record
    $showQuery = "SELECT footer_id, municipality_id, footer_title, footer_bg_color, is_active FROM footer_settings";
    $showResult = pg_query($connection, $showQuery);
    
    if ($showResult) {
        echo "Created records:\n";
        echo "----------------\n";
        while ($row = pg_fetch_assoc($showResult)) {
            echo "ID: {$row['footer_id']}, Municipality: {$row['municipality_id']}, ";
            echo "Title: {$row['footer_title']}, BG Color: {$row['footer_bg_color']}, ";
            echo "Active: " . ($row['is_active'] ? 'Yes' : 'No') . "\n";
        }
    }
    
    echo "\n✓ Installation completed successfully on Railway!\n";
    echo "You can now use the Footer Settings page in production.\n";
    
} catch (Exception $e) {
    die("ERROR: " . $e->getMessage() . "\n");
}

pg_close($connection);
