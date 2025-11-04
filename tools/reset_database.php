<?php
/**
 * DATABASE RESET SCRIPT
 * 
 * WARNING: This script will DROP ALL TABLES and recreate them from the schema dump.
 * ALL DATA WILL BE PERMANENTLY DELETED!
 * 
 * SECURITY: Requires SECRET_TOKEN environment variable
 * USAGE: https://your-app.railway.app/tools/reset_database.php?token=YOUR_SECRET_TOKEN&confirm=YES_DELETE_EVERYTHING
 */

// Security: Check token
$required_token = getenv('CLEANUP_SECRET_TOKEN') ?: 'CHANGE_ME_IN_RAILWAY_ENV';
$provided_token = $_GET['token'] ?? '';
$confirm = $_GET['confirm'] ?? '';

if ($provided_token !== $required_token || $required_token === 'CHANGE_ME_IN_RAILWAY_ENV') {
    http_response_code(403);
    die('Forbidden: Invalid or missing token');
}

if ($confirm !== 'YES_DELETE_EVERYTHING') {
    http_response_code(400);
    die('ERROR: You must add &confirm=YES_DELETE_EVERYTHING to proceed. This will delete ALL data!');
}

// Database connection using same logic as database.php
$databaseUrl = getenv('DATABASE_PUBLIC_URL');

if ($databaseUrl) {
    $parts = parse_url($databaseUrl);
    $db_host = $parts['host'] ?? 'localhost';
    $db_port = $parts['port'] ?? 5432;
    $db_name = ltrim($parts['path'] ?? '/railway', '/');
    $db_user = $parts['user'] ?? 'postgres';
    $db_pass = $parts['pass'] ?? '';
} else {
    $db_host = getenv('DB_HOST') ?: 'localhost';
    $db_port = getenv('DB_PORT') ?: '5432';
    $db_name = getenv('DB_NAME') ?: 'educaid';
    $db_user = getenv('DB_USER') ?: 'postgres';
    $db_pass = getenv('DB_PASSWORD') ?: '';
}

$conn_string = sprintf(
    'host=%s port=%s dbname=%s user=%s password=%s connect_timeout=10',
    $db_host,
    $db_port,
    $db_name,
    $db_user,
    $db_pass
);

$conn = @pg_connect($conn_string);

if (!$conn) {
    die('Database connection failed. Check Railway DATABASE_PUBLIC_URL or DB_* environment variables.');
}

echo "<html><head><title>Database Reset</title></head><body>";
echo "<h1>Database Reset Script</h1>";
echo "<p><strong style='color: red;'>WARNING: This will delete ALL data!</strong></p>";
echo "<p>Timestamp: " . date('Y-m-d H:i:s') . "</p>";
echo "<hr>";

// Start transaction
pg_query($conn, "BEGIN");
echo "<p><strong>Transaction started</strong></p>";

try {
    // Drop all tables in reverse dependency order
    $tables = [
        'student_notification_preferences',
        'admin_notifications',
        'announcements',
        'documents',
        'qr_logs',
        'signup_slots',
        'students',
        'admins',
        'barangays',
        'municipalities',
        'year_levels',
        'universities',
        'active_sessions',
        'password_reset_tokens',
        'school_student_ids'
    ];
    
    echo "<h2>Dropping Tables</h2>";
    foreach ($tables as $table) {
        $result = @pg_query($conn, "DROP TABLE IF EXISTS $table CASCADE");
        if ($result) {
            echo "<p>✓ Dropped table: <strong>$table</strong></p>";
        } else {
            echo "<p>⚠ Table <strong>$table</strong> does not exist or already dropped</p>";
        }
    }
    
    // Also drop any remaining tables not in the list
    $remaining_query = "SELECT tablename FROM pg_tables WHERE schemaname = 'public'";
    $remaining_result = pg_query($conn, $remaining_query);
    if ($remaining_result && pg_num_rows($remaining_result) > 0) {
        echo "<h3>Dropping remaining tables</h3>";
        while ($row = pg_fetch_assoc($remaining_result)) {
            $table = $row['tablename'];
            @pg_query($conn, "DROP TABLE IF EXISTS $table CASCADE");
            echo "<p>✓ Dropped: <strong>$table</strong></p>";
        }
    }
    
    // Commit transaction
    pg_query($conn, "COMMIT");
    echo "<hr>";
    echo "<h3 style='color: green;'>✓ SUCCESS: All tables dropped</h3>";
    echo "<p><strong>Database has been reset!</strong></p>";
    echo "<p>Next step: Run your schema dump SQL file to recreate the tables:</p>";
    echo "<ol>";
    echo "<li>Download: <code>CURRENT database_schema_dump_2025-11-02_latest.sql</code></li>";
    echo "<li>Go to Railway Dashboard → PostgreSQL → Query tab</li>";
    echo "<li>Paste and execute the SQL file</li>";
    echo "<li>Or use: <code>psql -h [host] -U [user] -d [dbname] -f schema.sql</code></li>";
    echo "</ol>";
    
} catch (Exception $e) {
    pg_query($conn, "ROLLBACK");
    echo "<hr>";
    echo "<h3 style='color: red;'>✗ ERROR: Transaction rolled back</h3>";
    echo "<p>Error: " . htmlspecialchars($e->getMessage()) . "</p>";
    echo "<p>No changes were made to the database.</p>";
}

pg_close($conn);

echo "</body></html>";
?>
