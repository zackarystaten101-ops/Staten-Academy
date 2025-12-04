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

# Safety check: Verify env.php is not tracked
Write-Host ""
Write-Host "=== Safety Check: env.php Protection ===" -ForegroundColor Cyan
$envInGit = git ls-files env.php 2>$null
if ($envInGit) {
    Write-Host "❌ WARNING: env.php is tracked by Git!" -ForegroundColor Red
    Write-Host "   This is DANGEROUS - it contains sensitive credentials!" -ForegroundColor Red
    Write-Host ""
    Write-Host "   To fix, run:" -ForegroundColor Yellow
    Write-Host "   git rm --cached env.php" -ForegroundColor Yellow
    Write-Host "   git commit -m 'Remove env.php from tracking'" -ForegroundColor Yellow
    Write-Host ""
    Write-Host "   Then run this script again." -ForegroundColor Yellow
    exit 1
} else {
    Write-Host "✅ env.php is NOT tracked by Git (safe)" -ForegroundColor Green
}

# Check if env.php is in gitignore
if (Test-Path ".gitignore") {
    $gitignoreContent = Get-Content ".gitignore" -Raw
    if ($gitignoreContent -match "env\.php") {
        Write-Host "✅ env.php is in .gitignore (protected)" -ForegroundColor Green
    } else {
        Write-Host "⚠️  WARNING: env.php is NOT in .gitignore" -ForegroundColor Yellow
        Write-Host "   Adding it now..." -ForegroundColor Yellow
        Add-Content -Path ".gitignore" -Value "`nenv.php"
        Write-Host "✅ Added env.php to .gitignore" -ForegroundColor Green
    }
}

Write-Host ""
Write-Host "=== Ready to push ===" -ForegroundColor Cyan
Write-Host ""
Write-Host "Next steps:" -ForegroundColor Yellow
Write-Host "1. Review the files to be committed:" -ForegroundColor White
Write-Host "   git status" -ForegroundColor Gray
Write-Host "   (env.php should NOT appear in the list)" -ForegroundColor Gray
Write-Host ""
Write-Host "2. Add all files:" -ForegroundColor White
Write-Host "   git add ." -ForegroundColor Gray
Write-Host "   (env.php will be automatically excluded)" -ForegroundColor Gray
Write-Host ""
Write-Host "3. Verify env.php is not included:" -ForegroundColor White
Write-Host "   git status" -ForegroundColor Gray
Write-Host "   (env.php should still NOT appear)" -ForegroundColor Gray
Write-Host ""
Write-Host "4. Commit your changes:" -ForegroundColor White
Write-Host "   git commit -m 'Update codebase with latest changes'" -ForegroundColor Gray
Write-Host ""
Write-Host "5. Push to GitHub:" -ForegroundColor White
Write-Host "   git branch -M main" -ForegroundColor Gray
Write-Host "   git push -u origin main --force" -ForegroundColor Gray
Write-Host ""
Write-Host "OR to merge with existing code:" -ForegroundColor White
Write-Host "   git pull origin main --allow-unrelated-histories" -ForegroundColor Gray
Write-Host "   git push -u origin main" -ForegroundColor Gray
Write-Host ""
Write-Host "Note: You may need to authenticate with a Personal Access Token." -ForegroundColor Yellow
Write-Host "Get one from: https://github.com/settings/tokens" -ForegroundColor Yellow
Write-Host ""
Write-Host "✅ Your production env.php on the server is safe!" -ForegroundColor Green
Write-Host "   It will NOT be overwritten when you push." -ForegroundColor Green

