# Staten Academy - Comprehensive Testing Checklist

## Pre-Testing Requirements

### Environment Setup
- [ ] Node.js and npm installed (for React bundle build)
- [ ] PHP 7.4+ with required extensions (mysqli, json, curl)
- [ ] MySQL/MariaDB database running
- [ ] Stripe API keys configured in `env.php`
- [ ] Google Calendar API credentials configured (if using calendar features)
- [ ] Web server running (Apache/Nginx/XAMPP)

### Database Setup
- [ ] Run `db.php` to initialize all tables
- [ ] Verify all tables created:
  - [ ] `signaling_queue`
  - [ ] `video_sessions`
  - [ ] `wallet_transactions` (with new columns: status, idempotency_key, lesson_id)
  - [ ] `admin_audit_log`
  - [ ] `whiteboard_operations`
- [ ] Admin account exists: `statenenglishacademy@gmail.com` / `123456789`

### Build Requirements
- [ ] Run `npm install` in project root
- [ ] Run `npm run build` to create `public/assets/js/classroom.bundle.js`
- [ ] Verify bundle file exists and is not empty

---

## Phase 1: Classroom Functionality Tests

### 1.1 Database Tables
- [ ] Verify `signaling_queue` table exists and has correct structure
- [ ] Verify `video_sessions` table exists with `is_test_session` column
- [ ] Verify `whiteboard_operations` table exists
- [ ] Test inserting a test session into `video_sessions`

### 1.2 Test Classroom (Teacher)
- [ ] Login as teacher
- [ ] Navigate to teacher dashboard
- [ ] Click "Test Classroom" button
- [ ] Verify test classroom opens
- [ ] Verify "Test Mode" indicator appears
- [ ] Test microphone access
- [ ] Test camera access
- [ ] Verify test session created in database with `is_test_session = TRUE`

### 1.3 Regular Classroom (Student-Teacher)
- [ ] Create a lesson booking (student books with teacher)
- [ ] As teacher: Navigate to lesson and click "Join Classroom"
- [ ] As student: Navigate to lesson and click "Join Classroom" (within 4 minutes of start time)
- [ ] Verify both can see each other's video
- [ ] Test audio communication
- [ ] Test whiteboard functionality
- [ ] Test screen sharing (if implemented)
- [ ] Verify signaling messages are stored in `signaling_queue`
- [ ] Verify messages are processed and marked as processed

### 1.4 Classroom Access Restrictions
- [ ] As student: Try to join classroom more than 4 minutes before lesson start → Should show restriction message
- [ ] As student: Try to join classroom more than 1 hour after lesson ended → Should show restriction message
- [ ] As teacher: Verify can always join (no restrictions)
- [ ] As unauthorized user: Try to access classroom with wrong session ID → Should be denied

### 1.5 Session Management API
- [ ] Test `GET /api/sessions.php?action=get-or-create&lessonId=X` → Should return session
- [ ] Test `GET /api/sessions.php?action=active&sessionId=X` → Should return session details
- [ ] Test `POST /api/sessions.php?action=end` → Should end session
- [ ] Verify test sessions can be created via API

---

## Phase 2: Wallet System Tests

### 2.1 Wallet Transaction Logging
- [ ] Verify `wallet_transactions` table has `status` column
- [ ] Verify `wallet_transactions` table has `idempotency_key` column
- [ ] Verify `wallet_transactions` table has `lesson_id` column
- [ ] Test adding funds → Verify transaction recorded with status='confirmed'
- [ ] Test deducting funds → Verify transaction recorded with status='confirmed'
- [ ] Verify idempotency keys are unique

### 2.2 Wallet Balance Management
- [ ] Test adding funds to student wallet
- [ ] Verify balance increases correctly
- [ ] Test deducting funds for lesson booking
- [ ] Verify balance decreases correctly
- [ ] Test insufficient funds scenario → Should fail gracefully
- [ ] Verify wallet cannot go negative (test with row-level locking)

### 2.3 Concurrent Booking Prevention
- [ ] Open two browser tabs as same student
- [ ] Try to book same time slot simultaneously in both tabs
- [ ] Verify only one booking succeeds
- [ ] Verify other booking shows conflict error
- [ ] Test booking same teacher at same time from different students → Should show conflict

### 2.4 Webhook Idempotency
- [ ] Send duplicate Stripe webhook with same session ID
- [ ] Verify second webhook is ignored (idempotency check)
- [ ] Verify no duplicate transactions created
- [ ] Check audit log for idempotency handling

### 2.5 Admin Wallet Reconciliation
- [ ] Login as admin
- [ ] Navigate to "Wallet Reconciliation" tab
- [ ] Verify wallet statistics display correctly
- [ ] Test filtering transactions by:
  - [ ] Student
  - [ ] Type (purchase, deduction, refund, trial, adjustment)
  - [ ] Status (pending, confirmed, failed, cancelled)
  - [ ] Date range
- [ ] Test CSV export → Verify file downloads with correct data
- [ ] Test manual wallet adjustment:
  - [ ] Add funds to student wallet
  - [ ] Deduct funds from student wallet
  - [ ] Verify adjustment logged in `admin_audit_log`
  - [ ] Verify transaction appears in ledger

---

## Phase 3: Booking Flow Tests

### 3.1 Student Booking Process
- [ ] Login as student
- [ ] Navigate to schedule page
- [ ] Browse teachers by category
- [ ] Select a teacher
- [ ] View teacher profile
- [ ] Select available time slot
- [ ] Complete booking
- [ ] Verify lesson created in database
- [ ] Verify wallet funds deducted (if not trial)
- [ ] Verify trial credit deducted (if trial)
- [ ] Verify booking confirmation message
- [ ] Verify lesson appears in student dashboard

### 3.2 Trial Lesson Flow
- [ ] Login as new student (hasn't used trial)
- [ ] Book trial lesson with teacher
- [ ] Complete Stripe payment ($25)
- [ ] Verify trial credit added to wallet
- [ ] Verify trial marked as used
- [ ] Book lesson using trial credit
- [ ] Verify trial credit deducted
- [ ] Try to book another trial → Should require payment

### 3.3 Payment Integration
- [ ] Test Stripe checkout session creation
- [ ] Complete test payment
- [ ] Verify webhook received and processed
- [ ] Verify funds added to wallet
- [ ] Verify transaction recorded
- [ ] Test payment failure scenario
- [ ] Verify error handling

### 3.4 Google Calendar Integration
- [ ] As teacher: Connect Google Calendar
- [ ] Book lesson with teacher who has calendar connected
- [ ] Verify calendar event created
- [ ] Verify event has correct details (time, participants, description)
- [ ] Test calendar sync on lesson cancellation

---

## Phase 4: Admin Dashboard Tests

### 4.1 User Management
- [ ] Login as admin
- [ ] Navigate to Users tab
- [ ] View teachers list
- [ ] View students list
- [ ] Test user search functionality
- [ ] Test role assignment
- [ ] Test teacher category assignment (Kids/Adults/Coding)
- [ ] Test account suspension/activation

### 4.2 Wallet Reconciliation (Already tested in Phase 2.5)
- [ ] All tests from Phase 2.5

### 4.3 Audit Logging
- [ ] Perform admin action (wallet adjustment, role change, etc.)
- [ ] Verify action logged in `admin_audit_log` table
- [ ] Verify log includes: admin_id, action, target_type, target_id, details, ip_address
- [ ] Test viewing audit log (if UI implemented)

### 4.4 Financial Reports
- [ ] View revenue dashboard
- [ ] Verify wallet top-up summary
- [ ] Verify refund tracking
- [ ] Test date range filtering
- [ ] Test export functionality

---

## Phase 5: Security Tests

### 5.1 Authentication & Authorization
- [ ] Try to access admin dashboard without login → Should redirect to login
- [ ] Try to access admin dashboard as student → Should be denied
- [ ] Try to access teacher dashboard as student → Should be denied
- [ ] Try to access student dashboard as teacher → Should be denied
- [ ] Test session timeout
- [ ] Test logout functionality

### 5.2 API Security
- [ ] Test `book-lesson-api.php` without authentication → Should return 401
- [ ] Test `book-lesson-api.php` as teacher → Should return 403
- [ ] Test `api/sessions.php` without authentication → Should return 401
- [ ] Test `api/webrtc.php` without authentication → Should return 401
- [ ] Test accessing other user's session → Should be denied

### 5.3 SQL Injection Prevention
- [ ] Test all user inputs with SQL injection attempts:
  - [ ] `' OR '1'='1`
  - [ ] `'; DROP TABLE users; --`
  - [ ] `1' UNION SELECT * FROM users --`
- [ ] Verify all queries use prepared statements
- [ ] Verify no direct query() calls with user input

### 5.4 Payment Security
- [ ] Verify Stripe webhook signature verification
- [ ] Test webhook with invalid signature → Should be rejected
- [ ] Verify no card data stored in database
- [ ] Verify payment amounts validated server-side
- [ ] Test idempotency on webhook processing

### 5.5 Input Validation
- [ ] Test booking with invalid date format → Should be rejected
- [ ] Test booking with past date → Should be rejected
- [ ] Test wallet adjustment with negative amount → Should be rejected
- [ ] Test file uploads with invalid file types → Should be rejected
- [ ] Test file uploads exceeding size limit → Should be rejected

---

## Phase 6: Edge Cases & Error Handling

### 6.1 Wallet Edge Cases
- [ ] Test booking with exact wallet balance → Should succeed
- [ ] Test booking with insufficient funds → Should show clear error
- [ ] Test concurrent bookings with same wallet → Verify locking works
- [ ] Test refund processing
- [ ] Test wallet adjustment with very large amount

### 6.2 Booking Edge Cases
- [ ] Test booking at exact lesson start time
- [ ] Test booking overlapping time slots → Should be prevented
- [ ] Test booking with teacher who has no availability
- [ ] Test cancelling a booked lesson
- [ ] Test rescheduling a lesson

### 6.3 Classroom Edge Cases
- [ ] Test classroom with slow internet connection
- [ ] Test classroom when one participant disconnects
- [ ] Test classroom with browser refresh (reconnection)
- [ ] Test classroom with multiple participants (if group classes supported)
- [ ] Test classroom when lesson time expires

### 6.4 Database Edge Cases
- [ ] Test with database connection failure → Should show graceful error
- [ ] Test with missing required tables → Should show clear error
- [ ] Test transaction rollback on error
- [ ] Test with very large result sets

---

## Phase 7: Performance Tests

### 7.1 Database Performance
- [ ] Test wallet reconciliation with 1000+ transactions → Should load in < 3 seconds
- [ ] Test booking query with many concurrent requests
- [ ] Verify database indexes are used (check EXPLAIN queries)

### 7.2 Classroom Performance
- [ ] Test classroom with multiple simultaneous sessions
- [ ] Test signaling queue processing speed
- [ ] Monitor database query performance during classroom use

### 7.3 Frontend Performance
- [ ] Test page load times
- [ ] Test dashboard with many lessons/bookings
- [ ] Test calendar rendering with many events

---

## Phase 8: Browser Compatibility

### 8.1 Desktop Browsers
- [ ] Chrome (latest)
- [ ] Firefox (latest)
- [ ] Safari (latest)
- [ ] Edge (latest)

### 8.2 Mobile Browsers
- [ ] Chrome Mobile
- [ ] Safari Mobile
- [ ] Test responsive design on various screen sizes

### 8.3 Classroom WebRTC
- [ ] Test WebRTC in Chrome
- [ ] Test WebRTC in Firefox
- [ ] Test WebRTC in Safari (may have limitations)
- [ ] Test on mobile devices

---

## Phase 9: Integration Tests

### 9.1 End-to-End Booking Flow
1. [ ] Student logs in
2. [ ] Student browses teachers
3. [ ] Student selects teacher and time
4. [ ] Student completes payment (if needed)
5. [ ] Lesson created in database
6. [ ] Wallet funds deducted
7. [ ] Calendar event created (if applicable)
8. [ ] Confirmation email sent (if implemented)
9. [ ] Lesson appears in both student and teacher dashboards
10. [ ] Both can join classroom at lesson time
11. [ ] Lesson completed and marked as such

### 9.2 Trial Lesson Flow
1. [ ] New student signs up
2. [ ] Student books trial lesson
3. [ ] Student pays $25 via Stripe
4. [ ] Webhook processes payment
5. [ ] Trial credit added to wallet
6. [ ] Student books lesson using trial credit
7. [ ] Trial credit deducted
8. [ ] Lesson created

### 9.3 Admin Workflow
1. [ ] Admin logs in
2. [ ] Admin views pending requests
3. [ ] Admin approves teacher application
4. [ ] Admin adjusts student wallet
5. [ ] Admin views audit log
6. [ ] Admin exports financial report

---

## Phase 10: Data Integrity Tests

### 10.1 Transaction Integrity
- [ ] Verify all wallet transactions have corresponding records
- [ ] Verify no orphaned transactions
- [ ] Verify lesson-wallet transaction linking
- [ ] Verify balance calculations match transaction sum

### 10.2 Referential Integrity
- [ ] Test deleting user with wallet → Should cascade or handle gracefully
- [ ] Test deleting lesson with transaction → Should handle foreign key
- [ ] Test deleting session with signaling messages → Should cleanup

### 10.3 Data Consistency
- [ ] Verify student wallet balance matches sum of transactions
- [ ] Verify lesson statuses are consistent
- [ ] Verify no duplicate bookings
- [ ] Verify no duplicate transactions (idempotency)

---

## Known Issues & Limitations

### Build Requirements
- **React Bundle**: Requires `npm run build` to be executed. Bundle must exist at `public/assets/js/classroom.bundle.js` for classroom to work.

### Browser Compatibility
- **Safari WebRTC**: May have limitations with WebRTC. Test thoroughly.
- **Mobile WebRTC**: May require additional permissions handling.

### Dependencies
- **Node.js**: Required for building React classroom bundle
- **Stripe PHP SDK**: Required for payment processing
- **Google Calendar API**: Optional, required only if using calendar features

---

## Post-Testing Actions

### If Tests Pass
1. [ ] Document any issues found
2. [ ] Create backup of database
3. [ ] Deploy to staging environment
4. [ ] Run smoke tests on staging
5. [ ] Deploy to production

### If Tests Fail
1. [ ] Document failure details
2. [ ] Check error logs
3. [ ] Verify database state
4. [ ] Fix issues and re-test
5. [ ] Update this checklist with fixes

---

## Critical Paths to Test First

**Priority 1 (Must Work):**
1. User authentication and authorization
2. Wallet balance management and transactions
3. Lesson booking flow
4. Payment processing
5. Classroom access and WebRTC

**Priority 2 (Should Work):**
1. Admin dashboard features
2. Teacher test classroom
3. Calendar integration
4. Messaging system

**Priority 3 (Nice to Have):**
1. Advanced filtering
2. Export functionality
3. Analytics and reports

---

## Test Data Setup

### Create Test Users
```sql
-- Test Admin (already exists)
-- Email: statenenglishacademy@gmail.com
-- Password: 123456789

-- Test Teacher
INSERT INTO users (email, password, name, role, application_status) 
VALUES ('teacher@test.com', '$2y$10$...', 'Test Teacher', 'teacher', 'approved');

-- Test Student
INSERT INTO users (email, password, name, role, preferred_category) 
VALUES ('student@test.com', '$2y$10$...', 'Test Student', 'student', 'adults');
```

### Create Test Wallet
```sql
-- Add funds to test student
INSERT INTO student_wallet (student_id, balance, trial_credits) 
VALUES (STUDENT_ID, 100.00, 0);
```

---

## Notes

- All tests should be performed in a development/staging environment first
- Never test with real payment credentials in production
- Keep test data separate from production data
- Document any browser-specific issues
- Test with various user roles and permissions
- Verify all error messages are user-friendly
- Check that all sensitive data is properly protected


