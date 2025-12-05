# Fix cPanel Git Merge Error for db.php

## Problem
cPanel Git is showing this error:
```
error: Your local changes to the following files would be overwritten by merge: db.php
Please commit your changes or stash them before you merge.
```

## Solution: Choose One Method

---

## ✅ Method 1: Fix via cPanel File Manager (Easiest - Recommended)

### Step 1: Open File Manager
1. Log into cPanel
2. Go to **File Manager**
3. Navigate to your website's root directory (usually `public_html`)

### Step 2: Edit db.php
1. Find `db.php` in the file list
2. Right-click → **Edit** (or double-click)
3. Scroll to the **very end** of the file

### Step 3: Remove the Closing Tag
**Find these lines at the end:**
```php
if (!in_array('google_calendar_refresh_token', $existing_cols)) $conn->query("ALTER TABLE users ADD COLUMN google_calendar_refresh_token LONGTEXT AFTER google_calendar_token_expiry");
?>
```

**Change to (remove `?>` and any blank lines after it):**
```php
if (!in_array('google_calendar_refresh_token', $existing_cols)) $conn->query("ALTER TABLE users ADD COLUMN google_calendar_refresh_token LONGTEXT AFTER google_calendar_token_expiry");
```

**The file should end at line 219 with NO closing `?>` tag and NO blank lines after it.**

### Step 4: Save
1. Click **Save Changes**
2. Close the editor

### Step 5: Retry Git Pull/Merge
1. Go back to **Git Version Control** in cPanel
2. Try pulling/merging again - it should work now!

---

## ✅ Method 2: Use Git Commands (If you have SSH/Terminal access)

### Option A: Stash Changes, Pull, Then Fix
```bash
# Navigate to your website directory
cd ~/public_html  # or wherever your site is

# Stash the local changes (saves them temporarily)
git stash

# Now pull the updates
git pull origin main

# Apply your fix to db.php (remove closing ?> tag)
# Edit db.php and remove the closing ?> tag

# Commit the fix
git add db.php
git commit -m "Fix db.php - remove closing PHP tag"
```

### Option B: Discard Local Changes (If you want to replace with GitHub version)
```bash
# Navigate to your website directory
cd ~/public_html

# Discard local changes to db.php
git checkout -- db.php

# Now pull the updates
git pull origin main
```

---

## ✅ Method 3: Use cPanel Terminal (If Available)

1. In cPanel, go to **Terminal** or **SSH Access**
2. Run these commands:
```bash
cd ~/public_html
git stash
git pull origin main
```
3. Then manually edit `db.php` via File Manager to remove the closing `?>` tag

---

## What to Fix in db.php

The file should end like this (NO closing tag, NO blank lines):

```php
// Add columns to users table for Google Calendar integration if they don't exist
if (!in_array('google_calendar_token', $existing_cols)) $conn->query("ALTER TABLE users ADD COLUMN google_calendar_token LONGTEXT AFTER video_url");
if (!in_array('google_calendar_token_expiry', $existing_cols)) $conn->query("ALTER TABLE users ADD COLUMN google_calendar_token_expiry DATETIME AFTER google_calendar_token");
if (!in_array('google_calendar_refresh_token', $existing_cols)) $conn->query("ALTER TABLE users ADD COLUMN google_calendar_refresh_token LONGTEXT AFTER google_calendar_token_expiry");
```

**NOT like this:**
```php
if (!in_array('google_calendar_refresh_token', $existing_cols)) $conn->query("ALTER TABLE users ADD COLUMN google_calendar_refresh_token LONGTEXT AFTER google_calendar_token_expiry");
?>

```

---

## After Fixing

1. ✅ The Git merge should work
2. ✅ Upload the fixed `login.php` and `register.php` files
3. ✅ Test login and registration - errors should be gone!

---

## Quick Reference: What Changed

- **db.php**: Removed closing `?>` tag (line 220) and trailing whitespace
- **login.php**: Added output buffering and proper session handling
- **register.php**: Added output buffering and proper session handling

All three files need to be updated on the server to fix the "headers already sent" errors.

