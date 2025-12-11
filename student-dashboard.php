<?php
session_start();
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/app/Views/components/dashboard-functions.php';
require_once __DIR__ . '/google-calendar-config.php';

// #region agent log helper
if (!function_exists('agent_debug_log')) {
    function agent_debug_log($hypothesisId, $location, $message, $data = []) {
        $payload = [
            'sessionId' => 'debug-session',
            'runId' => 'run1',
            'hypothesisId' => $hypothesisId,
            'location' => $location,
            'message' => $message,
            'data' => $data,
            'timestamp' => round(microtime(true) * 1000),
        ];
        $line = json_encode($payload);
        if ($line) {
            @file_put_contents(__DIR__ . '/.cursor/debug.log', $line . PHP_EOL, FILE_APPEND);
        }
    }
}
// #endregion

agent_debug_log('H1', 'student-dashboard.php:session', 'student dashboard entry', [
    'user_id' => $_SESSION['user_id'] ?? null,
    'user_role' => $_SESSION['user_role'] ?? null,
]);

if (!isset($_SESSION['user_id']) || ($_SESSION['user_role'] !== 'student' && $_SESSION['user_role'] !== 'new_student')) {
    header("Location: login.php");
    exit();
}

$student_id = $_SESSION['user_id'];
$user = getUserById($conn, $student_id);
$user_role = $_SESSION['user_role']; // Keep actual role (student or new_student)

// Get student's track and assigned teacher
require_once __DIR__ . '/app/Models/TeacherAssignment.php';
require_once __DIR__ . '/app/Models/GroupClass.php';
$assignmentModel = new TeacherAssignment($conn);
$groupClassModel = new GroupClass($conn);

$assigned_teacher = null;
$student_track = $user['learning_track'] ?? null;

// Get assigned teacher
if (!empty($user['assigned_teacher_id'])) {
    $stmt = $conn->prepare("SELECT id, name, email, profile_pic, bio FROM users WHERE id = ?");
    $stmt->bind_param("i", $user['assigned_teacher_id']);
    $stmt->execute();
    $result = $stmt->get_result();
    $assigned_teacher = $result->fetch_assoc();
    $stmt->close();
} else {
    // Try assignment table
    $assignment = $assignmentModel->getStudentTeacher($student_id);
    if ($assignment) {
        $assigned_teacher = [
            'id' => $assignment['teacher_id'],
            'name' => $assignment['teacher_name'],
            'email' => $assignment['teacher_email'],
            'profile_pic' => $assignment['teacher_pic'] ?? getAssetPath('images/placeholder-teacher.svg'),
            'bio' => $assignment['teacher_bio'] ?? ''
        ];
    }
}

// Get group classes for student's track
$group_classes = [];
if ($student_track) {
    $group_classes = $groupClassModel->getTrackClasses($student_track);
}

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

// Handle Learning Needs Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_learning_needs'])) {
    $track = $_POST['track'] ?? $user['learning_track'] ?? null;
    $age_range = trim($_POST['age_range'] ?? '');
    $current_level = trim($_POST['current_level'] ?? '');
    $learning_goals = trim($_POST['learning_goals'] ?? '');
    $preferred_schedule = trim($_POST['preferred_schedule'] ?? '');
    $special_requirements = trim($_POST['special_requirements'] ?? '');
    
    if (!$track || !in_array($track, ['kids', 'adults', 'coding'])) {
        $_SESSION['error_message'] = 'Please select a learning track.';
        header("Location: student-dashboard.php#learning-needs");
        exit();
    }
    
    // Check if learning needs already exist
    $check_stmt = $conn->prepare("SELECT id FROM student_learning_needs WHERE student_id = ?");
    $check_stmt->bind_param("i", $student_id);
    $check_stmt->execute();
    $existing = $check_stmt->get_result()->fetch_assoc();
    $check_stmt->close();
    
    if ($existing) {
        // Update existing
        $update_stmt = $conn->prepare("
            UPDATE student_learning_needs 
            SET track = ?, age_range = ?, current_level = ?, learning_goals = ?, 
                preferred_schedule = ?, special_requirements = ?, completed = 1, updated_at = NOW()
            WHERE student_id = ?
        ");
        $update_stmt->bind_param("ssssssi", $track, $age_range, $current_level, $learning_goals, 
                                 $preferred_schedule, $special_requirements, $student_id);
        $update_stmt->execute();
        $update_stmt->close();
    } else {
        // Insert new
        $insert_stmt = $conn->prepare("
            INSERT INTO student_learning_needs 
            (student_id, track, age_range, current_level, learning_goals, preferred_schedule, special_requirements, completed)
            VALUES (?, ?, ?, ?, ?, ?, ?, 1)
        ");
        $insert_stmt->bind_param("issssss", $student_id, $track, $age_range, $current_level, 
                                 $learning_goals, $preferred_schedule, $special_requirements);
        $insert_stmt->execute();
        $insert_stmt->close();
    }
    
    // Update user's track
    $track_stmt = $conn->prepare("UPDATE users SET learning_track = ? WHERE id = ?");
    $track_stmt->bind_param("si", $track, $student_id);
    $track_stmt->execute();
    $track_stmt->close();
    
    // Save preferred times
    // First, delete existing preferred times
    $delete_times = $conn->prepare("DELETE FROM preferred_times WHERE student_id = ?");
    $delete_times->bind_param("i", $student_id);
    $delete_times->execute();
    $delete_times->close();
    
    // Insert new preferred times
    if (isset($_POST['preferred_times']) && is_array($_POST['preferred_times'])) {
        $insert_time = $conn->prepare("INSERT INTO preferred_times (student_id, day_of_week, start_time, end_time) VALUES (?, ?, ?, ?)");
        foreach ($_POST['preferred_times'] as $time_slot) {
            if (!empty($time_slot['day']) && !empty($time_slot['start']) && !empty($time_slot['end'])) {
                $day = $time_slot['day'];
                $start = $time_slot['start'];
                $end = $time_slot['end'];
                
                // Validate that end time is after start time
                if (strtotime($end) > strtotime($start)) {
                    $insert_time->bind_param("isss", $student_id, $day, $start, $end);
                    $insert_time->execute();
                }
            }
        }
        $insert_time->close();
    }
    
    // Automated teacher assignment: Find best matching teacher
    // Priority: 1. Teachers with matching preferred times (if specified), 2. Teachers with matching track, 3. Available capacity, 4. Best rating
    
    // Check if student has preferred times
    $preferred_times_check = $conn->prepare("SELECT COUNT(*) as time_count FROM preferred_times WHERE student_id = ?");
    $preferred_times_check->bind_param("i", $student_id);
    $preferred_times_check->execute();
    $preferred_times_result = $preferred_times_check->get_result();
    $preferred_times_row = $preferred_times_result->fetch_assoc();
    $has_preferred_times = $preferred_times_row['time_count'] > 0;
    $preferred_times_check->close();
    
    if ($has_preferred_times) {
        // Match teachers with overlapping availability
        $teacher_match_stmt = $conn->prepare("
            SELECT DISTINCT u.id, u.name, u.email, u.profile_pic, u.bio,
                   COALESCE(AVG(r.rating), 0) as avg_rating,
                   (SELECT COUNT(*) FROM lessons l WHERE l.teacher_id = u.id AND l.status = 'scheduled' AND l.lesson_date >= CURDATE()) as active_lessons,
                   COUNT(DISTINCT pt.id) as matching_time_slots
            FROM users u
            LEFT JOIN reviews r ON u.id = r.teacher_id
            INNER JOIN teacher_availability ta ON u.id = ta.teacher_id
            INNER JOIN preferred_times pt ON ta.day_of_week = pt.day_of_week 
                AND pt.student_id = ?
                AND ta.start_time <= pt.end_time 
                AND ta.end_time >= pt.start_time
                AND ta.is_available = 1
            WHERE u.role = 'teacher' 
            AND u.application_status = 'approved'
            GROUP BY u.id
            HAVING active_lessons < 50
            ORDER BY matching_time_slots DESC, avg_rating DESC, active_lessons ASC
            LIMIT 1
        ");
        $teacher_match_stmt->bind_param("i", $student_id);
    } else {
        // Fallback to original matching without preferred times
        $teacher_match_stmt = $conn->prepare("
            SELECT u.id, u.name, u.email, u.profile_pic, u.bio,
                   COALESCE(AVG(r.rating), 0) as avg_rating,
                   (SELECT COUNT(*) FROM lessons l WHERE l.teacher_id = u.id AND l.status = 'scheduled' AND l.lesson_date >= CURDATE()) as active_lessons
            FROM users u
            LEFT JOIN reviews r ON u.id = r.teacher_id
            WHERE u.role = 'teacher' 
            AND u.application_status = 'approved'
            GROUP BY u.id
            HAVING active_lessons < 50
            ORDER BY avg_rating DESC, active_lessons ASC
            LIMIT 1
        ");
    }
    
    $teacher_match_stmt->execute();
    $matched_teacher = $teacher_match_stmt->get_result()->fetch_assoc();
    $teacher_match_stmt->close();
    
    if ($matched_teacher) {
        // Assign teacher to student
        $assign_stmt = $conn->prepare("UPDATE users SET assigned_teacher_id = ? WHERE id = ?");
        $assign_stmt->bind_param("ii", $matched_teacher['id'], $student_id);
        $assign_stmt->execute();
        $assign_stmt->close();
        
        // Also create assignment record
        $assign_record_stmt = $conn->prepare("
            INSERT INTO teacher_assignments (student_id, teacher_id, assigned_at, status)
            VALUES (?, ?, NOW(), 'active')
            ON DUPLICATE KEY UPDATE teacher_id = VALUES(teacher_id), assigned_at = NOW(), status = 'active'
        ");
        $assign_record_stmt->bind_param("ii", $student_id, $matched_teacher['id']);
        $assign_record_stmt->execute();
        $assign_record_stmt->close();
        
        // Notify student
        if (function_exists('createNotification')) {
            createNotification($conn, $student_id, 'assignment', 'Teacher Assigned', 
                'You have been assigned to ' . $matched_teacher['name'] . '. You can now book lessons!', 
                'student-dashboard.php#overview');
        }
        
        $_SESSION['success_message'] = 'Learning needs submitted! You have been assigned to ' . $matched_teacher['name'] . '. You can now book lessons!';
    } else {
        $_SESSION['success_message'] = 'Learning needs submitted! We will assign you a teacher within 24-48 hours.';
    }
    
    header("Location: student-dashboard.php#overview");
    exit();
}

// Handle Review Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_review'])) {
    $teacher_id = (int)$_POST['teacher_id'];
    $rating = (int)$_POST['rating'];
    $review_text = trim($_POST['review_text']);
    
    // REQUIREMENT: Only allow reviews after a booking exists
    // Check if student has a booking with this teacher
    $booking_check = $conn->prepare("SELECT id FROM bookings WHERE student_id = ? AND teacher_id = ? LIMIT 1");
    $booking_check->bind_param("ii", $student_id, $teacher_id);
    $booking_check->execute();
    $has_booking = $booking_check->get_result()->num_rows > 0;
    $booking_check->close();
    
    if (!$has_booking) {
        $_SESSION['error_message'] = 'You can only review teachers after booking a class with them.';
        header("Location: student-dashboard.php#reviews");
        exit();
    }
    
    // Check if already reviewed
    $review_check = $conn->prepare("SELECT id FROM reviews WHERE teacher_id = ? AND student_id = ? LIMIT 1");
    $review_check->bind_param("ii", $teacher_id, $student_id);
    $review_check->execute();
    $has_reviewed = $review_check->get_result()->num_rows > 0;
    $review_check->close();
    
    if ($has_reviewed) {
        // Update existing review
        $stmt = $conn->prepare("UPDATE reviews SET rating = ?, review_text = ?, updated_at = NOW() WHERE teacher_id = ? AND student_id = ?");
        if ($stmt) {
            $stmt->bind_param("isii", $rating, $review_text, $teacher_id, $student_id);
            $stmt->execute();
            $stmt->close();
        }
    } else {
        // Insert new review
        $stmt = $conn->prepare("INSERT INTO reviews (teacher_id, student_id, rating, review_text) VALUES (?, ?, ?, ?)");
        if ($stmt) {
            $stmt->bind_param("iiis", $teacher_id, $student_id, $rating, $review_text);
            $stmt->execute();
            $stmt->close();
        }
    }
    
    // Update teacher rating cache
    $rating_update = $conn->prepare("
        UPDATE users SET 
            avg_rating = (SELECT AVG(rating) FROM reviews WHERE teacher_id = ?),
            review_count = (SELECT COUNT(*) FROM reviews WHERE teacher_id = ?)
        WHERE id = ?
    ");
    $rating_update->bind_param("iii", $teacher_id, $teacher_id, $teacher_id);
    $rating_update->execute();
    $rating_update->close();
    
    // Notify teacher
    createNotification($conn, $teacher_id, 'review', 'New Review', 
        $_SESSION['user_name'] . " left you a $rating-star review!", 'teacher-dashboard.php#reviews');
    
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

// Fetch Past Lessons Needing Confirmation
$past_lessons_pending = [];
$stmt = $conn->prepare("
    SELECT l.*, u.name as teacher_name, u.profile_pic as teacher_pic
    FROM lessons l
    JOIN users u ON l.teacher_id = u.id
    WHERE l.student_id = ? 
    AND l.status = 'scheduled'
    AND CONCAT(l.lesson_date, ' ', l.end_time) < NOW()
    AND (l.attendance_status IS NULL OR l.attendance_status = '')
    ORDER BY l.lesson_date DESC, l.start_time DESC
    LIMIT 5
");
$stmt->bind_param("i", $student_id);
$stmt->execute();
$past_result = $stmt->get_result();
while ($row = $past_result->fetch_assoc()) {
    $past_lessons_pending[] = $row;
}
$stmt->close();

// Fetch Favorite Teachers
$favorites = [];
$stmt = $conn->prepare("
    SELECT ft.teacher_id, u.name, u.profile_pic, u.bio, 
           (SELECT COALESCE(AVG(rating), 0) FROM reviews WHERE teacher_id = u.id) as avg_rating,
           (SELECT COUNT(*) FROM reviews WHERE teacher_id = u.id) as review_count
    FROM favorite_teachers ft 
    JOIN users u ON ft.teacher_id = u.id 
    WHERE ft.student_id = ?
");
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
    SELECT DISTINCT u.id, u.name, u.profile_pic, u.bio, 
           (SELECT COALESCE(AVG(rating), 0) FROM reviews WHERE teacher_id = u.id) as avg_rating,
           (SELECT COUNT(*) FROM reviews WHERE teacher_id = u.id) as review_count,
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
    <script>
        function enrollInGroupClass(classId) {
            if (!confirm('Are you sure you want to enroll in this group class?')) {
                return;
            }
            
            fetch('api/group-classes.php?action=enroll', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    class_id: classId
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('Successfully enrolled in group class!');
                    location.reload();
                } else {
                    alert('Error: ' + (data.error || 'Failed to enroll'));
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('An error occurred. Please try again.');
            });
        }
    </script>
</head>
<body class="dashboard-layout">

<?php include __DIR__ . '/app/Views/components/dashboard-header.php'; ?>

<div class="content-wrapper">
    <?php include __DIR__ . '/app/Views/components/dashboard-sidebar.php'; ?>

    <div class="main">
        
        <!-- Overview Tab -->
        <div id="overview" class="tab-content active">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; flex-wrap: wrap; gap: 15px;">
                <h1>Welcome back, <?php echo h($user['name']); ?>! 👋</h1>
                <?php if ($student_track): ?>
                    <span class="badge" style="font-size: 1rem; padding: 8px 20px;">
                        <i class="fas fa-<?php echo $student_track === 'kids' ? 'child' : ($student_track === 'coding' ? 'code' : 'user-graduate'); ?>"></i>
                        <?php echo ucfirst($student_track); ?> Track
                    </span>
                <?php endif; ?>
            </div>
            
            <?php
            // Check student onboarding status
            $has_plan = !empty($user['plan_id']);
            $has_learning_needs = false;
            $learning_needs_stmt = $conn->prepare("SELECT id FROM student_learning_needs WHERE student_id = ? AND completed = 1");
            $learning_needs_stmt->bind_param("i", $student_id);
            $learning_needs_stmt->execute();
            $has_learning_needs = $learning_needs_stmt->get_result()->num_rows > 0;
            $learning_needs_stmt->close();
            
            // Show TODO list if student hasn't completed onboarding
            if (!$has_plan || !$has_learning_needs || !$assigned_teacher):
            ?>
            <div class="card" style="background: linear-gradient(135deg, #fff5f5 0%, #ffffff 100%); border: 2px solid #dc3545; margin-bottom: 30px;">
                <h2 style="color: #dc3545; margin-bottom: 20px;">
                    <i class="fas fa-tasks"></i> Complete Your Setup
                </h2>
                <div style="display: flex; flex-direction: column; gap: 15px;">
                    <?php if (!$has_plan): ?>
                    <div class="todo-item" style="display: flex; align-items: center; gap: 15px; padding: 15px; background: white; border-radius: 8px; border-left: 4px solid #ffc107;">
                        <div style="flex: 1;">
                            <h3 style="margin: 0 0 5px 0; color: #856404;">
                                <i class="fas fa-credit-card"></i> Step 1: Select Your Plan
                            </h3>
                            <p style="margin: 0; color: #666; font-size: 0.9rem;">Choose a subscription plan to get started with your learning journey.</p>
                        </div>
                        <a href="<?php echo $user['learning_track'] ? ($user['learning_track'] . '-plans.php') : 'index.php'; ?>" 
                           class="btn-primary" style="white-space: nowrap;">
                            Select Plan
                        </a>
                    </div>
                    <?php endif; ?>
                    
                    <?php if ($has_plan && !$has_learning_needs): ?>
                    <div class="todo-item" style="display: flex; align-items: center; gap: 15px; padding: 15px; background: white; border-radius: 8px; border-left: 4px solid #0b6cf5;">
                        <div style="flex: 1;">
                            <h3 style="margin: 0 0 5px 0; color: #004080;">
                                <i class="fas fa-user-graduate"></i> Step 2: Add Your Learning Needs
                            </h3>
                            <p style="margin: 0; color: #666; font-size: 0.9rem;">Tell us about your learning goals and preferences so we can assign the perfect teacher for you.</p>
                        </div>
                        <a href="#" onclick="switchTab('learning-needs')" class="btn-primary" style="white-space: nowrap;">
                            Add Needs
                        </a>
                    </div>
                    <?php endif; ?>
                    
                    <?php if ($has_plan && $has_learning_needs && !$assigned_teacher): ?>
                    <div class="todo-item" style="display: flex; align-items: center; gap: 15px; padding: 15px; background: white; border-radius: 8px; border-left: 4px solid #28a745;">
                        <div style="flex: 1;">
                            <h3 style="margin: 0 0 5px 0; color: #155724;">
                                <i class="fas fa-spinner fa-spin"></i> Step 3: Teacher Assignment
                            </h3>
                            <p style="margin: 0; color: #666; font-size: 0.9rem;">We're matching you with the perfect teacher based on your learning needs. This usually takes 24-48 hours.</p>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
            <?php endif; ?>
            
            <?php if ($assigned_teacher): ?>
            <div class="dashboard-card" style="margin-bottom: 30px; padding: 25px; background: linear-gradient(135deg, var(--track-bg, #f0f7ff) 0%, #ffffff 100%);">
                <h2 style="margin-bottom: 20px; color: var(--track-primary, #0b6cf5);">
                    <i class="fas fa-chalkboard-teacher"></i> Your Assigned Teacher
                </h2>
                <div style="display: flex; gap: 20px; align-items: center; flex-wrap: wrap;">
                    <img src="<?php echo htmlspecialchars($assigned_teacher['profile_pic'] ?? getAssetPath('images/placeholder-teacher.svg')); ?>" 
                         alt="<?php echo htmlspecialchars($assigned_teacher['name']); ?>" 
                         style="width: 100px; height: 100px; border-radius: 50%; object-fit: cover; border: 3px solid var(--track-primary, #0b6cf5);">
                    <div style="flex: 1; min-width: 200px;">
                        <h3 style="margin: 0 0 10px 0; color: var(--track-primary, #004080);"><?php echo htmlspecialchars($assigned_teacher['name']); ?></h3>
                        <?php if (!empty($assigned_teacher['bio'])): ?>
                            <p style="color: #666; margin: 0; line-height: 1.6;"><?php echo htmlspecialchars(substr($assigned_teacher['bio'], 0, 150)); ?><?php echo strlen($assigned_teacher['bio']) > 150 ? '...' : ''; ?></p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <?php else: ?>
            <div class="dashboard-card" style="margin-bottom: 30px; padding: 25px; background: #fff3cd; border-left: 4px solid #ffc107;">
                <h3 style="margin: 0; color: #856404;">
                    <i class="fas fa-info-circle"></i> No Teacher Assigned Yet
                </h3>
                <p style="margin: 10px 0 0 0; color: #856404;">Your teacher will be assigned soon. Please contact support if you have questions.</p>
            </div>
            <?php endif; ?>
            
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

            <!-- Recent Activity Section -->
            <?php
            // Get recent activity (recent lessons, assignments, messages)
            $recent_activity = [];
            
            // Recent lessons (last 5)
            $recent_lessons_stmt = $conn->prepare("
                SELECT l.*, u.name as teacher_name, u.profile_pic as teacher_pic, 'lesson' as activity_type
                FROM lessons l
                JOIN users u ON l.teacher_id = u.id
                WHERE l.student_id = ?
                ORDER BY l.lesson_date DESC, l.start_time DESC
                LIMIT 5
            ");
            $recent_lessons_stmt->bind_param("i", $student_id);
            $recent_lessons_stmt->execute();
            $recent_lessons_result = $recent_lessons_stmt->get_result();
            while ($row = $recent_lessons_result->fetch_assoc()) {
                $row['activity_date'] = $row['lesson_date'] . ' ' . $row['start_time'];
                $recent_activity[] = $row;
            }
            $recent_lessons_stmt->close();
            
            // Recent assignments (last 3)
            $recent_assignments_stmt = $conn->prepare("
                SELECT a.*, u.name as teacher_name, 'assignment' as activity_type, a.created_at as activity_date
                FROM assignments a
                JOIN users u ON a.teacher_id = u.id
                WHERE a.student_id = ?
                ORDER BY a.created_at DESC
                LIMIT 3
            ");
            $recent_assignments_stmt->bind_param("i", $student_id);
            $recent_assignments_stmt->execute();
            $recent_assignments_result = $recent_assignments_stmt->get_result();
            while ($row = $recent_assignments_result->fetch_assoc()) {
                $recent_activity[] = $row;
            }
            $recent_assignments_stmt->close();
            
            // Sort by date
            usort($recent_activity, function($a, $b) {
                return strtotime($b['activity_date']) - strtotime($a['activity_date']);
            });
            $recent_activity = array_slice($recent_activity, 0, 5);
            ?>
            
            <?php if (count($recent_activity) > 0): ?>
            <div class="card">
                <h2><i class="fas fa-history"></i> Recent Activity</h2>
                <div style="display: flex; flex-direction: column; gap: 12px;">
                    <?php foreach ($recent_activity as $activity): ?>
                        <div style="display: flex; align-items: center; gap: 15px; padding: 12px; background: #f8f9fa; border-radius: 8px; transition: all 0.2s;" 
                             onmouseover="this.style.background='#f0f7ff'; this.style.transform='translateX(5px)';" 
                             onmouseout="this.style.background='#f8f9fa'; this.style.transform='translateX(0)';">
                            <?php if ($activity['activity_type'] === 'lesson'): ?>
                                <div style="width: 40px; height: 40px; border-radius: 50%; background: #0b6cf5; color: white; display: flex; align-items: center; justify-content: center; flex-shrink: 0;">
                                    <i class="fas fa-calendar-check"></i>
                                </div>
                                <div style="flex: 1;">
                                    <div style="font-weight: 600; color: #333;">
                                        Lesson with <?php echo h($activity['teacher_name']); ?>
                                    </div>
                                    <div style="font-size: 0.85rem; color: #666;">
                                        <?php echo date('M d, Y', strtotime($activity['lesson_date'])); ?> 
                                        at <?php echo date('g:i A', strtotime($activity['start_time'])); ?>
                                        <?php if ($activity['status'] === 'completed'): ?>
                                            <span style="color: #28a745; margin-left: 10px;"><i class="fas fa-check-circle"></i> Completed</span>
                                        <?php elseif (strtotime($activity['lesson_date'] . ' ' . $activity['start_time']) < time()): ?>
                                            <span style="color: #ffc107; margin-left: 10px;"><i class="fas fa-exclamation-circle"></i> Needs Confirmation</span>
                                        <?php else: ?>
                                            <span style="color: #0b6cf5; margin-left: 10px;"><i class="fas fa-clock"></i> Upcoming</span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php else: ?>
                                <div style="width: 40px; height: 40px; border-radius: 50%; background: #ffc107; color: white; display: flex; align-items: center; justify-content: center; flex-shrink: 0;">
                                    <i class="fas fa-tasks"></i>
                                </div>
                                <div style="flex: 1;">
                                    <div style="font-weight: 600; color: #333;">
                                        <?php echo h($activity['title']); ?>
                                    </div>
                                    <div style="font-size: 0.85rem; color: #666;">
                                        From <?php echo h($activity['teacher_name']); ?>
                                        <?php if ($activity['status'] === 'pending'): ?>
                                            <span style="color: #dc3545; margin-left: 10px;"><i class="fas fa-exclamation-circle"></i> Pending</span>
                                        <?php elseif ($activity['status'] === 'submitted'): ?>
                                            <span style="color: #0b6cf5; margin-left: 10px;"><i class="fas fa-check"></i> Submitted</span>
                                        <?php elseif ($activity['status'] === 'graded'): ?>
                                            <span style="color: #28a745; margin-left: 10px;"><i class="fas fa-star"></i> Graded</span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>
            
            <div class="card">
                <h2><i class="fas fa-bolt"></i> Quick Actions</h2>
                <div class="quick-actions" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 15px;">
                    <a href="schedule.php" class="quick-action-btn" style="background: linear-gradient(135deg, #0b6cf5 0%, #004080 100%);">
                        <i class="fas fa-calendar-plus"></i>
                        <span>Book Lesson</span>
                    </a>
                    <a href="message_threads.php" class="quick-action-btn" style="background: linear-gradient(135deg, #28a745 0%, #20c997 100%); position: relative;">
                        <i class="fas fa-comments"></i>
                        <span>Messages</span>
                        <?php if ($unread_messages > 0): ?>
                            <span class="notification-badge" style="position: absolute; top: -5px; right: -5px; background: #dc3545; color: white; border-radius: 50%; width: 20px; height: 20px; display: flex; align-items: center; justify-content: center; font-size: 0.7rem; font-weight: bold;"><?php echo $unread_messages; ?></span>
                        <?php endif; ?>
                    </a>
                    <a href="#" onclick="startAdminChat(event)" class="quick-action-btn" style="background: linear-gradient(135deg, #17a2b8 0%, #138496 100%);">
                        <i class="fas fa-headset"></i>
                        <span>Contact Admin</span>
                    </a>
                    <a href="classroom.php" class="quick-action-btn" style="background: linear-gradient(135deg, #ffc107 0%, #ff9800 100%);">
                        <i class="fas fa-book-open"></i>
                        <span>Classroom</span>
                    </a>
                    <a href="#" onclick="switchTab('goals')" class="quick-action-btn" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);">
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

            <?php if (count($past_lessons_pending) > 0): ?>
            <div class="card" style="background: linear-gradient(135deg, #fff3cd 0%, #ffffff 100%); border: 2px solid #ffc107; margin-bottom: 30px;">
                <h2 style="color: #856404; margin-bottom: 15px;">
                    <i class="fas fa-exclamation-circle"></i> Confirm Past Lessons
                </h2>
                <p style="color: #856404; margin-bottom: 15px; font-size: 0.9rem;">Please confirm your attendance for these completed lessons:</p>
                <?php foreach (array_slice($past_lessons_pending, 0, 3) as $lesson): ?>
                    <div style="background: white; padding: 12px; border-radius: 8px; margin-bottom: 10px; border-left: 4px solid #ffc107; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 10px;">
                        <div style="flex: 1; min-width: 200px;">
                            <strong><?php echo h($lesson['teacher_name']); ?></strong>
                            <div style="font-size: 0.85rem; color: var(--gray); margin-top: 5px;">
                                <i class="fas fa-calendar"></i> <?php echo date('M d, Y', strtotime($lesson['lesson_date'])); ?>
                                <i class="fas fa-clock" style="margin-left: 10px;"></i> <?php echo date('g:i A', strtotime($lesson['start_time'])); ?>
                            </div>
                        </div>
                        <button onclick="showConfirmationModal(<?php echo $lesson['id']; ?>, '<?php echo h($lesson['teacher_name']); ?>', '<?php echo date('M d, Y', strtotime($lesson['lesson_date'])); ?>')" 
                                class="btn-primary btn-sm" style="white-space: nowrap;">
                            <i class="fas fa-check-circle"></i> Confirm
                        </button>
                    </div>
                <?php endforeach; ?>
                <?php if (count($past_lessons_pending) > 3): ?>
                    <a href="#" onclick="switchTab('bookings')" style="color: var(--primary); text-decoration: none; display: block; margin-top: 10px; text-align: center;">
                        View all pending confirmations →
                    </a>
                <?php endif; ?>
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

            <?php if (!empty($group_classes) && $student_track): ?>
            <div class="card">
                <h2><i class="fas fa-users"></i> Available Group Classes</h2>
                <p style="color: #666; margin-bottom: 20px;">Join group classes with other students in your track</p>
                <?php foreach (array_slice($group_classes, 0, 3) as $class): ?>
                    <div class="dashboard-card" style="padding: 20px; margin-bottom: 15px; border-left: 4px solid var(--track-primary, #0b6cf5);">
                        <div style="display: flex; justify-content: space-between; align-items: start; flex-wrap: wrap; gap: 15px;">
                            <div style="flex: 1; min-width: 200px;">
                                <h3 style="margin: 0 0 10px 0; color: var(--track-primary, #004080);">
                                    <?php echo htmlspecialchars($class['title'] ?? 'Group Class'); ?>
                                </h3>
                                <?php if (!empty($class['description'])): ?>
                                    <p style="color: #666; margin: 0 0 10px 0; font-size: 0.9rem;">
                                        <?php echo htmlspecialchars(substr($class['description'], 0, 100)); ?>
                                        <?php echo strlen($class['description']) > 100 ? '...' : ''; ?>
                                    </p>
                                <?php endif; ?>
                                <div style="font-size: 0.85rem; color: #666;">
                                    <?php if (!empty($class['scheduled_date'])): ?>
                                        <i class="fas fa-calendar"></i> <?php echo date('M d, Y', strtotime($class['scheduled_date'])); ?>
                                    <?php endif; ?>
                                    <?php if (!empty($class['scheduled_time'])): ?>
                                        <i class="fas fa-clock" style="margin-left: 15px;"></i> <?php echo date('H:i', strtotime($class['scheduled_time'])); ?>
                                    <?php endif; ?>
                                    <i class="fas fa-user" style="margin-left: 15px;"></i> <?php echo $class['current_enrollment'] ?? 0; ?>/<?php echo $class['max_students'] ?? 10; ?> students
                                </div>
                            </div>
                            <?php if (isset($class['id'])): ?>
                                <button onclick="enrollInGroupClass(<?php echo $class['id']; ?>)" 
                                        class="btn-primary" 
                                        style="white-space: nowrap;"
                                        <?php echo ($class['current_enrollment'] ?? 0) >= ($class['max_students'] ?? 10) ? 'disabled' : ''; ?>>
                                    <i class="fas fa-user-plus"></i> 
                                    <?php echo ($class['current_enrollment'] ?? 0) >= ($class['max_students'] ?? 10) ? 'Full' : 'Enroll'; ?>
                                </button>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
                <a href="#" onclick="switchTab('group-classes')" style="color: var(--primary); text-decoration: none; display: block; margin-top: 10px;">View all group classes →</a>
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

        <!-- Learning Needs Tab -->
        <div id="learning-needs" class="tab-content">
            <div style="background: linear-gradient(135deg, #f0f7ff 0%, #ffffff 100%); padding: 25px; border-radius: 10px; margin-bottom: 30px; border-left: 4px solid #0b6cf5;">
                <h1 style="color: #004080; margin-bottom: 10px;">
                    <i class="fas fa-user-graduate"></i> Add Your Learning Needs
                </h1>
                <p style="color: #666; margin-bottom: 0; font-size: 1.05rem; line-height: 1.6;">
                    Help us match you with the perfect teacher by telling us about your learning goals and preferences. 
                    This information is essential for teacher assignment and will help us create the best learning experience for you.
                </p>
            </div>
            
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px; margin-bottom: 30px;">
                <div style="background: white; padding: 20px; border-radius: 8px; text-align: center; box-shadow: 0 2px 8px rgba(0,0,0,0.1);">
                    <div style="font-size: 2rem; color: #0b6cf5; margin-bottom: 10px;"><i class="fas fa-bullseye"></i></div>
                    <h3 style="margin: 0 0 5px 0; color: #004080; font-size: 1rem;">Set Goals</h3>
                    <p style="margin: 0; color: #666; font-size: 0.85rem;">Define what you want to achieve</p>
                </div>
                <div style="background: white; padding: 20px; border-radius: 8px; text-align: center; box-shadow: 0 2px 8px rgba(0,0,0,0.1);">
                    <div style="font-size: 2rem; color: #28a745; margin-bottom: 10px;"><i class="fas fa-user-tie"></i></div>
                    <h3 style="margin: 0 0 5px 0; color: #004080; font-size: 1rem;">Get Matched</h3>
                    <p style="margin: 0; color: #666; font-size: 0.85rem;">We'll find your perfect teacher</p>
                </div>
                <div style="background: white; padding: 20px; border-radius: 8px; text-align: center; box-shadow: 0 2px 8px rgba(0,0,0,0.1);">
                    <div style="font-size: 2rem; color: #ffc107; margin-bottom: 10px;"><i class="fas fa-calendar-check"></i></div>
                    <h3 style="margin: 0 0 5px 0; color: #004080; font-size: 1rem;">Start Learning</h3>
                    <p style="margin: 0; color: #666; font-size: 0.85rem;">Book your first lesson</p>
                </div>
            </div>
            
            <?php
            // Get existing learning needs if any
            $existing_needs = null;
            $needs_stmt = $conn->prepare("SELECT * FROM student_learning_needs WHERE student_id = ?");
            $needs_stmt->bind_param("i", $student_id);
            $needs_stmt->execute();
            $needs_result = $needs_stmt->get_result();
            if ($needs_result->num_rows > 0) {
                $existing_needs = $needs_result->fetch_assoc();
            }
            $needs_stmt->close();
            ?>
            
            <div class="card" style="box-shadow: 0 4px 15px rgba(0,0,0,0.1);">
                <form method="POST" action="student-dashboard.php#learning-needs" id="learningNeedsForm">
                    <div class="form-group">
                        <label style="font-weight: 600; color: #004080; margin-bottom: 8px; display: block;">
                            <i class="fas fa-graduation-cap"></i> Learning Track *
                        </label>
                        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px; margin-bottom: 20px;">
                            <label style="display: flex; align-items: center; padding: 15px; border: 2px solid #ddd; border-radius: 8px; cursor: pointer; transition: all 0.3s; background: white;" 
                                   onmouseover="this.style.borderColor='#0b6cf5'; this.style.background='#f0f7ff';" 
                                   onmouseout="this.style.borderColor='#ddd'; this.style.background='white';">
                                <input type="radio" name="track" value="kids" required 
                                       <?php echo ($existing_needs && $existing_needs['track'] === 'kids') || $user['learning_track'] === 'kids' ? 'checked' : ''; ?>
                                       style="margin-right: 10px; width: 20px; height: 20px; cursor: pointer;">
                                <div>
                                    <div style="font-weight: 600; color: #004080;"><i class="fas fa-child"></i> Kids</div>
                                    <div style="font-size: 0.85rem; color: #666;">Ages 3-11</div>
                                </div>
                            </label>
                            <label style="display: flex; align-items: center; padding: 15px; border: 2px solid #ddd; border-radius: 8px; cursor: pointer; transition: all 0.3s; background: white;"
                                   onmouseover="this.style.borderColor='#0b6cf5'; this.style.background='#f0f7ff';" 
                                   onmouseout="this.style.borderColor='#ddd'; this.style.background='white';">
                                <input type="radio" name="track" value="adults" required
                                       <?php echo ($existing_needs && $existing_needs['track'] === 'adults') || $user['learning_track'] === 'adults' ? 'checked' : ''; ?>
                                       style="margin-right: 10px; width: 20px; height: 20px; cursor: pointer;">
                                <div>
                                    <div style="font-weight: 600; color: #004080;"><i class="fas fa-user-graduate"></i> Adults</div>
                                    <div style="font-size: 0.85rem; color: #666;">General English</div>
                                </div>
                            </label>
                            <label style="display: flex; align-items: center; padding: 15px; border: 2px solid #ddd; border-radius: 8px; cursor: pointer; transition: all 0.3s; background: white;"
                                   onmouseover="this.style.borderColor='#0b6cf5'; this.style.background='#f0f7ff';" 
                                   onmouseout="this.style.borderColor='#ddd'; this.style.background='white';">
                                <input type="radio" name="track" value="coding" required
                                       <?php echo ($existing_needs && $existing_needs['track'] === 'coding') || $user['learning_track'] === 'coding' ? 'checked' : ''; ?>
                                       style="margin-right: 10px; width: 20px; height: 20px; cursor: pointer;">
                                <div>
                                    <div style="font-weight: 600; color: #004080;"><i class="fas fa-code"></i> Coding</div>
                                    <div style="font-size: 0.85rem; color: #666;">Programming Skills</div>
                                </div>
                            </label>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label>Age Range</label>
                        <input type="text" name="age_range" 
                               value="<?php echo htmlspecialchars($existing_needs['age_range'] ?? $user['age'] ?? ''); ?>" 
                               placeholder="e.g., 8-10 years, Adult">
                    </div>
                    
                    <div class="form-group">
                        <label>Current English Level</label>
                        <select name="current_level">
                            <option value="">Select Level</option>
                            <option value="Beginner" <?php echo ($existing_needs && $existing_needs['current_level'] === 'Beginner') ? 'selected' : ''; ?>>Beginner</option>
                            <option value="Elementary" <?php echo ($existing_needs && $existing_needs['current_level'] === 'Elementary') ? 'selected' : ''; ?>>Elementary</option>
                            <option value="Intermediate" <?php echo ($existing_needs && $existing_needs['current_level'] === 'Intermediate') ? 'selected' : ''; ?>>Intermediate</option>
                            <option value="Upper Intermediate" <?php echo ($existing_needs && $existing_needs['current_level'] === 'Upper Intermediate') ? 'selected' : ''; ?>>Upper Intermediate</option>
                            <option value="Advanced" <?php echo ($existing_needs && $existing_needs['current_level'] === 'Advanced') ? 'selected' : ''; ?>>Advanced</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label style="font-weight: 600; color: #004080; margin-bottom: 8px; display: block;">
                            <i class="fas fa-bullseye"></i> Learning Goals *
                        </label>
                        <textarea name="learning_goals" rows="4" required 
                                  placeholder="What do you want to achieve? (e.g., Improve conversation skills, Prepare for exam, Learn business English, Build confidence in speaking...)"
                                  style="width: 100%; padding: 12px; border: 2px solid #ddd; border-radius: 8px; font-size: 1rem; transition: border-color 0.3s;"
                                  onfocus="this.style.borderColor='#0b6cf5'; this.style.outline='none';"
                                  onblur="this.style.borderColor='#ddd';"><?php echo htmlspecialchars($existing_needs['learning_goals'] ?? ''); ?></textarea>
                        <small style="color: #666; font-size: 0.85rem; margin-top: 5px; display: block;">
                            <i class="fas fa-lightbulb"></i> Be specific! This helps us match you with the right teacher.
                        </small>
                    </div>
                    
                    <div class="form-group">
                        <label style="font-weight: 600; color: #004080; margin-bottom: 8px; display: block;">
                            <i class="fas fa-clock"></i> General Preferred Schedule
                        </label>
                        <textarea name="preferred_schedule" rows="3" 
                                  placeholder="General availability (e.g., Weekdays after 5 PM, Weekends only, Flexible mornings...)"
                                  style="width: 100%; padding: 12px; border: 2px solid #ddd; border-radius: 8px; font-size: 1rem; transition: border-color 0.3s;"
                                  onfocus="this.style.borderColor='#0b6cf5'; this.style.outline='none';"
                                  onblur="this.style.borderColor='#ddd';"><?php echo htmlspecialchars($existing_needs['preferred_schedule'] ?? ''); ?></textarea>
                        <small style="color: #666; font-size: 0.85rem; margin-top: 5px; display: block;">
                            <i class="fas fa-info-circle"></i> Describe your general availability preferences
                        </small>
                    </div>
                    
                    <div class="form-group">
                        <label style="font-weight: 600; color: #004080; margin-bottom: 15px; display: block;">
                            <i class="fas fa-calendar-check"></i> Specific Preferred Time Slots (Optional)
                        </label>
                        <p style="color: #666; font-size: 0.9rem; margin-bottom: 15px;">
                            Select specific days and times when you prefer to have lessons. This helps us match you with teachers who have availability during these times.
                        </p>
                        <div id="preferred-times-container" style="border: 2px dashed #ddd; border-radius: 8px; padding: 20px; background: #f9f9f9; margin-bottom: 15px;">
                            <?php
                            // Load existing preferred times
                            $existing_times = [];
                            if ($existing_needs) {
                                $times_stmt = $conn->prepare("SELECT * FROM preferred_times WHERE student_id = ? ORDER BY day_of_week, start_time");
                                $times_stmt->bind_param("i", $student_id);
                                $times_stmt->execute();
                                $times_result = $times_stmt->get_result();
                                while ($time = $times_result->fetch_assoc()) {
                                    $existing_times[] = $time;
                                }
                                $times_stmt->close();
                            }
                            ?>
                            <div id="preferred-times-list">
                                <?php if (count($existing_times) > 0): ?>
                                    <?php foreach ($existing_times as $idx => $time): ?>
                                        <div class="preferred-time-row" style="display: grid; grid-template-columns: 2fr 1fr 1fr auto; gap: 10px; align-items: center; margin-bottom: 10px; padding: 10px; background: white; border-radius: 6px; border: 1px solid #ddd;">
                                            <select name="preferred_times[<?php echo $idx; ?>][day]" class="day-select" required style="padding: 8px; border: 1px solid #ddd; border-radius: 6px;">
                                                <option value="">Select Day</option>
                                                <option value="Monday" <?php echo $time['day_of_week'] === 'Monday' ? 'selected' : ''; ?>>Monday</option>
                                                <option value="Tuesday" <?php echo $time['day_of_week'] === 'Tuesday' ? 'selected' : ''; ?>>Tuesday</option>
                                                <option value="Wednesday" <?php echo $time['day_of_week'] === 'Wednesday' ? 'selected' : ''; ?>>Wednesday</option>
                                                <option value="Thursday" <?php echo $time['day_of_week'] === 'Thursday' ? 'selected' : ''; ?>>Thursday</option>
                                                <option value="Friday" <?php echo $time['day_of_week'] === 'Friday' ? 'selected' : ''; ?>>Friday</option>
                                                <option value="Saturday" <?php echo $time['day_of_week'] === 'Saturday' ? 'selected' : ''; ?>>Saturday</option>
                                                <option value="Sunday" <?php echo $time['day_of_week'] === 'Sunday' ? 'selected' : ''; ?>>Sunday</option>
                                            </select>
                                            <input type="time" name="preferred_times[<?php echo $idx; ?>][start]" value="<?php echo date('H:i', strtotime($time['start_time'])); ?>" required style="padding: 8px; border: 1px solid #ddd; border-radius: 6px;">
                                            <input type="time" name="preferred_times[<?php echo $idx; ?>][end]" value="<?php echo date('H:i', strtotime($time['end_time'])); ?>" required style="padding: 8px; border: 1px solid #ddd; border-radius: 6px;">
                                            <button type="button" onclick="removePreferredTime(this)" class="btn-danger" style="padding: 8px 12px; border: none; border-radius: 6px; cursor: pointer;">
                                                <i class="fas fa-times"></i>
                                            </button>
                                        </div>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </div>
                            <button type="button" onclick="addPreferredTime()" class="btn-outline" style="margin-top: 10px;">
                                <i class="fas fa-plus"></i> Add Time Slot
                            </button>
                        </div>
                        <small style="color: #666; font-size: 0.85rem; display: block;">
                            <i class="fas fa-lightbulb"></i> You can add multiple time slots. Leave empty if you prefer general availability only.
                        </small>
                    </div>
                    
                    <div class="form-group">
                        <label>Special Requirements or Notes</label>
                        <textarea name="special_requirements" rows="3" 
                                  placeholder="Any specific requirements, learning style preferences, or additional information..."
                                  style="width: 100%; padding: 12px; border: 2px solid #ddd; border-radius: 8px; font-size: 1rem; transition: border-color 0.3s;"
                                  onfocus="this.style.borderColor='#0b6cf5'; this.style.outline='none';"
                                  onblur="this.style.borderColor='#ddd';"><?php echo htmlspecialchars($existing_needs['special_requirements'] ?? ''); ?></textarea>
                    </div>
                    
                    <div style="display: flex; gap: 15px; align-items: center; margin-top: 25px; padding-top: 25px; border-top: 2px solid #f0f0f0;">
                        <button type="submit" name="submit_learning_needs" class="btn-primary" style="flex: 1; padding: 15px; font-size: 1.1rem; font-weight: 600;">
                            <i class="fas fa-paper-plane"></i> Submit Learning Needs
                        </button>
                        <?php if ($existing_needs): ?>
                            <span style="color: #666; font-size: 0.9rem;">
                                <i class="fas fa-info-circle"></i> Updating your information will help us better match you
                            </span>
                        <?php endif; ?>
                    </div>
                </form>
            </div>
            
            <?php if (!$existing_needs): ?>
            <div class="card" style="background: #fff3cd; border-left: 4px solid #ffc107; margin-top: 20px;">
                <h3 style="color: #856404; margin-bottom: 10px;">
                    <i class="fas fa-question-circle"></i> Why do we need this?
                </h3>
                <ul style="color: #856404; margin: 0; padding-left: 20px; line-height: 1.8;">
                    <li>Match you with a teacher who specializes in your learning goals</li>
                    <li>Create personalized lesson plans tailored to your needs</li>
                    <li>Ensure the best possible learning experience</li>
                    <li>Help teachers prepare appropriate materials and activities</li>
                </ul>
            </div>
            <?php endif; ?>
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
                        <?php 
                        // Only show profile link if teacher is assigned to student
                        $is_assigned = ($assigned_teacher && $assigned_teacher['id'] == $teacher['id']) || 
                                      ($user['assigned_teacher_id'] == $teacher['id']);
                        if ($is_assigned): ?>
                            <a href="profile.php?id=<?php echo $teacher['id']; ?>" class="btn-outline btn-sm">View Profile</a>
                        <?php endif; ?>
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
            
            <?php if (count($past_lessons_pending) > 0): ?>
            <div class="card" style="background: linear-gradient(135deg, #fff3cd 0%, #ffffff 100%); border: 2px solid #ffc107; margin-bottom: 30px;">
                <h2 style="color: #856404; margin-bottom: 20px;">
                    <i class="fas fa-exclamation-circle"></i> Confirm Past Lessons
                </h2>
                <p style="color: #856404; margin-bottom: 20px;">Please confirm your attendance for these completed lessons:</p>
                <?php foreach ($past_lessons_pending as $lesson): ?>
                    <div class="booking-item" style="background: white; padding: 15px; border-radius: 8px; margin-bottom: 15px; border-left: 4px solid #ffc107;">
                        <div style="display: flex; gap: 15px; align-items: center; flex-wrap: wrap;">
                            <img src="<?php echo h($lesson['teacher_pic']); ?>" alt="" style="width: 50px; height: 50px; border-radius: 50%; object-fit: cover;" onerror="this.src='<?php echo getAssetPath('images/placeholder-teacher.svg'); ?>'">
                            <div style="flex: 1; min-width: 200px;">
                                <strong><?php echo h($lesson['teacher_name']); ?></strong>
                                <div style="font-size: 0.85rem; color: var(--gray); margin-top: 5px;">
                                    <i class="fas fa-calendar"></i> <?php echo date('l, F d, Y', strtotime($lesson['lesson_date'])); ?>
                                    <i class="fas fa-clock" style="margin-left: 15px;"></i> <?php echo date('g:i A', strtotime($lesson['start_time'])); ?> - <?php echo date('g:i A', strtotime($lesson['end_time'])); ?>
                                </div>
                            </div>
                            <button onclick="showConfirmationModal(<?php echo $lesson['id']; ?>, '<?php echo h($lesson['teacher_name']); ?>', '<?php echo date('M d, Y', strtotime($lesson['lesson_date'])); ?>')" 
                                    class="btn-primary" style="white-space: nowrap;">
                                <i class="fas fa-check-circle"></i> Confirm Attendance
                            </button>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
            
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
                                <?php 
                                // Only show profile link if teacher is assigned to student
                                $is_assigned_lesson = ($assigned_teacher && $assigned_teacher['id'] == $lesson['teacher_id']) || 
                                                      ($user['assigned_teacher_id'] == $lesson['teacher_id']);
                                if ($is_assigned_lesson): ?>
                                    <a href="profile.php?id=<?php echo $lesson['teacher_id']; ?>" class="btn-outline btn-sm">View Teacher</a>
                                <?php endif; ?>
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

// Lesson Confirmation Modal
function showConfirmationModal(lessonId, teacherName, lessonDate) {
    const modal = document.getElementById('confirmationModal');
    if (!modal) {
        createConfirmationModal();
    }
    document.getElementById('modalLessonId').value = lessonId;
    document.getElementById('modalTeacherName').textContent = teacherName;
    document.getElementById('modalLessonDate').textContent = lessonDate;
    document.getElementById('confirmationModal').classList.add('active');
}

function closeConfirmationModal() {
    document.getElementById('confirmationModal').classList.remove('active');
    // Reset form
    const form = document.getElementById('confirmationForm');
    if (form) {
        form.reset();
        document.getElementById('studentNotes').value = '';
    }
}

function createConfirmationModal() {
    const modal = document.createElement('div');
    modal.id = 'confirmationModal';
    modal.className = 'modal-overlay';
    modal.innerHTML = `
        <div class="modal" style="max-width: 500px;">
            <div class="modal-header">
                <h3><i class="fas fa-check-circle"></i> Confirm Lesson Attendance</h3>
                <button class="modal-close" onclick="closeConfirmationModal()">&times;</button>
            </div>
            <form id="confirmationForm" onsubmit="submitConfirmation(event)">
                <input type="hidden" id="modalLessonId" name="lesson_id">
                <div style="padding: 20px;">
                    <p style="margin-bottom: 20px;">
                        <strong>Teacher:</strong> <span id="modalTeacherName"></span><br>
                        <strong>Date:</strong> <span id="modalLessonDate"></span>
                    </p>
                    
                    <div class="form-group">
                        <label>Attendance Status *</label>
                        <select name="attendance_status" id="attendanceStatus" required>
                            <option value="attended">I Attended</option>
                            <option value="no_show">I Did Not Attend</option>
                            <option value="cancelled">Lesson Was Cancelled</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label>Notes (Optional)</label>
                        <textarea name="student_notes" id="studentNotes" rows="3" placeholder="Any additional notes about this lesson..."></textarea>
                    </div>
                    
                    <div style="display: flex; gap: 10px; justify-content: flex-end; margin-top: 20px;">
                        <button type="button" class="btn-outline" onclick="closeConfirmationModal()">Cancel</button>
                        <button type="submit" class="btn-primary">
                            <i class="fas fa-check"></i> Confirm
                        </button>
                    </div>
                </div>
            </form>
        </div>
    `;
    document.body.appendChild(modal);
}

function submitConfirmation(event) {
    event.preventDefault();
    const form = event.target;
    const formData = new FormData(form);
    formData.append('action', 'confirm');
    
    const submitBtn = form.querySelector('button[type="submit"]');
    const originalText = submitBtn.innerHTML;
    submitBtn.disabled = true;
    submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Confirming...';
    
    fetch('api/lesson-confirmation.php', {
        method: 'POST',
        body: formData
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            if (typeof toast !== 'undefined') {
                toast.success(data.message || 'Attendance confirmed successfully!');
            } else {
                alert(data.message || 'Attendance confirmed successfully!');
            }
            closeConfirmationModal();
            // Reload page to update UI
            setTimeout(() => location.reload(), 1000);
        } else {
            if (typeof toast !== 'undefined') {
                toast.error(data.error || 'Failed to confirm attendance');
            } else {
                alert(data.error || 'Failed to confirm attendance');
            }
            submitBtn.disabled = false;
            submitBtn.innerHTML = originalText;
        }
    })
    .catch(err => {
        console.error('Error:', err);
        if (typeof toast !== 'undefined') {
            toast.error('An error occurred. Please try again.');
        } else {
            alert('An error occurred. Please try again.');
        }
        submitBtn.disabled = false;
        submitBtn.innerHTML = originalText;
    });
}

async function startAdminChat(event) {
    if (event) event.preventDefault();
    
    try {
        const response = await fetch('api/start-admin-chat.php');
        const data = await response.json();
        
        if (data.success) {
            window.location.href = data.redirect_url;
        } else {
            if (typeof toast !== 'undefined') {
                toast.error(data.error || 'Failed to start chat');
            } else {
                alert('Error: ' + (data.error || 'Failed to start chat'));
            }
        }
    } catch (error) {
        console.error('Error:', error);
        if (typeof toast !== 'undefined') {
            toast.error('An error occurred. Please try again.');
        } else {
            alert('An error occurred. Please try again.');
        }
    }
}

// Preferred Times Management
let preferredTimeIndex = <?php echo (isset($existing_times) && is_array($existing_times) && count($existing_times) > 0) ? count($existing_times) : 0; ?>;

function addPreferredTime() {
    const container = document.getElementById('preferred-times-list');
    if (!container) return;
    
    const row = document.createElement('div');
    row.className = 'preferred-time-row';
    row.style.cssText = 'display: grid; grid-template-columns: 2fr 1fr 1fr auto; gap: 10px; align-items: center; margin-bottom: 10px; padding: 10px; background: white; border-radius: 6px; border: 1px solid #ddd;';
    
    row.innerHTML = `
        <select name="preferred_times[${preferredTimeIndex}][day]" class="day-select" required style="padding: 8px; border: 1px solid #ddd; border-radius: 6px;">
            <option value="">Select Day</option>
            <option value="Monday">Monday</option>
            <option value="Tuesday">Tuesday</option>
            <option value="Wednesday">Wednesday</option>
            <option value="Thursday">Thursday</option>
            <option value="Friday">Friday</option>
            <option value="Saturday">Saturday</option>
            <option value="Sunday">Sunday</option>
        </select>
        <input type="time" name="preferred_times[${preferredTimeIndex}][start]" required style="padding: 8px; border: 1px solid #ddd; border-radius: 6px;">
        <input type="time" name="preferred_times[${preferredTimeIndex}][end]" required style="padding: 8px; border: 1px solid #ddd; border-radius: 6px;">
        <button type="button" onclick="removePreferredTime(this)" class="btn-danger" style="padding: 8px 12px; border: none; border-radius: 6px; cursor: pointer; background: #dc3545; color: white;">
            <i class="fas fa-times"></i>
        </button>
    `;
    
    container.appendChild(row);
    preferredTimeIndex++;
}

function removePreferredTime(button) {
    const row = button.closest('.preferred-time-row');
    if (row) {
        row.remove();
    }
}
</script>

<style>
.modal-overlay {
    display: none;
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0, 0, 0, 0.5);
    z-index: 10000;
    align-items: center;
    justify-content: center;
}
.modal-overlay.active {
    display: flex;
}
.modal {
    background: white;
    border-radius: 10px;
    box-shadow: 0 10px 40px rgba(0,0,0,0.2);
    max-width: 90%;
    max-height: 90vh;
    overflow: auto;
}
.modal-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 20px;
    border-bottom: 1px solid #eee;
}
.modal-header h3 {
    margin: 0;
    color: #004080;
}
.modal-close {
    background: none;
    border: none;
    font-size: 1.5rem;
    color: #666;
    cursor: pointer;
    padding: 0;
    width: 30px;
    height: 30px;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 50%;
    transition: all 0.2s;
}
.modal-close:hover {
    background: #f0f0f0;
    color: #000;
}
</style>

</body>
</html>
