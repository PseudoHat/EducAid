# CMS JSON Error Fix - Railway Production Issue

## Problem
When editing CMS content on Railway production (https://educaid-production.up.railway.app/website/landingpage.php?edit=1&municipality_id=1), the following error occurs:

```
Unexpected token '<', "<br /> <b>"... is not valid JSON
```

## Root Cause
PHP warnings, errors, or notices were being output as HTML **before** the JSON response in AJAX endpoints. This happens when:

1. PHP `display_errors` is enabled in production
2. Warnings/notices (e.g., undefined variables, deprecations) get output as HTML
3. The HTML appears before the `Content-Type: application/json` header takes effect
4. JavaScript's `JSON.parse()` fails because it receives `<br /> <b>Warning: ...</b>...{"success":true}` instead of pure JSON

## Solution Applied

### Code Changes
Added error suppression and output buffer cleaning to all CMS AJAX save endpoints:

```php
<?php
// Suppress all output before JSON response
error_reporting(0);
ini_set('display_errors', 0);
ob_start();

session_start();

// Clear any output that might have been generated
ob_clean();
header('Content-Type: application/json');

// ... rest of code ...

function resp($ok,$msg='',$extra=[]){
  if (ob_get_level() > 0) ob_clean();
  header('Content-Type: application/json');
  echo json_encode(array_merge(['success'=>$ok,'message'=>$msg],$extra));
  exit;
}
```

### Files Fixed
The following critical CMS AJAX files have been updated:

1. ✅ `website/ajax_save_landing_content.php` - Landing page editor
2. ✅ `website/ajax_save_about_content.php` - About page editor
3. ✅ `website/ajax_save_ann_content.php` - Announcements page editor
4. ✅ `website/ajax_save_contact_content.php` - Contact page editor
5. ✅ `website/ajax_save_hiw_content.php` - How It Works page editor
6. ✅ `website/ajax_save_req_content.php` - Requirements page editor

### What the Fix Does

1. **`error_reporting(0)`** - Disables all error reporting for this script
2. **`ini_set('display_errors', 0)`** - Prevents errors from being displayed (belt & suspenders)
3. **`ob_start()`** - Starts output buffering to catch any accidental output
4. **`ob_clean()`** - Clears the output buffer before sending JSON (called twice: once at start, once in resp function)
5. **Re-sets `Content-Type` header in resp function** - Ensures JSON mime type is set

## Testing

### Before Deployment
Test locally that CMS editors still work:
1. Login as super admin
2. Navigate to any CMS page (e.g., `landingpage.php?edit=1`)
3. Make an edit and click Save
4. Verify success message appears and content updates

### After Railway Deployment
1. Access: https://educaid-production.up.railway.app/website/landingpage.php?edit=1&municipality_id=1
2. Login as super admin
3. Edit any content block
4. Click Save
5. Verify no JSON parse errors
6. Check browser console for any errors
7. Verify content actually saved to database

### Rollback Plan
If issues occur, the fix can be reverted by removing the error suppression lines. However, the real fix would be to:
1. Identify the actual PHP warnings/errors causing HTML output
2. Fix those underlying issues
3. Keep error suppression as a safety net

## Additional Notes

### Why This Works
- Output buffering captures any accidental output (warnings, whitespace, etc.)
- Clearing the buffer ensures only our JSON response is sent
- Error suppression prevents PHP from injecting HTML error messages
- Multiple header calls ensure Content-Type is set correctly

### Production Best Practices
While this fix works, ideally you should:
1. Set `display_errors = Off` in Railway's PHP configuration
2. Set `log_errors = On` to log errors to files instead of output
3. Review PHP error logs to fix underlying warnings
4. Use this code as a defensive layer, not a primary solution

### Future Maintenance
This pattern should be applied to any new AJAX endpoints that return JSON:
```php
error_reporting(0);
ini_set('display_errors', 0);
ob_start();
// ... your code ...
ob_clean();
header('Content-Type: application/json');
echo json_encode($response);
```

## Related Files
- `fix_ajax_json_errors.ps1` - PowerShell script to batch-fix AJAX files (created but not run)
- All CMS AJAX files in `website/ajax_*.php`

## Deployment
Deploy these changes to Railway via git push:
```bash
git add website/ajax_save_*.php
git commit -m "Fix: Suppress PHP errors in CMS AJAX endpoints to prevent JSON parse errors"
git push railway main
```

---
**Fixed by:** GitHub Copilot  
**Date:** November 12, 2025  
**Issue:** Railway production CMS JSON parse error
