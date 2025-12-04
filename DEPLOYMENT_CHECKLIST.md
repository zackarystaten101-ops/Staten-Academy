# Deployment Checklist - Quick Reference

Use this checklist during your deployment to Banahosting.

## Pre-Deployment
- [ ] Have Banahosting cPanel login credentials
- [ ] Have Namecheap domain access
- [ ] Have Google Cloud Console access
- [ ] Have Stripe production API keys ready

## Database Setup
- [ ] Created MySQL database in Banahosting cPanel
- [ ] Created database user with strong password
- [ ] Granted user full privileges to database
- [ ] Saved database credentials securely:
  - [ ] Database Host: `_______________`
  - [ ] Database Name: `_______________`
  - [ ] Database Username: `_______________`
  - [ ] Database Password: `_______________`
- [ ] Imported `setup-tables.sql` OR verified auto-creation works

## File Deployment
- [ ] Chose deployment method (cPanel/FTP/Git)
- [ ] Uploaded all files to `public_html` folder
- [ ] Set folder permissions to `755`
- [ ] Set file permissions to `644`
- [ ] Set `images/` folder to `755` (writable)

## Configuration
- [ ] Created/updated `env.php` on server
- [ ] Updated database credentials in `env.php`
- [ ] Updated `GOOGLE_REDIRECT_URI` with production domain
- [ ] Set `APP_ENV` to `'production'`
- [ ] Set `APP_DEBUG` to `false`
- [ ] Verified Stripe keys are production keys
- [ ] Verified `.htaccess` file is in place

## Domain & DNS
- [ ] Got Banahosting IP address
- [ ] Added A record in Namecheap DNS (@ → Banahosting IP)
- [ ] Added CNAME record for www (optional)
- [ ] Added domain in Banahosting cPanel
- [ ] Waited for DNS propagation (check with whatsmydns.net)

## Google OAuth
- [ ] Updated Google Cloud Console OAuth redirect URI
- [ ] Added production domain to authorized domains
- [ ] Verified OAuth consent screen settings

## SSL Certificate
- [ ] Installed SSL certificate in Banahosting cPanel
- [ ] Enabled HTTPS redirect in `.htaccess`
- [ ] Tested HTTPS connection (padlock icon visible)

## Testing
- [ ] Website loads at `https://yourdomain.com`
- [ ] No PHP errors on homepage
- [ ] Registration form works
- [ ] Login form works
- [ ] Database connection successful
- [ ] User dashboard loads correctly
- [ ] Stripe payment flow works
- [ ] Google Calendar OAuth works
- [ ] File uploads work (images folder)
- [ ] Checked error logs in cPanel (no critical errors)

## Post-Deployment
- [ ] Set up automated backups
- [ ] Documented deployment process
- [ ] Saved production `env.php` backup securely
- [ ] Verified `.gitignore` excludes `env.php`
- [ ] Tested all major features
- [ ] Monitored error logs for 24 hours

## Security Verification
- [ ] `env.php` is not accessible via browser (should show 403)
- [ ] HTTPS is enforced
- [ ] Strong database password in use
- [ ] API keys are secure (not in version control)
- [ ] File permissions are correct
- [ ] `.htaccess` security rules active

---

**Deployment Date:** _______________
**Domain:** _______________
**Deployed By:** _______________

---

## Quick Commands Reference

### Check DNS Propagation
Visit: https://www.whatsmydns.net/

### Test HTTPS
Visit: https://yourdomain.com

### Check File Permissions (via SSH)
```bash
find . -type d -exec chmod 755 {} \;
find . -type f -exec chmod 644 {} \;
chmod 755 images/
```

### Test Database Connection
Create a test file `test-db.php`:
```php
<?php
require_once 'env.php';
$conn = new mysqli(DB_HOST, DB_USERNAME, DB_PASSWORD, DB_NAME);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}
echo "Database connection successful!";
$conn->close();
?>
```
Delete after testing!

---

**Need Help?** Refer to `DEPLOYMENT_GUIDE.md` for detailed instructions.





