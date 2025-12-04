# Server Database Error Fix

## 🚨 The Problem

Your server is showing this error:
```
You have an error in your SQL syntax; check the manual that corresponds to your MariaDB server version for the right syntax to use near 'Academy' at line 1
```

**Root Cause:** The database name in your `env.php` on the server likely has a **space** in it (like "Staten Academy" instead of "staten_academy").

## ✅ Fixes Applied

1. **Fixed `db.php`** - Now properly escapes database names with backticks
2. **Fixed `index.php`** - Added output buffering to prevent "headers already sent" errors
3. **Removed duplicate code** - Cleaned up redundant includes

## 🔧 What You Need to Do on Server

### Step 1: Upload Fixed Files

Upload these updated files to your Banahosting server:
- `db.php` (fixed database name escaping)
- `index.php` (fixed session handling)

### Step 2: Check Your Database Name

1. **Log into Banahosting cPanel**
2. **Go to "MySQL Databases"**
3. **Check your database name:**
   - Look at "Current Databases"
   - Note the exact name

### Step 3: Update env.php on Server

**Option A: If database name has NO spaces (recommended):**
```php
define('DB_NAME', 'staten_academy'); // or whatever your actual name is
```

**Option B: If database name HAS spaces:**
The fix I applied will handle it, but make sure `env.php` has the exact name:
```php
define('DB_NAME', 'Staten Academy'); // exact name with space
```

### Step 4: Verify Database Name

The database name in `env.php` must **exactly match** the database name in cPanel:
- Check cPanel → MySQL Databases
- Copy the exact database name
- Paste it into `env.php` (with or without spaces, but must match exactly)

## 📋 Quick Checklist

- [ ] Upload updated `db.php` to server
- [ ] Upload updated `index.php` to server  
- [ ] Check database name in cPanel
- [ ] Update `env.php` on server with exact database name
- [ ] Test website - error should be gone

## 🔍 How to Find Your Database Name

1. **In cPanel:**
   - MySQL Databases → Current Databases
   - Copy the exact name shown

2. **In phpMyAdmin:**
   - Open phpMyAdmin
   - Look at left sidebar
   - Database names are listed there

3. **Common formats:**
   - `yourusername_statenacademy`
   - `yourusername_staten_academy`
   - `staten_academy`
   - `Staten Academy` (with space - not recommended)

## ⚠️ Important

- **Database names with spaces work** but are not recommended
- **Use underscores instead:** `staten_academy` not `staten academy`
- **The fix handles spaces**, but it's better to rename the database

## Summary

✅ **Fixed:** Database name escaping in `db.php`
✅ **Fixed:** Session handling in `index.php`
✅ **Action:** Upload files and verify database name in `env.php`

**Upload the fixed files and your website should work!**

