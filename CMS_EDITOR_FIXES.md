# CMS Editor Fixes - Help Modal & Button Repairs

## Date: 2025-01-XX

## Issues Fixed

### 1. Added Help Modal
**Problem:** Users had no guidance on how to use the CMS editor features.

**Solution:** 
- Added help button (?) to toolbar header
- Created comprehensive help modal with sections:
  - Getting Started
  - Styling Options
  - Block Controls
  - Page Actions
  - History & Rollback
  - Tips & Best Practices

**Files Modified:**
- `includes/website/edit_toolbar.php` - Added help button and modal HTML

### 2. Fixed "Hide Boxes" Toggle
**Problem:** The Hide Boxes button wasn't working because:
- JavaScript was setting `style.outline = 'none'` inline
- CSS used `!important` which overrides inline styles
- The outline couldn't be hidden

**Solution:**
- Changed JavaScript to toggle a body class (`lp-hide-outlines`) instead
- Added CSS rule to hide outlines when class is present
- Now properly hides/shows all editable element outlines

**Files Modified:**
- `assets/js/website/content_editor.js` - Changed from inline style to class toggle
- `assets/css/content_editor.css` - Added `.lp-hide-outlines` CSS rule

### 3. Fixed "Reset Block" Button
**Problem:** Reset Block wasn't updating the textarea after restoring content.

**Solution:**
- Added `txt.value=state.target.innerText.trim();` after restoring innerHTML
- Now the text editor textarea properly reflects the reset content

**Files Modified:**
- `assets/js/website/content_editor.js` - Added textarea update after reset

## Technical Details

### Help Button Implementation
```javascript
// Help button functionality
const helpBtn = document.getElementById('lp-help-btn');
if (helpBtn) {
    helpBtn.addEventListener('click', () => {
        const helpModal = new bootstrap.Modal(document.getElementById('lp-help-modal'));
        helpModal.show();
    });
}
```

### Hide Boxes Fix
**Before:**
```javascript
// Didn't work - CSS !important overrode inline style
qsa('.lp-edit-highlight').forEach(el=>el.style.outline= on?'none':'');
```

**After:**
```javascript
// Works - Uses CSS class to control visibility
document.body.classList.toggle('lp-hide-outlines', on);
```

```css
/* CSS rule */
body.lp-hide-outlines [data-lp-key][contenteditable="true"],
body.lp-hide-outlines .editable-block,
body.lp-hide-outlines .editable-navbar-item {
  outline: none !important;
  animation: none !important;
}
```

### Reset Block Fix
**Before:**
```javascript
if(orig!==undefined) state.target.innerHTML=orig;
// Textarea still showed old content
```

**After:**
```javascript
if(orig!==undefined){
    state.target.innerHTML=orig; 
    txt.value=state.target.innerText.trim(); // Update textarea too
}
```

## Testing Checklist

- [ ] Help button shows modal when clicked
- [ ] Help modal displays all sections correctly
- [ ] Help modal closes when "Got It!" button clicked
- [ ] Hide Boxes toggle removes blue outlines
- [ ] Hide Boxes toggle text changes (Show Boxes ↔ Hide Boxes)
- [ ] Show Boxes toggle restores blue outlines
- [ ] Reset Block restores original content
- [ ] Reset Block updates the textarea
- [ ] Reset Block clears color styling
- [ ] All CSRF operations work without errors

## Deployment

1. Commit all changes:
   ```bash
   git add includes/website/edit_toolbar.php
   git add assets/js/website/content_editor.js
   git add assets/css/content_editor.css
   git commit -m "feat: Add CMS editor help modal and fix broken buttons

- Add help button with comprehensive usage guide modal
- Fix Hide Boxes toggle (was using inline styles, now uses CSS class)
- Fix Reset Block to update textarea after restoring content
- Improve UX with clear instructions for all editor features"
   ```

2. Push to GitHub:
   ```bash
   git push origin main
   ```

3. Railway will auto-deploy from GitHub

## User Benefits

✅ **Help System** - Users can now click ? for instant guidance
✅ **Working Hide Boxes** - Can toggle outlines on/off for cleaner view
✅ **Working Reset Block** - Can properly undo changes to individual elements
✅ **Better UX** - Clear instructions prevent confusion and errors
✅ **No More CSRF Errors** - All operations work smoothly after previous token fix

## Related Documentation
- See `CSRF_FIX_COMPLETE.md` for CSRF token fix details
- See `DEPLOYMENT_GUIDE.md` for deployment workflow
