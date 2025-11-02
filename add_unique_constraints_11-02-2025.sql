-- =====================================================
-- ADD UNIQUE CONSTRAINTS FOR DUPLICATE PREVENTION
-- Date: November 2, 2025
-- Purpose: Database-level duplicate prevention (absolute protection)
-- =====================================================

-- IMPORTANT: This migration adds partial UNIQUE indexes that only apply to active students
-- (WHERE is_archived = FALSE), allowing the same email/mobile to be reused after archival.

BEGIN;

-- =====================================================
-- STEP 1: VERIFY NO EXISTING DUPLICATES (PRE-CHECK)
-- =====================================================

-- Check for duplicate emails among active students
DO $$
DECLARE
    duplicate_count INTEGER;
BEGIN
    SELECT COUNT(*) INTO duplicate_count
    FROM (
        SELECT email, COUNT(*) as cnt
        FROM students
        WHERE is_archived = FALSE AND email IS NOT NULL
        GROUP BY email
        HAVING COUNT(*) > 1
    ) duplicates;
    
    IF duplicate_count > 0 THEN
        RAISE EXCEPTION 'MIGRATION BLOCKED: Found % duplicate email(s) among active students. Please fix duplicates before running this migration.', duplicate_count;
    END IF;
    
    RAISE NOTICE 'Pre-check PASSED: No duplicate emails found among active students';
END $$;

-- Check for duplicate mobiles among active students
DO $$
DECLARE
    duplicate_count INTEGER;
BEGIN
    SELECT COUNT(*) INTO duplicate_count
    FROM (
        SELECT mobile, COUNT(*) as cnt
        FROM students
        WHERE is_archived = FALSE AND mobile IS NOT NULL
        GROUP BY mobile
        HAVING COUNT(*) > 1
    ) duplicates;
    
    IF duplicate_count > 0 THEN
        RAISE EXCEPTION 'MIGRATION BLOCKED: Found % duplicate mobile(s) among active students. Please fix duplicates before running this migration.', duplicate_count;
    END IF;
    
    RAISE NOTICE 'Pre-check PASSED: No duplicate mobiles found among active students';
END $$;

-- Check for duplicate school_student_id + university_id combos among active students
DO $$
DECLARE
    duplicate_count INTEGER;
BEGIN
    SELECT COUNT(*) INTO duplicate_count
    FROM (
        SELECT school_student_id, university_id, COUNT(*) as cnt
        FROM students
        WHERE is_archived = FALSE 
          AND school_student_id IS NOT NULL 
          AND university_id IS NOT NULL
        GROUP BY school_student_id, university_id
        HAVING COUNT(*) > 1
    ) duplicates;
    
    IF duplicate_count > 0 THEN
        RAISE EXCEPTION 'MIGRATION BLOCKED: Found % duplicate school_student_id+university_id combo(s) among active students. Please fix duplicates before running this migration.', duplicate_count;
    END IF;
    
    RAISE NOTICE 'Pre-check PASSED: No duplicate school_student_id+university_id combos found among active students';
END $$;

-- =====================================================
-- STEP 2: DROP EXISTING CONSTRAINTS (IF ANY)
-- =====================================================

-- Drop existing unique indexes if they exist (idempotent - safe to run multiple times)
DROP INDEX IF EXISTS idx_unique_email_active;
DROP INDEX IF EXISTS idx_unique_mobile_active;
DROP INDEX IF EXISTS idx_unique_school_id_university_active;

DO $$ BEGIN RAISE NOTICE 'Dropped existing unique indexes (if any)'; END $$;

-- =====================================================
-- STEP 3: CREATE PARTIAL UNIQUE INDEXES
-- =====================================================

-- 1. Email UNIQUE constraint (only for active students)
-- This allows the same email to be reused after a student is archived
CREATE UNIQUE INDEX idx_unique_email_active 
ON students (LOWER(email))
WHERE is_archived = FALSE AND email IS NOT NULL;

DO $$ BEGIN RAISE NOTICE 'Created unique index on email (active students only)'; END $$;

-- 2. Mobile UNIQUE constraint (only for active students)
-- This allows the same mobile number to be reused after a student is archived
CREATE UNIQUE INDEX idx_unique_mobile_active 
ON students (mobile)
WHERE is_archived = FALSE AND mobile IS NOT NULL;

DO $$ BEGIN RAISE NOTICE 'Created unique index on mobile (active students only)'; END $$;

-- 3. School Student ID + University UNIQUE constraint (only for active students)
-- This prevents the same school ID from being registered twice at the same university
CREATE UNIQUE INDEX idx_unique_school_id_university_active 
ON students (university_id, school_student_id)
WHERE is_archived = FALSE AND school_student_id IS NOT NULL AND university_id IS NOT NULL;

DO $$ BEGIN RAISE NOTICE 'Created unique index on school_student_id+university_id (active students only)'; END $$;

-- =====================================================
-- STEP 4: CREATE PERFORMANCE INDEXES (OPTIONAL)
-- =====================================================

-- These indexes improve query performance for duplicate checks
-- They're separate from the unique constraints above

-- Index for email lookups (if not already exists)
CREATE INDEX IF NOT EXISTS idx_students_email 
ON students (email) 
WHERE email IS NOT NULL;

-- Index for mobile lookups (if not already exists)
CREATE INDEX IF NOT EXISTS idx_students_mobile 
ON students (mobile) 
WHERE mobile IS NOT NULL;

-- Index for school_student_id lookups (if not already exists)
CREATE INDEX IF NOT EXISTS idx_students_school_id 
ON students (school_student_id) 
WHERE school_student_id IS NOT NULL;

-- Index for is_archived flag (improves WHERE is_archived = FALSE queries)
CREATE INDEX IF NOT EXISTS idx_students_is_archived 
ON students (is_archived);

DO $$ BEGIN RAISE NOTICE 'Created performance indexes for duplicate check queries'; END $$;

-- =====================================================
-- STEP 5: VERIFY CONSTRAINTS WERE CREATED
-- =====================================================

DO $$
DECLARE
    email_index_exists BOOLEAN;
    mobile_index_exists BOOLEAN;
    school_id_index_exists BOOLEAN;
BEGIN
    -- Check if email unique index exists
    SELECT EXISTS (
        SELECT 1 FROM pg_indexes 
        WHERE tablename = 'students' 
        AND indexname = 'idx_unique_email_active'
    ) INTO email_index_exists;
    
    -- Check if mobile unique index exists
    SELECT EXISTS (
        SELECT 1 FROM pg_indexes 
        WHERE tablename = 'students' 
        AND indexname = 'idx_unique_mobile_active'
    ) INTO mobile_index_exists;
    
    -- Check if school_student_id+university_id unique index exists
    SELECT EXISTS (
        SELECT 1 FROM pg_indexes 
        WHERE tablename = 'students' 
        AND indexname = 'idx_unique_school_id_university_active'
    ) INTO school_id_index_exists;
    
    IF NOT email_index_exists THEN
        RAISE EXCEPTION 'VERIFICATION FAILED: Email unique index was not created!';
    END IF;
    
    IF NOT mobile_index_exists THEN
        RAISE EXCEPTION 'VERIFICATION FAILED: Mobile unique index was not created!';
    END IF;
    
    IF NOT school_id_index_exists THEN
        RAISE EXCEPTION 'VERIFICATION FAILED: School ID+University unique index was not created!';
    END IF;
    
    RAISE NOTICE 'VERIFICATION PASSED: All unique indexes created successfully!';
END $$;

-- =====================================================
-- STEP 6: TEST CONSTRAINTS (OPTIONAL - COMMENT OUT FOR PRODUCTION)
-- =====================================================

-- Uncomment this section to test the constraints work correctly

/*
DO $$
DECLARE
    test_email TEXT := 'test.duplicate.constraint@example.com';
    test_mobile TEXT := '09999999999';
    test_school_id TEXT := 'TEST-DUPLICATE-001';
    test_student_id TEXT;
BEGIN
    -- Test 1: Insert first student (should succeed)
    INSERT INTO students (
        student_id, first_name, last_name, email, mobile, 
        school_student_id, university_id, password_hash, 
        year_level_id, barangay_id, birth_date, sex, municipality_id, is_archived
    ) VALUES (
        'TEST-CONSTRAINT-001', 'Test', 'Duplicate1', test_email, test_mobile,
        test_school_id, 1, '$2y$10$test', 1, 1, '2000-01-01', 'Male', 1, FALSE
    ) RETURNING student_id INTO test_student_id;
    
    RAISE NOTICE 'Test 1 PASSED: First student inserted successfully';
    
    -- Test 2: Try to insert duplicate email (should fail)
    BEGIN
        INSERT INTO students (
            student_id, first_name, last_name, email, mobile, 
            school_student_id, university_id, password_hash, 
            year_level_id, barangay_id, birth_date, sex, municipality_id, is_archived
        ) VALUES (
            'TEST-CONSTRAINT-002', 'Test', 'Duplicate2', test_email, '09888888888',
            'TEST-DIFFERENT-001', 1, '$2y$10$test', 1, 1, '2000-01-01', 'Male', 1, FALSE
        );
        
        RAISE EXCEPTION 'Test 2 FAILED: Duplicate email was allowed (constraint not working!)';
    EXCEPTION
        WHEN unique_violation THEN
            RAISE NOTICE 'Test 2 PASSED: Duplicate email correctly blocked by constraint';
    END;
    
    -- Test 3: Try to insert duplicate mobile (should fail)
    BEGIN
        INSERT INTO students (
            student_id, first_name, last_name, email, mobile, 
            school_student_id, university_id, password_hash, 
            year_level_id, barangay_id, birth_date, sex, municipality_id, is_archived
        ) VALUES (
            'TEST-CONSTRAINT-003', 'Test', 'Duplicate3', 'different@example.com', test_mobile,
            'TEST-DIFFERENT-002', 1, '$2y$10$test', 1, 1, '2000-01-01', 'Male', 1, FALSE
        );
        
        RAISE EXCEPTION 'Test 3 FAILED: Duplicate mobile was allowed (constraint not working!)';
    EXCEPTION
        WHEN unique_violation THEN
            RAISE NOTICE 'Test 3 PASSED: Duplicate mobile correctly blocked by constraint';
    END;
    
    -- Test 4: Try to insert duplicate school_student_id+university_id (should fail)
    BEGIN
        INSERT INTO students (
            student_id, first_name, last_name, email, mobile, 
            school_student_id, university_id, password_hash, 
            year_level_id, barangay_id, birth_date, sex, municipality_id, is_archived
        ) VALUES (
            'TEST-CONSTRAINT-004', 'Test', 'Duplicate4', 'another@example.com', '09777777777',
            test_school_id, 1, '$2y$10$test', 1, 1, '2000-01-01', 'Male', 1, FALSE
        );
        
        RAISE EXCEPTION 'Test 4 FAILED: Duplicate school_student_id+university_id was allowed (constraint not working!)';
    EXCEPTION
        WHEN unique_violation THEN
            RAISE NOTICE 'Test 4 PASSED: Duplicate school_student_id+university_id correctly blocked by constraint';
    END;
    
    -- Test 5: Archive student and reuse email/mobile (should succeed)
    UPDATE students SET is_archived = TRUE WHERE student_id = test_student_id;
    
    INSERT INTO students (
        student_id, first_name, last_name, email, mobile, 
        school_student_id, university_id, password_hash, 
        year_level_id, barangay_id, birth_date, sex, municipality_id, is_archived
    ) VALUES (
        'TEST-CONSTRAINT-005', 'Test', 'Duplicate5', test_email, test_mobile,
        test_school_id, 1, '$2y$10$test', 1, 1, '2000-01-01', 'Male', 1, FALSE
    );
    
    RAISE NOTICE 'Test 5 PASSED: Email/mobile can be reused after archiving previous student';
    
    -- Cleanup test data
    DELETE FROM students WHERE student_id LIKE 'TEST-CONSTRAINT-%';
    
    RAISE NOTICE 'All tests PASSED! Constraints are working correctly. Test data cleaned up.';
END $$;
*/

COMMIT;

-- =====================================================
-- POST-MIGRATION VERIFICATION QUERIES
-- =====================================================

-- Run these queries after migration to verify everything is working

-- 1. List all unique indexes on students table
SELECT 
    indexname,
    indexdef
FROM pg_indexes
WHERE tablename = 'students'
AND (
    indexname LIKE '%unique%' OR 
    indexdef LIKE '%UNIQUE%'
)
ORDER BY indexname;

-- 2. Check for any duplicates that might have slipped through
SELECT 'Email duplicates' as check_type, COUNT(*) as duplicate_count
FROM (
    SELECT email, COUNT(*) as cnt
    FROM students
    WHERE is_archived = FALSE AND email IS NOT NULL
    GROUP BY email
    HAVING COUNT(*) > 1
) dup
UNION ALL
SELECT 'Mobile duplicates', COUNT(*)
FROM (
    SELECT mobile, COUNT(*) as cnt
    FROM students
    WHERE is_archived = FALSE AND mobile IS NOT NULL
    GROUP BY mobile
    HAVING COUNT(*) > 1
) dup
UNION ALL
SELECT 'School ID duplicates', COUNT(*)
FROM (
    SELECT school_student_id, university_id, COUNT(*) as cnt
    FROM students
    WHERE is_archived = FALSE 
      AND school_student_id IS NOT NULL 
      AND university_id IS NOT NULL
    GROUP BY school_student_id, university_id
    HAVING COUNT(*) > 1
) dup;
-- Expected: All counts should be 0

-- =====================================================
-- ROLLBACK SCRIPT (USE ONLY IF NEEDED)
-- =====================================================

/*
-- To remove the unique constraints, run:

BEGIN;

DROP INDEX IF EXISTS idx_unique_email_active;
DROP INDEX IF EXISTS idx_unique_mobile_active;
DROP INDEX IF EXISTS idx_unique_school_id_university_active;

-- Optionally remove performance indexes too
DROP INDEX IF EXISTS idx_students_email;
DROP INDEX IF EXISTS idx_students_mobile;
DROP INDEX IF EXISTS idx_students_school_id;
DROP INDEX IF EXISTS idx_students_is_archived;

COMMIT;

*/

-- =====================================================
-- MIGRATION COMPLETE
-- =====================================================

-- Expected Output:
-- NOTICE:  Pre-check PASSED: No duplicate emails found among active students
-- NOTICE:  Pre-check PASSED: No duplicate mobiles found among active students
-- NOTICE:  Pre-check PASSED: No duplicate school_student_id+university_id combos found among active students
-- NOTICE:  Dropped existing unique indexes (if any)
-- NOTICE:  Created unique index on email (active students only)
-- NOTICE:  Created unique index on mobile (active students only)
-- NOTICE:  Created unique index on school_student_id+university_id (active students only)
-- NOTICE:  Created performance indexes for duplicate check queries
-- NOTICE:  VERIFICATION PASSED: All unique indexes created successfully!
-- COMMIT
