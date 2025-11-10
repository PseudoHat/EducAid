#!/bin/bash
# Railway startup script to ensure upload directory structure exists

echo "Creating upload directory structure..."

# Create temp directories
mkdir -p /app/assets/uploads/temp/enrollment_forms
mkdir -p /app/assets/uploads/temp/id_pictures
mkdir -p /app/assets/uploads/temp/letter_to_mayor
mkdir -p /app/assets/uploads/temp/indigency
mkdir -p /app/assets/uploads/temp/grades

# Create student directories
mkdir -p /app/assets/uploads/student/enrollment_forms
mkdir -p /app/assets/uploads/student/id_pictures
mkdir -p /app/assets/uploads/student/letter_to_mayor
mkdir -p /app/assets/uploads/student/indigency
mkdir -p /app/assets/uploads/student/grades

# Set permissions
chmod -R 755 /app/assets/uploads

echo "✓ Upload directory structure created"
ls -la /app/assets/uploads/temp/

# Start PHP built-in server
php -S 0.0.0.0:${PORT:-8080} -t /app
