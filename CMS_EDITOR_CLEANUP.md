# CMS Editor Cleanup - Reset Block Removal

## Date: November 12, 2025

## Changes Made

### Removed "Reset Block" Button
**Reason:** Button was confusing and not functional:
- Always disabled (only enabled when element selected)
- Unclear purpose to users
- Functionality duplicated by History → Rollback feature
- Users can simply refresh page to discard unsaved changes

### Updated Toolbar Structure

**Before:**
```
Block Controls
├── Reset Block (disabled)
└── Hide Boxes
```

**After:**
```
View Controls
└── Hide Boxes
```

### Benefits
✅ **Cleaner UI** - Removed confusing disabled button
✅ **Better UX** - Simplified control panel
✅ **Clear Purpose** - "View Controls" is more descriptive than "Block Controls"
✅ **No Lost Functionality** - Users can:
  - Use History → Rollback to restore previous versions
  - Refresh page to discard all unsaved changes
  - Use "Reset All" to clear all content

### Help Modal Updates
Updated the help documentation to:
- Remove "Reset Block" references
- Rename "Block Controls" → "View Controls"
- Update tips to promote History/Rollback workflow
- Clarify that changes aren't permanent until saved

## Files Modified
1. `includes/website/edit_toolbar.php`
   - Removed Reset Block button HTML
   - Renamed section to "View Controls"
   - Updated help modal content
   - Removed Reset Block from tips

## User Impact
Users will notice:
- Simpler, cleaner toolbar
- One less button to be confused about
- Clear workflow: Edit → Save (or History → Rollback if needed)

## Next Steps
If users need to undo individual block changes:
1. Click **History** button
2. Find the version before the change
3. Click **Rollback** to restore

This is actually more powerful than Reset Block because:
- Shows what changed and when
- Can restore to any previous version
- Creates audit trail
- Can undo the rollback if needed
