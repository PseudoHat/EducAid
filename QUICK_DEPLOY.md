# 🚀 Quick Deployment Reference Card

## One-Command Deployment (Copy & Paste)

### 📤 Push to Both Environments

```powershell
# 1. Commit all changes
cd "C:\xampp\htdocs\EducAid 2\EducAid"
git add .
git commit -m "feat: Footer UI improvements and export feature fixes"
git push origin main
```

Railway will auto-deploy! Wait 2-3 minutes. ⏱️

---

### 🗄️ Database Setup

#### **Localhost PostgreSQL:**
```powershell
psql -U postgres -d educaid -f "C:/xampp/htdocs/EducAid 2/EducAid/database/migrations/2025-11-12_create_student_data_export_table.sql"
psql -U postgres -d educaid -f "C:/xampp/htdocs/EducAid 2/EducAid/database/migrations/2025-11-12_fix_student_id_type.sql"
```

#### **Railway PostgreSQL:**
```bash
# Option 1: Railway CLI
railway connect postgres
# Then inside psql:
\i database/migrations/2025-11-12_create_student_data_export_table.sql
\i database/migrations/2025-11-12_fix_student_id_type.sql
```

```sql
-- Option 2: Railway Dashboard (Copy-paste this)
DO $$ 
BEGIN
    IF NOT EXISTS (SELECT FROM pg_tables WHERE schemaname = 'public' AND tablename = 'student_data_export_requests') THEN
        CREATE TABLE public.student_data_export_requests (
            request_id SERIAL PRIMARY KEY,
            student_id VARCHAR(255) NOT NULL,
            status VARCHAR(50) NOT NULL DEFAULT 'pending',
            requested_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            processed_at TIMESTAMP,
            expires_at TIMESTAMP,
            download_token VARCHAR(255),
            file_path TEXT,
            file_size_bytes BIGINT,
            requested_by_ip VARCHAR(100),
            user_agent TEXT,
            error_message TEXT
        );
        CREATE INDEX idx_export_requests_student ON public.student_data_export_requests(student_id);
        CREATE INDEX idx_export_requests_status ON public.student_data_export_requests(status);
        CREATE INDEX idx_export_requests_requested_at ON public.student_data_export_requests(requested_at DESC);
        RAISE NOTICE 'Table created successfully';
    END IF;
END $$;
```

---

### 📁 Create Export Directory

#### **Localhost:**
```powershell
New-Item -ItemType Directory -Path "C:\xampp\htdocs\EducAid 2\EducAid\data\exports" -Force
```

#### **Railway:**
```bash
railway run bash
mkdir -p /mnt/assets/data/exports
chmod 777 /mnt/assets/data/exports
exit
```

---

### 🔧 Enable ZIP (Localhost Only)

```powershell
# Quick enable
(Get-Content "C:\xampp\php\php.ini") -replace ';extension=zip', 'extension=zip' | Set-Content "C:\xampp\php\php.ini"

# Verify
php -m | Select-String -Pattern "zip"
```

**Then restart Apache in XAMPP Control Panel!**

---

### ✅ Verify Everything Works

```powershell
# Localhost
psql -U postgres -d educaid -f "C:/xampp/htdocs/EducAid 2/EducAid/database/verify_export_setup.sql"
```

```bash
# Railway
railway connect postgres
\i database/verify_export_setup.sql
```

---

## 🧪 Quick Test

1. **Footer Settings:** `/modules/admin/footer_settings.php`
   - ✅ Sidebar sticks when scrolling
   - ✅ Colors change preview in real-time

2. **Export Feature:** `/modules/student/student_settings.php#privacy-data`
   - ✅ Click "Request Export" → Modal appears
   - ✅ Click "Confirm & Export" → Status shows "Processing..."
   - ✅ After 5-10 seconds → "Download Export" button appears
   - ✅ Click download → ZIP file downloads

---

## 🐛 Common Issues

| Error | Fix |
|-------|-----|
| "Unexpected end of JSON" | Check ZIP enabled: `php -m \| Select-String "zip"` |
| "Class 'ZipArchive' not found" | Enable zip in php.ini, restart Apache |
| "invalid input syntax for integer" | Run fix migration: `fix_student_id_type.sql` |
| "Table not found" | Run create migration: `create_student_data_export_table.sql` |

---

## 📋 Files Changed (7 files)

1. ✅ `modules/admin/footer_settings.php` - Modern UI CSS
2. ✅ `modules/student/student_settings.php` - Export modal
3. ✅ `api/student/export_status.php` - Error handling
4. ✅ `api/student/request_data_export.php` - Try-catch blocks
5. ✅ `config/FilePathConfig.php` - PHPDoc + directory fix
6. ✅ `includes/admin/admin_sidebar.php` - Function exists check
7. ✅ `includes/student/student_sidebar.php` - Function exists check

---

## ⏱️ Estimated Time

- **Git Push:** 1 minute
- **Railway Deploy:** 2-3 minutes (automatic)
- **Database Setup:** 2 minutes per environment
- **Directory Creation:** 30 seconds per environment
- **ZIP Enable (localhost):** 1 minute
- **Testing:** 5 minutes

**Total:** ~15-20 minutes for complete deployment to both environments

---

## 🎯 Success = All Green Checkmarks!

- ✅ Code pushed to GitHub
- ✅ Railway auto-deployed
- ✅ Database table created (both)
- ✅ Export directory created (both)
- ✅ ZIP enabled (localhost)
- ✅ Tests pass (both)

**Done! Both environments synchronized! 🎉**
