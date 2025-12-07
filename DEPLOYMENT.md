# cPanel Deployment Guide for Staten Academy

## Overview

This guide provides step-by-step instructions for deploying Staten Academy to a cPanel hosting environment.

## Prerequisites

- cPanel hosting account
- PHP 7.4 or higher (PHP 8.0+ recommended)
- MySQL 5.7+ or MariaDB 10.3+
- Access to cPanel File Manager and MySQL Databases

## Pre-Deployment Checklist

- [ ] PHP version meets requirements (7.4+)
- [ ] MySQL/MariaDB database created
- [ ] Database user created with appropriate permissions
- [ ] `env.php` file prepared with production credentials
- [ ] All files use forward slashes (/) in paths
- [ ] No hardcoded Windows paths remain

## Deployment Steps

### 1. Database Setup

1. **Create Database in cPanel:**
   - Log into cPanel
   - Navigate to "MySQL Databases"
   - Create a new database (e.g., `username_statenacademy`)
   - Create a database user with a strong password
   - Add user to database with ALL PRIVILEGES

2. **Note Database Credentials:**
   - Database name: `username_statenacademy`
   - Database user: `username_dbuser`
   - Database host: Usually `localhost` (check cPanel for actual host)
   - Database password: (the one you created)

### 2. Environment Configuration

1. **Create `env.php` file:**
   - Copy `env.example.php` to `env.php` on your local machine
   - Fill in production values:
     ```php
     define('DB_HOST', 'localhost'); // or your cPanel database host
     define('DB_USERNAME', 'your_database_username');
     define('DB_PASSWORD', 'your_database_password');
     define('DB_NAME', 'your_database_name');
     
     define('APP_ENV', 'production');
     define('APP_DEBUG', false); // IMPORTANT: Set to false in production
     
     // Add Stripe keys (get from Stripe Dashboard)
     define('STRIPE_SECRET_KEY', 'sk_live_...');
     define('STRIPE_PUBLISHABLE_KEY', 'pk_live_...');
     
     // Add Google OAuth credentials if using
     define('GOOGLE_CLIENT_ID', '...');
     define('GOOGLE_CLIENT_SECRET', '...');
     define('GOOGLE_REDIRECT_URI', 'https://yourdomain.com/google-calendar-callback.php');
     ```

2. **Upload `env.php` separately:**
   - Use cPanel File Manager or SFTP
   - Upload `env.php` to `public_html/`
   - Set permissions to 600 (read/write for owner only)

### 3. File Upload

**Option A: Using cPanel File Manager**
1. Log into cPanel
2. Open File Manager
3. Navigate to `public_html/`
4. Upload all project files EXCEPT `env.php` (upload that separately)
5. Ensure file structure matches one of these:

   **Structure 1: Flat (Recommended)**
   ```
   public_html/
   ├── index.php
   ├── login.php
   ├── api/
   ├── app/
   ├── assets/          (from public/assets/)
   └── uploads/         (from public/uploads/)
   ```

   **Structure 2: With public/ subdirectory**
   ```
   public_html/
   ├── index.php
   ├── login.php
   ├── api/
   ├── app/
   ├── public/
   │   ├── assets/
   │   └── uploads/
   └── .htaccess
   ```

**Option B: Using SFTP/FTP**
1. Use FileZilla or similar FTP client
2. Connect to your cPanel FTP server
3. Upload all files to `public_html/`
4. Maintain directory structure

### 4. File Permissions

Set the following permissions via cPanel File Manager or SSH:

**Directories:**
- All directories: `755` (rwxr-xr-x)
- Upload directories: `755` (must be writable)

**Files:**
- All PHP files: `644` (rw-r--r--)
- `env.php`: `600` (rw-------) - Owner read/write only
- `.htaccess`: `644`

**Upload Directories (must be writable):**
```bash
chmod 755 public_html/public/uploads/assignments
chmod 755 public_html/public/uploads/materials
chmod 755 public_html/public/uploads/resources
chmod 755 public_html/public/assets/images
```

Or if flat structure:
```bash
chmod 755 public_html/uploads/assignments
chmod 755 public_html/uploads/materials
chmod 755 public_html/uploads/resources
chmod 755 public_html/assets/images
```

### 5. PHP Configuration in cPanel

1. **Set PHP Version:**
   - Go to "Select PHP Version" in cPanel
   - Choose PHP 7.4 or higher (8.0+ recommended)
   - Click "Set as current"

2. **Configure PHP Settings:**
   - In "Select PHP Version", click "Options"
   - Set these values:
     - `upload_max_filesize`: 50M (for material uploads)
     - `post_max_size`: 50M
     - `max_execution_time`: 300
     - `memory_limit`: 256M
   - Enable required extensions:
     - `mysqli`
     - `mbstring`
     - `json`
     - `curl`

### 6. Database Migration

1. **Access Database:**
   - The application will automatically create tables on first access
   - Or use phpMyAdmin to import schema if needed

2. **Verify Tables Created:**
   - Log into phpMyAdmin via cPanel
   - Select your database
   - Verify these tables exist:
     - `users`
     - `subscription_plans`
     - `course_categories`
     - `courses`
     - (and all other tables)

3. **Seed Initial Data:**
   - Course categories and subscription plans are seeded automatically
   - Verify in phpMyAdmin

### 7. SSL Certificate Setup

1. **Enable SSL in cPanel:**
   - Go to "SSL/TLS Status"
   - Install SSL certificate (Let's Encrypt is free)
   - Force HTTPS redirect (update `.htaccess` or use cPanel option)

2. **Update `.htaccess`:**
   - Uncomment HTTPS redirect rules in `.htaccess`

### 8. Domain Configuration

1. **Verify Document Root:**
   - Document root should be `/home/username/public_html/`
   - Check in cPanel "Domains" section

2. **Update Redirect URIs:**
   - Update Google OAuth redirect URI in Google Console
   - Update Stripe webhook URLs if using

## Post-Deployment Verification

### 1. Test Basic Functionality

- [ ] Homepage loads correctly
- [ ] Assets (CSS, JS, images) load
- [ ] Login page works
- [ ] Registration works
- [ ] Database connection successful

### 2. Test File Uploads

- [ ] Profile picture upload works
- [ ] Material upload works
- [ ] Upload directories are writable

### 3. Test Database

- [ ] User registration creates database record
- [ ] Login authenticates correctly
- [ ] All tables exist and are accessible

### 4. Security Checks

- [ ] `env.php` is not publicly accessible (should return 403)
- [ ] Sensitive directories (`/app/`, `/config/`) return 403
- [ ] Error messages don't expose sensitive information
- [ ] SSL certificate is active

## Troubleshooting

### Issue: 500 Internal Server Error

**Solutions:**
1. Check error logs in cPanel ("Error Log")
2. Verify PHP version is 7.4+
3. Check file permissions (644 for files, 755 for directories)
4. Verify `.htaccess` syntax is correct
5. Check `env.php` exists and has correct credentials

### Issue: Assets Not Loading (404 errors)

**Solutions:**
1. Verify asset paths use forward slashes
2. Check `.htaccess` rewrite rules
3. Verify assets directory exists in correct location
4. Check file permissions on asset files

### Issue: Database Connection Failed

**Solutions:**
1. Verify database credentials in `env.php`
2. Check database host (might not be `localhost`)
3. Verify database user has proper permissions
4. Check database name is correct (includes cPanel username prefix)

### Issue: File Uploads Fail

**Solutions:**
1. Check upload directory permissions (must be 755)
2. Verify PHP `upload_max_filesize` and `post_max_size` settings
3. Check disk space quota
4. Verify upload directories exist

### Issue: Session Not Working

**Solutions:**
1. Verify `session_start()` called before any output
2. Check PHP session settings in cPanel
3. Verify session directory is writable
4. Clear browser cookies and try again

## File Structure Reference

### Recommended Structure (Flat)

```
public_html/
├── .htaccess
├── env.php (600 permissions)
├── index.php
├── login.php
├── register.php
├── api/
│   ├── materials.php
│   ├── notifications.php
│   └── ...
├── app/
│   ├── Controllers/
│   ├── Models/
│   ├── Views/
│   └── ...
├── assets/              (from public/assets/)
│   ├── css/
│   ├── js/
│   ├── images/
│   └── logo.png
├── uploads/             (from public/uploads/)
│   ├── assignments/
│   ├── materials/
│   └── resources/
└── config/
```

## Environment Variables Reference

Required in `env.php`:

```php
// Database
DB_HOST
DB_USERNAME
DB_PASSWORD
DB_NAME

// Application
APP_ENV          // 'development' or 'production'
APP_DEBUG        // true or false (MUST be false in production)

// Stripe (if using payments)
STRIPE_SECRET_KEY
STRIPE_PUBLISHABLE_KEY

// Google OAuth (if using)
GOOGLE_CLIENT_ID
GOOGLE_CLIENT_SECRET
GOOGLE_REDIRECT_URI
GOOGLE_SCOPES
```

## Security Best Practices

1. **Never commit `env.php` to version control**
2. **Set `APP_DEBUG = false` in production**
3. **Use strong database passwords**
4. **Keep PHP and all software updated**
5. **Regular backups of database and files**
6. **Monitor error logs regularly**
7. **Use HTTPS (SSL) for all connections**
8. **Set proper file permissions**

## Support

For issues specific to cPanel hosting:
- Check cPanel documentation
- Contact your hosting provider
- Review application error logs in cPanel

## Additional Notes

- cPanel may override some `.htaccess` PHP settings - use PHP Selector in cPanel instead
- Database host might not be `localhost` - check in cPanel MySQL Databases section
- File upload limits are controlled by both PHP settings and cPanel limits
- Some hosts disable certain PHP functions - check with your provider


