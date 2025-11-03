<?php
/**
 * Fix calculate_graduation_eligibility - Variable Scope Issue
 */

include 'config/database.php';

echo "=== Fixing calculate_graduation_eligibility Function ===\n\n";

$function_sql = "
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
    v_years_completed INTEGER;  -- Moved to main DECLARE block
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
    -- Extract starting year from academic year format \"2024-2025\"
    v_first_year := CAST(SPLIT_PART(v_student.first_registered_academic_year, '-', 1) AS INTEGER);
    v_current_year := CAST(SPLIT_PART(COALESCE(v_student.current_academic_year, 
                                       (SELECT year_code FROM academic_years WHERE is_current = TRUE)), 
                                     '-', 1) AS INTEGER);
    v_years_completed := v_current_year - v_first_year + 1; -- +1 because they're completing the current year

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

echo "Installing fixed calculate_graduation_eligibility()...\n";
if (pg_query($connection, $function_sql)) {
    echo "✓ Function fixed successfully!\n\n";
    echo "The variable scope issue has been resolved.\n";
} else {
    echo "✗ Error: " . pg_last_error($connection) . "\n";
}

pg_close($connection);
?>
