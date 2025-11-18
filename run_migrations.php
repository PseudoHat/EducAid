<?php
// Run database migrations for payroll_no TEXT conversion
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/config/database.php';

echo "<h2>Running Database Migrations</h2>";
echo "<pre>";

// Migration 1: Convert payroll_no to TEXT
echo "\n=== Running 00007_migrate_payroll_no_to_text.sql ===\n\n";

try {
    // Execute migration 1
    pg_query($connection, "BEGIN");
    
    // Convert students.payroll_no from integer to text
    $step1 = pg_query($connection, "
        DO \$\$ BEGIN
            IF EXISTS (
                SELECT 1 FROM information_schema.columns 
                WHERE table_name='students' AND column_name='payroll_no'
            ) THEN
                UPDATE students SET payroll_no = NULL WHERE payroll_no = 0;
                ALTER TABLE students 
                    ALTER COLUMN payroll_no TYPE text 
                    USING CASE WHEN payroll_no IS NULL THEN NULL ELSE payroll_no::text END;
            END IF;
        END \$\$;
    ");
    
    if (!$step1) throw new Exception("Failed to convert students.payroll_no: " . pg_last_error($connection));
    echo "   ✓ Converted students.payroll_no to TEXT\n";
    
    // Convert schedules.payroll_no to text
    $step2 = pg_query($connection, "
        DO \$\$ BEGIN
            IF EXISTS (
                SELECT 1 FROM information_schema.columns 
                WHERE table_name='schedules' AND column_name='payroll_no'
            ) THEN
                ALTER TABLE schedules 
                    ALTER COLUMN payroll_no TYPE text 
                    USING payroll_no::text;
            END IF;
        END \$\$;
    ");
    
    if (!$step2) throw new Exception("Failed to convert schedules.payroll_no: " . pg_last_error($connection));
    echo "   ✓ Converted schedules.payroll_no to TEXT\n";
    
    // Convert qr_codes.payroll_number to text
    $step3 = pg_query($connection, "
        DO \$\$ BEGIN
            IF EXISTS (
                SELECT 1 FROM information_schema.columns 
                WHERE table_name='qr_codes' AND column_name='payroll_number'
            ) THEN
                UPDATE qr_codes SET payroll_number = NULL WHERE payroll_number::text = '0';
                ALTER TABLE qr_codes 
                    ALTER COLUMN payroll_number TYPE text 
                    USING payroll_number::text;
            END IF;
        END \$\$;
    ");
    
    if (!$step3) throw new Exception("Failed to convert qr_codes.payroll_number: " . pg_last_error($connection));
    echo "   ✓ Converted qr_codes.payroll_number to TEXT\n";
    
    // Backfill from payroll_reference if exists
    $step4 = pg_query($connection, "
        DO \$\$ BEGIN
            IF EXISTS (
                SELECT 1 FROM information_schema.columns 
                WHERE table_name='students' AND column_name='payroll_reference'
            ) THEN
                UPDATE students
                   SET payroll_no = payroll_reference
                 WHERE COALESCE(payroll_reference,'') <> ''
                   AND (payroll_no IS NULL OR payroll_no ~ '^[0-9]+\$' OR payroll_no = '');
            END IF;
        END \$\$;
    ");
    
    if (!$step4) throw new Exception("Failed to backfill payroll_no: " . pg_last_error($connection));
    echo "   ✓ Backfilled data from payroll_reference\n";
    
    pg_query($connection, "COMMIT");
    echo "\n✅ Successfully completed 00007_migrate_payroll_no_to_text.sql\n";
    
} catch (Exception $e) {
    pg_query($connection, "ROLLBACK");
    echo "❌ ERROR: " . $e->getMessage() . "\n";
    exit(1);
}

// Migration 2: Drop payroll_reference column
echo "\n=== Running 00008_drop_payroll_reference_column.sql ===\n\n";

try {
    pg_query($connection, "BEGIN");
    
    // Drop index if exists
    $step1 = pg_query($connection, "
        DO \$\$ BEGIN
            IF EXISTS (
                SELECT 1 FROM pg_indexes WHERE tablename='students' AND indexname='idx_students_payroll_reference'
            ) THEN
                EXECUTE 'DROP INDEX IF EXISTS idx_students_payroll_reference';
            END IF;
        END \$\$;
    ");
    
    if (!$step1) throw new Exception("Failed to drop index: " . pg_last_error($connection));
    echo "   ✓ Dropped payroll_reference index (if existed)\n";
    
    // Drop column if exists
    $step2 = pg_query($connection, "
        DO \$\$ BEGIN
            IF EXISTS (
                SELECT 1 FROM information_schema.columns 
                WHERE table_name='students' AND column_name='payroll_reference'
            ) THEN
                ALTER TABLE students DROP COLUMN payroll_reference;
            END IF;
        END \$\$;
    ");
    
    if (!$step2) throw new Exception("Failed to drop column: " . pg_last_error($connection));
    echo "   ✓ Dropped students.payroll_reference column (if existed)\n";
    
    pg_query($connection, "COMMIT");
    echo "\n✅ Successfully completed 00008_drop_payroll_reference_column.sql\n";
    
} catch (Exception $e) {
    pg_query($connection, "ROLLBACK");
    echo "❌ ERROR: " . $e->getMessage() . "\n";
    exit(1);
}

// Migration 3: Create distribution_payrolls table
echo "\n=== Running 00009_create_distribution_payrolls.sql ===\n\n";

try {
    pg_query($connection, "BEGIN");
    
    // Create distribution_payrolls table
    $step1 = pg_query($connection, "
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
    ");
    
    if (!$step1) throw new Exception("Failed to create distribution_payrolls table: " . pg_last_error($connection));
    echo "   ✓ Created distribution_payrolls table\n";
    
    // Create indexes
    $step2 = pg_query($connection, "
        CREATE INDEX IF NOT EXISTS idx_dist_payrolls_student ON distribution_payrolls(student_id);
    ");
    
    if (!$step2) throw new Exception("Failed to create student index: " . pg_last_error($connection));
    echo "   ✓ Created index on student_id\n";
    
    $step3 = pg_query($connection, "
        CREATE INDEX IF NOT EXISTS idx_dist_payrolls_year_sem ON distribution_payrolls(academic_year, semester);
    ");
    
    if (!$step3) throw new Exception("Failed to create year/semester index: " . pg_last_error($connection));
    echo "   ✓ Created index on academic_year and semester\n";
    
    // Backfill from students table
    $step4 = pg_query($connection, "
        DO \$\$
        DECLARE
            rec RECORD;
            v_academic_year TEXT;
            v_semester TEXT;
        BEGIN
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
                    NULL;
                END;
            END LOOP;
        END \$\$;
    ");
    
    if (!$step4) throw new Exception("Failed to backfill distribution_payrolls: " . pg_last_error($connection));
    
    // Count backfilled records
    $count_result = pg_query($connection, "SELECT COUNT(*) as count FROM distribution_payrolls");
    $count_row = pg_fetch_assoc($count_result);
    echo "   ✓ Backfilled {$count_row['count']} payroll records from students table\n";
    
    pg_query($connection, "COMMIT");
    echo "\n✅ Successfully completed 00009_create_distribution_payrolls.sql\n";
    
} catch (Exception $e) {
    pg_query($connection, "ROLLBACK");
    echo "❌ ERROR: " . $e->getMessage() . "\n";
    exit(1);
}

// Verify changes
echo "\n=== Verifying Changes ===\n\n";

$verify = pg_query($connection, "
    SELECT 
        column_name,
        data_type,
        is_nullable
    FROM information_schema.columns 
    WHERE table_name='students' 
    AND column_name IN ('payroll_no', 'payroll_reference')
    ORDER BY column_name
");

echo "Students table columns:\n";
while ($row = pg_fetch_assoc($verify)) {
    echo "  - {$row['column_name']}: {$row['data_type']} (nullable: {$row['is_nullable']})\n";
}

// Check sample data
$sample = pg_query($connection, "
    SELECT student_id, payroll_no 
    FROM students 
    WHERE payroll_no IS NOT NULL 
    LIMIT 5
");

$count = pg_num_rows($sample);
echo "\nSample payroll numbers ({$count} records with payroll_no):\n";
if ($count > 0) {
    while ($row = pg_fetch_assoc($sample)) {
        echo "  - Student {$row['student_id']}: {$row['payroll_no']}\n";
    }
} else {
    echo "  (No students with payroll numbers yet)\n";
}

echo "\n✅ All migrations completed successfully!\n";
echo "</pre>";
?>
