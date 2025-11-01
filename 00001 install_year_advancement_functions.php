<?php
/**
 * Install Year Level Advancement Functions
 * 
 * This script installs the required database functions for the year level advancement system.
 * Run this file once to set up:
 * 1. calculate_graduation_eligibility() - Determines if a student should graduate
 * 2. preview_year_level_advancement() - Generates advancement preview with distribution checks
 * 
 * Usage: Run this file directly in the browser or via command line
 */

require_once __DIR__ . '/config/database.php';

// Check if running from command line or browser
$is_cli = php_sapi_name() === 'cli';

if (!$is_cli) {
    echo "<!DOCTYPE html>
    <html>
    <head>
        <title>Install Year Advancement Functions</title>
        <style>
            body { font-family: Arial, sans-serif; max-width: 1200px; margin: 50px auto; padding: 20px; }
            .success { background: #d4edda; border: 1px solid #c3e6cb; color: #155724; padding: 15px; margin: 10px 0; border-radius: 5px; }
            .error { background: #f8d7da; border: 1px solid #f5c6cb; color: #721c24; padding: 15px; margin: 10px 0; border-radius: 5px; }
            .info { background: #d1ecf1; border: 1px solid #bee5eb; color: #0c5460; padding: 15px; margin: 10px 0; border-radius: 5px; }
            pre { background: #f8f9fa; padding: 15px; border-radius: 5px; overflow-x: auto; }
            h1 { color: #333; }
            h2 { color: #666; margin-top: 30px; }
        </style>
    </head>
    <body>
        <h1>📚 Year Level Advancement Functions Installation</h1>
        <p>Installing required database functions for the year advancement system...</p>
    ";
}

function output($message, $type = 'info') {
    global $is_cli;
    
    if ($is_cli) {
        $prefix = match($type) {
            'success' => '[✓] ',
            'error' => '[✗] ',
            default => '[i] '
        };
        echo $prefix . strip_tags($message) . "\n";
    } else {
        echo "<div class='$type'>$message</div>";
    }
}

// Function 1: calculate_graduation_eligibility
$function1_sql = "
-- Function to determine if a student should graduate based on year level and program duration
-- Used during year advancement to decide whether 4th/5th year students should graduate

CREATE OR REPLACE FUNCTION calculate_graduation_eligibility(p_student_id TEXT)
RETURNS TABLE(
    should_graduate BOOLEAN,
    reason TEXT,
    current_year_level_id INTEGER,
    current_year_level_name TEXT,
    program_duration INTEGER,
    years_completed INTEGER,
    next_year_level_id INTEGER,
    next_year_level_name TEXT
) 
LANGUAGE plpgsql
AS \$\$
DECLARE
    v_student RECORD;
    v_course_mapping RECORD;
    v_next_level RECORD;
    v_years_completed INTEGER;
    v_first_year INTEGER;
    v_current_year INTEGER;
BEGIN
    -- Get student information
    SELECT 
        s.student_id,
        s.year_level_id,
        s.course,
        s.first_registered_academic_year,
        s.current_academic_year,
        yl.name as year_level_name,
        yl.sort_order
    INTO v_student
    FROM students s
    LEFT JOIN year_levels yl ON s.year_level_id = yl.year_level_id
    WHERE s.student_id = p_student_id
      AND (s.is_archived IS NULL OR s.is_archived = FALSE);

    -- If student not found or already archived
    IF v_student.student_id IS NULL THEN
        RETURN QUERY SELECT 
            FALSE, 
            'Student not found or already archived'::TEXT,
            NULL::INTEGER,
            NULL::TEXT,
            NULL::INTEGER,
            NULL::INTEGER,
            NULL::INTEGER,
            NULL::TEXT;
        RETURN;
    END IF;

    -- Get course mapping to determine program duration
    SELECT 
        cm.program_duration,
        cm.normalized_course
    INTO v_course_mapping
    FROM courses_mapping cm
    WHERE cm.raw_course_name = v_student.course
      AND (cm.university_id = (SELECT university_id FROM students WHERE student_id = p_student_id) 
           OR cm.university_id IS NULL)
    ORDER BY cm.university_id NULLS LAST
    LIMIT 1;

    -- Calculate years completed (current academic year - first registered year)
    -- Extract starting year from academic year format (YYYY-YYYY)
    v_first_year := CAST(SPLIT_PART(v_student.first_registered_academic_year, '-', 1) AS INTEGER);
    v_current_year := CAST(SPLIT_PART(COALESCE(v_student.current_academic_year, 
                                       (SELECT year_code FROM academic_years WHERE is_current = TRUE)), 
                                     '-', 1) AS INTEGER);
    v_years_completed := v_current_year - v_first_year + 1; -- +1 because they are completing the current year

    -- Get next year level
    SELECT 
        yl.year_level_id,
        yl.name
    INTO v_next_level
    FROM year_levels yl
    WHERE yl.sort_order = v_student.sort_order + 1
    LIMIT 1;

    -- Determine graduation eligibility
    -- 5th year students always graduate
    IF v_student.year_level_name = '5th Year' THEN
        RETURN QUERY SELECT 
            TRUE,
            'Completed 5th year - automatic graduation'::TEXT,
            v_student.year_level_id,
            v_student.year_level_name,
            v_course_mapping.program_duration,
            v_years_completed,
            NULL::INTEGER,
            'Graduated'::TEXT;
        RETURN;
    END IF;

    -- 4th year students - check program duration
    IF v_student.year_level_name = '4th Year' THEN
        IF v_course_mapping.program_duration IS NULL THEN
            -- No course mapping - default to 4-year assumption for safety
            RETURN QUERY SELECT 
                TRUE,
                'Completed 4th year - no course mapping found, defaulting to 4-year program'::TEXT,
                v_student.year_level_id,
                v_student.year_level_name,
                4::INTEGER,
                v_years_completed,
                NULL::INTEGER,
                'Graduated'::TEXT;
            RETURN;
        ELSIF v_course_mapping.program_duration = 4 THEN
            -- 4-year program - graduate
            RETURN QUERY SELECT 
                TRUE,
                FORMAT('Completed 4-year program (%s)', v_course_mapping.normalized_course)::TEXT,
                v_student.year_level_id,
                v_student.year_level_name,
                v_course_mapping.program_duration,
                v_years_completed,
                NULL::INTEGER,
                'Graduated'::TEXT;
            RETURN;
        ELSIF v_course_mapping.program_duration = 5 THEN
            -- 5-year program - advance to 5th year
            RETURN QUERY SELECT 
                FALSE,
                FORMAT('Advancing to 5th year (%s is a 5-year program)', v_course_mapping.normalized_course)::TEXT,
                v_student.year_level_id,
                v_student.year_level_name,
                v_course_mapping.program_duration,
                v_years_completed,
                v_next_level.year_level_id,
                v_next_level.name;
            RETURN;
        END IF;
    END IF;

    -- All other year levels - normal advancement
    RETURN QUERY SELECT 
        FALSE,
        FORMAT('Normal advancement from %s to %s', v_student.year_level_name, COALESCE(v_next_level.name, 'Unknown'))::TEXT,
        v_student.year_level_id,
        v_student.year_level_name,
        v_course_mapping.program_duration,
        v_years_completed,
        v_next_level.year_level_id,
        v_next_level.name;
END;
\$\$;

COMMENT ON FUNCTION calculate_graduation_eligibility(TEXT) IS 
'Determines if a student should graduate during year advancement. Returns graduation status, reason, and next year level information.';
";

// Function 2: preview_year_level_advancement
$function2_sql = "
-- Preview year level advancement without executing
-- Shows what would happen if advancement runs, with distribution completion checks

CREATE OR REPLACE FUNCTION preview_year_level_advancement()
RETURNS TABLE(
    summary JSONB,
    students_advancing JSONB,
    students_graduating JSONB,
    warnings JSONB,
    can_advance BOOLEAN,
    blocking_reasons TEXT[]
) 
LANGUAGE plpgsql
AS \$\$
DECLARE
    v_current_academic_year TEXT;
    v_next_academic_year TEXT;
    v_distributions_complete BOOLEAN := FALSE;
    v_completed_semesters INTEGER := 0;
    v_already_advanced BOOLEAN := FALSE;
    v_blocking_reasons TEXT[] := ARRAY[]::TEXT[];
    v_summary JSONB;
    v_advancing JSONB;
    v_graduating JSONB;
    v_warnings JSONB := '[]'::JSONB;
    v_student RECORD;
    v_graduation_check RECORD;
    
    -- Categorization arrays
    v_advancing_1_to_2 JSONB := '[]'::JSONB;
    v_advancing_2_to_3 JSONB := '[]'::JSONB;
    v_advancing_3_to_4 JSONB := '[]'::JSONB;
    v_advancing_4_to_5 JSONB := '[]'::JSONB;
    v_graduating_4th JSONB := '[]'::JSONB;
    v_graduating_5th JSONB := '[]'::JSONB;
    v_no_course_mapping JSONB := '[]'::JSONB;
    v_edge_cases JSONB := '[]'::JSONB;
    
    -- Counters
    v_total_students INTEGER := 0;
    v_total_advancing INTEGER := 0;
    v_total_graduating INTEGER := 0;
BEGIN
    -- Get current academic year
    SELECT year_code, year_levels_advanced
    INTO v_current_academic_year, v_already_advanced
    FROM academic_years
    WHERE is_current = TRUE
    LIMIT 1;

    -- Check if academic year exists
    IF v_current_academic_year IS NULL THEN
        v_blocking_reasons := array_append(v_blocking_reasons, 'No current academic year set in system');
    END IF;

    -- Check if already advanced this year
    IF v_already_advanced = TRUE THEN
        v_blocking_reasons := array_append(v_blocking_reasons, 
            FORMAT('Year levels already advanced for academic year %s', v_current_academic_year));
    END IF;

    -- Check distribution completion (both semesters must be finalized)
    SELECT 
        COUNT(DISTINCT semester),
        BOOL_AND(finalized_at IS NOT NULL)
    INTO v_completed_semesters, v_distributions_complete
    FROM distribution_snapshots
    WHERE academic_year = v_current_academic_year
      AND finalized_at IS NOT NULL;

    IF v_completed_semesters < 2 THEN
        v_blocking_reasons := array_append(v_blocking_reasons,
            FORMAT('Only %s semester(s) distributed for %s. Both semesters must be finalized before advancing.', 
                   v_completed_semesters, v_current_academic_year));
    END IF;

    -- Calculate next academic year
    IF v_current_academic_year IS NOT NULL THEN
        DECLARE
            v_start_year INTEGER;
            v_end_year INTEGER;
        BEGIN
            v_start_year := CAST(SPLIT_PART(v_current_academic_year, '-', 1) AS INTEGER);
            v_end_year := CAST(SPLIT_PART(v_current_academic_year, '-', 2) AS INTEGER);
            v_next_academic_year := FORMAT('%s-%s', v_start_year + 1, v_end_year + 1);
        END;
    END IF;

    -- Process each active student
    FOR v_student IN 
        SELECT 
            s.student_id,
            s.first_name,
            s.last_name,
            s.extension_name,
            s.email,
            s.course,
            s.year_level_id,
            s.first_registered_academic_year,
            s.current_academic_year,
            yl.name as current_year_level,
            yl.sort_order,
            u.name as university_name,
            b.name as barangay_name
        FROM students s
        LEFT JOIN year_levels yl ON s.year_level_id = yl.year_level_id
        LEFT JOIN universities u ON s.university_id = u.university_id
        LEFT JOIN barangays b ON s.barangay_id = b.barangay_id
        WHERE s.status IN ('active', 'applicant')
          AND (s.is_archived IS NULL OR s.is_archived = FALSE)
        ORDER BY yl.sort_order, s.last_name, s.first_name
    LOOP
        v_total_students := v_total_students + 1;
        
        -- Get graduation eligibility
        SELECT * INTO v_graduation_check
        FROM calculate_graduation_eligibility(v_student.student_id);
        
        -- Build student info object
        DECLARE
            v_student_info JSONB;
        BEGIN
            v_student_info := jsonb_build_object(
                'student_id', v_student.student_id,
                'name', CONCAT(v_student.first_name, ' ', v_student.last_name, 
                              COALESCE(' ' || v_student.extension_name, '')),
                'email', v_student.email,
                'current_year_level', v_student.current_year_level,
                'course', v_student.course,
                'program_duration', v_graduation_check.program_duration,
                'university', v_student.university_name,
                'barangay', v_student.barangay_name,
                'years_enrolled', v_graduation_check.years_completed,
                'next_status', CASE 
                    WHEN v_graduation_check.should_graduate THEN 'Graduated (Auto-Archived)'
                    ELSE v_graduation_check.next_year_level_name 
                END,
                'reason', v_graduation_check.reason
            );

            -- Categorize student
            IF v_graduation_check.should_graduate THEN
                v_total_graduating := v_total_graduating + 1;
                
                IF v_student.current_year_level = '4th Year' THEN
                    v_graduating_4th := v_graduating_4th || v_student_info;
                ELSIF v_student.current_year_level = '5th Year' THEN
                    v_graduating_5th := v_graduating_5th || v_student_info;
                END IF;
            ELSE
                v_total_advancing := v_total_advancing + 1;
                
                -- Check for missing course mapping
                IF v_graduation_check.program_duration IS NULL THEN
                    v_no_course_mapping := v_no_course_mapping || v_student_info;
                    v_warnings := v_warnings || jsonb_build_object(
                        'student_id', v_student.student_id,
                        'warning', FORMAT('No course mapping for \"%s\"', v_student.course),
                        'severity', 'medium'
                    );
                END IF;
                
                -- Categorize by advancement type
                IF v_student.current_year_level = '1st Year' THEN
                    v_advancing_1_to_2 := v_advancing_1_to_2 || v_student_info;
                ELSIF v_student.current_year_level = '2nd Year' THEN
                    v_advancing_2_to_3 := v_advancing_2_to_3 || v_student_info;
                ELSIF v_student.current_year_level = '3rd Year' THEN
                    v_advancing_3_to_4 := v_advancing_3_to_4 || v_student_info;
                ELSIF v_student.current_year_level = '4th Year' THEN
                    v_advancing_4_to_5 := v_advancing_4_to_5 || v_student_info;
                ELSE
                    v_edge_cases := v_edge_cases || v_student_info;
                END IF;
            END IF;
        END;
    END LOOP;

    -- Build summary
    v_summary := jsonb_build_object(
        'current_academic_year', v_current_academic_year,
        'next_academic_year', v_next_academic_year,
        'distributions_completed', v_completed_semesters,
        'distributions_required', 2,
        'already_advanced', v_already_advanced,
        'total_students', v_total_students,
        'total_advancing', v_total_advancing,
        'total_graduating', v_total_graduating,
        'breakdown', jsonb_build_object(
            'advancing_1st_to_2nd', jsonb_array_length(v_advancing_1_to_2),
            'advancing_2nd_to_3rd', jsonb_array_length(v_advancing_2_to_3),
            'advancing_3rd_to_4th', jsonb_array_length(v_advancing_3_to_4),
            'advancing_4th_to_5th', jsonb_array_length(v_advancing_4_to_5),
            'graduating_4th_year', jsonb_array_length(v_graduating_4th),
            'graduating_5th_year', jsonb_array_length(v_graduating_5th),
            'no_course_mapping', jsonb_array_length(v_no_course_mapping),
            'edge_cases', jsonb_array_length(v_edge_cases)
        )
    );

    -- Build advancing students object
    v_advancing := jsonb_build_object(
        '1st_to_2nd', v_advancing_1_to_2,
        '2nd_to_3rd', v_advancing_2_to_3,
        '3rd_to_4th', v_advancing_3_to_4,
        '4th_to_5th', v_advancing_4_to_5,
        'no_course_mapping', v_no_course_mapping,
        'edge_cases', v_edge_cases
    );

    -- Build graduating students object
    v_graduating := jsonb_build_object(
        '4th_year', v_graduating_4th,
        '5th_year', v_graduating_5th
    );

    -- Determine if advancement can proceed
    RETURN QUERY SELECT 
        v_summary,
        v_advancing,
        v_graduating,
        v_warnings,
        (CARDINALITY(v_blocking_reasons) = 0)::BOOLEAN,
        v_blocking_reasons;
END;
\$\$;

COMMENT ON FUNCTION preview_year_level_advancement() IS 
'Previews year level advancement, checking distribution completion and categorizing students by advancement type. Returns detailed JSON with all students grouped by their next status.';
";

// Function 3: execute_year_level_advancement
$function3_sql = "
-- Execute year level advancement
-- Performs the actual advancement with transaction safety, audit logging, and automatic archiving

CREATE OR REPLACE FUNCTION execute_year_level_advancement(
    p_admin_id INTEGER,
    p_notes TEXT DEFAULT NULL
)
RETURNS TABLE(
    success BOOLEAN,
    message TEXT,
    students_advanced INTEGER,
    students_graduated INTEGER,
    execution_log JSONB
) 
LANGUAGE plpgsql
AS \$\$
DECLARE
    v_current_academic_year TEXT;
    v_next_academic_year TEXT;
    v_can_advance BOOLEAN;
    v_blocking_reasons TEXT[];
    v_students_advanced INTEGER := 0;
    v_students_graduated INTEGER := 0;
    v_student RECORD;
    v_graduation_check RECORD;
    v_next_year_level_id INTEGER;
    v_execution_log JSONB := '[]'::JSONB;
    v_log_entry JSONB;
    v_audit_id INTEGER;
BEGIN
    -- Pre-flight checks using preview function
    SELECT can_advance, blocking_reasons, summary
    INTO v_can_advance, v_blocking_reasons, v_execution_log
    FROM preview_year_level_advancement();
    
    -- Abort if cannot advance
    IF NOT v_can_advance THEN
        RETURN QUERY SELECT 
            FALSE,
            'Cannot advance: ' || array_to_string(v_blocking_reasons, '; '),
            0::INTEGER,
            0::INTEGER,
            jsonb_build_object(
                'error', 'Pre-flight check failed',
                'blocking_reasons', v_blocking_reasons
            );
        RETURN;
    END IF;
    
    -- Get current academic year info
    SELECT year_code INTO v_current_academic_year
    FROM academic_years
    WHERE is_current = TRUE
    LIMIT 1;
    
    -- Calculate next academic year
    DECLARE
        v_start_year INTEGER;
        v_end_year INTEGER;
    BEGIN
        v_start_year := CAST(SPLIT_PART(v_current_academic_year, '-', 1) AS INTEGER);
        v_end_year := CAST(SPLIT_PART(v_current_academic_year, '-', 2) AS INTEGER);
        v_next_academic_year := FORMAT('%s-%s', v_start_year + 1, v_end_year + 1);
    END;
    
    -- Start transaction-safe execution
    BEGIN
        -- Log the start of advancement
        INSERT INTO audit_logs (
            user_id, user_type, username, event_type, event_category,
            action_description, metadata, status
        ) VALUES (
            p_admin_id, 'admin', 
            (SELECT username FROM admins WHERE admin_id = p_admin_id),
            'year_advancement_started',
            'academic_year',
            FORMAT('Started year level advancement: %s → %s', v_current_academic_year, v_next_academic_year),
            jsonb_build_object(
                'current_year', v_current_academic_year,
                'next_year', v_next_academic_year,
                'notes', p_notes
            ),
            'in_progress'
        ) RETURNING audit_id INTO v_audit_id;
        
        -- Process each active student
        FOR v_student IN 
            SELECT 
                s.student_id,
                s.first_name,
                s.last_name,
                s.year_level_id,
                s.year_level_history,
                yl.name as current_year_level,
                yl.sort_order
            FROM students s
            LEFT JOIN year_levels yl ON s.year_level_id = yl.year_level_id
            WHERE s.status IN ('active', 'applicant')
              AND (s.is_archived IS NULL OR s.is_archived = FALSE)
            ORDER BY yl.sort_order, s.last_name, s.first_name
        LOOP
            -- Get graduation eligibility for this student
            SELECT * INTO v_graduation_check
            FROM calculate_graduation_eligibility(v_student.student_id);
            
            IF v_graduation_check.should_graduate THEN
                -- Graduate and archive this student
                UPDATE students
                SET 
                    is_archived = TRUE,
                    archived_at = NOW(),
                    archived_by = NULL,
                    archive_reason = 'graduated',
                    current_academic_year = v_next_academic_year,
                    last_year_level_update = NOW(),
                    year_level_history = COALESCE(year_level_history, '[]'::JSONB) || 
                        jsonb_build_object(
                            'year', v_next_academic_year,
                            'level', 'Graduated',
                            'updated_at', NOW(),
                            'reason', v_graduation_check.reason
                        )
                WHERE student_id = v_student.student_id;
                
                v_students_graduated := v_students_graduated + 1;
                
                v_log_entry := jsonb_build_object(
                    'student_id', v_student.student_id,
                    'name', v_student.first_name || ' ' || v_student.last_name,
                    'action', 'graduated',
                    'from_level', v_student.current_year_level,
                    'to_level', 'Graduated (Archived)',
                    'reason', v_graduation_check.reason
                );
                
            ELSE
                -- Advance to next year level
                UPDATE students
                SET 
                    year_level_id = v_graduation_check.next_year_level_id,
                    current_academic_year = v_next_academic_year,
                    last_year_level_update = NOW(),
                    year_level_history = COALESCE(year_level_history, '[]'::JSONB) || 
                        jsonb_build_object(
                            'year', v_next_academic_year,
                            'level', v_graduation_check.next_year_level_name,
                            'updated_at', NOW(),
                            'reason', 'Annual year level advancement'
                        )
                WHERE student_id = v_student.student_id;
                
                v_students_advanced := v_students_advanced + 1;
                
                v_log_entry := jsonb_build_object(
                    'student_id', v_student.student_id,
                    'name', v_student.first_name || ' ' || v_student.last_name,
                    'action', 'advanced',
                    'from_level', v_student.current_year_level,
                    'to_level', v_graduation_check.next_year_level_name,
                    'reason', v_graduation_check.reason
                );
            END IF;
            
            v_execution_log := v_execution_log || v_log_entry;
        END LOOP;
        
        -- Mark academic year as advanced
        UPDATE academic_years
        SET 
            year_levels_advanced = TRUE,
            advanced_at = NOW(),
            advanced_by = p_admin_id
        WHERE year_code = v_current_academic_year;
        
        -- Update the audit log entry to success
        UPDATE audit_logs
        SET 
            status = 'success',
            action_description = FORMAT(
                'Completed year level advancement: %s students advanced, %s students graduated',
                v_students_advanced, v_students_graduated
            ),
            metadata = metadata || jsonb_build_object(
                'students_advanced', v_students_advanced,
                'students_graduated', v_students_graduated,
                'completed_at', NOW()
            )
        WHERE audit_id = v_audit_id;
        
        RETURN QUERY SELECT 
            TRUE,
            FORMAT('Successfully advanced %s students and graduated %s students', 
                   v_students_advanced, v_students_graduated),
            v_students_advanced,
            v_students_graduated,
            jsonb_build_object(
                'current_year', v_current_academic_year,
                'next_year', v_next_academic_year,
                'students_advanced', v_students_advanced,
                'students_graduated', v_students_graduated,
                'executed_at', NOW(),
                'executed_by', p_admin_id,
                'audit_id', v_audit_id
            );
        
    EXCEPTION WHEN OTHERS THEN
        UPDATE audit_logs
        SET 
            status = 'error',
            action_description = 'Year level advancement failed: ' || SQLERRM
        WHERE audit_id = v_audit_id;
        
        RETURN QUERY SELECT 
            FALSE,
            'Error during advancement: ' || SQLERRM,
            0::INTEGER,
            0::INTEGER,
            jsonb_build_object(
                'error', SQLERRM,
                'sqlstate', SQLSTATE
            );
    END;
END;
\$\$;

COMMENT ON FUNCTION execute_year_level_advancement(INTEGER, TEXT) IS 
'Executes year level advancement for all active students. Transaction-safe with full audit logging.';
";

// Install functions
try {
    output("<h2>Step 1: Installing calculate_graduation_eligibility()</h2>");
    
    $result1 = pg_query($connection, $function1_sql);
    
    if ($result1) {
        output("✓ Function <strong>calculate_graduation_eligibility()</strong> installed successfully!", 'success');
    } else {
        throw new Exception("Failed to install calculate_graduation_eligibility(): " . pg_last_error($connection));
    }
    
    output("<h2>Step 2: Installing preview_year_level_advancement()</h2>");
    
    $result2 = pg_query($connection, $function2_sql);
    
    if ($result2) {
        output("✓ Function <strong>preview_year_level_advancement()</strong> installed successfully!", 'success');
    } else {
        throw new Exception("Failed to install preview_year_level_advancement(): " . pg_last_error($connection));
    }
    
    output("<h2>Step 3: Installing execute_year_level_advancement()</h2>");
    
    $result3 = pg_query($connection, $function3_sql);
    
    if ($result3) {
        output("✓ Function <strong>execute_year_level_advancement()</strong> installed successfully!", 'success');
    } else {
        throw new Exception("Failed to install execute_year_level_advancement(): " . pg_last_error($connection));
    }
    
    // Test the functions
    output("<h2>Step 4: Testing Functions</h2>");
    
    // Test preview function
    $test_result = pg_query($connection, "SELECT * FROM preview_year_level_advancement()");
    
    if ($test_result && pg_num_rows($test_result) > 0) {
        $test_row = pg_fetch_assoc($test_result);
        $summary = json_decode($test_row['summary'], true);
        
        output("✓ Functions are working correctly!", 'success');
        
        if (!$is_cli) {
            echo "<div class='info'>";
            echo "<h3>Preview Summary:</h3>";
            echo "<ul>";
            echo "<li><strong>Current Academic Year:</strong> " . ($summary['current_academic_year'] ?? 'Not set') . "</li>";
            echo "<li><strong>Next Academic Year:</strong> " . ($summary['next_academic_year'] ?? 'N/A') . "</li>";
            echo "<li><strong>Distributions Completed:</strong> " . $summary['distributions_completed'] . " / " . $summary['distributions_required'] . "</li>";
            echo "<li><strong>Total Active Students:</strong> " . $summary['total_students'] . "</li>";
            echo "<li><strong>Students Advancing:</strong> " . $summary['total_advancing'] . "</li>";
            echo "<li><strong>Students Graduating:</strong> " . $summary['total_graduating'] . "</li>";
            echo "<li><strong>Already Advanced:</strong> " . ($summary['already_advanced'] ? 'Yes' : 'No') . "</li>";
            echo "</ul>";
            echo "</div>";
        } else {
            echo "\nPreview Summary:\n";
            echo "  Current Academic Year: " . ($summary['current_academic_year'] ?? 'Not set') . "\n";
            echo "  Next Academic Year: " . ($summary['next_academic_year'] ?? 'N/A') . "\n";
            echo "  Distributions Completed: " . $summary['distributions_completed'] . " / " . $summary['distributions_required'] . "\n";
            echo "  Total Active Students: " . $summary['total_students'] . "\n";
            echo "  Students Advancing: " . $summary['total_advancing'] . "\n";
            echo "  Students Graduating: " . $summary['total_graduating'] . "\n";
            echo "  Already Advanced: " . ($summary['already_advanced'] ? 'Yes' : 'No') . "\n";
        }
    } else {
        output("⚠ Functions installed but test query returned no results", 'info');
    }
    
    output("<h2>✅ Installation Complete!</h2>", 'success');
    output("You can now use the Year Level Advancement feature in the admin panel.", 'info');
    
    if (!$is_cli) {
        echo "<div class='info'>";
        echo "<h3>Next Steps:</h3>";
        echo "<ol>";
        echo "<li>Go to the admin panel</li>";
        echo "<li>Navigate to <strong>Advance Year Levels</strong></li>";
        echo "<li>Click <strong>Preview Year Advancement</strong> to see what will happen</li>";
        echo "<li>Review the students list and confirm to execute</li>";
        echo "</ol>";
        echo "</div>";
    }
    
} catch (Exception $e) {
    output("✗ Error: " . $e->getMessage(), 'error');
    
    if (!$is_cli) {
        echo "<div class='error'>";
        echo "<h3>Installation Failed</h3>";
        echo "<p>Please check your database connection and try again.</p>";
        echo "<pre>" . htmlspecialchars($e->getMessage()) . "</pre>";
        echo "</div>";
    }
}

if (!$is_cli) {
    echo "</body></html>";
}

pg_close($connection);
?>
