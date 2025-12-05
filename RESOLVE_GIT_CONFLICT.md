# Resolve Git Conflict on cPanel Server

## The Problem
Your server has local changes to `db.php` that conflict with the GitHub version. Git won't merge because it would overwrite your local changes.

## Solution: Discard Server's Local Changes (Recommended)

Since you want to use the **fixed version from GitHub**, you should discard the server's local changes.

---

## Step 1: First, Make Sure Fixes Are on GitHub

Before resolving the conflict, ensure your fixed files are pushed to GitHub:

### On Your Local Computer:

```powershell
# Navigate to your project
cd "C:\xampp\htdocs\Web page\Staten-Academy"

# Check status
git status

# Add the fixed files
git add db.php login.php register.php

# Commit
git commit -m "Fix headers already sent errors - remove closing PHP tags and add output buffering"

# Push to GitHub
git push origin main
```

**Wait for this to complete before proceeding to Step 2.**

---

## Step 2: Resolve Conflict on cPanel Server

You have **3 options** to resolve the conflict:

---

### ✅ Option A: Use cPanel Terminal/SSH (Best)

If cPanel has **Terminal** or **SSH Access**:

1. Open **Terminal** in cPanel
2. Run these commands:

```bash
# Navigate to your website directory
cd ~/public_html  # or wherever your site files are

# Discard local changes to db.php (accept GitHub version)
git checkout -- db.php

# Now pull the updates
git pull origin main
```

This will:
- Discard the server's broken `db.php` (with closing `?>` tag)
- Pull the fixed version from GitHub (without closing tag)

---

### ✅ Option B: Use cPanel Git Interface

If cPanel has a Git interface with options:

1. Look for a **"Discard Changes"** or **"Reset"** option for `db.php`
2. Select `db.php` and choose to discard/reset
3. Then try pulling/merging again

---

### ✅ Option C: Manual Fix via File Manager (If Git commands don't work)

If you can't use Git commands:

1. **First, manually fix `db.php` on the server:**
   - Open **File Manager** in cPanel
   - Edit `db.php`
   - Remove the closing `?>` tag at the end (line 220)
   - Remove any blank lines after it
   - Save

2. **Then in Git Version Control:**
   - The file should now match GitHub's version
   - Try pulling/merging again - it should work!

---

## Step 3: Verify the Fix

After resolving the conflict:

1. Check that `db.php` ends correctly (no closing `?>` tag):
   ```php
   if (!in_array('google_calendar_refresh_token', $existing_cols)) $conn->query("ALTER TABLE users ADD COLUMN google_calendar_refresh_token LONGTEXT AFTER google_calendar_token_expiry");
   ```

2. Verify `login.php` and `register.php` are updated (they should have `ob_start()` at the top)

3. Test login and registration - errors should be gone!

---

## What Each File Should Look Like

### db.php (end of file):
```php
// Add columns to users table for Google Calendar integration if they don't exist
if (!in_array('google_calendar_token', $existing_cols)) $conn->query("ALTER TABLE users ADD COLUMN google_calendar_token LONGTEXT AFTER video_url");
if (!in_array('google_calendar_token_expiry', $existing_cols)) $conn->query("ALTER TABLE users ADD COLUMN google_calendar_token_expiry DATETIME AFTER google_calendar_token");
if (!in_array('google_calendar_refresh_token', $existing_cols)) $conn->query("ALTER TABLE users ADD COLUMN google_calendar_refresh_token LONGTEXT AFTER google_calendar_token_expiry");
```
**NO closing `?>` tag, NO blank lines after**

### login.php (beginning):
```php
<?php
// Start output buffering to prevent headers already sent errors
ob_start();

// Load environment configuration first
if (!defined('DB_HOST')) {
    require_once __DIR__ . '/env.php';
}

// Start session before any output
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
```

### register.php (beginning):
```php
<?php
// Start output buffering to prevent headers already sent errors
ob_start();

// Load environment configuration first
if (!defined('DB_HOST')) {
    require_once __DIR__ . '/env.php';
}

// Start session before any output
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
```

---

## Troubleshooting

### If Git commands don't work in cPanel:
- Use **File Manager** to manually edit files
- Upload fixed files via **FTP** or **File Manager**

### If you get "permission denied":
- Check file permissions in File Manager
- Make sure files are writable (644 or 755)

### If errors persist:
- Clear browser cache
- Check PHP error logs in cPanel
- Verify all three files are updated correctly

---

## Summary

1. ✅ Push fixed files to GitHub (from local computer)
2. ✅ Resolve conflict on server (discard local `db.php` changes)
3. ✅ Pull updates from GitHub
4. ✅ Verify files are correct
5. ✅ Test login/registration

The key is: **Discard the server's broken `db.php` and accept the fixed version from GitHub.**

