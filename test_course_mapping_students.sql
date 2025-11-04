-- ==============================================================
-- TEST STUDENTS FOR COURSE MAPPING DEMONSTRATION
-- ==============================================================
-- This script creates two test students to demonstrate the course mapping workflow:
-- 1. Student with a NEW course that needs to be added to the mapping table
-- 2. Student with an EXISTING UNVERIFIED course mapping
-- ==============================================================

-- First, let's check what universities and year levels exist
-- SELECT university_id, name FROM universities LIMIT 5;
-- SELECT year_level_id, name FROM year_levels LIMIT 5;
-- SELECT barangay_id, name FROM barangays LIMIT 5;

-- ==============================================================
-- TEST STUDENT 1: NEW COURSE (not yet in courses_mapping)
-- ==============================================================
-- Course: "BS Data Science" - A newly introduced program that doesn't exist in the mapping table yet
-- This will test the system's ability to handle completely new courses

INSERT INTO students (
    student_id,
    first_name,
    middle_name,
    last_name,
    extension_name,
    birthdate,
    sex,
    email,
    mobile,
    password,
    university_id,
    year_level_id,
    barangay_id,
    course,
    status,
    created_at,
    school_student_id
) VALUES (
    'TEST-DS-2024-001',
    'Maria',
    'Santos',
    'Cruz',
    '',
    '2003-05-15',
    'Female',
    'maria.cruz.datasci@test.edu',
    '+639171234567',
    '$2y$10$YourHashedPasswordHere123456789012345678901234567890123',  -- password: Test123!
    (SELECT university_id FROM universities WHERE name ILIKE '%cavite state%' LIMIT 1),
    (SELECT year_level_id FROM year_levels WHERE name = 'Third Year' LIMIT 1),
    (SELECT barangay_id FROM barangays ORDER BY RANDOM() LIMIT 1),
    'BS Data Science',  -- NEW COURSE: Will create unverified mapping
    'under_registration',
    NOW() - INTERVAL '2 hours',
    '2024-3-DS-001'
);

-- Add document records for TEST STUDENT 1
INSERT INTO documents (student_id, document_type_id, filename, file_path, status, uploaded_at) VALUES
('TEST-DS-2024-001', 1, 'Cruz_Maria_idpic.jpg', 'assets/uploads/temp/id_pictures/Cruz_Maria_idpic.jpg', 'uploaded', NOW()),
('TEST-DS-2024-001', 2, 'Cruz_Maria_EAF.pdf', 'assets/uploads/temp/enrollment_forms/Cruz_Maria_EAF.pdf', 'uploaded', NOW()),
('TEST-DS-2024-001', 3, 'Cruz_Maria_Letter.pdf', 'assets/uploads/temp/letters/Cruz_Maria_Letter.pdf', 'uploaded', NOW()),
('TEST-DS-2024-001', 4, 'Cruz_Maria_Indigency.pdf', 'assets/uploads/temp/certificates/Cruz_Maria_Indigency.pdf', 'uploaded', NOW()),
('TEST-DS-2024-001', 5, 'Cruz_Maria_Grades.pdf', 'assets/uploads/temp/grades/Cruz_Maria_Grades.pdf', 'uploaded', NOW());

-- Set a confidence score for the student
UPDATE students 
SET confidence_score = 78.5 
WHERE student_id = 'TEST-DS-2024-001';

-- ==============================================================
-- TEST STUDENT 2: EXISTING UNVERIFIED COURSE MAPPING
-- ==============================================================
-- Course: "BS Cyber Security" - Already exists in courses_mapping but is UNVERIFIED
-- This will test the verification workflow

-- First, insert the unverified course mapping if it doesn't exist
INSERT INTO courses_mapping (
    raw_course_name,
    normalized_course,
    course_category,
    program_duration,
    university_id,
    is_verified,
    occurrence_count,
    created_at,
    last_seen
) VALUES (
    'BS Cyber Security',
    'BS Cybersecurity',
    'Engineering & Technology',
    4,
    NULL,  -- Applies to all universities
    FALSE,  -- UNVERIFIED - needs admin verification
    1,
    NOW() - INTERVAL '1 day',
    NOW() - INTERVAL '1 day'
) ON CONFLICT DO NOTHING;

-- Now insert the test student
INSERT INTO students (
    student_id,
    first_name,
    middle_name,
    last_name,
    extension_name,
    birthdate,
    sex,
    email,
    mobile,
    password,
    university_id,
    year_level_id,
    barangay_id,
    course,
    status,
    created_at,
    school_student_id
) VALUES (
    'TEST-CS-2024-002',
    'Juan',
    'Dela',
    'Reyes',
    'Jr.',
    '2002-08-22',
    'Male',
    'juan.reyes.cybersec@test.edu',
    '+639181234567',
    '$2y$10$YourHashedPasswordHere123456789012345678901234567890123',  -- password: Test123!
    (SELECT university_id FROM universities WHERE name ILIKE '%lyceum%' LIMIT 1),
    (SELECT year_level_id FROM year_levels WHERE name = 'Second Year' LIMIT 1),
    (SELECT barangay_id FROM barangays ORDER BY RANDOM() LIMIT 1),
    'BS Cyber Security',  -- EXISTING UNVERIFIED COURSE
    'under_registration',
    NOW() - INTERVAL '1 hour',
    '2024-2-CS-002'
);

-- Add document records for TEST STUDENT 2
INSERT INTO documents (student_id, document_type_id, filename, file_path, status, uploaded_at) VALUES
('TEST-CS-2024-002', 1, 'Reyes_Juan_idpic.jpg', 'assets/uploads/temp/id_pictures/Reyes_Juan_idpic.jpg', 'uploaded', NOW()),
('TEST-CS-2024-002', 2, 'Reyes_Juan_EAF.pdf', 'assets/uploads/temp/enrollment_forms/Reyes_Juan_EAF.pdf', 'uploaded', NOW()),
('TEST-CS-2024-002', 3, 'Reyes_Juan_Letter.pdf', 'assets/uploads/temp/letters/Reyes_Juan_Letter.pdf', 'uploaded', NOW()),
('TEST-CS-2024-002', 4, 'Reyes_Juan_Indigency.pdf', 'assets/uploads/temp/certificates/Reyes_Juan_Indigency.pdf', 'uploaded', NOW()),
('TEST-CS-2024-002', 5, 'Reyes_Juan_Grades.pdf', 'assets/uploads/temp/grades/Reyes_Juan_Grades.pdf', 'uploaded', NOW());

-- Set a confidence score for the student
UPDATE students 
SET confidence_score = 82.3 
WHERE student_id = 'TEST-CS-2024-002';

-- ==============================================================
-- VERIFICATION QUERIES
-- ==============================================================

-- Check if test students were created
SELECT 
    student_id,
    CONCAT(first_name, ' ', last_name) as full_name,
    course,
    status,
    confidence_score,
    email
FROM students 
WHERE student_id IN ('TEST-DS-2024-001', 'TEST-CS-2024-002')
ORDER BY student_id;

-- Check course mappings
SELECT 
    mapping_id,
    raw_course_name,
    normalized_course,
    course_category,
    is_verified,
    occurrence_count,
    (SELECT COUNT(*) FROM students WHERE course = cm.raw_course_name) as student_count
FROM courses_mapping cm
WHERE raw_course_name IN ('BS Data Science', 'BS Cyber Security')
ORDER BY raw_course_name;

-- ==============================================================
-- EXPECTED WORKFLOW:
-- ==============================================================
-- 
-- 1. MARIA CRUZ (BS Data Science - NEW COURSE):
--    - When admin views review_registrations.php, Maria's course will show as "NEW/UNVERIFIED"
--    - Approving Maria will automatically create an UNVERIFIED course mapping
--    - Admin must go to manage_course_mappings.php to verify "BS Data Science"
--    - Admin can set normalized name, category, duration, notes
--
-- 2. JUAN REYES (BS Cyber Security - EXISTING UNVERIFIED):
--    - When admin views review_registrations.php, Juan's course shows as "UNVERIFIED"
--    - The mapping already exists but needs verification
--    - Admin can verify it in manage_course_mappings.php
--    - Once verified, future students with "BS Cyber Security" will show as verified
--
-- ==============================================================
-- CLEANUP (Run this to remove test data):
-- ==============================================================
/*
DELETE FROM documents WHERE student_id IN ('TEST-DS-2024-001', 'TEST-CS-2024-002');
DELETE FROM students WHERE student_id IN ('TEST-DS-2024-001', 'TEST-CS-2024-002');
DELETE FROM courses_mapping WHERE raw_course_name IN ('BS Data Science', 'BS Cyber Security') AND is_verified = FALSE;
*/
