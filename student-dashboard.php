<?php
session_start();
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/app/Views/components/dashboard-functions.php';
require_once __DIR__ . '/google-calendar-config.php';

if (!isset($_SESSION['user_id']) || ($_SESSION['user_role'] !== 'student' && $_SESSION['user_role'] !== 'new_student')) {
    header("Location: login.php");
    exit();
}

$student_id = $_SESSION['user_id'];
$user = getUserById($conn, $student_id);
$user_role = $_SESSION['user_role']; // Keep actual role (student or new_student)

// Initialize Google Calendar API
$api = new GoogleCalendarAPI($conn);
$has_calendar = !empty($user['google_calendar_token']);

// Check for calendar connection success message
$calendar_connected = isset($_GET['calendar']) && $_GET['calendar'] === 'connected';

// Handle Profile Update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
    $bio = $_POST['bio'];
    $backup_email = filter_input(INPUT_POST, 'backup_email', FILTER_SANITIZE_EMAIL);
    $age = !empty($_POST['age']) ? (int)$_POST['age'] : NULL;
    $age_visibility = $_POST['age_visibility'] ?? 'private';
    $profile_pic = $user['profile_pic'];

    if (isset($_FILES['profile_pic_file']) && $_FILES['profile_pic_file']['error'] === UPLOAD_ERR_OK) {
        $file = $_FILES['profile_pic_file'];
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $allowed = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
        if (in_array($ext, $allowed) && $file['size'] <= 5 * 1024 * 1024) {
            $filename = 'student_' . $student_id . '_' . time() . '.' . $ext;
            
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
    }

    $stmt = $conn->prepare("UPDATE users SET bio = ?, profile_pic = ?, backup_email = ?, age = ?, age_visibility = ? WHERE id = ?");
    $stmt->bind_param("sssisi", $bio, $profile_pic, $backup_email, $age, $age_visibility, $student_id);
    $stmt->execute();
    $stmt->close();
    
    header("Location: student-dashboard.php#profile");
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
        $stmt->bind_param("si", $hashed_password, $student_id);
        $stmt->execute();
        $stmt->close();
        $password_error = 'password_changed';
    }
}

// Handle Goal Creation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_goal'])) {
    $goal_text = trim($_POST['goal_text']);
    $goal_type = $_POST['goal_type'];
    $target_value = (int)$_POST['target_value'];
    $deadline = !empty($_POST['deadline']) ? $_POST['deadline'] : null;
    
    $stmt = $conn->prepare("INSERT INTO learning_goals (student_id, goal_text, goal_type, target_value, deadline) VALUES (?, ?, ?, ?, ?)");
    if ($stmt) {
        $stmt->bind_param("issis", $student_id, $goal_text, $goal_type, $target_value, $deadline);
        $stmt->execute();
        $stmt->close();
    }
    header("Location: student-dashboard.php#goals");
    exit();
}

// Handle Review Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_review'])) {
    $teacher_id = (int)$_POST['teacher_id'];
    $rating = (int)$_POST['rating'];
    $review_text = trim($_POST['review_text']);
    
    $stmt = $conn->prepare("INSERT INTO reviews (teacher_id, student_id, rating, review_text) VALUES (?, ?, ?, ?) ON DUPLICATE KEY UPDATE rating = VALUES(rating), review_text = VALUES(review_text)");
    if ($stmt) {
        $stmt->bind_param("iiis", $teacher_id, $student_id, $rating, $review_text);
        $stmt->execute();
        $stmt->close();
    }
    header("Location: student-dashboard.php#reviews");
    exit();
}

// Fetch Student Stats
$stats = getStudentStats($conn, $student_id);

// Fetch Bookings with Teacher Info
$stmt = $conn->prepare("
    SELECT b.*, u.name as teacher_name, u.profile_pic as teacher_pic, u.bio as teacher_bio
    FROM bookings b 
    JOIN users u ON b.teacher_id = u.id 
    WHERE b.student_id = ? 
    ORDER BY b.booking_date DESC
");
$stmt->bind_param("i", $student_id);
$stmt->execute();
$bookings = $stmt->get_result();
$stmt->close();

// Fetch Upcoming Lessons (from lessons table) for join classroom functionality
$upcoming_lessons = [];
$stmt = $conn->prepare("
    SELECT l.*, u.name as teacher_name, u.profile_pic as teacher_pic
    FROM lessons l
    JOIN users u ON l.teacher_id = u.id
    WHERE l.student_id = ? AND l.lesson_date >= CURDATE() AND l.status = 'scheduled'
    ORDER BY l.lesson_date ASC, l.start_time ASC
    LIMIT 10
");
$stmt->bind_param("i", $student_id);
$stmt->execute();
$lessons_result = $stmt->get_result();
while ($row = $lessons_result->fetch_assoc()) {
    $upcoming_lessons[] = $row;
}
$stmt->close();

// Fetch Favorite Teachers
$favorites = [];
$stmt = $conn->prepare("SELECT ft.teacher_id, u.name, u.profile_pic, u.bio, u.avg_rating, u.review_count 
    FROM favorite_teachers ft 
    JOIN users u ON ft.teacher_id = u.id 
    WHERE ft.student_id = ?");
if ($stmt) {
    $stmt->bind_param("i", $student_id);
    $stmt->execute();
    $fav_result = $stmt->get_result();
    while ($row = $fav_result->fetch_assoc()) {
        $favorites[] = $row;
    }
    $stmt->close();
}

// Fetch Teachers from bookings for "My Teachers" tab
$my_teachers = [];
$stmt = $conn->prepare("
    SELECT DISTINCT u.id, u.name, u.profile_pic, u.bio, u.avg_rating, u.review_count,
           (SELECT COUNT(*) FROM bookings WHERE student_id = ? AND teacher_id = u.id) as lesson_count
    FROM users u 
    JOIN bookings b ON u.id = b.teacher_id 
    WHERE b.student_id = ?
");
if ($stmt) {
    $stmt->bind_param("ii", $student_id, $student_id);
    $stmt->execute();
    $teachers_result = $stmt->get_result();
    while ($row = $teachers_result->fetch_assoc()) {
        $my_teachers[] = $row;
    }
    $stmt->close();
}

// Fetch Learning Goals
$goals = [];
$stmt = $conn->prepare("SELECT * FROM learning_goals WHERE student_id = ? ORDER BY completed ASC, deadline ASC");
if ($stmt) {
    $stmt->bind_param("i", $student_id);
    $stmt->execute();
    $goals_result = $stmt->get_result();
    while ($row = $goals_result->fetch_assoc()) {
        $goals[] = $row;
    }
    $stmt->close();
}

// Fetch Assignments
$assignments = [];
$stmt = $conn->prepare("
    SELECT a.*, u.name as teacher_name 
    FROM assignments a 
    JOIN users u ON a.teacher_id = u.id 
    WHERE a.student_id = ? 
    ORDER BY CASE WHEN a.status = 'pending' THEN 0 ELSE 1 END, a.due_date ASC
");
if ($stmt) {
    $stmt->bind_param("i", $student_id);
    $stmt->execute();
    $assign_result = $stmt->get_result();
    while ($row = $assign_result->fetch_assoc()) {
        $assignments[] = $row;
    }
    $stmt->close();
}

// Fetch Student's Reviews
$my_reviews = [];
$stmt = $conn->prepare("
    SELECT r.*, u.name as teacher_name, u.profile_pic as teacher_pic
    FROM reviews r
    JOIN users u ON r.teacher_id = u.id
    WHERE r.student_id = ?
    ORDER BY r.created_at DESC
");
if ($stmt) {
    $stmt->bind_param("i", $student_id);
    $stmt->execute();
    $reviews_result = $stmt->get_result();
    while ($row = $reviews_result->fetch_assoc()) {
        $my_reviews[] = $row;
    }
    $stmt->close();
}

// Get teachers available to review (booked but not reviewed yet)
$reviewable_teachers = [];
$stmt = $conn->prepare("
    SELECT DISTINCT u.id, u.name, u.profile_pic
    FROM users u
    JOIN bookings b ON u.id = b.teacher_id
    WHERE b.student_id = ?
    AND u.id NOT IN (SELECT teacher_id FROM reviews WHERE student_id = ?)
");
if ($stmt) {
    $stmt->bind_param("ii", $student_id, $student_id);
    $stmt->execute();
    $reviewable_result = $stmt->get_result();
    while ($row = $reviewable_result->fetch_assoc()) {
        $reviewable_teachers[] = $row;
    }
    $stmt->close();
}

$active_tab = 'overview';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=5.0, user-scalable=yes">
    <meta name="theme-color" content="#004080">
    <meta name="mobile-web-app-capable" content="yes">
    <title>Student Dashboard - Staten Academy</title>
    <link rel="stylesheet" href="<?php echo getAssetPath('styles.css'); ?>">
    <link rel="stylesheet" href="<?php echo getAssetPath('css/dashboard.css'); ?>">
    <link rel="stylesheet" href="<?php echo getAssetPath('css/mobile.css'); ?>">
    <!-- MODERN SHADOWS - To disable, comment out the line below -->
    <link rel="stylesheet" href="<?php echo getAssetPath('css/modern-shadows.css'); ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <script src="<?php echo getAssetPath('js/toast.js'); ?>" defer></script>
</head>
<body class="dashboard-layout">

<?php include __DIR__ . '/app/Views/components/dashboard-header.php'; ?>

<div class="content-wrapper">
    <?php include __DIR__ . '/app/Views/components/dashboard-sidebar.php'; ?>

    <div class="main">
        
        <!-- Overview Tab -->
        <div id="overview" class="tab-content active">
            <h1>Welcome back, <?php echo h($user['name']); ?>! 👋</h1>
            
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-icon"><i class="fas fa-book-reader"></i></div>
                    <div class="stat-info">
                        <h3><?php echo $stats['total_lessons']; ?></h3>
                        <p>Total Lessons</p>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon success"><i class="fas fa-clock"></i></div>
                    <div class="stat-info">
                        <h3><?php echo $stats['hours_learned']; ?></h3>
                        <p>Hours Learned</p>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon info"><i class="fas fa-chalkboard-teacher"></i></div>
                    <div class="stat-info">
                        <h3><?php echo $stats['unique_teachers']; ?></h3>
                        <p>Teachers</p>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon warning"><i class="fas fa-tasks"></i></div>
                    <div class="stat-info">
                        <h3><?php echo $stats['pending_assignments']; ?></h3>
                        <p>Pending Homework</p>
                    </div>
                </div>
            </div>

            <div class="card">
                <h2><i class="fas fa-bolt"></i> Quick Actions</h2>
                <div class="quick-actions">
                    <a href="schedule.php" class="quick-action-btn">
                        <i class="fas fa-calendar-plus"></i>
                        <span>Book Lesson</span>
                    </a>
                    <a href="message_threads.php" class="quick-action-btn">
                        <i class="fas fa-comments"></i>
                        <span>Messages</span>
                    </a>
                    <a href="classroom.php" class="quick-action-btn">
                        <i class="fas fa-book-open"></i>
                        <span>Classroom</span>
                    </a>
                    <a href="#" onclick="switchTab('goals')" class="quick-action-btn">
                        <i class="fas fa-bullseye"></i>
                        <span>Set Goal</span>
                    </a>
                    <?php
                    // Check if user has pending or no teacher application
                    $app_check = $conn->prepare("SELECT application_status FROM users WHERE id = ?");
                    $app_check->bind_param("i", $student_id);
                    $app_check->execute();
                    $app_result = $app_check->get_result();
                    $app_status = $app_result->fetch_assoc()['application_status'] ?? 'none';
                    $app_check->close();
                    if ($app_status === 'none' || $app_status === 'rejected'): ?>
                    <a href="apply-teacher.php" class="quick-action-btn" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);">
                        <i class="fas fa-chalkboard-teacher"></i>
                        <span>Apply to Teach</span>
                    </a>
                    <?php endif; ?>
                </div>
            </div>

            <?php if (count($goals) > 0): ?>
            <div class="card">
                <h2><i class="fas fa-bullseye"></i> Active Goals</h2>
                <?php foreach (array_slice($goals, 0, 3) as $goal): ?>
                    <?php if (!$goal['completed']): ?>
                    <div class="goal-card">
                        <div class="goal-header">
                            <span class="goal-title"><?php echo h($goal['goal_text']); ?></span>
                            <span class="goal-badge"><?php echo ucfirst($goal['goal_type']); ?></span>
                        </div>
                        <?php echo getProgressBarHtml($goal['current_value'], $goal['target_value']); ?>
                    </div>
                    <?php endif; ?>
                <?php endforeach; ?>
                <a href="#" onclick="switchTab('goals')" style="color: var(--primary); text-decoration: none;">View all goals →</a>
            </div>
            <?php endif; ?>

            <?php if (count($upcoming_lessons) > 0): ?>
            <div class="card">
                <h2><i class="fas fa-calendar-check"></i> Upcoming Lessons</h2>
                <?php foreach (array_slice($upcoming_lessons, 0, 5) as $lesson): ?>
                    <?php
                    $lessonDateTime = strtotime($lesson['lesson_date'] . ' ' . $lesson['start_time']);
                    $canJoin = $lessonDateTime <= (time() + 3600); // Can join 1 hour before lesson
                    $isPast = $lessonDateTime < time();
                    ?>
                    <div class="lesson-item" style="display: flex; justify-content: space-between; align-items: center; padding: 15px; background: #f8f9fa; border-radius: 8px; margin-bottom: 10px; <?php echo $isPast ? 'opacity: 0.6;' : ''; ?>">
                        <div style="flex: 1;">
                            <strong><?php echo h($lesson['teacher_name']); ?></strong>
                            <div style="font-size: 0.85rem; color: var(--gray); margin-top: 5px;">
                                <i class="fas fa-calendar"></i> <?php echo date('M d, Y', strtotime($lesson['lesson_date'])); ?>
                                <i class="fas fa-clock" style="margin-left: 15px;"></i> <?php echo date('H:i', strtotime($lesson['start_time'])); ?> - <?php echo date('H:i', strtotime($lesson['end_time'])); ?>
                                <?php if ($canJoin && !$isPast): ?>
                                    <span style="color: #28a745; margin-left: 15px;"><i class="fas fa-circle" style="font-size: 0.6rem;"></i> Join now</span>
                                <?php elseif (!$isPast): ?>
                                    <span style="color: #6c757d; margin-left: 15px;">Starts in <?php echo round(($lessonDateTime - time()) / 3600, 1); ?> hours</span>
                                <?php endif; ?>
                            </div>
                        </div>
                        <a href="classroom.php?lessonId=<?php echo $lesson['id']; ?>" 
                           class="btn <?php echo $canJoin ? 'btn-primary' : 'btn-outline'; ?>" 
                           style="margin-left: 15px; white-space: nowrap;"
                           title="Join Classroom">
                            <i class="fas fa-video"></i> <?php echo $canJoin ? 'Join Now' : 'Join'; ?>
                        </a>
                    </div>
                <?php endforeach; ?>
                <a href="schedule.php" style="color: var(--primary); text-decoration: none; display: block; margin-top: 10px;">View all lessons →</a>
            </div>
            <?php endif; ?>

            <?php if (count($assignments) > 0): ?>
            <div class="card">
                <h2><i class="fas fa-tasks"></i> Upcoming Homework</h2>
                <?php foreach (array_slice($assignments, 0, 3) as $assignment): ?>
                    <?php if ($assignment['status'] === 'pending'): ?>
                    <div class="assignment-item <?php echo ($assignment['due_date'] && strtotime($assignment['due_date']) < time()) ? 'overdue' : ''; ?>">
                        <div style="flex: 1;">
                            <strong><?php echo h($assignment['title']); ?></strong>
                            <div style="font-size: 0.85rem; color: var(--gray);">
                                From: <?php echo h($assignment['teacher_name']); ?>
                                <?php if ($assignment['due_date']): ?>
                                    • Due: <?php echo date('M d, Y', strtotime($assignment['due_date'])); ?>
                                <?php endif; ?>
                            </div>
                        </div>
                        <span class="assignment-status status-<?php echo $assignment['status']; ?>">
                            <?php echo ucfirst($assignment['status']); ?>
                        </span>
                    </div>
                    <?php endif; ?>
                <?php endforeach; ?>
                <a href="#" onclick="switchTab('homework')" style="color: var(--primary); text-decoration: none;">View all homework →</a>
            </div>
            <?php endif; ?>
        </div>

        <!-- Profile Tab -->
        <div id="profile" class="tab-content">
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
                                    <i class="fas fa-camera"></i> Change Photo
                                    <input type="file" name="profile_pic_file" accept="image/*" style="display: none;" onchange="this.form.querySelector('.photo-preview').textContent = this.files[0]?.name || ''">
                                </label>
                                <div class="photo-preview" style="font-size: 0.8rem; color: var(--gray); margin-top: 5px;"></div>
                            </div>
                        </div>
                        <div style="flex: 1;">
                            <div class="form-group">
                                <label>Name</label>
                                <input type="text" value="<?php echo h($user['name']); ?>" disabled style="background: var(--light-gray);">
                            </div>
                            <div class="form-group">
                                <label>Email</label>
                                <input type="email" value="<?php echo h($user['email']); ?>" disabled style="background: var(--light-gray);">
                            </div>
                            <div class="form-group">
                                <label>Backup Email (Optional)</label>
                                <input type="email" name="backup_email" value="<?php echo h($user['backup_email'] ?? ''); ?>" placeholder="backup@example.com">
                            </div>
                        </div>
                    </div>
                    
                    <div class="profile-grid">
                        <div class="form-group">
                            <label>Age (Optional)</label>
                            <input type="number" name="age" min="1" max="150" value="<?php echo h($user['age'] ?? ''); ?>" placeholder="Your age">
                        </div>
                        <div class="form-group">
                            <label>Age Visibility</label>
                            <select name="age_visibility">
                                <option value="private" <?php echo ($user['age_visibility'] === 'private') ? 'selected' : ''; ?>>Private</option>
                                <option value="public" <?php echo ($user['age_visibility'] === 'public') ? 'selected' : ''; ?>>Public</option>
                            </select>
                        </div>
                    </div>

                    <div class="form-group">
                        <label>Bio (Optional)</label>
                        <textarea name="bio" rows="4" placeholder="Tell us about yourself..."><?php echo h($user['bio'] ?? ''); ?></textarea>
                    </div>

                    <button type="submit" name="update_profile" class="btn-primary">
                        <i class="fas fa-save"></i> Save Changes
                    </button>
                </form>
            </div>

            <!-- Google Calendar Connection -->
            <div class="card" style="margin-top: 20px;">
                <h2><i class="fab fa-google"></i> Google Calendar Integration</h2>
                <?php if ($calendar_connected): ?>
                    <div style="background: #d4edda; color: #155724; padding: 15px; border-radius: 5px; border-left: 4px solid #28a745; margin-bottom: 15px;">
                        <i class="fas fa-check-circle"></i> <strong>Google Calendar connected successfully!</strong>
                    </div>
                <?php endif; ?>
                <?php if ($has_calendar): ?>
                    <div style="background: #d4edda; color: #155724; padding: 15px; border-radius: 5px; border-left: 4px solid #28a745; margin-bottom: 15px;">
                        <i class="fas fa-check-circle"></i> <strong>Google Calendar Connected</strong>
                        <p style="margin: 10px 0 0 0; font-size: 14px;">Your booked lessons will automatically sync to your Google Calendar.</p>
                    </div>
                <?php else: ?>
                    <div style="background: #f0f7ff; padding: 20px; border-radius: 8px; border: 2px solid #0b6cf5; text-align: center;">
                        <p style="margin: 0 0 15px 0;">Connect your Google Calendar to automatically sync your booked lessons.</p>
                        <a href="<?php echo $api->getAuthUrl($student_id); ?>" style="display: inline-block; background: #db4437; color: white; padding: 12px 24px; text-decoration: none; border-radius: 4px; font-weight: bold;">
                            <i class="fab fa-google"></i> Connect Google Calendar
                        </a>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- My Teachers Tab -->
        <div id="teachers" class="tab-content">
            <h1>My Teachers</h1>
            
            <?php if (count($my_teachers) > 0): ?>
                <?php foreach ($my_teachers as $teacher): ?>
                <div class="teacher-item">
                    <img src="<?php echo h($teacher['profile_pic']); ?>" alt="" class="teacher-pic" onerror="this.src='<?php echo getAssetPath('images/placeholder-teacher.svg'); ?>'">
                    <div style="flex: 1;">
                        <strong><?php echo h($teacher['name']); ?></strong>
                        <div style="font-size: 0.85rem; color: var(--gray);">
                            <?php echo getStarRatingHtml($teacher['avg_rating'] ?? 0); ?>
                            <span style="margin-left: 10px;"><?php echo $teacher['lesson_count']; ?> lessons</span>
                        </div>
                        <p style="margin: 8px 0 0; font-size: 0.9rem; color: #555;">
                            <?php echo h(substr($teacher['bio'] ?? 'No bio available', 0, 100)); ?>...
                        </p>
                    </div>
                    <div style="display: flex; gap: 10px; align-items: center;">
                        <button class="favorite-btn <?php echo isTeacherFavorite($conn, $student_id, $teacher['id']) ? 'active' : ''; ?>" 
                                onclick="toggleFavorite(<?php echo $teacher['id']; ?>, this)" title="Add to favorites">
                            <i class="fas fa-heart"></i>
                        </button>
                        <a href="profile.php?id=<?php echo $teacher['id']; ?>" class="btn-outline btn-sm">View Profile</a>
                        <a href="message_threads.php?to=<?php echo $teacher['id']; ?>" class="btn-primary btn-sm">Message</a>
                    </div>
                </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="empty-state">
                    <i class="fas fa-chalkboard-teacher"></i>
                    <h3>No Teachers Yet</h3>
                    <p>Book your first lesson to start learning!</p>
                    <a href="schedule.php" class="btn-primary">Browse Teachers</a>
                </div>
            <?php endif; ?>

            <?php if (count($favorites) > 0): ?>
            <h2 style="margin-top: 40px;"><i class="fas fa-heart" style="color: #ff6b6b;"></i> Favorite Teachers</h2>
            <?php foreach ($favorites as $fav): ?>
            <div class="teacher-item">
                <img src="<?php echo h($fav['profile_pic']); ?>" alt="" class="teacher-pic" onerror="this.src='<?php echo getAssetPath('images/placeholder-teacher.svg'); ?>'">
                <div style="flex: 1;">
                    <strong><?php echo h($fav['name']); ?></strong>
                    <div style="font-size: 0.85rem; color: var(--gray);">
                        <?php echo getStarRatingHtml($fav['avg_rating'] ?? 0); ?>
                    </div>
                </div>
                <a href="schedule.php?teacher=<?php echo $fav['teacher_id']; ?>" class="btn-primary btn-sm">Book Lesson</a>
            </div>
            <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <!-- Bookings Tab -->
        <div id="bookings" class="tab-content">
            <h1>My Lessons</h1>
            <div class="card">
                <?php if (count($upcoming_lessons) > 0): ?>
                    <h2 style="margin-bottom: 20px;"><i class="fas fa-calendar-check"></i> Upcoming Lessons</h2>
                    <?php foreach ($upcoming_lessons as $lesson): ?>
                        <?php
                        $lessonDateTime = strtotime($lesson['lesson_date'] . ' ' . $lesson['start_time']);
                        $canJoin = $lessonDateTime <= (time() + 3600); // Can join 1 hour before lesson
                        $isPast = $lessonDateTime < time();
                        ?>
                        <div class="booking-item" style="<?php echo $isPast ? 'opacity: 0.6;' : ''; ?>">
                            <img src="<?php echo h($lesson['teacher_pic']); ?>" alt="" class="booking-pic" onerror="this.src='<?php echo getAssetPath('images/placeholder-teacher.svg'); ?>'">
                            <div style="flex: 1;">
                                <strong><?php echo h($lesson['teacher_name']); ?></strong>
                                <div style="font-size: 0.85rem; color: var(--gray);">
                                    <i class="fas fa-calendar"></i> <?php echo date('l, F d, Y', strtotime($lesson['lesson_date'])); ?>
                                    <i class="fas fa-clock" style="margin-left: 15px;"></i> <?php echo date('H:i', strtotime($lesson['start_time'])); ?> - <?php echo date('H:i', strtotime($lesson['end_time'])); ?>
                                    <?php if ($canJoin && !$isPast): ?>
                                        <span style="color: #28a745; margin-left: 15px;"><i class="fas fa-circle" style="font-size: 0.6rem;"></i> Join now</span>
                                    <?php elseif (!$isPast): ?>
                                        <span style="color: #6c757d; margin-left: 15px;">Starts in <?php echo round(($lessonDateTime - time()) / 3600, 1); ?> hours</span>
                                    <?php else: ?>
                                        <span style="color: #dc3545; margin-left: 15px;"><i class="fas fa-clock"></i> Past lesson</span>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <div style="display: flex; gap: 10px; align-items: center;">
                                <a href="classroom.php?lessonId=<?php echo $lesson['id']; ?>" 
                                   class="btn <?php echo $canJoin ? 'btn-primary' : 'btn-outline'; ?>" 
                                   style="white-space: nowrap;"
                                   title="Join Classroom">
                                    <i class="fas fa-video"></i> <?php echo $canJoin ? 'Join Now' : 'Join'; ?>
                                </a>
                                <a href="profile.php?id=<?php echo $lesson['teacher_id']; ?>" class="btn-outline btn-sm">View Teacher</a>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php elseif ($bookings->num_rows > 0): ?>
                    <p style="color: var(--gray); margin-bottom: 20px;">
                        You have <?php echo $bookings->num_rows; ?> lesson(s) booked.
                    </p>
                    <?php while($booking = $bookings->fetch_assoc()): ?>
                        <div class="booking-item">
                            <img src="<?php echo h($booking['teacher_pic']); ?>" alt="" class="booking-pic" onerror="this.src='<?php echo getAssetPath('images/placeholder-teacher.svg'); ?>'">
                            <div style="flex: 1;">
                                <strong><?php echo h($booking['teacher_name']); ?></strong>
                                <div style="font-size: 0.85rem; color: var(--gray);">
                                    <?php echo date('l, F d, Y', strtotime($booking['booking_date'])); ?>
                                </div>
                            </div>
                            <a href="profile.php?id=<?php echo $booking['teacher_id']; ?>" class="btn-outline btn-sm">View Teacher</a>
                        </div>
                    <?php endwhile; ?>
                <?php else: ?>
                    <div class="empty-state">
                        <i class="fas fa-calendar-alt"></i>
                        <h3>No Lessons Yet</h3>
                        <p>Book your first lesson to start your learning journey!</p>
                        <a href="schedule.php" class="btn-primary">Book a Lesson</a>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Goals Tab -->
        <div id="goals" class="tab-content">
            <h1>Learning Goals</h1>
            
            <div class="card">
                <h2><i class="fas fa-plus-circle"></i> Create New Goal</h2>
                <form method="POST" style="display: flex; gap: 15px; flex-wrap: wrap; align-items: flex-end;">
                    <div class="form-group" style="flex: 2; min-width: 200px; margin: 0;">
                        <label>Goal Description</label>
                        <input type="text" name="goal_text" placeholder="e.g., Complete 10 lessons this month" required>
                    </div>
                    <div class="form-group" style="flex: 1; min-width: 120px; margin: 0;">
                        <label>Type</label>
                        <select name="goal_type">
                            <option value="lessons">Lessons</option>
                            <option value="hours">Hours</option>
                            <option value="custom">Custom</option>
                        </select>
                    </div>
                    <div class="form-group" style="width: 100px; margin: 0;">
                        <label>Target</label>
                        <input type="number" name="target_value" value="10" min="1" required>
                    </div>
                    <div class="form-group" style="flex: 1; min-width: 150px; margin: 0;">
                        <label>Deadline (Optional)</label>
                        <input type="date" name="deadline">
                    </div>
                    <button type="submit" name="create_goal" class="btn-primary" style="height: 46px;">
                        <i class="fas fa-plus"></i> Add Goal
                    </button>
                </form>
            </div>

            <?php if (count($goals) > 0): ?>
                <h2 style="margin-top: 30px;">Your Goals</h2>
                <?php foreach ($goals as $goal): ?>
                <div class="goal-card <?php echo $goal['completed'] ? 'completed' : ''; ?>">
                    <div class="goal-header">
                        <span class="goal-title">
                            <?php if ($goal['completed']): ?>
                                <i class="fas fa-check-circle" style="color: var(--success);"></i>
                            <?php endif; ?>
                            <?php echo h($goal['goal_text']); ?>
                        </span>
                        <span class="goal-badge"><?php echo ucfirst($goal['goal_type']); ?></span>
                    </div>
                    <?php echo getProgressBarHtml($goal['current_value'], $goal['target_value'], $goal['completed'] ? '#28a745' : '#0b6cf5'); ?>
                    <?php if ($goal['deadline']): ?>
                        <div style="font-size: 0.8rem; color: var(--gray); margin-top: 8px;">
                            <i class="fas fa-calendar"></i> Deadline: <?php echo date('M d, Y', strtotime($goal['deadline'])); ?>
                        </div>
                    <?php endif; ?>
                </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="empty-state">
                    <i class="fas fa-bullseye"></i>
                    <h3>No Goals Yet</h3>
                    <p>Set your first learning goal to stay motivated!</p>
                </div>
            <?php endif; ?>
        </div>

        <!-- Homework Tab -->
        <div id="homework" class="tab-content">
            <h1>Homework & Assignments</h1>
            
            <?php if (count($assignments) > 0): ?>
                <?php foreach ($assignments as $assignment): ?>
                <div class="card assignment-item <?php echo ($assignment['due_date'] && strtotime($assignment['due_date']) < time() && $assignment['status'] === 'pending') ? 'overdue' : ''; ?>" style="flex-direction: column; align-items: stretch;">
                    <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 15px;">
                        <div>
                            <h3 style="margin: 0; border: none; padding: 0;"><?php echo h($assignment['title']); ?></h3>
                            <div style="font-size: 0.85rem; color: var(--gray); margin-top: 5px;">
                                From: <?php echo h($assignment['teacher_name']); ?>
                                <?php if ($assignment['due_date']): ?>
                                    • Due: <?php echo date('M d, Y', strtotime($assignment['due_date'])); ?>
                                <?php endif; ?>
                            </div>
                        </div>
                        <span class="assignment-status status-<?php echo $assignment['status']; ?>">
                            <?php echo ucfirst($assignment['status']); ?>
                        </span>
                    </div>
                    
                    <?php if ($assignment['description']): ?>
                    <p style="margin: 0 0 15px; color: #555;"><?php echo nl2br(h($assignment['description'])); ?></p>
                    <?php endif; ?>
                    
                    <?php if ($assignment['status'] === 'pending'): ?>
                    <form method="POST" action="api/assignments.php" style="margin-top: 10px;">
                        <input type="hidden" name="action" value="submit">
                        <input type="hidden" name="assignment_id" value="<?php echo $assignment['id']; ?>">
                        <div class="form-group">
                            <label>Your Submission</label>
                            <textarea name="submission_text" rows="4" placeholder="Enter your answer or notes here..." required></textarea>
                        </div>
                        <button type="submit" class="btn-success">
                            <i class="fas fa-paper-plane"></i> Submit Assignment
                        </button>
                    </form>
                    <?php elseif ($assignment['status'] === 'graded'): ?>
                    <div style="background: #f0fff0; padding: 15px; border-radius: 8px; border-left: 4px solid var(--success);">
                        <strong>Grade: <?php echo h($assignment['grade']); ?></strong>
                        <?php if ($assignment['feedback']): ?>
                        <p style="margin: 10px 0 0; color: #555;"><?php echo nl2br(h($assignment['feedback'])); ?></p>
                        <?php endif; ?>
                    </div>
                    <?php elseif ($assignment['status'] === 'submitted'): ?>
                    <div class="alert-info">
                        <i class="fas fa-clock"></i> Waiting for teacher to grade your submission.
                    </div>
                    <?php endif; ?>
                </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="empty-state">
                    <i class="fas fa-tasks"></i>
                    <h3>No Homework Yet</h3>
                    <p>Your teachers will assign homework here when ready.</p>
                </div>
            <?php endif; ?>
        </div>

        <!-- Reviews Tab -->
        <div id="reviews" class="tab-content">
            <h1>My Reviews</h1>
            
            <?php if (count($reviewable_teachers) > 0): ?>
            <div class="card">
                <h2><i class="fas fa-star"></i> Write a Review</h2>
                <form method="POST">
                    <div class="form-group">
                        <label>Select Teacher</label>
                        <select name="teacher_id" required>
                            <option value="">Choose a teacher...</option>
                            <?php foreach ($reviewable_teachers as $t): ?>
                            <option value="<?php echo $t['id']; ?>"><?php echo h($t['name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Rating</label>
                        <div class="star-rating-input">
                            <?php for ($i = 5; $i >= 1; $i--): ?>
                            <input type="radio" name="rating" value="<?php echo $i; ?>" id="star<?php echo $i; ?>" required>
                            <label for="star<?php echo $i; ?>"><i class="fas fa-star"></i></label>
                            <?php endfor; ?>
                        </div>
                    </div>
                    <div class="form-group">
                        <label>Your Review</label>
                        <textarea name="review_text" rows="4" placeholder="Share your experience with this teacher..."></textarea>
                    </div>
                    <button type="submit" name="submit_review" class="btn-primary">
                        <i class="fas fa-paper-plane"></i> Submit Review
                    </button>
                </form>
            </div>
            <?php endif; ?>

            <h2 style="margin-top: 30px;">Your Reviews</h2>
            <?php if (count($my_reviews) > 0): ?>
                <?php foreach ($my_reviews as $review): ?>
                <div class="review-card">
                    <div class="review-header">
                        <img src="<?php echo h($review['teacher_pic']); ?>" alt="" class="review-avatar" onerror="this.src='<?php echo getAssetPath('images/placeholder-teacher.svg'); ?>'">
                        <div class="review-meta">
                            <div class="review-author"><?php echo h($review['teacher_name']); ?></div>
                            <div class="review-date"><?php echo formatRelativeTime($review['created_at']); ?></div>
                        </div>
                        <?php echo getStarRatingHtml($review['rating'], false); ?>
                    </div>
                    <?php if ($review['review_text']): ?>
                    <p class="review-text"><?php echo nl2br(h($review['review_text'])); ?></p>
                    <?php endif; ?>
                </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="empty-state">
                    <i class="fas fa-star"></i>
                    <h3>No Reviews Yet</h3>
                    <p>Share your experience by reviewing your teachers!</p>
                </div>
            <?php endif; ?>
        </div>

        <!-- Security Tab -->
        <div id="security" class="tab-content">
            <h1>Security Settings</h1>
            <?php include __DIR__ . '/app/Views/components/password-change-form.php'; ?>
        </div>

    </div>
</div>

<script>
// Tab switching with URL hash
function switchTab(id) {
    // Prevent any page navigation
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
    if (sidebarHeader && id === 'overview') {
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

// Handle URL hash on load
document.addEventListener('DOMContentLoaded', function() {
    const hash = window.location.hash.substring(1);
    if (hash && document.getElementById(hash)) {
        switchTab(hash);
    } else {
        // Default to overview if no hash
        const overviewTab = document.getElementById('overview');
        if (overviewTab) {
            overviewTab.classList.add('active');
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

// Toggle favorite teacher
function toggleFavorite(teacherId, btn) {
    const isActive = btn.classList.contains('active');
    const action = isActive ? 'remove' : 'add';
    
    fetch('api/favorites.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: `teacher_id=${teacherId}&action=${action}`
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            btn.classList.toggle('active');
        }
    });
}

// Mobile sidebar toggle
function toggleMobileSidebar() {
    document.querySelector('.sidebar').classList.toggle('active');
    document.querySelector('.sidebar-overlay').classList.toggle('active');
}
</script>

</body>
</html>
