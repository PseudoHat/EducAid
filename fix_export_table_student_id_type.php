<?php
/**
 * Fix student_data_export_requests table - change student_id from INTEGER to VARCHAR
 */

require_once __DIR__ . '/config/database.php';

echo "Checking student_data_export_requests table structure...\n";

// Check current column type
$check = pg_query($connection, "
    SELECT column_name, data_type 
    FROM information_schema.columns 
    WHERE table_name = 'student_data_export_requests' 
    AND column_name = 'student_id'
");

if ($check && pg_num_rows($check) > 0) {
    $row = pg_fetch_assoc($check);
    echo "Current student_id type: " . $row['data_type'] . "\n";
    
    if ($row['data_type'] === 'integer') {
        echo "❌ student_id is INTEGER - needs to be changed to VARCHAR!\n";
        echo "Fixing...\n";
        
        // Drop and recreate table (since it's probably empty or test data only)
        $fix = pg_query($connection, "
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
            
            COMMENT ON TABLE public.student_data_export_requests IS 'Tracks student self-service data export requests';
            
            CREATE INDEX idx_export_requests_student ON public.student_data_export_requests(student_id);
            CREATE INDEX idx_export_requests_status ON public.student_data_export_requests(status);
            CREATE INDEX idx_export_requests_requested_at ON public.student_data_export_requests(requested_at DESC);
        ");
        
        if ($fix) {
            echo "✅ Table recreated successfully with VARCHAR student_id!\n";
        } else {
            echo "❌ Failed to fix table: " . pg_last_error($connection) . "\n";
            exit(1);
        }
    } else {
        echo "✅ student_id type is correct (VARCHAR)!\n";
    }
} else {
    echo "❌ Table or column not found!\n";
    exit(1);
}

pg_close($connection);
echo "\n✅ All done!\n";
 