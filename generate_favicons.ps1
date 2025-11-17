# EducAid Favicon Generator
# This script generates all required favicon files from educaid-logo.png

Write-Host "==================================" -ForegroundColor Cyan
Write-Host "EducAid Favicon Generator" -ForegroundColor Cyan
Write-Host "==================================" -ForegroundColor Cyan
Write-Host ""

# Set paths
$sourceLogo = "c:\xampp\htdocs\EducAid\assets\images\educaid-logo.png"
$outputDir = "c:\xampp\htdocs\EducAid"
$imagesDir = "$outputDir\assets\images"

# Check if source logo exists
if (-not (Test-Path $sourceLogo)) {
    Write-Host "❌ ERROR: Source logo not found at $sourceLogo" -ForegroundColor Red
    exit 1
}

Write-Host "✅ Found source logo: $sourceLogo" -ForegroundColor Green
Write-Host ""

# Check if Python is installed
$pythonInstalled = $false
try {
    $pythonVersion = python --version 2>&1
    if ($LASTEXITCODE -eq 0) {
        $pythonInstalled = $true
        Write-Host "✅ Python detected: $pythonVersion" -ForegroundColor Green
    }
} catch {
    Write-Host "⚠️  Python not detected" -ForegroundColor Yellow
}

Write-Host ""
Write-Host "==================================" -ForegroundColor Cyan
Write-Host "Generation Options" -ForegroundColor Cyan
Write-Host "==================================" -ForegroundColor Cyan
Write-Host ""
Write-Host "1. Use Python/Pillow (Recommended - Best Quality)" -ForegroundColor White
Write-Host "2. Use Online API (favicon.io) - Requires Internet" -ForegroundColor White
Write-Host "3. Manual Instructions" -ForegroundColor White
Write-Host ""

$choice = Read-Host "Select option (1-3)"

switch ($choice) {
    "1" {
        Write-Host ""
        Write-Host "==================================" -ForegroundColor Cyan
        Write-Host "Option 1: Python/Pillow Method" -ForegroundColor Cyan
        Write-Host "==================================" -ForegroundColor Cyan
        Write-Host ""
        
        if (-not $pythonInstalled) {
            Write-Host "❌ Python is not installed. Please install Python first or choose option 2." -ForegroundColor Red
            Write-Host "   Download from: https://www.python.org/downloads/" -ForegroundColor Yellow
            exit 1
        }
        
        # Check if Pillow is installed
        Write-Host "Checking for Pillow (PIL) library..." -ForegroundColor Yellow
        $pillowCheck = python -c "import PIL; print(PIL.__version__)" 2>&1
        
        if ($LASTEXITCODE -ne 0) {
            Write-Host "⚠️  Pillow not installed. Installing now..." -ForegroundColor Yellow
            python -m pip install Pillow
            if ($LASTEXITCODE -ne 0) {
                Write-Host "❌ Failed to install Pillow. Please run: pip install Pillow" -ForegroundColor Red
                exit 1
            }
        }
        
        Write-Host "✅ Pillow is installed" -ForegroundColor Green
        Write-Host ""
        Write-Host "Generating favicon files..." -ForegroundColor Yellow
        
        # Create Python script to generate favicons
        $pythonScript = @"
from PIL import Image
import os

source = r'$sourceLogo'
output_dir = r'$outputDir'
images_dir = r'$imagesDir'

print(f'Loading logo: {source}')
img = Image.open(source)

# Convert to RGBA if not already
if img.mode != 'RGBA':
    img = img.convert('RGBA')

print(f'Original size: {img.size}')

# Generate favicon-16x16.png
print('Generating favicon-16x16.png...')
icon_16 = img.resize((16, 16), Image.Resampling.LANCZOS)
icon_16.save(os.path.join(images_dir, 'favicon-16x16.png'))
print('✅ favicon-16x16.png created')

# Generate favicon-32x32.png
print('Generating favicon-32x32.png...')
icon_32 = img.resize((32, 32), Image.Resampling.LANCZOS)
icon_32.save(os.path.join(images_dir, 'favicon-32x32.png'))
print('✅ favicon-32x32.png created')

# Generate apple-touch-icon.png
print('Generating apple-touch-icon.png...')
icon_180 = img.resize((180, 180), Image.Resampling.LANCZOS)
icon_180.save(os.path.join(images_dir, 'apple-touch-icon.png'))
print('✅ apple-touch-icon.png created')

# Generate favicon.ico (multi-resolution)
print('Generating favicon.ico...')
icon_48 = img.resize((48, 48), Image.Resampling.LANCZOS)
icon_ico_path = os.path.join(output_dir, 'favicon.ico')
icon_48.save(icon_ico_path, format='ICO', sizes=[(16,16), (32,32), (48,48)])
print('✅ favicon.ico created')

# Optional: Generate additional sizes for PWA
print('Generating additional PWA icons...')
icon_192 = img.resize((192, 192), Image.Resampling.LANCZOS)
icon_192.save(os.path.join(images_dir, 'android-chrome-192x192.png'))
print('✅ android-chrome-192x192.png created')

icon_512 = img.resize((512, 512), Image.Resampling.LANCZOS)
icon_512.save(os.path.join(images_dir, 'android-chrome-512x512.png'))
print('✅ android-chrome-512x512.png created')

print('')
print('🎉 All favicon files generated successfully!')
"@
        
        # Save and run Python script
        $pythonScript | Out-File -FilePath "$outputDir\generate_favicons_temp.py" -Encoding UTF8
        python "$outputDir\generate_favicons_temp.py"
        
        if ($LASTEXITCODE -eq 0) {
            Remove-Item "$outputDir\generate_favicons_temp.py" -Force
            Write-Host ""
            Write-Host "🎉 SUCCESS! All favicon files have been generated!" -ForegroundColor Green
        } else {
            Write-Host ""
            Write-Host "❌ Error occurred during generation" -ForegroundColor Red
            exit 1
        }
    }
    
    "2" {
        Write-Host ""
        Write-Host "==================================" -ForegroundColor Cyan
        Write-Host "Option 2: Online API Method" -ForegroundColor Cyan
        Write-Host "==================================" -ForegroundColor Cyan
        Write-Host ""
        Write-Host "Opening RealFaviconGenerator.net in your browser..." -ForegroundColor Yellow
        Write-Host ""
        Write-Host "Steps:" -ForegroundColor White
        Write-Host "1. Upload your logo: $sourceLogo" -ForegroundColor White
        Write-Host "2. Configure settings (or keep defaults)" -ForegroundColor White
        Write-Host "3. Generate and download the package" -ForegroundColor White
        Write-Host "4. Extract files to:" -ForegroundColor White
        Write-Host "   - favicon.ico → $outputDir\" -ForegroundColor Yellow
        Write-Host "   - *.png files → $imagesDir\" -ForegroundColor Yellow
        Write-Host ""
        
        Start-Process "https://realfavicongenerator.net/"
        
        Write-Host "Press any key when you've downloaded and extracted the files..." -ForegroundColor Cyan
        $null = $Host.UI.RawUI.ReadKey("NoEcho,IncludeKeyDown")
    }
    
    "3" {
        Write-Host ""
        Write-Host "==================================" -ForegroundColor Cyan
        Write-Host "Option 3: Manual Instructions" -ForegroundColor Cyan
        Write-Host "==================================" -ForegroundColor Cyan
        Write-Host ""
        Write-Host "Use any image editing tool (GIMP, Photoshop, Paint.NET, etc.)" -ForegroundColor White
        Write-Host ""
        Write-Host "Source Logo: $sourceLogo" -ForegroundColor Yellow
        Write-Host ""
        Write-Host "Create these files:" -ForegroundColor White
        Write-Host "1. favicon-16x16.png (16x16 pixels) → $imagesDir\" -ForegroundColor White
        Write-Host "2. favicon-32x32.png (32x32 pixels) → $imagesDir\" -ForegroundColor White
        Write-Host "3. apple-touch-icon.png (180x180 pixels) → $imagesDir\" -ForegroundColor White
        Write-Host "4. favicon.ico (multi-resolution) → $outputDir\" -ForegroundColor White
        Write-Host ""
        Write-Host "For favicon.ico, use a converter like: https://convertio.co/png-ico/" -ForegroundColor Yellow
        Write-Host ""
        exit 0
    }
    
    default {
        Write-Host "❌ Invalid option selected" -ForegroundColor Red
        exit 1
    }
}

Write-Host ""
Write-Host "==================================" -ForegroundColor Cyan
Write-Host "Verification" -ForegroundColor Cyan
Write-Host "==================================" -ForegroundColor Cyan
Write-Host ""

# Verify all files were created
$requiredFiles = @(
    @{Path="$outputDir\favicon.ico"; Name="favicon.ico"},
    @{Path="$imagesDir\favicon-16x16.png"; Name="favicon-16x16.png"},
    @{Path="$imagesDir\favicon-32x32.png"; Name="favicon-32x32.png"},
    @{Path="$imagesDir\apple-touch-icon.png"; Name="apple-touch-icon.png"}
)

$allExist = $true
foreach ($file in $requiredFiles) {
    if (Test-Path $file.Path) {
        $size = (Get-Item $file.Path).Length
        $sizeKB = [math]::Round($size / 1KB, 2)
        Write-Host "✅ $($file.Name) - ${sizeKB} KB" -ForegroundColor Green
    } else {
        Write-Host "❌ MISSING: $($file.Name)" -ForegroundColor Red
        $allExist = $false
    }
}

Write-Host ""

if ($allExist) {
    Write-Host "🎉 All required favicon files are present!" -ForegroundColor Green
    Write-Host ""
    Write-Host "Next steps:" -ForegroundColor Cyan
    Write-Host "1. Clear your browser cache (Ctrl + F5)" -ForegroundColor White
    Write-Host "2. Visit: https://localhost/EducAid/" -ForegroundColor White
    Write-Host "3. Check the browser tab for your favicon!" -ForegroundColor White
    Write-Host ""
} else {
    Write-Host "⚠️  Some favicon files are missing. Please complete the generation." -ForegroundColor Yellow
}

Write-Host "Press any key to exit..." -ForegroundColor Cyan
$null = $Host.UI.RawUI.ReadKey("NoEcho,IncludeKeyDown")
