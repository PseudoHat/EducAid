#!/usr/bin/env bash
# Start script for web process: use heroku-php-apache2 if available, otherwise fall back to PHP built-in server

set -euo pipefail

# Install Tesseract OCR and ImageMagick if not already installed
if ! command -v tesseract &> /dev/null; then
    echo "Installing Tesseract OCR and ImageMagick..."
    apt-get update -qq
    apt-get install -y -qq tesseract-ocr tesseract-ocr-eng imagemagick
    echo "Tesseract and ImageMagick installed successfully"
else
    echo "Tesseract already installed: $(tesseract --version | head -n 1)"
fi

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
