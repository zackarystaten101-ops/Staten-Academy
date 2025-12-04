# Quick Commit Script
# Tries multiple methods to commit your changes

Write-Host "=== Quick Commit Script ===" -ForegroundColor Cyan
Write-Host ""

$projectPath = "C:\xampp\htdocs\Web page\Staten-Academy"
Set-Location $projectPath

# Try to find Git
$gitPath = $null

# Check common Git locations
$possiblePaths = @(
    "C:\Program Files\Git\bin\git.exe",
    "C:\Program Files (x86)\Git\bin\git.exe",
    "$env:LOCALAPPDATA\Programs\Git\bin\git.exe",
    "git"  # If in PATH
)

foreach ($path in $possiblePaths) {
    if ($path -eq "git") {
        try {
            $null = & git --version 2>$null
            if ($LASTEXITCODE -eq 0) {
                $gitPath = "git"
                break
            }
        } catch {
            continue
        }
    } else {
        if (Test-Path $path) {
            $gitPath = $path
            break
        }
    }
}

if (-not $gitPath) {
    Write-Host "❌ Git not found!" -ForegroundColor Red
    Write-Host ""
    Write-Host "Options:" -ForegroundColor Yellow
    Write-Host "1. Install Git: https://git-scm.com/download/win" -ForegroundColor White
    Write-Host "2. Use GitHub Desktop: https://desktop.github.com/" -ForegroundColor White
    Write-Host "3. Use Git Bash (if installed)" -ForegroundColor White
    Write-Host ""
    Write-Host "Git Bash Instructions:" -ForegroundColor Cyan
    Write-Host "1. Open 'Git Bash' from Start Menu" -ForegroundColor White
    Write-Host "2. Run these commands:" -ForegroundColor White
    Write-Host "   cd '/c/xampp/htdocs/Web page/Staten-Academy'" -ForegroundColor Gray
    Write-Host "   git add ." -ForegroundColor Gray
    Write-Host "   git commit -m 'Enhance admin actions and dashboard functionality'" -ForegroundColor Gray
    Write-Host "   git push origin main" -ForegroundColor Gray
    exit 1
}

Write-Host "✅ Found Git: $gitPath" -ForegroundColor Green
Write-Host ""

# Commit message
$commitMessage = "Enhance admin actions and dashboard functionality; auto-approve pending profile updates for teachers, unify pending requests tab, and improve Google Calendar integration for students."

Write-Host "Commit message:" -ForegroundColor Cyan
Write-Host $commitMessage -ForegroundColor Gray
Write-Host ""

# Stage all files
Write-Host "Staging files..." -ForegroundColor Yellow
if ($gitPath -eq "git") {
    & git add . 2>&1 | Out-Null
} else {
    & $gitPath add . 2>&1 | Out-Null
}

if ($LASTEXITCODE -ne 0) {
    Write-Host "⚠️  Warning: Some files may not have been staged" -ForegroundColor Yellow
}

# Check if there are changes to commit
Write-Host "Checking for changes..." -ForegroundColor Yellow
if ($gitPath -eq "git") {
    $status = & git status --porcelain 2>&1
} else {
    $status = & $gitPath status --porcelain 2>&1
}

if (-not $status -or $status.Count -eq 0) {
    Write-Host "ℹ️  No changes to commit" -ForegroundColor Cyan
    Write-Host "   All files are already committed" -ForegroundColor Gray
    exit 0
}

# Commit
Write-Host "Committing changes..." -ForegroundColor Yellow
if ($gitPath -eq "git") {
    & git commit -m $commitMessage 2>&1
} else {
    & $gitPath commit -m $commitMessage 2>&1
}

if ($LASTEXITCODE -eq 0) {
    Write-Host "✅ Commit successful!" -ForegroundColor Green
    Write-Host ""
    Write-Host "Next step: Push to GitHub" -ForegroundColor Cyan
    Write-Host "Run: git push origin main" -ForegroundColor Gray
    Write-Host ""
    Write-Host "Or use GitHub Desktop to push." -ForegroundColor Gray
} else {
    Write-Host "❌ Commit failed" -ForegroundColor Red
    Write-Host ""
    Write-Host "Try using GitHub Desktop instead:" -ForegroundColor Yellow
    Write-Host "1. Download: https://desktop.github.com/" -ForegroundColor White
    Write-Host "2. Add your repository" -ForegroundColor White
    Write-Host "3. Commit and push from there" -ForegroundColor White
}




