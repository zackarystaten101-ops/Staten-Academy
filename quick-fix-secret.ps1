# Quick Fix for GitHub Secret Detection
# This script helps remove the secret from Git history

Write-Host "=== Fix GitHub Secret Detection ===" -ForegroundColor Cyan
Write-Host ""

$projectPath = "C:\xampp\htdocs\Web page\Staten-Academy"
Set-Location $projectPath

Write-Host "This script will help you fix the GitHub secret detection error." -ForegroundColor Yellow
Write-Host ""
Write-Host "The secret (Stripe API key) is in commit: 9bd5447ca050223685c858e86ded829c6d641b03" -ForegroundColor Yellow
Write-Host ""

Write-Host "=== Option 1: Reset and Recommit (Easiest) ===" -ForegroundColor Cyan
Write-Host ""
Write-Host "This will reset to before the bad commit and recommit everything:" -ForegroundColor White
Write-Host ""
Write-Host "Steps:" -ForegroundColor Yellow
Write-Host "1. Find the commit BEFORE the bad one:" -ForegroundColor White
Write-Host "   git log --oneline" -ForegroundColor Gray
Write-Host ""
Write-Host "2. Reset to that commit (keep your changes):" -ForegroundColor White
Write-Host "   git reset --soft <commit-hash-before-bad-one>" -ForegroundColor Gray
Write-Host ""
Write-Host "3. Re-commit everything (with fixed file):" -ForegroundColor White
Write-Host "   git add ." -ForegroundColor Gray
Write-Host "   git commit -m 'Add deployment guides and fixes (with safe placeholders)'" -ForegroundColor Gray
Write-Host ""
Write-Host "4. Force push:" -ForegroundColor White
Write-Host "   git push origin main --force" -ForegroundColor Gray
Write-Host ""

Write-Host "=== Option 2: Use Git Bash ===" -ForegroundColor Cyan
Write-Host ""
Write-Host "If you have Git Bash installed:" -ForegroundColor White
Write-Host ""
Write-Host "1. Open Git Bash" -ForegroundColor Yellow
Write-Host "2. Run:" -ForegroundColor Yellow
Write-Host "   cd '/c/xampp/htdocs/Web page/Staten-Academy'" -ForegroundColor Gray
Write-Host "   git log --oneline -10" -ForegroundColor Gray
Write-Host "   # Find commit BEFORE 9bd5447" -ForegroundColor Gray
Write-Host "   git reset --soft <commit-hash>" -ForegroundColor Gray
Write-Host "   git add ." -ForegroundColor Gray
Write-Host "   git commit -m 'Add deployment guides and fixes'" -ForegroundColor Gray
Write-Host "   git push origin main --force" -ForegroundColor Gray
Write-Host ""

Write-Host "=== Option 3: Use GitHub Desktop ===" -ForegroundColor Cyan
Write-Host ""
Write-Host "1. Open GitHub Desktop" -ForegroundColor White
Write-Host "2. View → Show Git History" -ForegroundColor White
Write-Host "3. Right-click on commit BEFORE the bad one" -ForegroundColor White
Write-Host "4. Select 'Revert this commit' or 'Reset to this commit'" -ForegroundColor White
Write-Host "5. Then recommit your changes" -ForegroundColor White
Write-Host ""

Write-Host "=== IMPORTANT: Rotate Your Stripe Keys ===" -ForegroundColor Red
Write-Host ""
Write-Host "Since the keys were exposed in a commit, you MUST:" -ForegroundColor Yellow
Write-Host "1. Go to: https://dashboard.stripe.com/apikeys" -ForegroundColor White
Write-Host "2. Revoke the old keys" -ForegroundColor White
Write-Host "3. Generate new keys" -ForegroundColor White
Write-Host "4. Update env.php on your server with new keys" -ForegroundColor White
Write-Host ""

Write-Host "=== DONE ===" -ForegroundColor Green
Write-Host "Choose one of the options above to fix the push error." -ForegroundColor Yellow

