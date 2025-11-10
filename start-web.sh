#!/usr/bin/env bash
# Start script for web process: use heroku-php-apache2 if available, otherwise fall back to PHP built-in server

set -euo pipefail

# CRITICAL: Create upload directory structure in the Railway volume
echo "========================================="
echo "Initializing upload directory structure..."
echo "========================================="

# Check if Railway volume exists
if [ -d "/mnt/assets/uploads" ]; then
  echo "✓ Railway volume detected at /mnt/assets/uploads"
  
  # Create Railway volume directory structure with short folder names
  echo "Creating directory structure..."
  mkdir -p /mnt/assets/uploads/temp/EAF || echo "⚠ Failed to create temp/EAF"
  mkdir -p /mnt/assets/uploads/temp/ID || echo "⚠ Failed to create temp/ID"
  mkdir -p /mnt/assets/uploads/temp/Letter || echo "⚠ Failed to create temp/Letter"
  mkdir -p /mnt/assets/uploads/temp/Indigency || echo "⚠ Failed to create temp/Indigency"
  mkdir -p /mnt/assets/uploads/temp/Grades || echo "⚠ Failed to create temp/Grades"
  mkdir -p /mnt/assets/uploads/student/EAF || echo "⚠ Failed to create student/EAF"
  mkdir -p /mnt/assets/uploads/student/ID || echo "⚠ Failed to create student/ID"
  mkdir -p /mnt/assets/uploads/student/Letter || echo "⚠ Failed to create student/Letter"
  mkdir -p /mnt/assets/uploads/student/Indigency || echo "⚠ Failed to create student/Indigency"
  mkdir -p /mnt/assets/uploads/student/Grades || echo "⚠ Failed to create student/Grades"
  echo "✓ Directories created"
  
  echo "Setting permissions..."
  chmod -R 755 /mnt/assets/uploads || echo "⚠ Failed to set permissions"
  echo "✓ Permissions set to 755"
  
  # Create symlink so /app/assets/uploads points to volume
  echo "Creating symlink..."
  mkdir -p /app/assets || echo "⚠ Failed to create /app/assets"
  
  # FORCEFULLY remove existing directory/symlink if exists
  # Use rm -rf to handle directories with content
  if [ -e /app/assets/uploads ] || [ -L /app/assets/uploads ]; then
    echo "Removing existing /app/assets/uploads..."
    chmod -R 777 /app/assets/uploads 2>/dev/null || true  # Ensure we can delete
    rm -rf /app/assets/uploads 2>/dev/null || {
      echo "⚠ Standard removal failed, trying forced removal..."
      # Nuclear option: remove even if busy
      find /app/assets/uploads -type f -delete 2>/dev/null || true
      find /app/assets/uploads -type d -delete 2>/dev/null || true
      rmdir /app/assets/uploads 2>/dev/null || rm -f /app/assets/uploads 2>/dev/null || true
    }
    echo "✓ Existing uploads removed"
  fi
  
  # Create symlink
  ln -sf /mnt/assets/uploads /app/assets/uploads || {
    echo "✗ ERROR: Failed to create symlink!"
    echo "Attempting alternative symlink creation..."
    # Try absolute path
    cd /app/assets && ln -sf /mnt/assets/uploads uploads
  }
  
  # Verify symlink
  if [ -L /app/assets/uploads ]; then
    LINK_TARGET=$(readlink /app/assets/uploads)
    echo "✓ Symlink created: /app/assets/uploads -> $LINK_TARGET"
  else
    echo "✗ ERROR: Symlink creation failed!"
    echo "Directory listing:"
    ls -la /app/assets/ || echo "Cannot list /app/assets/"
  fi
  
  echo "✓ Railway volume setup complete!"
else
  echo "✗ No Railway volume detected at /mnt/assets/uploads"
  echo "Using local /app/assets/uploads (ephemeral - will be deleted on redeploy)"
  
  # Create local directory structure for development/non-Railway deploys
  mkdir -p /app/assets/uploads/temp/enrollment_forms
  mkdir -p /app/assets/uploads/temp/id_pictures
  mkdir -p /app/assets/uploads/temp/letter_to_mayor
  mkdir -p /app/assets/uploads/temp/indigency
  mkdir -p /app/assets/uploads/temp/grades
  mkdir -p /app/assets/uploads/student/enrollment_forms
  mkdir -p /app/assets/uploads/student/id_pictures
  mkdir -p /app/assets/uploads/student/letter_to_mayor
  mkdir -p /app/assets/uploads/student/indigency
  mkdir -p /app/assets/uploads/student/grades
  chmod -R 755 /app/assets/uploads
  echo "✓ Local upload directories created"
fi

echo "========================================="

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
