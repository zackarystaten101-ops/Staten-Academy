<?php
// Start output buffering to prevent headers already sent errors
ob_start();
session_start();
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/app/Views/components/dashboard-functions.php';

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'teacher') {
    ob_end_clean();
    header("Location: login.php");
    exit();
}

$teacher_id = $_SESSION['user_id'];
$user = getUserById($conn, $teacher_id);
$user_role = 'teacher';

// Load models for assigned students and group classes
require_once __DIR__ . '/app/Models/TeacherAssignment.php';
require_once __DIR__ . '/app/Models/GroupClass.php';
$assignmentModel = new TeacherAssignment($conn);
$groupClassModel = new GroupClass($conn);

// Get assigned students
$assigned_students = $assignmentModel->getTeacherStudents($teacher_id);

// Get teacher's track(s) from assigned students
$teacher_tracks = [];
foreach ($assigned_students as $student) {
    if (!empty($student['learning_track']) && !in_array($student['learning_track'], $teacher_tracks)) {
        $teacher_tracks[] = $student['learning_track'];
    }
}

// Get group classes for teacher's tracks
$group_classes = [];
if (!empty($teacher_tracks)) {
    foreach ($teacher_tracks as $track) {
        $track_classes = $groupClassModel->getTrackClasses($track);
        $group_classes = array_merge($group_classes, $track_classes);
    }
}
// Filter to only show classes taught by this teacher
$group_classes = array_filter($group_classes, function($class) use ($teacher_id) {
    return $class['teacher_id'] == $teacher_id;
});

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
    $upload_error = null;
    
    // Handle file upload with comprehensive error checking
    if (isset($_FILES['profile_pic_file'])) {
        $file = $_FILES['profile_pic_file'];
        
        // Check for upload errors first
        if ($file['error'] !== UPLOAD_ERR_OK) {
            switch ($file['error']) {
                case UPLOAD_ERR_INI_SIZE:
                case UPLOAD_ERR_FORM_SIZE:
                    $upload_error = "File size exceeds the maximum allowed limit. Images: 5MB max, Videos: 100MB max.";
                    break;
                case UPLOAD_ERR_PARTIAL:
                    $upload_error = "File was only partially uploaded. Please try again.";
                    break;
                case UPLOAD_ERR_NO_FILE:
                    // No file uploaded, skip processing
                    break;
                case UPLOAD_ERR_NO_TMP_DIR:
                    $upload_error = "Server configuration error: Temporary folder missing. Please contact administrator.";
                    break;
                case UPLOAD_ERR_CANT_WRITE:
                    $upload_error = "Failed to write file to disk. Please check server permissions.";
                    break;
                case UPLOAD_ERR_EXTENSION:
                    $upload_error = "File upload was stopped by PHP extension. Please try a different file.";
                    break;
                default:
                    $upload_error = "Unknown upload error occurred. Please try again.";
            }
        } elseif ($file['error'] === UPLOAD_ERR_OK) {
            // File uploaded successfully, now validate
            $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            $image_extensions = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
            $video_extensions = ['mp4', 'mov', 'avi', 'webm'];
            $allowed = array_merge($image_extensions, $video_extensions);
            
            // Determine file type and size limit
            $is_image = in_array($ext, $image_extensions);
            $is_video = in_array($ext, $video_extensions);
            $max_size = $is_image ? (5 * 1024 * 1024) : (100 * 1024 * 1024); // 5MB for images, 100MB for videos
            
            // Validate extension
            if (!in_array($ext, $allowed)) {
                $upload_error = "Invalid file type. Allowed: Images (JPG, PNG, GIF, WEBP) or Videos (MP4, MOV, AVI, WEBM).";
            }
            // Validate MIME type
            elseif ($is_image && strpos($file['type'], 'image/') !== 0) {
                $upload_error = "Invalid image file. Please upload a valid image file.";
            }
            elseif ($is_video && strpos($file['type'], 'video/') !== 0) {
                $upload_error = "Invalid video file. Please upload a valid video file.";
            }
            // Validate file size
            elseif ($file['size'] > $max_size) {
                $file_type = $is_image ? 'Image' : 'Video';
                $size_limit = $is_image ? '5MB' : '100MB';
                $upload_error = "$file_type file size exceeds the maximum allowed limit ($size_limit).";
            }
            // All validations passed, process upload
            else {
                $filename = 'pending_' . $teacher_id . '_' . time() . '.' . $ext;
                
                // Determine upload directory - works for both localhost and cPanel
                $upload_base = __DIR__;
                $public_images_dir = $upload_base . DIRECTORY_SEPARATOR . 'public' . DIRECTORY_SEPARATOR . 'assets' . DIRECTORY_SEPARATOR . 'images';
                $flat_images_dir = $upload_base . DIRECTORY_SEPARATOR . 'assets' . DIRECTORY_SEPARATOR . 'images';
                
                // Check which directory structure exists
                if (is_dir($public_images_dir)) {
                    $target_dir = $public_images_dir;
                } elseif (is_dir($flat_images_dir)) {
                    $target_dir = $flat_images_dir;
                } else {
                    // Create directory - prefer flat structure for cPanel
                    $target_dir = is_dir($upload_base . DIRECTORY_SEPARATOR . 'public') ? $public_images_dir : $flat_images_dir;
                    
                    // Check if parent directory is writable before creating
                    $parent_dir = dirname($target_dir);
                    if (!is_dir($parent_dir)) {
                        $upload_error = "Parent directory does not exist. Please check server configuration.";
                        error_log("Parent directory missing: " . $parent_dir);
                    } elseif (!is_writable($parent_dir) && !@chmod($parent_dir, 0755)) {
                        $upload_error = "Cannot create upload directory. Parent directory is not writable.";
                        error_log("Parent directory not writable: " . $parent_dir);
                    } elseif (!@mkdir($target_dir, 0755, true)) {
                        $upload_error = "Failed to create images directory. Please check server permissions.";
                        error_log("Failed to create images directory: " . $target_dir);
                        error_log("Parent directory: " . dirname($target_dir) . " (writable: " . (is_writable(dirname($target_dir)) ? 'yes' : 'no') . ")");
                    }
                }
                
                // Ensure directory exists and is writable
                if (!isset($upload_error) && isset($target_dir)) {
                    if (!is_dir($target_dir)) {
                        $upload_error = "Upload directory does not exist. Please contact administrator.";
                        error_log("Target directory missing: " . $target_dir);
                    } elseif (!is_writable($target_dir)) {
                        // Try to fix permissions
                        @chmod($target_dir, 0755);
                        if (!is_writable($target_dir)) {
                            $upload_error = "Upload directory is not writable. Please contact administrator.";
                            error_log("Upload directory not writable: " . $target_dir);
                            error_log("Current permissions: " . substr(sprintf('%o', fileperms($target_dir)), -4));
                        }
                    }
                    
                    // Proceed with file upload if no errors so far
                    if (!isset($upload_error) && is_writable($target_dir)) {
                        $target_path = $target_dir . DIRECTORY_SEPARATOR . $filename;
                        
                        // Verify the uploaded file is valid
                        if (!is_uploaded_file($file['tmp_name'])) {
                            $upload_error = "Invalid upload detected. Security check failed.";
                            error_log("Security check failed for: " . $file['tmp_name']);
                        } elseif (!@move_uploaded_file($file['tmp_name'], $target_path)) {
                            $upload_error = "Failed to save uploaded file. Please try again or contact administrator.";
                            error_log("Failed to move uploaded file: " . $file['tmp_name'] . " to " . $target_path);
                            error_log("Source exists: " . (file_exists($file['tmp_name']) ? 'yes' : 'no'));
                            error_log("Target directory writable: " . (is_writable($target_dir) ? 'yes' : 'no'));
                        } else {
                            // Verify file was actually written
                            if (!file_exists($target_path)) {
                                $upload_error = "File upload failed. File was not saved.";
                                error_log("File not found after move: " . $target_path);
                            } else {
                                // Set proper permissions and verify
                                @chmod($target_path, 0644);
                                if (!is_readable($target_path)) {
                                    $upload_error = "File uploaded but cannot be read. Please contact administrator.";
                                    error_log("File not readable after upload: " . $target_path);
                                } else {
                                    // Success! Update profile picture path
                                    $profile_pic_pending = '/assets/images/' . $filename;
                                    error_log("Successfully uploaded: " . $filename . " (" . round($file['size'] / 1024 / 1024, 2) . " MB)");
                                }
                            }
                        }
                    }
                }
            }
        }
    }

    // Only proceed if there's no upload error
    if (!isset($upload_error)) {
        // Submit to pending_updates table for admin approval
        $check_stmt = $conn->prepare("SELECT id FROM pending_updates WHERE user_id = ?");
        $check_stmt->bind_param("i", $teacher_id);
        $check_stmt->execute();
        $check = $check_stmt->get_result();
        if ($check && $check->num_rows > 0) {
            $stmt = $conn->prepare("UPDATE pending_updates SET name = ?, bio = ?, profile_pic = ?, about_text = ?, video_url = ?, requested_at = NOW() WHERE user_id = ?");
            $stmt->bind_param("sssssi", $_SESSION['user_name'], $bio, $profile_pic_pending, $about_text, $video_url, $teacher_id);
        } else {
            $stmt = $conn->prepare("INSERT INTO pending_updates (user_id, name, bio, profile_pic, about_text, video_url) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("isssss", $teacher_id, $_SESSION['user_name'], $bio, $profile_pic_pending, $about_text, $video_url);
        }
        $stmt->execute();
        $stmt->close();
        
        $msg = "Profile changes submitted for approval.";
    } else {
        // Upload failed, don't update database
        $msg = null; // Clear success message if upload failed
    }
    
    // Update fields that don't need approval (only if no upload error)
    if (!isset($upload_error)) {
        $stmt = $conn->prepare("UPDATE users SET dob = ?, calendly_link = ?, backup_email = ?, age = ?, age_visibility = ?, specialty = ?, hourly_rate = ? WHERE id = ?");
        $stmt->bind_param("sssisidi", $dob, $calendly, $backup_email, $age, $age_visibility, $specialty, $hourly_rate, $teacher_id);
        $stmt->execute();
        $stmt->close();
    }
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
$student_count = count($assigned_students); // Use assigned students count
$pending_assignments = getPendingAssignmentsCount($conn, $teacher_id);

// Use assigned students instead of students from lessons
$students = [];
foreach ($assigned_students as $assignment) {
    if (!isset($assignment['student_id'])) continue;
    
    $student_id = $assignment['student_id'];
    $stmt = $conn->prepare("
        SELECT u.id, u.name, u.email, u.profile_pic, u.learning_track,
               (SELECT COUNT(*) FROM lessons WHERE student_id = u.id AND teacher_id = ?) as lesson_count,
               (SELECT note FROM lesson_notes WHERE student_id = u.id AND teacher_id = ? ORDER BY created_at DESC LIMIT 1) as last_note
        FROM users u 
        WHERE u.id = ?
    ");
    $stmt->bind_param("iii", $teacher_id, $teacher_id, $student_id);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($row = $result->fetch_assoc()) {
        $row['track'] = $assignment['track'] ?? $row['learning_track'] ?? null;
        $row['assigned_at'] = $assignment['assigned_at'] ?? null;
        $students[] = $row;
    }
    $stmt->close();
}

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
$res_stmt = $conn->prepare("SELECT * FROM teacher_resources WHERE teacher_id = ? ORDER BY created_at DESC");
$res_stmt->bind_param("i", $teacher_id);
$res_stmt->execute();
$res_result = $res_stmt->get_result();
if ($res_result) {
    while ($row = $res_result->fetch_assoc()) {
        $resources[] = $row;
    }
}

// Fetch Classroom Materials
$materials = $conn->query("SELECT * FROM classroom_materials ORDER BY created_at DESC");

// Fetch Upcoming Lessons for teacher
$upcoming_lessons = [];
$stmt = $conn->prepare("
    SELECT l.*, u.name as student_name, u.profile_pic as student_pic
    FROM lessons l
    JOIN users u ON l.student_id = u.id
    WHERE l.teacher_id = ? AND l.lesson_date >= CURDATE() AND l.status = 'scheduled'
    ORDER BY l.lesson_date ASC, l.start_time ASC
    LIMIT 10
");
$stmt->bind_param("i", $teacher_id);
$stmt->execute();
$lessons_result = $stmt->get_result();
while ($row = $lessons_result->fetch_assoc()) {
    $upcoming_lessons[] = $row;
}
$stmt->close();

// Get unread messages count
$unread_messages = 0;
if (function_exists('getUnreadMessagesCount')) {
    $unread_messages = getUnreadMessagesCount($conn, $teacher_id);
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
    <title>Teacher Dashboard - Staten Academy</title>
    <link rel="stylesheet" href="<?php echo getAssetPath('styles.css'); ?>">
    <link rel="stylesheet" href="<?php echo getAssetPath('css/dashboard.css'); ?>">
    <link rel="stylesheet" href="<?php echo getAssetPath('css/mobile.css'); ?>">
    <!-- MODERN SHADOWS - To disable, comment out the line below -->
    <link rel="stylesheet" href="<?php echo getAssetPath('css/modern-shadows.css'); ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <script src="<?php echo getAssetPath('js/toast.js'); ?>" defer></script>
    <script>
        function createGroupClass(event) {
            event.preventDefault();
            const form = event.target;
            const formData = new FormData(form);
            
            fetch('api/group-classes.php?action=create', {
                method: 'POST',
                body: JSON.stringify({
                    track: formData.get('track'),
                    teacher_id: <?php echo $teacher_id; ?>,
                    scheduled_date: formData.get('scheduled_date'),
                    scheduled_time: formData.get('scheduled_time'),
                    duration: parseInt(formData.get('duration')),
                    max_students: parseInt(formData.get('max_students')),
                    title: formData.get('title'),
                    description: formData.get('description')
                }),
                headers: {
                    'Content-Type': 'application/json'
                }
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('Group class created successfully!');
                    location.reload();
                } else {
                    alert('Error: ' + (data.error || 'Failed to create group class'));
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('An error occurred. Please try again.');
            });
        }
        
        function viewGroupClassStudents(classId) {
            fetch('api/group-classes.php?action=details&class_id=' + classId)
                .then(response => response.json())
                .then(data => {
                    if (data.success && data.class.students) {
                        const students = data.class.students;
                        let message = 'Enrolled Students:\n\n';
                        if (students.length > 0) {
                            students.forEach((s, i) => {
                                message += (i + 1) + '. ' + s.name + ' (' + s.email + ')\n';
                            });
                        } else {
                            message += 'No students enrolled yet.';
                        }
                        alert(message);
                    } else {
                        alert('Error loading students');
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('An error occurred');
                });
        }
        
        function updateGroupClassStatus(classId, status) {
            if (!confirm('Are you sure you want to ' + status + ' this class?')) {
                return;
            }
            
            fetch('api/group-classes.php?action=update-status', {
                method: 'POST',
                body: JSON.stringify({
                    class_id: classId,
                    status: status
                }),
                headers: {
                    'Content-Type': 'application/json'
                }
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('Class status updated!');
                    location.reload();
                } else {
                    alert('Error: ' + (data.error || 'Failed to update status'));
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('An error occurred');
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
            <h1>Welcome back, <?php echo h($user['name']); ?>! 👋</h1>
            
            <?php if (isset($msg)): ?>
                <div class="alert-success"><i class="fas fa-check-circle"></i> <?php echo $msg; ?></div>
            <?php endif; ?>
            
            <?php
            // Check teacher onboarding status
            $has_calendar_setup = !empty($user['google_calendar_token']);
            $has_profile_complete = !empty($user['bio']) && !empty($user['profile_pic']);
            $has_students = count($assigned_students) > 0;
            
            // Get today's lessons
            $today_lessons = [];
            $today_stmt = $conn->prepare("
                SELECT l.*, u.name as student_name, u.profile_pic as student_pic
                FROM lessons l
                JOIN users u ON l.student_id = u.id
                WHERE l.teacher_id = ? 
                AND l.lesson_date = CURDATE()
                AND l.status = 'scheduled'
                ORDER BY l.start_time ASC
            ");
            $today_stmt->bind_param("i", $teacher_id);
            $today_stmt->execute();
            $today_result = $today_stmt->get_result();
            while ($row = $today_result->fetch_assoc()) {
                $today_lessons[] = $row;
            }
            $today_stmt->close();
            
            // Show onboarding checklist if not complete
            if (!$has_calendar_setup || !$has_profile_complete): ?>
            <div class="card" style="background: linear-gradient(135deg, #fff3cd 0%, #ffffff 100%); border: 2px solid #ffc107; margin-bottom: 30px;">
                <h2 style="color: #856404; margin-bottom: 20px;">
                    <i class="fas fa-clipboard-check"></i> Complete Your Teacher Setup
                </h2>
                <div style="display: flex; flex-direction: column; gap: 15px;">
                    <?php if (!$has_calendar_setup): ?>
                    <div class="todo-item" style="display: flex; align-items: center; gap: 15px; padding: 15px; background: white; border-radius: 8px; border-left: 4px solid #ffc107;">
                        <div style="flex: 1;">
                            <h3 style="margin: 0 0 5px 0; color: #856404;">
                                <i class="fas fa-calendar-alt"></i> Step 1: Set Up Your Calendar
                            </h3>
                            <p style="margin: 0; color: #666; font-size: 0.9rem;">Connect your Google Calendar and set your availability so students can book lessons with you.</p>
                        </div>
                        <a href="teacher-calendar-setup.php" class="btn-primary" style="white-space: nowrap;">
                            Set Up Calendar
                        </a>
                    </div>
                    <?php endif; ?>
                    
                    <?php if (!$has_profile_complete): ?>
                    <div class="todo-item" style="display: flex; align-items: center; gap: 15px; padding: 15px; background: white; border-radius: 8px; border-left: 4px solid #0b6cf5;">
                        <div style="flex: 1;">
                            <h3 style="margin: 0 0 5px 0; color: #004080;">
                                <i class="fas fa-user-edit"></i> Step 2: Complete Your Profile
                            </h3>
                            <p style="margin: 0; color: #666; font-size: 0.9rem;">Add your bio, profile picture, and teaching information to help students get to know you.</p>
                        </div>
                        <a href="#" onclick="switchTab('profile')" class="btn-primary" style="white-space: nowrap;">
                            Edit Profile
                        </a>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
            <?php endif; ?>
            
            <?php if (count($today_lessons) > 0): ?>
            <div class="card" style="background: linear-gradient(135deg, #e8f5e9 0%, #ffffff 100%); border: 2px solid #28a745; margin-bottom: 30px;">
                <h2 style="color: #155724; margin-bottom: 20px;">
                    <i class="fas fa-calendar-day"></i> Today's Schedule
                </h2>
                <div style="display: flex; flex-direction: column; gap: 12px;">
                    <?php foreach ($today_lessons as $lesson): ?>
                        <?php
                        $lesson_time = strtotime($lesson['lesson_date'] . ' ' . $lesson['start_time']);
                        $current_time = time();
                        $can_join = $lesson_time <= ($current_time + 3600); // Can join 1 hour before
                        $is_now = $lesson_time <= $current_time && strtotime($lesson['lesson_date'] . ' ' . $lesson['end_time']) >= $current_time;
                        ?>
                        <div style="display: flex; align-items: center; gap: 15px; padding: 15px; background: white; border-radius: 8px; border-left: 4px solid <?php echo $is_now ? '#28a745' : '#0b6cf5'; ?>; <?php echo $is_now ? 'box-shadow: 0 4px 15px rgba(40, 167, 69, 0.3);' : ''; ?>">
                            <img src="<?php echo h($lesson['student_pic']); ?>" alt="" style="width: 50px; height: 50px; border-radius: 50%; object-fit: cover;" onerror="this.src='<?php echo getAssetPath('images/placeholder-teacher.svg'); ?>'">
                            <div style="flex: 1;">
                                <div style="font-weight: 600; color: #333; font-size: 1.05rem;">
                                    <?php echo h($lesson['student_name']); ?>
                                    <?php if ($is_now): ?>
                                        <span style="color: #28a745; margin-left: 10px; font-size: 0.85rem;">
                                            <i class="fas fa-circle" style="font-size: 0.6rem; animation: pulse 2s infinite;"></i> In Progress
                                        </span>
                                    <?php endif; ?>
                                </div>
                                <div style="font-size: 0.9rem; color: #666; margin-top: 5px;">
                                    <i class="fas fa-clock"></i> <?php echo date('g:i A', strtotime($lesson['start_time'])); ?> - <?php echo date('g:i A', strtotime($lesson['end_time'])); ?>
                                    <?php if (!$is_now && $can_join): ?>
                                        <span style="color: #0b6cf5; margin-left: 10px;">• Join now available</span>
                                    <?php elseif (!$is_now): ?>
                                        <span style="color: #666; margin-left: 10px;">• Starts in <?php echo round(($lesson_time - $current_time) / 60); ?> minutes</span>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <a href="classroom.php?lessonId=<?php echo $lesson['id']; ?>" 
                               class="btn <?php echo $can_join ? 'btn-primary' : 'btn-outline'; ?>" 
                               style="white-space: nowrap;">
                                <i class="fas fa-video"></i> <?php echo $is_now ? 'Join Now' : ($can_join ? 'Join' : 'View'); ?>
                            </a>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>
            
            <div class="stats-grid">
                <a href="#" onclick="switchTab('students'); return false;" class="stat-card" style="text-decoration: none; color: inherit; cursor: pointer; transition: transform 0.2s, box-shadow 0.2s;" onmouseover="this.style.transform='translateY(-3px)'; this.style.boxShadow='0 4px 12px rgba(0,0,0,0.15)'" onmouseout="this.style.transform=''; this.style.boxShadow=''">
                    <div class="stat-icon"><i class="fas fa-users"></i></div>
                    <div class="stat-info">
                        <h3><?php echo $student_count; ?></h3>
                        <p>Active Students</p>
                    </div>
                    <div style="position: absolute; top: 10px; right: 10px; opacity: 0.5;">
                        <i class="fas fa-arrow-right"></i>
                    </div>
                </a>
                <a href="#" onclick="switchTab('earnings'); return false;" class="stat-card" style="text-decoration: none; color: inherit; cursor: pointer; transition: transform 0.2s, box-shadow 0.2s;" onmouseover="this.style.transform='translateY(-3px)'; this.style.boxShadow='0 4px 12px rgba(0,0,0,0.15)'" onmouseout="this.style.transform=''; this.style.boxShadow=''">
                    <div class="stat-icon success"><i class="fas fa-clock"></i></div>
                    <div class="stat-info">
                        <h3><?php echo $user['hours_taught'] ?? 0; ?></h3>
                        <p>Hours Taught</p>
                    </div>
                    <div style="position: absolute; top: 10px; right: 10px; opacity: 0.5;">
                        <i class="fas fa-arrow-right"></i>
                    </div>
                </a>
                <a href="#" onclick="switchTab('reviews'); return false;" class="stat-card" style="text-decoration: none; color: inherit; cursor: pointer; transition: transform 0.2s, box-shadow 0.2s;" onmouseover="this.style.transform='translateY(-3px)'; this.style.boxShadow='0 4px 12px rgba(0,0,0,0.15)'" onmouseout="this.style.transform=''; this.style.boxShadow=''">
                    <div class="stat-icon warning"><i class="fas fa-star"></i></div>
                    <div class="stat-info">
                        <h3><?php echo $rating_data['avg_rating']; ?></h3>
                        <p><?php echo $rating_data['review_count']; ?> Reviews</p>
                    </div>
                    <div style="position: absolute; top: 10px; right: 10px; opacity: 0.5;">
                        <i class="fas fa-arrow-right"></i>
                    </div>
                </a>
                <a href="#" onclick="switchTab('earnings'); return false;" class="stat-card" style="text-decoration: none; color: inherit; cursor: pointer; transition: transform 0.2s, box-shadow 0.2s;" onmouseover="this.style.transform='translateY(-3px)'; this.style.boxShadow='0 4px 12px rgba(0,0,0,0.15)'" onmouseout="this.style.transform=''; this.style.boxShadow=''">
                    <div class="stat-icon info"><i class="fas fa-dollar-sign"></i></div>
                    <div class="stat-info">
                        <h3><?php echo formatCurrency($earnings_data['total_earnings']); ?></h3>
                        <p>Total Earnings</p>
                    </div>
                    <div style="position: absolute; top: 10px; right: 10px; opacity: 0.5;">
                        <i class="fas fa-arrow-right"></i>
                    </div>
                </a>
            </div>

            <div class="card">
                <h2><i class="fas fa-bolt"></i> Quick Actions</h2>
                <div class="quick-actions" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 15px;">
                    <a href="schedule.php" class="quick-action-btn" style="background: linear-gradient(135deg, #0b6cf5 0%, #004080 100%); position: relative;">
                        <i class="fas fa-calendar"></i>
                        <span>View Schedule</span>
                    </a>
                    <a href="message_threads.php" class="quick-action-btn" style="background: linear-gradient(135deg, #28a745 0%, #20c997 100%); position: relative;">
                        <i class="fas fa-comments"></i>
                        <span>Messages</span>
                        <?php if ($unread_messages > 0): ?>
                            <span class="notification-badge" style="position: absolute; top: -5px; right: -5px; background: #dc3545; color: white; border-radius: 50%; width: 20px; height: 20px; display: flex; align-items: center; justify-content: center; font-size: 0.7rem; font-weight: bold;"><?php echo $unread_messages; ?></span>
                        <?php endif; ?>
                    </a>
                    <a href="#" onclick="switchTab('assignments')" class="quick-action-btn" style="background: linear-gradient(135deg, #ffc107 0%, #ff9800 100%); position: relative;">
                        <i class="fas fa-tasks"></i>
                        <span>Assignments</span>
                        <?php if ($pending_assignments > 0): ?>
                            <span class="notification-badge" style="position: absolute; top: -5px; right: -5px; background: #dc3545; color: white; border-radius: 50%; width: 20px; height: 20px; display: flex; align-items: center; justify-content: center; font-size: 0.7rem; font-weight: bold;"><?php echo $pending_assignments; ?></span>
                        <?php endif; ?>
                    </a>
                    <a href="teacher-calendar-setup.php" class="quick-action-btn" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);">
                        <i class="fas fa-calendar-alt"></i>
                        <span>Calendar Setup</span>
                    </a>
                    <a href="profile.php?id=<?php echo $teacher_id; ?>" class="quick-action-btn" style="background: linear-gradient(135deg, #17a2b8 0%, #138496 100%);">
                        <i class="fas fa-user"></i>
                        <span>My Profile</span>
                    </a>
                </div>
            </div>

            <?php if ($pending_assignments > 0): ?>
            <div class="card">
                <h2><i class="fas fa-exclamation-circle" style="color: var(--warning);"></i> Pending Submissions</h2>
                <?php foreach ($assignments as $a): ?>
                    <?php if ($a['status'] === 'submitted'): ?>
                    <div class="assignment-item">
                        <img src="<?php echo h($a['student_pic']); ?>" alt="" style="width: 40px; height: 40px; border-radius: 50%; object-fit: cover;" onerror="this.src='<?php echo getAssetPath('images/placeholder-teacher.svg'); ?>'">
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
                        <div style="display: flex; align-items: center; flex: 1;">
                            <img src="<?php echo h($lesson['student_pic']); ?>" alt="" style="width: 50px; height: 50px; border-radius: 50%; object-fit: cover; margin-right: 15px;" onerror="this.src='<?php echo getAssetPath('images/placeholder-teacher.svg'); ?>'">
                            <div style="flex: 1;">
                                <strong><?php echo h($lesson['student_name']); ?></strong>
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

            <?php if (count($reviews) > 0): ?>
            <div class="card">
                <h2><i class="fas fa-star"></i> Recent Reviews</h2>
                <?php foreach (array_slice($reviews, 0, 3) as $review): ?>
                <div class="review-card" style="margin-bottom: 10px;">
                    <div class="review-header">
                        <img src="<?php echo h($review['student_pic']); ?>" alt="" class="review-avatar" onerror="this.src='<?php echo getAssetPath('images/placeholder-teacher.svg'); ?>'">
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
                            <td data-label="Date"><?php echo date('M d, Y', strtotime($earning['created_at'])); ?></td>
                            <td data-label="Student"><?php echo h($earning['student_name'] ?? 'N/A'); ?></td>
                            <td data-label="Amount"><strong><?php echo formatCurrency($earning['net_amount']); ?></strong></td>
                            <td data-label="Status">
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
            <h1>My Assigned Students</h1>
            
            <?php if (!empty($teacher_tracks)): ?>
                <div style="margin-bottom: 20px; padding: 15px; background: #f0f7ff; border-radius: 8px; border-left: 4px solid #0b6cf5;">
                    <strong>Teaching Tracks:</strong> 
                    <?php foreach ($teacher_tracks as $track): ?>
                        <span style="display: inline-block; padding: 5px 15px; background: white; border-radius: 20px; margin-left: 10px; font-size: 0.9rem;">
                            <i class="fas fa-<?php echo $track === 'kids' ? 'child' : ($track === 'coding' ? 'code' : 'user-graduate'); ?>"></i>
                            <?php echo ucfirst($track); ?>
                        </span>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
            
            <?php if (count($students) > 0): ?>
                <?php foreach ($students as $student): ?>
                <div class="card" style="margin-bottom: 15px;">
                    <div style="display: flex; gap: 20px; align-items: flex-start;">
                        <img src="<?php echo h($student['profile_pic']); ?>" alt="" style="width: 60px; height: 60px; border-radius: 50%; object-fit: cover;" onerror="this.src='<?php echo getAssetPath('images/placeholder-teacher.svg'); ?>'">
                        <div style="flex: 1;">
                            <div style="display: flex; align-items: center; gap: 10px; margin-bottom: 5px;">
                                <h3 style="margin: 0; border: none; padding: 0;"><?php echo h($student['name']); ?></h3>
                                <?php if (!empty($student['track'])): ?>
                                    <span style="display: inline-block; padding: 3px 10px; background: #e1f0ff; color: #0b6cf5; border-radius: 12px; font-size: 0.75rem; font-weight: 600;">
                                        <?php echo ucfirst($student['track']); ?>
                                    </span>
                                <?php endif; ?>
                            </div>
                            <div style="font-size: 0.9rem; color: var(--gray); margin-bottom: 10px;">
                                <?php echo h($student['email']); ?> • <?php echo $student['lesson_count']; ?> lessons
                                <?php if (!empty($student['assigned_at'])): ?>
                                    • Assigned <?php echo date('M d, Y', strtotime($student['assigned_at'])); ?>
                                <?php endif; ?>
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
                            <img src="<?php echo h($a['student_pic']); ?>" alt="" style="width: 45px; height: 45px; border-radius: 50%; object-fit: cover;" onerror="this.src='<?php echo getAssetPath('images/placeholder-teacher.svg'); ?>'">
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
                        <img src="<?php echo h($review['student_pic']); ?>" alt="" class="review-avatar" onerror="this.src='<?php echo getAssetPath('images/placeholder-teacher.svg'); ?>'">
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

        <!-- Shared Materials Tab -->
        <div id="shared-materials" class="tab-content">
            <h1>Shared Materials Library</h1>
            <p style="color: var(--gray); margin-bottom: 20px;">
                All teachers can add materials here. All materials are visible to all teachers for use during lessons.
            </p>
            
            <div class="card">
                <h2><i class="fas fa-upload"></i> Add Shared Material</h2>
                <form id="addMaterialForm" enctype="multipart/form-data">
                    <div class="profile-grid">
                        <div class="form-group">
                            <label>Title *</label>
                            <input type="text" name="title" id="material_title" placeholder="Material title" required>
                        </div>
                        <div class="form-group">
                            <label>Type</label>
                            <select name="type" id="material_type" onchange="toggleMaterialInputs()">
                                <option value="file">File Upload</option>
                                <option value="link">External Link</option>
                                <option value="video">Video Link</option>
                            </select>
                        </div>
                    </div>
                    <div class="form-group">
                        <label>Description</label>
                        <textarea name="description" id="material_description" rows="3" placeholder="Brief description of the material..."></textarea>
                    </div>
                    <div class="form-group" id="file-input-group">
                        <label>File</label>
                        <div id="material-dropzone" style="border: 2px dashed var(--primary-light); border-radius: 8px; padding: 30px; text-align: center; background: #f9fbff; cursor: pointer; transition: all 0.3s;" 
                             onmouseover="this.style.borderColor='var(--primary)'; this.style.background='#f0f7ff';" 
                             onmouseout="this.style.borderColor='var(--primary-light)'; this.style.background='#f9fbff';">
                            <i class="fas fa-cloud-upload-alt" style="font-size: 2rem; color: var(--primary); margin-bottom: 10px;"></i>
                            <div style="color: var(--gray); margin-bottom: 10px;">
                                <strong style="color: var(--primary);">Drag & drop files here</strong> or click to browse
                            </div>
                            <div id="material-file-info" style="color: var(--gray); font-size: 0.9rem; margin-top: 10px;"></div>
                            <input type="file" name="file" id="material_file" accept=".pdf,.doc,.docx,.ppt,.pptx,.txt,.jpg,.jpeg,.png,.gif,.mp4,.mp3,.zip,.mov,.avi,.webm" style="display: none;" onchange="handleMaterialFileChange(this)">
                        </div>
                        <small style="color: var(--gray); display: block; margin-top: 5px;">Max 50MB. Supported: PDF, DOC, PPT, Images, Videos (MP4, MOV, AVI, WEBM), ZIP</small>
                    </div>
                    <div class="form-group" id="link-input-group" style="display: none;">
                        <label>Link URL</label>
                        <input type="url" name="link_url" id="material_link_url" placeholder="https://...">
                    </div>
                    <button type="submit" class="btn-primary">
                        <i class="fas fa-upload"></i> Add Material
                    </button>
                </form>
            </div>

            <h2 style="margin-top: 30px;">All Shared Materials</h2>
            <div id="materials-list" style="display: grid; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); gap: 20px;">
                <!-- Materials will be loaded here via JavaScript -->
            </div>
        </div>

        <!-- Profile Tab -->
        <div id="profile" class="tab-content">
            <h1>Edit Profile</h1>
            <?php if (isset($msg) && !empty($msg)): ?>
                <div class="alert-success" style="margin-bottom: 20px;"><i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($msg); ?></div>
            <?php endif; ?>
            <?php if (isset($upload_error) && !empty($upload_error)): ?>
                <div class="alert-error" style="margin-bottom: 20px;"><i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($upload_error); ?></div>
            <?php endif; ?>
            <div class="card">
                <form method="POST" enctype="multipart/form-data" id="profile-form">
                    <div style="display: flex; gap: 30px; margin-bottom: 25px; align-items: flex-start;">
                        <div style="text-align: center;">
                            <div id="profile-pic-dropzone" style="position: relative; display: inline-block; cursor: pointer;">
                                <img src="<?php echo h($user['profile_pic']); ?>" alt="Profile" id="profile-pic-preview"
                                     style="width: 120px; height: 120px; border-radius: 50%; object-fit: cover; border: 4px solid var(--primary-light); transition: all 0.3s;"
                                     onerror="this.src='<?php echo getAssetPath('images/placeholder-teacher.svg'); ?>'">
                                <div id="profile-pic-overlay" style="position: absolute; top: 0; left: 0; width: 100%; height: 100%; border-radius: 50%; background: rgba(0,0,0,0.5); color: white; display: none; align-items: center; justify-content: center; font-size: 0.8rem; text-align: center; padding: 10px;">
                                    <div>Drop image here</div>
                                </div>
                            </div>
                            <div style="margin-top: 15px;">
                                <label class="btn-outline btn-sm" style="cursor: pointer;">
                                    <i class="fas fa-camera"></i> Change Photo
                                    <input type="file" name="profile_pic_file" id="profile_pic_file" accept="image/*,video/*" style="display: none;" onchange="handleProfilePicChange(this)">
                                </label>
                                <small style="display: block; margin-top: 5px; color: var(--primary);">Requires admin approval</small>
                                <small style="display: block; margin-top: 5px; color: var(--gray); font-size: 0.8rem;">Drag & drop or click to upload</small>
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
            <?php include __DIR__ . '/app/Views/components/password-change-form.php'; ?>
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

// Shared Materials Functions
function toggleMaterialInputs() {
    const type = document.getElementById('material_type').value;
    const fileGroup = document.getElementById('file-input-group');
    const linkGroup = document.getElementById('link-input-group');
    const fileInput = document.getElementById('material_file');
    const linkInput = document.getElementById('material_link_url');
    
    if (type === 'file') {
        fileGroup.style.display = 'block';
        linkGroup.style.display = 'none';
        if (fileInput) fileInput.required = true;
        if (linkInput) linkInput.required = false;
    } else {
        fileGroup.style.display = 'none';
        linkGroup.style.display = 'block';
        if (fileInput) fileInput.required = false;
        if (linkInput) linkInput.required = true;
    }
}

// Load materials
function loadMaterials() {
    fetch('api/materials.php?action=list')
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                displayMaterials(data.materials);
            }
        })
        .catch(err => console.error('Error loading materials:', err));
}

// Display materials
function displayMaterials(materials) {
    const container = document.getElementById('materials-list');
    if (!container) return;
    
    if (materials.length === 0) {
        container.innerHTML = '<div class="empty-state"><i class="fas fa-folder-open"></i><h3>No Materials Yet</h3><p>Be the first to add a shared material!</p></div>';
        return;
    }
    
    const isAdmin = <?php echo $user_role === 'admin' ? 'true' : 'false'; ?>;
    container.innerHTML = materials.map(material => {
        const icon = material.type === 'video' ? 'fa-video' : (material.type === 'link' ? 'fa-link' : 'fa-file-alt');
        const url = material.file_path ? material.file_path : material.link_url;
        const uploadedBy = material.uploaded_by_name || 'Unknown';
        const date = new Date(material.created_at).toLocaleDateString();
        
        return `
            <div class="card" style="margin: 0;">
                <div class="material-icon" style="margin-bottom: 10px;">
                    <i class="fas ${icon}"></i>
                </div>
                <h3 style="border: none; padding: 0; font-size: 1rem; margin-bottom: 8px;">${escapeHtml(material.title)}</h3>
                ${material.description ? `<p style="font-size: 0.9rem; color: var(--gray); margin: 10px 0;">${escapeHtml(material.description)}</p>` : ''}
                <div style="font-size: 0.8rem; color: var(--gray); margin-bottom: 10px;">
                    <i class="fas fa-user"></i> ${escapeHtml(uploadedBy)}<br>
                    <i class="fas fa-calendar"></i> ${date}
                </div>
                <button onclick="openMaterialViewer(${material.id})" class="btn-primary btn-sm" style="width: 100%; margin-top: 10px;">
                    <i class="fas fa-eye"></i> View for Screen Share
                </button>
                ${isAdmin ? `
                    <button onclick="deleteMaterial(${material.id})" class="btn-danger btn-sm" style="width: 100%; margin-top: 5px;">
                        <i class="fas fa-trash"></i> Delete
                    </button>
                ` : ''}
            </div>
        `;
    }).join('');
}

// Open material viewer
function openMaterialViewer(materialId) {
    fetch(`api/materials.php?action=view&id=${materialId}`)
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                showMaterialModal(data.material);
            }
        })
        .catch(err => console.error('Error loading material:', err));
}

// Show material in modal
function showMaterialModal(material) {
    let modal = document.getElementById('materialViewerModal');
    if (!modal) {
        createMaterialModal();
        modal = document.getElementById('materialViewerModal');
    }
    
    const modalContent = document.getElementById('materialViewerContent');
    const url = material.file_path ? material.file_path : material.link_url;
    
    let content = '';
    if (material.type === 'video') {
        content = `<video controls style="width: 100%; max-height: 70vh;"><source src="${escapeHtml(url)}"></video>`;
    } else if (material.type === 'link') {
        content = `<iframe src="${escapeHtml(url)}" style="width: 100%; height: 70vh; border: none;"></iframe>`;
    } else if (material.file_path && material.file_path.endsWith('.pdf')) {
        content = `<iframe src="${escapeHtml(url)}" style="width: 100%; height: 70vh; border: none;"></iframe>`;
    } else if (material.file_path && /\.(jpg|jpeg|png|gif)$/i.test(material.file_path)) {
        content = `<img src="${escapeHtml(url)}" style="max-width: 100%; max-height: 70vh; display: block; margin: 0 auto;">`;
    } else {
        content = `<div style="text-align: center; padding: 40px;"><a href="${escapeHtml(url)}" target="_blank" class="btn-primary"><i class="fas fa-download"></i> Download File</a></div>`;
    }
    
    document.getElementById('materialViewerTitle').textContent = material.title;
    document.getElementById('materialViewerContent').innerHTML = content;
    modal.classList.add('active');
}

// Create material modal
function createMaterialModal() {
    const modal = document.createElement('div');
    modal.id = 'materialViewerModal';
    modal.className = 'modal-overlay';
    modal.innerHTML = `
        <div class="modal" style="max-width: 90vw; max-height: 90vh; width: 1200px;">
            <div class="modal-header">
                <h3 id="materialViewerTitle">Material Viewer</h3>
                <div>
                    <button onclick="toggleFullscreen()" class="btn-outline btn-sm" style="margin-right: 10px;">
                        <i class="fas fa-expand"></i> Fullscreen
                    </button>
                    <button class="modal-close" onclick="closeMaterialViewer()">&times;</button>
                </div>
            </div>
            <div id="materialViewerContent" style="overflow: auto; max-height: calc(90vh - 100px);">
                <!-- Content will be loaded here -->
            </div>
        </div>
    `;
    document.body.appendChild(modal);
}

// Close material viewer
function closeMaterialViewer() {
    const modal = document.getElementById('materialViewerModal');
    if (modal) modal.classList.remove('active');
}

// Toggle fullscreen
function toggleFullscreen() {
    const modal = document.getElementById('materialViewerModal');
    if (!document.fullscreenElement) {
        modal.requestFullscreen().catch(err => console.error('Error entering fullscreen:', err));
    } else {
        document.exitFullscreen();
    }
}

// Delete material (admin only)
async function deleteMaterial(materialId) {
    // Use toast confirm instead of native confirm
    const confirmed = await toast.confirm('Are you sure you want to delete this material?', 'Delete Material');
    if (!confirmed) return;
    
    const formData = new FormData();
    formData.append('action', 'delete');
    formData.append('material_id', materialId);
    
    fetch('api/materials.php', {
        method: 'POST',
        body: formData
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            toast.success('Material deleted successfully');
            loadMaterials();
        } else {
            toast.error('Error: ' + (data.error || 'Failed to delete material'));
        }
    })
    .catch(err => {
        console.error('Error deleting material:', err);
        toast.error('Error deleting material. Please try again.');
    });
}

// Escape HTML
function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

// Handle material form submission and tab switching
document.addEventListener('DOMContentLoaded', function() {
    const form = document.getElementById('addMaterialForm');
    if (form) {
        form.addEventListener('submit', function(e) {
            e.preventDefault();
            
            const formData = new FormData(form);
            formData.append('action', 'add');
            
            fetch('api/materials.php', {
                method: 'POST',
                body: formData
            })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    form.reset();
                    toggleMaterialInputs();
                    loadMaterials();
                    if (typeof toast !== 'undefined') {
                        toast.success('Material added successfully!');
                    } else {
                        alert('Material added successfully!');
                    }
                } else {
                    if (typeof toast !== 'undefined') {
                        toast.error('Error: ' + (data.error || 'Failed to add material'));
                    } else {
                        alert('Error: ' + (data.error || 'Failed to add material'));
                    }
                }
            })
            .catch(err => {
                console.error('Error adding material:', err);
                if (typeof toast !== 'undefined') {
                    toast.error('Error adding material. Please try again.');
                } else {
                    alert('Error adding material');
                }
            });
        });
    }
    
    // Override switchTab to load materials when shared-materials tab is active
    const originalSwitchTab = window.switchTab;
    window.switchTab = function(id) {
        if (typeof originalSwitchTab === 'function') {
            originalSwitchTab(id);
        } else {
            if (event) event.preventDefault();
            
            document.querySelectorAll('.tab-content').forEach(el => el.classList.remove('active'));
            const tab = document.getElementById(id);
            if (tab) tab.classList.add('active');
            
            document.querySelectorAll('.sidebar-menu a').forEach(el => el.classList.remove('active'));
            const activeLink = document.querySelector(`.sidebar-menu a[onclick*="${id}"]`);
            if (activeLink) activeLink.classList.add('active');
            
            // Also check sidebar header button
            const sidebarHeader = document.querySelector('.sidebar-header a');
            if (sidebarHeader && id === 'overview') {
                sidebarHeader.classList.add('active');
            }
            
            // Scroll to top
            const mainContent = document.querySelector('.main');
            if (mainContent) mainContent.scrollTop = 0;
            
            // Update URL hash without triggering page reload
            if (window.location.hash !== '#' + id) {
                window.history.pushState(null, null, '#' + id);
            }
        }
        
        if (id === 'shared-materials') {
            loadMaterials();
        }
    };
    
    // Load materials if already on shared-materials tab
    const hash = window.location.hash.substring(1);
    if (hash === 'shared-materials') {
        loadMaterials();
    }
    
    // Drag and drop for profile picture
    setupProfilePicDragDrop();
    
    // Drag and drop for materials
    setupMaterialDragDrop();
});

// Setup drag and drop for profile picture
function setupProfilePicDragDrop() {
    const dropzone = document.getElementById('profile-pic-dropzone');
    const overlay = document.getElementById('profile-pic-overlay');
    const fileInput = document.getElementById('profile_pic_file');
    
    if (!dropzone || !fileInput) return;
    
    // Click to upload
    dropzone.addEventListener('click', () => fileInput.click());
    
    // Drag events
    dropzone.addEventListener('dragover', (e) => {
        e.preventDefault();
        e.stopPropagation();
        overlay.style.display = 'flex';
        dropzone.querySelector('img').style.opacity = '0.5';
    });
    
    dropzone.addEventListener('dragleave', (e) => {
        e.preventDefault();
        e.stopPropagation();
        overlay.style.display = 'none';
        dropzone.querySelector('img').style.opacity = '1';
    });
    
    dropzone.addEventListener('drop', (e) => {
        e.preventDefault();
        e.stopPropagation();
        overlay.style.display = 'none';
        dropzone.querySelector('img').style.opacity = '1';
        
        const files = e.dataTransfer.files;
        if (files.length > 0) {
            const file = files[0];
            if (file.type.startsWith('image/') || file.type.startsWith('video/')) {
                // Use DataTransfer for cross-browser compatibility
                try {
                    const dataTransfer = new DataTransfer();
                    dataTransfer.items.add(file);
                    fileInput.files = dataTransfer.files;
                } catch (err) {
                    // Fallback for older browsers
                    try {
                        // Create a new FileList-like object
                        const fileList = Object.create(FileList.prototype);
                        Object.defineProperty(fileList, '0', { value: file, writable: false });
                        Object.defineProperty(fileList, 'length', { value: 1, writable: false });
                        Object.defineProperty(fileInput, 'files', { value: fileList, writable: false });
                    } catch (fallbackErr) {
                        // Last resort: trigger change event manually
                        console.warn('Direct file assignment not supported, using fallback method');
                        const reader = new FileReader();
                        reader.onload = (e) => {
                            // Create a fake input change event
                            const event = new Event('change', { bubbles: true });
                            fileInput.dispatchEvent(event);
                        };
                        // Store file reference for later use
                        fileInput._droppedFile = file;
                    }
                }
                handleProfilePicChange(fileInput);
            } else {
                if (typeof toast !== 'undefined') {
                    toast.error('Please upload an image or video file.');
                } else {
                    alert('Please upload an image or video file.');
                }
            }
        }
    });
}

// Handle profile picture change
function handleProfilePicChange(input) {
    // Handle both normal file selection and drag-drop fallback
    const file = input.files && input.files[0] ? input.files[0] : (input._droppedFile || null);
    if (!file) return;
    
    const preview = document.getElementById('profile-pic-preview');
    if (!preview) return;
    
    // Validate file type
    if (!file.type.startsWith('image/') && !file.type.startsWith('video/')) {
        if (typeof toast !== 'undefined') {
            toast.error('Please upload an image or video file.');
        } else {
            alert('Please upload an image or video file.');
        }
        input.value = '';
        return;
    }
    
    // Validate file size (5MB for images, larger for videos)
    const maxSize = file.type.startsWith('image/') ? 5 * 1024 * 1024 : 100 * 1024 * 1024;
    if (file.size > maxSize) {
        const limit = file.type.startsWith('image/') ? '5MB' : '100MB';
        if (typeof toast !== 'undefined') {
            toast.error(`File size exceeds limit. Maximum size: ${limit}`);
        } else {
            alert('File size exceeds limit. Images: 5MB max, Videos: 100MB max.');
        }
        input.value = '';
        return;
    }
    
    // Show preview
    if (file.type.startsWith('image/')) {
        const reader = new FileReader();
        reader.onload = (e) => {
            preview.src = e.target.result;
        };
        reader.readAsDataURL(file);
    } else {
        preview.src = '<?php echo getAssetPath("images/placeholder-teacher.svg"); ?>';
    }
    
    // Auto-submit form after selection
    const form = input.closest('form');
    if (form) {
        // Use async confirmation
        if (typeof toast !== 'undefined') {
            toast.confirm(`Upload ${file.name}? (Requires admin approval)`, 'Confirm Upload').then(confirmed => {
                if (confirmed) {
                    // Show loading state
                    const submitBtn = form.querySelector('button[type="submit"]');
                    if (submitBtn) {
                        submitBtn.disabled = true;
                        submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Uploading...';
                    }
                    form.submit();
                }
            });
        } else {
            if (confirm('Upload ' + file.name + '? (Requires admin approval)')) {
                form.submit();
            }
        }
    }
}

// Setup drag and drop for materials
function setupMaterialDragDrop() {
    const dropzone = document.getElementById('material-dropzone');
    const fileInput = document.getElementById('material_file');
    
    if (!dropzone || !fileInput) return;
    
    // Click to upload
    dropzone.addEventListener('click', () => fileInput.click());
    
    // Drag events
    dropzone.addEventListener('dragover', (e) => {
        e.preventDefault();
        e.stopPropagation();
        dropzone.style.borderColor = 'var(--primary)';
        dropzone.style.background = '#e6f3ff';
        dropzone.style.transform = 'scale(1.02)';
    });
    
    dropzone.addEventListener('dragleave', (e) => {
        e.preventDefault();
        e.stopPropagation();
        dropzone.style.borderColor = 'var(--primary-light)';
        dropzone.style.background = '#f9fbff';
        dropzone.style.transform = 'scale(1)';
    });
    
    dropzone.addEventListener('drop', (e) => {
        e.preventDefault();
        e.stopPropagation();
        dropzone.style.borderColor = 'var(--primary-light)';
        dropzone.style.background = '#f9fbff';
        dropzone.style.transform = 'scale(1)';
        
        const files = e.dataTransfer.files;
        if (files.length > 0) {
            // Use DataTransfer for cross-browser compatibility
            try {
                const dataTransfer = new DataTransfer();
                for (let i = 0; i < files.length; i++) {
                    dataTransfer.items.add(files[i]);
                }
                fileInput.files = dataTransfer.files;
            } catch (err) {
                // Fallback for older browsers
                try {
                    const fileList = Object.create(FileList.prototype);
                    for (let i = 0; i < files.length; i++) {
                        Object.defineProperty(fileList, i.toString(), { value: files[i], writable: false });
                    }
                    Object.defineProperty(fileList, 'length', { value: files.length, writable: false });
                    Object.defineProperty(fileInput, 'files', { value: fileList, writable: false });
                } catch (fallbackErr) {
                    console.warn('Direct file assignment not supported, using fallback method');
                    fileInput._droppedFiles = Array.from(files);
                }
            }
            handleMaterialFileChange(fileInput);
        }
    });
}

// Handle material file change
function handleMaterialFileChange(input) {
    // Handle both normal file selection and drag-drop fallback
    const file = input.files && input.files[0] ? input.files[0] : 
                 (input._droppedFiles && input._droppedFiles[0] ? input._droppedFiles[0] : null);
    const fileInfo = document.getElementById('material-file-info');
    
    if (!file) {
        if (fileInfo) fileInfo.textContent = '';
        return;
    }
    
    // Validate file size (50MB max)
    const maxSize = 50 * 1024 * 1024;
    if (file.size > maxSize) {
        if (typeof toast !== 'undefined') {
            toast.error('File size exceeds 50MB limit.');
        } else {
            alert('File size exceeds 50MB limit.');
        }
        input.value = '';
        if (fileInfo) fileInfo.textContent = '';
        return;
    }
    
    // Show file info
    if (fileInfo) {
        const fileSize = (file.size / (1024 * 1024)).toFixed(2);
        fileInfo.innerHTML = `<i class="fas fa-file"></i> ${file.name} (${fileSize} MB)`;
        fileInfo.style.color = 'var(--primary)';
    }
}
</script>
<?php
// End output buffering and send output
if (ob_get_level() > 0) {
    ob_end_flush();
}
?>

</body>
</html>
