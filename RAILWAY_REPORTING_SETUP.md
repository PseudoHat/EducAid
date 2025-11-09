# 📋 EducAid Reporting System - Railway Deployment Guide

## 🚀 Deployment Steps for Railway

### 1. **Install Composer Dependencies Locally (First Time)**

On your local machine (Windows with XAMPP):

```powershell
cd C:\xampp\htdocs\EducAid
composer install
```

This will:
- Download TCPDF (PDF generation)
- Download PhpSpreadsheet (Excel generation)
- Create `vendor/` folder with all dependencies
- Generate `composer.lock` file

### 2. **Commit Changes to Git**

```powershell
git add composer.json composer.lock
git add includes/report_filters.php
git add includes/report_generator.php
git add modules/admin/reports.php
git add api/reports/generate_report.php
git add assets/css/admin/reports.css
git add assets/js/admin/reports.js
git commit -m "Add comprehensive reporting system with PDF/Excel export"
git push origin main
```

### 3. **Railway Configuration**

Railway will automatically detect `composer.json` and run `composer install` during deployment.

#### **Verify Railway Build Settings:**

In your Railway project dashboard:

1. Go to **Settings** → **Deploy**
2. Ensure **Build Command** includes:
   ```bash
   composer install --no-dev --optimize-autoloader
   ```
3. **Start Command** should be (if using Apache/Nixpacks):
   ```bash
   apache2-foreground
   ```

#### **Required PHP Extensions (Already in Railway by default):**

Railway's PHP buildpack includes these extensions automatically:
- ✅ `ext-gd` - Image processing for TCPDF
- ✅ `ext-zip` - ZIP compression for Excel files
- ✅ `ext-pgsql` - PostgreSQL database
- ✅ `ext-mbstring` - Multi-byte string handling
- ✅ `ext-xml` - XML parsing (required by PhpSpreadsheet)

### 4. **Environment Variables (Already Set)**

No new environment variables needed! The system uses your existing:
- `DATABASE_PUBLIC_URL` or `DB_*` variables for database connection
- Existing session and admin authentication

### 5. **File Permissions (Railway handles automatically)**

Railway automatically sets correct permissions for:
- `vendor/` directory (read-only after build)
- Temporary file storage (writable)

### 6. **Deployment Verification**

After pushing to Railway:

1. **Check Build Logs:**
   - Go to Railway Dashboard → Deployments
   - Click on latest deployment
   - Look for: `Installing dependencies from lock file`
   - Should see: `Package operations: X installs, 0 updates, 0 removals`

2. **Verify Installation:**
   - SSH into Railway container (optional):
     ```bash
     railway shell
     php -m | grep -E 'gd|zip|pgsql'
     ```
   - Should show all three extensions enabled

3. **Test the Reporting Page:**
   - Navigate to: `https://your-app.railway.app/modules/admin/reports.php`
   - Login as admin
   - Select filters and click "Preview Report"
   - Try exporting PDF and Excel

### 7. **Troubleshooting**

#### **Issue: "Class 'TCPDF' not found"**

**Solution:**
```bash
# Railway console
composer dump-autoload --optimize
```

#### **Issue: "Call to undefined function imagecreate()"**

**Solution:** Add to Railway environment variables:
```
PHP_EXTENSIONS=gd,zip,pgsql,pdo_pgsql,mbstring
```

#### **Issue: Memory limit exceeded for large reports**

**Solution:** Add to Railway environment variables:
```
PHP_MEMORY_LIMIT=512M
```

#### **Issue: PDF generation timeout**

**Solution:** Add to Railway environment variables:
```
PHP_MAX_EXECUTION_TIME=120
```

### 8. **Performance Optimization**

#### **For Large Datasets (1000+ students):**

Add chunking to `includes/report_generator.php`:

```php
// For Excel export, process in batches
$offset = 0;
$batchSize = 500;
while ($batch = pg_fetch_all(pg_query_params($connection, $query . " LIMIT $batchSize OFFSET $offset", $params))) {
    // Process batch
    $offset += $batchSize;
}
```

#### **Enable OPcache (Railway default):**

Railway automatically enables OPcache for production PHP. Verify:

```bash
railway shell
php -i | grep opcache
```

### 9. **Monitoring & Logs**

#### **View Report Generation Logs:**

```bash
# Railway console
railway logs --filter "report"
```

#### **Database Query Performance:**

Check `audit_logs` table for report generation metrics:

```sql
SELECT 
    action_description,
    metadata->>'result_count' as records,
    created_at
FROM audit_logs 
WHERE event_category = 'reporting' 
ORDER BY created_at DESC 
LIMIT 20;
```

## 📊 **Report Features Available**

✅ **Filter by:**
- Student Status (Active, Applicant, Archived)
- Gender (Male, Female)
- Municipality (Super Admin only)
- Barangay (Multi-select)
- University (Multi-select)
- Year Level (Multi-select)
- Academic Year
- Distribution Cycle
- Registration Date Range
- Confidence Score Range

✅ **Export Options:**
- **PDF Report** - Professional formatted with municipality logo
- **Excel Spreadsheet** - With statistics sheet and auto-formatted columns
- **Preview** - Live preview with up to 50 records

✅ **Statistics Dashboard:**
- Total students count
- Gender breakdown with percentages
- Average GWA and confidence scores
- Coverage (municipalities, barangays, universities)

## 🔒 **Security Features**

- ✅ CSRF token protection on all exports
- ✅ Admin authentication required
- ✅ Role-based municipality filtering
- ✅ Parameterized SQL queries (SQL injection prevention)
- ✅ HTML escaping in previews (XSS prevention)
- ✅ Audit logging for all report generations

## 📝 **Usage Example**

### **Generate a report for all female students in a specific barangay:**

1. Go to `modules/admin/reports.php`
2. Select:
   - Gender: Female
   - Barangay: [Select target barangay]
3. Click "Preview Report"
4. Review statistics and preview
5. Click "Export PDF" or "Export Excel"

### **Generate distribution attendance report:**

1. Select:
   - Distribution: [Select distribution cycle]
   - Status: Active
2. Click "Preview Report"
3. Click "Export Excel" for detailed spreadsheet

## 🎉 **You're All Set!**

The reporting system is now fully integrated and will work seamlessly on Railway with automatic dependency installation during deployment.

**Next Steps:**
- Access reports at: `https://your-app.railway.app/modules/admin/reports.php`
- Add link to admin navigation menu
- Train staff on using filters and exports
- Monitor audit logs for usage patterns

**Support:**
- Check Railway logs for any deployment issues
- Review `audit_logs` table for report generation history
- Use browser console (F12) for JavaScript debugging
