# Apply Secure Session Configuration to All Files - Simple Version

Write-Host "Applying Secure Session Configuration..." -ForegroundColor Cyan

$rootPath = "C:\xampp\htdocs\EducAid 2\EducAid"
$updated = 0

# Key admin files to update
$files = @(
    "modules\admin\admin_profile.php",
    "modules\admin\archived_students.php",
    "modules\admin\logout.php",
    "modules\admin\notifications_api.php",
    "modules\admin\scan_qr.php",
    "modules\admin\sidebar_settings.php",
    "modules\admin\topbar_settings.php",
    "modules\admin\verify_password.php",
    "modules\student\student_logout.php"
)

foreach ($file in $files) {
    $fullPath = Join-Path $rootPath $file
    
    if (Test-Path $fullPath) {
        $content = Get-Content $fullPath -Raw
        
        # Skip if already has session_config
        if ($content -match "session_config") {
            Write-Host "Skip: $file" -ForegroundColor Gray
            continue
        }
        
        # Find session_start and add config before it
        if ($content -match "session_start") {
            $depth = ($file.Split('\').Count - 1)
            $relPath = ("../" * $depth) + "config/session_config.php"
            
            # Simple replacement
            $searchFor = "session_start();"
            $replaceWith = "// Load secure session configuration (must be before session_start)`nrequire_once __DIR__ . '$relPath';`n`nsession_start();"
            
            $newContent = $content.Replace($searchFor, $replaceWith)
            
            if ($newContent -ne $content) {
                Set-Content -Path $fullPath -Value $newContent -NoNewline
                Write-Host "Updated: $file" -ForegroundColor Green
                $updated++
            }
        }
    }
}

Write-Host "`nUpdated $updated files" -ForegroundColor Yellow
