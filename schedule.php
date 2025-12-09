<?php
// Start output buffering to prevent "headers already sent" errors
ob_start();

// Load environment configuration first to check APP_DEBUG
if (!defined('DB_HOST')) {
    require_once __DIR__ . '/env.php';
}

// Enable error reporting based on APP_DEBUG setting
if (defined('APP_DEBUG') && APP_DEBUG === true) {
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
    ini_set('display_startup_errors', 1);
} else {
    // In production, log errors but don't display them
    error_reporting(E_ALL);
    ini_set('display_errors', 0);
    ini_set('log_errors', 1);
}

// Start session before any output
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/google-calendar-config.php';
require_once __DIR__ . '/app/Views/components/dashboard-functions.php';
require_once __DIR__ . '/app/Services/TimezoneService.php';
require_once __DIR__ . '/app/Services/CalendarService.php';

// Ensure getAssetPath function is available
if (!function_exists('getAssetPath')) {
    function getAssetPath($asset) {
        $asset = ltrim($asset, '/');
        if (strpos($asset, 'assets/') === 0) {
            $assetPath = $asset;
        } else {
            $assetPath = 'assets/' . $asset;
        }
        return '/' . $assetPath;
    }
}

// If not logged in, redirect to login
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

require_once __DIR__ . '/app/Models/TeacherAssignment.php';

$api = new GoogleCalendarAPI($conn);
$tzService = new TimezoneService($conn);
$calendarService = new CalendarService($conn);
$assignmentModel = new TeacherAssignment($conn);

// Get user ID from session (needed early for timezone and other operations)
$user_id = $_SESSION['user_id'];

$selected_teacher = null;
$teacher_data = null;
$availability_slots = [];
$current_lessons = [];
$student_upcoming_lessons = [];
$user_role = $_SESSION['user_role'] ?? 'student';

// Get user's timezone
$user_timezone = $tzService->getUserTimezone($user_id);

// For students, get their assigned teacher
if ($user_role === 'student' || $user_role === 'new_student') {
    // Get assigned teacher from user record or assignment table
    $stmt = $conn->prepare("SELECT assigned_teacher_id FROM users WHERE id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();
    $stmt->close();
    
    $selected_teacher = $user['assigned_teacher_id'] ?? null;
    
    // If no assigned teacher, try to get from assignment table
    if (!$selected_teacher) {
        $assignment = $assignmentModel->getStudentTeacher($user_id);
        if ($assignment) {
            $selected_teacher = $assignment['teacher_id'];
            $teacher_data = [
                'id' => $assignment['teacher_id'],
                'name' => $assignment['teacher_name'],
                'email' => $assignment['teacher_email'],
                'profile_pic' => $assignment['teacher_pic'] ?? getAssetPath('images/placeholder-teacher.svg'),
                'bio' => $assignment['teacher_bio'] ?? ''
            ];
        }
    } else {
        // Fetch teacher details
        $stmt = $conn->prepare("SELECT id, name, email, profile_pic, bio FROM users WHERE id = ?");
        $stmt->bind_param("i", $selected_teacher);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result->num_rows > 0) {
            $teacher_data = $result->fetch_assoc();
        }
        $stmt->close();
    }
    
    // If still no teacher assigned, redirect to track selection
    if (!$selected_teacher) {
        header("Location: index.php");
        exit();
    }
    
    // Fetch teacher's availability slots (only available slots - students should only see available times)
    $availability_slots = $api->getTeacherAvailability($selected_teacher, null, null);
    
    // Fetch student's upcoming lessons
    $stmt = $conn->prepare("
        SELECT l.*, u.name as teacher_name, u.profile_pic as teacher_pic
        FROM lessons l
        JOIN users u ON l.teacher_id = u.id
        WHERE l.student_id = ? AND l.lesson_date >= CURDATE() AND l.status = 'scheduled'
        ORDER BY l.lesson_date ASC, l.start_time ASC
        LIMIT 10
    ");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $student_upcoming_lessons[] = $row;
    }
    $stmt->close();
} elseif ($user_role === 'teacher') {
    // Teachers view their own schedule
    $selected_teacher = $user_id;
    $stmt = $conn->prepare("SELECT id, name, email, profile_pic, bio FROM users WHERE id = ?");
    $stmt->bind_param("i", $selected_teacher);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows > 0) {
        $teacher_data = $result->fetch_assoc();
    }
    $stmt->close();
    
    $current_lessons = $api->getTeacherLessons($selected_teacher);
}

// Handle lesson booking (AJAX request)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'book_lesson') {
    header('Content-Type: application/json');
    
    // Only allow students (who have purchased) to book lessons
    if ($_SESSION['user_role'] !== 'student') {
        http_response_code(403);
        echo json_encode(['error' => 'Please purchase a lesson plan first to book lessons. Visit the payment page to get started.']);
        exit();
    }
    
    if (!$selected_teacher) {
        http_response_code(400);
        echo json_encode(['error' => 'No teacher assigned. Please contact support.']);
        exit();
    }

    $lesson_date = $_POST['lesson_date'] ?? null;
    $start_time = $_POST['start_time'] ?? null;
    $end_time = $_POST['end_time'] ?? null;

    if (!$lesson_date || !$start_time || !$end_time) {
        http_response_code(400);
        echo json_encode(['error' => 'Missing lesson details']);
        exit();
    }

    // Use the API to book lesson
    $json_input = json_encode([
        'teacher_id' => $selected_teacher,
        'lesson_date' => $lesson_date,
        'start_time' => $start_time,
        'end_time' => $end_time
    ]);

    // Create temporary file with JSON
    $temp_file = fopen('php://memory', 'r+');
    fwrite($temp_file, $json_input);
    rewind($temp_file);

    // Manually call the booking logic here instead
    $teacher_id = $selected_teacher;
    $student_id = $_SESSION['user_id'];

    // Validate
    if (strtotime($lesson_date . ' ' . $start_time) <= time()) {
        http_response_code(400);
        echo json_encode(['error' => 'Cannot book past dates']);
        exit();
    }

    // Check booking notice requirement
    $lessonDateTime = $lesson_date . ' ' . $start_time . ':00';
    $noticeCheck = $calendarService->validateBookingNotice($teacher_id, $lessonDateTime);
    if (!$noticeCheck['valid']) {
        http_response_code(400);
        echo json_encode(['error' => $noticeCheck['reason']]);
        exit();
    }

    // Check time-off conflicts
    if ($calendarService->checkTimeOffConflicts($teacher_id, $lesson_date, $lesson_date)) {
        http_response_code(409);
        echo json_encode(['error' => 'Teacher is on time-off during this period']);
        exit();
    }

    $availability_check = $api->isSlotAvailable($teacher_id, $lesson_date, $start_time, $end_time);
    if (!$availability_check['available']) {
        http_response_code(409);
        echo json_encode(['error' => $availability_check['reason']]);
        exit();
    }

    // Get teacher info
    $stmt = $conn->prepare("SELECT * FROM users WHERE id = ? AND role = 'teacher'");
    $stmt->bind_param("i", $teacher_id);
    $stmt->execute();
    $teacher = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    // Get student info
    $stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->bind_param("i", $student_id);
    $stmt->execute();
    $student = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    // Check if this is a test class (student@statenacademy.com)
    $isTestClass = (strtolower($student['email'] ?? '') === 'student@statenacademy.com');
    $studentDisplayName = $isTestClass ? 'Test Class' : ($student['name'] ?? 'Student');
    
    // Verify unlimited classes for test student (student@statenacademy.com should have has_unlimited_classes = TRUE)
    // Note: The booking system doesn't enforce class limits, so unlimited classes are automatically available
    // This is verified in the database setup scripts (setup-test-account.sql)

    // Create lesson record
    $google_event_id = null;
    $stmt = $conn->prepare("
        INSERT INTO lessons (teacher_id, student_id, lesson_date, start_time, end_time, status, lesson_type, color_code)
        VALUES (?, ?, ?, ?, ?, 'scheduled', 'single', '#0b6cf5')
    ");
    $stmt->bind_param("iisss", $teacher_id, $student_id, $lesson_date, $start_time, $end_time);

    if (!$stmt->execute()) {
        http_response_code(500);
        echo json_encode(['error' => 'Failed to create lesson']);
        $stmt->close();
        exit();
    }

    $lesson_id = $stmt->insert_id;
    $stmt->close();

    // Also create a booking record for compatibility
    $booking_stmt = $conn->prepare("INSERT IGNORE INTO bookings (student_id, teacher_id, booking_date) VALUES (?, ?, ?)");
    $booking_date = date('Y-m-d H:i:s');
    $booking_stmt->bind_param("iis", $student_id, $teacher_id, $booking_date);
    $booking_stmt->execute();
    $booking_stmt->close();

    // Create Google Calendar event if connected
    if (!empty($teacher['google_calendar_token'])) {
        if (!empty($teacher['google_calendar_token_expiry']) && strtotime($teacher['google_calendar_token_expiry']) <= time()) {
            if (!empty($teacher['google_calendar_refresh_token'])) {
                $token_response = $api->refreshAccessToken($teacher['google_calendar_refresh_token']);
                if (!isset($token_response['error'])) {
                    $teacher['google_calendar_token'] = $token_response['access_token'];
                    $new_expiry = date('Y-m-d H:i:s', time() + ($token_response['expires_in'] ?? 3600));
                    $stmt = $conn->prepare("UPDATE users SET google_calendar_token = ?, google_calendar_token_expiry = ? WHERE id = ?");
                    $stmt->bind_param("ssi", $teacher['google_calendar_token'], $new_expiry, $teacher_id);
                    $stmt->execute();
                    $stmt->close();
                }
            }
        }

        $start_datetime = $lesson_date . 'T' . $start_time . ':00';
        $end_datetime = $lesson_date . 'T' . $end_time . ':00';
        
        // Create classroom join URL
        $classroom_url = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http') . 
                        '://' . $_SERVER['HTTP_HOST'] . 
                        dirname($_SERVER['PHP_SELF']) . 
                        '/classroom.php?lessonId=' . $lesson_id;
        
        $event_data = [
            'title' => 'Lesson: ' . htmlspecialchars($studentDisplayName),
            'description' => 'Student: ' . htmlspecialchars($studentDisplayName) . ($isTestClass ? ' (Test Class)' : ' (' . htmlspecialchars($student['email']) . ')') . "\n\n" .
                           'Join Classroom: ' . $classroom_url,
            'start_datetime' => $start_datetime,
            'end_datetime' => $end_datetime,
            'location' => $classroom_url
        ];

        $calendar_result = $api->createEvent($teacher['google_calendar_token'], $event_data);
        
        if (isset($calendar_result['id'])) {
            $google_event_id = $calendar_result['id'];
            $stmt = $conn->prepare("UPDATE lessons SET google_calendar_event_id = ? WHERE id = ?");
            $stmt->bind_param("si", $google_event_id, $lesson_id);
            $stmt->execute();
            $stmt->close();
        }
    }

    // Create Google Calendar event in student's calendar if connected
    if (!empty($student['google_calendar_token'])) {
        // Refresh token if expired
        if (!empty($student['google_calendar_token_expiry']) && strtotime($student['google_calendar_token_expiry']) <= time()) {
            if (!empty($student['google_calendar_refresh_token'])) {
                $token_response = $api->refreshAccessToken($student['google_calendar_refresh_token']);
                if (!isset($token_response['error'])) {
                    $student['google_calendar_token'] = $token_response['access_token'];
                    $new_expiry = date('Y-m-d H:i:s', time() + ($token_response['expires_in'] ?? 3600));
                    $stmt = $conn->prepare("UPDATE users SET google_calendar_token = ?, google_calendar_token_expiry = ? WHERE id = ?");
                    $stmt->bind_param("ssi", $student['google_calendar_token'], $new_expiry, $student_id);
                    $stmt->execute();
                    $stmt->close();
                }
            }
        }

        $start_datetime = $lesson_date . 'T' . $start_time . ':00';
        $end_datetime = $lesson_date . 'T' . $end_time . ':00';
        
        // Create classroom join URL
        $classroom_url = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http') . 
                        '://' . $_SERVER['HTTP_HOST'] . 
                        dirname($_SERVER['PHP_SELF']) . 
                        '/classroom.php?lessonId=' . $lesson_id;
        
        $student_event_data = [
            'title' => 'Lesson with ' . htmlspecialchars($teacher['name']),
            'description' => 'Teacher: ' . htmlspecialchars($teacher['name']) . ' (' . htmlspecialchars($teacher['email']) . ')' . 
                           ($isTestClass ? "\n\nNote: This is a test class." : '') . "\n\n" .
                           'Join Classroom: ' . $classroom_url,
            'start_datetime' => $start_datetime,
            'end_datetime' => $end_datetime,
            'attendees' => [['email' => $teacher['email']]],
            'location' => $classroom_url
        ];

        $student_calendar_result = $api->createEvent($student['google_calendar_token'], $student_event_data);
        
        // Note: We don't store student's event ID separately, but the event is created in their calendar
    }

    http_response_code(201);
    echo json_encode([
        'success' => true,
        'lesson_id' => $lesson_id,
        'message' => 'Lesson booked successfully'
    ]);
    exit();
}

// Fetch user data for header
$stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
$stmt->close();

// Set page title for header
$page_title = 'Schedule & Book Lessons';
$_SESSION['profile_pic'] = $user['profile_pic'] ?? getAssetPath('images/placeholder-teacher.svg');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=5.0, user-scalable=yes">
    <meta name="theme-color" content="#004080">
    <meta name="mobile-web-app-capable" content="yes">
    <title>Schedule - Staten Academy</title>
    <link rel="stylesheet" href="<?php echo getAssetPath('styles.css'); ?>">
    <link rel="stylesheet" href="<?php echo getAssetPath('css/dashboard.css'); ?>">
    <link rel="stylesheet" href="<?php echo getAssetPath('css/mobile.css'); ?>">
    <!-- MODERN SHADOWS - To disable, comment out the line below -->
    <link rel="stylesheet" href="<?php echo getAssetPath('css/modern-shadows.css'); ?>">
    <link rel="stylesheet" href="<?php echo getAssetPath('css/calendar.css'); ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        /* Schedule page specific styles */
        .schedule-container { max-width: 1200px; margin: 0 auto; }

        .card { background: white; padding: 25px; border-radius: 8px; box-shadow: 0 2px 8px rgba(0,0,0,0.1); margin-bottom: 20px; }
        .card h3 { margin-top: 0; color: #004080; border-bottom: 2px solid #0b6cf5; padding-bottom: 10px; }

        .teachers-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(250px, 1fr)); gap: 15px; margin-top: 15px; }
        .teacher-card { 
            background: white; 
            padding: 20px; 
            border-radius: 8px; 
            border: 2px solid #ddd; 
            cursor: pointer;
            transition: all 0.2s;
            text-align: center;
        }
        .teacher-card:hover { border-color: #0b6cf5; box-shadow: 0 4px 12px rgba(11, 108, 245, 0.2); }
        .teacher-card h4 { margin: 0; color: #004080; }
        .teacher-card p { color: #666; font-size: 14px; margin: 5px 0; }
        .teacher-card a { color: #0b6cf5; text-decoration: none; font-weight: bold; }
        .teacher-card a:hover { text-decoration: underline; }

        .availability-section { margin-top: 30px; }
        .time-slots { display: grid; grid-template-columns: repeat(auto-fill, minmax(200px, 1fr)); gap: 10px; margin-top: 15px; }
        .time-slot { 
            padding: 15px; 
            border: 1px solid #ddd; 
            border-radius: 5px;
            cursor: pointer;
            transition: all 0.2s;
            text-align: center;
        }
        .time-slot:hover { background: #f0f7ff; border-color: #0b6cf5; }
        .time-slot.booked { background: #f8d7da; cursor: not-allowed; color: #721c24; border-color: #f5c6cb; }
        .time-slot.selected { background: #d4edda; border-color: #28a745; }

        .date-picker { margin: 15px 0; }
        input[type="date"] { padding: 10px; border: 1px solid #ddd; border-radius: 4px; font-size: 14px; }

        .booking-form { background: #f0f7ff; padding: 20px; border-radius: 8px; margin-top: 20px; border: 2px solid #0b6cf5; }
        .form-group { margin-bottom: 15px; }
        label { display: block; font-weight: bold; margin-bottom: 5px; }
        select, input { width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 4px; }

        .btn { display: inline-block; padding: 12px 24px; background: #0b6cf5; color: white; border: none; border-radius: 4px; cursor: pointer; font-weight: bold; transition: 0.2s; text-decoration: none; }
        .btn:hover { background: #004080; }
        .btn-success { background: #28a745; }
        .btn-success:hover { background: #218838; }

        .alert { padding: 15px; border-radius: 5px; margin-bottom: 20px; }
        .alert-success { background: #d4edda; color: #155724; border-left: 4px solid #28a745; }
        .alert-error { background: #f8d7da; color: #721c24; border-left: 4px solid #dc3545; }

        .lessons-list { margin-top: 15px; }
        .lesson-item { padding: 15px; background: #f8f9fa; border-left: 4px solid #0b6cf5; border-radius: 4px; margin-bottom: 10px; }
        .lesson-item strong { display: block; margin-bottom: 5px; }

        @media (max-width: 768px) {
            .teachers-grid { grid-template-columns: 1fr; }
            .time-slots { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body class="dashboard-layout">
<?php include __DIR__ . '/app/Views/components/dashboard-header.php'; ?>

<div class="content-wrapper">
    <?php 
    // Set active tab for sidebar based on current page
    $active_tab = 'schedule';
    include __DIR__ . '/app/Views/components/dashboard-sidebar.php'; 
    ?>

    <div class="main">
            <div class="schedule-container">
                <?php if (!isset($_GET['teacher']) && ($_SESSION['user_role'] === 'student' || $_SESSION['user_role'] === 'new_student')): ?>
                    <!-- Teacher Selection -->
                    <div class="card">
                        <h3><i class="fas fa-users"></i> Select a Teacher</h3>
                        <p>Choose a teacher to view their availability<?php echo $_SESSION['user_role'] === 'student' ? ' and book a lesson' : ''; ?>.</p>
                        <?php if ($_SESSION['user_role'] === 'new_student'): ?>
                            <div style="background: #fff3cd; border-left: 4px solid #ffc107; padding: 15px; margin-bottom: 20px; border-radius: 4px;">
                                <strong><i class="fas fa-info-circle"></i> Purchase Required:</strong> Please <a href="payment.php" style="color: #004080; font-weight: bold;">purchase a lesson plan</a> to book lessons with teachers.
                            </div>
                        <?php endif; ?>
                        <div class="teachers-grid">
                            <?php
                            $teachers_result = $conn->query("SELECT id, name, profile_pic, email FROM users WHERE role='teacher' ORDER BY name");
                            while ($teacher = $teachers_result->fetch_assoc()):
                            ?>
                                <div class="teacher-card">
                                    <img src="<?php echo htmlspecialchars($teacher['profile_pic'] ?? getAssetPath('images/placeholder-teacher.svg')); ?>" 
                                         style="width: 80px; height: 80px; border-radius: 50%; object-fit: cover; margin-bottom: 10px;" onerror="this.src='<?php echo getAssetPath('images/placeholder-teacher.svg'); ?>'">
                                    <h4><?php echo htmlspecialchars($teacher['name']); ?></h4>
                                    <p><?php echo htmlspecialchars($teacher['email']); ?></p>
                                    <a href="schedule.php?teacher=<?php echo urlencode($teacher['name']); ?>">View Availability</a>
                                </div>
                            <?php endwhile; ?>
                        </div>
                    </div>
                <?php elseif ($teacher_data): ?>
                    <!-- Teacher Details and Booking -->
                    <div class="card">
                        <h3><i class="fas fa-book"></i> Book Lesson with <?php echo htmlspecialchars($teacher_data['name']); ?></h3>
                        <div style="display: grid; grid-template-columns: 1fr 2fr; gap: 20px; align-items: start; margin-top: 20px;">
                            <div style="text-align: center;">
                                <img src="<?php echo htmlspecialchars($teacher_data['profile_pic'] ?? getAssetPath('images/placeholder-teacher.svg')); ?>" 
                                     style="width: 150px; height: 150px; border-radius: 50%; object-fit: cover; border: 3px solid #0b6cf5;" onerror="this.src='<?php echo getAssetPath('images/placeholder-teacher.svg'); ?>'">
                                <h4 style="margin: 15px 0 5px 0;"><?php echo htmlspecialchars($teacher_data['name']); ?></h4>
                                <p style="color: #666; margin: 0;"><?php echo htmlspecialchars($teacher_data['email']); ?></p>
                            </div>

                            <div>
                                <?php if ($_SESSION['user_role'] === 'new_student'): ?>
                                    <div style="background: #fff3cd; border-left: 4px solid #ffc107; padding: 15px; margin-bottom: 20px; border-radius: 4px;">
                                        <strong><i class="fas fa-lock"></i> Purchase Required:</strong> You need to <a href="payment.php" style="color: #004080; font-weight: bold;">purchase a lesson plan</a> before you can book lessons. View our plans <a href="payment.php" style="color: #004080; font-weight: bold;">here</a>.
                                    </div>
                                <?php endif; ?>
                                
                                <!-- Calendar View -->
                                <div style="background: #e7f3ff; padding: 15px; border-radius: 5px; margin: 20px 0; border-left: 4px solid #0b6cf5;">
                                    <p style="margin: 0; color: #004080;"><i class="fas fa-info-circle"></i> <strong>Tip:</strong> Times shown are in your timezone (<?php echo htmlspecialchars($user_timezone); ?>). The calendar below shows your upcoming lessons.</p>
                                </div>
                                <div id="calendar-container" class="calendar-container" style="margin-top: 20px; margin-bottom: 30px;"></div>
                                
                                <h4>Available Time Slots</h4>
                                <?php if (count($availability_slots) > 0): ?>
                                    <div class="availability-section">
                                        <div class="date-picker">
                                            <label for="lesson-date">Select Date:</label>
                                            <input type="date" id="lesson-date" name="lesson_date" min="<?php echo date('Y-m-d', strtotime('+1 day')); ?>">
                                        </div>

                                        <div id="time-slots-container">
                                            <p style="color: #999;">Select a date to view available times</p>
                                        </div>

                                        <?php if ($_SESSION['user_role'] === 'student'): ?>
                                        <form id="booking-form" class="booking-form" style="display: none;">
                                            <div class="form-group">
                                                <label><strong>Selected Time:</strong> <span id="selected-time-display"></span></label>
                                                <span class="timezone-indicator" id="timezone-display"><?php echo htmlspecialchars($user_timezone); ?></span>
                                            </div>
                                            <div id="booking-notice-warning" class="booking-notice-warning" style="display: none;">
                                                <i class="fas fa-exclamation-triangle"></i>
                                                <span id="booking-notice-message"></span>
                                            </div>
                                            <div class="form-group">
                                                <label>
                                                    <input type="checkbox" id="recurring-booking" name="recurring_booking">
                                                    Book as recurring weekly lesson
                                                </label>
                                            </div>
                                            <div id="recurring-options" style="display: none; margin-top: 15px;">
                                                <div class="form-group">
                                                    <label for="number-of-weeks">Number of weeks:</label>
                                                    <input type="number" id="number-of-weeks" name="number_of_weeks" min="2" max="52" value="12" style="width: 100px;">
                                                </div>
                                                <div class="form-group">
                                                    <label for="end-date">End date (optional):</label>
                                                    <input type="date" id="end-date" name="end_date" style="width: 200px;">
                                                </div>
                                            </div>
                                            <button type="submit" class="btn btn-success"><i class="fas fa-check"></i> Confirm Booking</button>
                                            <button type="button" class="btn" onclick="cancelBooking()" style="background: #6c757d; margin-left: 10px;">Cancel</button>
                                        </form>
                                        <?php else: ?>
                                        <div style="background: #f8f9fa; padding: 15px; border-radius: 4px; margin-top: 20px; text-align: center;">
                                            <p style="margin: 0; color: #6c757d;"><i class="fas fa-lock"></i> Booking is only available after purchasing a lesson plan.</p>
                                            <a href="payment.php" class="btn btn-primary" style="margin-top: 10px; display: inline-block;"><i class="fas fa-shopping-cart"></i> View Plans</a>
                                        </div>
                                        <?php endif; ?>
                                    </div>

                                    <script src="<?php echo getAssetPath('js/timezone.js'); ?>"></script>
                                    <script>
                                    const teacherId = <?php echo $selected_teacher; ?>;
                                    const availabilitySlots = <?php echo json_encode($availability_slots); ?>;
                                    const userTimezone = '<?php echo htmlspecialchars($user_timezone); ?>';
                                    let selectedSlot = null;
                                    let calendar = null;
                                    
                                    // Initialize calendar when DOM is ready
                                    document.addEventListener('DOMContentLoaded', function() {
                                        // Initialize teacher calendar (for booking)
                                        if (typeof Calendar !== 'undefined' && document.getElementById('calendar-container')) {
                                            calendar = new Calendar('calendar-container', {
                                                view: 'month',
                                                timezone: userTimezone || window.userTimezone || 'UTC',
                                                teacherId: teacherId,
                                                onSlotSelect: function(slot) {
                                                    // Handle slot selection from calendar
                                                    const dateInput = document.getElementById('lesson-date');
                                                    if (dateInput) {
                                                        dateInput.value = slot.date;
                                                        dateInput.dispatchEvent(new Event('change'));
                                                    }
                                                },
                                                onLessonClick: function(lesson) {
                                                    // Handle lesson click - could show lesson details
                                                    console.log('Lesson clicked:', lesson);
                                                }
                                            });
                                        }
                                    });
                                    
                                    // Show timezone indicator
                                    if (window.userTimezone) {
                                        const tzDisplay = document.getElementById('timezone-display');
                                        if (tzDisplay) {
                                            tzDisplay.textContent = window.userTimezone;
                                        }
                                    }
                                    
                                    // Handle recurring booking checkbox
                                    document.getElementById('recurring-booking').addEventListener('change', function() {
                                        document.getElementById('recurring-options').style.display = this.checked ? 'block' : 'none';
                                    });

                                    document.getElementById('lesson-date').addEventListener('change', function() {
                                        const selectedDate = this.value;
                                        const dayOfWeek = new Date(selectedDate + 'T00:00:00').toLocaleDateString('en-US', { weekday: 'long' });
                                        
                                        // Filter to only show available slots for this day
                                        // Note: availabilitySlots from getTeacherAvailability already filters by is_available = 1
                                        // This additional filter ensures we only show slots for the selected day (weekly or one-time)
                                        const slotsForDay = availabilitySlots.filter(slot => {
                                            // Check if it's a weekly slot for this day OR a one-time slot for this specific date
                                            const isWeeklySlot = slot.day_of_week === dayOfWeek && !slot.specific_date;
                                            const isOneTimeSlot = slot.specific_date === selectedDate;
                                            return (isWeeklySlot || isOneTimeSlot) && slot.is_available;
                                        });
                                        
                                        let html = '';
                                        if (slotsForDay.length > 0) {
                                            html = '<label>Available Times:</label><div class="time-slots">';
                                            slotsForDay.forEach(slot => {
                                                html += `<div class="time-slot" onclick="selectSlot('${selectedDate}', '${slot.start_time.substr(0,5)}', '${slot.end_time.substr(0,5)}')">
                                                    ${slot.start_time.substr(0,5)} - ${slot.end_time.substr(0,5)}
                                                </div>`;
                                            });
                                            html += '</div>';
                                        } else {
                                            html = '<p style="color: #dc3545;"><i class="fas fa-info-circle"></i> No available time slots for this date</p>';
                                        }
                                        
                                        document.getElementById('time-slots-container').innerHTML = html;
                                    });

                                    async function selectSlot(date, startTime, endTime) {
                                        selectedSlot = { date, startTime, endTime };
                                        document.getElementById('selected-time-display').textContent = `${date} from ${startTime} to ${endTime}`;
                                        document.getElementById('booking-form').style.display = 'block';
                                        
                                        // Check booking notice requirement
                                        const lessonDateTime = date + ' ' + startTime + ':00';
                                        try {
                                            // Get base path for API calls (works with subdirectories on cPanel)
                                            const basePath = window.location.pathname.substring(0, window.location.pathname.lastIndexOf('/'));
                                            const apiPath = basePath + '/api/calendar.php';
                                            const response = await fetch(apiPath + '?action=validate-notice', {
                                                method: 'POST',
                                                headers: { 'Content-Type': 'application/json' },
                                                body: JSON.stringify({
                                                    teacher_id: teacherId,
                                                    lesson_datetime: lessonDateTime
                                                })
                                            });
                                            const data = await response.json();
                                            if (!data.valid) {
                                                document.getElementById('booking-notice-warning').style.display = 'flex';
                                                document.getElementById('booking-notice-message').textContent = data.reason;
                                            } else {
                                                document.getElementById('booking-notice-warning').style.display = 'none';
                                            }
                                        } catch (error) {
                                            console.error('Failed to check booking notice:', error);
                                        }
                                        
                                        // Update time slot selection styling
                                        document.querySelectorAll('.time-slot').forEach(el => el.classList.remove('selected'));
                                        event.target.classList.add('selected');
                                    }

                                    function cancelBooking() {
                                        selectedSlot = null;
                                        document.getElementById('booking-form').style.display = 'none';
                                        document.querySelectorAll('.time-slot').forEach(el => el.classList.remove('selected'));
                                    }

                                    document.getElementById('booking-form').addEventListener('submit', async function(e) {
                                        e.preventDefault();
                                        
                                        if (!selectedSlot) {
                                            if (typeof toast !== 'undefined') {
                                                toast.error('Please select a time slot');
                                            } else {
                                                alert('Please select a time slot');
                                            }
                                            return;
                                        }

                                        const isRecurring = document.getElementById('recurring-booking').checked;
                                        
                                        if (isRecurring) {
                                            // Book recurring lesson
                                            const numberOfWeeks = parseInt(document.getElementById('number-of-weeks').value) || 12;
                                            const endDate = document.getElementById('end-date').value || null;
                                            const dayOfWeek = new Date(selectedSlot.date + 'T00:00:00').toLocaleDateString('en-US', { weekday: 'long' });
                                            
                                            try {
                                                // Get base path for API calls (works with subdirectories on cPanel)
                                                const basePath = window.location.pathname.substring(0, window.location.pathname.lastIndexOf('/'));
                                                const apiPath = basePath + '/api/calendar.php';
                                                const response = await fetch(apiPath + '?action=book-recurring', {
                                                    method: 'POST',
                                                    headers: { 'Content-Type': 'application/json' },
                                                    body: JSON.stringify({
                                                        teacher_id: teacherId,
                                                        day_of_week: dayOfWeek,
                                                        start_time: selectedSlot.startTime + ':00',
                                                        end_time: selectedSlot.endTime + ':00',
                                                        start_date: selectedSlot.date,
                                                        end_date: endDate,
                                                        frequency_weeks: 1,
                                                        number_of_weeks: numberOfWeeks
                                                    })
                                                });
                                                
                                                const data = await response.json();
                                                
                                                if (data.success) {
                                                    alert(`Recurring lesson series booked successfully! ${data.lessons_created} lessons created.`);
                                                    window.location.reload();
                                                } else {
                                                    alert('Error: ' + (data.error || 'Booking failed'));
                                                }
                                            } catch (error) {
                                                alert('Error booking recurring lesson: ' + error);
                                            }
                                        } else {
                                            // Book single lesson
                                            const formData = new FormData();
                                            formData.append('action', 'book_lesson');
                                            formData.append('lesson_date', selectedSlot.date);
                                            formData.append('start_time', selectedSlot.startTime);
                                            formData.append('end_time', selectedSlot.endTime);

                                            try {
                                                const response = await fetch('schedule.php', {
                                                    method: 'POST',
                                                    body: formData
                                                });

                                                const data = await response.json();
                                                
                                                if (response.ok) {
                                                    alert('Lesson booked successfully! Proceeding to payment.');
                                                    window.location.href = 'payment.php';
                                                } else {
                                                    alert('Error: ' + (data.error || 'Booking failed'));
                                                }
                                            } catch (error) {
                                                alert('Error booking lesson: ' + error);
                                            }
                                        }
                                    });
                                    </script>
                                <?php else: ?>
                                    <div class="alert alert-error">
                                        <i class="fas fa-exclamation-circle"></i> This teacher has not set up their availability yet. Please try another teacher.
                                    </div>
                                    <a href="schedule.php" class="btn"><i class="fas fa-arrow-left"></i> Back to Teachers</a>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <?php if ($_SESSION['user_role'] === 'teacher' && $selected_teacher === $_SESSION['user_id'] && count($current_lessons) > 0): ?>
                        <div class="card">
                            <h3><i class="fas fa-clock"></i> Your Upcoming Lessons</h3>
                            <div class="lessons-list">
                                <?php foreach ($current_lessons as $lesson): ?>
                                    <div class="lesson-item">
                                        <div style="display: flex; justify-content: space-between; align-items: flex-start;">
                                            <div>
                                                <strong>Student:</strong> <?php 
                                                    $displayName = (strtolower($lesson['student_email'] ?? '') === 'student@statenacademy.com') 
                                                        ? 'Test Class' 
                                                        : htmlspecialchars($lesson['student_name'] ?? 'Student');
                                                    echo $displayName;
                                                ?><br>
                                                <strong>Date:</strong> <?php echo date('M d, Y', strtotime($lesson['lesson_date'])); ?><br>
                                                <strong>Time:</strong> <?php echo date('H:i', strtotime($lesson['start_time'])); ?> - <?php echo date('H:i', strtotime($lesson['end_time'])); ?>
                                            </div>
                                            <a href="classroom.php?lessonId=<?php echo $lesson['id']; ?>" 
                                               class="btn btn-success" 
                                               style="margin-left: 15px; white-space: nowrap;"
                                               title="Join Classroom">
                                                <i class="fas fa-video"></i> Join
                                            </a>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endif; ?>
                    
                    <!-- Student Calendar View -->
                    <?php if (($user_role === 'student' || $user_role === 'new_student')): ?>
                        <div class="card" style="margin-top: 20px;">
                            <h3><i class="fas fa-calendar"></i> Your Lesson Calendar</h3>
                            <div id="student-calendar-container" class="calendar-container"></div>
                        </div>
                    <?php endif; ?>
                    
                    <?php if (($user_role === 'student' || $user_role === 'new_student') && count($student_upcoming_lessons) > 0): ?>
                        <div class="card">
                            <h3><i class="fas fa-calendar-check"></i> Your Upcoming Lessons</h3>
                            <div class="lessons-list">
                                <?php foreach ($student_upcoming_lessons as $lesson): ?>
                                    <?php
                                    $lessonDateTime = strtotime($lesson['lesson_date'] . ' ' . $lesson['start_time']);
                                    $currentTime = time();
                                    $minutesUntilLesson = ($lessonDateTime - $currentTime) / 60;
                                    // Students can only join 4 minutes before lesson starts
                                    $canJoin = ($minutesUntilLesson <= 4 && $minutesUntilLesson >= -60); // Can join 4 min before, up to 1 hour after
                                    $isPast = $lessonDateTime < time();
                                    ?>
                                    <div class="lesson-item" style="<?php echo $isPast ? 'opacity: 0.6;' : ''; ?>">
                                        <div style="display: flex; justify-content: space-between; align-items: flex-start; flex-wrap: wrap; gap: 10px;">
                                            <div style="flex: 1; min-width: 200px;">
                                                <strong>Teacher:</strong> <?php echo htmlspecialchars($lesson['teacher_name']); ?><br>
                                                <strong>Date:</strong> <?php echo date('M d, Y', strtotime($lesson['lesson_date'])); ?><br>
                                                <strong>Time:</strong> <?php echo date('H:i', strtotime($lesson['start_time'])); ?> - <?php echo date('H:i', strtotime($lesson['end_time'])); ?>
                                                <?php if ($isPast): ?>
                                                    <br><span style="color: #dc3545; font-size: 0.9rem;"><i class="fas fa-clock"></i> Past lesson</span>
                                                <?php elseif ($canJoin): ?>
                                                    <br><span style="color: #28a745; font-size: 0.9rem;"><i class="fas fa-circle" style="font-size: 0.6rem;"></i> Join now</span>
                                                <?php else: ?>
                                                    <br><span style="color: #6c757d; font-size: 0.9rem;"><i class="fas fa-clock"></i> Starts in <?php echo round(($lessonDateTime - time()) / 3600, 1); ?> hours</span>
                                                <?php endif; ?>
                                            </div>
                                            <div style="display: flex; gap: 10px; align-items: center;">
                                                <a href="classroom.php?lessonId=<?php echo $lesson['id']; ?>" 
                                                   class="btn <?php echo $canJoin ? 'btn-success' : 'btn-outline'; ?>" 
                                                   style="white-space: nowrap;"
                                                   title="Join Classroom">
                                                    <i class="fas fa-video"></i> <?php echo $canJoin ? 'Join Now' : 'Join'; ?>
                                                </a>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endif; ?>
                <?php endif; ?>
        </div>
    </div>
</div>

    <script src="<?php echo getAssetPath('js/menu.js'); ?>" defer></script>
    <script src="<?php echo getAssetPath('js/timezone.js'); ?>" defer></script>
    <script src="<?php echo getAssetPath('js/calendar.js'); ?>"></script>
    <script>
    // Initialize student calendar if on schedule page (outside teacher booking section)
    document.addEventListener('DOMContentLoaded', function() {
        const studentCalendarContainer = document.getElementById('student-calendar-container');
        if (typeof Calendar !== 'undefined' && studentCalendarContainer) {
            const studentId = <?php echo json_encode($user_id); ?>;
            const userTimezone = '<?php echo htmlspecialchars($user_timezone); ?>';
            const studentCalendar = new Calendar('student-calendar-container', {
                view: 'month',
                timezone: userTimezone || window.userTimezone || 'UTC',
                studentId: studentId,
                onLessonClick: function(lesson) {
                    // Navigate to classroom if lesson is available
                    if (lesson.id) {
                        window.location.href = 'classroom.php?lessonId=' + lesson.id;
                    }
                }
            });
        }
    });
    </script>
</body>
</html>
<?php
// End output buffering
ob_end_flush();
?>
