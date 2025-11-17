-- Reset Identity Documents description to show only "Valid School ID"
-- Run this in your PostgreSQL database

DELETE FROM requirements_content_blocks 
WHERE block_key = 'req_cat1_desc' 
AND municipality_id = 1;

-- Verify the change
SELECT block_key, html FROM requirements_content_blocks WHERE block_key = 'req_cat1_desc';
