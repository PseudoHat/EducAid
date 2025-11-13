<?php
/**
 * Fix University ID Sequence
 * Run this once to reset the sequence to the correct value
 * Works with both localhost and Railway database
 */

include __DIR__ . '/config/database.php';

// Check if we're connected to Railway or localhost
$dbInfo = pg_version($connection);
$host = pg_host($connection);
$dbname = pg_dbname($connection);

echo "<!DOCTYPE html>";
echo "<html><head><title>Fix Database Sequences</title>";
echo "<style>body{font-family:Arial,sans-serif;margin:40px;} .success{color:green;} .error{color:red;} .info{background:#e3f2fd;padding:15px;border-radius:5px;margin:10px 0;}</style>";
echo "</head><body>";

echo "<h2>🔧 Fixing Database Sequences</h2>";
echo "<div class='info'>";
echo "<strong>Connected to:</strong><br>";
echo "Host: <code>$host</code><br>";
echo "Database: <code>$dbname</code><br>";
echo "</div>";

// Fix Universities Sequence
echo "<h3>1. Universities Sequence</h3>";

// Get the maximum university_id
$maxQuery = "SELECT MAX(university_id) as max_id FROM universities";
$maxResult = pg_query($connection, $maxQuery);

if ($maxResult) {
    $maxRow = pg_fetch_assoc($maxResult);
    $maxId = $maxRow['max_id'] ?? 0;
    
    echo "<p>Current max university_id: <strong>$maxId</strong></p>";
    
    // Get current sequence value
    $currentSeqQuery = "SELECT last_value FROM universities_university_id_seq";
    $currentSeqResult = pg_query($connection, $currentSeqQuery);
    $currentSeqValue = pg_fetch_result($currentSeqResult, 0, 0);
    
    echo "<p>Current sequence value: <strong>$currentSeqValue</strong></p>";
    
    if ($currentSeqValue <= $maxId) {
        // Reset the sequence
        $resetQuery = "SELECT setval('universities_university_id_seq', $maxId, true)";
        $resetResult = pg_query($connection, $resetQuery);
        
        if ($resetResult) {
            $newSeq = pg_fetch_result($resetResult, 0, 0);
            echo "<p class='success'>✓ Sequence reset successfully! Next ID will be: <strong>" . ($newSeq + 1) . "</strong></p>";
        } else {
            echo "<p class='error'>✗ Failed to reset sequence: " . pg_last_error($connection) . "</p>";
        }
    } else {
        echo "<p class='info'>✓ Sequence is already correct. No changes needed.</p>";
    }
} else {
    echo "<p class='error'>✗ Failed to query universities: " . pg_last_error($connection) . "</p>";
}

// Fix Barangays Sequence
echo "<h3>2. Barangays Sequence</h3>";

$maxBarangayQuery = "SELECT MAX(barangay_id) as max_id FROM barangays";
$maxBarangayResult = pg_query($connection, $maxBarangayQuery);

if ($maxBarangayResult) {
    $maxBarangayRow = pg_fetch_assoc($maxBarangayResult);
    $maxBarangayId = $maxBarangayRow['max_id'] ?? 0;
    
    echo "<p>Current max barangay_id: <strong>$maxBarangayId</strong></p>";
    
    // Get current sequence value
    $currentBarangaySeqQuery = "SELECT last_value FROM barangays_barangay_id_seq";
    $currentBarangaySeqResult = pg_query($connection, $currentBarangaySeqQuery);
    $currentBarangaySeqValue = pg_fetch_result($currentBarangaySeqResult, 0, 0);
    
    echo "<p>Current sequence value: <strong>$currentBarangaySeqValue</strong></p>";
    
    if ($currentBarangaySeqValue <= $maxBarangayId) {
        $resetBarangayQuery = "SELECT setval('barangays_barangay_id_seq', $maxBarangayId, true)";
        $resetBarangayResult = pg_query($connection, $resetBarangayQuery);
        
        if ($resetBarangayResult) {
            $newBarangaySeq = pg_fetch_result($resetBarangayResult, 0, 0);
            echo "<p class='success'>✓ Barangay sequence reset successfully! Next ID will be: <strong>" . ($newBarangaySeq + 1) . "</strong></p>";
        } else {
            echo "<p class='error'>✗ Failed to reset barangay sequence: " . pg_last_error($connection) . "</p>";
        }
    } else {
        echo "<p class='info'>✓ Sequence is already correct. No changes needed.</p>";
    }
} else {
    echo "<p class='error'>✗ Failed to query barangays: " . pg_last_error($connection) . "</p>";
}

echo "<hr>";
echo "<h3>✅ Complete!</h3>";
echo "<p><strong>You can now add universities and barangays normally.</strong></p>";
echo "<p><a href='modules/admin/system_data.php' style='background:#1976d2;color:white;padding:10px 20px;text-decoration:none;border-radius:5px;display:inline-block;'>Go to System Data Management</a></p>";

pg_close($connection);

echo "</body></html>";
?>
