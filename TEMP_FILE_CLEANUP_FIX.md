# Temp File Cleanup Issue - FIXED

## Problem Identified

Student documents uploaded during registration were being **deleted automatically** before admin could review them.

### Root Cause

In `modules/student/student_register.php`, the `cleanupSessionFiles()` function was being called:
1. **Immediately on page load** (line 8095)
2. **Every 5 minutes** via setInterval (line 8098)

The cleanup logic was too aggressive:
```php
// OLD BUGGY LOGIC:
if ($isCurrentSessionFile || $fileAge > 1800) {  // 30 minutes
    @unlink($file);  // DELETED TOO EARLY!
}
```

This meant files were deleted if they were either:
- From "current session" (problematic - session tracking not reliable)
- Older than 30 minutes (way too short - admin needs time to review!)

## Impact

- Students could upload documents successfully during registration
- Documents would be stored in `assets/uploads/temp/`
- But before admin could review, files were automatically deleted
- Result: Admin sees "0 documents" for new registrants

## Solution Implemented

### 1. Fixed Immediate Cleanup Logic (student_register.php lines 107-138)

Changed the cleanup to be much more conservative:

```php
// NEW SAFE LOGIC:
$isActiveSession = (time() - $sessionStartTime) < 1800;

if ($isCurrentSessionFile && $isActiveSession) {
    @unlink($file);  // Only delete during ACTIVE registration session
}
```

Now files are ONLY deleted if:
- File belongs to current session prefix AND
- Session started less than 30 minutes ago (active registration in progress)

This means **completed registrations are preserved** for admin review!

### 2. Created Separate Maintenance Script

`cleanup_orphaned_temp_files.php` - Run this daily via cron job

This script intelligently cleans up:
- ✓ Files older than 7 days (always)
- ✓ Files older than 48 hours with no student ID pattern
- ✓ Files older than 24 hours where student status is no longer "under_registration" or "applicant"
- ✓ Files older than 24 hours with no matching student record

But it KEEPS:
- ✗ Files from pending registrations (status = "under_registration" or "applicant")
- ✗ Files less than 24 hours old
- ✗ .gitkeep files

## File Lifecycle

```
Student Registration
    ↓
Upload documents → assets/uploads/temp/{doc_type}/
    ↓
Files remain in temp (PROTECTED from auto-deletion)
    ↓
Admin reviews registration
    ↓
    ├─ APPROVED → Files moved to assets/uploads/student/{doc_type}/{student_id}/
    │               Temp files deleted after successful move
    │
    └─ REJECTED → Temp files can be deleted (status changed)
```

## Testing

To verify the fix works:

1. Register a new student with documents
2. Check temp folders - documents should be there:
   ```bash
   ls -la assets/uploads/temp/*/
   ```

3. Wait 1 hour (or any time)
4. Check temp folders again - documents should STILL be there

5. Run cleanup script manually:
   ```bash
   php cleanup_orphaned_temp_files.php
   ```

6. Documents should remain (student is pending review)

7. After admin approval, documents move to permanent storage and temp files are cleaned

## Cron Job Setup

Add to crontab to run daily at 2 AM:

```bash
0 2 * * * cd /path/to/EducAid && php cleanup_orphaned_temp_files.php >> logs/temp_cleanup.log 2>&1
```

Or for Railway/production:
```bash
# Run daily cleanup
0 2 * * * php /app/cleanup_orphaned_temp_files.php
```

## Files Modified

1. `modules/student/student_register.php` (lines 107-138)
   - Changed cleanup logic to preserve completed registrations

2. `cleanup_orphaned_temp_files.php` (NEW)
   - Intelligent maintenance script for truly orphaned files

## Related Issue

Student `GENERALTRIAS-2025-3-ZKXB6B` lost their uploaded documents due to this bug.
- Documents were uploaded successfully
- Auto-cleanup deleted them before admin review
- Database shows 0 documents for this student

**Recommendation:** Have the student re-register with this fix in place.

## Prevention

- Temp files now protected until admin action
- Separate maintenance script prevents accumulation
- Logging added to track cleanup operations

## Date Fixed
November 7, 2025
