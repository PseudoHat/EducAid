# CSRF Token Consumption Fix - Complete List

## ✅ Fixed (Already Updated)
1. `includes/CSRFProtection.php` - Increased token buffer from 5 to 10
2. `website/ajax_reset_about_content.php` - Added `, false` parameter
3. `website/ajax_reset_landing_content.php` - Added `, false` parameter
4. `website/ajax_reset_hiw_content.php` - Added `, false` parameter
5. `website/ajax_reset_req_content.php` - Added `, false` parameter

## 🔄 Still Need Fixing

### READ-ONLY Operations (should NOT consume tokens)
All history and rollback operations should use `, false` to not consume tokens:

```php
// Change from:
CSRFProtection::validateToken('cms_content', $token)

// To:
CSRFProtection::validateToken('cms_content', $token, false)
```

**Files to update:**
- `website/ajax_get_about_history.php` - Line 12
- `website/ajax_get_hiw_history.php` - Line 14
- `website/ajax_get_req_history.php` - Line 14
- `website/ajax_get_landing_history.php` - Line 22
- `website/ajax_get_contact_history.php` - Line 27
- `website/ajax_rollback_about_block.php` - Line 12
- `website/ajax_rollback_hiw_block.php` - Line 14
- `website/ajax_rollback_req_block.php` - Line 14
- `website/ajax_rollback_landing_block.php` - Line 15
- `website/ajax_rollback_contact_block.php` - Line 24

### WRITE Operations (CAN consume tokens, but optional)
Save operations can consume tokens, but it's better to not consume for better UX:

**Files to consider:**
- `website/ajax_save_about_content.php` - Line 15
- `website/ajax_save_hiw_content.php` - Line 13
- `website/ajax_save_req_content.php` - Line 13
- `website/ajax_save_landing_content.php` - Line 21
- `website/ajax_save_contact_content.php` - Line 26

## 📝 Quick Fix Command

For all history files:
```bash
find website/ajax_get_*_history.php -exec sed -i "s/validateToken('cms_content', \$token)/validateToken('cms_content', \$token, false)/g" {} \;
```

For all rollback files:
```bash
find website/ajax_rollback_*_block.php -exec sed -i "s/validateToken('cms_content', \$token)/validateToken('cms_content', \$token, false)/g" {} \;
```

## 🎯 Why This Fix Works

**Problem:** 
- Token consumed on first click
- Same token still in meta tag
- Second click fails validation

**Solution:**
- Don't consume tokens for read operations (`, false` parameter)
- Token stays valid for multiple operations
- Increased buffer (10 tokens) handles multiple tabs

## ✅ Testing Checklist

After applying fixes:
- [ ] Click reset button multiple times - should work every time
- [ ] Open history modal multiple times - should work
- [ ] Rollback a block multiple times - should work
- [ ] Save changes multiple times - should work
- [ ] Open multiple tabs and edit - all should work

## 🔒 Security Note

Not consuming tokens for read operations is safe because:
1. Still validates token authenticity
2. Still checks admin authorization
3. Only affects token reusability, not security
4. Common practice for GET-like operations
