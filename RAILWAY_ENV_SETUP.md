# Railway Environment Variables Setup

## Required Environment Variables

Set these in your Railway project settings (Variables tab):

### Database Connection (Railway Postgres)
```
DATABASE_URL=postgresql://postgres:rmcaehIHhQZeUcWLkoGEYfzokxiELwCY@switchback.proxy.rlwy.net:19056/railway
```

**OR** set individual variables:
```
DB_HOST=switchback.proxy.rlwy.net
DB_PORT=19056
DB_NAME=railway
DB_USER=postgres
DB_PASSWORD=rmcaehIHhQZeUcWLkoGEYfzokxiELwCY
```

### reCAPTCHA Keys (already set)
```
RECAPTCHA_SITE_KEY=6LcQ9NArAAAAALTbYBJn1b2iG9MJcJ6SnA3b6x53
RECAPTCHA_SECRET_KEY=6LcQ9NArAAAAALDwn00kgc8GdDr-tFo3vBE-I48o9o
```

## Steps to Deploy

1. **Set Environment Variables in Railway**
   - Go to your Railway project dashboard
   - Click on your service
   - Go to "Variables" tab
   - Add `DATABASE_URL` (preferred) or individual DB_* variables
   - Ensure reCAPTCHA keys are set (already done)

2. **Commit and Push Changes**
   ```powershell
   git add composer.json config/database.php
   git commit -m "Add pgsql extension requirement and Railway DB support"
   git push
   ```

3. **Railway will auto-deploy** and:
   - Install PostgreSQL PHP extensions (ext-pgsql, ext-pdo_pgsql)
   - Connect to your Railway Postgres database
   - Use the reCAPTCHA keys you've already set

4. **Verify Deployment**
   - Check Railway logs: `railway logs`
   - Should see: "Database connected: host=switchback.proxy.rlwy.net port=19056 dbname=railway"
   - Should NOT see: "Call to undefined function pg_connect()"

## Troubleshooting

### If pg_connect() still fails
- Verify `composer.json` has `ext-pgsql` and `ext-pdo_pgsql` in require section
- Check Railway build logs for extension installation messages
- Ensure the buildpack detected PHP correctly

### If database connection fails
- Verify DATABASE_URL is set correctly in Railway Variables
- Check if Railway Postgres service is running
- Verify the password hasn't changed (Railway rotates credentials sometimes)
- Check Railway logs for the exact connection error

### Local Development (XAMPP)
For local development, create a `.env` file or use the existing `config/env.php`:
```php
<?php
putenv('DB_HOST=localhost');
putenv('DB_NAME=educaid');
putenv('DB_USER=postgres');
putenv('DB_PASSWORD=your_local_password');
putenv('DB_PORT=5432');
```

The code will automatically use local fallback values if DATABASE_URL is not set.
