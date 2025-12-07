# Fix Google OAuth Redirect URI Mismatch Error

## The Problem
**Error 400: redirect_uri_mismatch**

This happens when the redirect URI in your code doesn't match what's configured in Google Cloud Console.

---

## Current Configuration

Your `env.php` has:
```php
define('GOOGLE_REDIRECT_URI', 'http://localhost/Web%20page/Staten-Academy/google-calendar-callback.php');
```

But you're on production at: **statenacademy.com**

---

## Solution: Update in Two Places

### Step 1: Update `env.php` on Your Server

In cPanel File Manager, edit `env.php` and change:

**FROM:**
```php
define('GOOGLE_REDIRECT_URI', 'http://localhost/Web%20page/Staten-Academy/google-calendar-callback.php');
```

**TO:**
```php
define('GOOGLE_REDIRECT_URI', 'https://statenacademy.com/google-calendar-callback.php');
```

**OR if your site is in a subdirectory:**
```php
define('GOOGLE_REDIRECT_URI', 'https://statenacademy.com/path/to/google-calendar-callback.php');
```

**Important:** 
- Use `https://` (not `http://`)
- Use your actual domain name
- Make sure the path matches where the file actually is on your server

---

### Step 2: Update Google Cloud Console

1. **Go to Google Cloud Console:**
   - Visit: https://console.cloud.google.com/
   - Sign in with your Google account

2. **Navigate to Credentials:**
   - Click on your project
   - Go to **APIs & Services** → **Credentials**
   - Find your OAuth 2.0 Client ID (the one with Client ID: `154840066316-cnoe60d3q2853vitb117usavqd356h60`)
   - Click the **Edit** (pencil icon) button

3. **Add Authorized Redirect URIs:**
   - Scroll down to **Authorized redirect URIs**
   - Click **+ ADD URI**
   - Add your production redirect URI:
     ```
     https://statenacademy.com/google-calendar-callback.php
     ```
   - **OR if in subdirectory:**
     ```
     https://statenacademy.com/path/to/google-calendar-callback.php
     ```

4. **Save:**
   - Click **SAVE** at the bottom
   - Wait a few minutes for changes to propagate

---

## How to Find Your Correct Redirect URI

### Option 1: Check Your Domain
1. Your site is at: **statenacademy.com**
2. The callback file is: `google-calendar-callback.php`
3. So the redirect URI should be: `https://statenacademy.com/google-calendar-callback.php`

### Option 2: Check File Location
1. In cPanel File Manager, find `google-calendar-callback.php`
2. Note the full path (e.g., `/public_html/google-calendar-callback.php`)
3. Your redirect URI is: `https://yourdomain.com` + path from public_html

**Example:**
- File location: `/public_html/google-calendar-callback.php`
- Domain: `statenacademy.com`
- Redirect URI: `https://statenacademy.com/google-calendar-callback.php`

---

## Common Issues

### Issue 1: "Still getting redirect_uri_mismatch"
- **Wait 5-10 minutes** after updating Google Console (changes take time to propagate)
- Make sure you're using `https://` (not `http://`)
- Check for typos in the URI
- Make sure there's no trailing slash: `https://statenacademy.com/google-calendar-callback.php` ✅ (not `/google-calendar-callback.php/`)

### Issue 2: "File not found" after redirect
- Check that `google-calendar-callback.php` exists in the root directory
- Verify the file path matches what you configured

### Issue 3: "Multiple redirect URIs"
You can have multiple redirect URIs in Google Console:
- `http://localhost/...` (for local development)
- `https://statenacademy.com/...` (for production)

Just add both - Google will use the one that matches the request.

---

## Quick Checklist

- [ ] Updated `GOOGLE_REDIRECT_URI` in `env.php` on server
- [ ] Changed to `https://` (not `http://`)
- [ ] Used correct domain name
- [ ] Added redirect URI to Google Cloud Console
- [ ] Saved changes in Google Console
- [ ] Waited 5-10 minutes for propagation
- [ ] Tested Google login again

---

## Testing

After making changes:

1. **Clear browser cache** (or use incognito mode)
2. **Try Google login again**
3. **Check browser console** for any errors
4. **Verify redirect** - you should be redirected back to your site after Google authentication

---

## For Development (Local)

If you also want to test locally, keep both URIs:

**In Google Cloud Console, add both:**
```
http://localhost/Web%20page/Staten-Academy/google-calendar-callback.php
https://statenacademy.com/google-calendar-callback.php
```

**In local `env.php`:**
```php
define('GOOGLE_REDIRECT_URI', 'http://localhost/Web%20page/Staten-Academy/google-calendar-callback.php');
```

**In production `env.php` (on server):**
```php
define('GOOGLE_REDIRECT_URI', 'https://statenacademy.com/google-calendar-callback.php');
```

---

## Summary

1. ✅ Update `env.php` on server with production redirect URI
2. ✅ Add the same URI to Google Cloud Console → Credentials
3. ✅ Wait 5-10 minutes
4. ✅ Test Google login

The redirect URI must match **exactly** in both places (your code and Google Console).

