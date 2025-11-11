# Student Sidebar Z-Index Test Setup

## Files Created

1. **student_profile_test.php**
   - Copy of student_profile.php for safe testing
   - Includes experimental CSS fix
   - Has orange badge "🧪 TEST PAGE" in bottom-right corner
   
2. **sidebar_test_fix.css**
   - Experimental z-index overrides
   - Uses `z-index: 9999 !important` for sidebar on mobile
   - Uses `z-index: 9998 !important` for backdrop

## How to Test

1. Navigate to: `http://localhost/EducAid/modules/student/student_profile_test.php`
2. Open on mobile view (width ≤ 992px) or use browser DevTools mobile emulator
3. Click the burger menu to open sidebar
4. Sidebar should now cover the topbar completely

## Current Z-Index Stack (Test Page)

Mobile (≤ 992px):
```
Sidebar:  9999 (highest)
Backdrop: 9998
Topbar:   1050
Header:   1030
Content:  1 (default)
```

Desktop (> 992px):
```
Topbar:   1050
Header:   1030
Sidebar:  1000
Content:  1 (default)
```

## If This Works

We'll apply the fix to the main files but with more reasonable z-index values:
- Sidebar: 1070 → 1080 (just needs to be 30-50 above topbar)
- Backdrop: 1055 → 1060
- Keep topbar at 1050
- Keep header at 1030

## To Revert

Just delete these test files:
- modules/student/student_profile_test.php
- assets/css/student/sidebar_test_fix.css
