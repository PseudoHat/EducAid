<?php
// CLI seeding script: creates demo students
// Usage: php cli_seed_demo_students.php

// Load DB connection
require_once __DIR__ . '/config/database.php';

if (!isset($connection)) {
    fwrite(STDERR, "Database connection not found.\n");
    exit(1);
}

function ensureMunicipalityExists($connection, $municipalityId) {
    $res = pg_query_params($connection, 'SELECT municipality_id FROM municipalities WHERE municipality_id = $1', [$municipalityId]);
    if (!$res || pg_num_rows($res) === 0) {
        // Create a minimal municipality record if table permits it
        // Fallback safe insert with generic values
        $insert = pg_query_params(
            $connection,
            'INSERT INTO municipalities (municipality_id, name, lgu_type) VALUES ($1, $2, $3) ON CONFLICT (municipality_id) DO NOTHING',
            [$municipalityId, 'General Trias (Demo)', 'city']
        );
        if (!$insert) {
            fwrite(STDERR, "Warning: Unable to ensure municipality #$municipalityId exists: " . pg_last_error($connection) . "\n");
        }
    }
}

function ensureBarangayExists($connection, $municipalityId) {
    // Try to find an existing barangay for this municipality
    $res = pg_query_params($connection, 'SELECT barangay_id FROM barangays WHERE municipality_id = $1 LIMIT 1', [$municipalityId]);
    if ($res && pg_num_rows($res) > 0) {
        $row = pg_fetch_assoc($res);
        return (int)$row['barangay_id'];
    }
    
    // Create a demo barangay if none exists
    $insert = pg_query_params(
        $connection,
        'INSERT INTO barangays (name, municipality_id) VALUES ($1, $2) RETURNING barangay_id',
        ['Demo Barangay', $municipalityId]
    );
    if ($insert && pg_num_rows($insert) > 0) {
        $row = pg_fetch_assoc($insert);
        return (int)$row['barangay_id'];
    }
    
    throw new RuntimeException('Failed to ensure barangay exists for municipality ' . $municipalityId);
}

function upsertStudent($connection, $payload) {
    $sql = "INSERT INTO students (
                student_id, first_name, last_name, email, mobile, sex, bdate, password,
                municipality_id, barangay_id, status, current_year_level, 
                first_registered_academic_year, current_academic_year, is_graduating
            ) VALUES ($1, $2, $3, $4, $5, $6, $7, $8, $9, $10, $11, $12, $13, $14, $15)
            ON CONFLICT (student_id) DO UPDATE SET
                first_name = EXCLUDED.first_name,
                last_name = EXCLUDED.last_name,
                email = EXCLUDED.email,
                mobile = EXCLUDED.mobile,
                sex = EXCLUDED.sex,
                bdate = EXCLUDED.bdate,
                password = EXCLUDED.password,
                municipality_id = EXCLUDED.municipality_id,
                barangay_id = EXCLUDED.barangay_id,
                status = EXCLUDED.status,
                current_year_level = EXCLUDED.current_year_level,
                first_registered_academic_year = EXCLUDED.first_registered_academic_year,
                current_academic_year = EXCLUDED.current_academic_year,
                is_graduating = EXCLUDED.is_graduating";

    $params = [
        $payload['student_id'],
        $payload['first_name'],
        $payload['last_name'],
        $payload['email'],
        $payload['mobile'],
        $payload['sex'],
        $payload['bdate'],
        $payload['password_hash'],
        $payload['municipality_id'],
        $payload['barangay_id'],
        $payload['status'],
        $payload['current_year_level'] ?? '2nd Year',
        $payload['first_registered_academic_year'] ?? '2024-2025',
        $payload['current_academic_year'] ?? '2025-2026',
        isset($payload['is_graduating']) ? ($payload['is_graduating'] ? 'true' : 'false') : 'false',
    ];

    $res = pg_query_params($connection, $sql, $params);
    if (!$res) {
        throw new RuntimeException('Insert failed for ' . $payload['student_id'] . ': ' . pg_last_error($connection));
    }
}

function hashPwd($plain) {
    return password_hash($plain, PASSWORD_BCRYPT);
}

try {
    // Ensure municipality #1 exists
    ensureMunicipalityExists($connection, 1);
    
    // Get or create a barangay for municipality 1
    $barangayId = ensureBarangayExists($connection, 1);

    $students = [
        [
            'student_id' => 'DEMO-UR-0001',
            'first_name' => 'Demo',
            'last_name' => 'Registrant',
            'email' => 'demo.ur@example.org',
            'sex' => 'Male',
            'bdate' => '2000-01-01',
            'mobile' => '09120000001',
            'password_hash' => hashPwd('Password123!'),
            'municipality_id' => 1,
            'barangay_id' => $barangayId,
            'status' => 'under_registration',
            'current_year_level' => '2nd Year',
            'first_registered_academic_year' => '2024-2025',
            'current_academic_year' => '2025-2026',
            'is_graduating' => false,
        ],
        [
            'student_id' => 'DEMO-APP-0001',
            'first_name' => 'Demo',
            'last_name' => 'ApplicantOne',
            'email' => 'demo.app1@example.org',
            'sex' => 'Female',
            'bdate' => '2001-05-15',
            'mobile' => '09120000002',
            'password_hash' => hashPwd('Password123!'),
            'municipality_id' => 1,
            'barangay_id' => $barangayId,
            'status' => 'applicant',
            'current_year_level' => '3rd Year',
            'first_registered_academic_year' => '2023-2024',
            'current_academic_year' => '2025-2026',
            'is_graduating' => false,
        ],
        [
            'student_id' => 'DEMO-APP-0002',
            'first_name' => 'Demo',
            'last_name' => 'ApplicantTwo',
            'email' => 'demo.app2@example.org',
            'sex' => 'Male',
            'bdate' => '1999-12-25',
            'mobile' => '09120000003',
            'password_hash' => hashPwd('Password123!'),
            'municipality_id' => 1,
            'barangay_id' => $barangayId,
            'status' => 'applicant',
            'current_year_level' => '4th Year',
            'first_registered_academic_year' => '2022-2023',
            'current_academic_year' => '2025-2026',
            'is_graduating' => false,
        ],
    ];

    foreach ($students as $s) {
        upsertStudent($connection, $s);
    }

    $out = [
        'success' => true,
        'inserted' => array_column($students, 'student_id'),
        'hint' => 'Use these records for archival/rejection/blacklisting demos.'
    ];
    echo json_encode($out, JSON_PRETTY_PRINT) . "\n";
} catch (Throwable $e) {
    $out = [
        'success' => false,
        'error' => $e->getMessage(),
    ];
    echo json_encode($out, JSON_PRETTY_PRINT) . "\n";
    exit(1);
}
