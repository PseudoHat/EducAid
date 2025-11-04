<?php
/**
 * DATABASE CONSTRAINT CLEANUP SCRIPT
 * 
 * This script fixes constraint violations that prevent student registration:
 * 1. Removes orphaned records (documents, distributions, qr_logs, grade_uploads without valid student_id)
 * 2. Removes duplicate email/mobile/school_student_id entries
 * 
 * SECURITY: Requires SECRET_TOKEN environment variable
 * USAGE: https://your-app.railway.app/tools/cleanup_constraints.php?token=YOUR_SECRET_TOKEN&action=detect
 *        https://your-app.railway.app/tools/cleanup_constraints.php?token=YOUR_SECRET_TOKEN&action=cleanup
 */

// Security: Check token
$required_token = getenv('CLEANUP_SECRET_TOKEN') ?: 'CHANGE_ME_IN_RAILWAY_ENV';
$provided_token = $_GET['token'] ?? '';

if ($provided_token !== $required_token || $required_token === 'CHANGE_ME_IN_RAILWAY_ENV') {
    http_response_code(403);
    die('Forbidden: Invalid or missing token');
}

// Get action (detect or cleanup)
$action = $_GET['action'] ?? 'detect';

// Database connection using same logic as database.php
$databaseUrl = getenv('DATABASE_PUBLIC_URL');

if ($databaseUrl) {
    // Parse DATABASE_PUBLIC_URL from Railway
    $parts = parse_url($databaseUrl);
    $db_host = $parts['host'] ?? 'localhost';
    $db_port = $parts['port'] ?? 5432;
    $db_name = ltrim($parts['path'] ?? '/railway', '/');
    $db_user = $parts['user'] ?? 'postgres';
    $db_pass = $parts['pass'] ?? '';
} else {
    // Fallback to individual env vars
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

echo "<html><head><title>Database Constraint Cleanup</title></head><body>";
echo "<h1>Database Constraint Cleanup Script</h1>";
echo "<p>Action: <strong>" . htmlspecialchars($action) . "</strong></p>";
echo "<p>Timestamp: " . date('Y-m-d H:i:s') . "</p>";
echo "<hr>";

if ($action === 'detect') {
    echo "<h2>DETECTION MODE - No changes will be made</h2>";
    
    // 1. Find orphaned documents
    $query = "SELECT COUNT(*) as cnt FROM documents d 
              LEFT JOIN students s ON d.student_id = s.student_id 
              WHERE s.student_id IS NULL";
    $result = pg_query($conn, $query);
    $row = pg_fetch_assoc($result);
    echo "<p><strong>Orphaned documents:</strong> " . $row['cnt'] . "</p>";
    
    // 2. Find orphaned distributions
    $query = "SELECT COUNT(*) as cnt FROM distributions d 
              LEFT JOIN students s ON d.student_id = s.student_id 
              WHERE s.student_id IS NULL";
    $result = pg_query($conn, $query);
    $row = pg_fetch_assoc($result);
    echo "<p><strong>Orphaned distributions:</strong> " . $row['cnt'] . "</p>";
    
    // 3. Find orphaned qr_logs
    $query = "SELECT COUNT(*) as cnt FROM qr_logs q 
              LEFT JOIN students s ON q.student_id = s.student_id 
              WHERE s.student_id IS NULL";
    $result = pg_query($conn, $query);
    $row = pg_fetch_assoc($result);
    echo "<p><strong>Orphaned qr_logs:</strong> " . $row['cnt'] . "</p>";
    
    // 4. Find orphaned grade_uploads
    $query = "SELECT COUNT(*) as cnt FROM grade_uploads g 
              LEFT JOIN students s ON g.student_id = s.student_id 
              WHERE s.student_id IS NULL";
    $result = pg_query($conn, $query);
    $row = pg_fetch_assoc($result);
    echo "<p><strong>Orphaned grade_uploads:</strong> " . $row['cnt'] . "</p>";
    
    echo "<hr>";
    
    // 5. Find duplicate emails
    $query = "SELECT email, COUNT(*) as cnt FROM students 
              WHERE email IS NOT NULL 
              GROUP BY email 
              HAVING COUNT(*) > 1";
    $result = pg_query($conn, $query);
    $dup_emails = pg_fetch_all($result);
    echo "<p><strong>Duplicate emails:</strong> " . (is_array($dup_emails) ? count($dup_emails) : 0) . "</p>";
    if (is_array($dup_emails) && count($dup_emails) > 0) {
        echo "<ul>";
        foreach ($dup_emails as $dup) {
            echo "<li>" . htmlspecialchars($dup['email']) . " (" . $dup['cnt'] . " times)</li>";
        }
        echo "</ul>";
    }
    
    // 6. Find duplicate mobile numbers
    $query = "SELECT mobile, COUNT(*) as cnt FROM students 
              WHERE mobile IS NOT NULL 
              GROUP BY mobile 
              HAVING COUNT(*) > 1";
    $result = pg_query($conn, $query);
    $dup_mobiles = pg_fetch_all($result);
    echo "<p><strong>Duplicate mobiles:</strong> " . (is_array($dup_mobiles) ? count($dup_mobiles) : 0) . "</p>";
    if (is_array($dup_mobiles) && count($dup_mobiles) > 0) {
        echo "<ul>";
        foreach ($dup_mobiles as $dup) {
            echo "<li>" . htmlspecialchars($dup['mobile']) . " (" . $dup['cnt'] . " times)</li>";
        }
        echo "</ul>";
    }
    
    // 7. Find duplicate school_student_ids (within same university)
    $query = "SELECT university_id, school_student_id, COUNT(*) as cnt FROM students 
              WHERE school_student_id IS NOT NULL 
              GROUP BY university_id, school_student_id 
              HAVING COUNT(*) > 1";
    $result = pg_query($conn, $query);
    $dup_school_ids = pg_fetch_all($result);
    echo "<p><strong>Duplicate school_student_ids:</strong> " . (is_array($dup_school_ids) ? count($dup_school_ids) : 0) . "</p>";
    if (is_array($dup_school_ids) && count($dup_school_ids) > 0) {
        echo "<ul>";
        foreach ($dup_school_ids as $dup) {
            echo "<li>University " . htmlspecialchars($dup['university_id']) . ", ID " . htmlspecialchars($dup['school_student_id']) . " (" . $dup['cnt'] . " times)</li>";
        }
        echo "</ul>";
    }
    
    echo "<hr>";
    echo "<h3>Next Step:</h3>";
    echo "<p>If you want to clean up these issues, run:</p>";
    echo "<p><code>?token=" . htmlspecialchars($provided_token) . "&action=cleanup</code></p>";
    
} elseif ($action === 'cleanup') {
    echo "<h2>CLEANUP MODE - Making database changes</h2>";
    
    // Start transaction
    pg_query($conn, "BEGIN");
    echo "<p><strong>Transaction started</strong></p>";
    
    try {
        $total_deleted = 0;
        
        // Helper function to safely delete orphaned records
        $safe_delete = function($table_name) use ($conn, &$total_deleted) {
            // Check if table exists first
            $check = pg_query($conn, "SELECT EXISTS (SELECT FROM information_schema.tables WHERE table_name = '$table_name')");
            $exists = pg_fetch_result($check, 0, 0);
            
            if ($exists === 't' || $exists === true) {
                $result = @pg_query($conn, "DELETE FROM $table_name WHERE student_id NOT IN (SELECT student_id FROM students)");
                if ($result) {
                    $deleted = pg_affected_rows($result);
                    echo "<p>✓ Deleted $deleted orphaned records from <strong>$table_name</strong></p>";
                    $total_deleted += $deleted;
                    return $deleted;
                } else {
                    echo "<p>⚠ Skipped $table_name (error: " . pg_last_error($conn) . ")</p>";
                    return 0;
                }
            } else {
                echo "<p>ℹ Skipped $table_name (table does not exist)</p>";
                return 0;
            }
        };
        
        // 1. Delete orphaned documents
        $safe_delete('documents');
        
        // 2. Delete orphaned distributions
        $safe_delete('distributions');
        
        // 3. Delete orphaned qr_logs
        $safe_delete('qr_logs');
        
        // 4. Delete orphaned grade_uploads
        $safe_delete('grade_uploads');
        
        echo "<hr>";
        
        // 5. Handle duplicate emails - keep oldest, delete rest
        $query = "SELECT email FROM students 
                  WHERE email IS NOT NULL 
                  GROUP BY email 
                  HAVING COUNT(*) > 1";
        $result = pg_query($conn, $query);
        $dup_emails = pg_fetch_all($result);
        
        $email_deleted = 0;
        if (is_array($dup_emails)) {
            foreach ($dup_emails as $dup) {
                $email = $dup['email'];
                // Delete all except the one with the smallest student_id (oldest)
                $del_query = "DELETE FROM students 
                              WHERE email = $1 
                              AND student_id NOT IN (
                                  SELECT MIN(student_id) 
                                  FROM students 
                                  WHERE email = $1
                              )";
                $del_result = pg_query_params($conn, $del_query, [$email]);
                $deleted = pg_affected_rows($del_result);
                $email_deleted += $deleted;
                echo "<p>✓ Removed $deleted duplicate(s) for email: " . htmlspecialchars($email) . "</p>";
            }
        }
        echo "<p><strong>Total duplicate emails cleaned: $email_deleted</strong></p>";
        $total_deleted += $email_deleted;
        
        // 6. Handle duplicate mobile numbers - keep oldest, delete rest
        $query = "SELECT mobile FROM students 
                  WHERE mobile IS NOT NULL 
                  GROUP BY mobile 
                  HAVING COUNT(*) > 1";
        $result = pg_query($conn, $query);
        $dup_mobiles = pg_fetch_all($result);
        
        $mobile_deleted = 0;
        if (is_array($dup_mobiles)) {
            foreach ($dup_mobiles as $dup) {
                $mobile = $dup['mobile'];
                // Delete all except the one with the smallest student_id (oldest)
                $del_query = "DELETE FROM students 
                              WHERE mobile = $1 
                              AND student_id NOT IN (
                                  SELECT MIN(student_id) 
                                  FROM students 
                                  WHERE mobile = $1
                              )";
                $del_result = pg_query_params($conn, $del_query, [$mobile]);
                $deleted = pg_affected_rows($del_result);
                $mobile_deleted += $deleted;
                echo "<p>✓ Removed $deleted duplicate(s) for mobile: " . htmlspecialchars($mobile) . "</p>";
            }
        }
        echo "<p><strong>Total duplicate mobiles cleaned: $mobile_deleted</strong></p>";
        $total_deleted += $mobile_deleted;
        
        // 7. Handle duplicate school_student_ids - keep oldest, delete rest
        $query = "SELECT university_id, school_student_id FROM students 
                  WHERE school_student_id IS NOT NULL 
                  GROUP BY university_id, school_student_id 
                  HAVING COUNT(*) > 1";
        $result = pg_query($conn, $query);
        $dup_school_ids = pg_fetch_all($result);
        
        $school_id_deleted = 0;
        if (is_array($dup_school_ids)) {
            foreach ($dup_school_ids as $dup) {
                $university_id = $dup['university_id'];
                $school_student_id = $dup['school_student_id'];
                // Delete all except the one with the smallest student_id (oldest)
                $del_query = "DELETE FROM students 
                              WHERE university_id = $1 
                              AND school_student_id = $2 
                              AND student_id NOT IN (
                                  SELECT MIN(student_id) 
                                  FROM students 
                                  WHERE university_id = $1 
                                  AND school_student_id = $2
                              )";
                $del_result = pg_query_params($conn, $del_query, [$university_id, $school_student_id]);
                $deleted = pg_affected_rows($del_result);
                $school_id_deleted += $deleted;
                echo "<p>✓ Removed $deleted duplicate(s) for university $university_id, school ID: " . htmlspecialchars($school_student_id) . "</p>";
            }
        }
        echo "<p><strong>Total duplicate school_student_ids cleaned: $school_id_deleted</strong></p>";
        $total_deleted += $school_id_deleted;
        
        // Commit transaction
        pg_query($conn, "COMMIT");
        echo "<hr>";
        echo "<h3 style='color: green;'>✓ SUCCESS: Transaction committed</h3>";
        echo "<p><strong>Total records cleaned: $total_deleted</strong></p>";
        echo "<p>The student_register.php form should now work correctly.</p>";
        
    } catch (Exception $e) {
        pg_query($conn, "ROLLBACK");
        echo "<hr>";
        echo "<h3 style='color: red;'>✗ ERROR: Transaction rolled back</h3>";
        echo "<p>Error: " . htmlspecialchars($e->getMessage()) . "</p>";
        echo "<p>No changes were made to the database.</p>";
    }
    
} else {
    echo "<p>Invalid action. Use action=detect or action=cleanup</p>";
}

pg_close($conn);

echo "</body></html>";
?>
