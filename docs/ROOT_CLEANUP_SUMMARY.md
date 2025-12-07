# Root Directory Cleanup Summary

## Files Moved to `docs/` Directory
All markdown documentation files have been moved to the `docs/` directory for better organization:
- Deployment guides
- Troubleshooting guides
- Setup instructions
- Migration documentation

## Files Moved to `archive/old/` Directory
Utility and test files that are not part of the main application flow:
- `admin_cleanup.php` - Admin utility script
- `admin_role_transfer.php` - Admin utility script
- `check_admin.php` - Admin check utility
- `test-connection.php` - Database connection test

## Files Kept in Root (Still in Use)
These files are still actively used and referenced throughout the application:

### Core Application Files
- `index.php` - Main homepage (teacher listing)
- `db.php` - Database connection (backward compatibility)
- `config.php` - Application configuration
- `env.php` - Environment configuration (sensitive, keep in root)
- `env.example.php` - Environment template

### Authentication Files (Still Referenced)
- `login.php` - Login page (migrated to AuthController, but kept for compatibility)
- `register.php` - Registration page (migrated to AuthController, but kept for compatibility)
- `logout.php` - Logout handler (migrated to AuthController, but kept for compatibility)

### Dashboard Files (Still Referenced)
- `teacher-dashboard.php` - Teacher dashboard (migrated to TeacherController, but kept for compatibility)
- `student-dashboard.php` - Student dashboard (migrated to StudentController, but kept for compatibility)
- `admin-dashboard.php` - Admin dashboard (migrated to AdminController, but kept for compatibility)

### Feature Files (Still Referenced)
- `profile.php` - Teacher profile view (migrated to ProfileController, but kept for compatibility)
- `schedule.php` - Schedule/booking page (migrated to ScheduleController, but kept for compatibility)
- `message_threads.php` - Messaging (migrated to MessageController, but kept for compatibility)
- `classroom.php` - Classroom/materials (migrated to MaterialController, but kept for compatibility)
- `support_contact.php` - Support contact (migrated to SupportController, but kept for compatibility)
- `payment.php` - Payment page (migrated to PaymentController, but kept for compatibility)

### Payment Processing Files
- `create_checkout_session.php` - Stripe checkout (still used)
- `success.php` - Payment success page
- `cancel.php` - Payment cancellation page
- `thank-you.php` - Thank you page

### Google Calendar Integration
- `google-calendar-callback.php` - OAuth callback (still used)
- `google-calendar-config.php` - Calendar configuration (still used)
- `teacher-calendar-setup.php` - Calendar setup page (still used)

### API Files
- `api/` directory - API endpoints (still in use)

### Other Active Files
- `apply-teacher.php` - Teacher application form
- `admin-actions.php` - Admin actions handler
- `admin-schedule-view.php` - Admin schedule view
- `get_messages.php` - Message retrieval (AJAX)
- `send_message.php` - Message sending (AJAX)
- `notifications.php` - Notifications page
- `book-lesson-api.php` - Lesson booking API
- `error-handler.php` - Error handling
- `header-user.php` - User header component
- `includes/` directory - Shared components

## Migration Strategy

The MVC architecture is in place, but old files are kept for backward compatibility during the gradual migration. Eventually, these files will be:

1. **Replaced with redirects** to the new MVC routes
2. **Fully migrated** to use the new Controllers
3. **Removed** once all references are updated

## Next Steps

1. Update all internal links to use new MVC routes
2. Create redirect wrappers for old URLs
3. Migrate remaining functionality to Controllers
4. Remove old files once migration is complete

