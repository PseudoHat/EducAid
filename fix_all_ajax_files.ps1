# Fix All AJAX Files - Add Output Buffering and Error Suppression
# This script adds ob_start() and proper error handling to all AJAX PHP files

Write-Host "Scanning for all AJAX PHP files..." -ForegroundColor Cyan

# Find all ajax PHP files
$ajaxFiles = Get-ChildItem -Path $PSScriptRoot -Filter "ajax*.php" -Recurse -File | 
    Select-Object -ExpandProperty FullName

$fixedCount = 0
$alreadyFixedCount = 0
$errorCount = 0

Write-Host "Found $($ajaxFiles.Count) AJAX files`n" -ForegroundColor Yellow

foreach ($fullPath in $ajaxFiles) {
    $relativePath = $fullPath.Replace($PSScriptRoot + "\", "")
    
    try {
        $content = Get-Content $fullPath -Raw -ErrorAction Stop
        
        # Check if already has ob_start() at the beginning (within first 100 chars)
        if ($content.Substring(0, [Math]::Min(100, $content.Length)) -match 'ob_start\s*\(\s*\)') {
            Write-Host "✓ Already fixed: $relativePath" -ForegroundColor Green
            $alreadyFixedCount++
            continue
        }
        
        # Add ob_start and error suppression right after <?php
        if ($content -match '^(<\?php)(\s*)') {
            $newContent = $content -replace '^(<\?php)(\s*)', ('$1' + "`nob_start();`nerror_reporting(E_ALL & ~E_NOTICE & ~E_WARNING);`nini_set('display_errors', '0');`n")
            
            # Write back to file
            [System.IO.File]::WriteAllText($fullPath, $newContent, [System.Text.UTF8Encoding]::new($false))
            
            Write-Host "✓ Fixed: $relativePath" -ForegroundColor Cyan
            $fixedCount++
        } else {
            Write-Host "✗ Could not parse: $relativePath" -ForegroundColor Red
            $errorCount++
        }
    } catch {
        Write-Host "✗ Error processing: $relativePath - $($_.Exception.Message)" -ForegroundColor Red
        $errorCount++
    }
}

Write-Host "`n==========================================" -ForegroundColor Magenta
Write-Host "SUMMARY" -ForegroundColor Magenta
Write-Host "==========================================" -ForegroundColor Magenta
Write-Host "Total files found:    $($ajaxFiles.Count)" -ForegroundColor White
Write-Host "Fixed:                $fixedCount" -ForegroundColor Cyan
Write-Host "Already Fixed:        $alreadyFixedCount" -ForegroundColor Green
Write-Host "Errors:               $errorCount" -ForegroundColor Red
Write-Host "==========================================" -ForegroundColor Magenta
