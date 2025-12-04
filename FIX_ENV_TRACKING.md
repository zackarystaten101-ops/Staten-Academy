# Fix env.php Git Tracking (If Needed)

## Quick Check: Is env.php Tracked by Git?

Run this command to check:
```bash
git ls-files | grep env.php
```

**If it returns nothing:** ✅ You're safe! env.php is not tracked.

**If it shows `env.php`:** ❌ It's tracked and needs to be removed.

## How to Remove env.php from Git Tracking

If env.php is currently tracked by Git, follow these steps:

### Step 1: Remove from Git (but keep local file)

```bash
git rm --cached env.php
```

This removes `env.php` from Git tracking but **keeps the file on your computer**.

### Step 2: Commit the removal

```bash
git commit -m "Remove env.php from Git tracking - contains sensitive credentials"
```

### Step 3: Verify it's removed

```bash
git ls-files | grep env.php
```

Should return nothing (empty).

### Step 4: Push to GitHub

```bash
git push origin main
```

This will remove `env.php` from GitHub (if it was there).

## Verify Protection is Working

### Check 1: .gitignore
```bash
cat .gitignore | grep env.php
```
Should show: `env.php`

### Check 2: Git Status
```bash
git status
```
`env.php` should NOT appear in the list.

### Check 3: Try to Add (Should Fail)
```bash
git add env.php
git status
```
`env.php` should NOT appear (Git will ignore it).

## Windows PowerShell Script

I've created `check-env-safety.ps1` that does all these checks automatically.

Run it:
```powershell
.\check-env-safety.ps1
```

It will tell you:
- ✅ If everything is safe
- ❌ If there are issues
- ⚠️  If there are warnings

## What I've Done to Protect You

1. ✅ **`.gitignore`** - env.php is listed (line 2)
2. ✅ **`.gitattributes`** - Extra protection added
3. ✅ **`check-env-safety.ps1`** - Verification script created
4. ✅ **`.htaccess`** - Protects env.php from web access

## Current Status

Your setup should be:
- ✅ Local: `env.php` with development credentials
- ✅ Server: `env.php` with production credentials (edited via Banahosting)
- ✅ GitHub: NO `env.php` (protected by .gitignore)

## If env.php Was Already on GitHub

If `env.php` was previously committed to GitHub:

1. **Remove it from Git** (steps above)
2. **Remove it from GitHub history** (if it contains sensitive data):
   ```bash
   git filter-branch --force --index-filter \
     "git rm --cached --ignore-unmatch env.php" \
     --prune-empty --tag-name-filter cat -- --all
   ```
3. **Force push** (⚠️ WARNING: This rewrites history):
   ```bash
   git push origin --force --all
   ```
4. **Rotate your credentials** - If production credentials were exposed, change them immediately!

## Best Practices Going Forward

1. **Before every push:**
   - Run `check-env-safety.ps1`
   - Verify `env.php` is not in `git status`

2. **When deploying:**
   - Upload all files EXCEPT `env.php`
   - Server's `env.php` stays untouched

3. **Keep backups:**
   - Backup production `env.php` credentials securely
   - Never store in Git or GitHub

## Summary

✅ **You're protected by:**
- `.gitignore` (primary protection)
- `.gitattributes` (extra protection)
- `.htaccess` (web access protection)

✅ **To verify:**
- Run `check-env-safety.ps1`
- Check `git status` doesn't show env.php

✅ **If env.php is tracked:**
- Run: `git rm --cached env.php`
- Commit and push

---

**Your production credentials on Banahosting are safe!** The server's `env.php` won't be overwritten because it's protected by `.gitignore`.





