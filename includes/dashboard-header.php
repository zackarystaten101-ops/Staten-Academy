<?php
/**
 * Dashboard Header Component
 * Unified header bar for all dashboard pages
 * 
 * Required variables before including:
 * - $user (array) - User data from database
 * - $page_title (string) - Optional page title
 */

if (!isset($user) && isset($_SESSION['user_id'])) {
    require_once __DIR__ . '/../db.php';
    require_once __DIR__ . '/dashboard-functions.php';
    $user = getUserById($conn, $_SESSION['user_id']);
}

$user_name = $user['name'] ?? $_SESSION['user_name'] ?? 'User';
$user_role = $user['role'] ?? $_SESSION['user_role'] ?? 'guest';
$user_pic = $user['profile_pic'] ?? 'images/placeholder-teacher.svg';
$notification_count = isset($conn) ? getUnreadNotificationCount($conn, $_SESSION['user_id'] ?? 0) : 0;
$message_count = isset($conn) ? getUnreadMessagesCount($conn, $_SESSION['user_id'] ?? 0) : 0;
?>

<div class="header-bar">
    <div class="header-bar-left">
        <button class="mobile-menu-toggle" onclick="toggleMobileSidebar()" aria-label="Toggle menu">
            <i class="fas fa-bars"></i>
        </button>
        <a href="index.php" class="header-logo-link">
            <img src="logo.png" alt="Logo" class="header-logo">
            <h2>Staten Academy</h2>
        </a>
    </div>
    
    <div class="header-bar-center">
        <?php if (isset($page_title)): ?>
            <span class="header-page-title"><?php echo h($page_title); ?></span>
        <?php endif; ?>
    </div>
    
    <div class="header-bar-right">
        <!-- Notification Bell -->
        <div class="header-notification" onclick="toggleNotifications()">
            <i class="fas fa-bell"></i>
            <?php if ($notification_count > 0): ?>
                <span class="notification-badge"><?php echo $notification_count > 99 ? '99+' : $notification_count; ?></span>
            <?php endif; ?>
            
            <!-- Notification Dropdown -->
            <div class="notification-dropdown" id="notificationDropdown">
                <div class="notification-header">
                    <strong>Notifications</strong>
                    <a href="#" onclick="markAllNotificationsRead(); return false;">Mark all read</a>
                </div>
                <div class="notification-list" id="notificationList">
                    <div class="notification-loading">
                        <i class="fas fa-spinner fa-spin"></i> Loading...
                    </div>
                </div>
                <div class="notification-footer">
                    <a href="notifications.php">View All Notifications</a>
                </div>
            </div>
        </div>
        
        <!-- Messages Icon -->
        <a href="message_threads.php" class="header-messages">
            <i class="fas fa-envelope"></i>
            <?php if ($message_count > 0): ?>
                <span class="notification-badge"><?php echo $message_count > 99 ? '99+' : $message_count; ?></span>
            <?php endif; ?>
        </a>
        
        <!-- User Profile -->
        <div class="header-bar-profile" onclick="toggleProfileMenu()">
            <div class="header-bar-info">
                <div class="header-bar-info-name"><?php echo h($user_name); ?></div>
                <div class="header-bar-info-role"><?php echo ucfirst(h($user_role)); ?></div>
            </div>
            <img src="<?php echo h($user_pic); ?>" alt="Profile" class="header-bar-profile-pic" onerror="this.src='images/placeholder-teacher.svg'">
            
            <!-- Profile Dropdown -->
            <div class="profile-dropdown" id="profileDropdown">
                <?php if ($user_role === 'student'): ?>
                    <a href="student-dashboard.php"><i class="fas fa-tachometer-alt"></i> Dashboard</a>
                    <a href="schedule.php"><i class="fas fa-calendar-plus"></i> Book Lesson</a>
                <?php elseif ($user_role === 'teacher'): ?>
                    <a href="teacher-dashboard.php"><i class="fas fa-tachometer-alt"></i> Dashboard</a>
                    <a href="profile.php?id=<?php echo $_SESSION['user_id']; ?>"><i class="fas fa-user"></i> View Profile</a>
                    <a href="schedule.php"><i class="fas fa-calendar"></i> Schedule</a>
                <?php elseif ($user_role === 'admin'): ?>
                    <a href="admin-dashboard.php"><i class="fas fa-cogs"></i> Admin Panel</a>
                    <a href="teacher-dashboard.php"><i class="fas fa-chalkboard-teacher"></i> Teaching</a>
                <?php endif; ?>
                <a href="classroom.php"><i class="fas fa-book-open"></i> Classroom</a>
                <a href="message_threads.php"><i class="fas fa-comments"></i> Messages</a>
                <hr>
                <a href="support_contact.php"><i class="fas fa-headset"></i> Support</a>
                <a href="logout.php" class="menu-logout"><i class="fas fa-sign-out-alt"></i> Logout</a>
            </div>
        </div>
    </div>
</div>

<script>
// Toggle notification dropdown
function toggleNotifications() {
    const dropdown = document.getElementById('notificationDropdown');
    const profileDropdown = document.getElementById('profileDropdown');
    profileDropdown.classList.remove('active');
    dropdown.classList.toggle('active');
    
    if (dropdown.classList.contains('active')) {
        loadNotifications();
    }
}

// Toggle profile dropdown
function toggleProfileMenu() {
    const dropdown = document.getElementById('profileDropdown');
    const notificationDropdown = document.getElementById('notificationDropdown');
    notificationDropdown.classList.remove('active');
    dropdown.classList.toggle('active');
}

// Load notifications via AJAX
function loadNotifications() {
    const list = document.getElementById('notificationList');
    fetch('api/notifications.php?action=recent')
        .then(res => res.json())
        .then(data => {
            if (data.length === 0) {
                list.innerHTML = '<div class="notification-empty">No notifications</div>';
                return;
            }
            list.innerHTML = data.map(n => `
                <a href="${n.link || '#'}" class="notification-item ${n.is_read ? '' : 'unread'}" onclick="markNotificationRead(${n.id})">
                    <div class="notification-icon"><i class="fas ${getNotificationIcon(n.type)}"></i></div>
                    <div class="notification-content">
                        <div class="notification-title">${n.title}</div>
                        <div class="notification-time">${n.time_ago}</div>
                    </div>
                </a>
            `).join('');
        })
        .catch(() => {
            list.innerHTML = '<div class="notification-empty">Failed to load</div>';
        });
}

function getNotificationIcon(type) {
    const icons = {
        'booking': 'fa-calendar-check',
        'message': 'fa-envelope',
        'review': 'fa-star',
        'assignment': 'fa-tasks',
        'payment': 'fa-dollar-sign',
        'system': 'fa-bell',
        'reminder': 'fa-clock'
    };
    return icons[type] || 'fa-bell';
}

function markNotificationRead(id) {
    fetch('api/notifications.php?action=read&id=' + id, { method: 'POST' });
}

function markAllNotificationsRead() {
    fetch('api/notifications.php?action=read_all', { method: 'POST' })
        .then(() => {
            document.querySelectorAll('.notification-badge').forEach(el => el.remove());
            document.querySelectorAll('.notification-item.unread').forEach(el => el.classList.remove('unread'));
        });
}

// Toggle mobile sidebar
function toggleMobileSidebar() {
    const sidebar = document.querySelector('.sidebar');
    const overlay = document.querySelector('.sidebar-overlay');
    sidebar.classList.toggle('active');
    if (!overlay) {
        const newOverlay = document.createElement('div');
        newOverlay.className = 'sidebar-overlay';
        newOverlay.onclick = toggleMobileSidebar;
        document.body.appendChild(newOverlay);
    }
    document.querySelector('.sidebar-overlay')?.classList.toggle('active');
}

// Close dropdowns when clicking outside
document.addEventListener('click', function(e) {
    if (!e.target.closest('.header-notification')) {
        document.getElementById('notificationDropdown')?.classList.remove('active');
    }
    if (!e.target.closest('.header-bar-profile')) {
        document.getElementById('profileDropdown')?.classList.remove('active');
    }
});
</script>

