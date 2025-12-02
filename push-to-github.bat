@echo off
echo ========================================
echo GitHub Push Script for Staten Academy
echo ========================================
echo.

cd /d "%~dp0"

echo Checking Git installation...
git --version >nul 2>&1
if errorlevel 1 (
    echo ERROR: Git is not installed!
    echo.
    echo Please install Git from: https://git-scm.com/download/win
    echo Then restart this script.
    pause
    exit /b 1
)

echo Git is installed!
echo.

echo Initializing repository (if needed)...
if not exist .git (
    git init
)

echo.
echo Setting up remote repository...
git remote remove origin 2>nul
git remote add origin https://github.com/zackarystaten101-ops/Staten-Academy.git
echo Remote configured: https://github.com/zackarystaten101-ops/Staten-Academy.git
echo.

echo Adding all files...
git add .
echo.

echo Current status:
git status
echo.

echo ========================================
echo Ready to commit and push!
echo ========================================
echo.
echo Next, run these commands:
echo.
echo   git commit -m "Update codebase with latest changes"
echo   git branch -M main
echo   git push -u origin main --force
echo.
echo WARNING: --force will overwrite existing code on GitHub!
echo.
echo Or to merge instead of overwrite:
echo   git pull origin main --allow-unrelated-histories
echo   git push -u origin main
echo.
pause

