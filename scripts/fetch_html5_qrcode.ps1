# Downloads html5-qrcode v2.3.8 to assets/vendor/html5-qrcode for local fallback
param(
    [string]$Version = "2.3.8"
)

$ErrorActionPreference = 'Stop'

$targetDir = Join-Path $PSScriptRoot "..\assets\vendor\html5-qrcode"
New-Item -ItemType Directory -Force -Path $targetDir | Out-Null

$cdnUrl1 = "https://cdn.jsdelivr.net/npm/html5-qrcode@$Version/minified/html5-qrcode.min.js"
$cdnUrl2 = "https://unpkg.com/html5-qrcode@$Version/minified/html5-qrcode.min.js"
$outFile = Join-Path $targetDir "html5-qrcode.min.js"

Write-Host "Fetching html5-qrcode v$Version..."

try {
    Invoke-WebRequest -Uri $cdnUrl1 -OutFile $outFile -UseBasicParsing
    Write-Host "Downloaded from jsDelivr to $outFile"
}
catch {
    Write-Warning "jsDelivr failed: $($_.Exception.Message)"
    Write-Host "Trying unpkg..."
    Invoke-WebRequest -Uri $cdnUrl2 -OutFile $outFile -UseBasicParsing
    Write-Host "Downloaded from unpkg to $outFile"
}

# Quick sanity check
if (-not (Test-Path $outFile)) {
    throw "Failed to download html5-qrcode.min.js"
}

Write-Host "Done. Local fallback available at assets/vendor/html5-qrcode/html5-qrcode.min.js"
