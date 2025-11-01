<?php
/**
 * Fix Year Advancement Functions - Update Status Check
 * 
 * This fixes two issues:
 * 1. Preview and execution now include students with status 'applicant' (not just 'active')
 * 2. Execution properly updates all relevant student columns
 */

include 'config/database.php';

echo "=== Fixing Year Advancement Functions ===\n\n";

// Updated preview function - includes applicant status
$preview_function = "
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

    -- Process each student (now includes 'applicant' status)
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
            s.status,
            yl.name as current_year_level,
            yl.sort_order,
            u.name as university_name,
            b.name as barangay_name
        FROM students s
        LEFT JOIN year_levels yl ON s.year_level_id = yl.year_level_id
        LEFT JOIN universities u ON s.university_id = u.university_id
        LEFT JOIN barangays b ON s.barangay_id = b.barangay_id
        WHERE s.status IN ('active', 'applicant')  -- FIXED: Include applicants
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
                'status', v_student.status,
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
";

echo "Step 1: Installing updated preview_year_level_advancement()...\n";
if (pg_query($connection, $preview_function)) {
    echo "✓ Preview function updated successfully!\n\n";
} else {
    die("✗ Error: " . pg_last_error($connection) . "\n");
}

// Updated execution function - includes all statuses and updates all columns
$execute_function = "
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
        
        -- Process each student (includes 'applicant' and 'active')
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
            WHERE s.status IN ('active', 'applicant')  -- FIXED: Include all eligible statuses
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
                            'academic_year', v_next_academic_year,
                            'year_level_id', NULL,
                            'year_level_name', 'Graduated',
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
                -- Advance to next year level - UPDATE ALL RELEVANT COLUMNS
                UPDATE students
                SET 
                    year_level_id = v_graduation_check.next_year_level_id,
                    current_academic_year = v_next_academic_year,
                    last_year_level_update = NOW(),
                    year_level_history = COALESCE(year_level_history, '[]'::JSONB) || 
                        jsonb_build_object(
                            'academic_year', v_next_academic_year,
                            'year_level_id', v_graduation_check.next_year_level_id,
                            'year_level_name', v_graduation_check.next_year_level_name,
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
                'audit_id', v_audit_id,
                'execution_log', v_execution_log
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
'Executes year level advancement for all eligible students. Updates year_level_id, current_academic_year, year_level_history, and archives graduating students. Includes both active and applicant status students.';
";

echo "Step 2: Installing updated execute_year_level_advancement()...\n";
if (pg_query($connection, $execute_function)) {
    echo "✓ Execute function updated successfully!\n\n";
} else {
    die("✗ Error: " . pg_last_error($connection) . "\n");
}

echo "=== Fix Complete! ===\n\n";
echo "Changes made:\n";
echo "1. ✓ Both functions now include students with status 'applicant' AND 'active'\n";
echo "2. ✓ Execute function properly updates:\n";
echo "   - year_level_id (next year level ID)\n";
echo "   - current_academic_year (next academic year)\n";
echo "   - last_year_level_update (NOW timestamp)\n";
echo "   - year_level_history (adds new entry with full details)\n";
echo "3. ✓ Graduating students are properly archived with year_level_history updated\n\n";

echo "IMPORTANT: The 'year_levels_advanced' flag is currently TRUE.\n";
echo "You need to reset it manually to test the advancement:\n\n";
echo "Run: UPDATE academic_years SET year_levels_advanced = FALSE WHERE is_current = TRUE;\n\n";

pg_close($connection);
?>
