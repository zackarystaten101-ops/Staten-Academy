<?php
// Start output buffering to prevent "headers already sent" errors
ob_start();

// Load environment configuration first
if (!defined('DB_HOST')) {
    require_once __DIR__ . '/env.php';
}

// Start session before any output
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/app/Views/components/dashboard-functions.php';

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
    ob_end_clean(); // Clear output buffer before redirect
    header("Location: login.php");
    exit();
}

$admin_id = $_SESSION['user_id'];
$user = getUserById($conn, $admin_id);
$user_role = 'admin';

// Get admin stats
$admin_stats = getAdminStats($conn);

// Handle Profile Update (Admin can update directly)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
    $dob = $_POST['dob'];
    $bio = $_POST['bio'];
    $calendly = $_POST['calendly'];
    $name = $_POST['name'];
    $backup_email = filter_input(INPUT_POST, 'backup_email', FILTER_SANITIZE_EMAIL);
    $age = !empty($_POST['age']) ? (int)$_POST['age'] : NULL;
    $age_visibility = $_POST['age_visibility'] ?? 'private';
    $profile_pic = $user['profile_pic'];

    if (isset($_FILES['profile_pic_file']) && $_FILES['profile_pic_file']['error'] === UPLOAD_ERR_OK) {
        $file = $_FILES['profile_pic_file'];
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $allowed = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
        if (in_array($ext, $allowed) && $file['size'] <= 5 * 1024 * 1024) {
            $filename = 'user_' . $admin_id . '_' . time() . '.' . $ext;
            
            // Determine upload directory - works for both localhost and cPanel
            $upload_base = __DIR__;
            $public_images_dir = $upload_base . DIRECTORY_SEPARATOR . 'public' . DIRECTORY_SEPARATOR . 'assets' . DIRECTORY_SEPARATOR . 'images';
            $flat_images_dir = $upload_base . DIRECTORY_SEPARATOR . 'assets' . DIRECTORY_SEPARATOR . 'images';
            
            if (is_dir($public_images_dir)) {
                $target_dir = $public_images_dir;
            } elseif (is_dir($flat_images_dir)) {
                $target_dir = $flat_images_dir;
            } else {
                $target_dir = is_dir($upload_base . DIRECTORY_SEPARATOR . 'public') ? $public_images_dir : $flat_images_dir;
                @mkdir($target_dir, 0755, true);
            }
            
            $target_path = $target_dir . DIRECTORY_SEPARATOR . $filename;
            if (move_uploaded_file($file['tmp_name'], $target_path)) {
                $profile_pic = '/assets/images/' . $filename;
            }
        }
    } elseif (!empty($_POST['profile_pic_url'])) {
        $profile_pic = $_POST['profile_pic_url'];
    }

    $stmt = $conn->prepare("UPDATE users SET name = ?, dob = ?, bio = ?, calendly_link = ?, profile_pic = ?, backup_email = ?, age = ?, age_visibility = ? WHERE id = ?");
    $stmt->bind_param("sssssisis", $name, $dob, $bio, $calendly, $profile_pic, $backup_email, $age, $age_visibility, $admin_id);
    $stmt->execute();
    $stmt->close();
    
    ob_end_clean(); // Clear output buffer before redirect
    header("Location: admin-dashboard.php#my-profile");
    exit();
}

// Handle Password Change
$password_error = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_password'])) {
    $current_password = $_POST['current_password'];
    $new_password = $_POST['new_password'];
    $confirm_password = $_POST['confirm_password'];
    
    if (!password_verify($current_password, $user['password'])) {
        $password_error = 'Current password is incorrect.';
    } elseif (strlen($new_password) < 6) {
        $password_error = 'New password must be at least 6 characters.';
    } elseif ($new_password !== $confirm_password) {
        $password_error = 'New passwords do not match.';
    } else {
        $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
        $stmt = $conn->prepare("UPDATE users SET password = ? WHERE id = ?");
        $stmt->bind_param("si", $hashed_password, $admin_id);
        $stmt->execute();
        $stmt->close();
        $password_error = 'password_changed';
    }
}

// Handle Material Upload
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['upload_material'])) {
    $title = $_POST['title'];
    $type = $_POST['type'];
    $content = $_POST['content_url'];
    
    $stmt = $conn->prepare("INSERT INTO classroom_materials (title, type, link_url, uploaded_by) VALUES (?, ?, ?, ?)");
    $stmt->bind_param("sssi", $title, $type, $content, $admin_id);
    $stmt->execute();
    $stmt->close();
    
    ob_end_clean(); // Clear output buffer before redirect
    header("Location: admin-dashboard.php#classroom");
    exit();
}

// Fetch data
$pending_updates = $conn->query("SELECT p.*, u.email as user_email FROM pending_updates p JOIN users u ON p.user_id = u.id");
if (!$pending_updates) {
    error_log("Error fetching pending updates: " . $conn->error);
    $pending_updates = new mysqli_result($conn);
}

$applications = $conn->query("SELECT * FROM users WHERE application_status='pending'");
if (!$applications) {
    error_log("Error fetching applications: " . $conn->error);
    $applications = new mysqli_result($conn);
}

$students = $conn->query("SELECT * FROM users WHERE role='student' ORDER BY reg_date DESC");
if (!$students) {
    error_log("Error fetching students: " . $conn->error);
    $students = new mysqli_result($conn);
}

$teachers = $conn->query("SELECT u.*, 
    (SELECT AVG(rating) FROM reviews WHERE teacher_id = u.id) as avg_rating,
    (SELECT COUNT(*) FROM reviews WHERE teacher_id = u.id) as review_count,
    (SELECT COUNT(DISTINCT student_id) FROM bookings WHERE teacher_id = u.id) as student_count
    FROM users u WHERE u.role='teacher' ORDER BY u.id DESC");
if (!$teachers) {
    error_log("Error fetching teachers: " . $conn->error);
    $teachers = new mysqli_result($conn);
}

$materials = $conn->query("SELECT * FROM classroom_materials ORDER BY created_at DESC");
if (!$materials) {
    error_log("Error fetching materials: " . $conn->error);
    $materials = new mysqli_result($conn);
}

$support_messages = $conn->query("
    SELECT sm.*, u.name as sender_name, u.profile_pic, u.role 
    FROM support_messages sm 
    JOIN users u ON sm.sender_id = u.id 
    ORDER BY sm.created_at DESC
");
if (!$support_messages) {
    error_log("Error fetching support messages: " . $conn->error);
    $support_messages = new mysqli_result($conn);
}

// Revenue analytics
$total_revenue = 0;
$revenue_result = $conn->query("SELECT COALESCE(SUM(amount), 0) as total FROM earnings");
if ($revenue_result) {
    $total_revenue = $revenue_result->fetch_assoc()['total'];
}

// Recent activity
$recent_bookings = $conn->query("
    SELECT b.*, s.name as student_name, t.name as teacher_name 
    FROM bookings b 
    JOIN users s ON b.student_id = s.id 
    JOIN users t ON b.teacher_id = t.id 
    ORDER BY b.booking_date DESC LIMIT 10
");
if (!$recent_bookings) {
    error_log("Error fetching recent bookings: " . $conn->error);
    $recent_bookings = new mysqli_result($conn);
}

// Engagement metrics
// Check if last_active column exists
$last_active_exists = false;
$col_check = $conn->query("SHOW COLUMNS FROM users LIKE 'last_active'");
if ($col_check && $col_check->num_rows > 0) {
    $last_active_exists = true;
}

$inactive_students_query = "
    SELECT u.* FROM users u 
    WHERE u.role = 'student' 
    AND u.id NOT IN (SELECT student_id FROM bookings WHERE booking_date > DATE_SUB(NOW(), INTERVAL 30 DAY))
";
if ($last_active_exists) {
    $inactive_students_query .= " ORDER BY u.last_active ASC";
} else {
    $inactive_students_query .= " ORDER BY u.reg_date ASC";
}
$inactive_students_query .= " LIMIT 10";

$inactive_students = $conn->query($inactive_students_query);
if (!$inactive_students) {
    error_log("Error fetching inactive students: " . $conn->error);
    $inactive_students = new mysqli_result($conn);
}

$unread_support = $admin_stats['open_support'];
$active_tab = 'dashboard';

// Get all users for admin chat
$all_users_stmt = $conn->prepare("
    SELECT DISTINCT u.id, u.name, u.email, u.role, u.profile_pic,
           COALESCE((SELECT COUNT(*) FROM messages m WHERE m.receiver_id = ? AND m.sender_id = u.id AND m.is_read = 0 AND m.message_type = 'direct'), 0) as unread_count,
           (SELECT MAX(sent_at) FROM messages m WHERE ((m.sender_id = ? AND m.receiver_id = u.id) OR (m.sender_id = u.id AND m.receiver_id = ?)) AND m.message_type = 'direct') as last_message_time
    FROM users u
    WHERE u.id != ? AND u.role != 'admin'
    ORDER BY last_message_time IS NULL, last_message_time DESC, u.name ASC
");
$all_users_stmt->bind_param("iiiii", $admin_id, $admin_id, $admin_id, $admin_id);
$all_users_stmt->execute();
$all_users_result = $all_users_stmt->get_result();
$all_users = [];
while ($row = $all_users_result->fetch_assoc()) {
    $all_users[] = $row;
}
$all_users_stmt->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=5.0, user-scalable=yes">
    <meta name="theme-color" content="#004080">
    <meta name="mobile-web-app-capable" content="yes">
    <title>Admin Dashboard - Staten Academy</title>
    <link rel="stylesheet" href="<?php echo getAssetPath('styles.css'); ?>">
    <link rel="stylesheet" href="<?php echo getAssetPath('css/dashboard.css'); ?>">
    <link rel="stylesheet" href="<?php echo getAssetPath('css/mobile.css'); ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <script src="<?php echo getAssetPath('js/toast.js'); ?>" defer></script>
</head>
<body class="dashboard-layout">

<?php include __DIR__ . '/app/Views/components/dashboard-header.php'; ?>

<div class="content-wrapper">
    <?php 
    // Make admin_stats available to sidebar
    $admin_stats_for_sidebar = $admin_stats;
    include __DIR__ . '/app/Views/components/dashboard-sidebar.php'; 
    ?>

    <div class="main">
        
        <!-- Dashboard Tab -->
        <div id="dashboard" class="tab-content active">
            <h1>Admin Dashboard</h1>
            
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-icon"><i class="fas fa-user-graduate"></i></div>
                    <div class="stat-info">
                        <h3><?php echo $admin_stats['students']; ?></h3>
                        <p>Students</p>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon success"><i class="fas fa-chalkboard-teacher"></i></div>
                    <div class="stat-info">
                        <h3><?php echo $admin_stats['teachers']; ?></h3>
                        <p>Teachers</p>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon warning"><i class="fas fa-user-plus"></i></div>
                    <div class="stat-info">
                        <h3><?php echo $admin_stats['pending_apps']; ?></h3>
                        <p>Pending Apps</p>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon info"><i class="fas fa-dollar-sign"></i></div>
                    <div class="stat-info">
                        <h3><?php echo formatCurrency($total_revenue); ?></h3>
                        <p>Total Revenue</p>
                    </div>
                </div>
            </div>

            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
                <div class="card">
                    <h2><i class="fas fa-exclamation-circle"></i> Pending Actions</h2>
                    <div style="display: flex; flex-direction: column; gap: 10px;">
                        <?php 
                        $total_pending = $admin_stats['pending_apps'] + $admin_stats['pending_updates'];
                        if ($total_pending > 0): ?>
                        <a href="#" onclick="switchTab('pending-requests')" class="quick-action-btn" style="flex-direction: row; justify-content: space-between; background: #fff5f5; border-color: #dc3545;">
                            <span><i class="fas fa-exclamation-circle"></i> Pending Requests</span>
                            <span class="notification-badge" style="position: static; background: #dc3545; color: white;"><?php echo $total_pending; ?></span>
                        </a>
                        <?php endif; ?>
                        <?php if ($unread_support > 0): ?>
                        <a href="#" onclick="switchTab('support')" class="quick-action-btn" style="flex-direction: row; justify-content: space-between; background: #fff5f5; border-color: var(--danger);">
                            <span><i class="fas fa-headset"></i> Support Messages</span>
                            <span class="notification-badge" style="position: static; background: var(--danger);"><?php echo $unread_support; ?></span>
                        </a>
                        <?php endif; ?>
                        <?php if ($total_pending == 0 && $unread_support == 0): ?>
                        <p style="color: var(--gray); text-align: center; padding: 20px;">
                            <i class="fas fa-check-circle" style="color: var(--success);"></i> All caught up!
                        </p>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="card">
                    <h2><i class="fas fa-history"></i> Recent Bookings</h2>
                    <?php if ($recent_bookings && $recent_bookings->num_rows > 0): ?>
                        <?php while ($booking = $recent_bookings->fetch_assoc()): ?>
                        <div style="display: flex; justify-content: space-between; padding: 10px 0; border-bottom: 1px solid var(--border);">
                            <div>
                                <strong><?php echo h($booking['student_name']); ?></strong>
                                <span style="color: var(--gray);">→</span>
                                <strong><?php echo h($booking['teacher_name']); ?></strong>
                            </div>
                            <span style="color: var(--gray); font-size: 0.85rem;">
                                <?php echo date('M d', strtotime($booking['booking_date'])); ?>
                            </span>
                        </div>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <p style="color: var(--gray); text-align: center;">No recent bookings</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Analytics Tab -->
        <div id="analytics" class="tab-content">
            <h1>Analytics & Reports</h1>
            
            <div class="earnings-summary">
                <div class="earnings-card primary">
                    <div class="earnings-amount"><?php echo formatCurrency($total_revenue); ?></div>
                    <div class="earnings-label">Total Revenue</div>
                </div>
                <div class="earnings-card">
                    <div class="earnings-amount"><?php echo $admin_stats['total_bookings']; ?></div>
                    <div class="earnings-label">Total Bookings</div>
                </div>
                <div class="earnings-card">
                    <div class="earnings-amount"><?php echo $admin_stats['students'] + $admin_stats['teachers']; ?></div>
                    <div class="earnings-label">Total Users</div>
                </div>
            </div>

            <div class="card">
                <h2><i class="fas fa-trophy"></i> Top Teachers</h2>
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Teacher</th>
                            <th>Rating</th>
                            <th>Students</th>
                            <th>Hours</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        $teachers->data_seek(0);
                        $count = 0;
                        while ($t = $teachers->fetch_assoc()): 
                            if ($count++ >= 10) break;
                        ?>
                        <tr>
                            <td data-label="Teacher">
                                <div style="display: flex; align-items: center; gap: 10px;">
                                    <img src="<?php echo h($t['profile_pic']); ?>" alt="<?php echo h($t['name']); ?>" style="width: 35px; height: 35px; border-radius: 50%; object-fit: cover;" onerror="this.src='<?php echo getAssetPath('images/placeholder-teacher.svg'); ?>'">
                                    <?php echo h($t['name']); ?>
                                </div>
                            </td>
                            <td data-label="Rating"><?php echo getStarRatingHtml($t['avg_rating'] ?? 0); ?></td>
                            <td data-label="Students"><?php echo $t['student_count'] ?? 0; ?></td>
                            <td data-label="Hours"><?php echo $t['hours_taught'] ?? 0; ?> hrs</td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>

            <div class="card">
                <h2><i class="fas fa-user-clock"></i> Inactive Students (30+ days)</h2>
                <?php if ($inactive_students && $inactive_students->num_rows > 0): ?>
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Student</th>
                            <th>Email</th>
                            <th>Joined</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($s = $inactive_students->fetch_assoc()): ?>
                        <tr>
                            <td data-label="Student"><?php echo h($s['name']); ?></td>
                            <td data-label="Email"><?php echo h($s['email']); ?></td>
                            <td data-label="Joined"><?php echo date('M d, Y', strtotime($s['reg_date'])); ?></td>
                            <td data-label="Actions">
                                <a href="mailto:<?php echo h($s['email']); ?>" class="btn-outline btn-sm">Send Email</a>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
                <?php else: ?>
                <p style="color: var(--gray); text-align: center; padding: 20px;">No inactive students found!</p>
                <?php endif; ?>
            </div>
        </div>

        <!-- Unified Pending Requests Tab -->
        <div id="pending-requests" class="tab-content">
            <h1><i class="fas fa-exclamation-circle"></i> Pending Requests</h1>
            <p style="color: var(--gray); margin-bottom: 20px;">Review and approve teacher applications and profile update requests.</p>
            
            <?php 
            $total_pending = $admin_stats['pending_apps'] + $admin_stats['pending_updates'];
            if ($total_pending == 0): ?>
            <div class="empty-state">
                <i class="fas fa-check-circle" style="color: var(--success);"></i>
                <h3>All Caught Up!</h3>
                <p>There are no pending requests at this time.</p>
            </div>
            <?php else: ?>
            
            <!-- Teacher Applications Section -->
            <?php if ($admin_stats['pending_apps'] > 0): ?>
            <div style="margin-bottom: 40px;">
                <h2 style="display: flex; align-items: center; gap: 10px; margin-bottom: 15px;">
                    <i class="fas fa-user-plus" style="color: var(--primary);"></i> 
                    Teacher Applications
                    <span class="notification-badge" style="background: #dc3545; color: white; margin-left: 10px;"><?php echo $admin_stats['pending_apps']; ?></span>
                </h2>
                <?php 
                $applications->data_seek(0); // Reset pointer
                if ($applications->num_rows > 0): ?>
                <div style="overflow-x: auto;">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Applicant</th>
                            <th>Email</th>
                            <th>Bio</th>
                            <th>Calendly</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while($app = $applications->fetch_assoc()): ?>
                        <tr>
                            <td data-label="Applicant">
                                <div style="display: flex; align-items: center; gap: 10px;">
                                    <img src="<?php echo h($app['profile_pic']); ?>" alt="<?php echo h($app['name']); ?>" style="width: 40px; height: 40px; border-radius: 50%; object-fit: cover;" onerror="this.src='<?php echo getAssetPath('images/placeholder-teacher.svg'); ?>'">
                                    <?php echo h($app['name']); ?>
                                </div>
                            </td>
                            <td data-label="Email"><?php echo h($app['email']); ?></td>
                            <td data-label="Bio"><?php echo h(substr($app['bio'] ?? '', 0, 50)); ?><?php echo strlen($app['bio'] ?? '') > 50 ? '...' : ''; ?></td>
                            <td data-label="Calendly">
                                <?php if ($app['calendly_link']): ?>
                                <a href="<?php echo h($app['calendly_link']); ?>" target="_blank" class="btn-outline btn-sm">View</a>
                                <?php else: ?>
                                <span style="color: var(--gray);">Not set</span>
                                <?php endif; ?>
                            </td>
                            <td data-label="Actions">
                                <form action="admin-actions.php" method="POST" style="display: inline-flex; gap: 5px;">
                                    <input type="hidden" name="user_id" value="<?php echo $app['id']; ?>">
                                    <button type="submit" name="action" value="approve_teacher" class="btn-success btn-sm">Approve</button>
                                    <button type="submit" name="action" value="reject_teacher" class="btn-danger btn-sm">Reject</button>
                                </form>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
                </div>
                <?php endif; ?>
            </div>
            <?php endif; ?>
            
            <!-- Profile Update Requests Section -->
            <?php if ($admin_stats['pending_updates'] > 0): ?>
            <div>
                <h2 style="display: flex; align-items: center; gap: 10px; margin-bottom: 15px;">
                    <i class="fas fa-edit" style="color: var(--primary);"></i> 
                    Profile Update Requests
                    <span class="notification-badge" style="background: #dc3545; color: white; margin-left: 10px;"><?php echo $admin_stats['pending_updates']; ?></span>
                </h2>
                <?php 
                $pending_updates->data_seek(0); // Reset pointer
                if ($pending_updates->num_rows > 0): ?>
                <div style="overflow-x: auto;">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>User</th>
                            <th>Name</th>
                            <th>Bio</th>
                            <th>About</th>
                            <th>Picture</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while($up = $pending_updates->fetch_assoc()): ?>
                        <tr>
                            <td><?php echo h($up['user_email']); ?></td>
                            <td><?php echo h($up['name']); ?></td>
                            <td title="<?php echo h($up['bio'] ?? ''); ?>"><?php echo h(substr($up['bio'] ?? '', 0, 30)); ?><?php echo strlen($up['bio'] ?? '') > 30 ? '...' : ''; ?></td>
                            <td title="<?php echo h($up['about_text'] ?? ''); ?>"><?php echo h(substr($up['about_text'] ?? '', 0, 30)); ?><?php echo strlen($up['about_text'] ?? '') > 30 ? '...' : ''; ?></td>
                            <td>
                                <?php if ($up['profile_pic']): ?>
                                <a href="<?php echo h($up['profile_pic']); ?>" target="_blank">
                                    <img src="<?php echo h($up['profile_pic']); ?>" alt="" style="width: 40px; height: 40px; border-radius: 5px; object-fit: cover;">
                                </a>
                                <?php else: ?>
                                <span style="color: var(--gray);">None</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <form action="admin-actions.php" method="POST" style="display: inline-flex; gap: 5px;">
                                    <input type="hidden" name="update_id" value="<?php echo $up['id']; ?>">
                                    <button type="submit" name="action" value="approve_profile" class="btn-success btn-sm">Approve</button>
                                    <button type="submit" name="action" value="reject_profile" class="btn-danger btn-sm">Reject</button>
                                </form>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
                </div>
                <?php endif; ?>
            </div>
            <?php endif; ?>
            
            <?php endif; ?>
        </div>

        <!-- Applications Tab (kept for backward compatibility) -->
        <div id="applications" class="tab-content">
            <h1>Teacher Applications</h1>
            <p style="color: var(--gray); margin-bottom: 20px;">This section has been moved to <a href="#" onclick="switchTab('pending-requests')" style="color: var(--primary);">Pending Requests</a>.</p>
            <?php if ($applications->num_rows > 0): ?>
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Applicant</th>
                        <th>Email</th>
                        <th>Bio</th>
                        <th>Calendly</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    $applications->data_seek(0);
                    while($app = $applications->fetch_assoc()): ?>
                    <tr>
                        <td data-label="Applicant">
                            <div style="display: flex; align-items: center; gap: 10px;">
                                <img src="<?php echo h($app['profile_pic']); ?>" alt="<?php echo h($app['name']); ?>" style="width: 40px; height: 40px; border-radius: 50%; object-fit: cover;" onerror="this.src='<?php echo getAssetPath('images/placeholder-teacher.svg'); ?>'">
                                <?php echo h($app['name']); ?>
                            </div>
                        </td>
                        <td data-label="Email"><?php echo h($app['email']); ?></td>
                        <td data-label="Bio"><?php echo h(substr($app['bio'] ?? '', 0, 50)); ?>...</td>
                        <td data-label="Calendly">
                            <?php if ($app['calendly_link']): ?>
                            <a href="<?php echo h($app['calendly_link']); ?>" target="_blank" class="btn-outline btn-sm">View</a>
                            <?php else: ?>
                            <span style="color: var(--gray);">Not set</span>
                            <?php endif; ?>
                        </td>
                        <td data-label="Actions">
                            <form action="admin-actions.php" method="POST" style="display: inline-flex; gap: 5px;">
                                <input type="hidden" name="user_id" value="<?php echo $app['id']; ?>">
                                <button type="submit" name="action" value="approve_teacher" class="btn-success btn-sm">Approve</button>
                                <button type="submit" name="action" value="reject_teacher" class="btn-danger btn-sm">Reject</button>
                            </form>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
            <?php else: ?>
            <div class="empty-state">
                <i class="fas fa-user-plus"></i>
                <h3>No Pending Applications</h3>
                <p>New teacher applications will appear here.</p>
            </div>
            <?php endif; ?>
        </div>

        <!-- Approvals Tab (kept for backward compatibility) -->
        <div id="approvals" class="tab-content">
            <h1>Profile Update Requests</h1>
            <p style="color: var(--gray); margin-bottom: 20px;">This section has been moved to <a href="#" onclick="switchTab('pending-requests')" style="color: var(--primary);">Pending Requests</a>.</p>
            <?php if ($pending_updates->num_rows > 0): ?>
            <div style="overflow-x: auto;">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>User</th>
                        <th>Name</th>
                        <th>Bio</th>
                        <th>About</th>
                        <th>Picture</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    $pending_updates->data_seek(0);
                    while($up = $pending_updates->fetch_assoc()): ?>
                    <tr>
                        <td><?php echo h($up['user_email']); ?></td>
                        <td><?php echo h($up['name']); ?></td>
                        <td title="<?php echo h($up['bio'] ?? ''); ?>"><?php echo h(substr($up['bio'] ?? '', 0, 30)); ?>...</td>
                        <td title="<?php echo h($up['about_text'] ?? ''); ?>"><?php echo h(substr($up['about_text'] ?? '', 0, 30)); ?>...</td>
                        <td>
                            <?php if ($up['profile_pic']): ?>
                            <a href="<?php echo h($up['profile_pic']); ?>" target="_blank">
                                <img src="<?php echo h($up['profile_pic']); ?>" alt="" style="width: 40px; height: 40px; border-radius: 5px; object-fit: cover;">
                            </a>
                            <?php else: ?>
                            <span style="color: var(--gray);">None</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <form action="admin-actions.php" method="POST" style="display: inline-flex; gap: 5px;">
                                <input type="hidden" name="update_id" value="<?php echo $up['id']; ?>">
                                <button type="submit" name="action" value="approve_profile" class="btn-success btn-sm">Approve</button>
                                <button type="submit" name="action" value="reject_profile" class="btn-danger btn-sm">Reject</button>
                            </form>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
            </div>
            <?php else: ?>
            <div class="empty-state">
                <i class="fas fa-check-circle"></i>
                <h3>No Pending Updates</h3>
                <p>Profile update requests will appear here.</p>
            </div>
            <?php endif; ?>
        </div>

        <!-- Teachers Tab -->
        <div id="teachers" class="tab-content">
            <h1>Teacher Management</h1>
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Teacher</th>
                        <th>Email</th>
                        <th>Rating</th>
                        <th>Students</th>
                        <th>Hours</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    $teachers->data_seek(0);
                    while($t = $teachers->fetch_assoc()): 
                    ?>
                    <tr>
                        <td data-label="Teacher">
                            <div style="display: flex; align-items: center; gap: 10px;">
                                <img src="<?php echo h($t['profile_pic']); ?>" alt="<?php echo h($t['name']); ?>" style="width: 40px; height: 40px; border-radius: 50%; object-fit: cover;" onerror="this.src='<?php echo getAssetPath('images/placeholder-teacher.svg'); ?>'">
                                <?php echo h($t['name']); ?>
                            </div>
                        </td>
                        <td data-label="Email"><?php echo h($t['email']); ?></td>
                        <td data-label="Rating"><?php echo getStarRatingHtml($t['avg_rating'] ?? 0); ?></td>
                        <td data-label="Students"><?php echo $t['student_count'] ?? 0; ?></td>
                        <td data-label="Hours"><?php echo $t['hours_taught'] ?? 0; ?> hrs</td>
                        <td data-label="Actions">
                            <a href="profile.php?id=<?php echo $t['id']; ?>" class="btn-outline btn-sm">View</a>
                            <form action="admin-actions.php" method="POST" style="display: inline;">
                                <input type="hidden" name="user_id" value="<?php echo $t['id']; ?>">
                                <button type="submit" name="action" value="make_student" class="btn-danger btn-sm" onclick="return handleDemoteClick(event, this)">Demote</button>
                            </form>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>

        <!-- Students Tab -->
        <div id="students" class="tab-content">
            <h1>Student Management</h1>
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Student</th>
                        <th>Email</th>
                        <th>Joined</th>
                        <th>Bio</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while($s = $students->fetch_assoc()): ?>
                    <tr>
                        <td data-label="Student">
                            <div style="display: flex; align-items: center; gap: 10px;">
                                <img src="<?php echo h($s['profile_pic']); ?>" alt="<?php echo h($s['name']); ?>" style="width: 40px; height: 40px; border-radius: 50%; object-fit: cover;" onerror="this.src='<?php echo getAssetPath('images/placeholder-teacher.svg'); ?>'">
                                <?php echo h($s['name']); ?>
                            </div>
                        </td>
                        <td data-label="Email"><?php echo h($s['email']); ?></td>
                        <td data-label="Joined"><?php echo date('M d, Y', strtotime($s['reg_date'])); ?></td>
                        <td data-label="Bio"><?php echo h(substr($s['bio'] ?? 'No bio', 0, 40)); ?>...</td>
                        <td data-label="Actions">
                            <form action="admin-actions.php" method="POST" style="display: inline;">
                                <input type="hidden" name="user_id" value="<?php echo $s['id']; ?>">
                                <button type="submit" name="action" value="make_teacher" class="btn-success btn-sm">Promote</button>
                            </form>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>

        <!-- Messages Tab -->
        <div id="messages" class="tab-content">
            <h1><i class="fas fa-comments"></i> Chat with Users</h1>
            <p style="color: #666; margin-bottom: 20px;">Start a conversation with any user or continue existing conversations.</p>
            
            <div style="display: grid; grid-template-columns: 300px 1fr; gap: 20px; height: 600px;">
                <!-- Users List -->
                <div style="background: white; border-radius: 8px; padding: 15px; overflow-y: auto; border: 1px solid #ddd;">
                    <h3 style="margin-top: 0; font-size: 1.1rem; color: #004080;">All Users</h3>
                    <div style="display: flex; flex-direction: column; gap: 10px;">
                        <?php if (count($all_users) > 0): ?>
                            <?php foreach ($all_users as $chat_user): ?>
                                <a href="message_threads.php?user_id=<?php echo $chat_user['id']; ?>" 
                                   style="display: flex; align-items: center; gap: 10px; padding: 12px; background: #f8f9fa; border-radius: 8px; text-decoration: none; color: inherit; transition: all 0.2s;"
                                   onmouseover="this.style.background='#f0f7ff'; this.style.transform='translateX(5px)';"
                                   onmouseout="this.style.background='#f8f9fa'; this.style.transform='translateX(0)';">
                                    <img src="<?php echo h($chat_user['profile_pic'] ?? getAssetPath('images/placeholder-teacher.svg')); ?>" 
                                         alt="" style="width: 45px; height: 45px; border-radius: 50%; object-fit: cover;"
                                         onerror="this.src='<?php echo getAssetPath('images/placeholder-teacher.svg'); ?>'">
                                    <div style="flex: 1; min-width: 0;">
                                        <div style="font-weight: 600; color: #333; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">
                                            <?php echo h($chat_user['name']); ?>
                                        </div>
                                        <div style="font-size: 0.85rem; color: #666;">
                                            <span class="tag <?php echo $chat_user['role']; ?>" style="font-size: 0.75rem; padding: 2px 8px;">
                                                <?php echo ucfirst($chat_user['role']); ?>
                                            </span>
                                        </div>
                                    </div>
                                    <?php if ($chat_user['unread_count'] > 0): ?>
                                        <span style="background: #dc3545; color: white; border-radius: 50%; width: 20px; height: 20px; display: flex; align-items: center; justify-content: center; font-size: 0.75rem; font-weight: bold;">
                                            <?php echo $chat_user['unread_count']; ?>
                                        </span>
                                    <?php endif; ?>
                                </a>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <p style="color: #999; text-align: center; padding: 20px;">No users found</p>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- Chat Area Placeholder -->
                <div style="background: white; border-radius: 8px; padding: 20px; border: 1px solid #ddd; display: flex; align-items: center; justify-content: center; flex-direction: column; color: #999;">
                    <i class="fas fa-comments" style="font-size: 3rem; margin-bottom: 15px; opacity: 0.3;"></i>
                    <p style="font-size: 1.1rem; margin: 0;">Select a user from the list to start chatting</p>
                    <p style="font-size: 0.9rem; margin-top: 10px;">Or click on a user to open the full chat interface</p>
                </div>
            </div>
        </div>

        <!-- Support Tab -->
        <div id="support" class="tab-content">
            <h1>Support Messages</h1>
            
            <div style="margin-bottom: 20px;">
                <label style="margin-right: 15px;">
                    <input type="radio" name="support-filter" value="all" checked onchange="filterSupport('all')"> All
                </label>
                <label style="margin-right: 15px;">
                    <input type="radio" name="support-filter" value="student" onchange="filterSupport('student')"> Students
                </label>
                <label>
                    <input type="radio" name="support-filter" value="teacher" onchange="filterSupport('teacher')"> Teachers
                </label>
            </div>
            
            <?php if ($support_messages->num_rows > 0): ?>
            <table class="data-table">
                <thead>
                    <tr>
                        <th>From</th>
                        <th>Role</th>
                        <th>Subject</th>
                        <th>Date</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody id="supportTableBody">
                    <?php while($sm = $support_messages->fetch_assoc()): ?>
                    <tr class="support-row" data-role="<?php echo $sm['sender_role']; ?>">
                        <td>
                            <div style="display: flex; align-items: center; gap: 10px;">
                                <img src="<?php echo h($sm['profile_pic']); ?>" alt="" style="width: 35px; height: 35px; border-radius: 50%; object-fit: cover;" onerror="this.src='<?php echo getAssetPath('images/placeholder-teacher.svg'); ?>'">
                                <?php echo h($sm['sender_name']); ?>
                            </div>
                        </td>
                        <td>
                            <span class="tag <?php echo $sm['sender_role']; ?>"><?php echo ucfirst($sm['sender_role']); ?></span>
                        </td>
                        <td><strong><?php echo h($sm['subject']); ?></strong></td>
                        <td><?php echo date('M d, Y', strtotime($sm['created_at'])); ?></td>
                        <td>
                            <span class="tag <?php echo $sm['status'] === 'open' ? 'pending' : 'active'; ?>">
                                <?php echo ucfirst($sm['status']); ?>
                            </span>
                        </td>
                        <td>
                            <button onclick="viewMessage(<?php echo $sm['id']; ?>, '<?php echo h(addslashes($sm['sender_name'])); ?>', '<?php echo h(addslashes($sm['subject'])); ?>', '<?php echo h(addslashes($sm['message'])); ?>')" class="btn-outline btn-sm">View</button>
                            <?php if ($sm['status'] === 'open'): ?>
                            <form action="admin-actions.php" method="POST" style="display: inline;">
                                <input type="hidden" name="support_id" value="<?php echo $sm['id']; ?>">
                                <button type="submit" name="action" value="mark_support_read" class="btn-success btn-sm">Mark Read</button>
                            </form>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
            <?php else: ?>
            <div class="empty-state">
                <i class="fas fa-headset"></i>
                <h3>No Support Messages</h3>
                <p>Support requests will appear here.</p>
            </div>
            <?php endif; ?>
        </div>

        <!-- Slot Requests Tab -->
        <div id="slot-requests" class="tab-content">
            <h1>Slot & Group Class Requests</h1>
            
            <?php
            // Fetch slot requests
            $slot_requests = $conn->query("
                SELECT sr.*, 
                       a.name as admin_name, 
                       t.name as teacher_name, 
                       t.email as teacher_email
                FROM admin_slot_requests sr
                JOIN users a ON sr.admin_id = a.id
                JOIN users t ON sr.teacher_id = t.id
                ORDER BY sr.created_at DESC
            ");
            ?>
            
            <div class="card" style="margin-bottom: 20px;">
                <h2><i class="fas fa-plus-circle"></i> Request New Slot or Group Class</h2>
                <form action="admin-actions.php" method="POST" id="slotRequestForm">
                    <input type="hidden" name="action" value="create_slot_request">
                    
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px; margin-bottom: 15px;">
                        <div>
                            <label>Request Type *</label>
                            <select name="request_type" id="requestType" required onchange="toggleRequestFields()">
                                <option value="time_slot">Time Slot</option>
                                <option value="group_class">Group Class</option>
                            </select>
                        </div>
                        <div>
                            <label>Teacher *</label>
                            <select name="teacher_id" required>
                                <option value="">Select Teacher</option>
                                <?php
                                $teacher_list = $conn->query("SELECT id, name FROM users WHERE role = 'teacher' ORDER BY name");
                                while ($t = $teacher_list->fetch_assoc()): ?>
                                    <option value="<?php echo $t['id']; ?>"><?php echo h($t['name']); ?></option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                    </div>
                    
                    <div id="timeSlotFields">
                        <div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 15px; margin-bottom: 15px;">
                            <div>
                                <label>Date *</label>
                                <input type="date" name="requested_date" required>
                            </div>
                            <div>
                                <label>Time *</label>
                                <input type="time" name="requested_time" required>
                            </div>
                            <div>
                                <label>Duration (minutes) *</label>
                                <input type="number" name="duration_minutes" value="60" min="15" step="15" required>
                            </div>
                        </div>
                    </div>
                    
                    <div id="groupClassFields" style="display: none;">
                        <div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 15px; margin-bottom: 15px;">
                            <div>
                                <label>Track *</label>
                                <select name="group_class_track" required>
                                    <option value="">Select Track</option>
                                    <option value="kids">Kids</option>
                                    <option value="adults">Adults</option>
                                    <option value="coding">Coding</option>
                                </select>
                            </div>
                            <div>
                                <label>Date *</label>
                                <input type="date" name="group_class_date" required>
                            </div>
                            <div>
                                <label>Time *</label>
                                <input type="time" name="group_class_time" required>
                            </div>
                        </div>
                    </div>
                    
                    <div>
                        <label>Message (optional)</label>
                        <textarea name="message" rows="3" placeholder="Add any notes or instructions for the teacher..."></textarea>
                    </div>
                    
                    <button type="submit" class="btn-primary" style="margin-top: 15px;">
                        <i class="fas fa-paper-plane"></i> Send Request
                    </button>
                </form>
            </div>
            
            <h2 style="margin-top: 30px;">Pending & Recent Requests</h2>
            <?php if ($slot_requests && $slot_requests->num_rows > 0): ?>
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Type</th>
                        <th>Teacher</th>
                        <th>Date/Time</th>
                        <th>Status</th>
                        <th>Requested</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while($sr = $slot_requests->fetch_assoc()): ?>
                    <tr>
                        <td>
                            <span class="tag <?php echo $sr['request_type'] === 'group_class' ? 'warning' : 'info'; ?>">
                                <?php echo ucfirst(str_replace('_', ' ', $sr['request_type'])); ?>
                            </span>
                        </td>
                        <td><?php echo h($sr['teacher_name']); ?></td>
                        <td>
                            <?php if ($sr['request_type'] === 'time_slot'): ?>
                                <?php echo date('M d, Y', strtotime($sr['requested_date'])); ?> at <?php echo date('g:i A', strtotime($sr['requested_time'])); ?>
                                (<?php echo $sr['duration_minutes']; ?> min)
                            <?php else: ?>
                                <?php echo date('M d, Y', strtotime($sr['group_class_date'])); ?> at <?php echo date('g:i A', strtotime($sr['group_class_time'])); ?>
                                (<?php echo ucfirst($sr['group_class_track']); ?>)
                            <?php endif; ?>
                        </td>
                        <td>
                            <span class="tag <?php 
                                echo $sr['status'] === 'pending' ? 'pending' : 
                                    ($sr['status'] === 'accepted' ? 'active' : 
                                    ($sr['status'] === 'rejected' ? 'danger' : 'success')); 
                            ?>">
                                <?php echo ucfirst($sr['status']); ?>
                            </span>
                        </td>
                        <td><?php echo date('M d, Y g:i A', strtotime($sr['created_at'])); ?></td>
                        <td>
                            <?php if ($sr['status'] === 'pending'): ?>
                                <button onclick="viewSlotRequest(<?php echo $sr['id']; ?>)" class="btn-outline btn-sm">View</button>
                            <?php else: ?>
                                <button onclick="viewSlotRequest(<?php echo $sr['id']; ?>)" class="btn-outline btn-sm">Details</button>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
            <?php else: ?>
            <div class="card">
                <p>No slot requests yet.</p>
            </div>
            <?php endif; ?>
        </div>

        <!-- Reports Tab -->
        <div id="reports" class="tab-content">
            <h1>Reports</h1>
            
            <div class="card">
                <h2><i class="fas fa-file-export"></i> Export Data</h2>
                <div class="quick-actions">
                    <a href="api/export.php?type=students" class="quick-action-btn">
                        <i class="fas fa-users"></i>
                        <span>Export Students</span>
                    </a>
                    <a href="api/export.php?type=teachers" class="quick-action-btn">
                        <i class="fas fa-chalkboard-teacher"></i>
                        <span>Export Teachers</span>
                    </a>
                    <a href="api/export.php?type=bookings" class="quick-action-btn">
                        <i class="fas fa-calendar"></i>
                        <span>Export Bookings</span>
                    </a>
                    <a href="api/export.php?type=earnings" class="quick-action-btn">
                        <i class="fas fa-dollar-sign"></i>
                        <span>Export Earnings</span>
                    </a>
                </div>
            </div>

            <div class="card">
                <h2><i class="fas fa-chart-bar"></i> Summary Statistics</h2>
                <table class="data-table">
                    <tr>
                        <td><strong>Total Students</strong></td>
                        <td><?php echo $admin_stats['students']; ?></td>
                    </tr>
                    <tr>
                        <td><strong>Total Teachers</strong></td>
                        <td><?php echo $admin_stats['teachers']; ?></td>
                    </tr>
                    <tr>
                        <td><strong>Total Bookings</strong></td>
                        <td><?php echo $admin_stats['total_bookings']; ?></td>
                    </tr>
                    <tr>
                        <td><strong>Total Revenue</strong></td>
                        <td><?php echo formatCurrency($total_revenue); ?></td>
                    </tr>
                    <tr>
                        <td><strong>Pending Applications</strong></td>
                        <td><?php echo $admin_stats['pending_apps']; ?></td>
                    </tr>
                    <tr>
                        <td><strong>Open Support Tickets</strong></td>
                        <td><?php echo $unread_support; ?></td>
                    </tr>
                </table>
            </div>
        </div>

        <!-- My Profile Tab -->
        <div id="my-profile" class="tab-content">
            <h1>My Profile</h1>
            <div class="card">
                <form method="POST" enctype="multipart/form-data">
                    <div style="display: flex; gap: 30px; margin-bottom: 25px; align-items: flex-start;">
                        <div style="text-align: center;">
                            <img src="<?php echo h($user['profile_pic']); ?>" alt="Profile" 
                                 style="width: 120px; height: 120px; border-radius: 50%; object-fit: cover; border: 4px solid var(--primary-light);"
                                 onerror="this.src='<?php echo getAssetPath('images/placeholder-teacher.svg'); ?>'">
                            <div style="margin-top: 15px;">
                                <label class="btn-outline btn-sm" style="cursor: pointer;">
                                    <i class="fas fa-camera"></i> Change
                                    <input type="file" name="profile_pic_file" accept="image/*" style="display: none;">
                                </label>
                            </div>
                        </div>
                        <div style="flex: 1;">
                            <div class="profile-grid">
                                <div class="form-group">
                                    <label>Display Name</label>
                                    <input type="text" name="name" value="<?php echo h($user['name']); ?>">
                                </div>
                                <div class="form-group">
                                    <label>Date of Birth</label>
                                    <input type="date" name="dob" value="<?php echo $user['dob']; ?>">
                                </div>
                                <div class="form-group">
                                    <label>Calendly Link</label>
                                    <input type="url" name="calendly" value="<?php echo h($user['calendly_link']); ?>">
                                </div>
                                <div class="form-group">
                                    <label>Backup Email</label>
                                    <input type="email" name="backup_email" value="<?php echo h($user['backup_email'] ?? ''); ?>">
                                </div>
                                <div class="form-group">
                                    <label>Age</label>
                                    <input type="number" name="age" value="<?php echo h($user['age'] ?? ''); ?>">
                                </div>
                                <div class="form-group">
                                    <label>Age Visibility</label>
                                    <select name="age_visibility">
                                        <option value="private" <?php echo ($user['age_visibility'] === 'private') ? 'selected' : ''; ?>>Private</option>
                                        <option value="public" <?php echo ($user['age_visibility'] === 'public') ? 'selected' : ''; ?>>Public</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label>Bio</label>
                        <textarea name="bio" rows="4"><?php echo h($user['bio']); ?></textarea>
                    </div>
                    
                    <button type="submit" name="update_profile" class="btn-primary">
                        <i class="fas fa-save"></i> Save Changes
                    </button>
                </form>
            </div>
        </div>

        <!-- Classroom Tab -->
        <div id="classroom" class="tab-content">
            <h1>Classroom Materials</h1>
            
            <div class="card">
                <h2><i class="fas fa-plus-circle"></i> Add Material</h2>
                <form method="POST">
                    <div class="profile-grid">
                        <div class="form-group">
                            <label>Title</label>
                            <input type="text" name="title" required>
                        </div>
                        <div class="form-group">
                            <label>Type</label>
                            <select name="type">
                                <option value="link">Link / URL</option>
                                <option value="video">Video</option>
                                <option value="file">File</option>
                            </select>
                        </div>
                    </div>
                    <div class="form-group">
                        <label>Content URL</label>
                        <input type="url" name="content_url" placeholder="https://..." required>
                    </div>
                    <button type="submit" name="upload_material" class="btn-primary">
                        <i class="fas fa-plus"></i> Add Material
                    </button>
                </form>
            </div>

            <h2 style="margin-top: 30px;">Current Materials</h2>
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Title</th>
                        <th>Type</th>
                        <th>Link</th>
                        <th>Added</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while($m = $materials->fetch_assoc()): ?>
                    <tr>
                        <td><?php echo h($m['title']); ?></td>
                        <td><span class="tag <?php echo $m['type']; ?>"><?php echo ucfirst($m['type']); ?></span></td>
                        <td><a href="<?php echo h($m['link_url']); ?>" target="_blank" class="btn-outline btn-sm">Open</a></td>
                        <td><?php echo date('M d, Y', strtotime($m['created_at'])); ?></td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>

        <!-- Security Tab -->
        <div id="my-security" class="tab-content">
            <h1>Security Settings</h1>
            <?php include __DIR__ . '/app/Views/components/password-change-form.php'; ?>
        </div>

    </div>
</div>

<!-- Message View Modal -->
<div class="modal-overlay" id="messageModal">
    <div class="modal">
        <div class="modal-header">
            <h3 id="modalSubject">Subject</h3>
            <button class="modal-close" onclick="closeMessageModal()">&times;</button>
        </div>
        <p><strong>From:</strong> <span id="modalSender"></span></p>
        <div style="background: var(--light-gray); padding: 15px; border-radius: 8px; margin-top: 15px; white-space: pre-wrap;" id="modalMessage"></div>
    </div>
</div>

<script>
function toggleRequestFields() {
    const requestType = document.getElementById('requestType').value;
    const timeSlotFields = document.getElementById('timeSlotFields');
    const groupClassFields = document.getElementById('groupClassFields');
    
    if (requestType === 'time_slot') {
        timeSlotFields.style.display = 'block';
        groupClassFields.style.display = 'none';
        // Make time slot fields required
        document.querySelector('[name="requested_date"]').required = true;
        document.querySelector('[name="requested_time"]').required = true;
        document.querySelector('[name="duration_minutes"]').required = true;
        // Make group class fields not required
        document.querySelector('[name="group_class_track"]').required = false;
        document.querySelector('[name="group_class_date"]').required = false;
        document.querySelector('[name="group_class_time"]').required = false;
    } else {
        timeSlotFields.style.display = 'none';
        groupClassFields.style.display = 'block';
        // Make group class fields required
        document.querySelector('[name="group_class_track"]').required = true;
        document.querySelector('[name="group_class_date"]').required = true;
        document.querySelector('[name="group_class_time"]').required = true;
        // Make time slot fields not required
        document.querySelector('[name="requested_date"]').required = false;
        document.querySelector('[name="requested_time"]').required = false;
        document.querySelector('[name="duration_minutes"]').required = false;
    }
}

function viewSlotRequest(id) {
    // TODO: Implement modal or detail view
    alert('Slot request details view - ID: ' + id);
}

function switchTab(id) {
    if (event) event.preventDefault();
    
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
    if (sidebarHeader && id === 'dashboard') {
        sidebarHeader.classList.add('active');
    }
    
    // Scroll to top of main content
    const mainContent = document.querySelector('.main');
    if (mainContent) mainContent.scrollTop = 0;
    
    // Update URL hash without triggering page reload
    if (window.location.hash !== '#' + id) {
        window.history.pushState(null, null, '#' + id);
    }
}

document.addEventListener('DOMContentLoaded', function() {
    const hash = window.location.hash.substring(1);
    if (hash && document.getElementById(hash)) {
        switchTab(hash);
    } else {
        // Default to dashboard if no hash
        const dashboardTab = document.getElementById('dashboard');
        if (dashboardTab) {
            dashboardTab.classList.add('active');
        }
    }
});

// Handle browser back/forward buttons (hashchange event)
window.addEventListener('hashchange', function() {
    const hash = window.location.hash.substring(1);
    if (hash && document.getElementById(hash)) {
        switchTab(hash);
    }
});

function filterSupport(role) {
    document.querySelectorAll('.support-row').forEach(row => {
        if (role === 'all' || row.dataset.role === role) {
            row.style.display = '';
        } else {
            row.style.display = 'none';
        }
    });
}

function viewMessage(id, sender, subject, message) {
    document.getElementById('modalSender').textContent = sender;
    document.getElementById('modalSubject').textContent = subject;
    document.getElementById('modalMessage').textContent = message;
    document.getElementById('messageModal').classList.add('active');
}

function closeMessageModal() {
    document.getElementById('messageModal').classList.remove('active');
}

function toggleMobileSidebar() {
    document.querySelector('.sidebar').classList.toggle('active');
    document.querySelector('.sidebar-overlay').classList.toggle('active');
}

// Handle demote confirmation with toast
async function handleDemoteClick(event, button) {
    event.preventDefault();
    if (typeof toast !== 'undefined') {
        const confirmed = await toast.confirm('Demote this teacher to student?', 'Confirm Demotion');
        if (confirmed) {
            button.closest('form').submit();
        }
        return false;
    } else {
        if (confirm('Demote this teacher to student?')) {
            return true;
        }
        return false;
    }
}
</script>

</body>
</html>
<?php
// End output buffering
ob_end_flush();
?>
