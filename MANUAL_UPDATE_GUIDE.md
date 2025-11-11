# Manual Update Guide - Footer Settings UI & Export Feature Fixes

## 📋 Changes Summary

This guide covers how to manually apply changes to **both Railway (production)** and **localhost** environments.

### Changes Made:
1. **Footer Settings UI** - Modern design with sticky sidebar
2. **Export Feature Fixes** - API error handling, database fixes, ZIP extension
3. **Function Redeclaration Fix** - `adjustColorOpacity()` in sidebars
4. **FilePathConfig** - PHPDoc annotations and directory fix

---

## 🚀 Updates to Apply

### 1️⃣ Footer Settings UI (`modules/admin/footer_settings.php`)

**What changed:** Complete CSS overhaul for modern UI and sticky sidebar functionality.

**How to update:**
1. Open the file on Railway/localhost
2. Locate the `<style>` section (starts around line 86)
3. Replace **ALL CSS** from line 86 to approximately line 283 with the new styling
4. The new CSS includes:
   - `.sticky-save` with `position: sticky; top: 80px;`
   - Enhanced card hover effects
   - Improved form controls and color pickers
   - Custom scrollbar for sticky sidebar
   - Responsive breakpoints

**Key Features:**
- Sticky right sidebar (stays visible when scrolling)
- Modern gradients and shadows
- Better button styling
- Improved spacing and typography

---

### 2️⃣ Export API Error Handling

#### File 1: `api/student/export_status.php`

**Changes:**
- Added `ob_start()` and `ob_clean()` for clean JSON output
- Added table existence check before querying
- Enhanced error handling with `pg_last_error()`
- Added try-catch for database connection

**Critical lines to add:**
```php
// At top of file (line 2-4):
error_reporting(E_ALL);
ini_set('display_errors', 0);
ob_start();

// Before any JSON output (multiple places):
ob_clean();

// Table existence check (after $studentId assignment):
$tableCheck = pg_query($connection, "SELECT to_regclass('public.student_data_export_requests') IS NOT NULL as exists");
if (!$tableCheck) {
    ob_clean();
    echo json_encode(['success' => true, 'exists' => false, 'error' => 'Export feature not available']);
    exit;
}
$tableExists = pg_fetch_assoc($tableCheck)['exists'] === 't';
if (!$tableExists) {
    ob_clean();
    echo json_encode(['success' => true, 'exists' => false, 'error' => 'Export table not found. Please contact administrator.']);
    exit;
}
```

#### File 2: `api/student/request_data_export.php`

**Changes:**
- Added `ob_start()` and `ob_clean()`
- Wrapped service calls in try-catch block
- Enhanced error messages

**Critical addition:**
```php
// Wrap DataExportService call (line 54+):
try {
    $service = new DataExportService($connection);
    $result = $service->buildExport($studentId);
    
    // ... existing code ...
    
    ob_clean(); // Before final JSON output
    echo json_encode([...]);
    
} catch (Exception $e) {
    pg_query_params($connection, "UPDATE student_data_export_requests SET status='failed', processed_at=NOW(), error_message=$2 WHERE request_id = $1", [$requestId, $e->getMessage()]);
    ob_clean();
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Export exception: ' . $e->getMessage()]);
    exit;
}
```

---

### 3️⃣ Function Redeclaration Fix (Both Sidebars)

#### Files:
- `includes/admin/admin_sidebar.php`
- `includes/student/student_sidebar.php`

**Change (around line 665 for admin, line 157 for student):**

**Before:**
```php
function adjustColorOpacity($color, $opacity = 0.3) {
    // ... function body ...
}
```

**After:**
```php
if (!function_exists('adjustColorOpacity')) {
    function adjustColorOpacity($color, $opacity = 0.3) {
        // ... function body ...
    }
}
```

---

### 4️⃣ FilePathConfig PHPDoc & Directory Fix (`config/FilePathConfig.php`)

**Changes:**
1. **getDataExportsPath()** - Fixed directory level (line 218):

**Before:**
```php
return dirname(dirname(__DIR__)) . DIRECTORY_SEPARATOR . 'data' . DIRECTORY_SEPARATOR . 'exports';
```

**After:**
```php
return dirname(__DIR__) . DIRECTORY_SEPARATOR . 'data' . DIRECTORY_SEPARATOR . 'exports';
```

2. **Added PHPDoc comments** for these methods:
   - `resolveRelativePath()` - Added `@param` and `@return`
   - `getRelativePath()` - Added `@param` and `@return`
   - `buildPath()` - Added `@param` and `@return`, plus empty check
   - `findExistingFolder()` - Added default parameters
   - `getAllDocumentFolders()` - Added default parameter and return type doc

---

### 5️⃣ Export Confirmation Modal (`modules/student/student_settings.php`)

**Add this modal HTML** (after line 1647, before closing `</section>`):

```html
<!-- Export Data Confirmation Modal -->
<div class="modal fade" id="exportConfirmModal" tabindex="-1" data-bs-backdrop="static" data-bs-keyboard="false">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">
          <i class="bi bi-cloud-arrow-down me-2"></i>Request Data Export
        </h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <div class="alert alert-info">
          <i class="bi bi-info-circle me-2"></i>
          <strong>What data will be exported?</strong>
        </div>
        
        <p class="mb-3">Your export will include:</p>
        <ul class="mb-3">
          <li><i class="bi bi-check-circle text-success me-2"></i>Personal information (name, contact details)</li>
          <li><i class="bi bi-check-circle text-success me-2"></i>Application status and history</li>
          <li><i class="bi bi-check-circle text-success me-2"></i>Uploaded documents</li>
          <li><i class="bi bi-check-circle text-success me-2"></i>Communication records</li>
          <li><i class="bi bi-check-circle text-success me-2"></i>Account activity logs</li>
        </ul>

        <div class="alert alert-warning">
          <i class="bi bi-clock-history me-2"></i>
          <strong>Processing time:</strong> Your export will be prepared in the background. 
          This may take a few minutes depending on the amount of data.
        </div>

        <div class="alert alert-success">
          <i class="bi bi-shield-check me-2"></i>
          <strong>Security:</strong> Your data export will be securely encrypted and will 
          expire after 7 days for your protection.
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
          <i class="bi bi-x-circle me-2"></i>Cancel
        </button>
        <button type="button" id="confirmExportBtn" class="btn btn-primary">
          <i class="bi bi-cloud-arrow-down me-2"></i>Confirm & Export
        </button>
      </div>
    </div>
  </div>
</div>
```

**Update JavaScript** (around line 1812, in the export request section):

Replace the `btn.addEventListener('click', async () => {` section with modal-based code:

```javascript
const exportModalEl = document.getElementById('exportConfirmModal');
const confirmBtn = document.getElementById('confirmExportBtn');

if (!exportModalEl || !confirmBtn) {
  console.error('Export modal or confirm button not found');
  return;
}

const exportModal = new bootstrap.Modal(exportModalEl);

// ... keep fetchStatus() function as is ...

async function processExport() {
  confirmBtn.disabled = true; 
  const original = confirmBtn.innerHTML; 
  confirmBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Processing…';
  
  try {
    const res = await fetch('../../api/student/request_data_export.php', { method: 'POST', credentials: 'include' });
    const data = await res.json();
    if (!data.success) { 
      statusEl.textContent = 'Export request failed.'; 
    } else {
      statusEl.textContent = 'Export request submitted successfully. Processing...';
    }
    await fetchStatus();
    exportModal.hide();
  } catch (e) {
    console.error('Export request error:', e);
    statusEl.textContent = 'Export request failed: ' + e.message;
  } finally { 
    confirmBtn.disabled = false; 
    confirmBtn.innerHTML = original; 
  }
}

// Open modal when "Request Export" is clicked
btn.addEventListener('click', () => {
  console.log('Request Export clicked, opening modal...');
  exportModal.show();
});

// Handle confirmation in modal
confirmBtn.addEventListener('click', processExport);
```

---

## 🗄️ Database Updates

### Create Export Table (Run on both environments)

**Option 1: Use provided script**
```bash
php check_and_create_export_table.php
```

**Option 2: Run SQL directly**
```sql
CREATE TABLE IF NOT EXISTS public.student_data_export_requests (
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
```

**⚠️ IMPORTANT:** Make sure `student_id` is **VARCHAR(255)**, NOT INTEGER!

If already created with wrong type, run:
```bash
php fix_export_table_student_id_type.php
```

---

## 📁 Directory Creation

### Create Export Directory

**Localhost:**
```powershell
New-Item -ItemType Directory -Path "C:\xampp\htdocs\EducAid 2\EducAid\data\exports" -Force
```

**Railway:**
```bash
mkdir -p /mnt/assets/data/exports
```

**Set permissions (Railway):**
```bash
chmod 755 /mnt/assets/data
chmod 777 /mnt/assets/data/exports
```

---

## 🔧 PHP Configuration (Localhost Only)

### Enable ZIP Extension in php.ini

1. Open `C:\xampp\php\php.ini`
2. Find `;extension=zip`
3. Remove semicolon: `extension=zip`
4. Save file
5. **Restart Apache in XAMPP**

**Verify:**
```powershell
php -m | Select-String -Pattern "zip"
```
Should output: `zip`

---

## ✅ Testing Checklist

### After applying all changes:

#### Footer Settings Page
- [ ] Navigate to `/modules/admin/footer_settings.php`
- [ ] Right sidebar should stick when scrolling
- [ ] Cards have hover effects
- [ ] Color pickers are larger and interactive
- [ ] Save button has gradient and shadow

#### Export Feature
- [ ] Navigate to student settings → Privacy & Data tab
- [ ] Click "Request Export" → Modal appears
- [ ] Click "Confirm & Export" → Shows "Processing..."
- [ ] Status updates to "ready" after processing
- [ ] Download link appears when ready
- [ ] ZIP file downloads successfully

#### Console Checks
- [ ] No JavaScript errors in browser console (F12)
- [ ] No PHP errors in error logs
- [ ] API returns clean JSON (no HTML mixed in)

---

## 🐛 Troubleshooting

### Export Returns "Unexpected end of JSON input"
- **Check:** PHP error log for fatal errors
- **Fix:** Ensure ZIP extension is enabled (localhost only)
- **Fix:** Verify `ob_clean()` added to API files

### "Invalid input syntax for type integer"
- **Check:** Database column type for student_id
- **Fix:** Run `fix_export_table_student_id_type.php`

### "Class 'ZipArchive' not found"
- **Environment:** Localhost only
- **Fix:** Enable php_zip extension in php.ini, restart Apache

### Sticky sidebar not working
- **Check:** `.sticky-save` class has `position: sticky; top: 80px;`
- **Check:** CSS properly applied (inspect element)
- **Note:** On mobile (<992px), sticky behavior is disabled

### "Export table not found"
- **Fix:** Run `check_and_create_export_table.php`
- **Or:** Execute SQL CREATE TABLE statement manually

---

## 📝 Files Modified Summary

| File | Changes |
|------|---------|
| `modules/admin/footer_settings.php` | Complete CSS overhaul, sticky sidebar |
| `modules/student/student_settings.php` | Export confirmation modal, enhanced JS |
| `api/student/export_status.php` | Error handling, table checks, ob_clean |
| `api/student/request_data_export.php` | Try-catch, error handling, ob_clean |
| `config/FilePathConfig.php` | PHPDoc annotations, directory fix |
| `includes/admin/admin_sidebar.php` | Function exists check |
| `includes/student/student_sidebar.php` | Function exists check |

---

## 🎯 Deployment Order

### For Railway (Production):
1. Apply database changes first (create table)
2. Create `/mnt/assets/data/exports` directory
3. Update PHP files via Git push or direct edit
4. Test export feature
5. Test footer settings UI

### For Localhost:
1. Apply database changes first
2. Create `data/exports` directory
3. Enable ZIP extension in php.ini
4. Restart Apache
5. Update PHP files
6. Test all features

---

## ✨ Benefits of These Changes

1. **Better UX** - Sticky sidebar keeps instructions visible
2. **Modern Design** - Gradients, shadows, smooth animations
3. **Reliable Export** - Proper error handling, no HTML in JSON
4. **No Crashes** - Function redeclaration fixed
5. **Type Safety** - VARCHAR student_id handles format correctly
6. **Clear Feedback** - Export modal explains what happens

---

## 📞 Support

If you encounter issues:
1. Check PHP error logs: `C:\xampp\php\logs\php_error_log`
2. Check browser console (F12)
3. Verify all files updated correctly
4. Ensure database table created with correct schema
5. Confirm ZIP extension enabled (localhost)

---

**Last Updated:** November 12, 2025
**Version:** 1.0
**Tested On:** XAMPP 8.x, PostgreSQL 14+, Railway Production
