#!/usr/bin/env bash
# Start script for web process: use heroku-php-apache2 if available, otherwise fall back to PHP built-in server

set -euo pipefail

PORT=${PORT:-8080}
# Default document root: the repository's website landing page directory
# Changeable via DOC_ROOT environment variable (e.g., set to 'modules/student' or other)
DOC_ROOT=${DOC_ROOT:-website}

if [ -x "./vendor/bin/heroku-php-apache2" ]; then
  echo "Starting heroku-php-apache2 (provided by buildpack)..."
  exec ./vendor/bin/heroku-php-apache2 "$DOC_ROOT"
else
  echo "vendor/bin/heroku-php-apache2 not found — falling back to php -S on port $PORT (doc root: $DOC_ROOT)"
  # Built-in server is single-process and not for heavy production use; OK for simple staging/testing
  exec php -S 0.0.0.0:"$PORT" -t "$DOC_ROOT"
fi
