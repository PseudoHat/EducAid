-- Active: 1751550943824@@127.0.0.1@5432@educaid
BEGIN;
-- Distribution Payroll History Table
-- Tracks each student's payroll number per academic year + semester (optionally linked to a distribution snapshot)
CREATE TABLE IF NOT EXISTS distribution_payrolls (
    id SERIAL PRIMARY KEY,
    student_id TEXT NOT NULL REFERENCES students(student_id) ON DELETE CASCADE,
    payroll_no TEXT NOT NULL,
    academic_year TEXT NOT NULL,
    semester TEXT NOT NULL,
    snapshot_id INTEGER NULL REFERENCES distribution_snapshots(snapshot_id) ON DELETE SET NULL,
    assigned_at TIMESTAMP WITHOUT TIME ZONE DEFAULT NOW(),
    UNIQUE(student_id, academic_year, semester)
);

CREATE INDEX IF NOT EXISTS idx_dist_payrolls_student ON distribution_payrolls(student_id);
CREATE INDEX IF NOT EXISTS idx_dist_payrolls_year_sem ON distribution_payrolls(academic_year, semester);

-- Backfill from current students.payroll_no (legacy latest values) if not already present
DO $$
DECLARE
    rec RECORD;
    v_academic_year TEXT;
    v_semester TEXT;
BEGIN
    /*
      NOTE:
      The students table contains current_academic_year but NOT current_semester in the present schema.
      The earlier attempt to conditionally reference a non-existent column caused a parse-time error.
      We now source academic year & semester from the config table if present; otherwise we fallback.
    */

    SELECT value INTO v_academic_year FROM config WHERE key='current_academic_year' LIMIT 1;
    SELECT value INTO v_semester FROM config WHERE key='current_semester' LIMIT 1;

    IF v_academic_year IS NULL THEN
        v_academic_year := to_char(now(),'YYYY')||'-'||to_char(now()+ interval '1 year','YYYY');
    END IF;
    IF v_semester IS NULL THEN
        v_semester := '1';
    END IF;

    FOR rec IN 
        SELECT student_id, payroll_no
        FROM students
        WHERE payroll_no IS NOT NULL AND payroll_no <> ''
    LOOP
        BEGIN
            INSERT INTO distribution_payrolls (student_id, payroll_no, academic_year, semester)
            VALUES (rec.student_id, rec.payroll_no::text, v_academic_year, v_semester)
            ON CONFLICT (student_id, academic_year, semester) DO NOTHING;
        EXCEPTION WHEN others THEN
            NULL; -- ignore individual failures to keep migration resilient
        END;
    END LOOP;
END $$;

COMMIT;