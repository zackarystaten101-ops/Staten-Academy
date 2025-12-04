# Script to Remove Large Files from Git
# This will speed up your commits and pushes

Write-Host "=== Remove Large Files from Git ===" -ForegroundColor Cyan
Write-Host ""

# Check for large files
Write-Host "Checking for large files..." -ForegroundColor Yellow

$largeFiles = @(
    "Zack Introduction.mp4"
    # Add other large files here if needed
)

$foundFiles = @()

foreach ($file in $largeFiles) {
    if (Test-Path $file) {
        $fileInfo = Get-Item $file
        $sizeMB = [math]::Round($fileInfo.Length / 1MB, 2)
        Write-Host "  Found: $file ($sizeMB MB)" -ForegroundColor Yellow
        $foundFiles += $file
    }
}

if ($foundFiles.Count -eq 0) {
    Write-Host "  ✅ No large files found to remove" -ForegroundColor Green
    exit 0
}

Write-Host ""
Write-Host "These files will be removed from Git (but kept locally):" -ForegroundColor Cyan
foreach ($file in $foundFiles) {
    Write-Host "  - $file" -ForegroundColor White
}

Write-Host ""
$confirm = Read-Host "Continue? (Y/N)"

if ($confirm -ne "Y" -and $confirm -ne "y") {
    Write-Host "Cancelled." -ForegroundColor Yellow
    exit 0
}

Write-Host ""
Write-Host "Removing files from Git..." -ForegroundColor Yellow

foreach ($file in $foundFiles) {
    try {
        git rm --cached $file 2>&1 | Out-Null
        if ($LASTEXITCODE -eq 0) {
            Write-Host "  ✅ Removed: $file" -ForegroundColor Green
        } else {
            Write-Host "  ⚠️  Could not remove: $file (may not be tracked)" -ForegroundColor Yellow
        }
    } catch {
        Write-Host "  ⚠️  Error removing: $file" -ForegroundColor Yellow
    }
}

Write-Host ""
Write-Host "=== Next Steps ===" -ForegroundColor Cyan
Write-Host "1. Commit the removal:" -ForegroundColor White
Write-Host "   git commit -m 'Remove large files - improve performance'" -ForegroundColor Gray
Write-Host ""
Write-Host "2. Push to GitHub (will be much faster now!):" -ForegroundColor White
Write-Host "   git push origin main" -ForegroundColor Gray
Write-Host ""
Write-Host "✅ Files are removed from Git but kept on your computer" -ForegroundColor Green
Write-Host "✅ Your commits and pushes will be much faster!" -ForegroundColor Green





