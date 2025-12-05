# Fix Git Conflict on cPanel - Step by Step

## The Problem
cPanel Git sees `db.php` as having local changes, even though you fixed it. Git won't merge because it thinks your changes would be overwritten.

## Solution: Discard Local Changes and Accept GitHub Version

Since both versions are now the same (fixed), you just need to tell Git to accept the GitHub version.

---

## Method 1: Use cPanel Terminal/SSH (Recommended)

### Step 1: Open Terminal in cPanel
1. Log into cPanel
2. Look for **"Terminal"** or **"SSH Access"** in the Advanced section
3. Click to open it

### Step 2: Run These Commands

```bash
# Navigate to your website directory
cd ~/public_html
# OR if your files are in a subdirectory:
# cd ~/public_html/your-folder-name

# Check current status
git status

# Discard local changes to db.php (accept GitHub version)
git checkout -- db.php

# Now pull from GitHub
git pull origin main
```

This will:
- Discard any local changes to `db.php`
- Pull the fixed version from GitHub
- Resolve the conflict

---

## Method 2: Use cPanel Git Interface (If Available)

Some cPanel versions have a Git interface with options:

1. Go to **Git Version Control** in cPanel
2. Look for **"Discard Changes"** or **"Reset"** option
3. Select `db.php` and choose to discard/reset
4. Then try pulling/merging again

---

## Method 3: Manual Fix via File Manager (If Terminal Not Available)

If you can't use Terminal, manually sync the file:

### Step 1: Check GitHub Version
1. Go to your GitHub repository
2. Open `db.php`
3. Copy the entire file content (especially the end - should end at line 219 with no closing tag)

### Step 2: Update cPanel File
1. In cPanel, open **File Manager**
2. Edit `db.php`
3. Make sure it ends exactly like this (NO closing tag, NO blank lines):

```php
// Add columns to users table for Google Calendar integration if they don't exist
if (!in_array('google_calendar_token', $existing_cols)) $conn->query("ALTER TABLE users ADD COLUMN google_calendar_token LONGTEXT AFTER video_url");
if (!in_array('google_calendar_token_expiry', $existing_cols)) $conn->query("ALTER TABLE users ADD COLUMN google_calendar_token_expiry DATETIME AFTER google_calendar_token");
if (!in_array('google_calendar_refresh_token', $existing_cols)) $conn->query("ALTER TABLE users ADD COLUMN google_calendar_refresh_token LONGTEXT AFTER google_calendar_token_expiry");
```

4. Save the file

### Step 3: Commit the Change (to resolve conflict)
1. Go back to **Git Version Control**
2. Look for option to **"Commit"** or **"Stage Changes"**
3. Commit `db.php` with message: "Sync db.php with GitHub"
4. Then try pulling again

---

## Method 4: Force Accept GitHub Version (Advanced)

If nothing else works, you can force Git to accept GitHub's version:

```bash
cd ~/public_html

# Remove db.php from Git's tracking temporarily
git rm --cached db.php

# Pull from GitHub (this will restore db.php from GitHub)
git pull origin main

# If there are still conflicts, force checkout
git checkout --theirs db.php
git add db.php
git commit -m "Resolve conflict - use GitHub version of db.php"
```

---

## Quick Command Reference

**Most Common Solution:**
```bash
cd ~/public_html
git checkout -- db.php
git pull origin main
```

**If that doesn't work:**
```bash
cd ~/public_html
git stash
git pull origin main
```

**If you need to force it:**
```bash
cd ~/public_html
git fetch origin
git reset --hard origin/main
```

⚠️ **Warning**: `git reset --hard` will discard ALL local changes. Only use if you're sure you want to match GitHub exactly.

---

## After Resolving Conflict

1. ✅ Verify `db.php` is correct (no closing tag)
2. ✅ Pull should work now
3. ✅ All three files (`db.php`, `login.php`, `register.php`) should be synced
4. ✅ Test login and registration

---

## Why This Happens

Git sees the file as "modified" because:
- The file was edited directly on the server (via File Manager)
- Git tracks file metadata (timestamps, line endings, etc.)
- Even if content is the same, Git may see it as different

The solution is to tell Git to accept the GitHub version, which we do by discarding local changes or stashing them.

---

## Troubleshooting

### "Permission denied" error:
```bash
# Fix file permissions
chmod 644 db.php
```

### "Not a git repository" error:
```bash
# Make sure you're in the right directory
pwd
# Should show: /home/username/public_html (or similar)
```

### Still getting conflicts:
1. Check if `db.php` is actually different:
   ```bash
   git diff db.php
   ```
2. If it shows differences, the files aren't actually the same
3. Make sure both versions end at line 219 with no closing tag

---

## Next Steps After Fixing

Once the conflict is resolved:
1. ✅ Pull should work
2. ✅ All files will be synced
3. ✅ Test your login/registration
4. ✅ Errors should be gone!

