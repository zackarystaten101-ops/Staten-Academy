# Fix Google OAuth 403: access_denied Error

## The Problem
**Error 403: access_denied**

This happens when Google OAuth consent screen is not properly configured or the app is in "Testing" mode with restricted access.

---

## Common Causes

1. **App is in Testing Mode** - Only test users can access
2. **User email not added as test user**
3. **OAuth consent screen not configured**
4. **Scopes not verified** (for sensitive scopes)

---

## Solution: Configure OAuth Consent Screen

### Step 1: Go to Google Cloud Console

1. Visit: https://console.cloud.google.com/
2. Select your project (the one with Client ID: `154840066316-cnoe60d3q2853vitb117usavqd356h60`)
3. Go to **APIs & Services** → **OAuth consent screen**

---

### Step 2: Configure Consent Screen

#### Option A: Make App Public (Recommended for Production)

1. **User Type:**
   - Select **"External"** (for public apps)
   - Click **CREATE**

2. **App Information:**
   - **App name:** Staten Academy (or your app name)
   - **User support email:** Your email
   - **App logo:** (Optional - upload a logo)
   - **App domain:** statenacademy.com
   - **Developer contact information:** Your email
   - Click **SAVE AND CONTINUE**

3. **Scopes:**
   - Click **ADD OR REMOVE SCOPES**
   - Add: `https://www.googleapis.com/auth/calendar`
   - Click **UPDATE** → **SAVE AND CONTINUE**

4. **Test Users (if app is in Testing mode):**
   - Add your email address as a test user
   - Add any other emails that need to test
   - Click **SAVE AND CONTINUE**

5. **Summary:**
   - Review settings
   - Click **BACK TO DASHBOARD**

6. **Publish App (if in Testing mode):**
   - At the top, you'll see "Publishing status: Testing"
   - Click **PUBLISH APP**
   - Confirm by clicking **CONFIRM**

⚠️ **Note:** Publishing makes your app available to all Google users. Make sure your app is ready for production.

---

#### Option B: Keep in Testing Mode (For Development)

If you want to keep it in testing mode:

1. **Add Test Users:**
   - Go to **OAuth consent screen**
   - Scroll to **Test users**
   - Click **+ ADD USERS**
   - Add email addresses that should have access:
     - Your email
     - Any test accounts
   - Click **ADD**

2. **Only these test users will be able to sign in**

---

### Step 3: Verify Scopes

1. Go to **APIs & Services** → **OAuth consent screen**
2. Click **EDIT APP**
3. Go to **Scopes** section
4. Make sure these scopes are added:
   - `https://www.googleapis.com/auth/calendar`
5. Click **SAVE AND CONTINUE**

---

### Step 4: Check App Status

1. Go to **OAuth consent screen**
2. Look at the top for **Publishing status**
3. **If it says "Testing":**
   - Only test users can access
   - Either add yourself as a test user, or publish the app

4. **If it says "In production":**
   - All Google users can access
   - No test users needed

---

## Quick Fix Checklist

- [ ] OAuth consent screen is configured
- [ ] App name and support email are set
- [ ] Scopes include: `https://www.googleapis.com/auth/calendar`
- [ ] If in Testing mode: Your email is added as a test user
- [ ] If in Testing mode: App is published (or you're okay with testing mode)
- [ ] Wait 5-10 minutes after changes for propagation

---

## For Production (Recommended)

**Publish your app:**

1. Go to **OAuth consent screen**
2. If status is "Testing", click **PUBLISH APP**
3. Confirm the action
4. Wait a few minutes
5. Try Google login again

**Benefits:**
- ✅ All users can sign in (not just test users)
- ✅ No need to add individual test users
- ✅ Better for production use

**Requirements:**
- App must have proper privacy policy URL (if required)
- App must have terms of service URL (if required)
- Scopes must be verified (for sensitive scopes)

---

## For Development/Testing

**Keep in Testing mode:**

1. Go to **OAuth consent screen**
2. Scroll to **Test users**
3. Add your email and any test emails
4. Save

**Limitations:**
- Only test users can sign in
- Need to add each user manually
- Not suitable for production

---

## Verify Your Email is Added

If app is in Testing mode:

1. Go to **OAuth consent screen**
2. Scroll to **Test users** section
3. Check if your email is listed
4. If not, click **+ ADD USERS** and add it

---

## Common Issues

### Issue 1: "App is in testing mode"
**Solution:** Either:
- Add your email as a test user, OR
- Publish the app (make it public)

### Issue 2: "Scope not verified"
**Solution:** 
- For sensitive scopes, Google may require verification
- For `calendar` scope, usually no verification needed
- If verification is required, follow Google's verification process

### Issue 3: "User not found in test users"
**Solution:**
- Add the user's email to test users list
- Or publish the app to allow all users

### Issue 4: "Still getting 403 after changes"
**Solution:**
- Wait 5-10 minutes (changes take time to propagate)
- Clear browser cache
- Try in incognito mode
- Make sure you're using the correct Google account

---

## Step-by-Step: Publish App (Production)

1. **Go to OAuth consent screen:**
   - https://console.cloud.google.com/
   - Your project → APIs & Services → OAuth consent screen

2. **Check current status:**
   - If it says "Testing", you need to publish

3. **Click "PUBLISH APP" button** (at the top)

4. **Read the warning:**
   - Publishing makes your app available to all Google users
   - Make sure your app is ready

5. **Click "CONFIRM"**

6. **Wait 5-10 minutes** for changes to propagate

7. **Test Google login** - should work now!

---

## Alternative: Add Test Users (Quick Fix)

If you don't want to publish yet:

1. Go to **OAuth consent screen**
2. Scroll to **Test users**
3. Click **+ ADD USERS**
4. Enter email addresses (one per line):
   ```
   your-email@gmail.com
   test-user@gmail.com
   ```
5. Click **ADD**
6. Wait a few minutes
7. Test with one of the added emails

---

## Summary

**The 403 error means:**
- App is in Testing mode AND your email is not in test users, OR
- OAuth consent screen is not properly configured

**Quick fixes:**
1. ✅ Add your email as a test user (if in Testing mode)
2. ✅ OR publish the app (for production)
3. ✅ Verify scopes are added
4. ✅ Wait 5-10 minutes after changes

**For production:** Publish the app so all users can sign in.

**For development:** Keep in Testing mode and add test user emails.

---

## Next Steps

1. ✅ Configure OAuth consent screen
2. ✅ Add test users OR publish app
3. ✅ Verify scopes
4. ✅ Wait 5-10 minutes
5. ✅ Test Google login again

The error should be resolved after these steps!

