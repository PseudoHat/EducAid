# 🚀 Deployment Guide - Footer UI & Export Feature Updates

## 📦 What This Deployment Includes

1. **Footer Settings UI Improvements** - Modern design with sticky sidebar
2. **Export Feature Fixes** - Error handling, database setup, modal confirmation
3. **Function Redeclaration Fixes** - `adjustColorOpacity()` in sidebars
4. **Database Schema** - `student_data_export_requests` table

---

## 🎯 Deployment Steps (Run in Order)

### ✅ Step 1: Commit and Push Changes to GitHub

All the code changes are already in your localhost. Now push them to both environments:

```powershell
# Navigate to your project directory
cd "C:\xampp\htdocs\EducAid 2\EducAid"

# Check what files changed
git status

# Add all changes
git add .

# Commit with descriptive message
git commit -m "feat: Footer UI improvements and export feature fixes

- Modern footer settings UI with sticky sidebar
- Enhanced export API error handling with ob_clean()
- Added export confirmation modal with detailed info
- Fixed adjustColorOpacity() function redeclaration
- Added PHPDoc annotations to FilePathConfig
- Created student_data_export_requests table migration
- Fixed student_id type (VARCHAR instead of INTEGER)"

# Push to GitHub
git push origin main
```

---

### ✅ Step 2: Railway Will Auto-Deploy

Railway is connected to your GitHub repo, so it will automatically:
1. ✅ Detect the push
2. ✅ Pull the latest code
3. ✅ Redeploy the application

**Wait 2-3 minutes for Railway deployment to complete.**

Check deployment status at: https://railway.app/project/[your-project]

---

### ✅ Step 3: Run Database Migrations

#### 🏠 **On Localhost:**

```powershell
# Connect to PostgreSQL
psql -U postgres -d educaid

# Run migration 1: Create table
\i "C:/xampp/htdocs/EducAid 2/EducAid/database/migrations/2025-11-12_create_student_data_export_table.sql"

# Run migration 2: Fix student_id type (if needed)
\i "C:/xampp/htdocs/EducAid 2/EducAid/database/migrations/2025-11-12_fix_student_id_type.sql"

# Verify table was created correctly
\d student_data_export_requests

# Exit psql
\q
```

#### ☁️ **On Railway:**

**Option A: Using Railway CLI**
```bash
# Install Railway CLI if not already installed
npm i -g @railway/cli

# Login
railway login

# Link to your project
railway link

# Connect to database
railway connect postgres

# Inside PostgreSQL prompt, run:
\i database/migrations/2025-11-12_create_student_data_export_table.sql
\i database/migrations/2025-11-12_fix_student_id_type.sql

# Verify
\d student_data_export_requests
\q
```

**Option B: Using Railway Dashboard**
1. Go to Railway Dashboard → Your Project → PostgreSQL Service
2. Click "Connect" → "psql"
3. Copy-paste the content of `2025-11-12_create_student_data_export_table.sql`
4. Press Enter to execute
5. Copy-paste the content of `2025-11-12_fix_student_id_type.sql`
6. Press Enter to execute
7. Verify: `SELECT * FROM information_schema.columns WHERE table_name = 'student_data_export_requests';`

---

### ✅ Step 4: Create Export Directories

#### 🏠 **On Localhost:**

```powershell
# Create data/exports directory
New-Item -ItemType Directory -Path "C:\xampp\htdocs\EducAid 2\EducAid\data\exports" -Force

# Verify
Test-Path "C:\xampp\htdocs\EducAid 2\EducAid\data\exports"
# Should return: True
```

#### ☁️ **On Railway:**

Railway should have `/mnt/assets/data/exports` from environment setup, but verify:

```bash
# Using Railway CLI
railway run bash

# Inside container:
mkdir -p /mnt/assets/data/exports
chmod 755 /mnt/assets/data
chmod 777 /mnt/assets/data/exports
ls -la /mnt/assets/data/

# Exit
exit
```

---

### ✅ Step 5: Enable ZIP Extension (Localhost Only)

**This step is ONLY for localhost. Railway already has ZIP enabled.**

#### Check if already enabled:
```powershell
php -m | Select-String -Pattern "zip"
```

If no output, enable it:

```powershell
# Open php.ini as Administrator
notepad "C:\xampp\php\php.ini"

# Find this line (Ctrl+F, search "extension=zip"):
;extension=zip

# Remove the semicolon:
extension=zip

# Save and close
```

**OR use automated script:**
```powershell
# Backup first
Copy-Item "C:\xampp\php\php.ini" "C:\xampp\php\php.ini.backup-$(Get-Date -Format 'yyyyMMdd-HHmmss')"

# Enable zip
(Get-Content "C:\xampp\php\php.ini") -replace ';extension=zip', 'extension=zip' | Set-Content "C:\xampp\php\php.ini"

# Verify change
Select-String -Path "C:\xampp\php\php.ini" -Pattern "^extension=zip"
```

**Restart Apache:**
- Open XAMPP Control Panel
- Click "Stop" next to Apache
- Wait 2 seconds
- Click "Start"

**Verify:**
```powershell
php -m | Select-String -Pattern "zip"
# Should output: zip
```

---

## 🧪 Testing & Verification

### Test Localhost (http://localhost/EducAid%202/EducAid/)

#### 1. Footer Settings UI
- [ ] Login as super_admin
- [ ] Navigate to: `/modules/admin/footer_settings.php`
- [ ] **Visual Check:**
  - [ ] Right sidebar sticks when scrolling down
  - [ ] Cards have hover effects (lift up slightly)
  - [ ] Color pickers are larger (56px × 42px)
  - [ ] Save button has gradient and shadow
  - [ ] Page has light background (#f8fafc)
- [ ] **Functionality Check:**
  - [ ] Change a color → Preview updates
  - [ ] Click Save → Success message appears
  - [ ] Refresh page → Changes persisted

#### 2. Export Feature
- [ ] Login as student
- [ ] Navigate to: `/modules/student/student_settings.php#privacy-data`
- [ ] **Modal Check:**
  - [ ] Click "Request Export" button
  - [ ] Modal appears with export details
  - [ ] Click "Confirm & Export"
  - [ ] Modal closes, status shows "Processing..."
- [ ] **API Check (F12 Console):**
  - [ ] No JavaScript errors
  - [ ] No "Unexpected end of JSON input" errors
  - [ ] Console shows "Export status data: {success: true, ...}"
- [ ] **Download Check:**
  - [ ] Wait 5-10 seconds
  - [ ] Status changes to "Status: ready"
  - [ ] "Download Export (ZIP)" button appears
  - [ ] Click download → ZIP file downloads
  - [ ] Open ZIP → Contains student data files

#### 3. No Errors
- [ ] Check PHP error log: `C:\xampp\php\logs\php_error_log`
  - [ ] No "Class 'ZipArchive' not found" errors
  - [ ] No "invalid input syntax for type integer" errors
  - [ ] No function redeclaration errors
- [ ] Check browser console (F12)
  - [ ] No red error messages
  - [ ] API calls return clean JSON

---

### Test Railway Production (https://educaid-production.up.railway.app/)

**Repeat the same tests as localhost:**

#### 1. Footer Settings UI
- [ ] Login as super_admin
- [ ] Navigate to: `/modules/admin/footer_settings.php`
- [ ] Verify sticky sidebar works
- [ ] Verify hover effects
- [ ] Test color changes and save

#### 2. Export Feature
- [ ] Login as student
- [ ] Navigate to: `/modules/student/student_settings.php#privacy-data`
- [ ] Test export modal
- [ ] Request export
- [ ] Download ZIP file

#### 3. Database Check
```sql
-- Connect to Railway PostgreSQL and run:
SELECT COUNT(*) FROM student_data_export_requests;
SELECT column_name, data_type FROM information_schema.columns 
WHERE table_name = 'student_data_export_requests';
```
- [ ] Table exists
- [ ] `student_id` is `character varying` (VARCHAR)
- [ ] All indexes created

---

## 🐛 Troubleshooting

### Issue: "Unexpected end of JSON input" on Export

**Cause:** PHP errors being output before JSON

**Fix:**
```powershell
# Check error log
Get-Content "C:\xampp\php\logs\php_error_log" -Tail 50

# Common fixes:
# 1. Ensure ob_clean() is called before json_encode()
# 2. Check ZipArchive is enabled: php -m | Select-String "zip"
# 3. Verify export directory exists and is writable
```

---

### Issue: "Class 'ZipArchive' not found"

**Localhost only:**
```powershell
# Verify zip enabled
php -m | Select-String -Pattern "zip"

# If not enabled, edit php.ini
notepad "C:\xampp\php\php.ini"
# Change: ;extension=zip → extension=zip
# Restart Apache
```

**Railway:** Should already be enabled. Check logs in Railway dashboard.

---

### Issue: "invalid input syntax for type integer"

**Cause:** student_id column is INTEGER instead of VARCHAR

**Fix:**
```sql
-- Run this migration again:
\i database/migrations/2025-11-12_fix_student_id_type.sql
```

---

### Issue: Footer sidebar not sticky

**Check:**
1. Inspect element on `.sticky-save` div
2. Should have: `position: sticky; top: 80px; z-index: 100;`
3. Clear browser cache (Ctrl+Shift+Delete)
4. Try in incognito mode

---

### Issue: Railway deployment failed

**Check Railway logs:**
1. Go to Railway Dashboard
2. Click on your service
3. Check "Deployments" tab
4. View logs for errors

**Common fixes:**
- Ensure all files committed and pushed
- Check for PHP syntax errors: `php -l filename.php`
- Verify composer.json is valid

---

## 📊 Deployment Checklist

### Pre-Deployment
- [x] All code changes tested locally
- [x] No PHP syntax errors
- [x] No JavaScript console errors
- [x] Git repository is clean

### Deployment
- [ ] Code committed to Git
- [ ] Pushed to GitHub main branch
- [ ] Railway auto-deployment completed
- [ ] Database migrations run on localhost
- [ ] Database migrations run on Railway
- [ ] Export directory created (localhost)
- [ ] ZIP extension enabled (localhost)
- [ ] Apache restarted (localhost)

### Post-Deployment
- [ ] Localhost tests passed
- [ ] Railway tests passed
- [ ] No errors in logs
- [ ] Export feature working
- [ ] Footer UI working
- [ ] User acceptance testing

---

## 🎉 Success Criteria

✅ **Localhost:**
- Footer settings page has modern UI with sticky sidebar
- Export modal appears with detailed information
- Export generates and downloads ZIP file
- No PHP or JavaScript errors

✅ **Railway:**
- All localhost features work on production
- Database table created correctly
- Export directory accessible
- No deployment errors

---

## 📝 Rollback Plan (If Needed)

If something goes wrong:

### Rollback Code:
```powershell
# Revert to previous commit
git log --oneline  # Find previous commit hash
git revert HEAD    # Or: git reset --hard [previous-commit-hash]
git push origin main
```

### Rollback Database:
```sql
-- Remove export table if causing issues
DROP TABLE IF EXISTS student_data_export_requests CASCADE;
```

---

## 🔄 Version Info

- **Deployment Date:** November 12, 2025
- **Version:** 2.0.0
- **Branch:** main
- **Migration Files:** 2 SQL files in `database/migrations/`
- **Affected Files:** 7 PHP files, 2 SQL files

---

## 📞 Need Help?

If you encounter any issues:

1. **Check this guide first** - Most issues covered in Troubleshooting section
2. **Check logs:**
   - Localhost: `C:\xampp\php\logs\php_error_log`
   - Railway: Dashboard → Service → Logs
3. **Verify each step completed** - Use the checklists above
4. **Check browser console** - Press F12 in browser

---

**🎯 Once all steps complete and tests pass, both environments will be synchronized and fully updated!**
