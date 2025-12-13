# Manual Testing Required - Items I Cannot Test Automatically

## ✅ What I've Tested (Automated)

### Code Quality ✅
- ✅ **PHP Syntax**: All files checked via linter (no syntax errors found)
- ✅ **Security Patterns**: All SQL queries use prepared statements
- ✅ **Output Escaping**: `h()` function used throughout for XSS prevention
- ✅ **Function Availability**: All helper functions (`getAssetPath`, `getLogoPath`, `formatCurrency`, `h`) are defined
- ✅ **Code Structure**: Proper error handling, transactions, and logging patterns
- ✅ **File Structure**: All required files present and properly organized

### Database Schema ✅
- ✅ **Table Definitions**: All tables defined in `db.php`
- ✅ **Column Definitions**: All new columns added via ALTER TABLE statements
- ✅ **Indexes**: Foreign keys and indexes properly defined
- ✅ **Migration Logic**: Database migration code present

---

## ❌ What YOU Need to Test Manually

### 1. Environment Setup & Configuration ⚠️ CRITICAL

#### Database Initialization
- [ ] **Run `db.php`** in browser or CLI to create all tables
- [ ] Verify all tables created successfully:
  - `users`, `lessons`, `messages`, `student_wallet`, `wallet_transactions`
  - `teacher_categories`, `teacher_availability_slots`, `trial_lessons`
  - `signaling_queue`, `video_sessions`, `admin_audit_log`, `whiteboard_operations`
  - `site_settings` (created when admin accesses settings)
- [ ] Check for any migration errors
- [ ] Verify existing data migrated correctly (if upgrading)

#### Environment Configuration
- [ ] Copy `env.example.php` to `env.php`
- [ ] Add Stripe API keys (test mode):
  - `STRIPE_SECRET_KEY`
  - `STRIPE_PUBLISHABLE_KEY`
  - `STRIPE_WEBHOOK_SECRET`
  - `STRIPE_PRODUCT_TRIAL`, `STRIPE_PRODUCT_KIDS`, `STRIPE_PRODUCT_ADULTS`, `STRIPE_PRODUCT_CODING`
- [ ] Configure database credentials:
  - `DB_HOST`, `DB_USERNAME`, `DB_PASSWORD`, `DB_NAME`
- [ ] Set `WALLET_API_URL` (if using Node.js backend, otherwise leave empty for MySQL fallback)
- [ ] Set `APP_DEBUG` to `true` for testing

#### React Bundle Build ⚠️ REQUIRED FOR CLASSROOM
- [ ] Install Node.js (v16+) and npm
- [ ] Run: `npm install`
- [ ] Run: `npm run build`
- [ ] Verify `public/assets/js/classroom.bundle.js` is created
- [ ] Check file size (should be > 100KB)

---

### 2. User Authentication & Sessions 🔐

#### Login/Logout
- [ ] **Admin Login**: Login as admin, verify redirect to admin dashboard
- [ ] **Teacher Login**: Login as teacher, verify redirect to teacher dashboard
- [ ] **Student Login**: Login as student, verify redirect to student dashboard
- [ ] **Logout**: Click logout, verify session cleared and redirect to login
- [ ] **Session Expiry**: Leave page idle, verify session expires correctly
- [ ] **Invalid Credentials**: Try wrong password, verify error message
- [ ] **Role-Based Redirects**: Verify users redirected to correct dashboard

#### Session Security
- [ ] **Cross-Role Access**: Try accessing admin features as student (should fail)
- [ ] **Direct URL Access**: Try accessing `admin-dashboard.php` without login (should redirect)
- [ ] **Session Persistence**: Refresh page, verify still logged in
- [ ] **Multiple Tabs**: Open multiple tabs, logout in one, verify others redirect

---

### 3. Admin Dashboard - User Management 👥

#### Search & Filter
- [ ] **Search Teachers**: Type teacher name, verify results filter
- [ ] **Search Students**: Type student name, verify results filter
- [ ] **Filter by Role**: Select "Teachers" filter, verify only teachers show
- [ ] **Clear Filters**: Click "Clear" button, verify all users show again
- [ ] **Empty Search**: Search for non-existent user, verify "no results" message

#### Role Management
- [ ] **Change Role**: Change student to teacher, verify role updates
- [ ] **Change to Admin**: Change user to admin, verify admin access granted
- [ ] **Role Persistence**: Change role, refresh page, verify role persists
- [ ] **Audit Log**: Check `admin_audit_log` table, verify role change logged

#### Category Assignment
- [ ] **Assign Category**: Assign teacher to "Young Learners" category
- [ ] **Multiple Categories**: Assign teacher to multiple categories (should replace existing)
- [ ] **Category Display**: Verify category badges appear in teacher list
- [ ] **Category Persistence**: Refresh page, verify categories still assigned

#### Account Suspension
- [ ] **Suspend Teacher**: Suspend teacher account, verify status changes to "Suspended"
- [ ] **Suspend Student**: Suspend student account, verify status changes
- [ ] **Suspended Login**: Try logging in as suspended user (should fail or show error)
- [ ] **Activate Account**: Activate suspended account, verify can login again

---

### 4. Admin Dashboard - Financial Reports 💰

#### Revenue Metrics
- [ ] **View Reports Tab**: Navigate to Reports → Financial Reports
- [ ] **Date Range Filter**: Select date range, click "Generate Report"
- [ ] **Total Purchases**: Verify amount matches wallet transactions
- [ ] **Trial Payments**: Verify trial payments tracked separately
- [ ] **Refunds**: Verify refunds tracked and deducted from revenue
- [ ] **Net Revenue**: Verify calculation: Purchases + Trials - Refunds

#### Refund Tracking
- [ ] **View Refunds**: Check if refunds appear in refund tracking table
- [ ] **Refund Details**: Verify student name, amount, reference ID correct
- [ ] **Refund Amount**: Verify refunds show negative amounts (red color)

#### CSV Export
- [ ] **Export Transactions**: Click "Export Wallet Transactions"
- [ ] **File Download**: Verify CSV file downloads
- [ ] **CSV Content**: Open CSV, verify all columns present:
  - ID, Student ID, Student Name, Type, Amount, Status, Stripe Payment ID, Reference ID, Description, Created At
- [ ] **Date Filter**: Apply date filter, export, verify only filtered transactions in CSV

---

### 5. Admin Dashboard - Scheduling 📅

#### Manual Lesson Creation
- [ ] **Create Lesson Form**: Fill out form (teacher, student, date, time, duration)
- [ ] **Submit Lesson**: Click "Create Lesson"
- [ ] **Success Message**: Verify lesson created successfully
- [ ] **Lesson Appears**: Check "All Lessons" table, verify lesson appears
- [ ] **Calendar View**: Verify lesson appears on calendar (if implemented)

#### Conflict Detection
- [ ] **Create Overlapping Lesson**: Try creating lesson at same time for same teacher
- [ ] **Conflict Warning**: Verify conflict detected and error message shown
- [ ] **Conflict Table**: Check "Scheduling Conflicts" section, verify conflict listed
- [ ] **Conflict Details**: Verify conflict shows lesson IDs and times

#### Lesson Management
- [ ] **View All Lessons**: Check "All Lessons" table displays correctly
- [ ] **Cancel Lesson**: Cancel a scheduled lesson, verify status changes
- [ ] **Lesson Status**: Verify status colors: Scheduled (blue), Completed (green), Cancelled (red)
- [ ] **Join Classroom**: Click "View" button, verify navigates to classroom

---

### 6. Admin Dashboard - Global Settings ⚙️

#### Timezone Settings
- [ ] **Change Timezone**: Select different timezone (e.g., Pacific Time)
- [ ] **Save Settings**: Click "Save Global Settings"
- [ ] **Settings Persist**: Refresh page, verify timezone still selected
- [ ] **Settings Applied**: Check if timezone affects date/time displays

#### Currency Settings
- [ ] **Change Currency**: Select different currency (e.g., EUR)
- [ ] **Change Symbol**: Change currency symbol (e.g., €)
- [ ] **Save Settings**: Verify settings save
- [ ] **Currency Display**: Check if currency displays update across site

#### Feature Flags
- [ ] **Disable Trial Lessons**: Uncheck "Trial Lessons Enabled", save
- [ ] **Verify Disabled**: Try booking trial lesson, verify option unavailable
- [ ] **Disable Wallet**: Uncheck "Wallet System Enabled", verify wallet features hidden
- [ ] **Disable Classroom**: Uncheck "Classroom Enabled", verify classroom button hidden
- [ ] **Re-enable Features**: Re-enable features, verify they work again

#### Notification Settings
- [ ] **Toggle Email**: Enable/disable email notifications, verify saves
- [ ] **Toggle SMS**: Enable/disable SMS notifications, verify saves

---

### 7. Teacher Dashboard - Calendar 📆

#### FullCalendar Display
- [ ] **Calendar Renders**: Navigate to Calendar tab, verify FullCalendar displays
- [ ] **Month View**: Verify month view shows lessons
- [ ] **Week View**: Switch to week view, verify lessons appear
- [ ] **Day View**: Switch to day view, verify lessons appear
- [ ] **Navigation**: Click prev/next, verify calendar navigates

#### Color Coding
- [ ] **Scheduled Lessons**: Verify blue color for scheduled lessons
- [ ] **Trial Lessons**: Verify yellow color for trial lessons
- [ ] **Completed Lessons**: Verify green color for completed lessons
- [ ] **Cancelled Lessons**: Verify red color for cancelled lessons
- [ ] **Category Colors**: Verify Young Learners (cyan) and Coding (purple) colors

#### Calendar Interactions
- [ ] **Click Lesson**: Click on lesson in calendar, verify navigates to classroom
- [ ] **Hover Effect**: Hover over lesson, verify cursor changes to pointer
- [ ] **Legend**: Verify color legend displays correctly

---

### 8. Teacher Dashboard - Student Management 👨‍🎓

#### Filters & Search
- [ ] **Search Students**: Type student name, verify results filter
- [ ] **Filter by Category**: Select category filter, verify only matching students show
- [ ] **Sort Options**: Test all sort options:
  - Most Recent
  - Name (A-Z)
  - Most Lessons
- [ ] **Clear Filters**: Click clear, verify all students show

#### Attendance Tracking
- [ ] **Attendance Rate**: Verify attendance percentage displays correctly
- [ ] **Completed Count**: Verify completed lessons count accurate
- [ ] **Cancelled Count**: Verify cancelled lessons count accurate
- [ ] **No-Show Count**: Verify no-show count accurate
- [ ] **After Lesson**: Complete a lesson, verify attendance updates

#### Student Notes
- [ ] **Add Note**: Type note in text field, click "Add Note"
- [ ] **Note Saves**: Verify note saves and appears in student card
- [ ] **Last Note**: Verify "Last Note" displays most recent note
- [ ] **Multiple Notes**: Add multiple notes, verify latest shows

#### Student Actions
- [ ] **Message Student**: Click "Message" button, verify navigates to messaging
- [ ] **Assign Work**: Click "Assign Work", verify assignment modal opens
- [ ] **View Profile**: Click student name, verify navigates to profile

---

### 9. Student Dashboard - Calendar 📅

#### FullCalendar Display
- [ ] **Calendar Renders**: Navigate to Bookings tab, verify calendar displays
- [ ] **Lessons Display**: Verify all lessons appear on calendar
- [ ] **Color Coding**: Verify colors match status (same as teacher calendar)
- [ ] **Views**: Test month/week/day views

#### Calendar Interactions
- [ ] **Click Lesson**: Click lesson, verify navigates to classroom
- [ ] **Hover**: Hover over lesson, verify cursor changes

---

### 10. Student Booking Flow 🛒

#### Browse Teachers
- [ ] **Category Pages**: Navigate to category pages (Young Learners, Adults, Coding)
- [ ] **Teacher List**: Verify teachers display with ratings and reviews
- [ ] **Teacher Profile**: Click teacher, verify profile page loads
- [ ] **Availability**: Verify teacher availability displays

#### Select & Book
- [ ] **Book Button**: Click "Book Trial/Lesson" button
- [ ] **Date Selection**: Select date from calendar
- [ ] **Time Selection**: Select available time slot
- [ ] **Timezone**: Verify timezone selection works
- [ ] **Submit Booking**: Click submit, verify redirects to payment

#### Payment Flow
- [ ] **Stripe Checkout**: Verify Stripe checkout form appears
- [ ] **Test Card**: Use test card `4242 4242 4242 4242`
- [ ] **Payment Success**: Complete payment, verify redirects to success page
- [ ] **Wallet Update**: Check wallet balance, verify updated
- [ ] **Lesson Created**: Check "My Lessons", verify lesson appears

#### Trial Lesson
- [ ] **Trial Checkout**: Book trial lesson, verify uses trial price
- [ ] **Trial Credit**: After payment, verify trial credit added to wallet
- [ ] **Trial Used**: Book lesson with trial credit, verify trial marked as used
- [ ] **Second Trial**: Try booking second trial, verify blocked

---

### 11. Wallet System 💳

#### Balance Display
- [ ] **View Balance**: Check student dashboard, verify balance displays
- [ ] **Balance Accuracy**: Verify balance matches transactions
- [ ] **After Payment**: Add funds, verify balance updates immediately
- [ ] **After Booking**: Book lesson, verify balance decreases

#### Add Funds
- [ ] **Add Funds Button**: Click "Add Funds" button
- [ ] **Stripe Checkout**: Verify Stripe checkout appears
- [ ] **Payment**: Complete payment
- [ ] **Funds Added**: Verify funds added to wallet
- [ ] **Transaction Record**: Check transaction history, verify transaction recorded

#### Transaction History
- [ ] **View History**: Check transaction history (if available)
- [ ] **Transaction Types**: Verify different types show:
  - Purchase (top-up)
  - Trial (trial payment)
  - Deduction (lesson booking)
  - Refund
- [ ] **Transaction Details**: Verify amounts, dates, reference IDs correct

#### Edge Cases
- [ ] **Insufficient Funds**: Try booking with insufficient balance, verify error
- [ ] **Negative Balance**: Try to create negative balance, verify prevented
- [ ] **Concurrent Bookings**: Open two tabs, try booking simultaneously, verify no double deduction

---

### 12. Classroom - Video/Audio 🎥

#### Test Classroom (Teacher)
- [ ] **Test Button**: Click "Test Classroom" button
- [ ] **Classroom Loads**: Verify classroom page loads
- [ ] **Video Stream**: Verify teacher's video stream starts
- [ ] **Audio**: Verify audio works (check microphone permissions)
- [ ] **No Lesson Required**: Verify test works without scheduled lesson

#### Join Classroom
- [ ] **Student Join**: Student joins scheduled lesson
- [ ] **Teacher Join**: Teacher joins same lesson
- [ ] **Video Streams**: Verify both video streams appear
- [ ] **Audio**: Verify both can hear each other
- [ ] **Connection**: Verify WebRTC connection establishes
- [ ] **Stability**: Leave connected for 5+ minutes, verify connection stable

#### WebRTC Features
- [ ] **ICE Candidates**: Check browser console, verify ICE candidates exchanged
- [ ] **Peer Connection**: Verify peer connection state changes to "connected"
- [ ] **Stream Sharing**: Verify local and remote streams work
- [ ] **Reconnection**: Disconnect and reconnect, verify reconnects

---

### 13. Classroom - Whiteboard 🎨

#### Drawing
- [ ] **Draw Tool**: Select draw tool, draw on whiteboard
- [ ] **Sync**: Verify drawing appears on other participant's screen
- [ ] **Erase**: Use erase tool, verify erases correctly
- [ ] **Clear**: Clear whiteboard, verify clears for both participants

#### Collaboration
- [ ] **Simultaneous Drawing**: Both participants draw, verify no conflicts
- [ ] **Cursor Movement**: Move cursor, verify cursor position syncs
- [ ] **Operations Sync**: Verify all whiteboard operations sync in real-time

---

### 14. Classroom - Messaging 💬

#### Send Messages
- [ ] **Type Message**: Type message in chat box
- [ ] **Send**: Click send or press Enter
- [ ] **Message Appears**: Verify message appears in chat
- [ ] **Other Participant**: Verify other participant sees message
- [ ] **Message History**: Refresh page, verify messages persist

#### Real-Time Updates
- [ ] **Polling**: Check if messages appear without refresh
- [ ] **Timestamps**: Verify message timestamps display
- [ ] **Sender Names**: Verify sender names display correctly

---

### 15. Messaging System 💌

#### Send Direct Message
- [ ] **Student to Teacher**: Student sends message to teacher
- [ ] **Teacher Receives**: Teacher sees message in inbox
- [ ] **Unread Count**: Verify unread count increments
- [ ] **Read Status**: Teacher reads message, verify marked as read
- [ ] **Seen Timestamp**: Verify "seen" timestamp displays

#### File Attachments
- [ ] **Upload File**: Attach file to message
- [ ] **File Validation**: Try uploading large file (>5MB), verify error
- [ ] **File Types**: Try uploading invalid file type, verify error
- [ ] **File Display**: Verify attachment displays in message thread
- [ ] **Download**: Click attachment, verify downloads

#### Message Threads
- [ ] **Thread View**: Verify messages grouped by thread
- [ ] **Latest Message**: Verify latest message preview shows
- [ ] **Unread Badge**: Verify unread count badge displays
- [ ] **Click Thread**: Click thread, verify opens message conversation

---

### 16. Booking & Scheduling ⏰

#### Availability Check
- [ ] **Teacher Availability**: Check teacher's availability slots
- [ ] **Available Slots**: Verify only available slots shown
- [ ] **Timezone Conversion**: Verify times convert correctly for student's timezone
- [ ] **Past Slots**: Verify past time slots not shown

#### Double Booking Prevention
- [ ] **Book Lesson**: Book a lesson
- [ ] **Try Overlap**: Try booking overlapping lesson for same teacher
- [ ] **Error Message**: Verify error message appears
- [ ] **Database Check**: Verify database prevents conflict

#### Lesson Management
- [ ] **Cancel Lesson**: Cancel a scheduled lesson
- [ ] **Status Update**: Verify lesson status changes to "cancelled"
- [ ] **Refund**: If applicable, verify refund processed
- [ ] **Calendar Update**: Verify calendar updates to show cancelled lesson

---

### 17. Security Testing 🔒

#### SQL Injection
- [ ] **Input Fields**: Try SQL injection in search fields (e.g., `' OR '1'='1`)
- [ ] **Verify Safe**: Verify no SQL errors, queries still work correctly
- [ ] **Prepared Statements**: Check all queries use prepared statements

#### XSS Prevention
- [ ] **Script Tags**: Try entering `<script>alert('XSS')</script>` in text fields
- [ ] **Verify Escaped**: Verify script tags escaped, not executed
- [ ] **Output Escaping**: Check all output uses `h()` function

#### CSRF Protection
- [ ] **Form Submission**: Submit forms, verify CSRF tokens (if implemented)
- [ ] **Direct POST**: Try direct POST without form, verify fails

#### File Upload Security
- [ ] **Invalid Files**: Try uploading PHP files, executables
- [ ] **Verify Blocked**: Verify invalid file types rejected
- [ ] **File Size**: Try uploading very large files, verify size limit enforced

---

### 18. Browser Compatibility 🌐

#### Desktop Browsers
- [ ] **Chrome** (latest): Test all features
- [ ] **Firefox** (latest): Test all features
- [ ] **Safari** (latest): Test all features
- [ ] **Edge** (latest): Test all features

#### Mobile Browsers
- [ ] **iOS Safari**: Test on iPhone/iPad
- [ ] **Chrome Mobile**: Test on Android
- [ ] **Responsive Design**: Verify layout adapts to mobile screens
- [ ] **Touch Interactions**: Verify buttons/links work with touch

#### Features to Test Per Browser
- [ ] **FullCalendar**: Verify calendar renders and works
- [ ] **WebRTC**: Verify video/audio works
- [ ] **File Uploads**: Verify file uploads work
- [ ] **Forms**: Verify all forms submit correctly
- [ ] **Navigation**: Verify navigation works

---

### 19. Performance Testing ⚡

#### Page Load Times
- [ ] **Dashboard Load**: Time dashboard page load (should be < 3 seconds)
- [ ] **Calendar Render**: Time calendar rendering (should be < 2 seconds)
- [ ] **Large Data Sets**: Test with 100+ lessons, verify performance acceptable

#### Database Performance
- [ ] **Query Speed**: Check slow query log (if enabled)
- [ ] **Index Usage**: Verify indexes used for common queries
- [ ] **Connection Pooling**: Verify database connections managed efficiently

#### API Response Times
- [ ] **API Endpoints**: Test API response times (should be < 1 second)
- [ ] **Polling**: Verify polling doesn't overload server
- [ ] **WebRTC Signaling**: Verify signaling messages processed quickly

---

### 20. Integration Testing 🔗

#### Stripe Integration
- [ ] **Test Mode**: Verify using Stripe test mode
- [ ] **Payment Processing**: Complete test payment
- [ ] **Webhook**: Verify webhook received and processed
- [ ] **Idempotency**: Try duplicate webhook, verify handled correctly
- [ ] **Wallet Credit**: Verify wallet credited after payment

#### Google Calendar (if enabled)
- [ ] **Connect Calendar**: Connect teacher's Google Calendar
- [ ] **Event Creation**: Book lesson, verify event created in Google Calendar
- [ ] **Event Update**: Update lesson, verify event updated
- [ ] **Event Deletion**: Cancel lesson, verify event deleted

---

### 21. Error Handling 🚨

#### Database Errors
- [ ] **Connection Failure**: Stop database, verify graceful error message
- [ ] **Query Errors**: Cause query error, verify error logged and user sees friendly message

#### Payment Errors
- [ ] **Card Decline**: Use declined card, verify error message
- [ ] **Network Error**: Simulate network error during payment, verify handled

#### File Upload Errors
- [ ] **Large File**: Upload file exceeding size limit, verify error message
- [ ] **Invalid Type**: Upload invalid file type, verify error message
- [ ] **Permission Error**: Test with restricted upload directory, verify error

---

### 22. Data Integrity ✅

#### Transaction Consistency
- [ ] **Wallet Deduction**: Book lesson, verify wallet deducted AND lesson created
- [ ] **Rollback Test**: Cause error during booking, verify wallet NOT deducted
- [ ] **Orphaned Records**: Check for orphaned records (lessons without transactions, etc.)

#### Audit Logging
- [ ] **Admin Actions**: Perform admin actions, verify logged in `admin_audit_log`
- [ ] **Log Details**: Check log details, verify contain action, target, IP address
- [ ] **Log Integrity**: Verify logs cannot be tampered with

---

## 🎯 Critical Path Testing (Do These First!)

1. **User Login** → Admin/Teacher/Student dashboards load
2. **Student Books Lesson** → Payment → Lesson Created → Appears in Calendar
3. **Teacher Joins Classroom** → Student Joins → Video/Audio Works
4. **Admin Creates Lesson Manually** → Lesson Appears → No Conflicts
5. **Wallet Top-Up** → Balance Updates → Can Book Lesson

---

## 📝 Testing Notes

- **Test Data**: Create test users for each role before testing
- **Test Payments**: Use Stripe test cards (4242 4242 4242 4242)
- **Browser Console**: Keep browser console open to catch JavaScript errors
- **Network Tab**: Monitor network requests for API calls
- **Database**: Check database directly to verify data integrity
- **Error Logs**: Check PHP error logs for server-side errors

---

## ⚠️ Known Issues to Watch For

1. **React Bundle Missing**: Classroom won't work until `npm run build` is run
2. **Database Not Initialized**: Tables won't exist until `db.php` is run
3. **Stripe Keys Missing**: Payments will fail without Stripe API keys
4. **File Permissions**: File uploads may fail if upload directories not writable
5. **Timezone Issues**: Complex timezone conversions may need adjustment

---

**Good luck with testing!** 🚀


