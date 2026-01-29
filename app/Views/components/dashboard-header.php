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
    require_once __DIR__ . '/../../../db.php';
    require_once __DIR__ . '/dashboard-functions.php';
    $user = getUserById($conn, $_SESSION['user_id']);
}

$user_name = $user['name'] ?? $_SESSION['user_name'] ?? 'User';
$user_role = $user['role'] ?? $_SESSION['user_role'] ?? 'guest';
$user_pic = $user['profile_pic'] ?? getAssetPath('images/placeholder-teacher.svg');
$notification_count = isset($conn) ? getUnreadNotificationCount($conn, $_SESSION['user_id'] ?? 0) : 0;
$message_count = isset($conn) ? getUnreadMessagesCount($conn, $_SESSION['user_id'] ?? 0) : 0;
?>

<div class="header-bar">
    <div class="header-bar-left">
        <button class="mobile-menu-toggle" onclick="toggleMobileSidebar()" aria-label="Toggle menu">
            <i class="fas fa-bars"></i>
        </button>
        <a href="index.php" class="header-logo-link" title="Go to Website Home">
            <img src="<?php echo getLogoPath(); ?>" alt="Logo" class="header-logo">
            <h2>Staten Academy</h2>
        </a>
        <?php if ($user_role === 'teacher'): ?>
            <a href="index.php" class="header-home-btn" title="Go to Website Home">
                <i class="fas fa-home"></i> <span>Home</span>
            </a>
        <?php elseif ($user_role === 'student'): ?>
            <a href="index.php" class="header-home-btn" title="Go to Website Home">
                <i class="fas fa-home"></i> <span>Home</span>
            </a>
        <?php elseif ($user_role === 'admin'): ?>
            <a href="index.php" class="header-home-btn" title="Go to Website Home">
                <i class="fas fa-home"></i> <span>Home</span>
            </a>
        <?php endif; ?>
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
        
        <!-- WhatsApp Support -->
        <a href="https://wa.me/50558477620?text=Hello%20Staten%20Academy" target="_blank" class="header-whatsapp" title="Contact Support via WhatsApp" style="color: #25D366; font-size: 1.5rem; margin: 0 10px; text-decoration: none; display: flex; align-items: center;">
            <i class="fab fa-whatsapp"></i>
        </a>
        
        <!-- User Profile -->
        <div class="header-bar-profile" onclick="toggleProfileMenu()">
            <div class="header-bar-info">
                <div class="header-bar-info-name"><?php echo h($user_name); ?></div>
                <div class="header-bar-info-role"><?php echo ucfirst(h($user_role)); ?></div>
            </div>
            <img src="<?php echo h($user_pic); ?>" alt="Profile" class="header-bar-profile-pic" onerror="this.src='<?php echo getAssetPath('images/placeholder-teacher.svg'); ?>'">
            
            <!-- Profile Dropdown -->
            <div class="profile-dropdown" id="profileDropdown">
                <?php if ($user_role === 'student'): ?>
                    <a href="student-dashboard.php"><i class="fas fa-tachometer-alt"></i> Dashboard</a>
                    <a href="student-dashboard.php#group-classes"><i class="fas fa-users"></i> My Classes</a>
                <?php elseif ($user_role === 'teacher'): ?>
                    <a href="teacher-dashboard.php"><i class="fas fa-tachometer-alt"></i> Dashboard</a>
                    <a href="teacher-dashboard.php#group-classes"><i class="fas fa-users"></i> My Classes</a>
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
    
    // Safety check: ensure sidebar exists
    if (!sidebar) {
        console.warn('Sidebar element not found');
        return;
    }
    
    sidebar.classList.toggle('active');
    if (!overlay) {
        const newOverlay = document.createElement('div');
        newOverlay.className = 'sidebar-overlay';
        newOverlay.onclick = toggleMobileSidebar;
        document.body.appendChild(newOverlay);
    }
    document.querySelector('.sidebar-overlay')?.classList.toggle('active');
    
    // Prevent body scroll when sidebar is open on mobile
    if (window.innerWidth <= 768) {
        if (sidebar.classList.contains('active')) {
            document.body.style.overflow = 'hidden';
        } else {
            document.body.style.overflow = '';
        }
    }
}

// Close sidebar when clicking menu items on mobile
if (window.innerWidth <= 768) {
    document.addEventListener('DOMContentLoaded', function() {
        const sidebarLinks = document.querySelectorAll('.sidebar-menu a');
        sidebarLinks.forEach(link => {
            link.addEventListener('click', function() {
                // Close sidebar after a short delay to allow navigation
                setTimeout(() => {
                    const sidebar = document.querySelector('.sidebar');
                    if (sidebar && sidebar.classList.contains('active')) {
                        toggleMobileSidebar();
                    }
                }, 300);
            });
        });
    });
}

// Swipe gesture support for sidebar (mobile)
(function() {
    let touchStartX = 0;
    let touchStartY = 0;
    let touchEndX = 0;
    let touchEndY = 0;
    const minSwipeDistance = 50;
    
    document.addEventListener('touchstart', function(e) {
        touchStartX = e.changedTouches[0].screenX;
        touchStartY = e.changedTouches[0].screenY;
    }, { passive: true });
    
    document.addEventListener('touchend', function(e) {
        if (window.innerWidth > 768) return; // Only on mobile
        
        touchEndX = e.changedTouches[0].screenX;
        touchEndY = e.changedTouches[0].screenY;
        
        const deltaX = touchEndX - touchStartX;
        const deltaY = touchEndY - touchStartY;
        
        // Check if it's a horizontal swipe (not vertical scroll)
        if (Math.abs(deltaX) > Math.abs(deltaY) && Math.abs(deltaX) > minSwipeDistance) {
            const sidebar = document.querySelector('.sidebar');
            
            // Swipe right to open (from left edge)
            if (deltaX > 0 && touchStartX < 20 && sidebar && !sidebar.classList.contains('active')) {
                toggleMobileSidebar();
            }
            // Swipe left to close (when sidebar is open)
            else if (deltaX < 0 && sidebar && sidebar.classList.contains('active')) {
                // Only close if swiping from sidebar area
                if (touchStartX < 300) {
                    toggleMobileSidebar();
                }
            }
        }
    }, { passive: true });
})();

// Close dropdowns when clicking outside
document.addEventListener('click', function(e) {
    if (!e.target.closest('.header-notification')) {
        document.getElementById('notificationDropdown')?.classList.remove('active');
    }
    if (!e.target.closest('.header-bar-profile')) {
        document.getElementById('profileDropdown')?.classList.remove('active');
    }
});

// Make switchTab function available globally for header home button
if (typeof switchTab === 'undefined') {
    window.switchTab = function(id, event) {
        // Prevent any page navigation if event is provided
        if (event && typeof event.preventDefault === 'function') {
            event.preventDefault();
        }
        
        document.querySelectorAll('.tab-content').forEach(el => el.classList.remove('active'));
        const targetTab = document.getElementById(id);
        if (targetTab) {
            targetTab.classList.add('active');
        }
        
        document.querySelectorAll('.sidebar-menu a').forEach(el => el.classList.remove('active'));
        const activeLink = document.querySelector(`.sidebar-menu a[onclick*="${id}"]`);
        if (activeLink) activeLink.classList.add('active');
        
        // Also check sidebar header button
        const sidebarHeader = document.querySelector('.sidebar-header a');
        if (sidebarHeader && (id === 'overview' || id === 'dashboard')) {
            sidebarHeader.classList.add('active');
        }
        
        // Scroll to top of main content
        const mainContent = document.querySelector('.main');
        if (mainContent) mainContent.scrollTop = 0;
        
        // Update URL hash without triggering page reload
        if (window.location.hash !== '#' + id) {
            window.history.pushState(null, null, '#' + id);
        }
    };
    
    // Handle hash changes for browser back/forward
    window.addEventListener('hashchange', function() {
        const hash = window.location.hash.substring(1);
        if (hash && document.getElementById(hash)) {
            window.switchTab(hash);
        }
    });
    
    // Handle initial hash on load
    document.addEventListener('DOMContentLoaded', function() {
        const hash = window.location.hash.substring(1);
        if (hash && document.getElementById(hash)) {
            window.switchTab(hash);
        }
        
        // Table scroll detection for mobile
        if (window.innerWidth <= 576) {
            const tableResponsive = document.querySelectorAll('.table-responsive');
            tableResponsive.forEach(table => {
                function checkScroll() {
                    if (table.scrollWidth > table.clientWidth) {
                        table.classList.add('has-scroll');
                    } else {
                        table.classList.remove('has-scroll');
                    }
                }
                
                checkScroll();
                window.addEventListener('resize', checkScroll);
                table.addEventListener('scroll', function() {
                    // Update scroll indicator
                    if (table.scrollLeft > 0) {
                        table.classList.add('scrolling');
                    } else {
                        table.classList.remove('scrolling');
                    }
                }, { passive: true });
            });
        }
        
        // Close sidebar when clicking overlay
        const overlay = document.querySelector('.sidebar-overlay');
        if (overlay) {
            overlay.addEventListener('click', function(e) {
                if (e.target === overlay) {
                    toggleMobileSidebar();
                }
            });
        }
        
        // Prevent body scroll when sidebar is open
        const observer = new MutationObserver(function(mutations) {
            mutations.forEach(function(mutation) {
                if (mutation.type === 'attributes' && mutation.attributeName === 'class') {
                    const sidebar = document.querySelector('.sidebar');
                    if (window.innerWidth <= 768) {
                        if (sidebar && sidebar.classList.contains('active')) {
                            document.body.style.overflow = 'hidden';
                        } else {
                            document.body.style.overflow = '';
                        }
                    }
                }
            });
        });
        
        const sidebar = document.querySelector('.sidebar');
        if (sidebar) {
            observer.observe(sidebar, { attributes: true });
        }
        
        // Improve form input focus on mobile
        if (window.innerWidth <= 576) {
            const inputs = document.querySelectorAll('input, select, textarea');
            inputs.forEach(input => {
                input.addEventListener('focus', function() {
                    // Scroll input into view with some offset
                    setTimeout(() => {
                        const rect = input.getBoundingClientRect();
                        const offset = 100; // Offset from top
                        if (rect.top < offset || rect.bottom > window.innerHeight - offset) {
                            input.scrollIntoView({ behavior: 'smooth', block: 'center' });
                        }
                    }, 300); // Delay to allow keyboard to appear
                });
            });
        }
        
        // Improve dropdown positioning on mobile
        if (window.innerWidth <= 576) {
            const notificationBtn = document.querySelector('.header-notification');
            const profileBtn = document.querySelector('.header-bar-profile');
            
            if (notificationBtn) {
                notificationBtn.addEventListener('click', function() {
                    const dropdown = document.getElementById('notificationDropdown');
                    if (dropdown) {
                        // Ensure dropdown is visible
                        setTimeout(() => {
                            dropdown.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
                        }, 100);
                    }
                });
            }
            
            if (profileBtn) {
                profileBtn.addEventListener('click', function() {
                    const dropdown = document.getElementById('profileDropdown');
                    if (dropdown) {
                        setTimeout(() => {
                            dropdown.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
                        }, 100);
                    }
                });
            }
        }
    });
}
</script>

