# GitHub Setup Guide

This guide will help you upload your code to GitHub.

## Step 1: Install Git

1. Download Git for Windows from: https://git-scm.com/download/win
2. Run the installer with default settings
3. Restart your terminal/PowerShell after installation

## Step 2: Verify Git Installation

Open PowerShell and run:
```powershell
git --version
```

If it shows a version number, Git is installed correctly.

## Step 3: Navigate to Your Project

```powershell
cd "C:\xampp\htdocs\Web page\Staten Accademy Webpage"
```

## Step 4: Initialize Git Repository (if not already done)

```powershell
git init
```

## Step 5: Add Remote Repository

```powershell
git remote add origin https://github.com/zackarystaten101-ops/Staten-Academy.git
```

If the remote already exists, use:
```powershell
git remote set-url origin https://github.com/zackarystaten101-ops/Staten-Academy.git
```

## Step 6: Configure Git (if first time)

```powershell
git config --global user.name "Your Name"
git config --global user.email "your.email@example.com"
```

## Step 7: Add All Files

```powershell
git add .
```

## Step 8: Commit Your Changes

```powershell
git commit -m "Update codebase with latest changes"
```

## Step 9: Push to GitHub

**Option A: If this is your first push or you want to replace everything:**
```powershell
git branch -M main
git push -u origin main --force
```

**Option B: If you want to merge with existing code:**
```powershell
git pull origin main --allow-unrelated-histories
git push -u origin main
```

⚠️ **Warning**: Using `--force` will overwrite all existing code on GitHub. Make sure you want to replace everything before using it.

## Alternative: Use GitHub Desktop

If you prefer a GUI:
1. Download GitHub Desktop: https://desktop.github.com/
2. Sign in with your GitHub account
3. File → Add Local Repository → Select your project folder
4. Click "Publish repository" or "Push origin"

## Troubleshooting

- **Authentication Issues**: GitHub now requires a Personal Access Token instead of password
  - Go to: https://github.com/settings/tokens
  - Generate a new token with `repo` permissions
  - Use the token as your password when pushing

- **Large Files**: If you have files > 100MB, consider using Git LFS or excluding them

