# Municipality Logo Storage Issue - Solution

## Problem

Municipality logos aren't displaying on production (Railway) because:
1. Logos are stored in `/assets/City Logos/` directory
2. Railway has **ephemeral filesystem** - files uploaded are lost on redeploy
3. Need persistent storage for user-uploaded files

## Solution: Use Railway Volume Mount

### Step 1: Create Railway Volume

In Railway dashboard:
1. Go to your project
2. Click on your service
3. Go to **"Variables"** tab
4. Scroll to **"Volumes"** section
5. Click **"+ New Volume"**
6. Set:
   - **Mount Path:** `/app/mnt`
   - **Name:** `educaid-uploads`
   - **Size:** 1GB (or more if needed)

### Step 2: Update Logo Storage Path

Change logo storage from `/assets/City Logos/` to `/mnt/municipality_logos/`

**Files to update:**

1. **cli_upload_municipality_logos.php**
2. **bulk_upload_municipality_logos.php**
3. **modules/admin/municipality_content.php**
4. Any file upload handlers

### Step 3: Migration Script

Create script to move existing logos to mounted volume:

```php
<?php
// migrate_logos_to_volume.php
require_once __DIR__ . '/config/database.php';

$oldPath = __DIR__ . '/assets/City Logos';
$newPath = __DIR__ . '/mnt/municipality_logos';

// Create new directory
if (!is_dir($newPath)) {
    mkdir($newPath, 0755, true);
}

// Get all municipalities with logos
$result = pg_query($connection, "SELECT municipality_id, name, preset_logo_image, custom_logo_image FROM municipalities");

while ($row = pg_fetch_assoc($result)) {
    // Move preset logo
    if ($row['preset_logo_image']) {
        $oldFile = __DIR__ . $row['preset_logo_image'];
        $filename = basename($row['preset_logo_image']);
        $newFile = $newPath . '/' . $filename;
        
        if (file_exists($oldFile)) {
            copy($oldFile, $newFile);
            $newWebPath = '/mnt/municipality_logos/' . $filename;
            pg_query_params($connection, 
                "UPDATE municipalities SET preset_logo_image = $1 WHERE municipality_id = $2",
                [$newWebPath, $row['municipality_id']]
            );
            echo "✅ Moved: {$row['name']}\n";
        }
    }
}
```

### Step 4: Update FilePathConfig

Add volume path constant:

```php
// config/FilePathConfig.php
class FilePathConfig {
    const VOLUME_PATH = '/mnt';
    const LOGO_PATH = '/mnt/municipality_logos';
    
    public static function getLogoPath(): string {
        // On Railway, use mounted volume
        if (getenv('RAILWAY_ENVIRONMENT')) {
            return self::LOGO_PATH;
        }
        // On localhost, use assets folder
        return '/assets/City Logos';
    }
}
```

### Step 5: Update Logo Upload Handler

```php
// In upload handler
$uploadPath = FilePathConfig::getLogoPath();
$fullPath = __DIR__ . $uploadPath;

if (!is_dir($fullPath)) {
    mkdir($fullPath, 0755, true);
}

$targetFile = $fullPath . '/' . $filename;
move_uploaded_file($_FILES['logo']['tmp_name'], $targetFile);
```

### Step 6: Update Logo Display

```php
// modules/admin/municipality_content.php
function build_logo_src(?string $path): ?string {
    // ... existing checks ...
    
    // Handle volume paths
    if (str_starts_with($normalizedRaw, '/mnt/')) {
        // Serve from volume
        return $encodedPath;
    }
    
    // ... rest of function ...
}
```

## Alternative: Store Logos as Base64 in Database

If you don't want to deal with volumes:

```php
// Convert logo to base64 and store in database
$logoData = file_get_contents($uploadedFile);
$base64 = 'data:image/png;base64,' . base64_encode($logoData);

pg_query_params($connection,
    "UPDATE municipalities SET custom_logo_image = $1 WHERE municipality_id = $2",
    [$base64, $municipality_id]
);
```

**Pros:**
- ✅ No filesystem needed
- ✅ Works on Railway without volumes
- ✅ Logos persist across deploys

**Cons:**
- ❌ Database size increases
- ❌ Slower queries with large BLOBs
- ❌ Not ideal for many/large images

## Recommended Approach

**Use Railway Volume** because:
1. ✅ Better performance
2. ✅ Cleaner database
3. ✅ Easier to manage/backup files
4. ✅ Can store other uploads (student docs, etc.)
5. ✅ Scalable

## Quick Fix for Now

If logos aren't showing, check:

```php
// Debug script
<?php
$logoPath = '/assets/City Logos/General Trias.png';
$fullPath = __DIR__ . $logoPath;

echo "Checking: $fullPath\n";
echo "Exists: " . (file_exists($fullPath) ? 'YES' : 'NO') . "\n";
echo "Readable: " . (is_readable($fullPath) ? 'YES' : 'NO') . "\n";
echo "Permissions: " . substr(sprintf('%o', fileperms($fullPath)), -4) . "\n";
?>
```

Would you like me to implement the Railway volume solution?
