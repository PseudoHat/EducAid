<?php
/**
 * Check and Create student_data_export_requests table if it doesn't exist
 */

require_once __DIR__ . '/config/database.php';

echo "Checking if student_data_export_requests table exists...\n";

// Check if table exists
$check = pg_query($connection, "SELECT to_regclass('public.student_data_export_requests') IS NOT NULL as exists");
$result = pg_fetch_assoc($check);

if ($result['exists'] === 't') {
    echo "✅ Table already exists!\n";
    exit(0);
}

echo "❌ Table does not exist. Creating...\n";

// Create table
$sql = "
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
";

$result = pg_query($connection, $sql);

if ($result) {
    echo "✅ Table created successfully!\n";
} else {
    echo "❌ Failed to create table: " . pg_last_error($connection) . "\n";
    exit(1);
}

pg_close($connection);
