---
name: Staten Academy Preply-Style Implementation
overview: Comprehensive implementation plan to transform statenacademy.com into a Preply-style platform with fixed classroom, enhanced wallet system, improved admin/teacher dashboards, and robust calendar/scheduling. Focuses on fixing the blank classroom page, hardening wallet flows, and ensuring all features work reliably.
todos:
  - id: classroom-build
    content: Build React classroom bundle (npm run build) and verify classroom.bundle.js exists
    status: pending
  - id: classroom-tables
    content: Create signaling_queue and video_sessions tables in db.php
    status: completed
  - id: classroom-init
    content: Fix classroom.php React initialization and error handling
    status: completed
    dependencies:
      - classroom-build
  - id: signaling-polling
    content: Implement signaling queue polling in React app (src/utils/polling.ts or new file)
    status: completed
    dependencies:
      - classroom-tables
  - id: webrtc-connection
    content: Fix WebRTC connection flow in src/utils/webrtc.ts and VideoConference.tsx
    status: completed
    dependencies:
      - signaling-polling
  - id: test-classroom-button
    content: Add 'Test Classroom' button to teacher-dashboard.php
    status: completed
    dependencies:
      - classroom-init
  - id: test-room-logic
    content: Implement test room access logic in classroom.php (allow teachers without lesson validation)
    status: completed
    dependencies:
      - test-classroom-button
  - id: wallet-audit
    content: Enhance wallet transaction logging in WalletService.php (add status, reference IDs)
    status: completed
  - id: admin-wallet-reconciliation
    content: Add wallet reconciliation section to admin-dashboard.php (ledger view, CSV export)
    status: completed
    dependencies:
      - wallet-audit
  - id: wallet-edge-cases
    content: "Test and fix wallet edge cases: insufficient funds, concurrent bookings, negative balance prevention"
    status: completed
    dependencies:
      - wallet-audit
  - id: admin-user-management
    content: Add user management section to admin-dashboard.php (search, role assignment, category assignment)
    status: completed
  - id: admin-scheduling
    content: Add scheduling oversight to admin-dashboard.php (calendar view, manual lesson creation, conflicts)
    status: completed
  - id: admin-financial-reports
    content: Add financial reports section to admin-dashboard.php (revenue, wallet top-ups, refunds, exports)
    status: completed
    dependencies:
      - admin-wallet-reconciliation
  - id: admin-audit-log
    content: Create admin_audit_log table and log all admin actions (wallet adjustments, account changes)
    status: completed
  - id: admin-settings
    content: Add Settings section to admin-dashboard.php (timezone, currency, notifications, feature flags)
    status: completed
  - id: teacher-calendar
    content: Enhance teacher calendar view with FullCalendar integration and color-coding
    status: completed
  - id: teacher-student-management
    content: Add enhanced student management to teacher-dashboard.php (filters, progress, attendance)
    status: completed
  - id: student-booking-flow
    content: "Verify and enhance student booking flow: browse → select → book → pay → confirm"
    status: completed
    dependencies:
      - wallet-edge-cases
  - id: student-calendar
    content: Add calendar view to student-dashboard.php showing booked sessions
    status: completed
  - id: timezone-handling
    content: Ensure proper timezone conversion in all calendar views and booking flows
    status: completed
    dependencies:
      - teacher-calendar
      - student-calendar
  - id: security-audit
    content: "Security audit: session validation, role-based access, API security, payment security"
    status: completed
  - id: testing-suite
    content: "Create testing checklist and run E2E tests: book → pay → join classroom flow"
    status: completed
    dependencies:
      - webrtc-connection
      - student-booking-flow
---

# Staten Academy Preply-Style Implementation Plan

## Overview

Transform statenacademy.com into a Preply-style platform with role-based dashboards, live classroom, wallet system, and comprehensive admin tools. Primary focus: fix classroom blank page, harden wallet flows, enhance dashboards.

## Current State Analysis

### Existing Components

- **Classroom**: React app (`src/classroom.tsx`) exists but bundle not built → blank page
- **Wallet**: PHP service + TypeScript backend, database-based transactions
- **WebRTC Signaling**: Database queue (`signaling_queue` table) - keeping this approach
- **Calendar**: FullCalendar integration via `CalendarService`
- **Admin Dashboard**: Basic structure exists, needs enhancements
- **Teacher Dashboard**: Basic structure, needs test classroom feature

### Critical Issues

1. **Classroom blank page**: React bundle (`classroom.bundle.js`) missing - needs build
2. **Database tables**: `signaling_queue` and `video_sessions` may not exist
3. **Wallet reconciliation**: Admin tools need transaction audit features
4. **Test classroom**: Teacher sandbox room not implemented

## Phase 1: Fix Classroom (Priority 1)

### 1.1 Build React Classroom Bundle

**Files**: `vite.config.js`, `src/classroom.tsx`, `package.json`

- Verify `src/classroom.tsx` imports and dependencies
- Run `npm install` to ensure all dependencies
- Build bundle: `npm run build` or `vite build`
- Verify output: `public/assets/js/classroom.bundle.js` exists
- Test classroom page loads React app

### 1.2 Create Missing Database Tables

**File**: `db.php`

Add table creation for WebRTC signaling:

```sql
CREATE TABLE IF NOT EXISTS signaling_queue (
    id INT AUTO_INCREMENT PRIMARY KEY,
    session_id VARCHAR(255) NOT NULL,
    from_user_id INT NOT NULL,
    to_user_id INT NOT NULL,
    message_type ENUM('webrtc-offer', 'webrtc-answer', 'webrtc-ice-candidate') NOT NULL,
    message_data TEXT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    processed_at TIMESTAMP NULL,
    INDEX idx_session (session_id),
    INDEX idx_users (from_user_id, to_user_id)
);

CREATE TABLE IF NOT EXISTS video_sessions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    session_id VARCHAR(255) UNIQUE NOT NULL,
    lesson_id INT,
    teacher_id INT NOT NULL,
    student_id INT NOT NULL,
    status ENUM('active', 'ended', 'cancelled') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    ended_at TIMESTAMP NULL,
    INDEX idx_lesson (lesson_id),
    INDEX idx_users (teacher_id, student_id)
);
```

### 1.3 Fix Classroom.php Initialization

**File**: `classroom.php`

- Verify session ID generation for lessons
- Ensure `data-*` attributes are correctly passed to React root
- Add error handling if bundle fails to load
- Add fallback message if React app doesn't initialize

### 1.4 Implement Signaling Polling

**File**: `src/utils/polling.ts` or new `src/utils/signaling.ts`

- Create polling mechanism to check `signaling_queue` table
- Poll every 500ms-1000ms for new messages
- Process offers, answers, ICE candidates
- Mark messages as processed after handling

### 1.5 WebRTC Connection Flow

**Files**: `src/utils/webrtc.ts`, `src/components/VideoConference.tsx`

- Initialize RTCPeerConnection with STUN servers
- Handle offer/answer exchange via signaling queue
- Process ICE candidates
- Establish video/audio streams
- Handle connection errors and reconnection

## Phase 2: Teacher Test Classroom (Priority 1)

### 2.1 Add Test Room Button

**File**: `teacher-dashboard.php`

- Add prominent "Test Classroom" button in dashboard
- Generate unique test session ID (e.g., `test_teacher_{teacher_id}_{timestamp}`)
- Link to `classroom.php?sessionId={test_session_id}&testMode=true`

### 2.2 Test Room Database Entry

**File**: `db.php` or new migration

- Allow `video_sessions` with `lesson_id = NULL` for test rooms
- Mark test sessions with special status or metadata
- Auto-cleanup test sessions after 24 hours

### 2.3 Test Room Access Logic

**File**: `classroom.php`

- Allow teachers to join test rooms without lesson validation
- Skip student join restrictions for test rooms
- Show "Test Mode" indicator in classroom UI

## Phase 3: Wallet System Hardening (Priority 2)

### 3.1 Transaction Audit Trail

**File**: `app/Services/WalletService.php`

- Ensure all transactions logged to `wallet_transactions` table
- Add transaction status tracking (pending, confirmed, failed)
- Implement idempotency keys for webhook processing
- Add transaction reference linking (Stripe payment ID, lesson ID)

### 3.2 Admin Wallet Reconciliation

**File**: `admin-dashboard.php`

- Add "Wallet Transactions" section
- Display transaction ledger with filters (date range, student, type)
- Show wallet balances per student
- Export to CSV functionality
- Manual wallet adjustment tool (with audit log)

### 3.3 Wallet Edge Cases

**Files**: `book-lesson-api.php`, `app/Services/WalletService.php`

- Test insufficient funds handling
- Test concurrent booking prevention
- Test payment gateway failure recovery
- Ensure wallet cannot go negative
- Handle refund processing correctly

### 3.4 Package Redemption Tracking

**File**: `db.php` (if packages table exists)

- Track package usage per lesson
- Update package remaining sessions on booking
- Handle package expiry
- Show package status in student dashboard

## Phase 4: Admin Dashboard Enhancements (Priority 2)

### 4.1 User Management

**File**: `admin-dashboard.php`

- Add user search and filtering
- Role assignment interface
- Teacher category assignment (Kids/Adults/Coding)
- Account suspension/activation
- Bulk actions (export user list)

### 4.2 Scheduling Oversight

**File**: `admin-dashboard.php` or new `admin-schedule.php`

- Calendar view of all lessons (FullCalendar integration)
- Filter by teacher, student, category, date range
- Manual lesson creation/editing
- Conflict detection and resolution
- Cancel/reschedule tools

### 4.3 Financial Reports

**File**: `admin-dashboard.php`

- Revenue dashboard (daily/weekly/monthly)
- Wallet top-up summary
- Refund tracking
- Teacher earnings export (for offline payment reconciliation)
- Transaction reconciliation reports

### 4.4 Audit Logs

**File**: `db.php` (create `admin_audit_log` table)

```sql
CREATE TABLE IF NOT EXISTS admin_audit_log (
    id INT AUTO_INCREMENT PRIMARY KEY,
    admin_id INT NOT NULL,
    action VARCHAR(100) NOT NULL,
    target_type VARCHAR(50),
    target_id INT,
    details TEXT,
    ip_address VARCHAR(45),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_admin (admin_id),
    INDEX idx_action (action)
);
```

- Log all admin actions (wallet adjustments, account changes, refunds)
- Display audit log in admin dashboard
- Filter by admin, action type, date range

### 4.5 Settings & Integrations

**File**: `admin-dashboard.php` (new Settings section)

- Global timezone settings
- Currency configuration
- Email/SMS notification templates
- Google Calendar sync toggle
- Feature flags (auto-accept bookings, test classroom, maintenance mode)

## Phase 5: Teacher Dashboard Improvements (Priority 3)

### 5.1 Enhanced Calendar View

**File**: `teacher-dashboard.php`

- FullCalendar integration showing all lessons
- Color-code by lesson type (one-on-one, group, trial)
- Drag-and-drop rescheduling (if allowed)
- Availability overlay
- Quick actions (accept/decline, reschedule)

### 5.2 Student Management

**File**: `teacher-dashboard.php`

- Enhanced student list with filters
- Student progress tracking
- Lesson history per student
- Quick message templates
- Attendance tracking

### 5.3 Performance Metrics

**File**: `teacher-dashboard.php`

- Classes taught count
- Average rating display
- Student retention metrics
- Upcoming lessons summary
- Earnings display (read-only, no payout flow)

## Phase 6: Student Dashboard Enhancements (Priority 3)

### 6.1 Booking Flow

**Files**: `schedule.php`, `category-teachers.php`, `teacher-profile.php`

- Ensure teacher browsing by category works
- Verify booking flow: select teacher → select time → wallet deduction → confirmation
- Show booking confirmation with classroom link
- Email notifications on booking

### 6.2 Calendar Integration

**File**: `student-dashboard.php`

- Show booked sessions in calendar view
- Upcoming lessons list
- Past lessons with recordings (if available)
- Join classroom button for upcoming lessons

### 6.3 Wallet UI

**File**: `student-dashboard.php`

- Prominent wallet balance display
- "Add Funds" button (links to payment page)
- Transaction history
- Package status (if packages implemented)

## Phase 7: Calendar & Scheduling Improvements (Priority 3)

### 7.1 Timezone Handling

**Files**: `app/Services/TimezoneService.php`, calendar components

- Ensure all times display in user's timezone
- Convert between teacher/student timezones correctly
- Show timezone in calendar UI
- Handle daylight saving time changes

### 7.2 Recurring Sessions

**File**: `teacher-dashboard.php` or new availability system

- Allow teachers to set recurring availability patterns
- Support weekly recurring lessons
- Bulk open/close time slots
- Handle recurring lesson cancellations

### 7.3 Conflict Detection

**Files**: `book-lesson-api.php`, `app/Services/TeacherService.php`

- Enhanced double-booking prevention
- Check teacher availability before booking
- Check student's existing bookings
- Show conflicts in calendar UI

## Phase 8: Security & Testing (Priority 4)

### 8.1 Authentication & Authorization

**Files**: All dashboard files

- Verify session validation on all pages
- Ensure role-based access control
- Validate user permissions for actions
- Secure API endpoints with CSRF protection

### 8.2 Classroom Security

**Files**: `classroom.php`, `api/webrtc.php`

- Validate session access before joining
- Short-lived join tokens (if implemented)
- Rate limiting on signaling endpoints
- Prevent unauthorized room access

### 8.3 Payment Security

**Files**: `create_checkout_session.php`, `stripe-webhook.php`

- Verify webhook signatures
- Idempotent webhook processing
- Secure payment data handling (no card storage)
- PCI compliance verification

### 8.4 Testing Checklist

- Unit tests for wallet transactions
- Integration tests for booking flow
- E2E tests: student books → wallet debits → teacher joins → student joins
- Load testing: concurrent classroom joins
- Cross-browser testing (Chrome, Firefox, Safari, Edge)
- Mobile responsiveness testing

## Phase 9: Documentation & Deployment (Priority 4)

### 9.1 Code Documentation

- Document wallet transaction flow
- Document classroom WebRTC setup
- Document admin audit log system
- API endpoint documentation

### 9.2 Deployment Checklist

- Build React bundle before deployment
- Run database migrations
- Verify environment variables
- Test in staging environment
- Monitor error logs post-deployment

## Implementation Order

1. **Week 1**: Phase 1 (Fix Classroom) + Phase 2 (Test Classroom)
2. **Week 2**: Phase 3 (Wallet Hardening) + Phase 4.1-4.3 (Admin Core Features)
3. **Week 3**: Phase 4.4-4.5 (Admin Audit & Settings) + Phase 5 (Teacher Dashboard)
4. **Week 4**: Phase 6 (Student Dashboard) + Phase 7 (Calendar Improvements)
5. **Week 5**: Phase 8 (Security & Testing) + Phase 9 (Documentation)

## Key Files to Modify

### Critical Fixes

- `classroom.php` - Fix React bundle loading
- `db.php` - Add missing tables (`signaling_queue`, `video_sessions`, `admin_audit_log`)
- `src/classroom.tsx` - Verify and fix React app initialization
- `package.json` - Ensure build script works
- `api/webrtc.php` - Verify signaling endpoint works

### New Features

- `admin-dashboard.php` - Add wallet reconciliation, audit logs, settings
- `teacher-dashboard.php` - Add test classroom button, enhanced calendar
- `app/Services/WalletService.php` - Enhance transaction logging
- New: `admin-audit-log.php` - Audit log viewer

### Enhancements

- `student-dashboard.php` - Improve wallet UI, calendar integration
- `schedule.php` - Enhance booking flow
- Calendar components - Timezone handling, conflict detection

## Success Criteria

- Classroom loads and connects teacher ↔ student reliably
- Teacher can access test classroom from dashboard
- Wallet transactions are fully auditable
- Admin can reconcile all financial transactions
- Booking flow works end-to-end: search → book → pay → join
- All dashboards display correct data
- No high-severity security issues
- Mobile-responsive on all core flows

## Notes

- Keep existing PHP/Node/JS architecture
- Maintain statenacademy.com visual style
- No teacher payout flows (offline payments only)
- Database-based signaling (no WebSocket migration)
- Custom package builder postponed to Phase 2+