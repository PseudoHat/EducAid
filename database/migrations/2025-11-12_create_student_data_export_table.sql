-- Active: 1762796816401@@shortline.proxy.rlwy.net@26026@railway@public
-- Migration: Create student_data_export_requests table
-- Date: 2025-11-12
-- Description: Creates table to track student self-service data export requests
-- Run on: Both localhost and Railway production

-- Check if table exists before creating
DO $$ 
BEGIN
    IF NOT EXISTS (
        SELECT FROM pg_tables 
        WHERE schemaname = 'public' 
        AND tablename = 'student_data_export_requests'
    ) THEN
        -- Create the table
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

        -- Add table comment
        COMMENT ON TABLE public.student_data_export_requests IS 'Tracks student self-service data export requests for GDPR compliance';

        -- Create indexes for performance
        CREATE INDEX idx_export_requests_student ON public.student_data_export_requests(student_id);
        CREATE INDEX idx_export_requests_status ON public.student_data_export_requests(status);
        CREATE INDEX idx_export_requests_requested_at ON public.student_data_export_requests(requested_at DESC);

        RAISE NOTICE '✅ Table student_data_export_requests created successfully';
    ELSE
        RAISE NOTICE '⚠️ Table student_data_export_requests already exists';
    END IF;
END $$;

-- Verify the table was created
SELECT 
    table_name, 
    column_name, 
    data_type,
    character_maximum_length
FROM information_schema.columns 
WHERE table_name = 'student_data_export_requests'
ORDER BY ordinal_position;
