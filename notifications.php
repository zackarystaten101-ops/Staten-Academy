<?php
/**
 * Notifications Page
 * View all notifications
 */

session_start();
require_once 'db.php';
require_once 'includes/dashboard-functions.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$user = getUserById($conn, $user_id);
$user_role = $user['role'];

// Mark as read if requested
if (isset($_GET['mark_read'])) {
    $id = (int)$_GET['mark_read'];
    $stmt = $conn->prepare("UPDATE notifications SET is_read = 1, read_at = NOW() WHERE id = ? AND user_id = ?");
    $stmt->bind_param("ii", $id, $user_id);
    $stmt->execute();
    $stmt->close();
    header("Location: notifications.php");
    exit();
}

// Mark all as read
if (isset($_GET['mark_all_read'])) {
    $stmt = $conn->prepare("UPDATE notifications SET is_read = 1, read_at = NOW() WHERE user_id = ? AND is_read = 0");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $stmt->close();
    header("Location: notifications.php");
    exit();
}

// Fetch notifications
$notifications = [];
$stmt = $conn->prepare("SELECT * FROM notifications WHERE user_id = ? ORDER BY created_at DESC LIMIT 100");
if ($stmt) {
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $notifications[] = $row;
    }
    $stmt->close();
}

$unread_count = 0;
foreach ($notifications as $n) {
    if (!$n['is_read']) $unread_count++;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Notifications - Staten Academy</title>
    <link rel="stylesheet" href="styles.css">
    <link rel="stylesheet" href="css/dashboard.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body class="dashboard-layout">

<?php include 'includes/dashboard-header.php'; ?>

<div class="content-wrapper">
    <div class="main" style="max-width: 800px; margin: 0 auto;">
        
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
            <h1>Notifications</h1>
            <?php if ($unread_count > 0): ?>
            <a href="?mark_all_read=1" class="btn-outline">
                <i class="fas fa-check-double"></i> Mark all as read
            </a>
            <?php endif; ?>
        </div>

        <?php if (count($notifications) > 0): ?>
            <?php foreach ($notifications as $n): ?>
            <div class="card notification-item <?php echo $n['is_read'] ? '' : 'unread'; ?>" style="display: flex; gap: 15px; align-items: flex-start; padding: 20px;">
                <div class="notification-icon">
                    <i class="fas <?php echo getNotificationIcon($n['type']); ?>"></i>
                </div>
                <div style="flex: 1;">
                    <div style="display: flex; justify-content: space-between; align-items: flex-start;">
                        <div>
                            <strong style="color: var(--dark);"><?php echo h($n['title']); ?></strong>
                            <?php if (!$n['is_read']): ?>
                            <span class="tag pending" style="margin-left: 10px;">New</span>
                            <?php endif; ?>
                        </div>
                        <span style="color: var(--gray); font-size: 0.85rem;">
                            <?php echo formatRelativeTime($n['created_at']); ?>
                        </span>
                    </div>
                    <?php if ($n['message']): ?>
                    <p style="color: #555; margin: 10px 0 0;"><?php echo h($n['message']); ?></p>
                    <?php endif; ?>
                    <div style="margin-top: 10px;">
                        <?php if ($n['link']): ?>
                        <a href="<?php echo h($n['link']); ?>" class="btn-primary btn-sm">View</a>
                        <?php endif; ?>
                        <?php if (!$n['is_read']): ?>
                        <a href="?mark_read=<?php echo $n['id']; ?>" class="btn-outline btn-sm">Mark Read</a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        <?php else: ?>
            <div class="empty-state">
                <i class="fas fa-bell-slash"></i>
                <h3>No Notifications</h3>
                <p>You're all caught up! Notifications will appear here.</p>
            </div>
        <?php endif; ?>

        <div style="text-align: center; margin-top: 30px;">
            <a href="<?php echo $user_role; ?>-dashboard.php" class="btn-outline">
                <i class="fas fa-arrow-left"></i> Back to Dashboard
            </a>
        </div>

    </div>
</div>

<script>
function toggleMobileSidebar() {
    document.querySelector('.sidebar')?.classList.toggle('active');
    document.querySelector('.sidebar-overlay')?.classList.toggle('active');
}
</script>

</body>
</html>

