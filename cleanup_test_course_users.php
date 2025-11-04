<?php
/**
 * Cleanup Test Students for Course Mapping Demo
 * Run this file via browser: http://localhost/EducAid/cleanup_test_course_users.php
 */

include __DIR__ . '/config/database.php';

echo "<html><head><title>Cleanup Test Course Mapping Users</title>";
echo "<style>body{font-family:Arial;padding:20px;background:#f5f5f5;} .success{color:green;} .error{color:red;} .warning{color:orange;} pre{background:#fff;padding:15px;border-radius:5px;border:1px solid #ddd;}</style>";
echo "</head><body>";
echo "<h1>🗑️ Cleanup Test Course Mapping Users</h1>";

try {
    pg_query($connection, "BEGIN");
    
    echo "<h2>Removing Test Data...</h2>";
    
    // Delete documents first (foreign key constraint)
    $deleteDocsQuery = "DELETE FROM documents WHERE student_id IN ('TEST-DS-2024-001', 'TEST-CS-2024-002')";
    $docsResult = pg_query($connection, $deleteDocsQuery);
    $docsDeleted = pg_affected_rows($docsResult);
    echo "<p class='success'>✅ Deleted $docsDeleted document records</p>";
    
    // Delete students
    $deleteStudentsQuery = "DELETE FROM students WHERE student_id IN ('TEST-DS-2024-001', 'TEST-CS-2024-002')";
    $studentsResult = pg_query($connection, $deleteStudentsQuery);
    $studentsDeleted = pg_affected_rows($studentsResult);
    echo "<p class='success'>✅ Deleted $studentsDeleted student records</p>";
    
    // Delete unverified course mappings (only if no other students use them)
    $deleteCoursesQuery = "DELETE FROM courses_mapping 
                          WHERE raw_course_name IN ('BS Data Science', 'BS Cyber Security') 
                          AND is_verified = FALSE
                          AND NOT EXISTS (
                              SELECT 1 FROM students WHERE course = courses_mapping.raw_course_name
                          )";
    $coursesResult = pg_query($connection, $deleteCoursesQuery);
    $coursesDeleted = pg_affected_rows($coursesResult);
    
    if ($coursesDeleted > 0) {
        echo "<p class='success'>✅ Deleted $coursesDeleted unverified course mappings</p>";
    } else {
        echo "<p class='warning'>⚠️ No unverified course mappings deleted (may be verified or in use by other students)</p>";
    }
    
    pg_query($connection, "COMMIT");
    
    echo "<hr>";
    echo "<h2>✅ Cleanup Complete!</h2>";
    echo "<p>Test students and related data have been removed.</p>";
    echo "<p><a href='create_test_course_mapping_users.php'>← Create Test Users Again</a> | <a href='modules/admin/review_registrations.php'>View Registrations →</a></p>";
    
} catch (Exception $e) {
    pg_query($connection, "ROLLBACK");
    echo "<p class='error'><strong>❌ ERROR:</strong> " . htmlspecialchars($e->getMessage()) . "</p>";
}

pg_close($connection);
echo "</body></html>";
?>
