<?php
/**
 * Add deleted_at column to announcements table
 */
require_once __DIR__ . '/config/database.php';

echo "Adding deleted_at column to announcements table...\n";

$query = "ALTER TABLE announcements ADD COLUMN IF NOT EXISTS deleted_at TIMESTAMP DEFAULT NULL";

$result = pg_query($connection, $query);

if ($result) {
    echo "✅ Successfully added deleted_at column to announcements table\n";
    
    // Add index for better performance on deleted_at queries
    $indexQuery = "CREATE INDEX IF NOT EXISTS idx_announcements_deleted_at ON announcements(deleted_at)";
    $indexResult = pg_query($connection, $indexQuery);
    
    if ($indexResult) {
        echo "✅ Successfully created index on deleted_at column\n";
    } else {
        echo "⚠️ Warning: Could not create index - " . pg_last_error($connection) . "\n";
    }
} else {
    echo "❌ Error: " . pg_last_error($connection) . "\n";
}

pg_close($connection);
