# 🚂 Run Railway Migration via CLI

# Step 1: Connect to Railway PostgreSQL
railway connect postgres

# Step 2: Once connected, run this command inside psql:
\i database/migrations/2025-11-12_fix_student_id_type.sql

# Step 3: Verify it worked:
SELECT column_name, data_type, character_maximum_length 
FROM information_schema.columns 
WHERE table_name = 'student_data_export_requests' 
AND column_name = 'student_id';

# Step 4: Exit
\q
