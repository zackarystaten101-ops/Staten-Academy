# Protecting env.php During Deployment

## ✅ Good News: Your Setup is Safe!

**`env.php` is already in `.gitignore`**, which means:
- ✅ It will **NOT** be pushed to GitHub
- ✅ Your production credentials on the server are **safe**
- ✅ Your local development credentials stay separate

## How It Works

### Current Situation:
1. **Local (Your Computer):**
   - `env.php` has development credentials (localhost, root, etc.)
   - This file is **ignored by Git** (won't be pushed)

2. **Server (Banahosting):**
   - `env.php` has production credentials (your actual database, etc.)
   - This file is **already on the server** and won't be overwritten

3. **GitHub:**
   - `env.php` is **NOT** in the repository (protected by `.gitignore`)
   - Only `env.example.php` is in GitHub (safe template)

## When You Push Updates

When you push code to GitHub and then deploy:

### ✅ Safe Methods (Recommended):

**Option 1: Upload Files Individually (Safest)**
- Upload files via cPanel File Manager or FTP
- **Skip/Exclude `env.php`** - don't upload it
- Your server's `env.php` stays untouched

**Option 2: Upload ZIP and Exclude env.php**
- Create ZIP of all files except `env.php`
- Upload and extract on server
- Server's `env.php` remains intact

**Option 3: Git Clone (if using Git on server)**
- Since `env.php` is in `.gitignore`, it won't be cloned
- You'll need to manually create `env.php` on server (first time only)
- After that, it stays safe

### ⚠️ Methods to AVOID:

**❌ DON'T: Upload entire folder including env.php**
- This will overwrite your production credentials!

**❌ DON'T: Use `git pull` if env.php was ever committed**
- Check first: `git ls-files | grep env.php`
- If it shows env.php, remove it from Git tracking first

## Best Practice Deployment Workflow

### Step 1: Before Pushing to GitHub
```bash
# Verify env.php is ignored
git status
# env.php should NOT appear in the list

# If it does appear, remove it:
git rm --cached env.php
git commit -m "Remove env.php from tracking"
```

### Step 2: Push to GitHub
```bash
git add .
git commit -m "Your update message"
git push origin main
```
✅ `env.php` will NOT be included in the push

### Step 3: Deploy to Server

**Method A: Selective Upload (Recommended)**
1. Download updated files from GitHub
2. Upload to server via FTP/cPanel
3. **Exclude `env.php`** from upload
4. Your server's `env.php` stays safe

**Method B: Use Deployment Script**
- See `deploy-exclude-env.sh` or `deploy-exclude-env.ps1` below

## Deployment Scripts

### For Windows (PowerShell):
```powershell
# deploy-exclude-env.ps1
# This script uploads all files EXCEPT env.php

$exclude = @('env.php', '.git', 'node_modules', '.DS_Store')
$source = "C:\xampp\htdocs\Web page\Staten-Academy"
$destination = "your-ftp-path/public_html"

# Use your FTP client to upload, excluding env.php
# Or use FileZilla with filter: -env.php
```

### For Linux/Mac (Bash):
```bash
#!/bin/bash
# deploy-exclude-env.sh

# Sync files excluding env.php
rsync -avz --exclude='env.php' \
  --exclude='.git' \
  --exclude='node_modules' \
  ./ user@server:/path/to/public_html/
```

## Verification Checklist

Before deploying, verify:

- [ ] `env.php` is in `.gitignore` ✅ (Already done)
- [ ] `env.php` is NOT tracked by Git:
  ```bash
  git ls-files | grep env.php
  # Should return nothing
  ```
- [ ] Server has `env.php` with production credentials
- [ ] Local has `env.php` with development credentials
- [ ] You have a backup of production `env.php` credentials

## What to Do If env.php Gets Overwritten

**If you accidentally overwrite server's env.php:**

1. **Don't panic!** Your credentials are still in your notes/backup
2. Recreate `env.php` on server with production credentials
3. Verify `.htaccess` protects it (should show 403 error)
4. Update `.gitignore` if needed
5. Remove from Git if it was accidentally committed:
   ```bash
   git rm --cached env.php
   git commit -m "Remove env.php from tracking"
   ```

## Current Status Check

Run these commands to verify everything is safe:

```bash
# Check if env.php is ignored
git status --ignored | grep env.php
# Should show: "env.php" under "Ignored files"

# Check if env.php is tracked
git ls-files | grep env.php
# Should return nothing (empty)

# Verify .gitignore contains env.php
cat .gitignore | grep env.php
# Should show: "env.php"
```

## Summary

✅ **You're Safe!** Your current setup is correct:
- `env.php` is in `.gitignore` ✅
- Local has development credentials ✅
- Server has production credentials ✅
- Pushing to GitHub won't affect server's `env.php` ✅

**Just remember:** When deploying updates, don't upload `env.php` to the server, and your production credentials will stay safe!

---

**Pro Tip:** Keep a secure backup of your production `env.php` credentials in a password manager or secure note, just in case!





