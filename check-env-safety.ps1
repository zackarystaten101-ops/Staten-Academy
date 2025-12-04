# env.php Safety Check Script
# This script verifies that env.php is properly protected from Git
# Run this before pushing to GitHub

Write-Host "=== env.php Safety Check ===" -ForegroundColor Cyan
Write-Host ""

$issues = @()
$warnings = @()

# Check 1: Verify .gitignore contains env.php
Write-Host "Checking .gitignore..." -ForegroundColor Yellow
if (Test-Path ".gitignore") {
    $gitignoreContent = Get-Content ".gitignore" -Raw
    if ($gitignoreContent -match "env\.php") {
        Write-Host "  ✅ env.php is in .gitignore" -ForegroundColor Green
    } else {
        $issues += "env.php is NOT in .gitignore"
        Write-Host "  ❌ env.php is NOT in .gitignore" -ForegroundColor Red
    }
} else {
    $issues += ".gitignore file does not exist"
    Write-Host "  ❌ .gitignore file does not exist" -ForegroundColor Red
}

# Check 2: Verify .gitattributes exists
Write-Host "`nChecking .gitattributes..." -ForegroundColor Yellow
if (Test-Path ".gitattributes") {
    $gitattributesContent = Get-Content ".gitattributes" -Raw
    if ($gitattributesContent -match "env\.php") {
        Write-Host "  ✅ env.php is protected in .gitattributes" -ForegroundColor Green
    } else {
        $warnings += "env.php is not in .gitattributes (optional but recommended)"
        Write-Host "  ⚠️  env.php is not in .gitattributes" -ForegroundColor Yellow
    }
} else {
    $warnings += ".gitattributes file does not exist (optional but recommended)"
    Write-Host "  ⚠️  .gitattributes file does not exist" -ForegroundColor Yellow
}

# Check 3: Check if env.php exists locally
Write-Host "`nChecking local env.php..." -ForegroundColor Yellow
if (Test-Path "env.php") {
    Write-Host "  ✅ env.php exists locally (this is OK for development)" -ForegroundColor Green
    
    # Check if it contains production-like credentials
    $envContent = Get-Content "env.php" -Raw
    if ($envContent -match "localhost" -and $envContent -match "root" -and $envContent -match "''") {
        Write-Host "  ✅ Contains development credentials (localhost, root)" -ForegroundColor Green
    } else {
        $warnings += "env.php might contain production credentials - verify this is for local development only"
        Write-Host "  ⚠️  WARNING: env.php might contain production credentials" -ForegroundColor Yellow
        Write-Host "     Verify this file is for local development only!" -ForegroundColor Yellow
    }
} else {
    Write-Host "  ℹ️  env.php does not exist locally (will be created from env.example.php)" -ForegroundColor Cyan
}

# Check 4: Verify env.example.php exists
Write-Host "`nChecking env.example.php..." -ForegroundColor Yellow
if (Test-Path "env.example.php") {
    Write-Host "  ✅ env.example.php exists (safe template)" -ForegroundColor Green
} else {
    $warnings += "env.example.php does not exist (recommended to have a template)"
    Write-Host "  ⚠️  env.example.php does not exist" -ForegroundColor Yellow
}

# Check 5: Try to check Git status (if Git is available)
Write-Host "`nChecking Git status..." -ForegroundColor Yellow
try {
    $gitStatus = git status --porcelain env.php 2>&1
    if ($LASTEXITCODE -eq 0) {
        if ($gitStatus -match "env\.php") {
            $issues += "env.php appears in Git status - it may be tracked!"
            Write-Host "  ❌ env.php appears in Git status" -ForegroundColor Red
            Write-Host "     Run: git rm --cached env.php" -ForegroundColor Yellow
        } else {
            Write-Host "  ✅ env.php is not tracked by Git" -ForegroundColor Green
        }
    } else {
        Write-Host "  ℹ️  Could not check Git status (Git may not be in PATH)" -ForegroundColor Cyan
    }
} catch {
    Write-Host "  ℹ️  Could not check Git status (Git may not be installed)" -ForegroundColor Cyan
}

# Summary
Write-Host "`n=== SUMMARY ===" -ForegroundColor Cyan
if ($issues.Count -eq 0) {
    Write-Host "✅ All critical checks passed!" -ForegroundColor Green
    Write-Host "   env.php is properly protected" -ForegroundColor Green
} else {
    Write-Host "❌ Issues found:" -ForegroundColor Red
    foreach ($issue in $issues) {
        Write-Host "   - $issue" -ForegroundColor Red
    }
}

if ($warnings.Count -gt 0) {
    Write-Host "`n⚠️  Warnings:" -ForegroundColor Yellow
    foreach ($warning in $warnings) {
        Write-Host "   - $warning" -ForegroundColor Yellow
    }
}

Write-Host "`n=== RECOMMENDATIONS ===" -ForegroundColor Cyan
Write-Host "1. If env.php is tracked by Git, run:" -ForegroundColor White
Write-Host "   git rm --cached env.php" -ForegroundColor Yellow
Write-Host "   git commit -m 'Remove env.php from tracking'" -ForegroundColor Yellow
Write-Host ""
Write-Host "2. Before pushing to GitHub, verify:" -ForegroundColor White
Write-Host "   - env.php is in .gitignore ✅" -ForegroundColor Green
Write-Host "   - env.php is NOT in git status" -ForegroundColor Green
Write-Host ""
Write-Host "3. When deploying to server:" -ForegroundColor White
Write-Host "   - NEVER upload env.php from local to server" -ForegroundColor Yellow
Write-Host "   - Server's env.php should have production credentials" -ForegroundColor Yellow
Write-Host "   - Local env.php should have development credentials" -ForegroundColor Yellow

Write-Host "`n=== DONE ===" -ForegroundColor Green





