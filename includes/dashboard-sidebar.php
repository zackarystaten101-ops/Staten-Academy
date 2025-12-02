<?php
/**
 * Dashboard Sidebar Component
 * Role-aware sidebar navigation for all dashboard pages
 * 
 * Required variables:
 * - $user_role (string) - 'student', 'teacher', or 'admin'
 * - $active_tab (string) - Current active tab ID
 */

$user_role = $user_role ?? $_SESSION['user_role'] ?? 'student';
$active_tab = $active_tab ?? 'overview';
$user_id = $_SESSION['user_id'] ?? 0;

// Get badge counts if available
$unread_messages = isset($conn) ? getUnreadMessagesCount($conn, $user_id) : 0;
$pending_assignments = 0;
if ($user_role === 'teacher' && isset($conn)) {
    $pending_assignments = getPendingAssignmentsCount($conn, $user_id);
}
?>

<div class="sidebar">
    <div class="sidebar-header">
        <?php if ($user_role === 'student'): ?>
            <h3><i class="fas fa-graduation-cap"></i> Student Portal</h3>
        <?php elseif ($user_role === 'teacher'): ?>
            <h3><i class="fas fa-chalkboard-teacher"></i> Teacher Portal</h3>
        <?php elseif ($user_role === 'admin'): ?>
            <h3><i class="fas fa-shield-alt"></i> Admin Panel</h3>
        <?php endif; ?>
    </div>
    
    <nav class="sidebar-menu">
        <?php if ($user_role === 'student'): ?>
            <!-- Student Navigation -->
            <a href="#" onclick="switchTab('overview')" class="<?php echo $active_tab === 'overview' ? 'active' : ''; ?>">
                <i class="fas fa-home"></i> Overview
            </a>
            <a href="#" onclick="switchTab('profile')" class="<?php echo $active_tab === 'profile' ? 'active' : ''; ?>">
                <i class="fas fa-user"></i> My Profile
            </a>
            <a href="#" onclick="switchTab('teachers')" class="<?php echo $active_tab === 'teachers' ? 'active' : ''; ?>">
                <i class="fas fa-heart"></i> My Teachers
            </a>
            <a href="#" onclick="switchTab('bookings')" class="<?php echo $active_tab === 'bookings' ? 'active' : ''; ?>">
                <i class="fas fa-calendar-alt"></i> My Lessons
            </a>
            <a href="#" onclick="switchTab('goals')" class="<?php echo $active_tab === 'goals' ? 'active' : ''; ?>">
                <i class="fas fa-bullseye"></i> Learning Goals
            </a>
            <a href="#" onclick="switchTab('homework')" class="<?php echo $active_tab === 'homework' ? 'active' : ''; ?>">
                <i class="fas fa-tasks"></i> Homework
                <?php if ($pending_assignments > 0): ?>
                    <span class="sidebar-badge"><?php echo $pending_assignments; ?></span>
                <?php endif; ?>
            </a>
            <a href="#" onclick="switchTab('reviews')" class="<?php echo $active_tab === 'reviews' ? 'active' : ''; ?>">
                <i class="fas fa-star"></i> My Reviews
            </a>
            <hr>
            <a href="schedule.php"><i class="fas fa-calendar-plus"></i> Book Lesson</a>
            <a href="message_threads.php">
                <i class="fas fa-comments"></i> Messages
                <?php if ($unread_messages > 0): ?>
                    <span class="sidebar-badge"><?php echo $unread_messages; ?></span>
                <?php endif; ?>
            </a>
            <a href="classroom.php"><i class="fas fa-book-open"></i> Classroom</a>
            <hr>
            <a href="#" onclick="switchTab('security')" class="<?php echo $active_tab === 'security' ? 'active' : ''; ?>">
                <i class="fas fa-lock"></i> Security
            </a>
            <a href="support_contact.php" class="support-link"><i class="fas fa-headset"></i> Support</a>
            
        <?php elseif ($user_role === 'teacher'): ?>
            <!-- Teacher Navigation -->
            <a href="#" onclick="switchTab('overview')" class="<?php echo $active_tab === 'overview' ? 'active' : ''; ?>">
                <i class="fas fa-home"></i> Overview
            </a>
            <a href="#" onclick="switchTab('earnings')" class="<?php echo $active_tab === 'earnings' ? 'active' : ''; ?>">
                <i class="fas fa-dollar-sign"></i> Earnings
            </a>
            <a href="#" onclick="switchTab('students')" class="<?php echo $active_tab === 'students' ? 'active' : ''; ?>">
                <i class="fas fa-users"></i> My Students
            </a>
            <a href="#" onclick="switchTab('assignments')" class="<?php echo $active_tab === 'assignments' ? 'active' : ''; ?>">
                <i class="fas fa-tasks"></i> Assignments
                <?php if ($pending_assignments > 0): ?>
                    <span class="sidebar-badge"><?php echo $pending_assignments; ?></span>
                <?php endif; ?>
            </a>
            <a href="#" onclick="switchTab('reviews')" class="<?php echo $active_tab === 'reviews' ? 'active' : ''; ?>">
                <i class="fas fa-star"></i> Reviews
            </a>
            <a href="#" onclick="switchTab('resources')" class="<?php echo $active_tab === 'resources' ? 'active' : ''; ?>">
                <i class="fas fa-folder-open"></i> Resources
            </a>
            <hr>
            <a href="#" onclick="switchTab('profile')" class="<?php echo $active_tab === 'profile' ? 'active' : ''; ?>">
                <i class="fas fa-user-edit"></i> Edit Profile
            </a>
            <a href="profile.php?id=<?php echo $user_id; ?>"><i class="fas fa-eye"></i> View Profile</a>
            <a href="teacher-calendar-setup.php"><i class="fas fa-calendar-alt"></i> Calendar Setup</a>
            <a href="schedule.php"><i class="fas fa-calendar"></i> View Bookings</a>
            <hr>
            <a href="message_threads.php">
                <i class="fas fa-comments"></i> Messages
                <?php if ($unread_messages > 0): ?>
                    <span class="sidebar-badge"><?php echo $unread_messages; ?></span>
                <?php endif; ?>
            </a>
            <a href="classroom.php"><i class="fas fa-book-open"></i> Classroom</a>
            <a href="#" onclick="switchTab('security')" class="<?php echo $active_tab === 'security' ? 'active' : ''; ?>">
                <i class="fas fa-lock"></i> Security
            </a>
            <a href="support_contact.php" class="support-link"><i class="fas fa-headset"></i> Support</a>
            
        <?php elseif ($user_role === 'admin'): ?>
            <!-- Admin Navigation -->
            <a href="#" onclick="switchTab('dashboard')" class="<?php echo $active_tab === 'dashboard' ? 'active' : ''; ?>">
                <i class="fas fa-tachometer-alt"></i> Dashboard
            </a>
            <a href="#" onclick="switchTab('analytics')" class="<?php echo $active_tab === 'analytics' ? 'active' : ''; ?>">
                <i class="fas fa-chart-line"></i> Analytics
            </a>
            <a href="#" onclick="switchTab('applications')" class="<?php echo $active_tab === 'applications' ? 'active' : ''; ?>">
                <i class="fas fa-user-plus"></i> Applications
            </a>
            <a href="#" onclick="switchTab('approvals')" class="<?php echo $active_tab === 'approvals' ? 'active' : ''; ?>">
                <i class="fas fa-check-circle"></i> Profile Updates
            </a>
            <a href="#" onclick="switchTab('teachers')" class="<?php echo $active_tab === 'teachers' ? 'active' : ''; ?>">
                <i class="fas fa-chalkboard-teacher"></i> Teachers
            </a>
            <a href="#" onclick="switchTab('students')" class="<?php echo $active_tab === 'students' ? 'active' : ''; ?>">
                <i class="fas fa-user-graduate"></i> Students
            </a>
            <a href="#" onclick="switchTab('support')" class="<?php echo $active_tab === 'support' ? 'active' : ''; ?>">
                <i class="fas fa-headset"></i> Support
                <?php 
                $open_support = isset($conn) ? $conn->query("SELECT COUNT(*) as c FROM support_messages WHERE status='open'")->fetch_assoc()['c'] : 0;
                if ($open_support > 0): ?>
                    <span class="sidebar-badge alert"><?php echo $open_support; ?></span>
                <?php endif; ?>
            </a>
            <a href="#" onclick="switchTab('reports')" class="<?php echo $active_tab === 'reports' ? 'active' : ''; ?>">
                <i class="fas fa-file-alt"></i> Reports
            </a>
            <hr>
            <a href="#" onclick="switchTab('my-profile')" class="<?php echo $active_tab === 'my-profile' ? 'active' : ''; ?>">
                <i class="fas fa-user-edit"></i> My Profile
            </a>
            <a href="#" onclick="switchTab('classroom')" class="<?php echo $active_tab === 'classroom' ? 'active' : ''; ?>">
                <i class="fas fa-book-open"></i> Classroom
            </a>
            <a href="admin-schedule-view.php"><i class="fas fa-calendar-check"></i> Schedules</a>
            <hr>
            <a href="#" onclick="switchTab('my-security')" class="<?php echo $active_tab === 'my-security' ? 'active' : ''; ?>">
                <i class="fas fa-lock"></i> Security
            </a>
        <?php endif; ?>
        
        <hr>
        <a href="index.php"><i class="fas fa-home"></i> Home Page</a>
        <a href="logout.php" class="logout-link"><i class="fas fa-sign-out-alt"></i> Logout</a>
    </nav>
</div>

<!-- Mobile Sidebar Overlay -->
<div class="sidebar-overlay" onclick="toggleMobileSidebar()"></div>

