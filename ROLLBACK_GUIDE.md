# Rollback Guide

If the new wallet/calendar system causes issues, you can safely revert to the original system.

## Quick Disable (No Code Changes)

1. **Disable feature flags in backend `.env`:**
   ```
   WALLET_V2_ENABLED=false
   CALENDAR_V2_ENABLED=false
   ```

2. **Stop the Node backend server** (if running):
   ```bash
   # Kill the Node process
   pkill -f "node.*backend"
   ```

3. **Existing PHP code will continue to work** - no changes were made to PHP files.

## Full Rollback (Remove New Code)

If you need to completely remove the new system:

1. **Remove backend directory:**
   ```bash
   rm -rf backend/
   ```

2. **Remove new React components** (if integrated):
   ```bash
   rm -rf src/components/calendar/
   rm -rf src/components/wallet/
   ```

3. **Remove from package.json dependencies** (if added):
   - Remove `date-fns` if it was only added for new components

4. **Database rollback** (PostgreSQL changes):
   ```sql
   -- Only if you want to remove the new tables
   -- Be careful - this will delete data!
   DROP TABLE IF EXISTS recurrence_groups CASCADE;
   DROP TABLE IF EXISTS audit_logs CASCADE;
   DROP TABLE IF EXISTS earnings CASCADE;
   DROP TABLE IF EXISTS slot_requests CASCADE;
   DROP TABLE IF EXISTS classes CASCADE;
   DROP TABLE IF EXISTS availability_slots CASCADE;
   DROP TABLE IF EXISTS teacher_profiles CASCADE;
   DROP TABLE IF EXISTS lesson_types CASCADE;
   DROP TABLE IF EXISTS wallet_items CASCADE;
   DROP TABLE IF EXISTS entitlements CASCADE;
   DROP TABLE IF EXISTS wallets CASCADE;
   ```

## Verification

After rollback, verify:
- ✅ Existing PHP booking system works (`book-lesson-api.php`)
- ✅ Schedule page works (`schedule.php`)
- ✅ Calendar API works (`api/calendar.php`)
- ✅ No references to `/api/wallets` or `/api/bookings` in frontend

## What Was NOT Changed

These files remain untouched and will continue to work:
- ✅ `schedule.php` - Existing schedule page
- ✅ `book-lesson-api.php` - Existing booking API
- ✅ `api/calendar.php` - Existing calendar API
- ✅ `db.php` - Existing database connection
- ✅ All existing PHP models and services
- ✅ All existing React components (except new ones added)

## Testing Original System

To verify original system still works:
1. Visit `schedule.php` - should work normally
2. Try booking a lesson - should use existing PHP API
3. Check calendar - should use existing PHP calendar service

The new system is **completely separate** and can be disabled without affecting existing functionality.

