<?php
session_start();
require_once 'db.php';
require_once 'includes/dashboard-functions.php';

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
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
        $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
        $allowed = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
        if (in_array(strtolower($ext), $allowed)) {
            $filename = 'user_' . $admin_id . '_' . time() . '.' . $ext;
            $target_path = 'images/' . $filename;
            if (move_uploaded_file($file['tmp_name'], $target_path)) {
                $profile_pic = $target_path;
            }
        }
    } elseif (!empty($_POST['profile_pic_url'])) {
        $profile_pic = $_POST['profile_pic_url'];
    }

    $stmt = $conn->prepare("UPDATE users SET name = ?, dob = ?, bio = ?, calendly_link = ?, profile_pic = ?, backup_email = ?, age = ?, age_visibility = ? WHERE id = ?");
    $stmt->bind_param("sssssisis", $name, $dob, $bio, $calendly, $profile_pic, $backup_email, $age, $age_visibility, $admin_id);
    $stmt->execute();
    $stmt->close();
    
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
    
    header("Location: admin-dashboard.php#classroom");
    exit();
}

// Fetch data
$pending_updates = $conn->query("SELECT p.*, u.email as user_email FROM pending_updates p JOIN users u ON p.user_id = u.id");
$applications = $conn->query("SELECT * FROM users WHERE application_status='pending'");
$students = $conn->query("SELECT * FROM users WHERE role='student' ORDER BY reg_date DESC");
$teachers = $conn->query("SELECT u.*, 
    (SELECT AVG(rating) FROM reviews WHERE teacher_id = u.id) as avg_rating,
    (SELECT COUNT(*) FROM reviews WHERE teacher_id = u.id) as review_count,
    (SELECT COUNT(DISTINCT student_id) FROM bookings WHERE teacher_id = u.id) as student_count
    FROM users u WHERE u.role='teacher' ORDER BY u.id DESC");
$materials = $conn->query("SELECT * FROM classroom_materials ORDER BY created_at DESC");
$support_messages = $conn->query("
    SELECT sm.*, u.name as sender_name, u.profile_pic, u.role 
    FROM support_messages sm 
    JOIN users u ON sm.sender_id = u.id 
    ORDER BY sm.created_at DESC
");

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

// Engagement metrics
$inactive_students = $conn->query("
    SELECT u.* FROM users u 
    WHERE u.role = 'student' 
    AND u.id NOT IN (SELECT student_id FROM bookings WHERE booking_date > DATE_SUB(NOW(), INTERVAL 30 DAY))
    ORDER BY u.last_active ASC
    LIMIT 10
");

$unread_support = $admin_stats['open_support'];
$active_tab = 'dashboard';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - Staten Academy</title>
    <link rel="stylesheet" href="styles.css">
    <link rel="stylesheet" href="css/dashboard.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body class="dashboard-layout">

<?php include 'includes/dashboard-header.php'; ?>

<div class="content-wrapper">
    <?php 
    // Make admin_stats available to sidebar
    $admin_stats_for_sidebar = $admin_stats;
    include 'includes/dashboard-sidebar.php'; 
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
                            <td>
                                <div style="display: flex; align-items: center; gap: 10px;">
                                    <img src="<?php echo h($t['profile_pic']); ?>" alt="" style="width: 35px; height: 35px; border-radius: 50%; object-fit: cover;" onerror="this.src='images/placeholder-teacher.svg'">
                                    <?php echo h($t['name']); ?>
                                </div>
                            </td>
                            <td><?php echo getStarRatingHtml($t['avg_rating'] ?? 0); ?></td>
                            <td><?php echo $t['student_count'] ?? 0; ?></td>
                            <td><?php echo $t['hours_taught'] ?? 0; ?> hrs</td>
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
                            <td><?php echo h($s['name']); ?></td>
                            <td><?php echo h($s['email']); ?></td>
                            <td><?php echo date('M d, Y', strtotime($s['reg_date'])); ?></td>
                            <td>
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
                            <td>
                                <div style="display: flex; align-items: center; gap: 10px;">
                                    <img src="<?php echo h($app['profile_pic']); ?>" alt="" style="width: 40px; height: 40px; border-radius: 50%; object-fit: cover;" onerror="this.src='images/placeholder-teacher.svg'">
                                    <?php echo h($app['name']); ?>
                                </div>
                            </td>
                            <td><?php echo h($app['email']); ?></td>
                            <td><?php echo h(substr($app['bio'] ?? '', 0, 50)); ?><?php echo strlen($app['bio'] ?? '') > 50 ? '...' : ''; ?></td>
                            <td>
                                <?php if ($app['calendly_link']): ?>
                                <a href="<?php echo h($app['calendly_link']); ?>" target="_blank" class="btn-outline btn-sm">View</a>
                                <?php else: ?>
                                <span style="color: var(--gray);">Not set</span>
                                <?php endif; ?>
                            </td>
                            <td>
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
                        <td>
                            <div style="display: flex; align-items: center; gap: 10px;">
                                <img src="<?php echo h($app['profile_pic']); ?>" alt="" style="width: 40px; height: 40px; border-radius: 50%; object-fit: cover;" onerror="this.src='images/placeholder-teacher.svg'">
                                <?php echo h($app['name']); ?>
                            </div>
                        </td>
                        <td><?php echo h($app['email']); ?></td>
                        <td><?php echo h(substr($app['bio'] ?? '', 0, 50)); ?>...</td>
                        <td>
                            <?php if ($app['calendly_link']): ?>
                            <a href="<?php echo h($app['calendly_link']); ?>" target="_blank" class="btn-outline btn-sm">View</a>
                            <?php else: ?>
                            <span style="color: var(--gray);">Not set</span>
                            <?php endif; ?>
                        </td>
                        <td>
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
                        <td>
                            <div style="display: flex; align-items: center; gap: 10px;">
                                <img src="<?php echo h($t['profile_pic']); ?>" alt="" style="width: 40px; height: 40px; border-radius: 50%; object-fit: cover;" onerror="this.src='images/placeholder-teacher.svg'">
                                <?php echo h($t['name']); ?>
                            </div>
                        </td>
                        <td><?php echo h($t['email']); ?></td>
                        <td><?php echo getStarRatingHtml($t['avg_rating'] ?? 0); ?></td>
                        <td><?php echo $t['student_count'] ?? 0; ?></td>
                        <td><?php echo $t['hours_taught'] ?? 0; ?> hrs</td>
                        <td>
                            <a href="profile.php?id=<?php echo $t['id']; ?>" class="btn-outline btn-sm">View</a>
                            <form action="admin-actions.php" method="POST" style="display: inline;">
                                <input type="hidden" name="user_id" value="<?php echo $t['id']; ?>">
                                <button type="submit" name="action" value="make_student" class="btn-danger btn-sm" onclick="return confirm('Demote this teacher to student?')">Demote</button>
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
                        <td>
                            <div style="display: flex; align-items: center; gap: 10px;">
                                <img src="<?php echo h($s['profile_pic']); ?>" alt="" style="width: 40px; height: 40px; border-radius: 50%; object-fit: cover;" onerror="this.src='images/placeholder-teacher.svg'">
                                <?php echo h($s['name']); ?>
                            </div>
                        </td>
                        <td><?php echo h($s['email']); ?></td>
                        <td><?php echo date('M d, Y', strtotime($s['reg_date'])); ?></td>
                        <td><?php echo h(substr($s['bio'] ?? 'No bio', 0, 40)); ?>...</td>
                        <td>
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
                                <img src="<?php echo h($sm['profile_pic']); ?>" alt="" style="width: 35px; height: 35px; border-radius: 50%; object-fit: cover;" onerror="this.src='images/placeholder-teacher.svg'">
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
                                 onerror="this.src='images/placeholder-teacher.svg'">
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
            <?php include 'includes/password-change-form.php'; ?>
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
function switchTab(id) {
    document.querySelectorAll('.tab-content').forEach(el => el.classList.remove('active'));
    document.getElementById(id).classList.add('active');
    
    document.querySelectorAll('.sidebar-menu a').forEach(el => el.classList.remove('active'));
    const activeLink = document.querySelector(`.sidebar-menu a[onclick*="${id}"]`);
    if (activeLink) activeLink.classList.add('active');
    
    window.location.hash = id;
}

document.addEventListener('DOMContentLoaded', function() {
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
</script>

</body>
</html>
