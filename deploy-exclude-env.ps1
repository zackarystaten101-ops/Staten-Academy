# PowerShell Deployment Script
# Uploads all files EXCEPT env.php to protect production credentials
# 
# Usage: 
#   1. Update $serverPath with your FTP path
#   2. Run: .\deploy-exclude-env.ps1
#
# Note: This is a template - adjust for your FTP method

$sourcePath = "C:\xampp\htdocs\Web page\Staten-Academy"
$excludeFiles = @('env.php', '.git', 'node_modules', '.DS_Store', 'Thumbs.db')

Write-Host "=== Deployment Script (Excluding env.php) ===" -ForegroundColor Green
Write-Host "Source: $sourcePath" -ForegroundColor Cyan

# List files that will be excluded
Write-Host "`nExcluding these files/folders:" -ForegroundColor Yellow
foreach ($exclude in $excludeFiles) {
    Write-Host "  - $exclude" -ForegroundColor Yellow
}

# Get all files to upload (excluding env.php and other ignored files)
$filesToUpload = Get-ChildItem -Path $sourcePath -Recurse -File | 
    Where-Object { 
        $excludeFiles -notcontains $_.Name -and 
        $excludeFiles -notcontains $_.Directory.Name -and
        $_.FullName -notlike "*\.git\*" -and
        $_.Name -ne "env.php"
    }

Write-Host "`nFiles ready to upload: $($filesToUpload.Count)" -ForegroundColor Green

# Option 1: Manual FTP Upload Instructions
Write-Host "`n=== MANUAL UPLOAD INSTRUCTIONS ===" -ForegroundColor Cyan
Write-Host "1. Use FileZilla or cPanel File Manager" -ForegroundColor White
Write-Host "2. Upload files from: $sourcePath" -ForegroundColor White
Write-Host "3. IMPORTANT: Skip/Exclude env.php" -ForegroundColor Yellow
Write-Host "4. Your server's env.php will remain untouched" -ForegroundColor Green

# Option 2: Create a ZIP without env.php (if you want to automate)
$zipPath = "$sourcePath\deploy-package.zip"
if (Test-Path $zipPath) {
    Remove-Item $zipPath
}

Write-Host "`n=== CREATING DEPLOYMENT ZIP (without env.php) ===" -ForegroundColor Cyan
$filesToZip = Get-ChildItem -Path $sourcePath -Recurse -File | 
    Where-Object { 
        $_.Name -ne "env.php" -and
        $_.FullName -notlike "*\.git\*" -and
        $excludeFiles -notcontains $_.Name
    }

# Create ZIP (requires .NET 4.5+)
Add-Type -AssemblyName System.IO.Compression.FileSystem
$zip = [System.IO.Compression.ZipFile]::Open($zipPath, [System.IO.Compression.ZipArchiveMode]::Create)

foreach ($file in $filesToZip) {
    $relativePath = $file.FullName.Substring($sourcePath.Length + 1)
    [System.IO.Compression.ZipFileExtensions]::CreateEntryFromFile($zip, $file.FullName, $relativePath) | Out-Null
}

$zip.Dispose()

Write-Host "✅ Created: $zipPath" -ForegroundColor Green
Write-Host "`nUpload this ZIP to your server and extract it." -ForegroundColor Cyan
Write-Host "Your server's env.php will NOT be overwritten!" -ForegroundColor Green

Write-Host "`n=== DONE ===" -ForegroundColor Green





