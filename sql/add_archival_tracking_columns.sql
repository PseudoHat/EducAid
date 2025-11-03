-- Add columns for comprehensive archival tracking
-- Run this script to add new columns to students table

-- Unarchival tracking (for household duplicates that can be restored)
ALTER TABLE students 
ADD COLUMN IF NOT EXISTS unarchived_at TIMESTAMP DEFAULT NULL;

ALTER TABLE students 
ADD COLUMN IF NOT EXISTS unarchived_by INTEGER DEFAULT NULL;

ALTER TABLE students 
ADD COLUMN IF NOT EXISTS unarchive_reason TEXT DEFAULT NULL;

-- Household relationship tracking
ALTER TABLE students 
ADD COLUMN IF NOT EXISTS household_verified BOOLEAN DEFAULT FALSE;

ALTER TABLE students 
ADD COLUMN IF NOT EXISTS household_primary BOOLEAN DEFAULT FALSE;

ALTER TABLE students 
ADD COLUMN IF NOT EXISTS household_group_id TEXT DEFAULT NULL;

-- Archival type tracking (to distinguish different archival scenarios)
ALTER TABLE students 
ADD COLUMN IF NOT EXISTS archival_type VARCHAR(50) DEFAULT NULL;

-- Add foreign key for unarchived_by
ALTER TABLE students 
ADD CONSTRAINT fk_unarchived_by 
FOREIGN KEY (unarchived_by) REFERENCES admins(admin_id) ON DELETE SET NULL;

-- Add comments for documentation
COMMENT ON COLUMN students.unarchived_at IS 'Timestamp when student was unarchived (for household duplicates)';
COMMENT ON COLUMN students.unarchived_by IS 'Admin who unarchived the student';
COMMENT ON COLUMN students.unarchive_reason IS 'Reason for unarchiving (e.g., primary recipient graduated)';
COMMENT ON COLUMN students.household_verified IS 'Admin verified household relationship (same/different household)';
COMMENT ON COLUMN students.household_primary IS 'TRUE if this is the primary household recipient receiving assistance';
COMMENT ON COLUMN students.household_group_id IS 'Links household members together (same value for siblings)';
COMMENT ON COLUMN students.archival_type IS 'Type of archival: manual, graduated, household_duplicate, blacklisted';

-- Create index for household queries
CREATE INDEX IF NOT EXISTS idx_students_household_group ON students(household_group_id) WHERE household_group_id IS NOT NULL;
CREATE INDEX IF NOT EXISTS idx_students_archival_type ON students(archival_type) WHERE archival_type IS NOT NULL;

-- Show success message
\echo '✓ Archival tracking columns added successfully';
