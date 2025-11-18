BEGIN;

-- Convert students.payroll_no from integer to text and normalize values
DO $$ BEGIN
    IF EXISTS (
        SELECT 1 FROM information_schema.columns 
        WHERE table_name='students' AND column_name='payroll_no'
    ) THEN
        -- Normalize 0 to NULL before type change
        BEGIN
            UPDATE students SET payroll_no = NULL WHERE payroll_no = 0;
        EXCEPTION WHEN others THEN
            -- ignore if not integer
            NULL;
        END;

        -- Change type to TEXT
        ALTER TABLE students 
            ALTER COLUMN payroll_no TYPE text 
            USING CASE WHEN payroll_no IS NULL THEN NULL ELSE payroll_no::text END;
    END IF;
END $$;

-- Convert schedules.payroll_no to text if it exists
DO $$ BEGIN
    IF EXISTS (
        SELECT 1 FROM information_schema.columns 
        WHERE table_name='schedules' AND column_name='payroll_no'
    ) THEN
        ALTER TABLE schedules 
            ALTER COLUMN payroll_no TYPE text 
            USING payroll_no::text;
    END IF;
END $$;

-- Convert qr_codes.payroll_number to text if it exists
DO $$ BEGIN
    IF EXISTS (
        SELECT 1 FROM information_schema.columns 
        WHERE table_name='qr_codes' AND column_name='payroll_number'
    ) THEN
        BEGIN
            UPDATE qr_codes SET payroll_number = NULL WHERE payroll_number::text = '0';
        EXCEPTION WHEN others THEN
            NULL;
        END;
        ALTER TABLE qr_codes 
            ALTER COLUMN payroll_number TYPE text 
            USING payroll_number::text;
    END IF;
END $$;

-- Backfill students.payroll_no from students.payroll_reference when available and payroll_no is numeric/empty
DO $$ BEGIN
    IF EXISTS (
        SELECT 1 FROM information_schema.columns 
        WHERE table_name='students' AND column_name='payroll_reference'
    ) THEN
        UPDATE students
           SET payroll_no = payroll_reference
         WHERE COALESCE(payroll_reference,'') <> ''
           AND (payroll_no IS NULL OR payroll_no ~ '^[0-9]+$' OR payroll_no = '');
    END IF;
END $$;

-- Backfill qr_codes.payroll_number from students.payroll_no (now formatted)
DO $$ BEGIN
    IF EXISTS (
        SELECT 1 FROM information_schema.columns 
        WHERE table_name='qr_codes' AND column_name='payroll_number'
    ) THEN
        UPDATE qr_codes q
           SET payroll_number = s.payroll_no
          FROM students s
         WHERE s.student_id = q.student_id
           AND COALESCE(s.payroll_no,'') <> ''
           AND (q.payroll_number IS NULL OR q.payroll_number ~ '^[0-9]+$' OR q.payroll_number = '');
    END IF;
END $$;

COMMIT;