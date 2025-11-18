<?php
/**
 * Update student email from old to new email address
 * Usage: Run this file once from browser or CLI to update Railway database
 */

// For Railway PostgreSQL connection
// Try multiple methods to get DATABASE_URL
$railway_db_url = getenv('DATABASE_URL') ?: ($_ENV['DATABASE_URL'] ?? ($_SERVER['DATABASE_URL'] ?? null));

echo "Checking for Railway DATABASE_URL...<br>";
echo "getenv: " . (getenv('DATABASE_URL') ? 'Found' : 'Not found') . "<br>";
echo "ENV: " . (isset($_ENV['DATABASE_URL']) ? 'Found' : 'Not found') . "<br>";
echo "SERVER: " . (isset($_SERVER['DATABASE_URL']) ? 'Found' : 'Not found') . "<br><br>";

if ($railway_db_url) {
    echo "Using Railway DATABASE_URL<br>";
    // Parse Railway DATABASE_URL
    $db = parse_url($railway_db_url);
    $connection = pg_connect(
        "host={$db['host']} " .
        "port={$db['port']} " .
        "dbname=" . ltrim($db['path'], '/') . " " .
        "user={$db['user']} " .
        "password={$db['pass']} " .
        "sslmode=require"
    );
    
    if (!$connection) {
        die("Failed to connect to Railway database: " . pg_last_error() . "<br>");
    }
    echo "<strong style='color: green;'>✅ Connected to Railway database</strong><br>";
    echo "Host: {$db['host']}<br>";
    echo "Database: " . ltrim($db['path'], '/') . "<br><br>";
} else {
    // Fallback to local database
    echo "DATABASE_URL not found, using local database<br>";
    require_once __DIR__ . '/config/database.php';
    echo "<strong style='color: blue;'>Connected to local database</strong><br><br>";
}

// Configuration
$old_email = ''; // Leave empty to find the first student
$new_email = 'migueldy420@gmail.com';

try {
    // If no old email specified, find first student
    if (empty($old_email)) {
        $query = "SELECT student_id, first_name, last_name, email FROM students ORDER BY created_at ASC LIMIT 1";
        $result = pg_query($connection, $query);
        
        if ($result && pg_num_rows($result) > 0) {
            $student = pg_fetch_assoc($result);
            $old_email = $student['email'];
            
            echo "Found student: {$student['first_name']} {$student['last_name']}<br>";
            echo "Student ID: {$student['student_id']}<br>";
            echo "Current Email: {$student['email']}<br><br>";
        } else {
            die("No students found in database.<br>");
        }
    }
    
    // Check if new email already exists
    $check_query = pg_query_params($connection, 
        "SELECT student_id, first_name, last_name FROM students WHERE email = $1",
        [$new_email]
    );
    
    if ($check_query && pg_num_rows($check_query) > 0) {
        $existing = pg_fetch_assoc($check_query);
        die("Error: Email '{$new_email}' is already in use by {$existing['first_name']} {$existing['last_name']} (ID: {$existing['student_id']})<br>");
    }
    
    // Update the email
    $update_query = pg_query_params($connection,
        "UPDATE students SET email = $1 WHERE email = $2 RETURNING student_id, first_name, last_name",
        [$new_email, $old_email]
    );
    
    if ($update_query && pg_num_rows($update_query) > 0) {
        $updated = pg_fetch_assoc($update_query);
        echo "<div style='color: green; font-weight: bold;'>✅ SUCCESS!</div>";
        echo "Updated student: {$updated['first_name']} {$updated['last_name']}<br>";
        echo "Student ID: {$updated['student_id']}<br>";
        echo "Old Email: {$old_email}<br>";
        echo "New Email: {$new_email}<br>";
    } else {
        echo "<div style='color: red;'>❌ No students found with email: {$old_email}</div>";
    }
    
} catch (Exception $e) {
    echo "<div style='color: red;'>Error: " . htmlspecialchars($e->getMessage()) . "</div>";
}

pg_close($connection);
?>
