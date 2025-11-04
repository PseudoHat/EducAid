<?php
/**
 * Create Test Students for Course Mapping Demo
 * Run this file via browser: http://localhost/EducAid/create_test_course_mapping_users.php
 */

include __DIR__ . '/config/database.php';

echo "<html><head><title>Create Test Course Mapping Users</title>";
echo "<style>body{font-family:Arial;padding:20px;background:#f5f5f5;} .success{color:green;} .error{color:red;} .info{color:#0066cc;} pre{background:#fff;padding:15px;border-radius:5px;border:1px solid #ddd;}</style>";
echo "</head><body>";
echo "<h1>🧪 Create Test Students for Course Mapping</h1>";

try {
    // Start transaction
    pg_query($connection, "BEGIN");
    
    echo "<h2>Creating Test Students...</h2>";
    
    // ==========================================
    // TEST STUDENT 1: NEW COURSE (BS Data Science)
    // ==========================================
    echo "<h3 class='info'>📘 Test Student 1: Maria Cruz - BS Data Science (NEW COURSE)</h3>";
    
    // Get IDs for foreign keys
    $universityResult = pg_query($connection, "SELECT university_id FROM universities WHERE name ILIKE '%cavite state%' LIMIT 1");
    if (!$universityResult || pg_num_rows($universityResult) == 0) {
        $universityResult = pg_query($connection, "SELECT university_id FROM universities ORDER BY university_id LIMIT 1");
    }
    $universityId = pg_fetch_result($universityResult, 0, 0);
    
    $yearLevelResult = pg_query($connection, "SELECT year_level_id FROM year_levels WHERE name ILIKE '%third%year%' LIMIT 1");
    if (!$yearLevelResult || pg_num_rows($yearLevelResult) == 0) {
        // Fallback: get year level with sort_order = 3 or just the 3rd one
        $yearLevelResult = pg_query($connection, "SELECT year_level_id FROM year_levels ORDER BY sort_order LIMIT 1 OFFSET 2");
    }
    if (!$yearLevelResult || pg_num_rows($yearLevelResult) == 0) {
        // Final fallback: just get any year level
        $yearLevelResult = pg_query($connection, "SELECT year_level_id FROM year_levels ORDER BY year_level_id LIMIT 1");
    }
    $yearLevelId = pg_fetch_result($yearLevelResult, 0, 0);
    
    $barangayResult = pg_query($connection, "SELECT barangay_id FROM barangays ORDER BY RANDOM() LIMIT 1");
    $barangayId = pg_fetch_result($barangayResult, 0, 0);
    
    echo "<p>Using University ID: $universityId, Year Level ID: $yearLevelId, Barangay ID: $barangayId</p>";
    
    // Insert Student 1
    $password = password_hash('Test123!', PASSWORD_DEFAULT);
    $student1Query = "INSERT INTO students (
        student_id, first_name, middle_name, last_name, extension_name,
        bdate, sex, email, mobile, password,
        university_id, year_level_id, barangay_id, municipality_id, course, status,
        application_date, school_student_id, confidence_score
    ) VALUES (
        'TEST-DS-2024-001', 'Maria', 'Santos', 'Cruz', '',
        '2003-05-15', 'Female', 'maria.cruz.datasci@test.edu', '+639171234567', $1,
        $2, $3, $4, 1, 'BS Data Science', 'under_registration',
        NOW() - INTERVAL '2 hours', '2024-3-DS-001', 78.5
    ) ON CONFLICT (student_id) DO UPDATE SET 
        course = EXCLUDED.course,
        status = EXCLUDED.status,
        confidence_score = EXCLUDED.confidence_score";
    
    $result = pg_query_params($connection, $student1Query, [
        $password, $universityId, $yearLevelId, $barangayId
    ]);
    
    if ($result) {
        echo "<p class='success'>✅ Created Maria Cruz (TEST-DS-2024-001)</p>";
        echo "<ul>";
        echo "<li><strong>Course:</strong> BS Data Science (NEW - will create unverified mapping)</li>";
        echo "<li><strong>Email:</strong> maria.cruz.datasci@test.edu</li>";
        echo "<li><strong>Password:</strong> Test123!</li>";
        echo "<li><strong>Confidence Score:</strong> 78.5%</li>";
        echo "</ul>";
    } else {
        throw new Exception("Failed to create Student 1: " . pg_last_error($connection));
    }
    
    // ==========================================
    // TEST STUDENT 2: EXISTING UNVERIFIED COURSE
    // ==========================================
    echo "<h3 class='info'>📗 Test Student 2: Juan Reyes - BS Cyber Security (EXISTING UNVERIFIED)</h3>";
    
    // First, create the unverified course mapping (or update if exists)
    $courseMappingQuery = "INSERT INTO courses_mapping (
        raw_course_name, normalized_course, course_category,
        program_duration, university_id, is_verified,
        occurrence_count, created_at, last_seen
    ) VALUES (
        'BS Cyber Security', 'BS Cybersecurity', 'Engineering & Technology',
        4, NULL, FALSE,
        1, NOW() - INTERVAL '1 day', NOW() - INTERVAL '1 day'
    ) ON CONFLICT (raw_course_name, university_id) DO UPDATE SET
        occurrence_count = courses_mapping.occurrence_count + 1,
        last_seen = NOW()";
    
    $mappingResult = pg_query($connection, $courseMappingQuery);
    if ($mappingResult) {
        echo "<p class='success'>✅ Created/Updated course mapping for 'BS Cyber Security'</p>";
    } else {
        echo "<p class='error'>⚠️ Note: Could not create course mapping (may already exist)</p>";
    }
    
    // Get different university for student 2
    $university2Result = pg_query($connection, "SELECT university_id FROM universities WHERE name ILIKE '%lyceum%' LIMIT 1");
    if (!$university2Result || pg_num_rows($university2Result) == 0) {
        // Fallback: get a different university than student 1
        $university2Result = pg_query_params($connection, "SELECT university_id FROM universities WHERE university_id != $1 ORDER BY university_id LIMIT 1", [$universityId]);
    }
    if (!$university2Result || pg_num_rows($university2Result) == 0) {
        // Final fallback: just use any university
        $university2Result = pg_query($connection, "SELECT university_id FROM universities ORDER BY university_id LIMIT 1 OFFSET 1");
    }
    $university2Id = pg_fetch_result($university2Result, 0, 0);
    
    $yearLevel2Result = pg_query($connection, "SELECT year_level_id FROM year_levels WHERE name ILIKE '%second%year%' LIMIT 1");
    if (!$yearLevel2Result || pg_num_rows($yearLevel2Result) == 0) {
        // Fallback: get year level with sort_order = 2 or the 2nd one
        $yearLevel2Result = pg_query($connection, "SELECT year_level_id FROM year_levels ORDER BY sort_order LIMIT 1 OFFSET 1");
    }
    if (!$yearLevel2Result || pg_num_rows($yearLevel2Result) == 0) {
        // Final fallback
        $yearLevel2Result = pg_query($connection, "SELECT year_level_id FROM year_levels ORDER BY year_level_id LIMIT 1");
    }
    $yearLevel2Id = pg_fetch_result($yearLevel2Result, 0, 0);
    
    $barangay2Result = pg_query($connection, "SELECT barangay_id FROM barangays ORDER BY RANDOM() LIMIT 1");
    $barangay2Id = pg_fetch_result($barangay2Result, 0, 0);
    
    // Insert Student 2
    $student2Query = "INSERT INTO students (
        student_id, first_name, middle_name, last_name, extension_name,
        bdate, sex, email, mobile, password,
        university_id, year_level_id, barangay_id, municipality_id, course, status,
        application_date, school_student_id, confidence_score
    ) VALUES (
        'TEST-CS-2024-002', 'Juan', 'Dela', 'Reyes', 'Jr.',
        '2002-08-22', 'Male', 'juan.reyes.cybersec@test.edu', '+639181234567', $1,
        $2, $3, $4, 1, 'BS Cyber Security', 'under_registration',
        NOW() - INTERVAL '1 hour', '2024-2-CS-002', 82.3
    ) ON CONFLICT (student_id) DO UPDATE SET 
        course = EXCLUDED.course,
        status = EXCLUDED.status,
        confidence_score = EXCLUDED.confidence_score";
    
    $result2 = pg_query_params($connection, $student2Query, [
        $password, $university2Id, $yearLevel2Id, $barangay2Id
    ]);
    
    if ($result2) {
        echo "<p class='success'>✅ Created Juan Reyes (TEST-CS-2024-002)</p>";
        echo "<ul>";
        echo "<li><strong>Course:</strong> BS Cyber Security (EXISTING but UNVERIFIED)</li>";
        echo "<li><strong>Email:</strong> juan.reyes.cybersec@test.edu</li>";
        echo "<li><strong>Password:</strong> Test123!</li>";
        echo "<li><strong>Confidence Score:</strong> 82.3%</li>";
        echo "</ul>";
    } else {
        throw new Exception("Failed to create Student 2: " . pg_last_error($connection));
    }
    
    // Commit transaction
    pg_query($connection, "COMMIT");
    
    // ==========================================
    // VERIFICATION
    // ==========================================
    echo "<hr>";
    echo "<h2>📊 Verification Results</h2>";
    
    // Check students
    echo "<h3>Created Students:</h3>";
    $verifyStudents = pg_query($connection, "
        SELECT 
            s.student_id,
            CONCAT(s.first_name, ' ', s.last_name) as full_name,
            s.course,
            s.status,
            s.confidence_score,
            s.email,
            u.name as university_name,
            yl.name as year_level_name
        FROM students s
        LEFT JOIN universities u ON s.university_id = u.university_id
        LEFT JOIN year_levels yl ON s.year_level_id = yl.year_level_id
        WHERE s.student_id IN ('TEST-DS-2024-001', 'TEST-CS-2024-002')
        ORDER BY s.student_id
    ");
    
    echo "<pre>";
    echo str_pad("Student ID", 20) . str_pad("Name", 25) . str_pad("Course", 30) . str_pad("Status", 20) . "Confidence\n";
    echo str_repeat("-", 115) . "\n";
    while ($row = pg_fetch_assoc($verifyStudents)) {
        echo str_pad($row['student_id'], 20) . 
             str_pad($row['full_name'], 25) . 
             str_pad($row['course'], 30) . 
             str_pad($row['status'], 20) . 
             $row['confidence_score'] . "%\n";
    }
    echo "</pre>";
    
    // Check course mappings
    echo "<h3>Course Mappings:</h3>";
    $verifyCourses = pg_query($connection, "
        SELECT 
            cm.mapping_id,
            cm.raw_course_name,
            cm.normalized_course,
            cm.course_category,
            cm.is_verified,
            cm.occurrence_count,
            (SELECT COUNT(*) FROM students WHERE course = cm.raw_course_name) as student_count
        FROM courses_mapping cm
        WHERE cm.raw_course_name IN ('BS Data Science', 'BS Cyber Security')
        ORDER BY cm.raw_course_name
    ");
    
    echo "<pre>";
    echo str_pad("Course", 25) . str_pad("Normalized", 25) . str_pad("Category", 30) . str_pad("Verified", 12) . "Students\n";
    echo str_repeat("-", 112) . "\n";
    while ($row = pg_fetch_assoc($verifyCourses)) {
        echo str_pad($row['raw_course_name'], 25) . 
             str_pad($row['normalized_course'], 25) . 
             str_pad($row['course_category'], 30) . 
             str_pad($row['is_verified'] === 't' ? '✅ YES' : '❌ NO', 12) . 
             $row['student_count'] . "\n";
    }
    echo "</pre>";
    
    // ==========================================
    // INSTRUCTIONS
    // ==========================================
    echo "<hr>";
    echo "<h2>📋 Testing Instructions</h2>";
    echo "<div style='background:#fff;padding:20px;border-radius:5px;border:1px solid #ddd;'>";
    echo "<h3>1️⃣ Review Registrations (review_registrations.php)</h3>";
    echo "<ul>";
    echo "<li>Navigate to: <a href='modules/admin/review_registrations.php' target='_blank'>Review Registrations</a></li>";
    echo "<li>You should see both Maria Cruz and Juan Reyes in the pending list</li>";
    echo "<li>Notice their course mapping status badges (NEW vs UNVERIFIED)</li>";
    echo "<li>Try approving one or both students</li>";
    echo "</ul>";
    
    echo "<h3>2️⃣ Manage Course Mappings (manage_course_mappings.php)</h3>";
    echo "<ul>";
    echo "<li>Navigate to: <a href='modules/admin/manage_course_mappings.php' target='_blank'>Manage Course Mappings</a></li>";
    echo "<li>Filter by 'Unverified' courses</li>";
    echo "<li>You should see 'BS Data Science' and 'BS Cyber Security'</li>";
    echo "<li>Try verifying them with proper normalized names and categories</li>";
    echo "<li>After verification, check review_registrations.php again to see verified badges</li>";
    echo "</ul>";
    
    echo "<h3>3️⃣ Test Workflow:</h3>";
    echo "<ol>";
    echo "<li><strong>BS Data Science (NEW):</strong> When you approve Maria, system will auto-create an unverified mapping</li>";
    echo "<li><strong>BS Cyber Security (EXISTING):</strong> When you approve Juan, the existing mapping occurrence_count increases</li>";
    echo "<li>Go to Course Mappings and verify both courses</li>";
    echo "<li>Set normalized names (e.g., 'BS Data Science', 'BS Cybersecurity')</li>";
    echo "<li>Assign categories and program duration</li>";
    echo "</ol>";
    
    echo "<h3>🧹 Cleanup</h3>";
    echo "<p>To remove test data, run this SQL:</p>";
    echo "<pre style='background:#f9f9f9;'>";
    echo "DELETE FROM documents WHERE student_id IN ('TEST-DS-2024-001', 'TEST-CS-2024-002');\n";
    echo "DELETE FROM students WHERE student_id IN ('TEST-DS-2024-001', 'TEST-CS-2024-002');\n";
    echo "DELETE FROM courses_mapping WHERE raw_course_name IN ('BS Data Science', 'BS Cyber Security') AND is_verified = FALSE;\n";
    echo "</pre>";
    echo "<p>Or click: <a href='cleanup_test_course_users.php' style='color:red;font-weight:bold;'>🗑️ Delete Test Users</a></p>";
    echo "</div>";
    
    echo "<hr>";
    echo "<p class='success' style='font-size:18px;'><strong>✅ SUCCESS! Test students created successfully!</strong></p>";
    
} catch (Exception $e) {
    pg_query($connection, "ROLLBACK");
    echo "<p class='error'><strong>❌ ERROR:</strong> " . htmlspecialchars($e->getMessage()) . "</p>";
    echo "<pre>" . htmlspecialchars(pg_last_error($connection)) . "</pre>";
}

pg_close($connection);
echo "</body></html>";
?>
