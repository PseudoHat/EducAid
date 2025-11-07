#!/usr/bin/env bash
# Start script for web process: use heroku-php-apache2 if available, otherwise fall back to PHP built-in server

set -euo pipefail

# CRITICAL: Create upload directory structure in the mounted volume
echo "Initializing upload directory structure..."
mkdir -p /app/assets/uploads/temp/enrollment_forms
mkdir -p /app/assets/uploads/temp/id_pictures
mkdir -p /app/assets/uploads/temp/letter_mayor
mkdir -p /app/assets/uploads/temp/indigency
mkdir -p /app/assets/uploads/temp/grades
mkdir -p /app/assets/uploads/student/enrollment_forms
mkdir -p /app/assets/uploads/student/id_pictures
mkdir -p /app/assets/uploads/student/letter_mayor
mkdir -p /app/assets/uploads/student/indigency
mkdir -p /app/assets/uploads/student/grades
chmod -R 755 /app/assets/uploads
echo "✓ Upload directories created"

PORT=${PORT:-8080}
# Default document root: the repository's website landing page directory
# Changeable via DOC_ROOT environment variable (e.g., set to 'modules/student' or other)
DOC_ROOT=${DOC_ROOT:-website}

if [ -x "./vendor/bin/heroku-php-apache2" ]; then
  echo "Starting heroku-php-apache2 (provided by buildpack)..."
  exec ./vendor/bin/heroku-php-apache2 "$DOC_ROOT"
else
  echo "vendor/bin/heroku-php-apache2 not found — falling back to php -S on port $PORT with router"
  # Use router.php to properly serve static assets and route PHP requests
  exec php -S 0.0.0.0:"$PORT" router.php
fi
