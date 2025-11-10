-- Active: 1762333255156@@shortline.proxy.rlwy.net@26026@railway
-- ============================================================================
-- RAILWAY DATABASE MIGRATION: New Year Advancement & Graduation System
-- ============================================================================
-- Date: November 11, 2025
-- Purpose: Update Railway database to match localhost schema with new 
--          year-level based graduation logic (removes courses_mapping dependency)
--
-- CRITICAL: This migration makes BREAKING CHANGES
-- - Drops courses_mapping table and related functions
-- - Replaces trigger logic to use current_year_level instead of year_level_id
-- - Adds new columns: current_year_level, is_graduating, status_academic_year
--
-- BACKUP YOUR DATABASE BEFORE RUNNING THIS MIGRATION!
-- ============================================================================

BEGIN;

-- ============================================================================
-- SECTION 0: Add Missing Columns to students Table
-- ============================================================================
-- These columns are required by the new graduation system and PHP code

DO $$
BEGIN
    -- Add current_year_level column (replaces year_level_id FK lookup)
    IF NOT EXISTS (
        SELECT 1 FROM information_schema.columns 
        WHERE table_name = 'students' AND column_name = 'current_year_level'
    ) THEN
        ALTER TABLE students 
        ADD COLUMN current_year_level VARCHAR(20);
        
        RAISE NOTICE 'Added column: current_year_level';
    ELSE
        RAISE NOTICE 'Column current_year_level already exists';
    END IF;

    -- Add is_graduating flag (student self-declaration)
    IF NOT EXISTS (
        SELECT 1 FROM information_schema.columns 
        WHERE table_name = 'students' AND column_name = 'is_graduating'
    ) THEN
        ALTER TABLE students 
        ADD COLUMN is_graduating BOOLEAN DEFAULT FALSE;
        
        RAISE NOTICE 'Added column: is_graduating';
    ELSE
        RAISE NOTICE 'Column is_graduating already exists';
    END IF;

    -- Add last_status_update timestamp
    IF NOT EXISTS (
        SELECT 1 FROM information_schema.columns 
        WHERE table_name = 'students' AND column_name = 'last_status_update'
    ) THEN
        ALTER TABLE students 
        ADD COLUMN last_status_update TIMESTAMP WITHOUT TIME ZONE DEFAULT NOW();
        
        RAISE NOTICE 'Added column: last_status_update';
    ELSE
        RAISE NOTICE 'Column last_status_update already exists';
    END IF;

    -- Add status_academic_year (tracks which academic year the status applies to)
    IF NOT EXISTS (
        SELECT 1 FROM information_schema.columns 
        WHERE table_name = 'students' AND column_name = 'status_academic_year'
    ) THEN
        ALTER TABLE students 
        ADD COLUMN status_academic_year VARCHAR(20);
        
        RAISE NOTICE 'Added column: status_academic_year';
    ELSE
        RAISE NOTICE 'Column status_academic_year already exists';
    END IF;
END $$;


-- ============================================================================
-- SECTION 1: Migrate Existing Data (Year Levels)
-- ============================================================================
-- Populate current_year_level from year_level_id before we change the trigger

DO $$
DECLARE
    v_updated_count INTEGER;
BEGIN
    -- Map year_level_id to current_year_level text values
    UPDATE students s
    SET current_year_level = yl.name
    FROM year_levels yl
    WHERE s.year_level_id = yl.year_level_id
      AND s.current_year_level IS NULL;
    
    GET DIAGNOSTICS v_updated_count = ROW_COUNT;
    RAISE NOTICE 'Migrated % students: year_level_id -> current_year_level', v_updated_count;
END $$;


-- ============================================================================
-- SECTION 2: Drop OLD Courses Mapping Infrastructure
-- ============================================================================
-- Remove old system that depended on courses_mapping table lookup

DO $$
BEGIN
    -- Drop the OLD trigger (watches course, year_level_id - wrong columns)
    DROP TRIGGER IF EXISTS trigger_calculate_graduation_year ON students;
    RAISE NOTICE 'Dropped OLD trigger: trigger_calculate_graduation_year';

    -- Drop the OLD graduation calculation function (uses courses_mapping)
    DROP FUNCTION IF EXISTS calculate_expected_graduation_year() CASCADE;
    RAISE NOTICE 'Dropped OLD function: calculate_expected_graduation_year()';

    -- Drop course mapping helper functions
    DROP FUNCTION IF EXISTS find_course_mapping(character varying, integer) CASCADE;
    DROP FUNCTION IF EXISTS upsert_course_mapping(character varying, character varying, integer, character varying, integer, integer) CASCADE;
    DROP FUNCTION IF EXISTS update_courses_mapping_updated_at() CASCADE;
    RAISE NOTICE 'Dropped course mapping helper functions';

    -- Drop the courses_mapping table (no longer needed)
    DROP TABLE IF EXISTS courses_mapping CASCADE;
    RAISE NOTICE 'Dropped table: courses_mapping';
END $$;


-- ============================================================================
-- SECTION 3: Create student_status_history Table
-- ============================================================================
-- This table tracks all changes to year level and graduation status for audit trail

CREATE TABLE IF NOT EXISTS student_status_history (
    history_id SERIAL PRIMARY KEY,
    student_id TEXT NOT NULL,
    year_level VARCHAR(20) NOT NULL,
    is_graduating BOOLEAN DEFAULT FALSE,
    academic_year VARCHAR(20) NOT NULL,
    updated_at TIMESTAMP WITHOUT TIME ZONE DEFAULT NOW(),
    updated_by INTEGER,
    update_source VARCHAR(50) DEFAULT 'self_declared',
    notes TEXT,
    CONSTRAINT fk_student_status_history_student 
        FOREIGN KEY (student_id) REFERENCES students(student_id) ON DELETE CASCADE,
    CONSTRAINT fk_student_status_history_admin 
        FOREIGN KEY (updated_by) REFERENCES admins(admin_id) ON DELETE SET NULL
);

COMMENT ON TABLE student_status_history IS 
'Audit trail of student year level and graduation status changes';

COMMENT ON COLUMN student_status_history.update_source IS 
'How the status was updated: self_declared, admin_edit, enrollment, distribution';

-- Create indexes for performance
CREATE INDEX IF NOT EXISTS idx_student_status_history_student_id 
    ON student_status_history(student_id);
CREATE INDEX IF NOT EXISTS idx_student_status_history_academic_year 
    ON student_status_history(academic_year);

DO $$
BEGIN
    RAISE NOTICE 'Created table: student_status_history';
END $$;


-- ============================================================================
-- SECTION 4: Install NEW Year-Level Based Graduation Logic
-- ============================================================================

-- NEW Function: Pure calculation function (no table lookups)
-- Maps year level strings to graduation years
CREATE OR REPLACE FUNCTION calculate_graduation_year(
    p_enrollment_year VARCHAR(20),  -- e.g., "2024-2025"
    p_current_year_level VARCHAR(20) -- e.g., "1st Year", "4th Year"
)
RETURNS INTEGER
LANGUAGE plpgsql
IMMUTABLE
AS $$
DECLARE
    base_year INTEGER;
    remaining_years INTEGER;
BEGIN
    -- Extract first year from "2024-2025" format
    base_year := CAST(SPLIT_PART(p_enrollment_year, '-', 1) AS INTEGER);
    
    -- Calculate remaining years based on current year level
    remaining_years := CASE p_current_year_level
        WHEN '1st Year' THEN 4  -- 4 more years
        WHEN '2nd Year' THEN 3  -- 3 more years
        WHEN '3rd Year' THEN 2  -- 2 more years
        WHEN '4th Year' THEN 1  -- 1 more year (graduating)
        WHEN '5th Year' THEN 1  -- 1 more year (5-year programs)
        ELSE 4                  -- Default to 4 years if unknown
    END;
    
    RETURN base_year + remaining_years;
END;
$$;

COMMENT ON FUNCTION calculate_graduation_year(VARCHAR, VARCHAR) IS 
'Pure calculation: Maps year level string to expected graduation year';


-- NEW Trigger Function: Uses current_year_level (VARCHAR) instead of year_level_id (FK)
CREATE OR REPLACE FUNCTION calculate_expected_graduation_year()
RETURNS TRIGGER
LANGUAGE plpgsql
AS $$
BEGIN
    -- Only calculate if we have the required fields
    IF NEW.first_registered_academic_year IS NOT NULL 
       AND NEW.current_year_level IS NOT NULL THEN
        
        -- Use the pure calculation function
        NEW.expected_graduation_year := calculate_graduation_year(
            NEW.first_registered_academic_year,
            NEW.current_year_level
        );
    END IF;
    
    RETURN NEW;
END;
$$;

COMMENT ON FUNCTION calculate_expected_graduation_year() IS
'NEW Trigger function: Calculates graduation year from current_year_level string';


-- NEW Trigger: Watches current_year_level (VARCHAR) instead of year_level_id (INTEGER FK)
CREATE TRIGGER trigger_calculate_graduation_year
    BEFORE INSERT OR UPDATE OF first_registered_academic_year, current_year_level
    ON students
    FOR EACH ROW
    EXECUTE FUNCTION calculate_expected_graduation_year();

COMMENT ON TRIGGER trigger_calculate_graduation_year ON students IS
'NEW Trigger: Watches current_year_level changes (no more year_level_id dependency)';


-- Drop OLD version of graduation eligibility function (may have different return type)
DROP FUNCTION IF EXISTS calculate_graduation_eligibility(TEXT) CASCADE;

-- NEW Function: Simplified graduation eligibility check
CREATE OR REPLACE FUNCTION calculate_graduation_eligibility(p_student_id TEXT)
RETURNS TABLE(
    should_graduate BOOLEAN,
    reason TEXT,
    current_year_level TEXT,
    is_graduating BOOLEAN
)
LANGUAGE plpgsql
AS $$
DECLARE
    v_student RECORD;
BEGIN
    -- Get student information
    SELECT 
        s.student_id,
        s.current_year_level,
        s.is_graduating,
        s.status
    INTO v_student
    FROM students s
    WHERE s.student_id = p_student_id;
    
    -- If student not found
    IF NOT FOUND THEN
        RETURN QUERY SELECT FALSE, 'Student not found'::TEXT, NULL::TEXT, FALSE;
        RETURN;
    END IF;
    
    -- If student already archived/blacklisted
    IF v_student.status IN ('archived', 'blacklisted') THEN
        RETURN QUERY SELECT FALSE, 'Student already archived/blacklisted'::TEXT, 
                            v_student.current_year_level, v_student.is_graduating;
        RETURN;
    END IF;
    
    -- Simple logic: Graduate if student declared they are graduating
    IF v_student.is_graduating = TRUE THEN
        RETURN QUERY SELECT TRUE, 'Student declared as graduating'::TEXT,
                            v_student.current_year_level, TRUE;
    ELSE
        RETURN QUERY SELECT FALSE, 'Student not yet graduating'::TEXT,
                            v_student.current_year_level, FALSE;
    END IF;
END;
$$;

COMMENT ON FUNCTION calculate_graduation_eligibility(TEXT) IS
'Simplified graduation check based on student self-declaration';


-- Drop OLD versions of year advancement functions (may have different signatures)
DROP FUNCTION IF EXISTS preview_year_level_advancement() CASCADE;
DROP FUNCTION IF EXISTS execute_year_level_advancement(INTEGER, TEXT) CASCADE;

-- NEW Function: Preview year level advancement (simplified - no course mapping)
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
AS $$
DECLARE
    v_current_academic_year TEXT;
    v_active_students INTEGER := 0;
    v_graduating INTEGER := 0;
    v_advancing INTEGER := 0;
    v_warnings JSONB := '[]'::JSONB;
    v_advancing_list JSONB := '[]'::JSONB;
    v_graduating_list JSONB := '[]'::JSONB;
    v_student RECORD;
    v_can_advance BOOLEAN := TRUE;
    v_blocking_reasons TEXT[] := ARRAY[]::TEXT[];
BEGIN
    -- Get current academic year
    SELECT year_code INTO v_current_academic_year
    FROM academic_years
    WHERE is_current = TRUE
    LIMIT 1;
    
    IF v_current_academic_year IS NULL THEN
        v_blocking_reasons := array_append(v_blocking_reasons, 'No current academic year set');
        v_can_advance := FALSE;
    END IF;
    
    -- Count active students
    SELECT COUNT(*) INTO v_active_students
    FROM students
    WHERE status = 'active' AND is_archived = FALSE;
    
    -- Collect graduating students
    FOR v_student IN
        SELECT student_id, 
               CONCAT(first_name, ' ', last_name) as full_name,
               current_year_level,
               is_graduating
        FROM students
        WHERE status = 'active' 
          AND is_archived = FALSE
          AND is_graduating = TRUE
    LOOP
        v_graduating := v_graduating + 1;
        v_graduating_list := v_graduating_list || jsonb_build_object(
            'student_id', v_student.student_id,
            'name', v_student.full_name,
            'current_level', v_student.current_year_level,
            'action', 'Will be archived as graduated'
        );
    END LOOP;
    
    -- Collect advancing students (non-graduating active students)
    FOR v_student IN
        SELECT student_id,
               CONCAT(first_name, ' ', last_name) as full_name,
               current_year_level,
               is_graduating
        FROM students
        WHERE status = 'active'
          AND is_archived = FALSE
          AND (is_graduating = FALSE OR is_graduating IS NULL)
    LOOP
        v_advancing := v_advancing + 1;
        v_advancing_list := v_advancing_list || jsonb_build_object(
            'student_id', v_student.student_id,
            'name', v_student.full_name,
            'current_level', v_student.current_year_level,
            'action', 'Will advance to next year'
        );
    END LOOP;
    
    -- Build summary
    RETURN QUERY SELECT
        jsonb_build_object(
            'total_active', v_active_students,
            'graduating', v_graduating,
            'advancing', v_advancing,
            'current_academic_year', v_current_academic_year
        ),
        v_advancing_list,
        v_graduating_list,
        v_warnings,
        v_can_advance,
        v_blocking_reasons;
END;
$$;

COMMENT ON FUNCTION preview_year_level_advancement() IS
'Simplified year advancement preview - no course mapping required';


-- NEW Function: Execute year level advancement (simplified - uses current_year_level)
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
AS $$
DECLARE
    v_current_academic_year TEXT;
    v_next_academic_year TEXT;
    v_can_advance BOOLEAN;
    v_blocking_reasons TEXT[];
    v_students_advanced INTEGER := 0;
    v_students_graduated INTEGER := 0;
    v_student RECORD;
    v_next_year_level VARCHAR(20);
    v_execution_log JSONB := '[]'::JSONB;
    v_log_entry JSONB;
    v_audit_id INTEGER;
    v_start_year INTEGER;
    v_end_year INTEGER;
BEGIN
    -- Pre-flight checks using preview function
    SELECT can_advance, blocking_reasons
    INTO v_can_advance, v_blocking_reasons
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
    v_start_year := CAST(SPLIT_PART(v_current_academic_year, '-', 1) AS INTEGER);
    v_end_year := CAST(SPLIT_PART(v_current_academic_year, '-', 2) AS INTEGER);
    v_next_academic_year := FORMAT('%s-%s', v_start_year + 1, v_end_year + 1);
    
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
        
        -- Process graduating students
        FOR v_student IN 
            SELECT 
                s.student_id,
                s.first_name,
                s.last_name,
                s.current_year_level,
                s.is_graduating
            FROM students s
            WHERE s.status IN ('active', 'applicant')
              AND (s.is_archived IS NULL OR s.is_archived = FALSE)
              AND s.is_graduating = TRUE
            ORDER BY s.last_name, s.first_name
        LOOP
            -- Graduate and archive this student
            UPDATE students
            SET 
                status = 'archived',
                is_archived = TRUE,
                archived_at = NOW(),
                archived_by = NULL,
                archive_reason = 'graduated',
                archival_type = 'graduated',
                current_academic_year = v_next_academic_year,
                status_academic_year = v_next_academic_year,
                last_status_update = NOW()
            WHERE student_id = v_student.student_id;
            
            v_students_graduated := v_students_graduated + 1;
            
            v_log_entry := jsonb_build_object(
                'student_id', v_student.student_id,
                'name', v_student.first_name || ' ' || v_student.last_name,
                'action', 'graduated',
                'from_level', v_student.current_year_level,
                'to_level', 'Graduated (Archived)',
                'reason', 'Student declared as graduating'
            );
            
            v_execution_log := v_execution_log || v_log_entry;
        END LOOP;
        
        -- Process advancing students (non-graduating)
        FOR v_student IN 
            SELECT 
                s.student_id,
                s.first_name,
                s.last_name,
                s.current_year_level,
                s.is_graduating
            FROM students s
            WHERE s.status IN ('active', 'applicant')
              AND (s.is_archived IS NULL OR s.is_archived = FALSE)
              AND (s.is_graduating = FALSE OR s.is_graduating IS NULL)
            ORDER BY s.last_name, s.first_name
        LOOP
            -- Calculate next year level based on current
            v_next_year_level := CASE v_student.current_year_level
                WHEN '1st Year' THEN '2nd Year'
                WHEN '2nd Year' THEN '3rd Year'
                WHEN '3rd Year' THEN '4th Year'
                WHEN '4th Year' THEN '5th Year'
                WHEN '5th Year' THEN '5th Year'  -- Stay at 5th
                ELSE '2nd Year'  -- Default if unknown
            END;
            
            -- Advance to next year level
            UPDATE students
            SET 
                current_year_level = v_next_year_level,
                current_academic_year = v_next_academic_year,
                status_academic_year = v_next_academic_year,
                last_status_update = NOW(),
                expected_graduation_year = calculate_graduation_year(
                    first_registered_academic_year,
                    v_next_year_level
                )
            WHERE student_id = v_student.student_id;
            
            v_students_advanced := v_students_advanced + 1;
            
            v_log_entry := jsonb_build_object(
                'student_id', v_student.student_id,
                'name', v_student.first_name || ' ' || v_student.last_name,
                'action', 'advanced',
                'from_level', v_student.current_year_level,
                'to_level', v_next_year_level,
                'reason', 'Annual year level advancement'
            );
            
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
$$;

COMMENT ON FUNCTION execute_year_level_advancement(INTEGER, TEXT) IS
'Executes year level advancement for all active students. Uses current_year_level (no FK dependency). Transaction-safe with full audit logging.';


-- ============================================================================
-- SECTION 5: Update Existing Students with Graduation Status
-- ============================================================================

DO $$
DECLARE
    v_updated_count INTEGER;
BEGIN
    -- Set is_graduating = TRUE for students in 4th or 5th year
    UPDATE students
    SET is_graduating = TRUE
    WHERE current_year_level IN ('4th Year', '5th Year')
      AND is_graduating = FALSE
      AND status NOT IN ('archived', 'blacklisted');
    
    GET DIAGNOSTICS v_updated_count = ROW_COUNT;
    RAISE NOTICE 'Set is_graduating=TRUE for % students (4th/5th year)', v_updated_count;
    
    -- Recalculate expected_graduation_year for all active students
    UPDATE students
    SET expected_graduation_year = calculate_graduation_year(
        first_registered_academic_year,
        current_year_level
    )
    WHERE first_registered_academic_year IS NOT NULL
      AND current_year_level IS NOT NULL
      AND status NOT IN ('archived', 'blacklisted');
    
    GET DIAGNOSTICS v_updated_count = ROW_COUNT;
    RAISE NOTICE 'Recalculated graduation year for % students', v_updated_count;
END $$;


-- ============================================================================
-- SECTION 6: Add Status Change Logging Trigger
-- ============================================================================

-- This trigger logs changes to current_year_level and is_graduating
CREATE OR REPLACE FUNCTION log_student_status_change()
RETURNS TRIGGER
LANGUAGE plpgsql
AS $$
BEGIN
    -- Check if graduation-related fields changed
    IF (OLD.current_year_level IS DISTINCT FROM NEW.current_year_level) OR
       (OLD.status IS DISTINCT FROM NEW.status) OR
       (OLD.is_graduating IS DISTINCT FROM NEW.is_graduating) THEN
        
        -- Insert into student_status_history table
        INSERT INTO student_status_history (
            student_id,
            year_level,
            is_graduating,
            academic_year,
            updated_at,
            updated_by,
            update_source,
            notes
        ) VALUES (
            NEW.student_id,
            NEW.current_year_level,
            NEW.is_graduating,
            COALESCE(NEW.status_academic_year, NEW.current_academic_year),
            NOW(),
            NEW.archived_by,  -- Reuse archived_by as updated_by
            'system',
            format('Status changed: year_level=%s->%s, is_graduating=%s->%s, status=%s->%s',
                   OLD.current_year_level, NEW.current_year_level,
                   OLD.is_graduating, NEW.is_graduating,
                   OLD.status, NEW.status)
        );
        
        -- Also log to audit_logs (using existing log_document_audit function)
        PERFORM log_document_audit(
            p_user_id := COALESCE(NEW.archived_by, 0),
            p_user_type := 'system',
            p_username := 'system',
            p_event_type := 'student_status_change',
            p_event_category := 'student_management',
            p_action_description := format(
                'Student %s status changed: year_level=%s->%s, is_graduating=%s->%s, status=%s->%s',
                NEW.student_id,
                OLD.current_year_level, NEW.current_year_level,
                OLD.is_graduating, NEW.is_graduating,
                OLD.status, NEW.status
            ),
            p_affected_table := 'students',
            p_affected_record_id := NULL,
            p_metadata := jsonb_build_object(
                'student_id', NEW.student_id,
                'old_year_level', OLD.current_year_level,
                'new_year_level', NEW.current_year_level,
                'old_is_graduating', OLD.is_graduating,
                'new_is_graduating', NEW.is_graduating,
                'old_status', OLD.status,
                'new_status', NEW.status
            ),
            p_status := 'success'
        );
    END IF;
    
    RETURN NEW;
END;
$$;

-- Create trigger if it doesn't exist
DROP TRIGGER IF EXISTS trigger_log_student_status_change ON students;
CREATE TRIGGER trigger_log_student_status_change
    AFTER UPDATE ON students
    FOR EACH ROW
    EXECUTE FUNCTION log_student_status_change();

COMMENT ON TRIGGER trigger_log_student_status_change ON students IS
'Logs changes to current_year_level, is_graduating, and status for audit trail';


-- ============================================================================
-- SECTION 7: Verification Queries
-- ============================================================================

DO $$
DECLARE
    v_count INTEGER;
BEGIN
    RAISE NOTICE '=== MIGRATION VERIFICATION ===';
    
    -- Check new columns exist
    SELECT COUNT(*) INTO v_count
    FROM information_schema.columns
    WHERE table_name = 'students'
      AND column_name IN ('current_year_level', 'is_graduating', 'status_academic_year');
    RAISE NOTICE 'New columns created: % of 3', v_count;
    
    -- Check courses_mapping is gone
    SELECT COUNT(*) INTO v_count
    FROM information_schema.tables
    WHERE table_name = 'courses_mapping';
    IF v_count = 0 THEN
        RAISE NOTICE 'OLD courses_mapping table removed: YES';
    ELSE
        RAISE WARNING 'OLD courses_mapping table still exists!';
    END IF;
    
    -- Check new trigger exists
    SELECT COUNT(*) INTO v_count
    FROM information_schema.triggers
    WHERE trigger_name = 'trigger_calculate_graduation_year'
      AND event_object_table = 'students';
    RAISE NOTICE 'NEW trigger installed: % (should be 1)', v_count;
    
    -- Check new functions exist
    SELECT COUNT(*) INTO v_count
    FROM pg_proc p
    JOIN pg_namespace n ON p.pronamespace = n.oid
    WHERE n.nspname = 'public'
      AND p.proname IN ('calculate_graduation_year', 'calculate_graduation_eligibility');
    RAISE NOTICE 'NEW functions installed: % of 2', v_count;
    
    -- Check student_status_history table exists
    SELECT COUNT(*) INTO v_count
    FROM information_schema.tables
    WHERE table_name = 'student_status_history';
    IF v_count = 1 THEN
        RAISE NOTICE 'NEW student_status_history table created: YES';
    ELSE
        RAISE WARNING 'NEW student_status_history table NOT created!';
    END IF;
    
    -- Count students with populated data
    SELECT COUNT(*) INTO v_count
    FROM students
    WHERE current_year_level IS NOT NULL;
    RAISE NOTICE 'Students with current_year_level: %', v_count;
    
    SELECT COUNT(*) INTO v_count
    FROM students
    WHERE is_graduating = TRUE;
    RAISE NOTICE 'Students marked as graduating: %', v_count;
    
    RAISE NOTICE '=== MIGRATION COMPLETE ===';
END $$;

COMMIT;

-- ============================================================================
-- POST-MIGRATION TESTING QUERIES
-- ============================================================================
-- Run these queries AFTER migration to verify everything works:

-- 1. Check all new columns exist
-- SELECT column_name, data_type, is_nullable 
-- FROM information_schema.columns 
-- WHERE table_name = 'students' 
--   AND column_name IN ('current_year_level', 'is_graduating', 'status_academic_year', 'last_status_update')
-- ORDER BY column_name;

-- 2. Check trigger definition
-- SELECT trigger_name, event_manipulation, event_object_table, action_timing
-- FROM information_schema.triggers
-- WHERE trigger_name = 'trigger_calculate_graduation_year';

-- 3. Test graduation calculation
-- SELECT student_id, current_year_level, is_graduating, 
--        first_registered_academic_year, expected_graduation_year
-- FROM students 
-- WHERE current_year_level IS NOT NULL 
-- LIMIT 10;

-- 4. Test graduation eligibility function
-- SELECT * FROM calculate_graduation_eligibility('GENERALTRIAS-2025-1-ABC123');

-- 5. Verify courses_mapping is gone
-- SELECT tablename FROM pg_tables WHERE tablename = 'courses_mapping';
-- -- Should return 0 rows

-- 6. Check student_status_history table
-- SELECT COUNT(*) FROM student_status_history;
-- -- Should return 0 initially (will populate as students update their status)

-- 7. Verify student_status_history structure
-- SELECT column_name, data_type, is_nullable 
-- FROM information_schema.columns 
-- WHERE table_name = 'student_status_history' 
-- ORDER BY ordinal_position;

-- ============================================================================
-- ROLLBACK INSTRUCTIONS
-- ============================================================================
-- If migration fails or causes issues:
--
-- 1. RESTORE from backup (CRITICAL - cannot undo table drops)
-- 2. The migration is wrapped in BEGIN/COMMIT for transaction safety
-- 3. If you see any errors during migration, do NOT deploy PHP code
-- 4. Contact development team before retrying
--
-- ============================================================================
-- DEPLOYMENT CHECKLIST
-- ============================================================================
-- [ ] Database backup created and verified
-- [ ] Migration script reviewed for your specific Railway environment
-- [ ] PostgreSQL version matches (17.5 recommended)
-- [ ] All verification queries passed
-- [ ] No errors in PostgreSQL logs
-- [ ] Test with sample student data before production
-- [ ] PHP code deployment scheduled AFTER migration success
-- ============================================================================
