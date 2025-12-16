<?php
// Start output buffering to prevent headers already sent errors
ob_start();
session_start();
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/app/Views/components/dashboard-functions.php';

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

agent_debug_log('H1', 'teacher-dashboard.php:session', 'teacher dashboard entry', [
    'user_id' => $_SESSION['user_id'] ?? null,
    'user_role' => $_SESSION['user_role'] ?? null,
]);

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'teacher') {
    ob_end_clean();
    header("Location: login.php");
    exit();
}

// ALL TEACHERS (including ZacharyStayton101@gmail.com) have equal access to all dashboard features
// No teacher-specific exclusions - all features available to all users with role='teacher'
$teacher_id = $_SESSION['user_id'];
$user_id = $teacher_id; // Ensure $user_id is set for sidebar component and slot requests
$user = getUserById($conn, $teacher_id);
$user_role = 'teacher';

// Load models for group classes
require_once __DIR__ . '/app/Models/GroupClass.php';
$groupClassModel = new GroupClass($conn);

// Get students who have booked lessons with this teacher (from lessons table)
$students = [];
$stmt = $conn->prepare("
    SELECT DISTINCT u.id, u.name, u.email, u.profile_pic, u.preferred_category,
           (SELECT COUNT(*) FROM lessons WHERE student_id = u.id AND teacher_id = ?) as lesson_count,
           (SELECT MAX(lesson_date) FROM lessons WHERE student_id = u.id AND teacher_id = ?) as last_lesson_date,
           (SELECT note FROM lesson_notes WHERE student_id = u.id AND teacher_id = ? ORDER BY created_at DESC LIMIT 1) as last_note
    FROM users u
    INNER JOIN lessons l ON u.id = l.student_id
    WHERE l.teacher_id = ? AND u.role IN ('student', 'new_student')
    ORDER BY last_lesson_date DESC
");
$stmt->bind_param("iiii", $teacher_id, $teacher_id, $teacher_id, $teacher_id);
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $students[] = $row;
}
$stmt->close();

// Get teacher's categories
$teacher_categories = [];
$stmt = $conn->prepare("SELECT category FROM teacher_categories WHERE teacher_id = ? AND is_active = 1");
$stmt->bind_param("i", $teacher_id);
$stmt->execute();
$cat_result = $stmt->get_result();
while ($row = $cat_result->fetch_assoc()) {
    $teacher_categories[] = $row['category'];
}
$stmt->close();

// Get group classes for teacher
$group_classes = [];
$stmt = $conn->prepare("SELECT * FROM group_classes WHERE teacher_id = ? ORDER BY scheduled_date DESC");
$stmt->bind_param("i", $teacher_id);
$stmt->execute();
$gc_result = $stmt->get_result();
while ($row = $gc_result->fetch_assoc()) {
    $group_classes[] = $row;
}
$stmt->close();

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
        // Always insert/update even if bio, about_text, etc. are empty - admin needs to see the request
        $check_stmt = $conn->prepare("SELECT id FROM pending_updates WHERE user_id = ?");
        $check_stmt->bind_param("i", $teacher_id);
        $check_stmt->execute();
        $check = $check_stmt->get_result();
        
        $name = $_SESSION['user_name'] ?? $user['name'] ?? '';
        $bio = $bio ?? '';
        $about_text = $about_text ?? '';
        $video_url = $video_url ?? '';
        
        if ($check && $check->num_rows > 0) {
            $stmt = $conn->prepare("UPDATE pending_updates SET name = ?, bio = ?, profile_pic = ?, about_text = ?, video_url = ?, requested_at = NOW() WHERE user_id = ?");
            $stmt->bind_param("sssssi", $name, $bio, $profile_pic_pending, $about_text, $video_url, $teacher_id);
        } else {
            $stmt = $conn->prepare("INSERT INTO pending_updates (user_id, name, bio, profile_pic, about_text, video_url, requested_at) VALUES (?, ?, ?, ?, ?, ?, NOW())");
            $stmt->bind_param("isssss", $teacher_id, $name, $bio, $profile_pic_pending, $about_text, $video_url);
        }
        
        if ($stmt->execute()) {
            $msg = "Profile changes submitted for approval. An admin will review your changes shortly.";
            error_log("Profile update submitted for teacher ID: $teacher_id");
        } else {
            $msg = "Error submitting profile changes. Please try again or contact support.";
            error_log("Error submitting profile update for teacher ID $teacher_id: " . $stmt->error);
        }
        $stmt->close();
        $check_stmt->close();
    } else {
        // Upload failed, don't update database
        $msg = "Error: " . $upload_error;
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

// Handle Availability Slot Management
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_availability_slot'])) {
    $day_of_week = $_POST['day_of_week'] ?? null;
    $start_time = $_POST['start_time'];
    $end_time = $_POST['end_time'];
    $timezone = $_POST['timezone'] ?? 'America/New_York';
    $is_recurring = isset($_POST['is_recurring']) ? 1 : 0;
    $specific_date = !empty($_POST['specific_date']) ? $_POST['specific_date'] : null;
    
    if (strtotime($start_time) >= strtotime($end_time)) {
        $error_msg = 'End time must be after start time';
    } else {
        $stmt = $conn->prepare("
            INSERT INTO teacher_availability_slots (teacher_id, day_of_week, start_time, end_time, timezone, is_recurring, specific_date, is_available)
            VALUES (?, ?, ?, ?, ?, ?, ?, 1)
        ");
        $stmt->bind_param("issssis", $teacher_id, $day_of_week, $start_time, $end_time, $timezone, $is_recurring, $specific_date);
        if ($stmt->execute()) {
            $success_msg = 'Availability slot added successfully';
        } else {
            $error_msg = 'Error adding availability slot: ' . $stmt->error;
        }
        $stmt->close();
    }
    header("Location: teacher-dashboard.php#calendar");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_availability_slot'])) {
    $slot_id = (int)$_POST['slot_id'];
    $stmt = $conn->prepare("DELETE FROM teacher_availability_slots WHERE id = ? AND teacher_id = ?");
    $stmt->bind_param("ii", $slot_id, $teacher_id);
    if ($stmt->execute()) {
        $success_msg = 'Availability slot deleted successfully';
    } else {
        $error_msg = 'Error deleting availability slot';
    }
    $stmt->close();
    header("Location: teacher-dashboard.php#calendar");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['toggle_availability_slot'])) {
    $slot_id = (int)$_POST['slot_id'];
    $stmt = $conn->prepare("UPDATE teacher_availability_slots SET is_available = NOT is_available WHERE id = ? AND teacher_id = ?");
    $stmt->bind_param("ii", $slot_id, $teacher_id);
    $stmt->execute();
    $stmt->close();
    header("Location: teacher-dashboard.php#calendar");
    exit();
}

// Handle Group Class Request
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_group_class_request'])) {
    $track = $_POST['track'];
    $scheduled_date = $_POST['scheduled_date'];
    $scheduled_time = $_POST['scheduled_time'];
    $duration = (int)$_POST['duration'];
    $max_students = (int)$_POST['max_students'];
    $title = trim($_POST['title']);
    $description = trim($_POST['description']);
    
    $stmt = $conn->prepare("
        INSERT INTO group_classes (teacher_id, track, scheduled_date, scheduled_time, duration, max_students, title, description, status)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'pending')
    ");
    $stmt->bind_param("isssiiss", $teacher_id, $track, $scheduled_date, $scheduled_time, $duration, $max_students, $title, $description);
    if ($stmt->execute()) {
        $success_msg = 'Group class request submitted successfully';
    } else {
        $error_msg = 'Error creating group class request: ' . $stmt->error;
    }
    $stmt->close();
    header("Location: teacher-dashboard.php#group-classes");
    exit();
}

// Handle Support Message
$support_message = '';
$support_error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['send_support'])) {
    $subject = trim($_POST['subject'] ?? '');
    $message_text = trim($_POST['message'] ?? '');
    
    if (empty($subject)) {
        $support_error = 'Subject is required.';
    } elseif (empty($message_text)) {
        $support_error = 'Message is required.';
    } else {
        // Insert support message
        $stmt = $conn->prepare("INSERT INTO support_messages (sender_id, sender_role, subject, message) VALUES (?, ?, ?, ?)");
        if ($stmt) {
            $stmt->bind_param("isss", $teacher_id, $user_role, $subject, $message_text);
            if ($stmt->execute()) {
                $support_message = 'Your message has been sent to all admins. They will review and respond shortly.';
                // Clear form by redirecting
                header("Location: teacher-dashboard.php#support");
                exit();
            } else {
                $support_error = 'Error sending message. Please try again.';
            }
            $stmt->close();
        } else {
            $support_error = 'Error preparing statement. Please try again.';
        }
    }
}

// Fetch Stats
$rating_data = getTeacherRating($conn, $teacher_id);
$earnings_data = getTeacherEarnings($conn, $teacher_id);
$student_count = count($students);
$pending_assignments = getPendingAssignmentsCount($conn, $teacher_id);

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

// Fetch Resources (excluding soft-deleted)
$resources = [];
$res_stmt = $conn->prepare("SELECT * FROM teacher_resources WHERE teacher_id = ? AND is_deleted = 0 ORDER BY created_at DESC");
$res_stmt->bind_param("i", $teacher_id);
$res_stmt->execute();
$res_result = $res_stmt->get_result();
if ($res_result) {
    while ($row = $res_result->fetch_assoc()) {
        $resources[] = $row;
    }
}

// Fetch Classroom Materials
$materials = $conn->query("SELECT * FROM classroom_materials WHERE is_deleted = 0 ORDER BY created_at DESC");

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
    <?php
    // Use the same logo function as the header to get the correct logo
    $logo_path = getLogoPath();
    $logo_ext = pathinfo($logo_path, PATHINFO_EXTENSION);
    $logo_type = ($logo_ext === 'svg') ? 'image/svg+xml' : 'image/png';
    ?>
    <link rel="icon" type="<?php echo $logo_type; ?>" href="<?php echo $logo_path; ?>">
    <link rel="shortcut icon" type="<?php echo $logo_type; ?>" href="<?php echo $logo_path; ?>">
    <link rel="apple-touch-icon" href="<?php echo $logo_path; ?>">
    <link rel="stylesheet" href="<?php echo getAssetPath('styles.css'); ?>">
    <link rel="stylesheet" href="<?php echo getAssetPath('css/dashboard.css'); ?>">
    <link rel="stylesheet" href="<?php echo getAssetPath('css/mobile.css'); ?>">
    <!-- MODERN SHADOWS - To disable, comment out the line below -->
    <link rel="stylesheet" href="<?php echo getAssetPath('css/modern-shadows.css'); ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link href="https://cdn.jsdelivr.net/npm/fullcalendar@5.11.5/main.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/fullcalendar@5.11.5/main.min.js"></script>
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
            fetch('api/group-classes.php?action=details&class_id=' + classId, {
                method: 'GET',
                headers: {
                    'Accept': 'application/json'
                },
                credentials: 'same-origin'
            })
                .then(response => {
                    if (!response.ok) {
                        throw new Error(`HTTP error! status: ${response.status}`);
                    }
                    return response.json();
                })
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
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json'
                },
                body: JSON.stringify({
                    class_id: classId,
                    status: status
                })
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
            <div style="margin-bottom: 30px;">
                <h1 style="margin-bottom: 10px; display: flex; align-items: center; gap: 15px;">
                    <div style="width: 60px; height: 60px; border-radius: 15px; background: linear-gradient(135deg, #0b6cf5 0%, #004080 100%); display: flex; align-items: center; justify-content: center; color: white; font-size: 1.8rem; box-shadow: 0 4px 15px rgba(11, 108, 245, 0.3);">
                        <i class="fas fa-chalkboard-teacher"></i>
                    </div>
                    <div>
                        <div style="font-size: 1.8rem; font-weight: 700; color: #004080; line-height: 1.2;">
                            Welcome back, <?php echo h($user['name']); ?>! 👋
                        </div>
                        <div style="font-size: 1rem; color: #666; font-weight: 400; margin-top: 5px;">
                            Here's what's happening today
                        </div>
                    </div>
                </h1>
            </div>
            
            <?php if (isset($msg)): ?>
                <div class="alert-success" style="margin-bottom: 25px; padding: 15px 20px; border-radius: 10px; background: linear-gradient(135deg, #d4edda 0%, #c3e6cb 100%); border-left: 4px solid #28a745;">
                    <i class="fas fa-check-circle"></i> <?php echo $msg; ?>
                </div>
            <?php endif; ?>
            
            <!-- Test Classroom Button -->
            <div class="card" style="background: linear-gradient(135deg, #e3f2fd 0%, #ffffff 100%); border: 2px solid #0b6cf5; margin-bottom: 30px;">
                <div style="display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 15px;">
                    <div style="flex: 1; min-width: 250px;">
                        <h3 style="color: #004080; margin: 0 0 8px 0; font-size: 1.2rem;">
                            <i class="fas fa-video"></i> Test Classroom
                        </h3>
                        <p style="margin: 0; color: #666; font-size: 0.95rem;">
                            Test your microphone, camera, and classroom features before your lessons. This is a sandbox environment for practice.
                        </p>
                    </div>
                    <a href="classroom.php?testMode=true&sessionId=test_teacher_<?php echo $teacher_id; ?>_<?php echo time(); ?>" 
                       class="btn-primary" 
                       style="white-space: nowrap; padding: 12px 24px; font-size: 1rem; font-weight: 600; text-decoration: none; display: inline-flex; align-items: center; gap: 8px;">
                        <i class="fas fa-play-circle"></i> Open Test Classroom
                    </a>
                </div>
            </div>
            
            <?php
            // Check teacher onboarding status
            $has_calendar_setup = !empty($user['google_calendar_token']);
            $has_profile_complete = !empty($user['bio']) && !empty($user['profile_pic']);
            $has_students = isset($students) && is_array($students) && count($students) > 0;
            
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
            
            <!-- Statistics Cards - Improved Design -->
            <div class="stats-grid" style="grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap: 20px; margin-bottom: 30px;">
                <a href="#" onclick="switchTab('students'); return false;" class="stat-card" style="text-decoration: none; color: inherit; cursor: pointer; transition: all 0.3s; border-top: 4px solid #0b6cf5;" onmouseover="this.style.transform='translateY(-5px)'; this.style.boxShadow='0 6px 20px rgba(11, 108, 245, 0.2)'" onmouseout="this.style.transform=''; this.style.boxShadow=''">
                    <div class="stat-icon" style="background: linear-gradient(135deg, #0b6cf5 0%, #004080 100%);"><i class="fas fa-users"></i></div>
                    <div class="stat-info">
                        <h3 style="font-size: 2rem; margin: 0;"><?php echo $student_count; ?></h3>
                        <p style="margin-top: 5px; font-size: 0.95rem; color: #666;">Active Students</p>
                    </div>
                    <div style="position: absolute; top: 15px; right: 15px; opacity: 0.3; font-size: 1.5rem;">
                        <i class="fas fa-arrow-right"></i>
                    </div>
                </a>
                <a href="#" onclick="switchTab('performance'); switchPerformanceSubTab('earnings'); return false;" class="stat-card" style="text-decoration: none; color: inherit; cursor: pointer; transition: all 0.3s; border-top: 4px solid #28a745;" onmouseover="this.style.transform='translateY(-5px)'; this.style.boxShadow='0 6px 20px rgba(40, 167, 69, 0.2)'" onmouseout="this.style.transform=''; this.style.boxShadow=''">
                    <div class="stat-icon success" style="background: linear-gradient(135deg, #28a745 0%, #20c997 100%);"><i class="fas fa-clock"></i></div>
                    <div class="stat-info">
                        <h3 style="font-size: 2rem; margin: 0;"><?php echo $user['hours_taught'] ?? 0; ?></h3>
                        <p style="margin-top: 5px; font-size: 0.95rem; color: #666;">Hours Taught</p>
                    </div>
                    <div style="position: absolute; top: 15px; right: 15px; opacity: 0.3; font-size: 1.5rem;">
                        <i class="fas fa-arrow-right"></i>
                    </div>
                </a>
                <a href="#" onclick="switchTab('performance'); switchPerformanceSubTab('reviews'); return false;" class="stat-card" style="text-decoration: none; color: inherit; cursor: pointer; transition: all 0.3s; border-top: 4px solid #ffc107;" onmouseover="this.style.transform='translateY(-5px)'; this.style.boxShadow='0 6px 20px rgba(255, 193, 7, 0.2)'" onmouseout="this.style.transform=''; this.style.boxShadow=''">
                    <div class="stat-icon warning" style="background: linear-gradient(135deg, #ffc107 0%, #ff9800 100%);"><i class="fas fa-star"></i></div>
                    <div class="stat-info">
                        <h3 style="font-size: 2rem; margin: 0;"><?php echo number_format($rating_data['avg_rating'], 1); ?></h3>
                        <p style="margin-top: 5px; font-size: 0.95rem; color: #666;"><?php echo $rating_data['review_count']; ?> Reviews</p>
                    </div>
                    <div style="position: absolute; top: 15px; right: 15px; opacity: 0.3; font-size: 1.5rem;">
                        <i class="fas fa-arrow-right"></i>
                    </div>
                </a>
                <a href="#" onclick="switchTab('performance'); switchPerformanceSubTab('earnings'); return false;" class="stat-card" style="text-decoration: none; color: inherit; cursor: pointer; transition: all 0.3s; border-top: 4px solid #17a2b8;" onmouseover="this.style.transform='translateY(-5px)'; this.style.boxShadow='0 6px 20px rgba(23, 162, 184, 0.2)'" onmouseout="this.style.transform=''; this.style.boxShadow=''">
                    <div class="stat-icon info" style="background: linear-gradient(135deg, #17a2b8 0%, #138496 100%);"><i class="fas fa-dollar-sign"></i></div>
                    <div class="stat-info">
                        <h3 style="font-size: 2rem; margin: 0;"><?php echo formatCurrency($earnings_data['total_earnings']); ?></h3>
                        <p style="margin-top: 5px; font-size: 0.95rem; color: #666;">Total Earnings</p>
                    </div>
                    <div style="position: absolute; top: 15px; right: 15px; opacity: 0.3; font-size: 1.5rem;">
                        <i class="fas fa-arrow-right"></i>
                    </div>
                </a>
            </div>

            <!-- Organized Quick Actions by Category -->
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(320px, 1fr)); gap: 20px; margin-bottom: 30px;">
                
                <!-- Calendar & Scheduling Section -->
                <div class="card" style="border-top: 4px solid #0b6cf5; background: linear-gradient(135deg, #f0f7ff 0%, #ffffff 100%);">
                    <h3 style="margin-top: 0; color: #004080; display: flex; align-items: center; gap: 10px;">
                        <i class="fas fa-calendar-alt" style="font-size: 1.3rem;"></i>
                        Calendar & Scheduling
                    </h3>
                    <div style="display: flex; flex-direction: column; gap: 10px;">
                        <a href="schedule.php" class="quick-action-btn" style="background: linear-gradient(135deg, #0b6cf5 0%, #004080 100%); text-decoration: none; color: white;">
                            <i class="fas fa-calendar"></i>
                            <span>View Schedule</span>
                        </a>
                        <a href="teacher-calendar-setup.php" class="quick-action-btn" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); text-decoration: none; color: white;">
                            <i class="fas fa-cog"></i>
                            <span>Calendar Setup</span>
                        </a>
                        <a href="#" onclick="switchTab('calendar'); return false;" class="quick-action-btn" style="background: linear-gradient(135deg, #0b6cf5 0%, #004080 100%); text-decoration: none; color: white;">
                            <i class="fas fa-calendar-alt"></i>
                            <span>Manage Availability</span>
                        </a>
                        <a href="#" onclick="switchTab('slot-requests'); return false;" class="quick-action-btn" style="background: linear-gradient(135deg, #ff6b6b 0%, #ee5a6f 100%); text-decoration: none; color: white; position: relative;">
                            <i class="fas fa-calendar-plus"></i>
                            <span>Slot Requests</span>
                            <?php
                            // Get pending slot requests count
                            $pending_slot_count = 0;
                            $slot_count_stmt = $conn->prepare("SELECT COUNT(*) as c FROM admin_slot_requests WHERE teacher_id = ? AND status = 'pending'");
                            if ($slot_count_stmt) {
                                $slot_count_stmt->bind_param("i", $teacher_id);
                                $slot_count_stmt->execute();
                                $slot_count_result = $slot_count_stmt->get_result();
                                if ($slot_count_result) {
                                    $pending_slot_count = $slot_count_result->fetch_assoc()['c'] ?? 0;
                                }
                                $slot_count_stmt->close();
                            }
                            ?>
                            <?php if ($pending_slot_count > 0): ?>
                                <span class="notification-badge" style="position: absolute; top: -5px; right: -5px; background: #ffffff; color: #dc3545; border-radius: 50%; width: 22px; height: 22px; display: flex; align-items: center; justify-content: center; font-size: 0.75rem; font-weight: bold; box-shadow: 0 2px 4px rgba(0,0,0,0.2);"><?php echo $pending_slot_count; ?></span>
                            <?php endif; ?>
                        </a>
                    </div>
                </div>

                <!-- Teaching & Students Section -->
                <div class="card" style="border-top: 4px solid #28a745; background: linear-gradient(135deg, #e8f5e9 0%, #ffffff 100%);">
                    <h3 style="margin-top: 0; color: #155724; display: flex; align-items: center; gap: 10px;">
                        <i class="fas fa-chalkboard-teacher" style="font-size: 1.3rem;"></i>
                        Teaching & Students
                    </h3>
                    <div style="display: flex; flex-direction: column; gap: 10px;">
                        <a href="#" onclick="switchTab('students'); return false;" class="quick-action-btn" style="background: linear-gradient(135deg, #28a745 0%, #20c997 100%); text-decoration: none; color: white;">
                            <i class="fas fa-users"></i>
                            <span>My Students</span>
                        </a>
                        <a href="#" onclick="switchTab('assignments'); return false;" class="quick-action-btn" style="background: linear-gradient(135deg, #ffc107 0%, #ff9800 100%); text-decoration: none; color: white; position: relative;">
                            <i class="fas fa-tasks"></i>
                            <span>Assignments</span>
                            <?php if ($pending_assignments > 0): ?>
                                <span class="notification-badge" style="position: absolute; top: -5px; right: -5px; background: #dc3545; color: white; border-radius: 50%; width: 22px; height: 22px; display: flex; align-items: center; justify-content: center; font-size: 0.75rem; font-weight: bold; box-shadow: 0 2px 4px rgba(0,0,0,0.2);"><?php echo $pending_assignments; ?></span>
                            <?php endif; ?>
                        </a>
                        <a href="#" onclick="switchTab('performance'); return false;" class="quick-action-btn" style="background: linear-gradient(135deg, #17a2b8 0%, #138496 100%); text-decoration: none; color: white;">
                            <i class="fas fa-chart-line"></i>
                            <span>Performance</span>
                        </a>
                    </div>
                </div>

                <!-- Communication Section -->
                <div class="card" style="border-top: 4px solid #9c27b0; background: linear-gradient(135deg, #f3e5f5 0%, #ffffff 100%);">
                    <h3 style="margin-top: 0; color: #4a148c; display: flex; align-items: center; gap: 10px;">
                        <i class="fas fa-comments" style="font-size: 1.3rem;"></i>
                        Communication
                    </h3>
                    <div style="display: flex; flex-direction: column; gap: 10px;">
                        <a href="#" onclick="switchTab('messages'); return false;" class="quick-action-btn" style="background: linear-gradient(135deg, #9c27b0 0%, #7b1fa2 100%); text-decoration: none; color: white; position: relative;">
                            <i class="fas fa-inbox"></i>
                            <span>Messages</span>
                            <?php if ($unread_messages > 0): ?>
                                <span class="notification-badge" style="position: absolute; top: -5px; right: -5px; background: #dc3545; color: white; border-radius: 50%; width: 22px; height: 22px; display: flex; align-items: center; justify-content: center; font-size: 0.75rem; font-weight: bold; box-shadow: 0 2px 4px rgba(0,0,0,0.2);"><?php echo $unread_messages; ?></span>
                            <?php endif; ?>
                        </a>
                        <a href="classroom.php" class="quick-action-btn" style="background: linear-gradient(135deg, #e91e63 0%, #c2185b 100%); text-decoration: none; color: white;">
                            <i class="fas fa-video"></i>
                            <span>Classroom</span>
                        </a>
                    </div>
                </div>

                <!-- Resources & Materials Section -->
                <div class="card" style="border-top: 4px solid #ff9800; background: linear-gradient(135deg, #fff3e0 0%, #ffffff 100%);">
                    <h3 style="margin-top: 0; color: #e65100; display: flex; align-items: center; gap: 10px;">
                        <i class="fas fa-folder-open" style="font-size: 1.3rem;"></i>
                        Resources & Materials
                    </h3>
                    <div style="display: flex; flex-direction: column; gap: 10px;">
                        <a href="#" onclick="switchTab('materials'); return false;" class="quick-action-btn" style="background: linear-gradient(135deg, #ff9800 0%, #f57c00 100%); text-decoration: none; color: white;">
                            <i class="fas fa-book"></i>
                            <span>Materials</span>
                        </a>
                        <a href="#" onclick="switchTab('group-classes'); return false;" class="quick-action-btn" style="background: linear-gradient(135deg, #607d8b 0%, #455a64 100%); text-decoration: none; color: white;">
                            <i class="fas fa-users"></i>
                            <span>Group Classes</span>
                        </a>
                    </div>
                </div>

                <!-- Profile & Settings Section -->
                <div class="card" style="border-top: 4px solid #607d8b; background: linear-gradient(135deg, #eceff1 0%, #ffffff 100%);">
                    <h3 style="margin-top: 0; color: #263238; display: flex; align-items: center; gap: 10px;">
                        <i class="fas fa-cog" style="font-size: 1.3rem;"></i>
                        Profile & Settings
                    </h3>
                    <div style="display: flex; flex-direction: column; gap: 10px;">
                        <a href="profile.php?id=<?php echo $teacher_id; ?>" class="quick-action-btn" style="background: linear-gradient(135deg, #607d8b 0%, #455a64 100%); text-decoration: none; color: white;">
                            <i class="fas fa-user"></i>
                            <span>View Profile</span>
                        </a>
                        <a href="#" onclick="switchTab('settings'); return false;" class="quick-action-btn" style="background: linear-gradient(135deg, #546e7a 0%, #37474f 100%); text-decoration: none; color: white;">
                            <i class="fas fa-edit"></i>
                            <span>Edit Settings</span>
                        </a>
                    </div>
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

        <!-- Performance Tab (Combined Earnings & Reviews) -->
        <div id="performance" class="tab-content">
            <h1>Performance</h1>
            <div style="display: flex; gap: 15px; margin-bottom: 30px; border-bottom: 2px solid #dee2e6; padding-bottom: 15px;">
                <button onclick="switchPerformanceSubTab('earnings')" class="btn-outline" id="perf-earnings-btn" style="border-bottom: 3px solid #0b6cf5;">
                    <i class="fas fa-dollar-sign"></i> Earnings
                </button>
                <button onclick="switchPerformanceSubTab('reviews')" class="btn-outline" id="perf-reviews-btn">
                    <i class="fas fa-star"></i> Reviews
                </button>
            </div>
            
            <div id="performance-earnings" class="performance-subtab active">
                <h2>Earnings</h2>
                <div style="background: #e1f0ff; padding: 15px; border-radius: 8px; margin-bottom: 20px; border-left: 4px solid #0b6cf5;">
                    <i class="fas fa-info-circle"></i> <strong>Note:</strong> Payouts are handled outside this system. Contact admin for payment inquiries.
                </div>
            
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
            <h1>My Students</h1>
            
            <?php
            // Get filter parameters
            $student_filter_category = $_GET['student_filter_category'] ?? '';
            $student_search = $_GET['student_search'] ?? '';
            $student_sort = $_GET['student_sort'] ?? 'recent';
            
            // Filter and sort students
            $filtered_students = $students;
            if ($student_filter_category) {
                $filtered_students = array_filter($filtered_students, function($s) use ($student_filter_category) {
                    return ($s['preferred_category'] ?? '') === $student_filter_category;
                });
            }
            if ($student_search) {
                $search_lower = strtolower($student_search);
                $filtered_students = array_filter($filtered_students, function($s) use ($search_lower) {
                    return strpos(strtolower($s['name']), $search_lower) !== false || 
                           strpos(strtolower($s['email']), $search_lower) !== false;
                });
            }
            
            // Sort students
            if ($student_sort === 'name') {
                usort($filtered_students, function($a, $b) {
                    return strcmp($a['name'], $b['name']);
                });
            } elseif ($student_sort === 'lessons') {
                usort($filtered_students, function($a, $b) {
                    return ($b['lesson_count'] ?? 0) - ($a['lesson_count'] ?? 0);
                });
            } else {
                // Recent (default) - already sorted by last_lesson_date DESC
            }
            
            // Get attendance stats for each student
            foreach ($filtered_students as &$student) {
                $attendance_stmt = $conn->prepare("
                    SELECT 
                        COUNT(*) as total_lessons,
                        SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed,
                        SUM(CASE WHEN status = 'cancelled' THEN 1 ELSE 0 END) as cancelled,
                        SUM(CASE WHEN status = 'no_show' THEN 1 ELSE 0 END) as no_show
                    FROM lessons 
                    WHERE student_id = ? AND teacher_id = ?
                ");
                $attendance_stmt->bind_param("ii", $student['id'], $teacher_id);
                $attendance_stmt->execute();
                $attendance_result = $attendance_stmt->get_result();
                $attendance = $attendance_result->fetch_assoc();
                $student['attendance'] = $attendance;
                $attendance_stmt->close();
            }
            unset($student);
            ?>
            
            <?php if (!empty($teacher_categories)): ?>
                <div style="margin-bottom: 20px; padding: 15px; background: #f0f7ff; border-radius: 8px; border-left: 4px solid #0b6cf5;">
                    <strong>Teaching Categories:</strong> 
                    <?php foreach ($teacher_categories as $category): ?>
                        <span style="display: inline-block; padding: 5px 15px; background: white; border-radius: 20px; margin-left: 10px; font-size: 0.9rem;">
                            <i class="fas fa-<?php echo $category === 'young_learners' ? 'child' : ($category === 'coding' ? 'code' : 'user-graduate'); ?>"></i>
                            <?php echo ucfirst(str_replace('_', ' ', $category)); ?>
                        </span>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
            
            <div class="card" style="margin-bottom: 30px;">
                <h2><i class="fas fa-filter"></i> Filters & Search</h2>
                <form method="GET" action="" style="display: grid; grid-template-columns: 2fr 1fr 1fr auto; gap: 15px; align-items: end;">
                    <input type="hidden" name="tab" value="students">
                    <div>
                        <label>Search</label>
                        <input type="text" name="student_search" value="<?php echo h($student_search); ?>" 
                               placeholder="Search by name or email..." class="form-control">
                    </div>
                    <div>
                        <label>Category</label>
                        <select name="student_filter_category" class="form-control">
                            <option value="">All Categories</option>
                            <option value="young_learners" <?php echo $student_filter_category === 'young_learners' ? 'selected' : ''; ?>>Young Learners</option>
                            <option value="adults" <?php echo $student_filter_category === 'adults' ? 'selected' : ''; ?>>Adults</option>
                            <option value="coding" <?php echo $student_filter_category === 'coding' ? 'selected' : ''; ?>>Coding/Tech</option>
                        </select>
                    </div>
                    <div>
                        <label>Sort By</label>
                        <select name="student_sort" class="form-control">
                            <option value="recent" <?php echo $student_sort === 'recent' ? 'selected' : ''; ?>>Most Recent</option>
                            <option value="name" <?php echo $student_sort === 'name' ? 'selected' : ''; ?>>Name (A-Z)</option>
                            <option value="lessons" <?php echo $student_sort === 'lessons' ? 'selected' : ''; ?>>Most Lessons</option>
                        </select>
                    </div>
                    <div>
                        <button type="submit" class="btn-primary">
                            <i class="fas fa-search"></i> Filter
                        </button>
                        <?php if ($student_search || $student_filter_category): ?>
                        <a href="teacher-dashboard.php#students" class="btn-outline" style="margin-left: 10px;">
                            <i class="fas fa-times"></i> Clear
                        </a>
                        <?php endif; ?>
                    </div>
                </form>
            </div>
            
            <?php if (count($filtered_students) > 0): ?>
                <?php foreach ($filtered_students as $student): 
                    $attendance = $student['attendance'] ?? [];
                    $total_lessons = $attendance['total_lessons'] ?? 0;
                    $completed = $attendance['completed'] ?? 0;
                    $attendance_rate = $total_lessons > 0 ? round(($completed / $total_lessons) * 100) : 0;
                ?>
                <div class="card" style="margin-bottom: 15px;">
                    <div style="display: flex; gap: 20px; align-items: flex-start;">
                        <img src="<?php echo h($student['profile_pic'] ?? getAssetPath('images/placeholder-teacher.svg')); ?>" alt="" style="width: 60px; height: 60px; border-radius: 50%; object-fit: cover;" onerror="this.src='<?php echo getAssetPath('images/placeholder-teacher.svg'); ?>'">
                        <div style="flex: 1;">
                            <div style="display: flex; align-items: center; gap: 10px; margin-bottom: 5px;">
                                <h3 style="margin: 0; border: none; padding: 0;"><?php echo h($student['name']); ?></h3>
                                <?php if (!empty($student['preferred_category'])): ?>
                                    <span style="display: inline-block; padding: 3px 10px; background: #e1f0ff; color: #0b6cf5; border-radius: 12px; font-size: 0.75rem; font-weight: 600;">
                                        <?php echo ucfirst(str_replace('_', ' ', $student['preferred_category'])); ?>
                                    </span>
                                <?php endif; ?>
                            </div>
                            <div style="font-size: 0.9rem; color: var(--gray); margin-bottom: 10px;">
                                <?php echo h($student['email']); ?> • <?php echo $student['lesson_count']; ?> lessons
                                <?php if (!empty($student['last_lesson_date'])): ?>
                                    • Last lesson <?php echo date('M d, Y', strtotime($student['last_lesson_date'])); ?>
                                <?php endif; ?>
                            </div>
                            
                            <?php if ($total_lessons > 0): ?>
                            <div style="display: flex; gap: 20px; margin-bottom: 10px; padding: 10px; background: #f8f9fa; border-radius: 8px;">
                                <div>
                                    <strong style="color: #28a745;"><?php echo $attendance_rate; ?>%</strong>
                                    <small style="color: #666; display: block;">Attendance Rate</small>
                                </div>
                                <div>
                                    <strong><?php echo $completed; ?></strong>
                                    <small style="color: #666; display: block;">Completed</small>
                                </div>
                                <div>
                                    <strong style="color: #dc3545;"><?php echo $attendance['cancelled'] ?? 0; ?></strong>
                                    <small style="color: #666; display: block;">Cancelled</small>
                                </div>
                                <div>
                                    <strong style="color: #ffc107;"><?php echo $attendance['no_show'] ?? 0; ?></strong>
                                    <small style="color: #666; display: block;">No Show</small>
                                </div>
                            </div>
                            <?php endif; ?>
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
                            <a href="message_threads.php?user_id=<?php echo $student['id']; ?>" class="btn-outline btn-sm">Message</a>
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

                </div>
            </div>
            
            <div id="performance-reviews" class="performance-subtab" style="display: none;">
                <h2>Reviews</h2>
            
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

        <!-- Slot Requests Tab -->
        <div id="slot-requests" class="tab-content">
            <div style="margin-bottom: 25px;">
                <h1 style="margin-bottom: 10px; display: flex; align-items: center; gap: 15px;">
                    <div style="width: 50px; height: 50px; border-radius: 12px; background: linear-gradient(135deg, #ff6b6b 0%, #ee5a6f 100%); display: flex; align-items: center; justify-content: center; color: white; font-size: 1.5rem;">
                        <i class="fas fa-calendar-plus"></i>
                    </div>
                    Slot Requests
                </h1>
                <p style="color: var(--gray); font-size: 1rem; line-height: 1.6;">
                    Review and respond to admin requests for opening specific time slots. When you accept, the slot will be <strong>immediately added</strong> to your calendar and available for students to book.
                </p>
            </div>
            
            <div id="slot-requests-container">
                <div style="text-align: center; padding: 60px 40px;">
                    <div style="display: inline-block; padding: 20px; background: #f8f9fa; border-radius: 50%; margin-bottom: 20px;">
                        <i class="fas fa-spinner fa-spin" style="font-size: 2.5rem; color: #0b6cf5;"></i>
                    </div>
                    <p style="margin-top: 15px; color: #666; font-size: 1.1rem;">Loading slot requests...</p>
                </div>
            </div>
        </div>

        <!-- Calendar Editor Tab -->
        <div id="calendar" class="tab-content">
            <h1><i class="fas fa-calendar-alt"></i> Manage Availability</h1>
            <p style="color: var(--gray); margin-bottom: 30px;">Set your available time slots for students to book lessons.</p>
            
            <?php
            // Fetch existing availability slots
            $availability_slots = [];
            $stmt = $conn->prepare("
                SELECT * FROM teacher_availability_slots 
                WHERE teacher_id = ? 
                ORDER BY day_of_week, start_time
            ");
            $stmt->bind_param("i", $teacher_id);
            $stmt->execute();
            $slots_result = $stmt->get_result();
            while ($row = $slots_result->fetch_assoc()) {
                $availability_slots[] = $row;
            }
            $stmt->close();
            
            // Fetch all lessons for calendar (past 30 days and future)
            $all_lessons_for_calendar = [];
            $calendar_stmt = $conn->prepare("
                SELECT l.*, u.name as student_name, u.profile_pic as student_pic, l.category, l.status
                FROM lessons l
                JOIN users u ON l.student_id = u.id
                WHERE l.teacher_id = ? 
                AND l.lesson_date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
                ORDER BY l.lesson_date ASC, l.start_time ASC
            ");
            $calendar_stmt->bind_param("i", $teacher_id);
            $calendar_stmt->execute();
            $calendar_result = $calendar_stmt->get_result();
            while ($row = $calendar_result->fetch_assoc()) {
                $all_lessons_for_calendar[] = $row;
            }
            $calendar_stmt->close();
            
            // Prepare calendar events JSON
            $calendar_events = [];
            foreach ($all_lessons_for_calendar as $lesson) {
                // Skip if required fields are missing
                if (empty($lesson['lesson_date']) || empty($lesson['start_time']) || empty($lesson['end_time'])) {
                    continue;
                }
                
                $start_datetime = $lesson['lesson_date'] . 'T' . $lesson['start_time'];
                $end_datetime = $lesson['lesson_date'] . 'T' . $lesson['end_time'];
                
                // Calculate duration in minutes
                $start_timestamp = strtotime('2000-01-01 ' . $lesson['start_time']);
                $end_timestamp = strtotime('2000-01-01 ' . $lesson['end_time']);
                $duration = ($start_timestamp && $end_timestamp && $end_timestamp > $start_timestamp) 
                    ? round(($end_timestamp - $start_timestamp) / 60) 
                    : 60; // Default to 60 minutes if calculation fails
                
                // Color coding by status and category
                $color = '#0b6cf5'; // Default blue
                if (!empty($lesson['status']) && $lesson['status'] === 'completed') $color = '#28a745';
                elseif (!empty($lesson['status']) && $lesson['status'] === 'cancelled') $color = '#dc3545';
                elseif (!empty($lesson['is_trial']) && $lesson['is_trial']) $color = '#ffc107';
                elseif (!empty($lesson['category']) && $lesson['category'] === 'young_learners') $color = '#17a2b8';
                elseif (!empty($lesson['category']) && $lesson['category'] === 'coding') $color = '#6f42c1';
                
                $calendar_events[] = [
                    'id' => $lesson['id'],
                    'title' => (!empty($lesson['student_name']) ? $lesson['student_name'] : 'Student') . (!empty($lesson['is_trial']) && $lesson['is_trial'] ? ' (Trial)' : ''),
                    'start' => $start_datetime,
                    'end' => $end_datetime,
                    'color' => $color,
                    'extendedProps' => [
                        'student_name' => $lesson['student_name'] ?? '',
                        'category' => $lesson['category'] ?? '',
                        'status' => $lesson['status'] ?? 'scheduled',
                        'is_trial' => !empty($lesson['is_trial']) ? 1 : 0,
                        'duration' => $duration
                    ]
                ];
            }
            ?>
            
            <div class="card" style="margin-bottom: 30px;">
                <h2><i class="fas fa-calendar-check"></i> Calendar View</h2>
                <div id="teacher-calendar" style="margin-bottom: 20px;"></div>
                <div style="display: flex; gap: 20px; flex-wrap: wrap; margin-top: 15px;">
                    <div style="display: flex; align-items: center; gap: 8px;">
                        <div style="width: 20px; height: 20px; background: #0b6cf5; border-radius: 4px;"></div>
                        <span>Scheduled</span>
                    </div>
                    <div style="display: flex; align-items: center; gap: 8px;">
                        <div style="width: 20px; height: 20px; background: #ffc107; border-radius: 4px;"></div>
                        <span>Trial Lesson</span>
                    </div>
                    <div style="display: flex; align-items: center; gap: 8px;">
                        <div style="width: 20px; height: 20px; background: #28a745; border-radius: 4px;"></div>
                        <span>Completed</span>
                    </div>
                    <div style="display: flex; align-items: center; gap: 8px;">
                        <div style="width: 20px; height: 20px; background: #dc3545; border-radius: 4px;"></div>
                        <span>Cancelled</span>
                    </div>
                    <div style="display: flex; align-items: center; gap: 8px;">
                        <div style="width: 20px; height: 20px; background: #17a2b8; border-radius: 4px;"></div>
                        <span>Young Learners</span>
                    </div>
                    <div style="display: flex; align-items: center; gap: 8px;">
                        <div style="width: 20px; height: 20px; background: #6f42c1; border-radius: 4px;"></div>
                        <span>Coding/Tech</span>
                    </div>
                </div>
            </div>
            
            <div class="card" style="margin-bottom: 30px;">
                <h2><i class="fas fa-plus-circle"></i> Add Availability Slot</h2>
                <form method="POST">
                    <div class="profile-grid">
                        <div class="form-group">
                            <label>Day of Week (for recurring slots)</label>
                            <select name="day_of_week">
                                <option value="">Select day...</option>
                                <option value="Monday">Monday</option>
                                <option value="Tuesday">Tuesday</option>
                                <option value="Wednesday">Wednesday</option>
                                <option value="Thursday">Thursday</option>
                                <option value="Friday">Friday</option>
                                <option value="Saturday">Saturday</option>
                                <option value="Sunday">Sunday</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Specific Date (optional, for one-time slots)</label>
                            <input type="date" name="specific_date">
                        </div>
                        <div class="form-group">
                            <label>Start Time</label>
                            <input type="time" name="start_time" required>
                        </div>
                        <div class="form-group">
                            <label>End Time</label>
                            <input type="time" name="end_time" required>
                        </div>
                        <div class="form-group">
                            <label>Timezone</label>
                            <select name="timezone" required>
                                <option value="America/New_York" selected>Eastern Time (ET)</option>
                                <option value="America/Chicago">Central Time (CT)</option>
                                <option value="America/Denver">Mountain Time (MT)</option>
                                <option value="America/Los_Angeles">Pacific Time (PT)</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>
                                <input type="checkbox" name="is_recurring" value="1" checked> Recurring Weekly
                            </label>
                        </div>
                    </div>
                    <button type="submit" name="add_availability_slot" class="btn-primary">
                        <i class="fas fa-plus"></i> Add Slot
                    </button>
                </form>
            </div>
            
            <div class="card">
                <h2><i class="fas fa-list"></i> Current Availability Slots</h2>
                <?php if (count($availability_slots) > 0): ?>
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Day/Date</th>
                            <th>Time</th>
                            <th>Timezone</th>
                            <th>Type</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($availability_slots as $slot): ?>
                        <tr>
                            <td>
                                <?php 
                                if ($slot['specific_date']) {
                                    echo date('M d, Y', strtotime($slot['specific_date']));
                                } else {
                                    echo $slot['day_of_week'] ?? 'N/A';
                                }
                                ?>
                            </td>
                            <td><?php echo date('g:i A', strtotime($slot['start_time'])); ?> - <?php echo date('g:i A', strtotime($slot['end_time'])); ?></td>
                            <td><?php echo $slot['timezone']; ?></td>
                            <td><?php echo $slot['is_recurring'] ? 'Recurring' : 'One-time'; ?></td>
                            <td>
                                <span class="tag <?php echo $slot['is_available'] ? 'success' : 'warning'; ?>">
                                    <?php echo $slot['is_available'] ? 'Available' : 'Unavailable'; ?>
                                </span>
                            </td>
                            <td>
                                <form method="POST" style="display: inline;">
                                    <input type="hidden" name="slot_id" value="<?php echo $slot['id']; ?>">
                                    <button type="submit" name="toggle_availability_slot" class="btn-outline btn-sm">
                                        <?php echo $slot['is_available'] ? 'Disable' : 'Enable'; ?>
                                    </button>
                                </form>
                                <form method="POST" style="display: inline;" onsubmit="return confirm('Are you sure you want to delete this slot?');">
                                    <input type="hidden" name="slot_id" value="<?php echo $slot['id']; ?>">
                                    <button type="submit" name="delete_availability_slot" class="btn-danger btn-sm">
                                        <i class="fas fa-trash"></i> Delete
                                    </button>
                                </form>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <?php else: ?>
                <div class="empty-state">
                    <i class="fas fa-calendar-times"></i>
                    <h3>No Availability Slots</h3>
                    <p>Add availability slots above to allow students to book lessons with you.</p>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Messages Tab -->
        <div id="messages" class="tab-content">
            <h1><i class="fas fa-envelope"></i> Messages</h1>
            <p style="color: var(--gray); margin-bottom: 30px;">Communicate with your students.</p>
            
            <?php
            // Fetch message threads for this teacher
            $message_threads = [];
            $stmt = $conn->prepare("
                SELECT 
                    t.other_user_id,
                    u.name as other_user_name,
                    u.profile_pic as other_user_pic,
                    (SELECT message FROM messages WHERE (sender_id = ? AND receiver_id = t.other_user_id) OR (sender_id = t.other_user_id AND receiver_id = ?) ORDER BY sent_at DESC LIMIT 1) as last_message,
                    (SELECT sent_at FROM messages WHERE (sender_id = ? AND receiver_id = t.other_user_id) OR (sender_id = t.other_user_id AND receiver_id = ?) ORDER BY sent_at DESC LIMIT 1) as last_message_time,
                    (SELECT COUNT(*) FROM messages WHERE receiver_id = ? AND sender_id = t.other_user_id AND is_read = 0) as unread_count
                FROM (
                    SELECT DISTINCT 
                        CASE 
                            WHEN m.sender_id = ? THEN m.receiver_id
                            ELSE m.sender_id
                        END as other_user_id
                    FROM messages m
                    WHERE (m.sender_id = ? OR m.receiver_id = ?)
                ) t
                JOIN users u ON t.other_user_id = u.id
                WHERE u.role IN ('student', 'new_student')
                ORDER BY last_message_time DESC
            ");
            $stmt->bind_param("iiiiiiii", $teacher_id, $teacher_id, $teacher_id, $teacher_id, $teacher_id, $teacher_id, $teacher_id, $teacher_id);
            $stmt->execute();
            $threads_result = $stmt->get_result();
            while ($row = $threads_result->fetch_assoc()) {
                $message_threads[] = $row;
            }
            $stmt->close();
            ?>
            
            <?php if (count($message_threads) > 0): ?>
                <?php foreach ($message_threads as $thread): ?>
                <div class="card" style="margin-bottom: 15px; cursor: pointer;" onclick="window.location.href='message_threads.php?user_id=<?php echo $thread['other_user_id']; ?>'">
                    <div style="display: flex; gap: 15px; align-items: center;">
                        <img src="<?php echo h($thread['other_user_pic'] ?? getAssetPath('images/placeholder-teacher.svg')); ?>" alt="" style="width: 50px; height: 50px; border-radius: 50%; object-fit: cover;" onerror="this.src='<?php echo getAssetPath('images/placeholder-teacher.svg'); ?>'">
                        <div style="flex: 1;">
                            <div style="display: flex; align-items: center; gap: 10px; margin-bottom: 5px;">
                                <h3 style="margin: 0; border: none; padding: 0;"><?php echo h($thread['other_user_name']); ?></h3>
                                <?php if ($thread['unread_count'] > 0): ?>
                                    <span style="background: #0b6cf5; color: white; padding: 2px 8px; border-radius: 12px; font-size: 0.75rem; font-weight: 600;">
                                        <?php echo $thread['unread_count']; ?> new
                                    </span>
                                <?php endif; ?>
                            </div>
                            <p style="color: var(--gray); font-size: 0.9rem; margin: 0;">
                                <?php echo h(substr($thread['last_message'] ?? 'No messages', 0, 80)); ?>...
                            </p>
                            <p style="color: var(--gray); font-size: 0.8rem; margin: 5px 0 0;">
                                <?php echo $thread['last_message_time'] ? formatRelativeTime($thread['last_message_time']) : 'No messages'; ?>
                            </p>
                        </div>
                        <i class="fas fa-chevron-right" style="color: var(--gray);"></i>
                    </div>
                </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="empty-state">
                    <i class="fas fa-envelope-open"></i>
                    <h3>No Messages Yet</h3>
                    <p>Messages from students will appear here.</p>
                </div>
            <?php endif; ?>
        </div>

        <!-- Group Classes Tab -->
        <div id="group-classes" class="tab-content">
            <h1><i class="fas fa-users"></i> Group Classes</h1>
            <p style="color: var(--gray); margin-bottom: 30px;">Create and manage group class requests.</p>
            
            <div class="card" style="margin-bottom: 30px;">
                <h2><i class="fas fa-plus-circle"></i> Create Group Class Request</h2>
                <form method="POST">
                    <div class="profile-grid">
                        <div class="form-group">
                            <label>Category/Track</label>
                            <select name="track" required>
                                <option value="">Select category...</option>
                                <option value="young_learners">Young Learners (0-11)</option>
                                <option value="adults">Adults (12+)</option>
                                <option value="coding">English for Coding / Tech</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Scheduled Date</label>
                            <input type="date" name="scheduled_date" required>
                        </div>
                        <div class="form-group">
                            <label>Scheduled Time</label>
                            <input type="time" name="scheduled_time" required>
                        </div>
                        <div class="form-group">
                            <label>Duration (minutes)</label>
                            <input type="number" name="duration" value="60" min="30" step="15" required>
                        </div>
                        <div class="form-group">
                            <label>Max Students</label>
                            <input type="number" name="max_students" value="10" min="2" max="50" required>
                        </div>
                    </div>
                    <div class="form-group">
                        <label>Title</label>
                        <input type="text" name="title" placeholder="e.g., Intermediate Conversation Practice" required>
                    </div>
                    <div class="form-group">
                        <label>Description</label>
                        <textarea name="description" rows="4" placeholder="Describe the class content and objectives..."></textarea>
                    </div>
                    <button type="submit" name="create_group_class_request" class="btn-primary">
                        <i class="fas fa-paper-plane"></i> Submit Request
                    </button>
                </form>
            </div>
            
            <div class="card">
                <h2><i class="fas fa-list"></i> My Group Classes</h2>
                <?php if (count($group_classes) > 0): ?>
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Title</th>
                            <th>Category</th>
                            <th>Date & Time</th>
                            <th>Duration</th>
                            <th>Max Students</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($group_classes as $class): ?>
                        <tr>
                            <td><?php echo h($class['title']); ?></td>
                            <td><?php echo ucfirst(str_replace('_', ' ', $class['track'] ?? 'N/A')); ?></td>
                            <td>
                                <?php echo date('M d, Y', strtotime($class['scheduled_date'])); ?><br>
                                <small><?php echo date('g:i A', strtotime($class['scheduled_time'])); ?></small>
                            </td>
                            <td><?php echo $class['duration']; ?> min</td>
                            <td><?php echo $class['max_students']; ?></td>
                            <td>
                                <span class="tag <?php echo $class['status']; ?>">
                                    <?php echo ucfirst($class['status']); ?>
                                </span>
                            </td>
                            <td>
                                <button onclick="viewGroupClassStudents(<?php echo $class['id']; ?>)" class="btn-outline btn-sm">
                                    <i class="fas fa-users"></i> View Students
                                </button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <?php else: ?>
                <div class="empty-state">
                    <i class="fas fa-users"></i>
                    <h3>No Group Classes Yet</h3>
                    <p>Create a group class request above to get started.</p>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Materials Tab -->
        <div id="materials" class="tab-content">
            <h1><i class="fas fa-folder-open"></i> Materials</h1>
            <p style="color: var(--gray); margin-bottom: 30px;">Manage your teaching resources and shared materials.</p>
            
            <!-- Sub-tab Navigation -->
            <div style="display: flex; gap: 15px; margin-bottom: 30px; border-bottom: 2px solid #dee2e6; padding-bottom: 15px;">
                <button onclick="switchMaterialsSubTab('resources')" class="btn-outline" id="mat-resources-btn" style="border-bottom: 3px solid #0b6cf5;">
                    <i class="fas fa-book"></i> My Resources
                </button>
                <button onclick="switchMaterialsSubTab('shared-materials')" class="btn-outline" id="mat-shared-btn">
                    <i class="fas fa-share-alt"></i> Shared Materials
                </button>
            </div>
            
            <!-- Resources Sub-tab -->
            <div id="materials-resources" class="materials-subtab">
                <h2>Resource Library</h2>
                
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
            
            <!-- Shared Materials Sub-tab -->
            <div id="materials-shared-materials" class="materials-subtab" style="display: none;">
                <h2>Shared Materials Library</h2>
                <p style="color: var(--gray); margin-bottom: 20px;">
                    All teachers can add materials here. All materials are visible to all teachers for use during lessons.
                </p>
                
                <div class="card" style="background: linear-gradient(135deg, #f0f7ff 0%, #ffffff 100%); border: 2px solid #0b6cf5;">
                <div style="display: flex; align-items: center; gap: 15px; margin-bottom: 25px;">
                    <div style="width: 50px; height: 50px; border-radius: 12px; background: linear-gradient(135deg, #0b6cf5 0%, #004080 100%); display: flex; align-items: center; justify-content: center; color: white; font-size: 1.5rem;">
                        <i class="fas fa-upload"></i>
                    </div>
                    <div>
                        <h2 style="margin: 0; color: #004080;">Add Shared Material</h2>
                        <p style="margin: 5px 0 0; color: #666; font-size: 0.9rem;">Share teaching materials with all teachers</p>
                    </div>
                </div>
                
                <form id="addMaterialForm" enctype="multipart/form-data">
                    <div class="profile-grid">
                        <div class="form-group">
                            <label>Title *</label>
                            <input type="text" name="title" id="material_title" placeholder="e.g., Beginner Vocabulary Worksheet" required>
                        </div>
                        <div class="form-group">
                            <label>Category *</label>
                            <select name="category" id="material_category" required style="padding: 10px;">
                                <option value="general">General (All Classes)</option>
                                <option value="kids">Kids Classes</option>
                                <option value="adults">Adult Classes</option>
                                <option value="coding">English for Coding</option>
                            </select>
                        </div>
                    </div>
                    <div class="profile-grid">
                        <div class="form-group">
                            <label>Material Type *</label>
                            <select name="type" id="material_type" onchange="toggleMaterialInputs()" required style="padding: 10px;">
                                <option value="file">File Upload (PDF, DOC, Images, Videos)</option>
                                <option value="link">External Link</option>
                                <option value="video">Video Link (YouTube, etc.)</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Tags (optional)</label>
                            <input type="text" name="tags" id="material_tags" placeholder="e.g., vocabulary, grammar, beginner (comma-separated)">
                            <small style="color: var(--gray); display: block; margin-top: 5px;">Add keywords to help teachers find this material</small>
                        </div>
                    </div>
                    <div class="form-group">
                        <label>Description</label>
                        <textarea name="description" id="material_description" rows="3" placeholder="Brief description of what this material covers and how to use it..."></textarea>
                    </div>
                    <div class="form-group" id="file-input-group">
                        <label>File</label>
                        <div id="material-dropzone" style="border: 3px dashed var(--primary-light); border-radius: 12px; padding: 40px; text-align: center; background: #ffffff; cursor: pointer; transition: all 0.3s; position: relative; overflow: hidden;" 
                             onmouseover="this.style.borderColor='var(--primary)'; this.style.boxShadow='0 4px 15px rgba(11, 108, 245, 0.2)';" 
                             onmouseout="this.style.borderColor='var(--primary-light)'; this.style.boxShadow='none';">
                            <div style="position: absolute; top: 0; left: 0; right: 0; height: 4px; background: linear-gradient(90deg, #0b6cf5, #004080); opacity: 0; transition: opacity 0.3s;" id="dropzone-progress"></div>
                            <i class="fas fa-cloud-upload-alt" style="font-size: 3rem; color: var(--primary); margin-bottom: 15px; display: block;"></i>
                            <div style="color: var(--gray); margin-bottom: 10px; font-size: 1.1rem;">
                                <strong style="color: var(--primary); display: block; margin-bottom: 5px;">Drag & Drop Files Here</strong>
                                <span style="font-size: 0.9rem;">or click to browse</span>
                            </div>
                            <div id="material-file-info" style="color: var(--success); font-size: 0.9rem; margin-top: 15px; font-weight: 600;"></div>
                            <input type="file" name="file" id="material_file" accept=".pdf,.doc,.docx,.ppt,.pptx,.txt,.jpg,.jpeg,.png,.gif,.mp4,.mp3,.zip,.mov,.avi,.webm" style="display: none;" onchange="handleMaterialFileChange(this)">
                        </div>
                        <small style="color: var(--gray); display: block; margin-top: 8px; line-height: 1.5;">
                            <i class="fas fa-info-circle"></i> Max 50MB. Supported formats: PDF, DOC/DOCX, PPT/PPTX, Images (JPG, PNG, GIF), Videos (MP4, MOV, AVI, WEBM), ZIP archives
                        </small>
                    </div>
                    <div class="form-group" id="link-input-group" style="display: none;">
                        <label>Link URL *</label>
                        <input type="url" name="link_url" id="material_link_url" placeholder="https://example.com/resource" style="padding: 12px;">
                        <small style="color: var(--gray); display: block; margin-top: 5px;">Enter a valid URL for the external resource</small>
                    </div>
                    <div style="display: flex; gap: 15px; margin-top: 25px;">
                        <button type="submit" class="btn-primary" style="flex: 1; padding: 14px 24px; font-size: 1rem; font-weight: 600;">
                            <i class="fas fa-upload"></i> Add Shared Material
                        </button>
                        <button type="reset" class="btn-secondary" onclick="document.getElementById('addMaterialForm').reset(); document.getElementById('material-file-info').textContent=''; toggleMaterialInputs();" style="padding: 14px 24px;">
                            <i class="fas fa-redo"></i> Clear
                        </button>
                    </div>
                </form>
            </div>

                <div style="margin-top: 30px; margin-bottom: 20px;">
                    <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 15px;">
                        <h2 style="margin: 0;">All Shared Materials</h2>
                        <div style="display: flex; gap: 10px; flex-wrap: wrap;">
                            <select id="material-category-filter" onchange="filterMaterials()" style="padding: 8px 12px; border: 1px solid #ddd; border-radius: 6px;">
                                <option value="">All Categories</option>
                                <option value="general">General</option>
                                <option value="kids">Kids Classes</option>
                                <option value="adults">Adult Classes</option>
                                <option value="coding">English for Coding</option>
                            </select>
                            <input type="text" id="material-search" placeholder="Search materials..." onkeyup="filterMaterials()" style="padding: 8px 12px; border: 1px solid #ddd; border-radius: 6px; min-width: 200px;">
                        </div>
                    </div>
                </div>
                <div id="materials-list" style="display: grid; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); gap: 20px;">
                    <!-- Materials will be loaded here via JavaScript -->
                </div>
            </div>
        </div>

        <!-- Resources Tab (legacy - redirects to materials) -->
        <div id="resources" class="tab-content">
            <script>
                if (window.location.hash === '#resources') {
                    window.location.hash = '#materials';
                    if (typeof switchTab === 'function') switchTab('materials');
                    if (typeof switchMaterialsSubTab === 'function') switchMaterialsSubTab('resources');
                }
            </script>
            <p>Redirecting to Materials tab...</p>
        </div>

        <!-- Shared Materials Tab (legacy - redirects to materials) -->
        <div id="shared-materials" class="tab-content">
            <script>
                if (window.location.hash === '#shared-materials') {
                    window.location.hash = '#materials';
                    if (typeof switchTab === 'function') switchTab('materials');
                    if (typeof switchMaterialsSubTab === 'function') switchMaterialsSubTab('shared-materials');
                }
            </script>
            <p>Redirecting to Materials tab...</p>
        </div>

        <!-- Settings Tab (Combined Profile & Security) -->
        <div id="settings" class="tab-content">
            <h1>Settings</h1>
            <div style="display: flex; gap: 15px; margin-bottom: 30px; border-bottom: 2px solid #dee2e6; padding-bottom: 15px;">
                <button onclick="switchSettingsSubTab('profile')" class="btn-outline" id="set-profile-btn" style="border-bottom: 3px solid #0b6cf5;">
                    <i class="fas fa-user-edit"></i> Profile
                </button>
                <button onclick="switchSettingsSubTab('security')" class="btn-outline" id="set-security-btn">
                    <i class="fas fa-lock"></i> Security
                </button>
            </div>
            
            <div id="settings-profile" class="settings-subtab active">
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
            
            <div id="settings-security" class="settings-subtab" style="display: none;">
                <h2>Security Settings</h2>
                <?php include __DIR__ . '/app/Views/components/password-change-form.php'; ?>
            </div>
        </div>

        <!-- Profile Tab (legacy - redirects to settings) -->
        <div id="profile" class="tab-content">
            <script>
                if (window.location.hash === '#profile') {
                    window.location.hash = '#settings';
                    if (typeof switchTab === 'function') switchTab('settings');
                    if (typeof switchSettingsSubTab === 'function') switchSettingsSubTab('profile');
                }
            </script>
            <p>Redirecting to Settings tab...</p>
        </div>

        <!-- Security Tab (legacy - redirects to settings) -->
        <div id="security" class="tab-content">
            <script>
                if (window.location.hash === '#security') {
                    window.location.hash = '#settings';
                    if (typeof switchTab === 'function') switchTab('settings');
                    if (typeof switchSettingsSubTab === 'function') switchSettingsSubTab('security');
                }
            </script>
            <p>Redirecting to Settings tab...</p>
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

        <!-- Support Tab -->
        <div id="support" class="tab-content">
            <div style="margin-bottom: 25px;">
                <h1 style="margin-bottom: 10px; display: flex; align-items: center; gap: 15px;">
                    <div style="width: 50px; height: 50px; border-radius: 12px; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); display: flex; align-items: center; justify-content: center; color: white; font-size: 1.5rem;">
                        <i class="fas fa-headset"></i>
                    </div>
                    Contact Support
                </h1>
                <p style="color: var(--gray); font-size: 1rem; line-height: 1.6;">
                    Send a message to our admin team. We'll review and respond shortly.
                </p>
            </div>
            
            <div class="card" style="max-width: 700px; margin: 0 auto;">
                <?php if (!empty($support_message)): ?>
                    <div class="alert-success" style="margin-bottom: 20px;">
                        <i class="fas fa-check-circle"></i> <strong>Success!</strong><br>
                        <?php echo htmlspecialchars($support_message); ?>
                    </div>
                <?php endif; ?>
                
                <?php if (!empty($support_error)): ?>
                    <div class="alert-error" style="margin-bottom: 20px;">
                        <i class="fas fa-exclamation-circle"></i> <strong>Error:</strong><br>
                        <?php echo htmlspecialchars($support_error); ?>
                    </div>
                <?php endif; ?>
                
                <form method="POST">
                    <div class="form-group">
                        <label>Your Name</label>
                        <input type="text" value="<?php echo htmlspecialchars($user['name']); ?>" disabled style="background: #f8f9fa; cursor: not-allowed;">
                        <small style="color: #999; display: block; margin-top: 5px;">
                            <span style="display: inline-block; background: #e1f0ff; color: #004080; padding: 4px 10px; border-radius: 3px; font-size: 0.85rem;"><?php echo ucfirst($user_role); ?></span>
                        </small>
                    </div>
                    
                    <div class="form-group">
                        <label>Subject <span style="color: red;">*</span></label>
                        <input type="text" name="subject" placeholder="What is your issue about?" required value="<?php echo htmlspecialchars($_POST['subject'] ?? ''); ?>" style="width: 100%; padding: 12px; border: 2px solid #ddd; border-radius: 5px; font-size: 1rem; box-sizing: border-box;">
                    </div>
                    
                    <div class="form-group">
                        <label>Message <span style="color: red;">*</span></label>
                        <textarea name="message" placeholder="Please describe your issue in detail..." required rows="8" style="width: 100%; padding: 12px; border: 2px solid #ddd; border-radius: 5px; font-size: 1rem; font-family: inherit; resize: vertical; box-sizing: border-box; min-height: 150px;"><?php echo htmlspecialchars($_POST['message'] ?? ''); ?></textarea>
                    </div>
                    
                    <button type="submit" name="send_support" class="btn-primary" style="width: 100%; padding: 14px; font-size: 1rem; font-weight: 600; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); border: none; border-radius: 5px; color: white; cursor: pointer; transition: transform 0.2s, box-shadow 0.2s;" onmouseover="this.style.transform='translateY(-2px)'; this.style.boxShadow='0 5px 15px rgba(102, 126, 234, 0.4)'" onmouseout="this.style.transform=''; this.style.boxShadow='none'">
                        <i class="fas fa-paper-plane"></i> Send Message
                    </button>
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

// Initialize FullCalendar for teacher dashboard
document.addEventListener('DOMContentLoaded', function() {
    const calendarEl = document.getElementById('teacher-calendar');
    if (calendarEl) {
        const calendar = new FullCalendar.Calendar(calendarEl, {
            initialView: 'dayGridMonth',
            headerToolbar: {
                left: 'prev,next today',
                center: 'title',
                right: 'dayGridMonth,timeGridWeek,timeGridDay'
            },
            events: <?php echo json_encode($calendar_events); ?>,
            eventClick: function(info) {
                const props = info.event.extendedProps;
                const lessonId = info.event.id;
                window.location.href = 'classroom.php?lesson_id=' + lessonId;
            },
            eventMouseEnter: function(info) {
                info.el.style.cursor = 'pointer';
            },
            height: 'auto',
            eventTimeFormat: {
                hour: 'numeric',
                minute: '2-digit',
                meridiem: 'short'
            }
        });
        calendar.render();
    }
});

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

// Enhanced drag-and-drop functionality
function setupMaterialDropzone() {
    const dropzone = document.getElementById('material-dropzone');
    const fileInput = document.getElementById('material_file');
    const progressBar = document.getElementById('dropzone-progress');
    
    if (!dropzone || !fileInput) return;
    
    // Click to browse
    dropzone.addEventListener('click', () => {
        fileInput.click();
    });
    
    // Drag and drop
    dropzone.addEventListener('dragover', (e) => {
        e.preventDefault();
        dropzone.style.borderColor = '#0b6cf5';
        dropzone.style.background = '#f0f7ff';
        dropzone.style.boxShadow = '0 4px 15px rgba(11, 108, 245, 0.3)';
        if (progressBar) progressBar.style.opacity = '1';
    });
    
    dropzone.addEventListener('dragleave', (e) => {
        e.preventDefault();
        dropzone.style.borderColor = 'var(--primary-light)';
        dropzone.style.background = '#ffffff';
        dropzone.style.boxShadow = 'none';
        if (progressBar) progressBar.style.opacity = '0';
    });
    
    dropzone.addEventListener('drop', (e) => {
        e.preventDefault();
        dropzone.style.borderColor = 'var(--primary-light)';
        dropzone.style.background = '#ffffff';
        dropzone.style.boxShadow = 'none';
        if (progressBar) progressBar.style.opacity = '0';
        
        const files = e.dataTransfer.files;
        if (files.length > 0) {
            fileInput.files = files;
            handleMaterialFileChange(fileInput);
        }
    });
}

// Initialize dropzone on page load
document.addEventListener('DOMContentLoaded', function() {
    setupMaterialDropzone();
});

// Load materials with optional filters
function loadMaterials() {
    const category = document.getElementById('material-category-filter')?.value || '';
    const search = document.getElementById('material-search')?.value || '';
    
    let url = 'api/materials.php?action=list';
    if (category) url += '&category=' + encodeURIComponent(category);
    if (search) url += '&search=' + encodeURIComponent(search);
    
    fetch(url)
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                displayMaterials(data.materials);
            }
        })
        .catch(err => console.error('Error loading materials:', err));
}

// Filter materials (with debounce for search)
let filterTimeout;
function filterMaterials() {
    clearTimeout(filterTimeout);
    filterTimeout = setTimeout(() => {
        loadMaterials();
    }, 300); // Wait 300ms after user stops typing
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
        
        const categoryColors = {
            'general': '#6c757d',
            'kids': '#ff6b6b',
            'adults': '#4ecdc4',
            'coding': '#45b7d1'
        };
        const categoryLabels = {
            'general': 'General',
            'kids': 'Kids',
            'adults': 'Adults',
            'coding': 'Coding'
        };
        const categoryColor = categoryColors[material.category] || '#6c757d';
        const categoryLabel = categoryLabels[material.category] || 'General';
        const tags = material.tags ? material.tags.split(',').map(t => t.trim()).filter(t => t) : [];
        
        return `
            <div class="card" style="margin: 0; border-left: 4px solid ${categoryColor};">
                <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 10px;">
                    <div class="material-icon" style="margin: 0;">
                        <i class="fas ${icon}" style="color: ${categoryColor};"></i>
                    </div>
                    <span style="background: ${categoryColor}; color: white; padding: 4px 10px; border-radius: 12px; font-size: 0.75rem; font-weight: 600;">
                        ${categoryLabel}
                    </span>
                </div>
                <h3 style="border: none; padding: 0; font-size: 1rem; margin-bottom: 8px; color: #333;">${escapeHtml(material.title)}</h3>
                ${material.description ? `<p style="font-size: 0.9rem; color: var(--gray); margin: 10px 0; line-height: 1.5;">${escapeHtml(material.description)}</p>` : ''}
                ${tags.length > 0 ? `
                    <div style="margin: 10px 0; display: flex; flex-wrap: wrap; gap: 5px;">
                        ${tags.map(tag => `<span style="background: #f0f0f0; padding: 3px 8px; border-radius: 10px; font-size: 0.75rem; color: #666;">#${escapeHtml(tag)}</span>`).join('')}
                    </div>
                ` : ''}
                <div style="font-size: 0.8rem; color: var(--gray); margin-bottom: 10px; padding-top: 10px; border-top: 1px solid #eee;">
                    <i class="fas fa-user"></i> ${escapeHtml(uploadedBy)}<br>
                    <i class="fas fa-calendar"></i> ${date}
                    ${material.usage_count > 0 ? `<br><i class="fas fa-chart-line"></i> Used ${material.usage_count} time${material.usage_count > 1 ? 's' : ''}` : ''}
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
    fetch(`api/materials.php?action=view&id=${materialId}`, {
        method: 'GET',
        headers: {
            'Accept': 'application/json'
        },
        credentials: 'same-origin'
    })
        .then(res => {
            if (!res.ok) {
                throw new Error(`HTTP error! status: ${res.status}`);
            }
            return res.json();
        })
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
        headers: {
            'Accept': 'application/json'
        },
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

// Sub-tab switching functions
function switchPerformanceSubTab(subTab) {
    document.querySelectorAll('.performance-subtab').forEach(el => el.style.display = 'none');
    document.querySelectorAll('#performance-earnings, #performance-reviews').forEach(el => {
        const btn = document.getElementById('perf-' + (el.id === 'performance-earnings' ? 'earnings' : 'reviews') + '-btn');
        if (btn) btn.style.borderBottom = 'none';
    });
    
    const targetTab = document.getElementById('performance-' + subTab);
    const targetBtn = document.getElementById('perf-' + subTab + '-btn');
    if (targetTab) targetTab.style.display = 'block';
    if (targetBtn) targetBtn.style.borderBottom = '3px solid #0b6cf5';
}

function switchMaterialsSubTab(subTab) {
    // Hide all subtabs
    document.querySelectorAll('.materials-subtab').forEach(el => el.style.display = 'none');
    
    // Reset all button styles
    document.querySelectorAll('#materials-resources, #materials-shared-materials').forEach(el => {
        const btn = document.getElementById('mat-' + (el.id === 'materials-resources' ? 'resources' : 'shared') + '-btn');
        if (btn) btn.style.borderBottom = 'none';
    });
    
    // Determine target tab ID
    const targetTabId = subTab === 'shared-materials' ? 'materials-shared-materials' : 'materials-resources';
    const targetTab = document.getElementById(targetTabId);
    
    // Determine target button ID
    const targetBtnId = 'mat-' + (subTab === 'shared-materials' ? 'shared' : 'resources') + '-btn';
    const targetBtn = document.getElementById(targetBtnId);
    
    // Show target tab and update button
    if (targetTab) {
        targetTab.style.display = 'block';
    } else {
        // Fallback: if target tab not found, show resources as default
        const fallbackTab = document.getElementById('materials-resources');
        if (fallbackTab) {
            fallbackTab.style.display = 'block';
        }
    }
    
    if (targetBtn) {
        targetBtn.style.borderBottom = '3px solid #0b6cf5';
    }
    
    // Load shared materials if switching to that tab
    if (subTab === 'shared-materials') {
        loadMaterials();
    }
}

function switchSettingsSubTab(subTab) {
    document.querySelectorAll('.settings-subtab').forEach(el => el.style.display = 'none');
    document.querySelectorAll('#settings-profile, #settings-security').forEach(el => {
        const btn = document.getElementById('set-' + (el.id === 'settings-profile' ? 'profile' : 'security') + '-btn');
        if (btn) btn.style.borderBottom = 'none';
    });
    
    const targetTab = document.getElementById('settings-' + subTab);
    const targetBtn = document.getElementById('set-' + subTab + '-btn');
    if (targetTab) targetTab.style.display = 'block';
    if (targetBtn) targetBtn.style.borderBottom = '3px solid #0b6cf5';
}

// Slot Requests Functions
async function loadSlotRequests() {
    const container = document.getElementById('slot-requests-container');
    if (!container) return;
    
    container.innerHTML = '<div style="text-align: center; padding: 40px;"><i class="fas fa-spinner fa-spin" style="font-size: 2rem; color: #0b6cf5;"></i><p style="margin-top: 15px; color: #666;">Loading slot requests...</p></div>';
    
    try {
        // Use relative path - works from root directory
        const apiPath = 'api/slot-requests.php?action=get-pending';
        const response = await fetch(apiPath);
        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }
        const data = await response.json();
        
        if (data.success && data.requests && data.requests.length > 0) {
            let html = '';
            data.requests.forEach(request => {
                // Parse date and time correctly
                const requestDateStr = request.requested_date;
                const requestTimeStr = request.requested_time;
                
                // Create date objects for proper formatting
                const requestDate = new Date(requestDateStr + 'T00:00:00');
                const requestDateTime = new Date(requestDateStr + 'T' + requestTimeStr);
                
                // Format times in 12-hour format
                const requestTime = requestDateTime.toLocaleTimeString('en-US', { hour: 'numeric', minute: '2-digit', hour12: true });
                
                // Calculate end time
                const durationMinutes = request.duration_minutes || 60;
                const endTimeObj = new Date(requestDateTime);
                endTimeObj.setMinutes(endTimeObj.getMinutes() + durationMinutes);
                const endTime = endTimeObj.toLocaleTimeString('en-US', { hour: 'numeric', minute: '2-digit', hour12: true });
                
                const requestDateFormatted = requestDate.toLocaleDateString('en-US', { weekday: 'long', month: 'long', day: 'numeric', year: 'numeric' });
                const requestTimeFormatted = new Date(request.requested_date + 'T' + request.requested_time).toLocaleTimeString('en-US', { hour: 'numeric', minute: '2-digit' });
                const endTimeFormatted = endTimeObj.toLocaleTimeString('en-US', { hour: 'numeric', minute: '2-digit' });
                
                html += `
                    <div class="card" style="margin-bottom: 25px; border-left: 5px solid #0b6cf5; box-shadow: 0 2px 8px rgba(0,0,0,0.08); transition: all 0.3s; position: relative; overflow: hidden;">
                        <div style="position: absolute; top: 0; right: 0; width: 100px; height: 100px; background: linear-gradient(135deg, rgba(11, 108, 245, 0.1) 0%, transparent 100%); border-radius: 0 0 0 100%; pointer-events: none;"></div>
                        <div style="display: flex; justify-content: space-between; align-items: flex-start; flex-wrap: wrap; gap: 20px; position: relative;">
                            <div style="flex: 1; min-width: 280px;">
                                <div style="display: flex; align-items: center; gap: 12px; margin-bottom: 20px;">
                                    <div style="width: 45px; height: 45px; border-radius: 10px; background: linear-gradient(135deg, #0b6cf5 0%, #004080 100%); display: flex; align-items: center; justify-content: center; color: white; font-size: 1.3rem;">
                                        <i class="fas fa-calendar-check"></i>
                                    </div>
                                    <h3 style="margin: 0; border: none; padding: 0; color: #004080; font-size: 1.3rem;">
                                        Time Slot Request
                                    </h3>
                                </div>
                                <div style="background: #f8f9fa; padding: 20px; border-radius: 10px; margin-bottom: 15px;">
                                    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px;">
                                        <div style="display: flex; align-items: flex-start; gap: 10px;">
                                            <i class="fas fa-user-shield" style="color: #0b6cf5; margin-top: 3px; font-size: 1.1rem;"></i>
                                            <div>
                                                <div style="font-size: 0.85rem; color: #666; margin-bottom: 3px;">Requested By</div>
                                                <div style="font-weight: 600; color: #333; font-size: 1rem;">${escapeHtml(request.admin_name)}</div>
                                            </div>
                                        </div>
                                        <div style="display: flex; align-items: flex-start; gap: 10px;">
                                            <i class="fas fa-calendar" style="color: #28a745; margin-top: 3px; font-size: 1.1rem;"></i>
                                            <div>
                                                <div style="font-size: 0.85rem; color: #666; margin-bottom: 3px;">Date</div>
                                                <div style="font-weight: 600; color: #333; font-size: 1rem;">${requestDateFormatted}</div>
                                            </div>
                                        </div>
                                        <div style="display: flex; align-items: flex-start; gap: 10px;">
                                            <i class="fas fa-clock" style="color: #ff9800; margin-top: 3px; font-size: 1.1rem;"></i>
                                            <div>
                                                <div style="font-size: 0.85rem; color: #666; margin-bottom: 3px;">Time</div>
                                                <div style="font-weight: 600; color: #333; font-size: 1rem;">${requestTime} - ${endTime}</div>
                                                <div style="font-size: 0.8rem; color: #999; margin-top: 2px;">${request.duration_minutes || 60} minutes</div>
                                            </div>
                                        </div>
                                    </div>
                                    ${request.message ? `
                                        <div style="margin-top: 15px; padding-top: 15px; border-top: 1px solid #dee2e6;">
                                            <div style="display: flex; align-items: flex-start; gap: 10px;">
                                                <i class="fas fa-comment" style="color: #9c27b0; margin-top: 3px;"></i>
                                                <div style="flex: 1;">
                                                    <div style="font-size: 0.85rem; color: #666; margin-bottom: 5px;">Admin Message</div>
                                                    <div style="color: #333; line-height: 1.5; background: white; padding: 12px; border-radius: 6px; border-left: 3px solid #9c27b0;">${escapeHtml(request.message)}</div>
                                                </div>
                                            </div>
                                        </div>
                                    ` : ''}
                                </div>
                                <div style="display: flex; align-items: center; gap: 8px; color: #666; font-size: 0.9rem;">
                                    <i class="fas fa-history"></i>
                                    <span>Requested ${new Date(request.created_at).toLocaleString('en-US', { month: 'short', day: 'numeric', year: 'numeric', hour: 'numeric', minute: '2-digit' })}</span>
                                </div>
                            </div>
                            <div style="display: flex; flex-direction: column; gap: 12px; min-width: 150px;">
                                <button onclick="acceptSlotRequest(${request.id})" class="btn-success" style="white-space: nowrap; padding: 14px 24px; font-size: 1rem; font-weight: 600; border-radius: 8px; box-shadow: 0 2px 6px rgba(40, 167, 69, 0.3); transition: all 0.2s; border: none; cursor: pointer;" onmouseover="this.style.transform='translateY(-2px)'; this.style.boxShadow='0 4px 12px rgba(40, 167, 69, 0.4)'" onmouseout="this.style.transform=''; this.style.boxShadow='0 2px 6px rgba(40, 167, 69, 0.3)'">
                                    <i class="fas fa-check"></i> Accept
                                </button>
                                <button onclick="rejectSlotRequest(${request.id})" class="btn-danger" style="white-space: nowrap; padding: 14px 24px; font-size: 1rem; font-weight: 600; border-radius: 8px; box-shadow: 0 2px 6px rgba(220, 53, 69, 0.3); transition: all 0.2s; border: none; cursor: pointer;" onmouseover="this.style.transform='translateY(-2px)'; this.style.boxShadow='0 4px 12px rgba(220, 53, 69, 0.4)'" onmouseout="this.style.transform=''; this.style.boxShadow='0 2px 6px rgba(220, 53, 69, 0.3)'">
                                    <i class="fas fa-times"></i> Reject
                                </button>
                            </div>
                        </div>
                    </div>
                `;
            });
            container.innerHTML = html;
        } else {
            container.innerHTML = `
                <div class="empty-state" style="padding: 60px 40px; text-align: center; background: linear-gradient(135deg, #f8f9fa 0%, #ffffff 100%); border-radius: 12px; border: 2px dashed #dee2e6;">
                    <div style="width: 80px; height: 80px; margin: 0 auto 25px; background: linear-gradient(135deg, #28a745 0%, #20c997 100%); border-radius: 50%; display: flex; align-items: center; justify-content: center; color: white; font-size: 2.5rem; box-shadow: 0 4px 15px rgba(40, 167, 69, 0.3);">
                        <i class="fas fa-calendar-check"></i>
                    </div>
                    <h3 style="color: #155724; margin-bottom: 10px; font-size: 1.5rem;">All Caught Up!</h3>
                    <p style="color: #666; font-size: 1.1rem; max-width: 500px; margin: 0 auto; line-height: 1.6;">You have no pending slot requests from administrators at this time. New requests will appear here when admins request time slots from you.</p>
                </div>
            `;
        }
    } catch (error) {
        console.error('Failed to load slot requests:', error);
        container.innerHTML = `
            <div class="alert alert-error">
                <i class="fas fa-exclamation-circle"></i> Failed to load slot requests. Please try again later.
            </div>
        `;
    }
}

async function acceptSlotRequest(requestId) {
    // Show confirmation dialog
    const confirmMessage = 'Accept this slot request? The time slot will be immediately added to your calendar and available for students to book.';
    let confirmed = false;
    
    if (typeof toast !== 'undefined' && toast.confirm) {
        confirmed = await toast.confirm(confirmMessage, 'Accept Slot Request');
    } else {
        confirmed = confirm(confirmMessage);
    }
    
    if (!confirmed) return;
    
    // Disable buttons during processing
    const acceptBtn = event?.target?.closest('.btn-success');
    const rejectBtn = event?.target?.closest('.card')?.querySelector('.btn-danger');
    if (acceptBtn) acceptBtn.disabled = true;
    if (rejectBtn) rejectBtn.disabled = true;
    
    try {
        // Use relative path - works from root directory
        const response = await fetch('api/slot-requests.php?action=accept', {
            method: 'POST',
            headers: { 
                'Content-Type': 'application/json',
                'Accept': 'application/json'
            },
            body: JSON.stringify({ request_id: requestId })
        });
        
        const data = await response.json();
        
        if (data.success) {
            if (typeof toast !== 'undefined' && toast.success) {
                toast.success('Slot request accepted! The time slot has been added to your calendar and is now available for booking.');
            } else {
                alert('✓ Slot request accepted! The time slot has been added to your calendar.');
            }
            // Reload requests
            loadSlotRequests();
            // Refresh calendar if on calendar page
            setTimeout(() => {
                if (window.location.pathname.includes('teacher-calendar-setup')) {
                    window.location.reload();
                } else if (window.location.pathname.includes('schedule')) {
                    window.location.reload();
                }
            }, 500);
        } else {
            if (typeof toast !== 'undefined' && toast.error) {
                toast.error(data.error || 'Failed to accept slot request');
            } else {
                alert('Error: ' + (data.error || 'Failed to accept slot request'));
            }
            if (acceptBtn) acceptBtn.disabled = false;
            if (rejectBtn) rejectBtn.disabled = false;
        }
    } catch (error) {
        console.error('Failed to accept slot request:', error);
        if (typeof toast !== 'undefined' && toast.error) {
            toast.error('An error occurred. Please try again.');
        } else {
            alert('An error occurred. Please try again.');
        }
        if (acceptBtn) acceptBtn.disabled = false;
        if (rejectBtn) rejectBtn.disabled = false;
    }
}

async function rejectSlotRequest(requestId) {
    // Show modal for rejection reason instead of prompt
    const modal = document.createElement('div');
    modal.className = 'action-selection-modal-overlay';
    modal.innerHTML = `
        <div class="action-selection-modal" style="max-width: 500px;">
            <div class="modal-header">
                <h3>Reject Slot Request</h3>
                <button class="modal-close-btn" onclick="this.closest('.action-selection-modal-overlay').remove()">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div style="padding: 20px 0;">
                <p style="color: #666; margin-bottom: 15px;">Are you sure you want to reject this slot request? You can optionally provide a reason.</p>
                <div class="form-group">
                    <label>Reason (optional):</label>
                    <textarea id="rejectReason" rows="4" class="form-control" placeholder="e.g., Time conflict, Unavailable, etc."></textarea>
                </div>
            </div>
            <div class="modal-actions">
                <button class="btn-danger" id="confirmReject">
                    <i class="fas fa-times"></i> Reject Request
                </button>
                <button class="btn-secondary" onclick="this.closest('.action-selection-modal-overlay').remove()">Cancel</button>
            </div>
        </div>
    `;
    
    document.body.appendChild(modal);
    
    modal.querySelector('#confirmReject').addEventListener('click', async () => {
        const reason = modal.querySelector('#rejectReason').value.trim();
        modal.remove();
        
        // Disable buttons during processing
        const rejectBtn = event?.target?.closest('.btn-danger');
        const acceptBtn = event?.target?.closest('.card')?.querySelector('.btn-success');
        if (rejectBtn) rejectBtn.disabled = true;
        if (acceptBtn) acceptBtn.disabled = true;
        
        try {
            // Use relative path - works from root directory
            const response = await fetch('api/slot-requests.php?action=reject', {
                method: 'POST',
                headers: { 
                    'Content-Type': 'application/json',
                    'Accept': 'application/json'
                },
                body: JSON.stringify({ 
                    request_id: requestId,
                    reason: reason || null
                })
            });
            
            const data = await response.json();
            
            if (data.success) {
                if (typeof toast !== 'undefined' && toast.success) {
                    toast.success('Slot request rejected.');
                } else {
                    alert('Slot request rejected.');
                }
                // Reload requests
                loadSlotRequests();
            } else {
                if (typeof toast !== 'undefined' && toast.error) {
                    toast.error(data.error || 'Failed to reject slot request');
                } else {
                    alert('Error: ' + (data.error || 'Failed to reject slot request'));
                }
                if (rejectBtn) rejectBtn.disabled = false;
                if (acceptBtn) acceptBtn.disabled = false;
            }
        } catch (error) {
            console.error('Failed to reject slot request:', error);
            if (typeof toast !== 'undefined' && toast.error) {
                toast.error('An error occurred. Please try again.');
            } else {
                alert('An error occurred. Please try again.');
            }
            if (rejectBtn) rejectBtn.disabled = false;
            if (acceptBtn) acceptBtn.disabled = false;
        }
    });
    
    // Close on overlay click
    modal.addEventListener('click', (e) => {
        if (e.target === modal) {
            modal.remove();
        }
    });
    
    // Focus on textarea
    setTimeout(() => {
        const textarea = document.getElementById('rejectReason');
        if (textarea) textarea.focus();
    }, 100);
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
                headers: {
                    'Accept': 'application/json'
                },
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
        
        if (id === 'slot-requests') {
            loadSlotRequests();
        }
        
        // Handle sub-tab navigation
        if (id === 'performance') {
            const hashParts = window.location.hash.split('-');
            if (hashParts.length > 1 && (hashParts[1] === 'earnings' || hashParts[1] === 'reviews')) {
                switchPerformanceSubTab(hashParts[1]);
            } else {
                switchPerformanceSubTab('earnings'); // Default to earnings
            }
        }
        if (id === 'materials') {
            const hashParts = window.location.hash.split('-');
            if (hashParts.length > 1 && (hashParts[1] === 'resources' || hashParts[1] === 'shared')) {
                switchMaterialsSubTab(hashParts[1] === 'shared' ? 'shared-materials' : 'resources');
            } else {
                // Always ensure default subtab is shown, even if clicking Materials again
                switchMaterialsSubTab('resources'); // Default to resources
            }
        }
        if (id === 'settings') {
            const hashParts = window.location.hash.split('-');
            if (hashParts.length > 1 && (hashParts[1] === 'profile' || hashParts[1] === 'security')) {
                switchSettingsSubTab(hashParts[1]);
            } else {
                switchSettingsSubTab('profile'); // Default to profile
            }
        }
    };
    
    // Load materials if already on shared-materials tab
    const hash = window.location.hash.substring(1);
    if (hash === 'shared-materials') {
        loadMaterials();
    }
    
    // Load slot requests if already on slot-requests tab
    if (hash === 'slot-requests') {
        loadSlotRequests();
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
    const fileInfo = document.getElementById('material-file-info');
    const dropzone = document.getElementById('material-dropzone');
    
    if (!input || !input.files || input.files.length === 0) {
        if (fileInfo) fileInfo.textContent = '';
        if (dropzone) {
            dropzone.style.borderColor = 'var(--primary-light)';
            dropzone.style.background = '#ffffff';
        }
        return;
    }
    
    const file = input.files[0];
    
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
        if (dropzone) {
            dropzone.style.borderColor = 'var(--primary-light)';
            dropzone.style.background = '#ffffff';
        }
        return;
    }
    
    const fileSize = (file.size / (1024 * 1024)).toFixed(2); // Size in MB
    
    if (fileInfo) {
        fileInfo.innerHTML = `
            <i class="fas fa-check-circle" style="color: var(--success); margin-right: 8px;"></i>
            <strong>${escapeHtml(file.name)}</strong> (${fileSize} MB)
        `;
    }
    
    if (dropzone) {
        dropzone.style.borderColor = '#28a745';
        dropzone.style.background = '#f0fff4';
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

