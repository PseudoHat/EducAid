<?php
// Helper script to run SQL files
if ($argc < 2) {
    die("Usage: php run_sql_file.php <sql_file_path>\n");
}

$sqlFile = $argv[1];
if (!file_exists($sqlFile)) {
    die("Error: File not found: $sqlFile\n");
}

require_once __DIR__ . '/config/database.php';

$sql = file_get_contents($sqlFile);
echo "Running: $sqlFile\n";
echo str_repeat("=", 70) . "\n";

$result = pg_query($connection, $sql);
if ($result) {
    echo "✓ Successfully executed: $sqlFile\n";
} else {
    echo "✗ Error executing: $sqlFile\n";
    echo "Error: " . pg_last_error($connection) . "\n";
}

echo str_repeat("=", 70) . "\n";
