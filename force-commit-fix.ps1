# Force Commit Fix Script
# This will help unstick a hanging commit

Write-Host "=== Force Commit Fix ===" -ForegroundColor Cyan
Write-Host ""

$projectPath = "C:\xampp\htdocs\Web page\Staten-Academy"
Set-Location $projectPath

# Step 1: Check for lock files
Write-Host "Step 1: Checking for lock files..." -ForegroundColor Yellow
$lockFiles = @(
    ".git\index.lock",
    ".git\COMMIT_EDITMSG.lock",
    ".git\*.lock"
)

$foundLocks = $false
foreach ($pattern in $lockFiles) {
    $files = Get-ChildItem -Path $pattern -ErrorAction SilentlyContinue
    if ($files) {
        Write-Host "  ⚠️  Found lock file: $($files.Name)" -ForegroundColor Yellow
        Write-Host "     Removing..." -ForegroundColor Yellow
        Remove-Item $files.FullName -Force
        $foundLocks = $true
    }
}

if ($foundLocks) {
    Write-Host "  ✅ Lock files removed" -ForegroundColor Green
} else {
    Write-Host "  ✅ No lock files found" -ForegroundColor Green
}

# Step 2: Verify .gitattributes is fixed
Write-Host "`nStep 2: Verifying .gitattributes..." -ForegroundColor Yellow
if (Test-Path ".gitattributes") {
    $content = Get-Content ".gitattributes" -Raw
    if ($content -match "filter=gitignore") {
        Write-Host "  ❌ .gitattributes still has invalid filters!" -ForegroundColor Red
        Write-Host "     This needs to be fixed first." -ForegroundColor Yellow
    } else {
        Write-Host "  ✅ .gitattributes looks good (no invalid filters)" -ForegroundColor Green
    }
}

# Step 3: Try to abort any hanging commit
Write-Host "`nStep 3: Checking for hanging processes..." -ForegroundColor Yellow
Write-Host "  ℹ️  If VS Code is stuck, try:" -ForegroundColor Cyan
Write-Host "     1. Close VS Code completely" -ForegroundColor White
Write-Host "     2. Reopen VS Code" -ForegroundColor White
Write-Host "     3. Try committing again" -ForegroundColor White

# Step 4: Provide command line alternative
Write-Host "`n=== Alternative: Use Command Line ===" -ForegroundColor Cyan
Write-Host ""
Write-Host "If VS Code is still stuck, use Git Bash:" -ForegroundColor Yellow
Write-Host ""
Write-Host "1. Open Git Bash (from Start Menu)" -ForegroundColor White
Write-Host "2. Run these commands:" -ForegroundColor White
Write-Host ""
Write-Host "   cd '/c/xampp/htdocs/Web page/Staten-Academy'" -ForegroundColor Gray
Write-Host "   git add ." -ForegroundColor Gray
Write-Host "   git commit --no-verify -m 'Fix commit hang and update deployment files'" -ForegroundColor Gray
Write-Host ""
Write-Host "   (--no-verify bypasses any hooks that might be causing issues)" -ForegroundColor Yellow
Write-Host ""

# Step 5: Check if there are staged changes
Write-Host "=== Current Status ===" -ForegroundColor Cyan
Write-Host "If you have staged changes, you can:" -ForegroundColor Yellow
Write-Host "1. Complete the commit via command line (above)" -ForegroundColor White
Write-Host "2. Or unstage everything and start fresh:" -ForegroundColor White
Write-Host "   git reset" -ForegroundColor Gray
Write-Host ""

Write-Host "=== DONE ===" -ForegroundColor Green


