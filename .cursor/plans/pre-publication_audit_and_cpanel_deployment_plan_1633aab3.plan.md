---
name: Pre-Publication Audit and cPanel Deployment Plan
overview: ""
todos: []
---

# Pre-Publication Audit and cPanel Deployment Plan

## Critical Issues to Fix Before Publishing

### 1. File Path and Directory Structure Compatibility

#### 1.1 Cross-Platform Path Issues

- **Problem**: Windows backslashes (`\`) and case-sensitivity differences
- **Files to check**: All files using `__DIR__`, `dirname()`, `require_once`, `include`
- **Action**: 
- Verify all paths use forward slashes (`/`)
- Ensure `__DIR__` is used consistently (not `dirname(__FILE__)`)
- Test relative paths work from root and subdirectories
- Check `app/Views/components/dashboard-functions.php` for path resolution
- Verify `getAssetPath()` function works correctly on Linux

#### 1.2 Asset Path Resolution

- **Problem**: `getAssetPath()` may not work correctly on cPanel
- **Files**: `app/Views/components/dashboard-functions.php`, all PHP files using assets
- **Action**:
- Review `getAssetPath()` implementation for Linux compatibility
- Ensure it handles both `/assets/` and `assets/` correctly
- Test with absolute and relative paths
- Verify `.htaccess` rules properly route `/assets/` to `public/assets/`

#### 1.3 Include/Require Path Issues

- **Problem**: Relative paths may break on cPanel depending on document root
- **Files**: All files with `require_once` or `include`
- **Action**:
- Audit all `require_once` statements
- Ensure `__DIR__` is used for relative includes
- Check `app/Views/components/dashboard-sidebar.php` uses `__DIR__ . '/../../../db.php'`
- Verify `config/database.php` includes work correctly

### 2. Database Configuration and Migration

#### 2.1 Environment Configuration

- **Problem**: `env.php` may not exist or be properly configured
- **Files**: `db.php`, `config/database.php`, `env.php` (if exists)
- **Action**:
- Create `env.example.php` template if missing
- Ensure `db.php` handles missing `env.php` gracefully
- Verify database credentials are not hardcoded
- Check `APP_DEBUG` constant is properly set
- Document required environment variables

#### 2.2 Database Schema Migration

- **Problem**: `config/database.php` may not handle migrations correctly
- **Files**: `config/database.php`, `db.php`
- **Action**:
- Verify `users` table includes all new columns:
  - `role` ENUM includes 'visitor'
  - `has_purchased_class` BOOLEAN
  - `first_purchase_date` TIMESTAMP
  - `subscription_plan_id` INT
  - `subscription_status` ENUM
  - `subscription_start_date`, `subscription_end_date` TIMESTAMP
- Check all new tables exist: `courses`, `course_categories`, `course_lessons`, `user_course_progress`, `course_enrollments`, `course_reviews`, `subscription_plans`
- Verify migration logic doesn't fail on existing databases
- Test schema creation on fresh database
- Ensure foreign keys are properly set up

#### 2.3 Database Connection Error Handling

- **Problem**: Error messages may expose sensitive information
- **Files**: `db.php`, `config/database.php`
- **Action**:
- Verify `APP_DEBUG` check works correctly
- Ensure production errors don't expose database credentials
- Add proper error logging instead of `die()` statements
- Test connection failure scenarios

### 3. Apache/.htaccess Configuration

#### 3.1 Rewrite Rules Compatibility

- **Problem**: `.htaccess` rules may not work on all cPanel configurations
- **Files**: `.htaccess`, `public/.htaccess`
- **Action**:
- Verify `RewriteBase` is not needed (or set correctly)
- Test that `/assets/` correctly routes to `public/assets/`
- Ensure `RewriteEngine On` is present
- Check that directory redirects work: `/app/`, `/config/`, `/docs/` return 403
- Verify `public/index.php` routing works
- Test that existing files are not redirected incorrectly

#### 3.2 Security Headers

- **Problem**: Security headers may not be supported on all hosts
- **Files**: `.htaccess`
- **Action**:
- Wrap security headers in `<IfModule mod_headers.c>` (already done)
- Verify headers don't break functionality
- Test X-Frame-Options, X-XSS-Protection, etc.

#### 3.3 PHP Settings

- **Problem**: PHP settings in `.htaccess` may be overridden by cPanel
- **Files**: `.htaccess`
- **Action**:
- Verify `upload_max_filesize`, `post_max_size` settings
- Check if `php.ini` needs separate configuration
- Document cPanel PHP settings requirements
- Test file upload functionality

### 4. File Upload and Permissions

#### 4.1 Upload Directory Permissions

- **Problem**: Upload directories may not have correct permissions on Linux
- **Files**: `api/materials.php`, `teacher-dashboard.php` (profile pics)
- **Action**:
- Identify all upload directories: `public/assets/images/`, any material upload paths
- Document required permissions (typically 755 for directories, 644 for files)
- Remove any `chmod()` calls that may fail
- Ensure directories are created if they don't exist
- Test file uploads work correctly

#### 4.2 File Upload Security

- **Problem**: File uploads may be vulnerable
- **Files**: `api/materials.php`, `teacher-dashboard.php`
- **Action**:
- Verify file type validation (extensions, MIME types)
- Check file size limits are enforced
- Ensure uploaded files are not executable
- Verify file names are sanitized
- Test against malicious file uploads

#### 4.3 Upload Path Configuration

- **Problem**: Upload paths may use Windows-specific paths
- **Files**: `teacher-dashboard.php`, `api/materials.php`
- **Action**:
- Verify `__DIR__ . '/public/assets/images/'` works on Linux
- Check all `move_uploaded_file()` calls use correct paths
- Ensure paths are relative to document root or use `__DIR__`

### 5. Session and Security

#### 5.1 Session Configuration

- **Problem**: Sessions may not work correctly on cPanel
- **Files**: All files with `session_start()`
- **Action**:
- Verify `session_start()` is called before any output
- Check `ob_start()` is used where needed (already in `index.php`)
- Ensure session cookie settings are secure
- Test session persistence across requests
- Verify session directory permissions

#### 5.2 SQL Injection Prevention

- **Problem**: Some queries may be vulnerable
- **Files**: All files with database queries
- **Action**:
- Audit all SQL queries for prepared statements
- Verify `bind_param()` is used for all user input
- Check `message_threads.php` query is safe
- Review any dynamic table/column names
- Test with SQL injection attempts

#### 5.3 XSS Prevention

- **Problem**: User input may not be properly escaped
- **Files**: All files displaying user data
- **Action**:
- Verify `htmlspecialchars()` or `h()` function is used
- Check all `echo` statements escape output
- Review JavaScript injection in user-generated content
- Test with XSS payloads

#### 5.4 CSRF Protection

- **Problem**: Forms may lack CSRF protection
- **Files**: All forms (login, register, payment, etc.)
- **Action**:
- Implement CSRF tokens for all forms
- Verify POST requests validate tokens
- Check payment forms are protected
- Test form submissions

### 6. Error Handling and Logging

#### 6.1 Production Error Display

- **Problem**: Errors may be displayed to users in production
- **Files**: `index.php`, `db.php`, all entry points
- **Action**:
- Verify `APP_DEBUG` controls error display
- Ensure `display_errors = 0` in production
- Set up error logging to file
- Test error pages don't expose sensitive info
- Create user-friendly error pages

#### 6.2 Error Logging

- **Problem**: Errors may not be logged
- **Files**: All PHP files
- **Action**:
- Set up `error_log()` calls for important errors
- Configure log file location (ensure writable)
- Document log file location for cPanel
- Test error logging works

### 7. JavaScript and Frontend

#### 7.1 Hash Navigation

- **Problem**: `hashchange` events may not work correctly
- **Files**: `teacher-dashboard.php`, `student-dashboard.php`, `admin-dashboard.php`, `visitor-dashboard.php`
- **Action**:
- Verify `switchTab()` function works on all browsers
- Test browser back/forward buttons
- Check `pushState()` compatibility
- Verify hash navigation doesn't break on page reload

#### 7.2 Asset Loading

- **Problem**: CSS/JS may not load correctly
- **Files**: All PHP files with `<link>` or `<script>` tags
- **Action**:
- Verify all asset paths use `getAssetPath()`
- Check `logo.png` and other images load correctly
- Test CSS files are loaded
- Verify JavaScript files are loaded and execute
- Check for 404 errors on assets

### 8. Missing Files and Incomplete Features

#### 8.1 Required Files

- **Problem**: Some files may be missing
- **Action**:
- Verify `env.php` exists or create `env.example.php`
- Check `public/index.php` exists and works
- Verify all API endpoints exist: `api/notifications.php`, `api/materials.php`, `api/favorites.php`, etc.
- Ensure `favicon.ico` exists
- Check `robots.txt` exists (optional but recommended)

#### 8.2 Incomplete Features

- **Problem**: Some features may be partially implemented
- **Action**:
- Verify visitor dashboard is fully functional
- Check course system is complete or properly disabled
- Review subscription plan functionality
- Test payment flow end-to-end
- Verify teacher assignment for Economy Plan

### 9. Database Content and Seeding

#### 9.1 Initial Data

- **Problem**: Database may need initial data
- **Action**:
- Verify course categories are seeded (if courses are enabled)
- Check subscription plans exist in database
- Ensure default admin user exists (or document creation)
- Test with empty database

### 10. cPanel-Specific Considerations

#### 10.1 Document Root Configuration

- **Problem**: cPanel document root may differ
- **Action**:
- Document where files should be placed in cPanel
- Verify `.htaccess` works from cPanel document root
- Test if `public/` should be document root or subdirectory
- Check if `RewriteBase` needs to be set

#### 10.2 PHP Version

- **Problem**: PHP version may differ
- **Action**:
- Document minimum PHP version required (7.4+)
- Verify no deprecated functions are used
- Test with PHP 7.4, 8.0, 8.1, 8.2
- Check `mysqli` extension is available

#### 10.3 MySQL/MariaDB Compatibility

- **Problem**: Database version may differ
- **Action**:
- Test with MySQL 5.7+, MariaDB 10.3+
- Verify all SQL syntax is compatible
- Check `ENGINE=InnoDB` is used (if specified)
- Test foreign key constraints work

### 11. Testing Checklist

#### 11.1 Functional Testing

- [ ] User registration and login
- [ ] Teacher dashboard navigation (all tabs)
- [ ] Student dashboard navigation (all tabs)
- [ ] Admin dashboard navigation
- [ ] Visitor dashboard (if implemented)
- [ ] Profile updates
- [ ] File uploads (profile pics, materials)
- [ ] Messaging system
- [ ] Payment flow (Stripe)
- [ ] Subscription management
- [ ] Course enrollment (if enabled)
- [ ] Schedule/booking system
- [ ] Google Calendar integration

#### 11.2 Cross-Browser Testing

- [ ] Chrome
- [ ] Firefox
- [ ] Safari
- [ ] Edge
- [ ] Mobile brows