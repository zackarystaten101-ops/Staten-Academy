# Quick Start - Deploy to Banahosting

This is a condensed guide for deploying Staten Academy to Banahosting. For detailed instructions, see `DEPLOYMENT_GUIDE.md`.

## 🚀 Quick Steps

### 1. Database Setup (5 minutes)
- Log into Banahosting cPanel
- Create MySQL database and user
- **Save credentials:** Host, Name, Username, Password

### 2. Upload Files (10 minutes)
**Option A - cPanel File Manager:**
- Download ZIP from GitHub
- Upload to `public_html` in cPanel
- Extract files

**Option B - FTP:**
- Use FileZilla to connect
- Upload all files to `public_html`

**Set permissions:** Folders `755`, Files `644`

### 3. Configure Environment (5 minutes)
- Edit `env.php` on server:
  - Update database credentials
  - Change `GOOGLE_REDIRECT_URI` to: `https://yourdomain.com/google-calendar-callback.php`
  - Set `APP_ENV` to `'production'`
  - Set `APP_DEBUG` to `false`

### 4. Domain & DNS (10 minutes)
- Get Banahosting IP address
- In Namecheap: Add A record (`@` → Banahosting IP)
- In Banahosting: Add domain in cPanel
- Wait for DNS propagation (check: whatsmydns.net)

### 5. Google OAuth (5 minutes)
- Go to Google Cloud Console
- Update OAuth redirect URI: `https://yourdomain.com/google-calendar-callback.php`

### 6. SSL Certificate (5 minutes)
- In Banahosting cPanel: Install Let's Encrypt SSL
- Uncomment HTTPS redirect in `.htaccess`

### 7. Verify (5 minutes)
- Visit `https://yourdomain.com/verify-production.php`
- Fix any issues shown
- **Delete `verify-production.php` after checking**

### 8. Test Everything
- [ ] Website loads
- [ ] Registration works
- [ ] Login works
- [ ] Payments work
- [ ] Google Calendar works

---

## 📋 Files Created for You

- **`.htaccess`** - Security and HTTPS configuration
- **`DEPLOYMENT_GUIDE.md`** - Detailed step-by-step guide
- **`DEPLOYMENT_CHECKLIST.md`** - Printable checklist
- **`PRODUCTION_CONFIG_UPDATE.md`** - Exact env.php changes needed
- **`env.production.php`** - Production template
- **`verify-production.php`** - Configuration verification tool

---

## ⚠️ Important Reminders

1. **Never commit `env.php`** to GitHub (already in `.gitignore`)
2. **Delete `verify-production.php`** after verification
3. **Backup your production `env.php`** securely
4. **Test everything** before going live
5. **Monitor error logs** for the first 24 hours

---

## 🆘 Need Help?

- **Detailed Guide:** See `DEPLOYMENT_GUIDE.md`
- **Configuration Help:** See `PRODUCTION_CONFIG_UPDATE.md`
- **Checklist:** See `DEPLOYMENT_CHECKLIST.md`
- **Banahosting Support:** Check your hosting account
- **Namecheap Support:** https://www.namecheap.com/support/

---

**Ready to deploy?** Follow the steps above and use the detailed guide for any questions!

