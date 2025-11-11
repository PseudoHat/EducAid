-- Active: 1762333255156@@shortline.proxy.rlwy.net@26026@railway
-- =====================================================
-- Household Duplicate Prevention System
-- Migration Date: November 11, 2025
-- Purpose: Add mother's maiden name + barangay validation
-- =====================================================

-- Enable pg_trgm extension for fuzzy matching (if not already enabled)
CREATE EXTENSION IF NOT EXISTS pg_trgm;

-- =====================================================
-- 1. Add mothers_maiden_name column to students table
-- =====================================================

ALTER TABLE students 
ADD COLUMN IF NOT EXISTS mothers_maiden_name VARCHAR(100) NULL;

COMMENT ON COLUMN students.mothers_maiden_name IS 
'Mother''s maiden name (surname before marriage) - used for household duplicate prevention. Combined with student surname and barangay to identify unique households.';

-- =====================================================
-- 2. Add admin review flag for edge cases
-- =====================================================

ALTER TABLE students 
ADD COLUMN IF NOT EXISTS admin_review_required BOOLEAN DEFAULT FALSE;

COMMENT ON COLUMN students.admin_review_required IS 
'Flag for students requiring admin verification (e.g., mother''s maiden name matches student surname)';

-- =====================================================
-- 3. Create composite index for fast household lookups
-- =====================================================

CREATE INDEX IF NOT EXISTS idx_students_household_lookup 
ON students(last_name, mothers_maiden_name, barangay_id) 
WHERE is_archived = false;

COMMENT ON INDEX idx_students_household_lookup IS 
'Composite index for household duplicate detection. Filters only active (non-archived) students for performance.';

-- =====================================================
-- 4. Create household block attempts log table
-- =====================================================

CREATE TABLE IF NOT EXISTS household_block_attempts (
    attempt_id SERIAL PRIMARY KEY,
    attempted_first_name VARCHAR(100) NOT NULL,
    attempted_last_name VARCHAR(100) NOT NULL,
    attempted_email VARCHAR(255),
    attempted_mobile VARCHAR(20),
    mothers_maiden_name_entered VARCHAR(100) NOT NULL,
    barangay_entered VARCHAR(100) NOT NULL,
    blocked_by_student_id VARCHAR(50) REFERENCES students(student_id),
    blocked_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    ip_address INET,
    user_agent TEXT,
    match_type VARCHAR(20) CHECK (match_type IN ('exact', 'fuzzy', 'user_confirmed')),
    similarity_score DECIMAL(3,2), -- For fuzzy matches (0.00 to 1.00)
    admin_override BOOLEAN DEFAULT FALSE,
    override_reason TEXT,
    override_by_admin_id INTEGER REFERENCES admins(admin_id),
    override_at TIMESTAMP,
    bypass_token VARCHAR(64) UNIQUE,
    bypass_token_expires_at TIMESTAMP,
    bypass_token_used BOOLEAN DEFAULT FALSE,
    notes TEXT
);

COMMENT ON TABLE household_block_attempts IS 
'Log of all household duplicate registration attempts that were blocked. Used for analytics, fraud detection, and appeal processing.';

COMMENT ON COLUMN household_block_attempts.match_type IS 
'Type of match: exact (identical), fuzzy (similar via pg_trgm), user_confirmed (user confirmed fuzzy match)';

COMMENT ON COLUMN household_block_attempts.similarity_score IS 
'Similarity score from pg_trgm for fuzzy matches (0.70-1.00)';

COMMENT ON COLUMN household_block_attempts.bypass_token IS 
'One-time token for admin-approved override registrations';

-- Create indexes for household_block_attempts table
CREATE INDEX IF NOT EXISTS idx_household_blocks_date 
ON household_block_attempts(blocked_at DESC);

CREATE INDEX IF NOT EXISTS idx_household_blocks_barangay 
ON household_block_attempts(barangay_entered);

CREATE INDEX IF NOT EXISTS idx_household_blocks_override 
ON household_block_attempts(admin_override) 
WHERE admin_override = false;

CREATE INDEX IF NOT EXISTS idx_household_blocks_bypass_token 
ON household_block_attempts(bypass_token) 
WHERE bypass_token IS NOT NULL AND bypass_token_used = false;

-- =====================================================
-- 5. Create index for fuzzy matching (trigram)
-- =====================================================

CREATE INDEX IF NOT EXISTS idx_students_maiden_name_trgm 
ON students USING gin (mothers_maiden_name gin_trgm_ops)
WHERE is_archived = false;

COMMENT ON INDEX idx_students_maiden_name_trgm IS 
'Trigram index for fuzzy matching of mother''s maiden name (catches typos)';

-- =====================================================
-- 6. Create audit log for admin reviews
-- =====================================================

CREATE TABLE IF NOT EXISTS household_admin_reviews (
    review_id SERIAL PRIMARY KEY,
    student_id VARCHAR(50) REFERENCES students(student_id),
    review_type VARCHAR(50) CHECK (review_type IN ('same_surname_flag', 'override_approval', 'manual_correction')),
    reviewed_by_admin_id INTEGER REFERENCES admins(admin_id),
    reviewed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    review_notes TEXT,
    previous_value TEXT,
    new_value TEXT,
    action_taken VARCHAR(50) CHECK (action_taken IN ('approved', 'rejected', 'corrected', 'flagged'))
);

COMMENT ON TABLE household_admin_reviews IS 
'Audit trail for admin actions related to household duplicate prevention (reviews, overrides, corrections)';

CREATE INDEX IF NOT EXISTS idx_household_reviews_student 
ON household_admin_reviews(student_id);

CREATE INDEX IF NOT EXISTS idx_household_reviews_date 
ON household_admin_reviews(reviewed_at DESC);

-- =====================================================
-- 7. Create view for admin dashboard statistics
-- =====================================================

CREATE OR REPLACE VIEW v_household_prevention_stats AS
SELECT 
    -- Blocked attempts (last 30 days)
    (SELECT COUNT(*) 
     FROM household_block_attempts 
     WHERE blocked_at >= CURRENT_DATE - INTERVAL '30 days'
    ) AS blocked_attempts_30d,
    

    (SELECT COUNT(*) 
     FROM students 
     WHERE admin_review_required = true 
       AND is_archived = false
    ) AS flagged_for_review,
    

    (SELECT COUNT(*) 
     FROM household_block_attempts 
     WHERE admin_override = true 
       AND override_at >= CURRENT_DATE - INTERVAL '30 days'
    ) AS override_approvals_30d,
    

    (SELECT COUNT(*) 
     FROM students 
     WHERE is_archived = false
    ) AS total_active_students,
    

    (SELECT COUNT(*) 
     FROM students 
     WHERE mothers_maiden_name IS NULL 
       AND is_archived = false
    ) AS students_missing_maiden_name,
    

    (SELECT barangay_entered 
     FROM household_block_attempts 
     WHERE blocked_at >= CURRENT_DATE - INTERVAL '30 days'
     GROUP BY barangay_entered 
     ORDER BY COUNT(*) DESC 
     LIMIT 1
    ) AS top_blocked_barangay,
    

    CASE 
        WHEN (SELECT COUNT(*) FROM household_block_attempts) > 0 
        THEN ROUND(
            (SELECT COUNT(*)::NUMERIC FROM household_block_attempts WHERE admin_override = true) / 
            (SELECT COUNT(*)::NUMERIC FROM household_block_attempts) * 100, 
            2
        )
        ELSE 0 
    END AS false_positive_rate_percent;

COMMENT ON VIEW v_household_prevention_stats IS 
'Real-time statistics for household duplicate prevention system (used in admin dashboard widget)';

-- =====================================================
-- 8. Create view for top blocked barangays
-- =====================================================

CREATE OR REPLACE VIEW v_household_blocks_by_barangay AS
SELECT 
    barangay_entered AS barangay,
    COUNT(*) AS total_blocks,
    COUNT(CASE WHEN admin_override = true THEN 1 END) AS overridden_blocks,
    COUNT(CASE WHEN blocked_at >= CURRENT_DATE - INTERVAL '30 days' THEN 1 END) AS blocks_last_30d,
    MIN(blocked_at) AS first_block_date,
    MAX(blocked_at) AS last_block_date
FROM household_block_attempts
GROUP BY barangay_entered
ORDER BY total_blocks DESC;

COMMENT ON VIEW v_household_blocks_by_barangay IS 
'Analytics view: household blocks aggregated by barangay for fraud detection and reporting';

-- =====================================================
-- 9. Grant permissions (adjust as needed for your setup)
-- =====================================================

-- Grant SELECT on views to admin users
-- GRANT SELECT ON v_household_prevention_stats TO admin_role;
-- GRANT SELECT ON v_household_blocks_by_barangay TO admin_role;

-- Grant appropriate permissions on tables
-- GRANT SELECT, INSERT ON household_block_attempts TO app_user;
-- GRANT SELECT, INSERT ON household_admin_reviews TO admin_role;

-- =====================================================
-- 10. Sample query: Check for household duplicate
-- =====================================================

-- Example exact match query (use in PHP with parameterized query)
/*
SELECT 
    student_id, 
    first_name, 
    last_name, 
    created_at AS registered_date
FROM students 
WHERE LOWER(last_name) = LOWER('Santos')
  AND LOWER(mothers_maiden_name) = LOWER('Garcia')
  AND barangay = 'San Gabriel'
  AND is_archived = false
LIMIT 1;
*/

-- Example fuzzy match query (catches typos)
/*
SELECT 
    student_id,
    first_name,
    last_name,
    mothers_maiden_name,
    similarity(mothers_maiden_name, 'Garsia') AS match_score
FROM students 
WHERE LOWER(last_name) = LOWER('Santos')
  AND barangay = 'San Gabriel'
  AND similarity(mothers_maiden_name, 'Garsia') > 0.70
  AND is_archived = false
ORDER BY match_score DESC
LIMIT 1;
*/

-- =====================================================
-- 11. Migration status check queries
-- =====================================================

-- Check if columns were added successfully
/*
SELECT 
    column_name, 
    data_type, 
    is_nullable,
    column_default
FROM information_schema.columns 
WHERE table_name = 'students' 
  AND column_name IN ('mothers_maiden_name', 'admin_review_required');
*/

-- Check indexes
/*
SELECT 
    indexname, 
    indexdef 
FROM pg_indexes 
WHERE tablename IN ('students', 'household_block_attempts')
ORDER BY tablename, indexname;
*/

-- Check extension
/*
SELECT * FROM pg_extension WHERE extname = 'pg_trgm';
*/

-- =====================================================
-- MIGRATION COMPLETE
-- =====================================================

-- Run this query to verify everything is set up correctly:
SELECT 
    'students.mothers_maiden_name' AS component,
    CASE WHEN column_name IS NOT NULL THEN '✓ Created' ELSE '✗ Missing' END AS status
FROM information_schema.columns 
WHERE table_name = 'students' AND column_name = 'mothers_maiden_name'

UNION ALL

SELECT 
    'students.admin_review_required' AS component,
    CASE WHEN column_name IS NOT NULL THEN '✓ Created' ELSE '✗ Missing' END AS status
FROM information_schema.columns 
WHERE table_name = 'students' AND column_name = 'admin_review_required'

UNION ALL

SELECT 
    'idx_students_household_lookup' AS component,
    CASE WHEN indexname IS NOT NULL THEN '✓ Created' ELSE '✗ Missing' END AS status
FROM pg_indexes 
WHERE tablename = 'students' AND indexname = 'idx_students_household_lookup'

UNION ALL

SELECT 
    'household_block_attempts table' AS component,
    CASE WHEN table_name IS NOT NULL THEN '✓ Created' ELSE '✗ Missing' END AS status
FROM information_schema.tables 
WHERE table_name = 'household_block_attempts'

UNION ALL

SELECT 
    'household_admin_reviews table' AS component,
    CASE WHEN table_name IS NOT NULL THEN '✓ Created' ELSE '✗ Missing' END AS status
FROM information_schema.tables 
WHERE table_name = 'household_admin_reviews'

UNION ALL

SELECT 
    'pg_trgm extension' AS component,
    CASE WHEN extname IS NOT NULL THEN '✓ Enabled' ELSE '✗ Missing' END AS status
FROM pg_extension 
WHERE extname = 'pg_trgm';

-- End of migration script
