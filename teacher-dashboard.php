<?php
session_start();
require_once 'db.php';
require_once 'includes/dashboard-functions.php';

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'teacher') {
    header("Location: login.php");
    exit();
}

$teacher_id = $_SESSION['user_id'];
$user = getUserById($conn, $teacher_id);
$user_role = 'teacher';

// Handle Profile Update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
    $dob = $_POST['dob'];
    $bio = $_POST['bio'];
    $calendly = $_POST['calendly'];
    $about_text = $_POST['about_text'];
    $video_url = $_POST['video_url'];
    $backup_email = filter_input(INPUT_POST, 'backup_email', FILTER_SANITIZE_EMAIL);
    $age = !empty($_POST['age']) ? (int)$_POST['age'] : NULL;
    $age_visibility = $_POST['age_visibility'] ?? 'private';
    $specialty = trim($_POST['specialty'] ?? '');
    $hourly_rate = !empty($_POST['hourly_rate']) ? (float)$_POST['hourly_rate'] : NULL;
    
    $profile_pic_pending = $user['profile_pic'];
    
    if (isset($_FILES['profile_pic_file']) && $_FILES['profile_pic_file']['error'] === UPLOAD_ERR_OK) {
        $file = $_FILES['profile_pic_file'];
        $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
        $allowed = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
        if (in_array(strtolower($ext), $allowed)) {
            $filename = 'pending_' . $teacher_id . '_' . time() . '.' . $ext;
            $target_path = 'images/' . $filename;
            if (move_uploaded_file($file['tmp_name'], $target_path)) {
                $profile_pic_pending = $target_path;
            }
        }
    }

    // Submit to pending_updates table for admin approval
    $check = $conn->query("SELECT id FROM pending_updates WHERE user_id = $teacher_id");
    if ($check->num_rows > 0) {
        $stmt = $conn->prepare("UPDATE pending_updates SET name = ?, bio = ?, profile_pic = ?, about_text = ?, video_url = ?, requested_at = NOW() WHERE user_id = ?");
        $stmt->bind_param("sssssi", $_SESSION['user_name'], $bio, $profile_pic_pending, $about_text, $video_url, $teacher_id);
    } else {
        $stmt = $conn->prepare("INSERT INTO pending_updates (user_id, name, bio, profile_pic, about_text, video_url) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("isssss", $teacher_id, $_SESSION['user_name'], $bio, $profile_pic_pending, $about_text, $video_url);
    }
    $stmt->execute();
    $stmt->close();
    
    // Update fields that don't need approval
    $stmt = $conn->prepare("UPDATE users SET dob = ?, calendly_link = ?, backup_email = ?, age = ?, age_visibility = ?, specialty = ?, hourly_rate = ? WHERE id = ?");
    $stmt->bind_param("sssisidi", $dob, $calendly, $backup_email, $age, $age_visibility, $specialty, $hourly_rate, $teacher_id);
    $stmt->execute();
    $stmt->close();
    
    $msg = "Profile changes submitted for approval.";
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
        $stmt->bind_param("si", $hashed_password, $teacher_id);
        $stmt->execute();
        $stmt->close();
        $password_error = 'password_changed';
    }
}

// Handle Assignment Creation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_assignment'])) {
    $student_id = (int)$_POST['student_id'];
    $title = trim($_POST['title']);
    $description = trim($_POST['description']);
    $due_date = !empty($_POST['due_date']) ? $_POST['due_date'] : null;
    
    $stmt = $conn->prepare("INSERT INTO assignments (teacher_id, student_id, title, description, due_date) VALUES (?, ?, ?, ?, ?)");
    if ($stmt) {
        $stmt->bind_param("iisss", $teacher_id, $student_id, $title, $description, $due_date);
        $stmt->execute();
        $stmt->close();
        
        // Notify student
        createNotification($conn, $student_id, 'assignment', 'New Assignment', "You have a new assignment: $title", 'student-dashboard.php#homework');
    }
    header("Location: teacher-dashboard.php#assignments");
    exit();
}

// Handle Assignment Grading
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['grade_assignment'])) {
    $assignment_id = (int)$_POST['assignment_id'];
    $grade = trim($_POST['grade']);
    $feedback = trim($_POST['feedback']);
    
    $stmt = $conn->prepare("UPDATE assignments SET grade = ?, feedback = ?, status = 'graded', graded_at = NOW() WHERE id = ? AND teacher_id = ?");
    if ($stmt) {
        $stmt->bind_param("ssii", $grade, $feedback, $assignment_id, $teacher_id);
        $stmt->execute();
        $stmt->close();
    }
    header("Location: teacher-dashboard.php#assignments");
    exit();
}

// Handle Lesson Note
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_note'])) {
    $student_id = (int)$_POST['student_id'];
    $note = trim($_POST['note']);
    
    $stmt = $conn->prepare("INSERT INTO lesson_notes (teacher_id, student_id, note) VALUES (?, ?, ?)");
    if ($stmt) {
        $stmt->bind_param("iis", $teacher_id, $student_id, $note);
        $stmt->execute();
        $stmt->close();
    }
    header("Location: teacher-dashboard.php#students");
    exit();
}

// Fetch Stats
$rating_data = getTeacherRating($conn, $teacher_id);
$earnings_data = getTeacherEarnings($conn, $teacher_id);
$student_count = getTeacherStudentCount($conn, $teacher_id);
$pending_assignments = getPendingAssignmentsCount($conn, $teacher_id);

// Fetch Students
$students = [];
$stmt = $conn->prepare("
    SELECT DISTINCT u.id, u.name, u.email, u.profile_pic,
           (SELECT COUNT(*) FROM bookings WHERE student_id = u.id AND teacher_id = ?) as lesson_count,
           (SELECT note FROM lesson_notes WHERE student_id = u.id AND teacher_id = ? ORDER BY created_at DESC LIMIT 1) as last_note
    FROM users u 
    JOIN bookings b ON u.id = b.student_id 
    WHERE b.teacher_id = ?
");
$stmt->bind_param("iii", $teacher_id, $teacher_id, $teacher_id);
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $students[] = $row;
}
$stmt->close();

// Fetch Assignments
$assignments = [];
$assign_result = $conn->query("
    SELECT a.*, u.name as student_name, u.profile_pic as student_pic
    FROM assignments a
    JOIN users u ON a.student_id = u.id
    WHERE a.teacher_id = $teacher_id
    ORDER BY CASE WHEN a.status = 'submitted' THEN 0 WHEN a.status = 'pending' THEN 1 ELSE 2 END, a.created_at DESC
");
if ($assign_result) {
    while ($row = $assign_result->fetch_assoc()) {
        $assignments[] = $row;
    }
}

// Fetch Reviews
$reviews = [];
$reviews_result = $conn->query("
    SELECT r.*, u.name as student_name, u.profile_pic as student_pic
    FROM reviews r
    JOIN users u ON r.student_id = u.id
    WHERE r.teacher_id = $teacher_id
    ORDER BY r.created_at DESC
");
if ($reviews_result) {
    while ($row = $reviews_result->fetch_assoc()) {
        $reviews[] = $row;
    }
}

// Fetch Earnings History
$earnings_history = [];
$earnings_result = $conn->query("
    SELECT e.*, u.name as student_name
    FROM earnings e
    LEFT JOIN bookings b ON e.booking_id = b.id
    LEFT JOIN users u ON b.student_id = u.id
    WHERE e.teacher_id = $teacher_id
    ORDER BY e.created_at DESC
    LIMIT 20
");
if ($earnings_result) {
    while ($row = $earnings_result->fetch_assoc()) {
        $earnings_history[] = $row;
    }
}

// Fetch Resources
$resources = [];
$res_result = $conn->query("SELECT * FROM teacher_resources WHERE teacher_id = $teacher_id ORDER BY created_at DESC");
if ($res_result) {
    while ($row = $res_result->fetch_assoc()) {
        $resources[] = $row;
    }
}

// Fetch Classroom Materials
$materials = $conn->query("SELECT * FROM classroom_materials ORDER BY created_at DESC");

$active_tab = 'overview';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Teacher Dashboard - Staten Academy</title>
    <link rel="stylesheet" href="styles.css">
    <link rel="stylesheet" href="css/dashboard.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body class="dashboard-layout">

<?php include 'includes/dashboard-header.php'; ?>

<div class="content-wrapper">
    <?php include 'includes/dashboard-sidebar.php'; ?>

    <div class="main">
        
        <!-- Overview Tab -->
        <div id="overview" class="tab-content active">
            <h1>Welcome back, <?php echo h($user['name']); ?>! 👋</h1>
            
            <?php if (isset($msg)): ?>
                <div class="alert-success"><i class="fas fa-check-circle"></i> <?php echo $msg; ?></div>
            <?php endif; ?>
            
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-icon"><i class="fas fa-users"></i></div>
                    <div class="stat-info">
                        <h3><?php echo $student_count; ?></h3>
                        <p>Active Students</p>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon success"><i class="fas fa-clock"></i></div>
                    <div class="stat-info">
                        <h3><?php echo $user['hours_taught'] ?? 0; ?></h3>
                        <p>Hours Taught</p>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon warning"><i class="fas fa-star"></i></div>
                    <div class="stat-info">
                        <h3><?php echo $rating_data['avg_rating']; ?></h3>
                        <p><?php echo $rating_data['review_count']; ?> Reviews</p>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon info"><i class="fas fa-dollar-sign"></i></div>
                    <div class="stat-info">
                        <h3><?php echo formatCurrency($earnings_data['total_earnings']); ?></h3>
                        <p>Total Earnings</p>
                    </div>
                </div>
            </div>

            <div class="card">
                <h2><i class="fas fa-bolt"></i> Quick Actions</h2>
                <div class="quick-actions">
                    <a href="schedule.php" class="quick-action-btn">
                        <i class="fas fa-calendar"></i>
                        <span>View Schedule</span>
                    </a>
                    <a href="message_threads.php" class="quick-action-btn">
                        <i class="fas fa-comments"></i>
                        <span>Messages</span>
                    </a>
                    <a href="#" onclick="switchTab('assignments')" class="quick-action-btn">
                        <i class="fas fa-tasks"></i>
                        <span>Assignments</span>
                        <?php if ($pending_assignments > 0): ?>
                            <span class="notification-badge" style="position: static; margin-left: 5px;"><?php echo $pending_assignments; ?></span>
                        <?php endif; ?>
                    </a>
                    <a href="profile.php?id=<?php echo $teacher_id; ?>" class="quick-action-btn">
                        <i class="fas fa-eye"></i>
                        <span>View Profile</span>
                    </a>
                </div>
            </div>

            <?php if ($pending_assignments > 0): ?>
            <div class="card">
                <h2><i class="fas fa-exclamation-circle" style="color: var(--warning);"></i> Pending Submissions</h2>
                <?php foreach ($assignments as $a): ?>
                    <?php if ($a['status'] === 'submitted'): ?>
                    <div class="assignment-item">
                        <img src="<?php echo h($a['student_pic']); ?>" alt="" style="width: 40px; height: 40px; border-radius: 50%; object-fit: cover;" onerror="this.src='images/placeholder-teacher.svg'">
                        <div style="flex: 1;">
                            <strong><?php echo h($a['title']); ?></strong>
                            <div style="font-size: 0.85rem; color: var(--gray);">
                                From: <?php echo h($a['student_name']); ?> • Submitted <?php echo formatRelativeTime($a['submitted_at']); ?>
                            </div>
                        </div>
                        <a href="#" onclick="switchTab('assignments')" class="btn-primary btn-sm">Grade</a>
                    </div>
                    <?php endif; ?>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>

            <?php if (count($reviews) > 0): ?>
            <div class="card">
                <h2><i class="fas fa-star"></i> Recent Reviews</h2>
                <?php foreach (array_slice($reviews, 0, 3) as $review): ?>
                <div class="review-card" style="margin-bottom: 10px;">
                    <div class="review-header">
                        <img src="<?php echo h($review['student_pic']); ?>" alt="" class="review-avatar" onerror="this.src='images/placeholder-teacher.svg'">
                        <div class="review-meta">
                            <div class="review-author"><?php echo h($review['student_name']); ?></div>
                            <div class="review-date"><?php echo formatRelativeTime($review['created_at']); ?></div>
                        </div>
                        <?php echo getStarRatingHtml($review['rating'], false); ?>
                    </div>
                    <?php if ($review['review_text']): ?>
                    <p class="review-text"><?php echo h(substr($review['review_text'], 0, 150)); ?>...</p>
                    <?php endif; ?>
                </div>
                <?php endforeach; ?>
                <a href="#" onclick="switchTab('reviews')" style="color: var(--primary); text-decoration: none;">View all reviews →</a>
            </div>
            <?php endif; ?>
        </div>

        <!-- Earnings Tab -->
        <div id="earnings" class="tab-content">
            <h1>Earnings</h1>
            
            <div class="earnings-summary">
                <div class="earnings-card primary">
                    <div class="earnings-amount"><?php echo formatCurrency($earnings_data['total_earnings']); ?></div>
                    <div class="earnings-label">Total Earnings</div>
                </div>
                <div class="earnings-card">
                    <div class="earnings-amount" style="color: var(--success);"><?php echo formatCurrency($earnings_data['total_paid']); ?></div>
                    <div class="earnings-label">Paid Out</div>
                </div>
                <div class="earnings-card">
                    <div class="earnings-amount" style="color: var(--warning);"><?php echo formatCurrency($earnings_data['total_pending']); ?></div>
                    <div class="earnings-label">Pending</div>
                </div>
            </div>

            <div class="card">
                <h2><i class="fas fa-history"></i> Payment History</h2>
                <?php if (count($earnings_history) > 0): ?>
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Student</th>
                            <th>Amount</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($earnings_history as $earning): ?>
                        <tr>
                            <td><?php echo date('M d, Y', strtotime($earning['created_at'])); ?></td>
                            <td><?php echo h($earning['student_name'] ?? 'N/A'); ?></td>
                            <td><strong><?php echo formatCurrency($earning['net_amount']); ?></strong></td>
                            <td>
                                <span class="tag <?php echo $earning['status']; ?>">
                                    <?php echo ucfirst($earning['status']); ?>
                                </span>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <?php else: ?>
                <div class="empty-state">
                    <i class="fas fa-dollar-sign"></i>
                    <h3>No Earnings Yet</h3>
                    <p>Your earnings will appear here after completing lessons.</p>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Students Tab -->
        <div id="students" class="tab-content">
            <h1>My Students</h1>
            
            <?php if (count($students) > 0): ?>
                <?php foreach ($students as $student): ?>
                <div class="card" style="margin-bottom: 15px;">
                    <div style="display: flex; gap: 20px; align-items: flex-start;">
                        <img src="<?php echo h($student['profile_pic']); ?>" alt="" style="width: 60px; height: 60px; border-radius: 50%; object-fit: cover;" onerror="this.src='images/placeholder-teacher.svg'">
                        <div style="flex: 1;">
                            <h3 style="margin: 0 0 5px; border: none; padding: 0;"><?php echo h($student['name']); ?></h3>
                            <div style="font-size: 0.9rem; color: var(--gray); margin-bottom: 10px;">
                                <?php echo h($student['email']); ?> • <?php echo $student['lesson_count']; ?> lessons
                            </div>
                            <?php if ($student['last_note']): ?>
                            <div style="background: var(--light-gray); padding: 10px; border-radius: 5px; font-size: 0.9rem; margin-bottom: 10px;">
                                <strong>Last Note:</strong> <?php echo h(substr($student['last_note'], 0, 100)); ?>...
                            </div>
                            <?php endif; ?>
                            
                            <form method="POST" style="display: flex; gap: 10px;">
                                <input type="hidden" name="student_id" value="<?php echo $student['id']; ?>">
                                <input type="text" name="note" placeholder="Add a private note about this student..." style="flex: 1; padding: 8px; border: 1px solid var(--border); border-radius: 5px;">
                                <button type="submit" name="add_note" class="btn-primary btn-sm">Add Note</button>
                            </form>
                        </div>
                        <div style="display: flex; flex-direction: column; gap: 8px;">
                            <a href="message_threads.php?to=<?php echo $student['id']; ?>" class="btn-outline btn-sm">Message</a>
                            <button onclick="showAssignmentModal(<?php echo $student['id']; ?>, '<?php echo h($student['name']); ?>')" class="btn-primary btn-sm">Assign Work</button>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="empty-state">
                    <i class="fas fa-users"></i>
                    <h3>No Students Yet</h3>
                    <p>Students who book lessons with you will appear here.</p>
                </div>
            <?php endif; ?>
        </div>

        <!-- Assignments Tab -->
        <div id="assignments" class="tab-content">
            <h1>Assignments</h1>
            
            <?php if (count($students) > 0): ?>
            <div class="card">
                <h2><i class="fas fa-plus-circle"></i> Create Assignment</h2>
                <form method="POST">
                    <div class="profile-grid">
                        <div class="form-group">
                            <label>Student</label>
                            <select name="student_id" required>
                                <option value="">Select a student...</option>
                                <?php foreach ($students as $s): ?>
                                <option value="<?php echo $s['id']; ?>"><?php echo h($s['name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Due Date (Optional)</label>
                            <input type="date" name="due_date">
                        </div>
                    </div>
                    <div class="form-group">
                        <label>Assignment Title</label>
                        <input type="text" name="title" placeholder="e.g., Practice vocabulary words" required>
                    </div>
                    <div class="form-group">
                        <label>Description</label>
                        <textarea name="description" rows="4" placeholder="Describe the assignment details..."></textarea>
                    </div>
                    <button type="submit" name="create_assignment" class="btn-primary">
                        <i class="fas fa-paper-plane"></i> Create Assignment
                    </button>
                </form>
            </div>
            <?php endif; ?>

            <h2 style="margin-top: 30px;">All Assignments</h2>
            <?php if (count($assignments) > 0): ?>
                <?php foreach ($assignments as $a): ?>
                <div class="card" style="margin-bottom: 15px;">
                    <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 15px;">
                        <div style="display: flex; gap: 15px; align-items: center;">
                            <img src="<?php echo h($a['student_pic']); ?>" alt="" style="width: 45px; height: 45px; border-radius: 50%; object-fit: cover;" onerror="this.src='images/placeholder-teacher.svg'">
                            <div>
                                <h3 style="margin: 0; border: none; padding: 0;"><?php echo h($a['title']); ?></h3>
                                <div style="font-size: 0.85rem; color: var(--gray);">
                                    For: <?php echo h($a['student_name']); ?>
                                    <?php if ($a['due_date']): ?>
                                    • Due: <?php echo date('M d, Y', strtotime($a['due_date'])); ?>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                        <span class="assignment-status status-<?php echo $a['status']; ?>">
                            <?php echo ucfirst($a['status']); ?>
                        </span>
                    </div>
                    
                    <?php if ($a['description']): ?>
                    <p style="color: #555; margin-bottom: 15px;"><?php echo nl2br(h($a['description'])); ?></p>
                    <?php endif; ?>
                    
                    <?php if ($a['status'] === 'submitted'): ?>
                    <div style="background: #f0f7ff; padding: 15px; border-radius: 8px; margin-bottom: 15px;">
                        <strong>Student's Submission:</strong>
                        <p style="margin: 10px 0 0;"><?php echo nl2br(h($a['submission_text'])); ?></p>
                        <?php if ($a['submission_file']): ?>
                        <a href="<?php echo h($a['submission_file']); ?>" target="_blank" style="color: var(--primary);">
                            <i class="fas fa-paperclip"></i> View Attached File
                        </a>
                        <?php endif; ?>
                    </div>
                    
                    <form method="POST" style="display: flex; gap: 15px; align-items: flex-end;">
                        <input type="hidden" name="assignment_id" value="<?php echo $a['id']; ?>">
                        <div class="form-group" style="width: 100px; margin: 0;">
                            <label>Grade</label>
                            <input type="text" name="grade" placeholder="A, B, 95%..." required>
                        </div>
                        <div class="form-group" style="flex: 1; margin: 0;">
                            <label>Feedback</label>
                            <input type="text" name="feedback" placeholder="Great work! Keep it up...">
                        </div>
                        <button type="submit" name="grade_assignment" class="btn-success" style="height: 46px;">
                            <i class="fas fa-check"></i> Submit Grade
                        </button>
                    </form>
                    <?php elseif ($a['status'] === 'graded'): ?>
                    <div style="background: #f0fff0; padding: 15px; border-radius: 8px; border-left: 4px solid var(--success);">
                        <strong>Grade: <?php echo h($a['grade']); ?></strong>
                        <?php if ($a['feedback']): ?>
                        <p style="margin: 5px 0 0; color: #555;"><?php echo h($a['feedback']); ?></p>
                        <?php endif; ?>
                    </div>
                    <?php endif; ?>
                </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="empty-state">
                    <i class="fas fa-tasks"></i>
                    <h3>No Assignments Yet</h3>
                    <p>Create assignments for your students above.</p>
                </div>
            <?php endif; ?>
        </div>

        <!-- Reviews Tab -->
        <div id="reviews" class="tab-content">
            <h1>Reviews</h1>
            
            <div class="card" style="margin-bottom: 30px;">
                <div style="display: flex; align-items: center; gap: 30px;">
                    <div style="text-align: center;">
                        <div style="font-size: 3rem; font-weight: 700; color: var(--primary);">
                            <?php echo $rating_data['avg_rating']; ?>
                        </div>
                        <div><?php echo getStarRatingHtml($rating_data['avg_rating'], false); ?></div>
                        <div style="color: var(--gray); margin-top: 5px;"><?php echo $rating_data['review_count']; ?> reviews</div>
                    </div>
                    <div style="flex: 1;">
                        <?php
                        // Rating distribution (simplified)
                        for ($i = 5; $i >= 1; $i--) {
                            $count = 0;
                            foreach ($reviews as $r) {
                                if ($r['rating'] == $i) $count++;
                            }
                            $percent = $rating_data['review_count'] > 0 ? ($count / $rating_data['review_count']) * 100 : 0;
                            echo "<div style='display: flex; align-items: center; gap: 10px; margin-bottom: 5px;'>
                                    <span style='width: 50px;'>$i star</span>
                                    <div style='flex: 1; height: 8px; background: #eee; border-radius: 4px;'>
                                        <div style='width: {$percent}%; height: 100%; background: #ffc107; border-radius: 4px;'></div>
                                    </div>
                                    <span style='width: 30px; color: var(--gray);'>$count</span>
                                  </div>";
                        }
                        ?>
                    </div>
                </div>
            </div>

            <?php if (count($reviews) > 0): ?>
                <?php foreach ($reviews as $review): ?>
                <div class="review-card">
                    <div class="review-header">
                        <img src="<?php echo h($review['student_pic']); ?>" alt="" class="review-avatar" onerror="this.src='images/placeholder-teacher.svg'">
                        <div class="review-meta">
                            <div class="review-author"><?php echo h($review['student_name']); ?></div>
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
                    <p>Reviews from your students will appear here.</p>
                </div>
            <?php endif; ?>
        </div>

        <!-- Resources Tab -->
        <div id="resources" class="tab-content">
            <h1>Resource Library</h1>
            
            <div class="card">
                <h2><i class="fas fa-upload"></i> Upload Resource</h2>
                <form method="POST" action="api/resources.php" enctype="multipart/form-data">
                    <input type="hidden" name="action" value="upload">
                    <div class="profile-grid">
                        <div class="form-group">
                            <label>Title</label>
                            <input type="text" name="title" placeholder="Resource title" required>
                        </div>
                        <div class="form-group">
                            <label>Category</label>
                            <select name="category">
                                <option value="general">General</option>
                                <option value="vocabulary">Vocabulary</option>
                                <option value="grammar">Grammar</option>
                                <option value="exercises">Exercises</option>
                                <option value="reading">Reading</option>
                            </select>
                        </div>
                    </div>
                    <div class="form-group">
                        <label>Description</label>
                        <textarea name="description" rows="2" placeholder="Brief description..."></textarea>
                    </div>
                    <div class="form-group">
                        <label>File or URL</label>
                        <input type="file" name="file" accept=".pdf,.doc,.docx,.ppt,.pptx,.txt,.jpg,.png">
                        <small style="color: var(--gray); display: block; margin-top: 5px;">Or enter external URL:</small>
                        <input type="url" name="external_url" placeholder="https://..." style="margin-top: 5px;">
                    </div>
                    <button type="submit" class="btn-primary"><i class="fas fa-upload"></i> Upload</button>
                </form>
            </div>

            <h2 style="margin-top: 30px;">Your Resources</h2>
            <?php if (count($resources) > 0): ?>
                <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); gap: 20px;">
                    <?php foreach ($resources as $res): ?>
                    <div class="card" style="margin: 0;">
                        <div class="material-icon" style="margin-bottom: 10px;">
                            <i class="fas fa-file-alt"></i>
                        </div>
                        <h3 style="border: none; padding: 0; font-size: 1rem;"><?php echo h($res['title']); ?></h3>
                        <span class="tag"><?php echo ucfirst($res['category']); ?></span>
                        <?php if ($res['description']): ?>
                        <p style="font-size: 0.9rem; color: var(--gray); margin: 10px 0;"><?php echo h($res['description']); ?></p>
                        <?php endif; ?>
                        <a href="<?php echo h($res['file_path'] ?: $res['external_url']); ?>" target="_blank" class="btn-outline btn-sm" style="margin-top: 10px;">
                            <i class="fas fa-external-link-alt"></i> Open
                        </a>
                    </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="empty-state">
                    <i class="fas fa-folder-open"></i>
                    <h3>No Resources Yet</h3>
                    <p>Upload teaching materials to share with your students.</p>
                </div>
            <?php endif; ?>
        </div>

        <!-- Profile Tab -->
        <div id="profile" class="tab-content">
            <h1>Edit Profile</h1>
            <div class="card">
                <form method="POST" enctype="multipart/form-data">
                    <div style="display: flex; gap: 30px; margin-bottom: 25px; align-items: flex-start;">
                        <div style="text-align: center;">
                            <img src="<?php echo h($user['profile_pic']); ?>" alt="Profile" 
                                 style="width: 120px; height: 120px; border-radius: 50%; object-fit: cover; border: 4px solid var(--primary-light);"
                                 onerror="this.src='images/placeholder-teacher.svg'">
                            <div style="margin-top: 15px;">
                                <label class="btn-outline btn-sm" style="cursor: pointer;">
                                    <i class="fas fa-camera"></i> Change Photo
                                    <input type="file" name="profile_pic_file" accept="image/*" style="display: none;">
                                </label>
                                <small style="display: block; margin-top: 5px; color: var(--primary);">Requires admin approval</small>
                            </div>
                        </div>
                        <div style="flex: 1;">
                            <div class="profile-grid">
                                <div class="form-group">
                                    <label>Date of Birth</label>
                                    <input type="date" name="dob" value="<?php echo $user['dob']; ?>">
                                </div>
                                <div class="form-group">
                                    <label>Specialty / Subject</label>
                                    <input type="text" name="specialty" value="<?php echo h($user['specialty'] ?? ''); ?>" placeholder="e.g., English, Math">
                                </div>
                                <div class="form-group">
                                    <label>Hourly Rate ($)</label>
                                    <input type="number" name="hourly_rate" step="0.01" value="<?php echo h($user['hourly_rate'] ?? ''); ?>" placeholder="25.00">
                                </div>
                                <div class="form-group">
                                    <label>Calendly Link</label>
                                    <input type="url" name="calendly" value="<?php echo h($user['calendly_link']); ?>" placeholder="https://calendly.com/...">
                                </div>
                                <div class="form-group">
                                    <label>Backup Email</label>
                                    <input type="email" name="backup_email" value="<?php echo h($user['backup_email'] ?? ''); ?>">
                                </div>
                                <div class="form-group">
                                    <label>Age</label>
                                    <input type="number" name="age" value="<?php echo h($user['age'] ?? ''); ?>">
                                </div>
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
                    
                    <div class="form-group">
                        <label>Bio / Introduction</label>
                        <textarea name="bio" rows="4"><?php echo h($user['bio']); ?></textarea>
                    </div>
                    <div class="form-group">
                        <label>About Me (Profile Page)</label>
                        <textarea name="about_text" rows="4" placeholder="Tell students about yourself..."><?php echo h($user['about_text']); ?></textarea>
                    </div>
                    <div class="form-group">
                        <label>Introduction Video URL</label>
                        <input type="url" name="video_url" value="<?php echo h($user['video_url']); ?>" placeholder="https://...">
                    </div>
                    
                    <div class="alert-info">
                        <i class="fas fa-info-circle"></i>
                        Bio, About, Profile Picture, and Video changes require admin approval.
                    </div>
                    
                    <button type="submit" name="update_profile" class="btn-primary">
                        <i class="fas fa-save"></i> Submit Changes
                    </button>
                </form>
            </div>
        </div>

        <!-- Security Tab -->
        <div id="security" class="tab-content">
            <h1>Security Settings</h1>
            <?php include 'includes/password-change-form.php'; ?>
        </div>

    </div>
</div>

<!-- Assignment Modal -->
<div class="modal-overlay" id="assignmentModal">
    <div class="modal">
        <div class="modal-header">
            <h3>Create Assignment</h3>
            <button class="modal-close" onclick="closeAssignmentModal()">&times;</button>
        </div>
        <form method="POST">
            <input type="hidden" name="student_id" id="modalStudentId">
            <p>Assigning to: <strong id="modalStudentName"></strong></p>
            <div class="form-group">
                <label>Title</label>
                <input type="text" name="title" required>
            </div>
            <div class="form-group">
                <label>Description</label>
                <textarea name="description" rows="3"></textarea>
            </div>
            <div class="form-group">
                <label>Due Date</label>
                <input type="date" name="due_date">
            </div>
            <button type="submit" name="create_assignment" class="btn-primary">Create</button>
        </form>
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

function showAssignmentModal(studentId, studentName) {
    document.getElementById('modalStudentId').value = studentId;
    document.getElementById('modalStudentName').textContent = studentName;
    document.getElementById('assignmentModal').classList.add('active');
}

function closeAssignmentModal() {
    document.getElementById('assignmentModal').classList.remove('active');
}

function toggleMobileSidebar() {
    document.querySelector('.sidebar').classList.toggle('active');
    document.querySelector('.sidebar-overlay').classList.toggle('active');
}
</script>

</body>
</html>
