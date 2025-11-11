-- Migration: Fix student_id type in student_data_export_requests
-- Date: 2025-11-12
-- Description: Changes student_id from INTEGER to VARCHAR(255) if needed
-- Run on: Both localhost and Railway production (if table already exists with wrong type)

-- Check current data type
DO $$ 
DECLARE
    current_type TEXT;
BEGIN
    -- Get current data type
    SELECT data_type INTO current_type
    FROM information_schema.columns 
    WHERE table_name = 'student_data_export_requests' 
    AND column_name = 'student_id';

    IF current_type IS NULL THEN
        RAISE NOTICE '❌ Table student_data_export_requests does not exist. Run creation migration first.';
    ELSIF current_type = 'integer' THEN
        RAISE NOTICE '⚠️ student_id is INTEGER, converting to VARCHAR(255)...';
        
        -- Check if table has data
        IF EXISTS (SELECT 1 FROM student_data_export_requests LIMIT 1) THEN
            RAISE NOTICE '⚠️ Table contains data. Backing up before modification...';
            
            -- Create backup table
            CREATE TABLE student_data_export_requests_backup AS 
            SELECT * FROM student_data_export_requests;
            
            RAISE NOTICE '✅ Backup created: student_data_export_requests_backup';
        END IF;
        
        -- Drop and recreate with correct type
        DROP TABLE IF EXISTS public.student_data_export_requests CASCADE;
        
        CREATE TABLE public.student_data_export_requests (
            request_id SERIAL PRIMARY KEY,
            student_id VARCHAR(255) NOT NULL,
            status VARCHAR(50) NOT NULL DEFAULT 'pending',
            requested_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            processed_at TIMESTAMP,
            expires_at TIMESTAMP,
            download_token VARCHAR(255),
            file_path TEXT,
            file_size_bytes BIGINT,
            requested_by_ip VARCHAR(100),
            user_agent TEXT,
            error_message TEXT
        );
        
        COMMENT ON TABLE public.student_data_export_requests IS 'Tracks student self-service data export requests for GDPR compliance';
        
        CREATE INDEX idx_export_requests_student ON public.student_data_export_requests(student_id);
        CREATE INDEX idx_export_requests_status ON public.student_data_export_requests(status);
        CREATE INDEX idx_export_requests_requested_at ON public.student_data_export_requests(requested_at DESC);
        
        RAISE NOTICE '✅ Table recreated with VARCHAR(255) student_id';
        
    ELSIF current_type = 'character varying' THEN
        RAISE NOTICE '✅ student_id type is already correct (VARCHAR)';
    ELSE
        RAISE NOTICE '⚠️ Unexpected type: %', current_type;
    END IF;
END $$;

-- Verify the fix
SELECT 
    column_name, 
    data_type,
    character_maximum_length,
    is_nullable
FROM information_schema.columns 
WHERE table_name = 'student_data_export_requests'
AND column_name = 'student_id';
