# Fix JSON errors in CMS AJAX files by suppressing PHP errors and cleaning output buffers
# This prevents "<br /> <b>..." HTML output before JSON responses

$ajaxFiles = @(
    "website\ajax_save_ann_content.php",
    "website\ajax_save_contact_content.php",
    "website\ajax_save_hiw_content.php",
    "website\ajax_save_req_content.php",
    "website\ajax_get_landing_blocks.php",
    "website\ajax_get_about_blocks.php",
    "website\ajax_get_ann_blocks.php",
    "website\ajax_get_contact_blocks.php",
    "website\ajax_get_hiw_blocks.php",
    "website\ajax_get_req_blocks.php",
    "website\ajax_get_landing_history.php",
    "website\ajax_get_about_history.php",
    "website\ajax_rollback_landing_block.php",
    "website\ajax_rollback_about_block.php",
    "website\ajax_rollback_ann_block.php",
    "website\ajax_rollback_contact_block.php",
    "website\ajax_rollback_hiw_block.php",
    "website\ajax_rollback_req_block.php",
    "website\ajax_reset_landing_content.php",
    "website\ajax_reset_about_content.php",
    "website\ajax_reset_ann_content.php",
    "website\ajax_reset_contact_content.php",
    "website\ajax_reset_hiw_content.php",
    "website\ajax_reset_req_content.php"
)

$errorSuppressionCode = @'
// Suppress all output before JSON response
error_reporting(0);
ini_set('display_errors', 0);
ob_start();
'@

$cleanBufferCode = @'
// Clear any output that might have been generated
ob_clean();
'@

$respFunctionFix = @'
function resp($ok,$msg='',$extra=[]){
  if (ob_get_level() > 0) ob_clean();
  header('Content-Type: application/json');
  echo json_encode(array_merge(['success'=>$ok,'message'=>$msg],$extra));
  exit;
}
'@

foreach ($file in $ajaxFiles) {
    $fullPath = Join-Path $PSScriptRoot $file
    
    if (Test-Path $fullPath) {
        Write-Host "Processing: $file" -ForegroundColor Cyan
        
        $content = Get-Content $fullPath -Raw
        
        # Check if already fixed
        if ($content -match 'error_reporting\(0\)') {
            Write-Host "  Already fixed, skipping" -ForegroundColor Yellow
            continue
        }
        
        # Add error suppression after <?php
        $content = $content -replace '(<\?php\s+)', "`$1`n$errorSuppressionCode`n"
        
        # Add buffer cleaning before header
        $content = $content -replace '(header\(''Content-Type: application/json''\);)', "$cleanBufferCode`n`$1"
        
        # Fix resp function if it exists
        if ($content -match 'function resp\(\$ok,\$msg') {
            $content = $content -replace 'function resp\(\$ok,\$msg.*?\{echo json_encode.*?exit;\}', $respFunctionFix
        }
        
        # Save the file
        Set-Content -Path $fullPath -Value $content -NoNewline
        Write-Host "  Fixed!" -ForegroundColor Green
    } else {
        Write-Host "File not found: $file" -ForegroundColor Red
    }
}

Write-Host "`nAll AJAX files have been processed!" -ForegroundColor Green
Write-Host "This fix prevents PHP errors/warnings from appearing before JSON responses." -ForegroundColor Cyan
