<?php
// Test to verify Excel column mapping
echo "<h2>Excel Column Mapping Test</h2>";
echo "<table border='1' cellpadding='5'>";
echo "<tr><th>Column Letter</th><th>Index</th><th>Field Name</th></tr>";

$headers = [
    'No.', 'Student ID', 'Last Name', 'First Name', 'Middle Name', 'Extension', 
    'Gender', 'Birth Date', 'Email', 'Mobile', 'Barangay', 'Municipality', 
    'University', 'Course', 'Year Level', 'Status'
];

for ($i = 0; $i < count($headers); $i++) {
    $colLetter = chr(65 + $i); // A=65, B=66, etc.
    echo "<tr>";
    echo "<td><strong>$colLetter</strong></td>";
    echo "<td>$i</td>";
    echo "<td>{$headers[$i]}</td>";
    echo "</tr>";
}

echo "</table>";
echo "<p><strong>Student ID should be in Column B (index 1)</strong></p>";
?>
