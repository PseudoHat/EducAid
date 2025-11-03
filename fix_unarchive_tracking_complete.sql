-- ================================================================
-- Complete Unarchive Tracking System Update
-- ================================================================
-- This script updates the unarchive_student() function to:
-- 1. Track WHO unarchived the student (unarchived_by)
-- 2. Track WHEN the student was unarchived (unarchived_at)
-- 3. Track WHY the student was unarchived (unarchive_reason)
-- 4. Clear all archival metadata (archived_at, archived_by, archive_reason, archival_type)
-- 5. Restore student to applicant status
--
-- Date: November 2, 2025
-- Author: EducAid Development Team
-- ================================================================

-- Drop existing function if it exists (to ensure clean update)
DROP FUNCTION IF EXISTS public.unarchive_student(text, integer, text);
DROP FUNCTION IF EXISTS public.unarchive_student(text, integer);

-- Create the complete unarchive_student function
CREATE OR REPLACE FUNCTION public.unarchive_student(
    p_student_id text, 
    p_admin_id integer,
    p_unarchive_reason text DEFAULT NULL
) 
RETURNS boolean
LANGUAGE plpgsql
AS $$
BEGIN
    -- Update student record
    UPDATE students
    SET 
        -- Clear archival status and metadata
        is_archived = FALSE,
        archived_at = NULL,
        archived_by = NULL,
        archive_reason = NULL,
        archival_type = NULL,
        
        -- Track unarchival action (audit trail)
        unarchived_by = p_admin_id,
        unarchived_at = NOW(),
        unarchive_reason = p_unarchive_reason,
        
        -- Restore to applicant status (requires re-verification)
        status = 'applicant'
    WHERE student_id = p_student_id
    AND is_archived = TRUE; -- Only unarchive if currently archived
    
    -- Return true if a row was updated
    RETURN FOUND;
END;
$$;

-- Add helpful comment to the function
COMMENT ON FUNCTION public.unarchive_student(p_student_id text, p_admin_id integer, p_unarchive_reason text) IS 
'Unarchives a student and restores them to active applicant status. Clears all archival metadata (archived_at, archived_by, archive_reason, archival_type) and creates a complete audit trail of the restoration action (unarchived_by, unarchived_at, unarchive_reason). This ensures full accountability for both archiving and unarchiving operations.';

-- Verification query (optional - can be commented out)
SELECT 
    'Function created successfully!' as status,
    p.proname as function_name,
    pg_get_function_arguments(p.oid) as parameters
FROM pg_proc p
JOIN pg_namespace n ON p.pronamespace = n.oid
WHERE n.nspname = 'public'
AND p.proname = 'unarchive_student';
