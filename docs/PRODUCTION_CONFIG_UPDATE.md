# Production Configuration Update Guide

This guide shows exactly what to update in `env.php` for production deployment.

## Current vs Production Settings

### Database Configuration

**Current (Development):**
```php
define('DB_HOST', 'localhost');
define('DB_USERNAME', 'root');
define('DB_PASSWORD', '');
define('DB_NAME', 'staten_academy');
```

**Production (Update to):**
```php
define('DB_HOST', 'localhost'); // Usually stays 'localhost' for shared hosting
define('DB_USERNAME', 'YOUR_BANAHOSTING_DB_USERNAME'); // From cPanel MySQL setup
define('DB_PASSWORD', 'YOUR_BANAHOSTING_DB_PASSWORD'); // From cPanel MySQL setup
define('DB_NAME', 'YOUR_BANAHOSTING_DB_NAME'); // From cPanel MySQL setup
```

### Google OAuth Redirect URI

**Current (Development):**
```php
define('GOOGLE_REDIRECT_URI', 'http://localhost/Web%20page/Staten-Academy/google-calendar-callback.php');
```

**Production (Update to):**
```php
// Replace 'yourdomain.com' with your actual domain name
define('GOOGLE_REDIRECT_URI', 'https://yourdomain.com/google-calendar-callback.php');
```

**Important:** Also update this in Google Cloud Console:
1. Go to https://console.cloud.google.com/
2. APIs & Services → Credentials
3. Edit your OAuth 2.0 Client ID
4. Add the production redirect URI to "Authorized redirect URIs"

### Application Settings

**Current (Development):**
```php
define('APP_ENV', 'development');
define('APP_DEBUG', true);
```

**Production (Update to):**
```php
define('APP_ENV', 'production');
define('APP_DEBUG', false);
```

### Stripe Keys

**Current:** Already using production keys (good!)
- `sk_live_...` ✅
- `pk_live_...` ✅

**No changes needed** - these are already production keys.

### Google Client Secret

**Current:**
```php
define('GOOGLE_CLIENT_SECRET', 'YOUR_CLIENT_SECRET_HERE');
```

**Production:** Update with your actual Google Client Secret from Google Cloud Console.

---

## Step-by-Step Update Instructions

1. **On your production server (Banahosting):**
   - Log into cPanel File Manager
   - Navigate to your website root (`public_html`)
   - Open `env.php` for editing

2. **Update each section:**
   - Replace database credentials with your Banahosting database info
   - Update `GOOGLE_REDIRECT_URI` with your production domain
   - Change `APP_ENV` to `'production'`
   - Change `APP_DEBUG` to `false`
   - Update `GOOGLE_CLIENT_SECRET` if you have it

3. **Save the file**

4. **Verify the file is protected:**
   - Try accessing `https://yourdomain.com/env.php` in a browser
   - You should see a 403 Forbidden error (this is good!)
   - If you can see the file contents, check `.htaccess` is working

---

## Example Production env.php

```php
<?php
/**
 * Environment Configuration - PRODUCTION
 * 
 * IMPORTANT: This file contains sensitive credentials.
 * - Never commit this file to version control
 * - Keep a backup of your credentials in a secure location
 */

// ===========================================
// Database Configuration
// ===========================================
define('DB_HOST', 'localhost');
define('DB_USERNAME', 'banauser_statenacademy');
define('DB_PASSWORD', 'YourSecurePassword123!');
define('DB_NAME', 'banauser_statenacademy');

// ===========================================
// Stripe API Keys (Production)
// Get these from https://dashboard.stripe.com/apikeys
// IMPORTANT: Use your actual production keys from Stripe dashboard
// ===========================================
define('STRIPE_SECRET_KEY', 'sk_live_YOUR_PRODUCTION_SECRET_KEY_HERE');
define('STRIPE_PUBLISHABLE_KEY', 'pk_live_YOUR_PRODUCTION_PUBLISHABLE_KEY_HERE');

// ===========================================
// Google OAuth Configuration
// ===========================================
define('GOOGLE_CLIENT_ID', '154840066316-cnoe60d3q2853vitb117usavqd356h60.apps.googleusercontent.com');
define('GOOGLE_CLIENT_SECRET', 'GOCSPX-YourActualClientSecretHere');
define('GOOGLE_REDIRECT_URI', 'https://yourdomain.com/google-calendar-callback.php');
define('GOOGLE_SCOPES', 'https://www.googleapis.com/auth/calendar');

// ===========================================
// Application Settings
// ===========================================
define('APP_ENV', 'production');
define('APP_DEBUG', false);
?>
```

**Remember:** Replace placeholder values with your actual production credentials!

---

## Verification Checklist

After updating `env.php`:

- [ ] Database credentials match your Banahosting MySQL database
- [ ] `GOOGLE_REDIRECT_URI` uses `https://` and your actual domain
- [ ] `APP_ENV` is set to `'production'`
- [ ] `APP_DEBUG` is set to `false`
- [ ] File is saved on the server
- [ ] File is not accessible via browser (403 error)
- [ ] Google Cloud Console has matching redirect URI
- [ ] Website loads without errors
- [ ] Database connection works (test login/registration)

