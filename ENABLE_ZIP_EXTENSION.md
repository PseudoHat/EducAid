# Enable PHP Zip Extension in XAMPP

## Steps to Enable:

1. Open `C:\xampp\php\php.ini` in a text editor (as Administrator)

2. Find this line (press Ctrl+F and search for "zip"):
   ```
   ;extension=zip
   ```

3. Remove the semicolon (`;`) to uncomment it:
   ```
   extension=zip
   ```

4. Save the file

5. Restart Apache in XAMPP Control Panel

6. Verify it's enabled:
   ```powershell
   php -m | Select-String -Pattern "zip"
   ```
   Should output: `zip`

## Alternative: Quick PowerShell Script

Run this in PowerShell as Administrator:

```powershell
# Backup php.ini
Copy-Item "C:\xampp\php\php.ini" "C:\xampp\php\php.ini.backup"

# Enable zip extension
(Get-Content "C:\xampp\php\php.ini") -replace ';extension=zip', 'extension=zip' | Set-Content "C:\xampp\php\php.ini"

# Verify
php -m | Select-String -Pattern "zip"
```

Then restart Apache.
