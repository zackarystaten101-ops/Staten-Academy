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

// Detect if we're on a dashboard page or another page
$current_page = basename($_SERVER['PHP_SELF']);
$dashboard_pages = ['teacher-dashboard.php', 'student-dashboard.php', 'admin-dashboard.php', 'visitor-dashboard.php'];
$is_dashboard_page = in_array($current_page, $dashboard_pages);

// Load dashboard functions if needed
if (!function_exists('getUnreadMessagesCount')) {
    require_once __DIR__ . '/../../../db.php';
    require_once __DIR__ . '/dashboard-functions.php';
}

// Get badge counts if available
$unread_messages = 0;
$pending_assignments = 0;
if (isset($conn) && function_exists('getUnreadMessagesCount')) {
    $unread_messages = getUnreadMessagesCount($conn, $user_id);
}
if ($user_role === 'teacher' && isset($conn) && function_exists('getPendingAssignmentsCount')) {
    $pending_assignments = getPendingAssignmentsCount($conn, $user_id);
}

// Get admin stats for pending requests badge
$admin_stats = [];
if (isset($admin_stats_for_sidebar)) {
    $admin_stats = $admin_stats_for_sidebar;
} elseif (isset($admin_stats)) {
    $admin_stats = $admin_stats;
} elseif (isset($conn) && $user_role === 'admin' && function_exists('getAdminStats')) {
    $admin_stats = getAdminStats($conn);
}
?>

<div class="sidebar">
    <div class="sidebar-header">
        <?php if ($user_role === 'visitor'): ?>
            <?php if ($is_dashboard_page): ?>
            <a href="#" onclick="if(typeof switchTab === 'function') { switchTab('overview'); } else { window.location.href='visitor-dashboard.php#overview'; } return false;" style="text-decoration: none; color: inherit; display: block; padding: 10px; border-radius: 5px; transition: background 0.2s; margin-bottom: 10px;" onmouseover="this.style.background='rgba(255,255,255,0.1)'" onmouseout="this.style.background='transparent'">
            <?php else: ?>
            <a href="visitor-dashboard.php#overview" style="text-decoration: none; color: inherit; display: block; padding: 10px; border-radius: 5px; transition: background 0.2s; margin-bottom: 10px;" onmouseover="this.style.background='rgba(255,255,255,0.1)'" onmouseout="this.style.background='transparent'">
            <?php endif; ?>
                <h3 style="margin: 0; cursor: pointer; transition: opacity 0.2s;" onmouseover="this.style.opacity='0.8'" onmouseout="this.style.opacity='1'">
                    <i class="fas fa-user-circle"></i> Visitor Portal
                </h3>
            </a>
        <?php elseif ($user_role === 'student' || $user_role === 'new_student'): ?>
            <?php if ($is_dashboard_page): ?>
            <a href="#" onclick="if(typeof switchTab === 'function') { switchTab('overview'); } else { window.location.href='student-dashboard.php#overview'; } return false;" style="text-decoration: none; color: inherit; display: block; padding: 10px; border-radius: 5px; transition: background 0.2s; margin-bottom: 10px;" onmouseover="this.style.background='rgba(255,255,255,0.1)'" onmouseout="this.style.background='transparent'">
            <?php else: ?>
            <a href="student-dashboard.php#overview" style="text-decoration: none; color: inherit; display: block; padding: 10px; border-radius: 5px; transition: background 0.2s; margin-bottom: 10px;" onmouseover="this.style.background='rgba(255,255,255,0.1)'" onmouseout="this.style.background='transparent'">
            <?php endif; ?>
                <h3 style="margin: 0; cursor: pointer; transition: opacity 0.2s;" onmouseover="this.style.opacity='0.8'" onmouseout="this.style.opacity='1'">
                    <i class="fas fa-graduation-cap"></i> <?php echo $user_role === 'new_student' ? 'New Student' : 'Student'; ?> Portal
                </h3>
            </a>
        <?php elseif ($user_role === 'teacher'): ?>
            <?php if ($is_dashboard_page): ?>
            <a href="#" onclick="if(typeof switchTab === 'function') { switchTab('overview'); } else { window.location.href='teacher-dashboard.php#overview'; } return false;" style="text-decoration: none; color: inherit; display: block; padding: 10px; border-radius: 5px; transition: background 0.2s; margin-bottom: 10px;" onmouseover="this.style.background='rgba(255,255,255,0.1)'" onmouseout="this.style.background='transparent'">
            <?php else: ?>
            <a href="teacher-dashboard.php#overview" style="text-decoration: none; color: inherit; display: block; padding: 10px; border-radius: 5px; transition: background 0.2s; margin-bottom: 10px;" onmouseover="this.style.background='rgba(255,255,255,0.1)'" onmouseout="this.style.background='transparent'">
            <?php endif; ?>
                <h3 style="margin: 0; cursor: pointer; transition: opacity 0.2s;" onmouseover="this.style.opacity='0.8'" onmouseout="this.style.opacity='1'">
                    <i class="fas fa-chalkboard-teacher"></i> Teacher Portal
                </h3>
            </a>
        <?php elseif ($user_role === 'admin'): ?>
            <?php if ($is_dashboard_page): ?>
            <a href="#" onclick="if(typeof switchTab === 'function') { switchTab('dashboard'); } else { window.location.href='admin-dashboard.php#dashboard'; } return false;" style="text-decoration: none; color: inherit; display: block; padding: 10px; border-radius: 5px; transition: background 0.2s; margin-bottom: 10px;" onmouseover="this.style.background='rgba(255,255,255,0.1)'" onmouseout="this.style.background='transparent'">
            <?php else: ?>
            <a href="admin-dashboard.php#dashboard" style="text-decoration: none; color: inherit; display: block; padding: 10px; border-radius: 5px; transition: background 0.2s; margin-bottom: 10px;" onmouseover="this.style.background='rgba(255,255,255,0.1)'" onmouseout="this.style.background='transparent'">
            <?php endif; ?>
                <h3 style="margin: 0; cursor: pointer; transition: opacity 0.2s;" onmouseover="this.style.opacity='0.8'" onmouseout="this.style.opacity='1'">
                    <i class="fas fa-shield-alt"></i> Admin Panel
                </h3>
            </a>
        <?php endif; ?>
    </div>
    
    <nav class="sidebar-menu">
        <?php if ($user_role === 'visitor'): ?>
            <!-- Visitor Navigation -->
            <?php if ($is_dashboard_page): ?>
            <a href="#" onclick="if(typeof switchTab === 'function') { switchTab('overview'); } return false;" class="<?php echo $active_tab === 'overview' ? 'active' : ''; ?>">
                <i class="fas fa-home"></i> Overview
            </a>
            <a href="#" onclick="if(typeof switchTab === 'function') { switchTab('courses'); } return false;" class="<?php echo $active_tab === 'courses' ? 'active' : ''; ?>">
                <i class="fas fa-book-open"></i> Course Library
            </a>
            <a href="#" onclick="if(typeof switchTab === 'function') { switchTab('plans'); } return false;" class="<?php echo $active_tab === 'plans' ? 'active' : ''; ?>">
                <i class="fas fa-credit-card"></i> Plans & Pricing
            </a>
            <hr>
            <a href="#" onclick="if(typeof switchTab === 'function') { switchTab('profile'); } return false;" class="<?php echo $active_tab === 'profile' ? 'active' : ''; ?>">
                <i class="fas fa-user"></i> My Profile
            </a>
            <?php else: ?>
            <a href="visitor-dashboard.php#overview" class="<?php echo $active_tab === 'overview' ? 'active' : ''; ?>">
                <i class="fas fa-home"></i> Overview
            </a>
            <a href="visitor-dashboard.php#courses" class="<?php echo $active_tab === 'courses' ? 'active' : ''; ?>">
                <i class="fas fa-book-open"></i> Course Library
            </a>
            <a href="visitor-dashboard.php#plans" class="<?php echo $active_tab === 'plans' ? 'active' : ''; ?>">
                <i class="fas fa-credit-card"></i> Plans & Pricing
            </a>
            <hr>
            <a href="visitor-dashboard.php#profile" class="<?php echo $active_tab === 'profile' ? 'active' : ''; ?>">
                <i class="fas fa-user"></i> My Profile
            </a>
            <?php endif; ?>
            <a href="payment.php" class="upgrade-link" style="background: #004080; color: white; font-weight: bold;">
                <i class="fas fa-arrow-up"></i> Upgrade to Student
            </a>
            <hr>
            <a href="support_contact.php" class="support-link"><i class="fas fa-headset"></i> Support</a>
            
        <?php elseif ($user_role === 'student' || $user_role === 'new_student'): ?>
            <!-- Student Navigation -->
            <?php if ($is_dashboard_page): ?>
            <a href="#" onclick="if(typeof switchTab === 'function') { switchTab('overview'); } return false;" class="<?php echo $active_tab === 'overview' ? 'active' : ''; ?>">
                <i class="fas fa-home"></i> Overview
            </a>
            <a href="#" onclick="if(typeof switchTab === 'function') { switchTab('profile'); } return false;" class="<?php echo $active_tab === 'profile' ? 'active' : ''; ?>">
                <i class="fas fa-user"></i> My Profile
            </a>
            <a href="#" onclick="if(typeof switchTab === 'function') { switchTab('teachers'); } return false;" class="<?php echo $active_tab === 'teachers' ? 'active' : ''; ?>">
                <i class="fas fa-heart"></i> My Teachers
            </a>
            <a href="#" onclick="if(typeof switchTab === 'function') { switchTab('bookings'); } return false;" class="<?php echo $active_tab === 'bookings' ? 'active' : ''; ?>">
                <i class="fas fa-calendar-alt"></i> My Lessons
            </a>
            <a href="#" onclick="if(typeof switchTab === 'function') { switchTab('goals'); } return false;" class="<?php echo $active_tab === 'goals' ? 'active' : ''; ?>">
                <i class="fas fa-bullseye"></i> Learning Goals
            </a>
            <a href="#" onclick="if(typeof switchTab === 'function') { switchTab('homework'); } return false;" class="<?php echo $active_tab === 'homework' ? 'active' : ''; ?>">
                <i class="fas fa-tasks"></i> Homework
                <?php if ($pending_assignments > 0): ?>
                    <span class="sidebar-badge"><?php echo $pending_assignments; ?></span>
                <?php endif; ?>
            </a>
            <a href="#" onclick="if(typeof switchTab === 'function') { switchTab('reviews'); } return false;" class="<?php echo $active_tab === 'reviews' ? 'active' : ''; ?>">
                <i class="fas fa-star"></i> My Reviews
            </a>
            <hr>
            <a href="#" onclick="if(typeof switchTab === 'function') { switchTab('security'); } return false;" class="<?php echo $active_tab === 'security' ? 'active' : ''; ?>">
                <i class="fas fa-lock"></i> Security
            </a>
            <?php else: ?>
            <a href="student-dashboard.php#overview" class="<?php echo $active_tab === 'overview' ? 'active' : ''; ?>">
                <i class="fas fa-home"></i> Overview
            </a>
            <a href="student-dashboard.php#profile" class="<?php echo $active_tab === 'profile' ? 'active' : ''; ?>">
                <i class="fas fa-user"></i> My Profile
            </a>
            <?php
            // Show learning needs link if not completed and has plan
            if (isset($conn) && isset($user_id)) {
                $plan_check = $conn->prepare("SELECT plan_id FROM users WHERE id = ?");
                if ($plan_check) {
                    $plan_check->bind_param("i", $user_id);
                    $plan_check->execute();
                    $plan_result = $plan_check->get_result();
                    $plan_user = $plan_result->fetch_assoc();
                    $plan_check->close();
                    
                    if ($plan_user && $plan_user['plan_id']) {
                        $needs_check = $conn->prepare("SELECT id FROM student_learning_needs WHERE student_id = ? AND completed = 1");
                        $needs_check->bind_param("i", $user_id);
                        $needs_check->execute();
                        $needs_completed = $needs_check->get_result()->num_rows > 0;
                        $needs_check->close();
                        
                        if (!$needs_completed) {
                            echo '<a href="student-dashboard.php#learning-needs" class="' . ($active_tab === 'learning-needs' ? 'active' : '') . '" style="background: rgba(255, 193, 7, 0.15); color: #856404; font-weight: bold;">';
                            echo '<i class="fas fa-user-graduate"></i> Add Learning Needs';
                            echo '<span class="sidebar-badge" style="background: #ffc107; color: #000;">!</span>';
                            echo '</a>';
                        }
                    }
                }
            }
            ?>
            <a href="student-dashboard.php#teachers" class="<?php echo $active_tab === 'teachers' ? 'active' : ''; ?>">
                <i class="fas fa-heart"></i> My Teachers
            </a>
            <a href="student-dashboard.php#bookings" class="<?php echo $active_tab === 'bookings' ? 'active' : ''; ?>">
                <i class="fas fa-calendar-alt"></i> My Lessons
            </a>
            <a href="student-dashboard.php#goals" class="<?php echo $active_tab === 'goals' ? 'active' : ''; ?>">
                <i class="fas fa-bullseye"></i> Learning Goals
            </a>
            <a href="student-dashboard.php#homework" class="<?php echo $active_tab === 'homework' ? 'active' : ''; ?>">
                <i class="fas fa-tasks"></i> Homework
                <?php if ($pending_assignments > 0): ?>
                    <span class="sidebar-badge"><?php echo $pending_assignments; ?></span>
                <?php endif; ?>
            </a>
            <a href="student-dashboard.php#reviews" class="<?php echo $active_tab === 'reviews' ? 'active' : ''; ?>">
                <i class="fas fa-star"></i> My Reviews
            </a>
            <hr>
            <a href="student-dashboard.php#security" class="<?php echo $active_tab === 'security' ? 'active' : ''; ?>">
                <i class="fas fa-lock"></i> Security
            </a>
            <?php endif; ?>
            <a href="schedule.php"><i class="fas fa-calendar-plus"></i> Book Lesson</a>
            <a href="message_threads.php" class="<?php echo (basename($_SERVER['PHP_SELF']) === 'message_threads.php' || $active_tab === 'messages') ? 'active' : ''; ?>">
                <i class="fas fa-comments"></i> Messages
                <?php if ($unread_messages > 0): ?>
                    <span class="sidebar-badge"><?php echo $unread_messages; ?></span>
                <?php endif; ?>
            </a>
            <a href="classroom.php" class="<?php echo (basename($_SERVER['PHP_SELF']) === 'classroom.php' || $active_tab === 'classroom') ? 'active' : ''; ?>"><i class="fas fa-book-open"></i> Classroom</a>
            <hr>
            <?php
            // Check application status for students/new_students
            $application_status = 'none';
            if (isset($conn) && isset($user_id) && in_array($user_role, ['student', 'new_student'])) {
                $app_check = $conn->prepare("SELECT application_status FROM users WHERE id = ?");
                if ($app_check) {
                    $app_check->bind_param("i", $user_id);
                    $app_check->execute();
                    $app_result = $app_check->get_result();
                    if ($app_row = $app_result->fetch_assoc()) {
                        $application_status = $app_row['application_status'];
                    }
                    $app_check->close();
                }
            }
            ?>
            <a href="apply-teacher.php" class="<?php echo (basename($_SERVER['PHP_SELF']) === 'apply-teacher.php') ? 'active' : ''; ?>" style="<?php echo $application_status === 'pending' ? 'background: rgba(255, 193, 7, 0.15); color: #856404;' : ($application_status === 'approved' ? 'background: rgba(40, 167, 69, 0.15); color: #155724;' : ''); ?>">
                <i class="fas fa-chalkboard-teacher"></i> Apply to Teach
                <?php if ($application_status === 'pending'): ?>
                    <span class="sidebar-badge" style="background: #ffc107; color: #000;">Pending</span>
                <?php elseif ($application_status === 'approved'): ?>
                    <span class="sidebar-badge" style="background: #28a745;">Approved</span>
                <?php endif; ?>
            </a>
            <a href="support_contact.php" class="support-link"><i class="fas fa-headset"></i> Support</a>
            
        <?php elseif ($user_role === 'teacher'): ?>
            <!-- Teacher Navigation -->
            <?php if ($is_dashboard_page): ?>
            <a href="#" onclick="if(typeof switchTab === 'function') { switchTab('overview'); } return false;" class="<?php echo $active_tab === 'overview' ? 'active' : ''; ?>">
                <i class="fas fa-home"></i> Overview
            </a>
            <a href="#" onclick="if(typeof switchTab === 'function') { switchTab('earnings'); } return false;" class="<?php echo $active_tab === 'earnings' ? 'active' : ''; ?>">
                <i class="fas fa-dollar-sign"></i> Earnings
            </a>
            <a href="#" onclick="if(typeof switchTab === 'function') { switchTab('students'); } return false;" class="<?php echo $active_tab === 'students' ? 'active' : ''; ?>">
                <i class="fas fa-users"></i> My Students
            </a>
            <a href="#" onclick="if(typeof switchTab === 'function') { switchTab('assignments'); } return false;" class="<?php echo $active_tab === 'assignments' ? 'active' : ''; ?>">
                <i class="fas fa-tasks"></i> Assignments
                <?php if ($pending_assignments > 0): ?>
                    <span class="sidebar-badge"><?php echo $pending_assignments; ?></span>
                <?php endif; ?>
            </a>
            <a href="#" onclick="if(typeof switchTab === 'function') { switchTab('reviews'); } return false;" class="<?php echo $active_tab === 'reviews' ? 'active' : ''; ?>">
                <i class="fas fa-star"></i> Reviews
            </a>
            <a href="#" onclick="if(typeof switchTab === 'function') { switchTab('group-classes'); } return false;" class="<?php echo $active_tab === 'group-classes' ? 'active' : ''; ?>">
                <i class="fas fa-users"></i> Group Classes
            </a>
            <a href="#" onclick="if(typeof switchTab === 'function') { switchTab('resources'); } return false;" class="<?php echo $active_tab === 'resources' ? 'active' : ''; ?>">
                <i class="fas fa-folder-open"></i> Resources
            </a>
            <a href="#" onclick="if(typeof switchTab === 'function') { switchTab('shared-materials'); } return false;" class="<?php echo $active_tab === 'shared-materials' ? 'active' : ''; ?>">
                <i class="fas fa-share-alt"></i> Shared Materials
            </a>
            <hr>
            <a href="#" onclick="if(typeof switchTab === 'function') { switchTab('profile'); } return false;" class="<?php echo $active_tab === 'profile' ? 'active' : ''; ?>">
                <i class="fas fa-user-edit"></i> Edit Profile
            </a>
            <?php else: ?>
            <a href="teacher-dashboard.php#overview" class="<?php echo $active_tab === 'overview' ? 'active' : ''; ?>">
                <i class="fas fa-home"></i> Overview
            </a>
            <a href="teacher-dashboard.php#earnings" class="<?php echo $active_tab === 'earnings' ? 'active' : ''; ?>">
                <i class="fas fa-dollar-sign"></i> Earnings
            </a>
            <a href="teacher-dashboard.php#students" class="<?php echo $active_tab === 'students' ? 'active' : ''; ?>">
                <i class="fas fa-users"></i> My Students
            </a>
            <a href="teacher-dashboard.php#assignments" class="<?php echo $active_tab === 'assignments' ? 'active' : ''; ?>">
                <i class="fas fa-tasks"></i> Assignments
                <?php if ($pending_assignments > 0): ?>
                    <span class="sidebar-badge"><?php echo $pending_assignments; ?></span>
                <?php endif; ?>
            </a>
            <a href="teacher-dashboard.php#reviews" class="<?php echo $active_tab === 'reviews' ? 'active' : ''; ?>">
                <i class="fas fa-star"></i> Reviews
            </a>
            <a href="teacher-dashboard.php#group-classes" class="<?php echo $active_tab === 'group-classes' ? 'active' : ''; ?>">
                <i class="fas fa-users"></i> Group Classes
            </a>
            <a href="teacher-dashboard.php#resources" class="<?php echo $active_tab === 'resources' ? 'active' : ''; ?>">
                <i class="fas fa-folder-open"></i> Resources
            </a>
            <a href="teacher-dashboard.php#shared-materials" class="<?php echo $active_tab === 'shared-materials' ? 'active' : ''; ?>">
                <i class="fas fa-share-alt"></i> Shared Materials
            </a>
            <hr>
            <a href="teacher-dashboard.php#profile" class="<?php echo $active_tab === 'profile' ? 'active' : ''; ?>">
                <i class="fas fa-user-edit"></i> Edit Profile
            </a>
            <?php endif; ?>
            <a href="teacher-calendar-setup.php" class="<?php echo ($active_tab === 'calendar-setup' || basename($_SERVER['PHP_SELF']) === 'teacher-calendar-setup.php') ? 'active' : ''; ?>"><i class="fas fa-calendar-alt"></i> Calendar Setup</a>
            <a href="schedule.php" class="<?php echo (basename($_SERVER['PHP_SELF']) === 'schedule.php') ? 'active' : ''; ?>"><i class="fas fa-calendar"></i> View Bookings</a>
            <hr>
            <a href="message_threads.php" class="<?php echo (basename($_SERVER['PHP_SELF']) === 'message_threads.php') ? 'active' : ''; ?>">
                <i class="fas fa-comments"></i> Messages
                <?php if ($unread_messages > 0): ?>
                    <span class="sidebar-badge"><?php echo $unread_messages; ?></span>
                <?php endif; ?>
            </a>
            <a href="classroom.php" class="<?php echo (basename($_SERVER['PHP_SELF']) === 'classroom.php' || $active_tab === 'classroom') ? 'active' : ''; ?>"><i class="fas fa-book-open"></i> Classroom</a>
            <?php if ($is_dashboard_page): ?>
            <a href="#" onclick="if(typeof switchTab === 'function') { switchTab('security'); } return false;" class="<?php echo $active_tab === 'security' ? 'active' : ''; ?>">
                <i class="fas fa-lock"></i> Security
            </a>
            <?php else: ?>
            <a href="teacher-dashboard.php#security" class="<?php echo $active_tab === 'security' ? 'active' : ''; ?>">
                <i class="fas fa-lock"></i> Security
            </a>
            <?php endif; ?>
            <a href="support_contact.php" class="support-link"><i class="fas fa-headset"></i> Support</a>
            
        <?php elseif ($user_role === 'admin'): ?>
            <!-- Admin Navigation -->
            <?php if ($is_dashboard_page): ?>
            <a href="#" onclick="if(typeof switchTab === 'function') { switchTab('dashboard'); } return false;" class="<?php echo $active_tab === 'dashboard' ? 'active' : ''; ?>">
                <i class="fas fa-tachometer-alt"></i> Dashboard
            </a>
            <a href="#" onclick="if(typeof switchTab === 'function') { switchTab('analytics'); } return false;" class="<?php echo $active_tab === 'analytics' ? 'active' : ''; ?>">
                <i class="fas fa-chart-line"></i> Analytics
            </a>
            <a href="#" onclick="if(typeof switchTab === 'function') { switchTab('pending-requests'); } return false;" class="<?php echo $active_tab === 'pending-requests' ? 'active' : ''; ?>">
                <i class="fas fa-exclamation-circle"></i> Pending Requests
                <?php 
                $total_pending = isset($admin_stats) ? ($admin_stats['pending_apps'] + $admin_stats['pending_updates']) : 0;
                if ($total_pending > 0): ?>
                    <span class="sidebar-badge" style="background: #dc3545;"><?php echo $total_pending; ?></span>
                <?php endif; ?>
            </a>
            <a href="#" onclick="if(typeof switchTab === 'function') { switchTab('teachers'); } return false;" class="<?php echo $active_tab === 'teachers' ? 'active' : ''; ?>">
                <i class="fas fa-chalkboard-teacher"></i> Teachers
            </a>
            <a href="#" onclick="if(typeof switchTab === 'function') { switchTab('students'); } return false;" class="<?php echo $active_tab === 'students' ? 'active' : ''; ?>">
                <i class="fas fa-user-graduate"></i> Students
            </a>
            <a href="#" onclick="if(typeof switchTab === 'function') { switchTab('messages'); } return false;" class="<?php echo $active_tab === 'messages' ? 'active' : ''; ?>">
                <i class="fas fa-comments"></i> Messages
                <?php 
                $admin_unread = 0;
                if (isset($conn) && isset($user_id)) {
                    $admin_unread_stmt = $conn->prepare("SELECT COUNT(*) as c FROM messages m WHERE m.receiver_id = ? AND m.is_read = 0 AND m.message_type = 'direct'");
                    if ($admin_unread_stmt) {
                        $admin_unread_stmt->bind_param("i", $user_id);
                        $admin_unread_stmt->execute();
                        $result = $admin_unread_stmt->get_result();
                        if ($result) {
                            $admin_unread = $result->fetch_assoc()['c'] ?? 0;
                        }
                        $admin_unread_stmt->close();
                    }
                }
                if ($admin_unread > 0): ?>
                    <span class="sidebar-badge alert"><?php echo $admin_unread; ?></span>
                <?php endif; ?>
            </a>
            <a href="#" onclick="if(typeof switchTab === 'function') { switchTab('support'); } return false;" class="<?php echo $active_tab === 'support' ? 'active' : ''; ?>">
                <i class="fas fa-headset"></i> Support
                <?php 
                $open_support = 0;
                if (isset($admin_stats) && isset($admin_stats['open_support'])) {
                    $open_support = $admin_stats['open_support'];
                } elseif (isset($conn)) {
                    $stmt = $conn->prepare("SELECT COUNT(*) as c FROM support_messages WHERE status='open'");
                    if ($stmt) {
                        $stmt->execute();
                        $result = $stmt->get_result();
                        if ($result) {
                            $open_support = $result->fetch_assoc()['c'] ?? 0;
                        }
                        $stmt->close();
                    }
                }
                if ($open_support > 0): ?>
                    <span class="sidebar-badge alert"><?php echo $open_support; ?></span>
                <?php endif; ?>
            </a>
            <a href="#" onclick="if(typeof switchTab === 'function') { switchTab('slot-requests'); } return false;" class="<?php echo $active_tab === 'slot-requests' ? 'active' : ''; ?>">
                <i class="fas fa-calendar-plus"></i> Slot Requests
            </a>
            <a href="#" onclick="if(typeof switchTab === 'function') { switchTab('reports'); } return false;" class="<?php echo $active_tab === 'reports' ? 'active' : ''; ?>">
                <i class="fas fa-file-alt"></i> Reports
            </a>
            <hr>
            <a href="#" onclick="if(typeof switchTab === 'function') { switchTab('my-profile'); } return false;" class="<?php echo $active_tab === 'my-profile' ? 'active' : ''; ?>">
                <i class="fas fa-user-edit"></i> My Profile
            </a>
            <a href="#" onclick="if(typeof switchTab === 'function') { switchTab('classroom'); } return false;" class="<?php echo $active_tab === 'classroom' ? 'active' : ''; ?>">
                <i class="fas fa-book-open"></i> Classroom
            </a>
            <a href="#" onclick="if(typeof switchTab === 'function') { switchTab('my-security'); } return false;" class="<?php echo $active_tab === 'my-security' ? 'active' : ''; ?>">
                <i class="fas fa-lock"></i> Security
            </a>
            <?php else: ?>
            <a href="admin-dashboard.php#dashboard" class="<?php echo $active_tab === 'dashboard' ? 'active' : ''; ?>">
                <i class="fas fa-tachometer-alt"></i> Dashboard
            </a>
            <a href="admin-dashboard.php#analytics" class="<?php echo $active_tab === 'analytics' ? 'active' : ''; ?>">
                <i class="fas fa-chart-line"></i> Analytics
            </a>
            <a href="admin-dashboard.php#pending-requests" class="<?php echo $active_tab === 'pending-requests' ? 'active' : ''; ?>">
                <i class="fas fa-exclamation-circle"></i> Pending Requests
                <?php 
                $total_pending = isset($admin_stats) ? ($admin_stats['pending_apps'] + $admin_stats['pending_updates']) : 0;
                if ($total_pending > 0): ?>
                    <span class="sidebar-badge" style="background: #dc3545;"><?php echo $total_pending; ?></span>
                <?php endif; ?>
            </a>
            <a href="admin-dashboard.php#teachers" class="<?php echo $active_tab === 'teachers' ? 'active' : ''; ?>">
                <i class="fas fa-chalkboard-teacher"></i> Teachers
            </a>
            <a href="admin-dashboard.php#students" class="<?php echo $active_tab === 'students' ? 'active' : ''; ?>">
                <i class="fas fa-user-graduate"></i> Students
            </a>
            <a href="admin-dashboard.php#messages" class="<?php echo $active_tab === 'messages' ? 'active' : ''; ?>">
                <i class="fas fa-comments"></i> Messages
                <?php 
                $admin_unread = 0;
                if (isset($conn) && isset($user_id)) {
                    $admin_unread_stmt = $conn->prepare("SELECT COUNT(*) as c FROM messages m WHERE m.receiver_id = ? AND m.is_read = 0 AND m.message_type = 'direct'");
                    if ($admin_unread_stmt) {
                        $admin_unread_stmt->bind_param("i", $user_id);
                        $admin_unread_stmt->execute();
                        $result = $admin_unread_stmt->get_result();
                        if ($result) {
                            $admin_unread = $result->fetch_assoc()['c'] ?? 0;
                        }
                        $admin_unread_stmt->close();
                    }
                }
                if ($admin_unread > 0): ?>
                    <span class="sidebar-badge alert"><?php echo $admin_unread; ?></span>
                <?php endif; ?>
            </a>
            <a href="admin-dashboard.php#support" class="<?php echo $active_tab === 'support' ? 'active' : ''; ?>">
                <i class="fas fa-headset"></i> Support
                <?php 
                $open_support = 0;
                if (isset($admin_stats) && isset($admin_stats['open_support'])) {
                    $open_support = $admin_stats['open_support'];
                } elseif (isset($conn)) {
                    $stmt = $conn->prepare("SELECT COUNT(*) as c FROM support_messages WHERE status='open'");
                    if ($stmt) {
                        $stmt->execute();
                        $result = $stmt->get_result();
                        if ($result) {
                            $open_support = $result->fetch_assoc()['c'] ?? 0;
                        }
                        $stmt->close();
                    }
                }
                if ($open_support > 0): ?>
                    <span class="sidebar-badge alert"><?php echo $open_support; ?></span>
                <?php endif; ?>
            </a>
            <a href="admin-dashboard.php#slot-requests" class="<?php echo $active_tab === 'slot-requests' ? 'active' : ''; ?>">
                <i class="fas fa-calendar-plus"></i> Slot Requests
            </a>
            <a href="admin-dashboard.php#reports" class="<?php echo $active_tab === 'reports' ? 'active' : ''; ?>">
                <i class="fas fa-file-alt"></i> Reports
            </a>
            <hr>
            <a href="admin-dashboard.php#my-profile" class="<?php echo $active_tab === 'my-profile' ? 'active' : ''; ?>">
                <i class="fas fa-user-edit"></i> My Profile
            </a>
            <a href="admin-dashboard.php#classroom" class="<?php echo $active_tab === 'classroom' ? 'active' : ''; ?>">
                <i class="fas fa-book-open"></i> Classroom
            </a>
            <a href="admin-dashboard.php#my-security" class="<?php echo $active_tab === 'my-security' ? 'active' : ''; ?>">
                <i class="fas fa-lock"></i> Security
            </a>
            <?php endif; ?>
            <a href="admin-schedule-view.php"><i class="fas fa-calendar-check"></i> Schedules</a>
            <hr>
        <?php endif; ?>
        
        <hr>
        <a href="index.php"><i class="fas fa-home"></i> Home Page</a>
        <a href="logout.php" class="logout-link"><i class="fas fa-sign-out-alt"></i> Logout</a>
    </nav>
</div>

<!-- Mobile Sidebar Overlay -->
<div class="sidebar-overlay" onclick="toggleMobileSidebar()"></div>

