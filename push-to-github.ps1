# PowerShell script to push code to GitHub
# Run this script after installing Git

Write-Host "=== GitHub Push Script ===" -ForegroundColor Cyan
Write-Host ""

# Check if Git is installed
try {
    $gitVersion = git --version
    Write-Host "✓ Git is installed: $gitVersion" -ForegroundColor Green
} catch {
    Write-Host "✗ Git is not installed!" -ForegroundColor Red
    Write-Host "Please install Git from: https://git-scm.com/download/win" -ForegroundColor Yellow
    Write-Host "Then restart PowerShell and run this script again." -ForegroundColor Yellow
    exit 1
}

# Navigate to project directory
$projectPath = Split-Path -Parent $MyInvocation.MyCommand.Path
Set-Location $projectPath
Write-Host "Current directory: $projectPath" -ForegroundColor Cyan
Write-Host ""

# Initialize git if needed
if (-not (Test-Path .git)) {
    Write-Host "Initializing Git repository..." -ForegroundColor Yellow
    git init
}

# Set remote
$remoteUrl = "https://github.com/zackarystaten101-ops/Staten-Academy.git"
Write-Host "Setting remote repository..." -ForegroundColor Yellow
$existingRemote = git remote get-url origin 2>$null
if ($existingRemote) {
    if ($existingRemote -ne $remoteUrl) {
        git remote set-url origin $remoteUrl
        Write-Host "✓ Updated remote URL" -ForegroundColor Green
    } else {
        Write-Host "✓ Remote already configured correctly" -ForegroundColor Green
    }
} else {
    git remote add origin $remoteUrl
    Write-Host "✓ Added remote repository" -ForegroundColor Green
}

Write-Host ""
Write-Host "=== Ready to push ===" -ForegroundColor Cyan
Write-Host ""
Write-Host "Next steps:" -ForegroundColor Yellow
Write-Host "1. Review the files to be committed:" -ForegroundColor White
Write-Host "   git status" -ForegroundColor Gray
Write-Host ""
Write-Host "2. Add all files:" -ForegroundColor White
Write-Host "   git add ." -ForegroundColor Gray
Write-Host ""
Write-Host "3. Commit your changes:" -ForegroundColor White
Write-Host "   git commit -m 'Update codebase with latest changes'" -ForegroundColor Gray
Write-Host ""
Write-Host "4. Push to GitHub (WARNING: This will overwrite existing code):" -ForegroundColor White
Write-Host "   git branch -M main" -ForegroundColor Gray
Write-Host "   git push -u origin main --force" -ForegroundColor Gray
Write-Host ""
Write-Host "OR to merge with existing code:" -ForegroundColor White
Write-Host "   git pull origin main --allow-unrelated-histories" -ForegroundColor Gray
Write-Host "   git push -u origin main" -ForegroundColor Gray
Write-Host ""
Write-Host "Note: You may need to authenticate with a Personal Access Token." -ForegroundColor Yellow
Write-Host "Get one from: https://github.com/settings/tokens" -ForegroundColor Yellow

