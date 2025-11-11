-- Verification Script: Check Export Feature Setup
-- Run this on both localhost and Railway to verify everything is set up correctly
-- Date: 2025-11-12

-- =======================================
-- Export Feature Setup Verification
-- =======================================

-- 1. Check if table exists
SELECT '1. Checking if student_data_export_requests table exists...' AS step;

SELECT 
    CASE 
        WHEN EXISTS (
            SELECT 1 FROM pg_tables 
            WHERE schemaname = 'public' 
            AND tablename = 'student_data_export_requests'
        ) THEN 'Table exists ✅'
        ELSE 'Table NOT found ❌ - Run creation migration!'
    END AS table_status;

-- 2. Check student_id column type
SELECT '2. Checking student_id column type...' AS step;

SELECT 
    column_name,
    data_type,
    character_maximum_length,
    CASE 
        WHEN data_type = 'character varying' THEN 'Correct type (VARCHAR) ✅'
        WHEN data_type = 'integer' THEN 'WRONG TYPE (INTEGER) ❌ - Run fix migration!'
        ELSE 'Unexpected type ⚠️'
    END AS type_check
FROM information_schema.columns 
WHERE table_name = 'student_data_export_requests'
AND column_name = 'student_id';

-- 3. Check all columns exist
SELECT '3. Verifying all required columns...' AS step;

SELECT 
    column_name,
    data_type,
    is_nullable
FROM information_schema.columns 
WHERE table_name = 'student_data_export_requests'
ORDER BY ordinal_position;

-- 4. Check indexes
SELECT '4. Checking indexes...' AS step;

SELECT 
    indexname,
    indexdef
FROM pg_indexes 
WHERE tablename = 'student_data_export_requests'
ORDER BY indexname;

-- 5. Count existing records
SELECT '5. Checking existing export requests...' AS step;

SELECT 
    COUNT(*) AS total_requests,
    COUNT(CASE WHEN status = 'pending' THEN 1 END) AS pending,
    COUNT(CASE WHEN status = 'processing' THEN 1 END) AS processing,
    COUNT(CASE WHEN status = 'ready' THEN 1 END) AS ready,
    COUNT(CASE WHEN status = 'failed' THEN 1 END) AS failed,
    COUNT(CASE WHEN status = 'expired' THEN 1 END) AS expired
FROM student_data_export_requests;

-- 6. Check table permissions
SELECT '6. Checking table permissions...' AS step;

SELECT 
    grantee,
    privilege_type
FROM information_schema.table_privileges 
WHERE table_name = 'student_data_export_requests'
ORDER BY grantee, privilege_type;

-- =======================================
-- Verification Complete!
-- =======================================
-- Expected Results:
--   ✅ Table exists
--   ✅ student_id is VARCHAR(255)
--   ✅ 12 columns present
--   ✅ 3 indexes created
--
-- If you see any ❌ symbols above, run the appropriate migration from:
--   database/migrations/2025-11-12_create_student_data_export_table.sql
--   database/migrations/2025-11-12_fix_student_id_type.sql
