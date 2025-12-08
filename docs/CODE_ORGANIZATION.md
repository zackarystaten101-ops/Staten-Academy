# Code Organization and Structure

## File Organization Summary

All files have been organized and optimized for cPanel deployment. Here's the current structure:

## Directory Structure

```
Staten-Academy/
├── api/                    # API endpoints
│   ├── assignments.php
│   ├── materials.php
│   ├── notifications.php
│   └── ...
├── app/
│   ├── Controllers/        # MVC Controllers
│   ├── Helpers/            # Helper functions
│   │   ├── PathHelper.php
│   │   └── SecurityHelper.php  # CSRF and security utilities
│   ├── Middleware/         # Authentication middleware
│   ├── Models/             # Data models
│   ├── Services/           # Business logic services
│   └── Views/              # View components
│       └── components/
│           ├── dashboard-functions.php  # Shared dashboard utilities
│           ├── dashboard-header.php
│           └── dashboard-sidebar.php
├── config/                 # Configuration files
│   ├── database.php        # Comprehensive database schema
│   └── ...
├── core/                   # Core framework files
├── public/                 # Public assets
│   ├── assets/             # CSS, JS, images
│   └── uploads/            # User uploads
├── docs/                   # Documentation
├── db.php                  # Database connection (legacy)
├── env.php                 # Environment config (not in git)
├── env.example.php         # Environment template
├── index.php               # Homepage
├── login.php               # Authentication
├── .htaccess               # Apache configuration
└── DEPLOYMENT.md           # Deployment guide
```

## File Path Standards

### Asset Paths
- All assets use `getAssetPath()` function
- Returns `/assets/...` paths (cPanel compatible)
- Function defined in `app/Views/components/dashboard-functions.php`
- Fallback definitions in files that don't include dashboard-functions.php

### Database Includes
- Use `require_once __DIR__ . '/db.php'` for absolute paths (recommended)
- Some files use relative `require_once 'db.php'` (works but less reliable)
- Database schema in `config/database.php` (comprehensive)
- Legacy schema in `db.php` (includes migrations)

### Helper Functions
- `dashboard-functions.php` - Shared dashboard utilities
- `SecurityHelper.php` - CSRF protection and security utilities
- Load SecurityHelper in dashboard-functions.php or directly where needed

## Key Functions Available Globally

### Asset Management
- `getAssetPath($asset)` - Returns cPanel-compatible asset paths

### Security (from SecurityHelper.php)
- `generateCSRFToken()` - Generate CSRF token
- `getCSRFToken()` - Get or generate CSRF token
- `validateCSRFToken($token)` - Validate CSRF token
- `csrfTokenField()` - Generate hidden input field
- `requireCSRFToken()` - Validate POST requests
- `sanitizeFilename($filename)` - Sanitize file names
- `validateFileUpload($file, ...)` - Validate uploads

### Dashboard Utilities (from dashboard-functions.php)
- `getUserById($conn, $user_id)` - Get user data
- `h($string)` - HTML escape (XSS protection)
- `getTeacherRating($conn, $teacher_id)` - Get ratings
- `createNotification($conn, ...)` - Create notifications
- Many more utility functions...

## Include/Require Standards

### Critical Files Load Order
1. Environment config (`env.php`)
2. Error handling setup
3. Session start
4. Database connection (`db.php`)
5. Helper functions
6. Security helpers (if needed)

### Recommended Pattern
```php
<?php
// 1. Output buffering
ob_start();

// 2. Environment
if (!defined('DB_HOST')) {
    require_once __DIR__ . '/env.php';
}

// 3. Error handling
if (defined('APP_DEBUG') && APP_DEBUG === true) {
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
}

// 4. Session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// 5. Database
require_once __DIR__ . '/db.php';

// 6. Helpers
require_once __DIR__ . '/app/Views/components/dashboard-functions.php';
```

## cPanel Compatibility

All paths use forward slashes (`/`) for cross-platform compatibility:
- File paths: `__DIR__ . '/path/to/file'`
- Asset URLs: `/assets/css/styles.css`
- No Windows backslashes in code

## Security Organization

### Security Helper Location
- File: `app/Helpers/SecurityHelper.php`
- Auto-loaded by: `dashboard-functions.php` (if available)
- Functions available globally after dashboard-functions.php is loaded

### CSRF Protection Status
- Helper functions ready
- Need to add tokens to forms (see PRE_PUBLICATION_AUDIT_STATUS.md)

## Database Organization

### Schema Files
1. **config/database.php** - Comprehensive schema with all tables
2. **db.php** - Legacy file with migrations (kept for compatibility)

### Migration Strategy
- Both files create tables if they don't exist
- `db.php` includes migration logic for existing databases
- All new fields/tables added to both files

## Upload Organization

### Upload Directories
- Profile pictures: `public/assets/images/`
- Materials: `public/uploads/materials/`
- Assignments: `public/uploads/assignments/`
- Resources: `public/uploads/resources/`

### Upload Paths
- All use `__DIR__` for reliability
- Forward slashes only
- Directory creation with proper permissions (0755)

## Best Practices

1. **Always use `__DIR__` for includes** - More reliable than relative paths
2. **Load helpers before use** - Ensure functions are available
3. **Use forward slashes** - Cross-platform compatibility
4. **Check function existence** - Use `function_exists()` before calling
5. **Load in correct order** - Environment → Session → Database → Helpers

## Files Needing Attention

### Include Path Consistency
Some files use relative paths for `db.php`:
- `require_once 'db.php'` - Works but less reliable
- Consider updating to `require_once __DIR__ . '/db.php'`

Files using relative paths:
- teacher-dashboard.php
- student-dashboard.php
- admin-dashboard.php
- visitor-dashboard.php
- schedule.php
- And others...

These work but could be more reliable with absolute paths.

## Notes

- All critical fixes are complete
- Paths are cPanel-compatible
- Security helpers are ready for integration
- Code is organized and documented





