# Staten Academy - Production Deployment Guide

This guide will walk you through deploying your Staten Academy website from GitHub to Banahosting.

## Prerequisites

Before starting, ensure you have:
- ✅ Banahosting cPanel access credentials
- ✅ Your domain name from Namecheap
- ✅ Database credentials from Banahosting (or ready to create)
- ✅ Google Cloud Console access (for OAuth updates)
- ✅ Stripe account with production API keys

---

## Step 1: Database Setup on Banahosting

1. **Log into Banahosting cPanel**
   - Go to your Banahosting account
   - Access cPanel (usually at `yourdomain.com/cpanel` or provided URL)

2. **Create MySQL Database**
   - Navigate to "MySQL Databases" or "MySQL Database Wizard"
   - Create a new database (e.g., `yourusername_statenacademy`)
   - **Save the full database name** (usually includes your username prefix)

3. **Create Database User**
   - Create a new MySQL user with a strong password
   - **Save the username and password securely**

4. **Grant Privileges**
   - Add the user to the database
   - Grant "ALL PRIVILEGES" to the user
   - **Note the database hostname** (usually `localhost` for shared hosting)

5. **Import Database Schema**
   - Go to "phpMyAdmin" in cPanel
   - Select your database
   - Click "Import" tab
   - Upload `setup-tables.sql` file
   - Click "Go" to import
   - **OR** the database will auto-create tables on first page load via `db.php`

**Save these credentials:**
- Database Host: `localhost` (usually)
- Database Name: `yourusername_statenacademy`
- Database Username: `yourusername_dbuser`
- Database Password: `your_secure_password`

---

## Step 2: Deploy Files to Banahosting

Choose one of the following methods:

### Option A: Using cPanel File Manager (Recommended for beginners)

1. **Download Repository from GitHub**
   - Go to: https://github.com/zackarystaten101-ops/Staten-Academy
   - Click "Code" → "Download ZIP"
   - Extract the ZIP file on your computer

2. **Upload to Banahosting**
   - In cPanel, open "File Manager"
   - Navigate to `public_html` folder (or your domain's root directory)
   - Delete any default files (like `index.html`) if present
   - Upload all files from the extracted folder
   - **Important:** Upload files directly to `public_html`, not in a subfolder

3. **Set File Permissions**
   - Right-click on folders → Change Permissions → Set to `755`
   - Right-click on files → Change Permissions → Set to `644`
   - For `images/` folder, ensure it's `755` (writable for uploads)

### Option B: Using FTP/SFTP (Recommended for larger deployments)

1. **Get FTP Credentials**
   - In cPanel, go to "FTP Accounts"
   - Note your FTP hostname, username, and password
   - Or use your cPanel username/password

2. **Connect with FileZilla**
   - Download FileZilla: https://filezilla-project.org/
   - Connect using:
     - Host: `ftp.yourdomain.com` or IP provided by Banahosting
     - Username: Your FTP username
     - Password: Your FTP password
     - Port: `21` (FTP) or `22` (SFTP)

3. **Upload Files**
   - Navigate to `public_html` on the server
   - Upload all files from your local project folder
   - Ensure file structure matches your local setup

4. **Set Permissions**
   - Right-click folders → File Permissions → `755`
   - Right-click files → File Permissions → `644`

### Option C: Using Git (if SSH access available)

1. **Enable SSH Access** (if not already enabled)
   - Contact Banahosting support to enable SSH access
   - Get your SSH credentials

2. **SSH into Server**
   ```bash
   ssh username@yourdomain.com
   ```

3. **Clone Repository**
   ```bash
   cd public_html
   git clone https://github.com/zackarystaten101-ops/Staten-Academy.git .
   ```
   (The `.` at the end clones directly into current directory)

4. **Set Permissions**
   ```bash
   find . -type d -exec chmod 755 {} \;
   find . -type f -exec chmod 644 {} \;
   chmod 755 images/
   ```

---

## Step 3: Configure Production Environment

1. **Create Production `env.php`**
   - In cPanel File Manager, navigate to your site root
   - Locate `env.php` (or create it from `env.example.php`)
   - Edit the file with cPanel's code editor

2. **Update Database Configuration**
   ```php
   define('DB_HOST', 'localhost'); // Usually localhost for shared hosting
   define('DB_USERNAME', 'your_database_username');
   define('DB_PASSWORD', 'your_database_password');
   define('DB_NAME', 'your_database_name');
   ```

3. **Update Google OAuth Redirect URI**
   ```php
   define('GOOGLE_REDIRECT_URI', 'https://yourdomain.com/google-calendar-callback.php');
   ```
   **Important:** Replace `yourdomain.com` with your actual domain

4. **Update Application Settings**
   ```php
   define('APP_ENV', 'production');
   define('APP_DEBUG', false);
   ```

5. **Verify Stripe Keys**
   - Ensure you're using production keys (starting with `sk_live_` and `pk_live_`)
   - These should already be in your `env.php`

6. **Save the File**
   - Click "Save Changes" in the editor
   - **Never commit this file to GitHub!**

---

## Step 4: Configure Namecheap Domain DNS

1. **Get Banahosting IP Address**
   - In Banahosting cPanel, look for "Server Information" or "Account Information"
   - Note the "Shared IP Address" or contact support
   - **Save this IP address**

2. **Configure DNS in Namecheap**
   - Log into Namecheap: https://www.namecheap.com/
   - Go to "Domain List" → Click "Manage" next to your domain
   - Go to "Advanced DNS" tab

3. **Add A Record**
   - Click "Add New Record"
   - Type: `A Record`
   - Host: `@` (or leave blank for root domain)
   - Value: `YOUR_BANAHOSTING_IP_ADDRESS`
   - TTL: `Automatic` or `30 min`
   - Click the checkmark to save

4. **Add CNAME for www (Optional)**
   - Click "Add New Record"
   - Type: `CNAME Record`
   - Host: `www`
   - Value: `yourdomain.com` (your actual domain)
   - TTL: `Automatic`
   - Click the checkmark to save

5. **Wait for DNS Propagation**
   - DNS changes can take 24-48 hours, but usually work within a few hours
   - Check propagation: https://www.whatsmydns.net/

---

## Step 5: Add Domain in Banahosting

1. **In Banahosting cPanel**
   - Go to "Addon Domains" or "Parked Domains"
   - Add your domain name
   - Point it to `public_html` folder
   - Follow Banahosting's instructions

---

## Step 6: Update Google Cloud Console

1. **Go to Google Cloud Console**
   - Visit: https://console.cloud.google.com/
   - Select your project

2. **Update OAuth Credentials**
   - Go to "APIs & Services" → "Credentials"
   - Click on your OAuth 2.0 Client ID
   - Under "Authorized redirect URIs", add:
     ```
     https://yourdomain.com/google-calendar-callback.php
     ```
   - Replace `yourdomain.com` with your actual domain
   - Click "Save"

3. **Update OAuth Consent Screen** (if needed)
   - Go to "APIs & Services" → "OAuth consent screen"
   - Add your production domain to authorized domains
   - Update privacy policy and terms of service URLs if you have them

---

## Step 7: Enable SSL Certificate

1. **In Banahosting cPanel**
   - Look for "SSL/TLS" or "Let's Encrypt SSL"
   - Click "Install SSL Certificate" or "Enable SSL"
   - Select your domain
   - Choose "Let's Encrypt" (free option)
   - Click "Install" or "Enable"

2. **Force HTTPS Redirect**
   - After SSL is installed, edit `.htaccess` file
   - Uncomment these lines (remove the `#`):
     ```apache
     RewriteCond %{HTTPS} off
     RewriteRule ^(.*)$ https://%{HTTP_HOST}%{REQUEST_URI} [L,R=301]
     ```
   - Save the file

3. **Test HTTPS**
   - Visit `https://yourdomain.com`
   - Ensure the padlock icon appears in the browser

---

## Step 8: Test Your Deployment

1. **Visit Your Website**
   - Go to `https://yourdomain.com`
   - Check if the homepage loads correctly

2. **Test Registration**
   - Create a new user account
   - Verify email/password registration works

3. **Test Login**
   - Log in with your test account
   - Verify dashboard loads correctly

4. **Test Database Connection**
   - If you see database errors, check:
     - Database credentials in `env.php`
     - Database user has proper permissions
     - Database exists and is accessible

5. **Test Stripe Payments**
   - Go through the payment flow
   - Use Stripe test mode first, then switch to live mode
   - Verify webhooks are configured if needed

6. **Test Google Calendar Integration**
   - Try connecting a teacher's Google Calendar
   - Verify OAuth flow works
   - Test creating a calendar event

7. **Check Error Logs**
   - In cPanel, go to "Error Logs"
   - Review any PHP errors or warnings
   - Fix issues as needed

---

## Step 9: Post-Deployment Checklist

- [ ] Database is created and accessible
- [ ] All files uploaded to `public_html`
- [ ] `env.php` configured with production values
- [ ] File permissions set correctly (folders: 755, files: 644)
- [ ] DNS configured and propagated
- [ ] Domain added in Banahosting cPanel
- [ ] SSL certificate installed and HTTPS working
- [ ] Google OAuth redirect URI updated
- [ ] `.htaccess` file in place with security settings
- [ ] Website loads without errors
- [ ] Registration and login working
- [ ] Database tables created (check via phpMyAdmin)
- [ ] Stripe payments working
- [ ] Google Calendar integration working
- [ ] Error logs reviewed and clean

---

## Troubleshooting

### Website Shows "500 Internal Server Error"
- Check file permissions (folders: 755, files: 644)
- Review error logs in cPanel
- Verify `.htaccess` syntax is correct
- Check PHP version compatibility

### Database Connection Errors
- Verify database credentials in `env.php`
- Check database host (usually `localhost`)
- Ensure database user has proper permissions
- Verify database exists in phpMyAdmin

### "Page Not Found" or 404 Errors
- Verify files are in `public_html` root, not a subfolder
- Check `.htaccess` rewrite rules
- Ensure `index.php` exists in root

### SSL Certificate Issues
- Wait a few minutes after installation
- Clear browser cache
- Verify DNS is fully propagated
- Check if Let's Encrypt certificate is active

### Google OAuth Not Working
- Verify redirect URI matches exactly in Google Console
- Check that redirect URI uses `https://`
- Ensure OAuth consent screen is configured
- Review error logs for specific OAuth errors

### Images Not Uploading
- Check `images/` folder permissions (should be 755)
- Verify folder is writable
- Check PHP upload limits in cPanel

---

## Security Reminders

- ✅ Never commit `env.php` to GitHub
- ✅ Keep backups of your production `env.php` securely
- ✅ Use strong database passwords
- ✅ Keep PHP and all software updated
- ✅ Regularly backup your database
- ✅ Monitor error logs for suspicious activity
- ✅ Use HTTPS for all connections
- ✅ Keep Stripe API keys secure

---

## Support Resources

- **Banahosting Support:** Check your hosting account for support contact
- **Namecheap Support:** https://www.namecheap.com/support/
- **Google Cloud Console:** https://console.cloud.google.com/
- **Stripe Dashboard:** https://dashboard.stripe.com/

---

## Next Steps

After successful deployment:
1. Set up automated backups in cPanel
2. Configure email notifications for errors
3. Set up monitoring/uptime checking
4. Create a staging environment for testing updates
5. Document your deployment process for future reference

---

**Last Updated:** Based on deployment plan for Staten Academy





