<?php
// Quick test to check student_id values in database
require_once 'config/database.php';

$query = "SELECT student_id, first_name, last_name, municipality_id 
          FROM students 
          WHERE municipality_id = (SELECT municipality_id FROM municipalities WHERE name = 'General Trias')
          LIMIT 10";

$result = pg_query($connection, $query);

echo "<h2>Sample Student IDs from General Trias:</h2>";
echo "<table border='1' cellpadding='5'>";
echo "<tr><th>Student ID</th><th>Type</th><th>Length</th><th>First Name</th><th>Last Name</th></tr>";

while ($row = pg_fetch_assoc($result)) {
    $studentId = $row['student_id'];
    $type = gettype($studentId);
    $length = strlen($studentId);
    
    echo "<tr>";
    echo "<td>" . htmlspecialchars($studentId ?? 'NULL') . "</td>";
    echo "<td>$type</td>";
    echo "<td>$length</td>";
    echo "<td>" . htmlspecialchars($row['first_name']) . "</td>";
    echo "<td>" . htmlspecialchars($row['last_name']) . "</td>";
    echo "</tr>";
}

echo "</table>";

// Test if blank or empty
$query2 = "SELECT COUNT(*) as count, 
           SUM(CASE WHEN student_id IS NULL THEN 1 ELSE 0 END) as null_count,
           SUM(CASE WHEN student_id = '' THEN 1 ELSE 0 END) as empty_count,
           SUM(CASE WHEN TRIM(student_id) = '' THEN 1 ELSE 0 END) as whitespace_count
           FROM students 
           WHERE municipality_id = (SELECT municipality_id FROM municipalities WHERE name = 'General Trias')";

$result2 = pg_query($connection, $query2);
$stats = pg_fetch_assoc($result2);

echo "<h2>Student ID Statistics:</h2>";
echo "<ul>";
echo "<li>Total Students: " . $stats['count'] . "</li>";
echo "<li>NULL student_id: " . $stats['null_count'] . "</li>";
echo "<li>Empty string student_id: " . $stats['empty_count'] . "</li>";
echo "<li>Whitespace-only student_id: " . $stats['whitespace_count'] . "</li>";
echo "</ul>";
?>
