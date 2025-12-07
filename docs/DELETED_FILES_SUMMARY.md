# Deleted Files Summary

## Files Moved to Archive

The following files have been moved to `archive/old/` as they are not actively used in the application:

### Utility/Test Files
- `admin_cleanup.php` - Admin utility script (not part of main flow)
- `admin_role_transfer.php` - Admin utility script (not part of main flow)
- `check_admin.php` - Admin check utility (not part of main flow)
- `test-connection.php` - Database connection test (development tool)

### Development/Deployment Tools
- `error-handler.php` - Error handler utility (not required/included anywhere)
- `verify-production.php` - Production verification script (should be deleted after use)
- `env.production.php` - Production environment template (not required for app to run)

### Empty/Unused Directories
- `Staten-Academy/` - Empty or duplicate directory (removed)

## Files Kept in Root (All Active)

All remaining PHP files in the root directory are actively used:

### Core Files
- `index.php` - Main homepage entry point
- `db.php` - Database connection (required everywhere)
- `config.php` - Application configuration (used by payment/calendar files)
- `env.php` - Environment configuration (required)
- `env.example.php` - Environment template

### Authentication
- `login.php` - Login page entry point
- `register.php` - Registration page entry point
- `logout.php` - Logout handler entry point

### Dashboards
- `teacher-dashboard.php` - Teacher dashboard entry point
- `student-dashboard.php` - Student dashboard entry point
- `admin-dashboard.php` - Admin dashboard entry point

### Features
- `profile.php` - Teacher profile view entry point
- `schedule.php` - Schedule/booking page entry point
- `message_threads.php` - Messaging entry point
- `classroom.php` - Classroom/materials entry point
- `support_contact.php` - Support contact entry point
- `payment.php` - Payment page entry point
- `notifications.php` - Notifications page entry point

### Payment Processing
- `create_checkout_session.php` - Stripe checkout handler (entry point)
- `success.php` - Payment success callback (entry point)
- `cancel.php` - Payment cancellation callback (entry point)
- `thank-you.php` - Thank you page (entry point)

### Google Calendar
- `google-calendar-callback.php` - OAuth callback (entry point)
- `google-calendar-config.php` - Calendar configuration (required)
- `teacher-calendar-setup.php` - Calendar setup page (entry point)

### Admin Features
- `admin-actions.php` - Admin actions handler (entry point)
- `admin-schedule-view.php` - Admin schedule view (entry point)
- `apply-teacher.php` - Teacher application form (entry point)

### API Endpoints
- `book-lesson-api.php` - Lesson booking API (entry point)
- `get_messages.php` - Message retrieval API (entry point)
- `send_message.php` - Message sending API (entry point)

### Components
- `header-user.php` - User header component (included in many files)

## Result

- **Deleted/Moved**: 7 files + 1 empty directory
- **Kept**: All 32 active PHP files remain in root
- **Root Directory**: Now contains only actively used application files

All files in the root directory are now essential for the application to function properly.

