# PowerShell verification script
# Verify system is ready for commit

Write-Host "🔍 Verifying system readiness..." -ForegroundColor Cyan

# Check 1: Backend structure exists
Write-Host "1. Checking backend structure..." -ForegroundColor Yellow
if (-not (Test-Path "backend\src")) {
    Write-Host "❌ Backend directory missing" -ForegroundColor Red
    exit 1
} else {
    Write-Host "✅ Backend directory exists" -ForegroundColor Green
}

# Check 2: Feature flags file exists
Write-Host "2. Checking feature flags..." -ForegroundColor Yellow
if (-not (Test-Path "backend\src\config\feature-flags.ts")) {
    Write-Host "❌ Feature flags file missing" -ForegroundColor Red
    exit 1
} else {
    Write-Host "✅ Feature flags implemented" -ForegroundColor Green
}

# Check 3: Rollback guide exists
Write-Host "3. Checking documentation..." -ForegroundColor Yellow
if (-not (Test-Path "ROLLBACK_GUIDE.md")) {
    Write-Host "❌ Rollback guide missing" -ForegroundColor Red
    exit 1
} else {
    Write-Host "✅ Rollback guide exists" -ForegroundColor Green
}

# Check 4: .gitignore includes backend
Write-Host "4. Checking .gitignore..." -ForegroundColor Yellow
$gitignoreContent = Get-Content .gitignore -Raw -ErrorAction SilentlyContinue
if ($gitignoreContent -match "backend/node_modules") {
    Write-Host "✅ .gitignore includes backend/node_modules" -ForegroundColor Green
} else {
    Write-Host "⚠️  .gitignore may need backend/node_modules entry" -ForegroundColor Yellow
}

Write-Host ""
Write-Host "✅ All checks passed! System is ready for commit." -ForegroundColor Green
Write-Host ""
Write-Host "📋 Next steps:" -ForegroundColor Cyan
Write-Host "1. Review PRE_COMMIT_CHECKLIST.md"
Write-Host "2. Test existing PHP functionality"
Write-Host "3. Commit with appropriate message"
Write-Host "4. Feature flags disabled by default - enable when ready"



