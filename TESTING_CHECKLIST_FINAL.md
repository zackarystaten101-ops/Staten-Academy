# Complete Testing Checklist for Staten Academy Platform

## Critical Pre-Testing Setup

### 1. Environment Setup
- [ ] **Node.js Installation**: Install Node.js (v16+) and npm to build React classroom bundle
  - Run: `npm install` in project root
  - Run: `npm run build` to create `public/assets/js/classroom.bundle.js`
- [ ] **Database Initialization**: Run `db.php` to create all tables
- [ ] **Environment Variables**: Copy `env.example.php` to `env.php` and configure:
  - Stripe API keys (test mode)
  - Database credentials
  - Google Calendar API credentials (if using)
  - Wallet API URL (if using Node.js backend)

### 2. Initial Data Setup
- [ ] Create at least 1 admin account
- [ ] Create at least 2 teacher accounts
- [ ] Create at least 2 student accounts
- [ ] Assign teachers to categories (Young Learners, Adults, Coding)
- [ ] Set up teacher availability slots

---

## Phase 1: Admin Dashboard Testing

### User Management
- [ ] **Search Functionality**
  - Search teachers by name/email
  - Search students by name/email
  - Filter by role (teacher/student)
  - Clear filters works correctly

- [ ] **Role Management**
  - Change user role (student ↔ teacher ↔ admin)
  - Role changes are logged in audit log
  - Role changes persist after page refresh

- [ ] **Category Assignment**
  - Assign teacher to category (Young Learners, Adults, Coding)
  - Multiple categories can be assigned
  - Category assignment replaces existing categories
  - Category appears in teacher profile

- [ ] **Account Suspension**
  - Suspend teacher account
  - Suspend student account
  - Suspended accounts show correct status
  - Activate suspended accounts
  - Suspended users cannot log in

### Financial Reports
- [ ] **Revenue Metrics**
  - Total purchases display correctly
  - Trial payments tracked separately
  - Refunds tracked and deducted from revenue
  - Net revenue calculation is correct
  - Date range filters work

- [ ] **Refund Tracking**
  - Refunds appear in refund tracking table
  - Refund details (student, amount, reference) correct
  - Refunds show negative amounts

- [ ] **CSV Export**
  - Export wallet transactions works
  - CSV file downloads correctly
  - CSV contains all expected columns
  - Date filters apply to CSV export

### Scheduling Oversight
- [ ] **Manual Lesson Creation**
  - Create lesson manually (teacher + student + date/time)
  - Conflict detection works (overlapping lessons)
  - Conflict warning appears before creation
  - Lesson appears in calendar view

- [ ] **Conflict Detection**
  - Overlapping lessons for same teacher detected
  - Conflicts highlighted in conflicts section
  - Conflict details show lesson IDs and times

- [ ] **Lesson Management**
  - View all lessons in table
  - Cancel lessons (non-completed)
  - Lesson status updates correctly
  - Calendar view shows all lessons

### Global Settings
- [ ] **Timezone Settings**
  - Change default timezone
  - Settings save correctly
  - Settings persist after refresh

- [ ] **Currency Settings**
  - Change currency (USD, EUR, GBP, CAD)
  - Currency symbol updates
  - Settings save correctly

- [ ] **Feature Flags**
  - Toggle trial lessons enabled/disabled
  - Toggle wallet system enabled/disabled
  - Toggle classroom enabled/disabled
  - Toggle group classes enabled/disabled
  - Feature flags affect functionality

- [ ] **Notification Settings**
  - Enable/disable email notifications
  - Enable/disable SMS notifications
  - Settings save correctly

---

## Phase 2: Teacher Dashboard Testing

### Calendar & Availability
- [ ] **FullCalendar Integration**
  - Calendar displays correctly (month/week/day views)
  - Lessons appear on calendar with correct colors
  - Color coding: Scheduled (blue), Trial (yellow), Completed (green), Cancelled (red)
  - Category colors: Young Learners (cyan), Coding (purple)
  - Click lesson to navigate to classroom

- [ ] **Availability Management**
  - Add recurring availability slots
  - Add one-time availability slots
  - Delete availability slots
  - Toggle availability on/off
  - Timezone selection works

### Student Management
- [ ] **Student Filters**
  - Search students by name/email
  - Filter by category
  - Sort by: Most Recent, Name (A-Z), Most Lessons
  - Clear filters works

- [ ] **Progress Tracking**
  - Attendance rate displays correctly
  - Completed lessons count accurate
  - Cancelled lessons count accurate
  - No-show count accurate
  - Progress updates after lessons

- [ ] **Student Notes**
  - Add private notes about students
  - Notes save correctly
  - Last note displays in student card

- [ ] **Student Actions**
  - Message student works
  - Assign work to student works
  - View student profile works

---

## Phase 3: Student Dashboard Testing

### Calendar View
- [ ] **FullCalendar Integration**
  - Calendar displays correctly
  - All lessons appear with correct colors
  - Click lesson to join classroom
  - Month/week/day views work

### Booking Flow
- [ ] **Browse Teachers**
  - Navigate to category pages (Young Learners, Adults, Coding)
  - View teacher profiles
  - See teacher ratings and reviews
  - See teacher availability

- [ ] **Select Teacher**
  - Click "Book Trial/Lesson" button
  - Select date and time from calendar
  - Timezone selection works
  - See available time slots

- [ ] **Payment Flow**
  - Trial lesson checkout works
  - Regular lesson checkout works
  - Stripe payment form appears
  - Payment success redirects correctly
  - Wallet balance updates after payment

- [ ] **Booking Confirmation**
  - Lesson appears in "My Lessons"
  - Lesson appears on calendar
  - Confirmation email sent (if enabled)
  - Google Calendar event created (if connected)

### Wallet System
- [ ] **Wallet Balance**
  - Balance displays correctly
  - Balance updates after payment
  - Balance updates after lesson booking

- [ ] **Add Funds**
  - Click "Add Funds" button
  - Stripe checkout works
  - Funds added to wallet
  - Transaction appears in history

- [ ] **Trial Credits**
  - Trial credit appears after trial payment
  - Trial credit used for first lesson
  - Cannot use trial twice

---

## Phase 4: Classroom Testing

### Video/Audio
- [ ] **Test Classroom (Teacher)**
  - Click "Test Classroom" button
  - Video stream starts
  - Audio works
  - Can test without lesson

- [ ] **Join Classroom**
  - Student can join scheduled lesson
  - Teacher can join scheduled lesson
  - Video streams work for both
  - Audio works for both
  - Can join 1 hour before lesson time

- [ ] **WebRTC Connection**
  - Peer connection establishes
  - ICE candidates exchanged
  - Video/audio streams shared
  - Connection stable

### Whiteboard
- [ ] **Whiteboard Functionality**
  - Draw on whiteboard
  - Erase works
  - Clear whiteboard
  - Changes sync between participants

### Messaging
- [ ] **In-Classroom Chat**
  - Send messages
  - Receive messages
  - Messages appear in real-time
  - Message history persists

---

## Phase 5: Messaging System

### Direct Messages
- [ ] **Send Message**
  - Student can message teacher
  - Teacher can message student
  - Admin can message any user
  - Messages save correctly

- [ ] **File Attachments**
  - Upload file attachment
  - File size validation works
  - File type validation works
  - Attachment displays correctly
  - Download attachment works

- [ ] **Read/Unread Status**
  - Unread count displays correctly
  - Mark as read works
  - "Seen" timestamp displays
  - Read status syncs

### Message Threads
- [ ] **Thread View**
  - Messages grouped by thread
  - Latest message shows
  - Unread count per thread
  - Click thread to view messages

---

## Phase 6: Booking & Scheduling

### Lesson Booking
- [ ] **Availability Check**
  - Only available slots shown
  - Teacher timezone respected
  - Student timezone conversion works
  - Conflicts prevented

- [ ] **Double Booking Prevention**
  - Cannot book overlapping lessons
  - Error message appears
  - Database prevents conflicts

- [ ] **Wallet Deduction**
  - Funds deducted on booking
  - Transaction recorded
  - Balance updates immediately
  - Transaction ID linked to lesson

### Lesson Management
- [ ] **Cancel Lesson**
  - Student can cancel (if allowed)
  - Teacher can cancel
  - Admin can cancel
  - Refund processed (if applicable)
  - Calendar updates

---

## Phase 7: Security Testing

### Authentication
- [ ] **Session Management**
  - Sessions expire correctly
  - Logout works
  - Cannot access dashboard without login
  - Role-based redirects work

### Authorization
- [ ] **Role-Based Access**
  - Students cannot access admin features
  - Teachers cannot access admin features
  - Admins can access all features
  - API endpoints check roles

### Payment Security
- [ ] **Stripe Integration**
  - Test mode works
  - Webhook processing works
  - Idempotency prevents duplicate processing
  - Payment verification works

### Data Security
- [ ] **SQL Injection Prevention**
  - All queries use prepared statements
  - User input sanitized
  - No raw SQL with user data

- [ ] **XSS Prevention**
  - All output escaped
  - File uploads validated
  - No script injection possible

---

## Phase 8: Edge Cases

### Wallet Edge Cases
- [ ] **Insufficient Funds**
  - Cannot book with insufficient balance
  - Error message appears
  - Balance check works

- [ ] **Concurrent Bookings**
  - Multiple simultaneous bookings
  - Race conditions prevented
  - Row-level locking works
  - No double deductions

- [ ] **Negative Balance Prevention**
  - Wallet cannot go negative
  - Validation works
  - Error handling correct

### Booking Edge Cases
- [ ] **Past Lessons**
  - Cannot book in the past
  - Validation works

- [ ] **Same Time Slots**
  - Cannot book overlapping times
  - Conflict detection works

- [ ] **Timezone Edge Cases**
  - Different timezones handled
  - Conversion accurate
  - Daylight saving time handled

### Data Integrity
- [ ] **Transaction Consistency**
  - Database transactions work
  - Rollback on errors
  - No orphaned records

---

## Phase 9: Performance Testing

- [ ] **Page Load Times**
  - Dashboard loads < 3 seconds
  - Calendar renders quickly
  - Large data sets handled

- [ ] **Database Queries**
  - Queries optimized
  - Indexes used
  - No N+1 queries

- [ ] **API Response Times**
  - API endpoints respond < 1 second
  - Polling doesn't overload server

---

## Phase 10: Browser Compatibility

- [ ] **Chrome** (latest)
- [ ] **Firefox** (latest)
- [ ] **Safari** (latest)
- [ ] **Edge** (latest)
- [ ] **Mobile browsers** (iOS Safari, Chrome Mobile)

---

## Phase 11: Integration Testing

### Stripe Integration
- [ ] **Payment Processing**
  - Test payments work
  - Webhooks received
  - Wallet credited correctly
  - Transaction recorded

### Google Calendar (if enabled)
- [ ] **Event Creation**
  - Events created on booking
  - Events updated on changes
  - Events deleted on cancellation

---

## Phase 12: User Experience

- [ ] **Navigation**
  - All links work
  - Breadcrumbs correct
  - Back button works
  - Mobile menu works

- [ ] **Forms**
  - Validation messages clear
  - Required fields marked
  - Error handling user-friendly
  - Success messages appear

- [ ] **Responsive Design**
  - Mobile layout works
  - Tablet layout works
  - Desktop layout works
  - Touch interactions work

---

## Critical Issues to Report

If any of these occur, report immediately:
1. Payment processing fails
2. Wallet balance incorrect
3. Double booking possible
4. Security vulnerabilities
5. Data loss
6. Cannot join classroom
7. Messages not sending/receiving
8. Admin actions not logging

---

## Notes

- Test with real Stripe test cards: `4242 4242 4242 4242`
- Use different browsers for different user roles
- Test timezone conversions with users in different timezones
- Monitor database logs for errors
- Check browser console for JavaScript errors
- Test on actual mobile devices, not just browser dev tools

---

## Post-Testing

After completing all tests:
1. Document any bugs found
2. Verify all critical paths work
3. Test rollback procedures
4. Verify backup/restore works
5. Performance benchmarks met
6. Security audit passed

