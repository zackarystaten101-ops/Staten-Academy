# Fix Database SQL Syntax Error

## 🚨 Problem

**Error:** `You have an error in your SQL syntax; check the manual that corresponds to your MariaDB server version for the right syntax to use near 'Academy' at line 1`

**Cause:** The database name in `env.php` on your server likely has a **space** in it (like "Staten Academy" instead of "staten_academy").

## ✅ Fix Applied

I've updated `db.php` to properly escape database names with backticks, which handles:
- Spaces in database names
- Special characters
- Reserved words

## 🔧 What You Need to Do

### Option 1: Fix Database Name (Recommended)

**Best solution:** Use a database name without spaces.

1. **In Banahosting cPanel:**
   - Go to MySQL Databases
   - Check your database name
   - If it has a space, create a new database with underscores:
     - ❌ Bad: `staten academy` or `Staten Academy`
     - ✅ Good: `staten_academy` or `statenacademy`

2. **Update `env.php` on server:**
   ```php
   define('DB_NAME', 'staten_academy'); // No spaces!
   ```

3. **Update database user permissions:**
   - Grant the user access to the new database

### Option 2: Keep Current Database Name

If you want to keep the database name with spaces:

1. **The fix I applied will handle it** - database names are now escaped with backticks
2. **Just upload the updated `db.php` file** to your server
3. **Make sure `env.php` has the exact database name** (with spaces if that's what it is)

## 📋 Steps to Fix

1. **Upload the fixed `db.php` file** to your server
   - Replace the old `db.php` in `public_html`

2. **Verify `env.php` on server:**
   - Check the `DB_NAME` value
   - Make sure it matches your actual database name exactly

3. **Test your website:**
   - Visit your domain
   - The error should be gone

## 🔍 How to Check Your Database Name

1. **In Banahosting cPanel:**
   - Go to "MySQL Databases"
   - Look at "Current Databases"
   - Note the exact name (including any spaces)

2. **In phpMyAdmin:**
   - Go to phpMyAdmin
   - Look at the database list on the left
   - See the exact name

3. **Update `env.php` to match exactly:**
   ```php
   define('DB_NAME', 'exact_database_name_here');
   ```

## ⚠️ Important Notes

- **Database names with spaces are not recommended** - use underscores instead
- **The fix I applied will work** with spaces, but it's better to rename the database
- **Make sure `env.php` on server matches the actual database name**

## Summary

✅ **Fixed:** `db.php` now properly escapes database names
✅ **Action:** Upload updated `db.php` to server
✅ **Verify:** Check `env.php` database name matches your actual database

**Upload the fixed `db.php` file and your website should work!**

