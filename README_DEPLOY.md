## Deployment notes for Railway

This project is a PHP application originally developed to run under XAMPP/Apache on Windows.
These are minimal changes to let Railway detect and run the app using the Heroku PHP buildpack.

Files added:
- `composer.json` — minimal manifest so Railway treats this as a PHP project.
- `Procfile` — start command for the Heroku PHP runtime (Apache).

Important environment variables (set these in Railway project settings):
- `DB_HOST` (or DATABASE_URL)
- `DB_PORT`
- `DB_NAME`
- `DB_USER`
- `DB_PASSWORD`
- `SECRET_KEY` (if your app needs it)

Notes & caveats:
- The code contains many hard-coded Windows paths (e.g. `C:\xampp\htdocs\EducAid\...`). These will break on Linux containers. Search and replace those occurrences before production deployment.
- The built-in Heroku PHP runtime will serve the repository root. If your application expects a different document root (for example `public/`), update `Procfile` to point to that directory: `web: vendor/bin/heroku-php-apache2 public/`.
- If you need full control over extensions (Postgres, GD, etc.), consider adding a `Dockerfile` instead.

Quick deploy steps:
1. Commit and push these files to your repository.
2. In Railway, connect your repo and deploy. Set environment variables listed above.
3. Inspect build logs; if the build complains about missing PHP extensions, consider switching to a Dockerfile with the required extensions.

If you want, I can also:
- Create a `Dockerfile` that installs specific PHP extensions and psql client.
- Run a repository-wide patch to convert absolute Windows paths to portable relative paths (requires careful testing).
