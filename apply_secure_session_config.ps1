# Apply Secure Session Configuration to All Files
# This script adds the session_config.php include before all session_start() calls

Write-Host "🔒 Applying Secure Session Configuration..." -ForegroundColor Cyan
Write-Host ""

$rootPath = "C:\xampp\htdocs\EducAid 2\EducAid"

# Files that need updating (excluding already updated files)
$filesToUpdate = @(
    "modules\admin\admin_profile.php",
    "modules\admin\archived_students.php",
    "modules\admin\auto_approve_high_confidence.php",
    "modules\admin\blacklist_archive.php",
    "modules\admin\blacklist_service.php",
    "modules\admin\check_automatic_archiving.php",
    "modules\admin\compress_archived_students.php",
    "modules\admin\logout.php",
    "modules\admin\manage_course_mappings.php",
    "modules\admin\manage_schedules.php",
    "modules\admin\notifications_api.php",
    "modules\admin\scan_qr.php",
    "modules\admin\sidebar_settings.php",
    "modules\admin\slot_threshold_admin.php",
    "modules\admin\topbar_settings.php",
    "modules\admin\verify_password.php",
    "modules\admin\verify_password_debug.php",
    "modules\admin\advance_year_levels.php",
    "modules\student\student_logout.php"
)

$updated = 0
$skipped = 0
$errors = 0

foreach ($file in $filesToUpdate) {
    $fullPath = Join-Path $rootPath $file
    
    if (-not (Test-Path $fullPath)) {
        Write-Host "⚠️  File not found: $file" -ForegroundColor Yellow
        $skipped++
        continue
    }
    
    try {
        $content = Get-Content $fullPath -Raw
        
        # Check if session_config.php is already included
        if ($content -match "session_config\.php") {
            Write-Host "✓ Already configured: $file" -ForegroundColor Gray
            $skipped++
            continue
        }
        
        # Check if session_start() exists
        if ($content -notmatch "session_start\(\)") {
            Write-Host "⊗ No session_start found: $file" -ForegroundColor DarkGray
            $skipped++
            continue
        }
        
        # Calculate the relative path to config
        $depth = ($file.Split('\').Count - 1)
        $relativePath = "../" * $depth + "config/session_config.php"
        
        # Add the require_once before session_start()
        $pattern = "session_start\(\);"
        $sessionLine = "session_start();"
        $configLine = "// Load secure session configuration (must be before session_start)`r`nrequire_once __DIR__ . '/$relativePath';`r`n`r`n"
        $replacement = $configLine + $sessionLine
        
        $newContent = $content -replace $pattern, $replacement
        
        if ($newContent -ne $content) {
            Set-Content -Path $fullPath -Value $newContent -NoNewline
            Write-Host "✅ Updated: $file" -ForegroundColor Green
            $updated++
        } else {
            Write-Host "⊗ No changes needed: $file" -ForegroundColor DarkGray
            $skipped++
        }
        
    } catch {
        Write-Host "❌ Error processing $file : $_" -ForegroundColor Red
        $errors++
    }
}

Write-Host ""
Write-Host "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━" -ForegroundColor Cyan
Write-Host "📊 Summary:" -ForegroundColor Yellow
Write-Host "  ✅ Updated: $updated files" -ForegroundColor Green
Write-Host "  ⊗ Skipped: $skipped files" -ForegroundColor Gray
Write-Host "  ❌ Errors: $errors files" -ForegroundColor Red
Write-Host ""
Write-Host "🔐 Session Security Enhancements Applied!" -ForegroundColor Cyan
Write-Host ""
Write-Host "Cookie Flags Now Set:" -ForegroundColor Yellow
Write-Host "  ✓ HttpOnly = true (prevents XSS)" -ForegroundColor Green
Write-Host "  ✓ Secure = true (HTTPS only in production)" -ForegroundColor Green
Write-Host "  ✓ SameSite = Lax (CSRF protection)" -ForegroundColor Green
Write-Host "  ✓ Cookie Prefix = __Host- (on HTTPS)" -ForegroundColor Green
Write-Host ""
