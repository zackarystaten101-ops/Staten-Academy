# Cleanup script to remove nested assets directories and prepare for staging

Write-Host "=== Cleaning up nested assets directories ===" -ForegroundColor Cyan

# Remove nested assets directory from filesystem
$nestedPath = "public\assets\js\assets"
if (Test-Path $nestedPath) {
    Write-Host "Removing nested directory: $nestedPath" -ForegroundColor Yellow
    Remove-Item -Path $nestedPath -Recurse -Force -ErrorAction SilentlyContinue
    Write-Host "✓ Removed nested directory" -ForegroundColor Green
} else {
    Write-Host "✓ No nested directory found" -ForegroundColor Green
}

# Remove from Git tracking if it exists
Write-Host "`n=== Removing from Git tracking ===" -ForegroundColor Cyan
git rm -r --cached "public/assets/js/assets" 2>&1 | Out-Null
if ($LASTEXITCODE -eq 0) {
    Write-Host "✓ Removed from Git tracking" -ForegroundColor Green
} else {
    Write-Host "  (Not tracked in Git or already removed)" -ForegroundColor Gray
}

# Configure Git for Windows line endings
Write-Host "`n=== Configuring Git line endings ===" -ForegroundColor Cyan
git config core.autocrlf true
Write-Host "✓ Git line ending normalization enabled" -ForegroundColor Green

Write-Host "`n=== Cleanup complete ===" -ForegroundColor Green
Write-Host "You can now stage your files safely." -ForegroundColor Cyan

