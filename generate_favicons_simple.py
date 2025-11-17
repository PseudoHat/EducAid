#!/usr/bin/env python3
"""
EducAid Favicon Generator (Simple Python Version)
Generates all required favicon files from educaid-logo.png
"""

from PIL import Image
import os
import sys

def main():
    print("=" * 50)
    print("EducAid Favicon Generator")
    print("=" * 50)
    print()
    
    # Set paths
    source_logo = r"c:\xampp\htdocs\EducAid\assets\images\educaid-logo.png"
    output_dir = r"c:\xampp\htdocs\EducAid"
    images_dir = os.path.join(output_dir, "assets", "images")
    
    # Check if source exists
    if not os.path.exists(source_logo):
        print(f"❌ ERROR: Source logo not found at {source_logo}")
        sys.exit(1)
    
    print(f"✅ Found source logo: {source_logo}")
    print()
    
    try:
        # Load image
        print("Loading logo...")
        img = Image.open(source_logo)
        
        # Convert to RGBA if needed
        if img.mode != 'RGBA':
            img = img.convert('RGBA')
        
        print(f"Original size: {img.size}")
        print()
        
        # Create output directories if they don't exist
        os.makedirs(images_dir, exist_ok=True)
        
        # Generate favicon-16x16.png
        print("Generating favicon-16x16.png...")
        icon_16 = img.resize((16, 16), Image.Resampling.LANCZOS)
        icon_16.save(os.path.join(images_dir, 'favicon-16x16.png'))
        print("✅ favicon-16x16.png created")
        
        # Generate favicon-32x32.png
        print("Generating favicon-32x32.png...")
        icon_32 = img.resize((32, 32), Image.Resampling.LANCZOS)
        icon_32.save(os.path.join(images_dir, 'favicon-32x32.png'))
        print("✅ favicon-32x32.png created")
        
        # Generate apple-touch-icon.png
        print("Generating apple-touch-icon.png...")
        icon_180 = img.resize((180, 180), Image.Resampling.LANCZOS)
        icon_180.save(os.path.join(images_dir, 'apple-touch-icon.png'))
        print("✅ apple-touch-icon.png created")
        
        # Generate favicon.ico (multi-resolution)
        print("Generating favicon.ico...")
        icon_48 = img.resize((48, 48), Image.Resampling.LANCZOS)
        icon_ico_path = os.path.join(output_dir, 'favicon.ico')
        icon_48.save(icon_ico_path, format='ICO', sizes=[(16, 16), (32, 32), (48, 48)])
        print("✅ favicon.ico created")
        
        # Optional: Generate additional sizes for PWA
        print()
        print("Generating additional PWA icons...")
        
        icon_192 = img.resize((192, 192), Image.Resampling.LANCZOS)
        icon_192.save(os.path.join(images_dir, 'android-chrome-192x192.png'))
        print("✅ android-chrome-192x192.png created")
        
        icon_512 = img.resize((512, 512), Image.Resampling.LANCZOS)
        icon_512.save(os.path.join(images_dir, 'android-chrome-512x512.png'))
        print("✅ android-chrome-512x512.png created")
        
        print()
        print("=" * 50)
        print("🎉 SUCCESS! All favicon files generated!")
        print("=" * 50)
        print()
        print("Files created:")
        print(f"  - {os.path.join(output_dir, 'favicon.ico')}")
        print(f"  - {os.path.join(images_dir, 'favicon-16x16.png')}")
        print(f"  - {os.path.join(images_dir, 'favicon-32x32.png')}")
        print(f"  - {os.path.join(images_dir, 'apple-touch-icon.png')}")
        print(f"  - {os.path.join(images_dir, 'android-chrome-192x192.png')}")
        print(f"  - {os.path.join(images_dir, 'android-chrome-512x512.png')}")
        print()
        print("Next steps:")
        print("1. Clear your browser cache (Ctrl + F5)")
        print("2. Visit: https://localhost/EducAid/")
        print("3. Check the browser tab for your favicon!")
        print()
        
    except Exception as e:
        print(f"❌ ERROR: {str(e)}")
        sys.exit(1)

if __name__ == "__main__":
    main()
