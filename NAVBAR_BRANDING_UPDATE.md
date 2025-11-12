# Navbar Branding Update - System Name & Municipality Name Display

## Summary
Updated the website navigation bar to dynamically display **System Name** and **Municipality Name** from the `theme_settings` database table, making these fields functional instead of just stored values.

## Changes Made

### 1. Updated `includes/website/navbar.php`
**Location:** Lines 17-38

**What Changed:**
- Added database query to fetch `system_name` and `municipality_name` from `theme_settings` table
- Dynamic brand text generation using format: `System Name • Municipality Name`
- Falls back to "EducAid • City of General Trias" if database values are not available

**Code Added:**
```php
// Fetch system_name and municipality_name from theme_settings
$navbar_system_name = 'EducAid'; // fallback
$navbar_municipality_name_from_theme = 'City of General Trias'; // fallback

if (isset($connection)) {
    $theme_result = pg_query_params(
        $connection,
        "SELECT system_name, municipality_name FROM theme_settings WHERE municipality_id = $1 AND is_active = TRUE LIMIT 1",
        [1]
    );
    
    if ($theme_result && pg_num_rows($theme_result) > 0) {
        $theme_data = pg_fetch_assoc($theme_result);
        if (!empty($theme_data['system_name'])) {
            $navbar_system_name = $theme_data['system_name'];
        }
        if (!empty($theme_data['municipality_name'])) {
            $navbar_municipality_name_from_theme = $theme_data['municipality_name'];
        }
        pg_free_result($theme_result);
    }
}

$brand_config = [
  'name' => $navbar_system_name . ' • ' . $navbar_municipality_name_from_theme,
  // ... rest of config
];
```

### 2. Updated `modules/admin/topbar_settings.php`
**Location:** Lines 718-732

**What Changed:**
- Enhanced form field descriptions to explain where these values are displayed
- Added visual indicators (info icons) to make the purpose clearer
- Included format explanation: "System Name • Municipality Name"

**Updated Help Text:**
- **System Name:** Now shows "Displayed in the website navigation bar as the brand name."
- **Municipality Name:** Now shows "Displayed in the website navigation bar after the system name (format: System Name • Municipality Name)."

## How It Works

### Admin Side (Topbar Settings Page)
1. Admin navigates to `Website CMS` → `Topbar Settings`
2. Edits **System Name** field (e.g., "EducAid")
3. Edits **Municipality Name** field (e.g., "City of General Trias")
4. Saves the changes

### Website Side (Public Navbar)
1. When any website page loads (landing, about, contact, etc.), the navbar is included
2. The navbar fetches the latest `system_name` and `municipality_name` from the database
3. These values are combined with a bullet separator: `EducAid • City of General Trias`
4. The combined text is displayed in the navigation bar next to the logo

## Visual Result

**Before:**
- Navbar showed hardcoded text: "EducAid • City of General Trias"
- System Name and Municipality Name fields were stored but never displayed

**After:**
- Navbar shows dynamic text from database
- Changes in Topbar Settings immediately reflect on the website
- Format: `[System Name] • [Municipality Name]`

## Database Reference

**Table:** `theme_settings`
**Columns Used:**
- `system_name` (VARCHAR) - The application name
- `municipality_name` (VARCHAR) - The local government unit name
- `municipality_id` (INTEGER) - Municipality identifier (default: 1)
- `is_active` (BOOLEAN) - Active theme flag

**Query:**
```sql
SELECT system_name, municipality_name 
FROM theme_settings 
WHERE municipality_id = 1 AND is_active = TRUE 
LIMIT 1;
```

## Testing

**Test File Created:** `test_navbar_branding.php`
- Verifies database query works
- Shows current values
- Tests navbar include functionality

**To Test:**
1. Visit: `http://localhost/EducAid%202/EducAid/test_navbar_branding.php`
2. Check if System Name and Municipality Name display correctly
3. Visit any website page to see the navbar in action

## Benefits

✅ **Dynamic Branding** - Municipality can customize their system name without code changes
✅ **Centralized Management** - One place to update branding across entire website
✅ **Multi-Municipality Support** - Ready for future multi-tenancy (multiple municipalities)
✅ **Professional Appearance** - Clean, consistent branding format
✅ **Admin-Friendly** - Clear explanations in the admin interface

## Files Modified

1. `includes/website/navbar.php` - Added dynamic brand text fetching
2. `modules/admin/topbar_settings.php` - Enhanced field descriptions
3. `test_navbar_branding.php` - Created for testing purposes

## Future Enhancements

- Support for custom separators (• vs - vs |)
- Option to hide/show municipality name
- Municipality-specific logos alongside names
- RTL language support for brand text
