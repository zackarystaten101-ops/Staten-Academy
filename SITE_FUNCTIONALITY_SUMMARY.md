# Staten Academy - Complete Site Functionality Summary

## Platform Overview
Staten Academy is an online English learning platform connecting students with teachers for personalized language instruction. The platform supports three learning tracks (Kids, Adults, Coding), flexible scheduling, subscription-based plans, and comprehensive admin management.

## User Roles & Access Levels

### 1. Visitors (Unauthenticated)
- Browse homepage with three learning tracks (Kids, Adults, Coding)
- View track-specific pages showing approved teachers
- View teacher profiles (public information only)
- Access "About Us" and "How We Work" pages
- Register new account or login

### 2. Students (Authenticated)
- **Dashboard Features:**
  - View upcoming lessons (calendar view)
  - Book new lessons (1-on-1 or group classes)
  - View lesson history and past recordings
  - Track learning progress and goals
  - Manage wallet balance
  - View assigned materials and homework
  - Review and rate teachers
  - Optional: Submit learning preferences/needs assessment
  - Update profile (bio, profile picture, age, preferences) - NO admin approval required

- **Booking System:**
  - Browse approved teachers by category
  - Filter teachers by rating, price, availability
  - View teacher availability in student's timezone
  - Book lessons directly from teacher profile
  - Cancel/reschedule lessons with notice
  - Book trial lessons (one-time payment)

- **Payment System:**
  - Purchase monthly subscription plans (Starter, Core, Intensive, Elite)
  - Plans include mix of 1-on-1 and group classes per month
  - Wallet system for managing credits
  - Stripe integration for payments
  - View billing history

### 3. Teachers (Authenticated)
- **Dashboard Features:**
  - View upcoming lessons and calendar
  - Manage availability slots (timezone-aware)
  - View student list and their progress
  - Access classroom/virtual room for lessons (Google Meet integration)
  - Upload and manage teaching materials
  - Create group classes
  - View earnings and payment history
  - Manage profile (requires admin approval for: name, bio, profile pic, about text, video URL)
  - Update other fields directly (specialty, hourly rate, calendly link, etc.)

- **Profile Management:**
  - Profile changes submitted to `pending_updates` table
  - Admin must approve/reject profile updates
  - Direct updates allowed for non-visible fields

- **Availability Management:**
  - Set recurring weekly availability slots
  - Set specific date availability
  - Google Calendar integration
  - Timezone conversion for students

- **Teaching Tools:**
  - Test classroom/virtual room
  - Upload materials (documents, videos, links)
  - View student notes and progress
  - Group class management

### 4. Admins (Full System Access)
- **User Management:**
  - View all users (teachers, students, admins, visitors)
  - Approve/reject teacher applications
  - Assign/change user roles
  - Suspend/activate user accounts
  - Manage teacher categories (Kids, Adults, Coding) - CRITICAL FUNCTION
    - Click Categories button in Teachers table
    - Check/uncheck categories to approve teachers for teaching sections
    - Teachers with approved categories appear on respective category pages
  - Adjust student wallet balances

- **Content Management:**
  - Approve/reject teacher profile updates
  - Manage subscription plans and pricing
  - Set teacher salary/commission rates (default and per-teacher)
  - View and manage all lessons/bookings
  - Manage classroom materials
  - View support messages and respond

- **Analytics & Reports:**
  - View dashboard statistics (students, teachers, bookings, revenue)
  - Export wallet transactions (CSV)
  - View revenue analytics
  - Track user engagement metrics
  - View pending requests (applications, profile updates)

- **Operational Features:**
  - Request teachers to open specific time slots
  - Request teachers to create group classes
  - View lesson conflicts
  - Audit log for all admin actions

## Learning Tracks & Categories

### 1. Kids Classes (young_learners)
- Target: Ages 3-11
- Page: `kids-plans.php`
- Shows: Approved teachers with `young_learners` category
- Plans: 4 tiers (Starter $140, Core $240, Intensive $420, Elite $520/month)
- Features: Age-appropriate materials, parent progress reports

### 2. Adult Classes (adults)
- Target: Ages 12+
- Page: `adults-plans.php`
- Shows: Approved teachers with `adults` category
- Plans: 4 tiers (Starter $180, Core $310, Intensive $540, Elite $670/month)
- Features: Career-focused, business English, flexible scheduling

### 3. English for Coding (coding)
- Target: Developers and tech professionals
- Page: `coding-plans.php`
- Shows: Approved teachers with `coding` category
- Plans: 4 tiers (Starter $215, Core $370, Intensive $640, Elite $790/month)
- Features: Technical vocabulary, interview prep, documentation skills

## Subscription Plans System

### Plan Structure (All Tracks)
- **Starter**: Basic plan with 4×1-on-1 + 4×group classes/month
- **Core**: 8×1-on-1 + 6×group classes/month
- **Intensive**: 13×1-on-1 + 12×group classes/month
- **Elite**: 16×1-on-1 + 14×group classes/month (marked as "Best Value")

### Plan Features
- Monthly recurring subscriptions via Stripe
- All sessions are 50 minutes
- Track-specific features per plan
- Stripe Product IDs stored in database
- Price IDs fetched automatically from Stripe API
- Display order and active/inactive status

## Booking & Lesson System

### Lesson Types
1. **1-on-1 Classes**: Individual instruction
2. **Group Classes**: Multiple students with one teacher
3. **Trial Lessons**: One-time booking for new students

### Booking Flow
1. Student selects learning track
2. Browses approved teachers for that track
3. Views teacher profile (bio, ratings, availability, video intro)
4. Views teacher availability in student's timezone
5. Selects available time slot
6. Books lesson (wallet balance checked)
7. Google Calendar event created (if teacher has calendar connected)
8. Lesson link sent via email/notification

### Lesson Management
- Lessons stored with: teacher_id, student_id, date, time, duration, status
- Status options: scheduled, completed, cancelled, no_show
- Timezone conversion handled automatically
- Conflict detection prevents double-booking
- Recording links stored after completion

## Payment & Wallet System

### Payment Methods
- Stripe integration for subscriptions
- Stripe integration for one-time payments (trials)
- Wallet system for managing lesson credits

### Wallet Functions
- Students add funds to wallet
- Wallet balance checked before booking
- Automatic deduction on lesson booking
- Admin can manually adjust balances
- Transaction history tracked
- CSV export available for admins

## Communication System

### Messaging
- Direct messaging between users
- Thread-based conversations
- Real-time message updates
- Unread message counts
- Support ticket system (students → admins)

### Notifications
- In-app notification system
- Types: lesson reminders, profile approvals, messages, reviews
- Notification dropdown in header
- Click notifications to navigate to relevant pages

## Teacher Approval & Category System

### Teacher Application Process
1. User applies to be teacher (fills out application form)
2. Application stored with `application_status = 'pending'`
3. Admin views pending applications in dashboard
4. Admin approves/rejects application
5. If approved: role changed to 'teacher', status = 'approved'

### Category Approval (Critical Admin Function)
1. Admin navigates to Users → Teachers tab
2. Clicks Categories button for a teacher
3. Modal opens showing three checkboxes:
   - Kids Classes (category: young_learners)
   - Adults Classes (category: adults)
   - Coding Classes (category: coding)
4. Admin checks desired categories
5. Saves changes
6. Teacher's categories stored in `teacher_categories` table with `is_active = TRUE`
7. Teacher immediately appears on corresponding category pages:
   - Kids Classes page for young_learners
   - Adults Classes page for adults
   - Coding Classes page for coding

### Profile Update Approval
- Teachers can update: name, bio, profile_pic, about_text, video_url
- Changes saved to `pending_updates` table
- Admin reviews and approves/rejects
- If approved: changes applied to user profile
- If rejected: changes discarded, notification sent

## Technical Architecture

### Database Tables (Key Tables)
- `users`: User accounts (role, status, profile info)
- `teacher_categories`: Category approvals (teacher_id, category, is_active)
- `pending_updates`: Teacher profile change requests
- `subscription_plans`: Plan definitions (pricing, features, Stripe IDs)
- `lessons`: Scheduled lessons/bookings
- `wallet_transactions`: Payment and credit transactions
- `reviews`: Teacher ratings and reviews
- `teacher_availability_slots`: Teacher time slots
- `admin_audit_log`: Admin action tracking

### Integration Services
- **Stripe Service**: Fetches price IDs from product IDs
- **Teacher Service**: Handles teacher queries, filtering, availability
- **Wallet Service**: Manages student credits and transactions
- **Timezone Service**: Converts times between user timezones
- **Google Calendar API**: Creates calendar events for lessons

### Key Files
- `admin-dashboard.php`: Admin control panel
- `student-dashboard.php`: Student interface
- `teacher-dashboard.php`: Teacher interface
- `kids-plans.php`, `adults-plans.php`, `coding-plans.php`: Category pages showing approved teachers
- `teacher-profile.php`: Individual teacher profile (public view)
- `admin-actions.php`: Backend handlers for admin actions
- `create_checkout_session.php`: Stripe checkout processing

## Security & Permissions

### Role-Based Access
- Visitors: Public pages only
- Students: Can book lessons, manage profile, view own data
- Teachers: Can manage availability, view students, upload materials
- Admins: Full system access, user management, content approval

### Data Protection
- Password hashing (bcrypt)
- SQL injection protection (prepared statements)
- XSS protection (htmlspecialchars)
- Session-based authentication
- CSRF protection on forms

## Content Management

### Static Pages
- `index.php`: Homepage with track selection
- `about.php`: About us page
- `how-we-work.php`: Process explanation
- Track pages: Display approved teachers and plans

### Dynamic Content
- Teacher profiles (bios, videos, ratings)
- Student learning needs/preferences
- Classroom materials (uploaded by teachers/admins)
- Support messages and responses

## Current Workflow

### Student Journey
1. Visit homepage → Select track
2. View approved teachers for that track
3. Click teacher profile → View availability
4. Subscribe to plan or book trial
5. Book lessons from teacher's availability
6. Attend lessons via Google Meet
7. Review and rate teacher

### Teacher Journey
1. Apply to be teacher (submit application)
2. Admin approves application
3. Admin approves teacher for categories (Kids/Adults/Coding)
4. Teacher appears on category pages
5. Teacher sets availability slots
6. Students book lessons with teacher
7. Teacher receives bookings, teaches lessons

### Admin Workflow
1. Approve teacher applications
2. Assign categories to teachers (enable them to appear on category pages)
3. Approve teacher profile updates
4. Manage subscription plans and pricing
5. Monitor bookings, revenue, user activity
6. Handle support requests

## Important Notes

- **Category System**: Teachers must be explicitly approved for categories by admin before they appear on category pages. This is done via the Categories button in the Teachers table.
- **Profile Updates**: Teacher profile changes (name, bio, picture, about, video) require admin approval. Other fields (specialty, rate, calendly) update immediately.
- **Student Profile Updates**: Students can update their profiles (including pictures) without admin approval.
- **Google Meet Integration**: All lessons conducted via Google Meet (not Zoom).
- **Teacher Selection**: Students browse and select teachers themselves (no automated matching system).

