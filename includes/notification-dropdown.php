<?php
/**
 * Notification Dropdown Component
 * Standalone notification bell with dropdown
 * Can be included separately if needed
 */

$notification_count = 0;
if (isset($conn) && isset($_SESSION['user_id'])) {
    require_once __DIR__ . '/dashboard-functions.php';
    $notification_count = getUnreadNotificationCount($conn, $_SESSION['user_id']);
}
?>

<div class="notification-wrapper">
    <button class="notification-bell" onclick="toggleNotificationDropdown()" aria-label="Notifications">
        <i class="fas fa-bell"></i>
        <?php if ($notification_count > 0): ?>
            <span class="notification-count"><?php echo $notification_count > 99 ? '99+' : $notification_count; ?></span>
        <?php endif; ?>
    </button>
    
    <div class="notification-panel" id="notificationPanel">
        <div class="notification-panel-header">
            <h4>Notifications</h4>
            <button onclick="markAllAsRead()" class="mark-read-btn">Mark all as read</button>
        </div>
        
        <div class="notification-panel-body" id="notificationPanelBody">
            <!-- Loaded via AJAX -->
            <div class="notification-loading">
                <i class="fas fa-spinner fa-spin"></i>
                <span>Loading notifications...</span>
            </div>
        </div>
        
        <div class="notification-panel-footer">
            <a href="notifications.php">View all notifications</a>
        </div>
    </div>
</div>

<style>
.notification-wrapper {
    position: relative;
    display: inline-block;
}

.notification-bell {
    background: transparent;
    border: none;
    color: white;
    font-size: 1.2rem;
    cursor: pointer;
    padding: 8px;
    position: relative;
    transition: transform 0.2s;
}

.notification-bell:hover {
    transform: scale(1.1);
}

.notification-count {
    position: absolute;
    top: 0;
    right: 0;
    background: #ff4757;
    color: white;
    font-size: 0.7rem;
    padding: 2px 6px;
    border-radius: 10px;
    min-width: 18px;
    text-align: center;
    font-weight: bold;
}

.notification-panel {
    position: absolute;
    top: 100%;
    right: 0;
    width: 350px;
    max-height: 450px;
    background: white;
    border-radius: 10px;
    box-shadow: 0 5px 25px rgba(0,0,0,0.15);
    z-index: 1000;
    display: none;
    overflow: hidden;
}

.notification-panel.active {
    display: block;
    animation: slideDown 0.2s ease;
}

@keyframes slideDown {
    from { opacity: 0; transform: translateY(-10px); }
    to { opacity: 1; transform: translateY(0); }
}

.notification-panel-header {
    padding: 15px;
    border-bottom: 1px solid #eee;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.notification-panel-header h4 {
    margin: 0;
    color: #333;
}

.mark-read-btn {
    background: none;
    border: none;
    color: #0b6cf5;
    cursor: pointer;
    font-size: 0.85rem;
}

.notification-panel-body {
    max-height: 320px;
    overflow-y: auto;
}

.notification-item {
    display: flex;
    padding: 12px 15px;
    border-bottom: 1px solid #f0f0f0;
    transition: background 0.2s;
    text-decoration: none;
    color: inherit;
}

.notification-item:hover {
    background: #f8f9fa;
}

.notification-item.unread {
    background: #f0f7ff;
}

.notification-item.unread:hover {
    background: #e1f0ff;
}

.notification-icon {
    width: 40px;
    height: 40px;
    border-radius: 50%;
    background: #e1f0ff;
    display: flex;
    align-items: center;
    justify-content: center;
    margin-right: 12px;
    flex-shrink: 0;
}

.notification-icon i {
    color: #0b6cf5;
}

.notification-content {
    flex: 1;
    min-width: 0;
}

.notification-title {
    font-size: 0.9rem;
    color: #333;
    margin-bottom: 3px;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.notification-message {
    font-size: 0.8rem;
    color: #666;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.notification-time {
    font-size: 0.75rem;
    color: #999;
    margin-top: 3px;
}

.notification-loading,
.notification-empty {
    padding: 40px 20px;
    text-align: center;
    color: #999;
}

.notification-panel-footer {
    padding: 12px;
    text-align: center;
    border-top: 1px solid #eee;
}

.notification-panel-footer a {
    color: #0b6cf5;
    text-decoration: none;
    font-size: 0.9rem;
}

@media (max-width: 400px) {
    .notification-panel {
        width: 300px;
        right: -50px;
    }
}
</style>

<script>
function toggleNotificationDropdown() {
    const panel = document.getElementById('notificationPanel');
    panel.classList.toggle('active');
    
    if (panel.classList.contains('active')) {
        loadNotificationPanel();
    }
}

function loadNotificationPanel() {
    const body = document.getElementById('notificationPanelBody');
    
    fetch('api/notifications.php?action=recent&limit=10')
        .then(res => res.json())
        .then(data => {
            if (!data || data.length === 0) {
                body.innerHTML = '<div class="notification-empty"><i class="fas fa-bell-slash"></i><br>No notifications yet</div>';
                return;
            }
            
            body.innerHTML = data.map(n => `
                <a href="${n.link || '#'}" class="notification-item ${n.is_read ? '' : 'unread'}" data-id="${n.id}">
                    <div class="notification-icon">
                        <i class="fas ${getNotifIcon(n.type)}"></i>
                    </div>
                    <div class="notification-content">
                        <div class="notification-title">${escapeHtml(n.title)}</div>
                        ${n.message ? `<div class="notification-message">${escapeHtml(n.message)}</div>` : ''}
                        <div class="notification-time">${n.time_ago || ''}</div>
                    </div>
                </a>
            `).join('');
        })
        .catch(err => {
            body.innerHTML = '<div class="notification-empty">Failed to load notifications</div>';
        });
}

function getNotifIcon(type) {
    const icons = {
        'booking': 'fa-calendar-check',
        'message': 'fa-envelope',
        'review': 'fa-star',
        'assignment': 'fa-tasks',
        'payment': 'fa-dollar-sign',
        'system': 'fa-info-circle',
        'reminder': 'fa-clock',
        'achievement': 'fa-trophy'
    };
    return icons[type] || 'fa-bell';
}

function markAllAsRead() {
    fetch('api/notifications.php?action=read_all', { method: 'POST' })
        .then(() => {
            document.querySelectorAll('.notification-item.unread').forEach(el => {
                el.classList.remove('unread');
            });
            const countBadge = document.querySelector('.notification-count');
            if (countBadge) countBadge.remove();
        });
}

function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

// Close on outside click
document.addEventListener('click', function(e) {
    if (!e.target.closest('.notification-wrapper')) {
        document.getElementById('notificationPanel')?.classList.remove('active');
    }
});
</script>

