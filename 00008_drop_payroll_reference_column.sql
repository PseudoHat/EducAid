BEGIN;

-- Drop index on payroll_reference if it exists (safety)
DO $$ BEGIN
    IF EXISTS (
        SELECT 1 FROM pg_indexes WHERE tablename='students' AND indexname='idx_students_payroll_reference'
    ) THEN
        EXECUTE 'DROP INDEX IF EXISTS idx_students_payroll_reference';
    END IF;
END $$;

-- Drop students.payroll_reference if present
DO $$ BEGIN
    IF EXISTS (
        SELECT 1 FROM information_schema.columns 
        WHERE table_name='students' AND column_name='payroll_reference'
    ) THEN
        ALTER TABLE students DROP COLUMN payroll_reference;
    END IF;
END $$;

COMMIT;