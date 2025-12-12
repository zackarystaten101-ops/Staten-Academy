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
require_once __DIR__ . '/app/Services/TeacherService.php';

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

// Initialize TeacherService
$teacherService = new TeacherService($conn);

// For students, get teachers in their category
if ($user_role === 'student' || $user_role === 'new_student') {
    // Get student's preferred category
    $stmt = $conn->prepare("SELECT preferred_category FROM users WHERE id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();
    $stmt->close();
    
    $student_category = ($user && isset($user['preferred_category'])) ? $user['preferred_category'] : 'adults'; // Default to adults if not set
    
    // Get all teachers in student's category
    $all_available_teachers = $teacherService->getTeachersByCategory($student_category, ['has_availability' => true]);
    
    // Get selected teacher from query parameter (if browsing a specific teacher)
    $selected_teacher = isset($_GET['teacher_id']) ? intval($_GET['teacher_id']) : null;
    
    if ($selected_teacher) {
        // Fetch selected teacher details
        $teacher_data = $teacherService->getTeacherProfile($selected_teacher);
        if ($teacher_data) {
            // Fetch teacher's availability slots
            $start_date = date('Y-m-d');
            $end_date = date('Y-m-d', strtotime('+30 days'));
            $availability_slots = $teacherService->getTeacherAvailability($selected_teacher, $start_date, $end_date);
        }
    }
    
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
if ($_SERVER['REQUEST_METHOD'] === 'POST' && (
    (isset($_POST['action']) && $_POST['action'] === 'book_lesson') ||
    (isset($_SERVER['CONTENT_TYPE']) && strpos($_SERVER['CONTENT_TYPE'], 'application/json') !== false)
)) {
    header('Content-Type: application/json');
    
    // Handle JSON input
    $json_input = null;
    if (isset($_SERVER['CONTENT_TYPE']) && strpos($_SERVER['CONTENT_TYPE'], 'application/json') !== false) {
        $json_input = json_decode(file_get_contents('php://input'), true);
        if ($json_input && isset($json_input['action']) && $json_input['action'] === 'book_lesson') {
            $_POST = array_merge($_POST, $json_input);
        }
    }
    
    // Only allow students (who have purchased) to book lessons
    if ($_SESSION['user_role'] !== 'student' && $_SESSION['user_role'] !== 'new_student') {
        http_response_code(403);
        echo json_encode(['error' => 'Please purchase a lesson plan first to book lessons. Visit the payment page to get started.']);
        exit();
    }
    
    // Check if student has completed onboarding
    $stmt = $conn->prepare("SELECT plan_id FROM users WHERE id = ?");
    $stmt->bind_param("i", $_SESSION['user_id']);
    $stmt->execute();
    $user_result = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    
    if (!$user_result || empty($user_result['plan_id'])) {
        http_response_code(403);
        echo json_encode(['error' => 'Please select and purchase a plan first.']);
        exit();
    }
    
    // Get teacher_id from POST data (for combined availability bookings)
    $teacher_id_from_post = isset($_POST['teacher_id']) ? (int)$_POST['teacher_id'] : null;
    
    // Use teacher from POST if provided (combined availability), otherwise use assigned teacher
    $booking_teacher_id = $teacher_id_from_post ?? $selected_teacher;
    
    if (!$booking_teacher_id) {
        http_response_code(400);
        echo json_encode(['error' => 'No teacher selected. Please select a teacher and time slot.']);
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
        'teacher_id' => $booking_teacher_id,
        'lesson_date' => $lesson_date,
        'start_time' => $start_time,
        'end_time' => $end_time
    ]);

    // Create temporary file with JSON
    $temp_file = fopen('php://memory', 'r+');
    fwrite($temp_file, $json_input);
    rewind($temp_file);

    // Manually call the booking logic here instead
    $teacher_id = $booking_teacher_id;
    $student_id = $_SESSION['user_id'];
    
    // Note: assigned_teacher_id is deprecated - students can now book with any teacher in their category
    // No need to update assigned_teacher_id anymore

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

    // Get teacher's meeting preferences and buffer time
    $teacher_buffer = $teacher['default_buffer_minutes'] ?? 15;
    $meeting_type = $teacher['preferred_meeting_type'] ?? 'zoom';
    $meeting_link = null;
    
    // Generate meeting link based on teacher preference
    if ($meeting_type === 'zoom' && !empty($teacher['zoom_link'])) {
        $meeting_link = $teacher['zoom_link'];
    } elseif ($meeting_type === 'google_meet' && !empty($teacher['google_meet_link'])) {
        $meeting_link = $teacher['google_meet_link'];
    } else {
        // Generate default classroom link
        $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http";
        $domain = $protocol . "://" . $_SERVER['HTTP_HOST'] . dirname($_SERVER['PHP_SELF']);
        $meeting_link = $domain . '/classroom.php?lessonId=';
    }
    
    // Get reschedule and cancel policy hours (default 24 hours)
    // Note: These are stored per lesson, not per teacher, so we use defaults here
    $reschedule_policy = 24; // Default 24 hours advance notice
    $cancel_policy = 24; // Default 24 hours advance notice
    
    // Create lesson record with Preply-style features
    $google_event_id = null;
    $stmt = $conn->prepare("
        INSERT INTO lessons (teacher_id, student_id, lesson_date, start_time, end_time, status, lesson_type, color_code, 
                            meeting_link, meeting_type, buffer_time_minutes, reschedule_policy_hours, cancel_policy_hours)
        VALUES (?, ?, ?, ?, ?, 'scheduled', 'single', '#0b6cf5', ?, ?, ?, ?, ?)
    ");
    $stmt->bind_param("iisssssiii", $teacher_id, $student_id, $lesson_date, $start_time, $end_time, 
                     $meeting_link, $meeting_type, $teacher_buffer, $reschedule_policy, $cancel_policy);

    if (!$stmt->execute()) {
        http_response_code(500);
        echo json_encode(['error' => 'Failed to create lesson']);
        $stmt->close();
        exit();
    }

    $lesson_id = $stmt->insert_id;
    $stmt->close();
    
    // Update meeting link with lesson ID if it's a classroom link
    if (strpos($meeting_link, 'classroom.php?lessonId=') !== false) {
        $meeting_link = $meeting_link . $lesson_id;
        $update_link = $conn->prepare("UPDATE lessons SET meeting_link = ? WHERE id = ?");
        $update_link->bind_param("si", $meeting_link, $lesson_id);
        $update_link->execute();
        $update_link->close();
    }

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
                           'Join Lesson: ' . $meeting_link . "\n" .
                           'Classroom: ' . $classroom_url,
            'start_datetime' => $start_datetime,
            'end_datetime' => $end_datetime,
            'location' => $meeting_link
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
$_SESSION['profile_pic'] = ($user && isset($user['profile_pic'])) ? $user['profile_pic'] : getAssetPath('images/placeholder-teacher.svg');
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
                <?php if (isset($all_available_teachers) && count($all_available_teachers) > 0 && !$selected_teacher): ?>
                    <!-- Combined Availability View: Show all available teachers' schedules -->
                    <div class="card">
                        <h3><i class="fas fa-calendar-alt"></i> Select Available Time Slot</h3>
                        <p style="color: #666; margin-bottom: 20px;">
                            Choose from available time slots across all teachers. Once you book a lesson, that teacher will be assigned to you.
                        </p>
                        
                        <div style="margin-top: 20px;">
                            <label>Select Date:</label>
                            <input type="date" id="lesson-date-combined" 
                                   min="<?php echo date('Y-m-d'); ?>" 
                                   value="<?php echo date('Y-m-d'); ?>"
                                   style="width: 100%; padding: 10px; margin: 10px 0; border: 1px solid #ddd; border-radius: 4px;">
                            
                            <div id="combined-time-slots-container" style="margin-top: 15px;">
                                <p style="color: #666;">Select a date to see available time slots</p>
                            </div>
                            
                            <div id="combined-booking-form" style="display: none; margin-top: 20px; padding: 20px; background: #f8f9fa; border-radius: 8px;">
                                <h4>Book This Lesson</h4>
                                <div id="selected-time-display-combined" style="font-weight: bold; margin-bottom: 15px;"></div>
                                <button onclick="bookCombinedSlot()" class="btn-primary">Confirm Booking</button>
                            </div>
                        </div>
                        
                        <script>
                        const allAvailableTeachers = <?php echo json_encode($all_available_teachers); ?>;
                        let selectedCombinedSlot = null;
                        
                        document.getElementById('lesson-date-combined').addEventListener('change', function() {
                            const selectedDate = this.value;
                            const dayOfWeek = new Date(selectedDate + 'T00:00:00').toLocaleDateString('en-US', { weekday: 'long' });
                            
                            // Collect all available slots from all teachers for this date
                            let combinedSlots = [];
                            
                            allAvailableTeachers.forEach(teacher => {
                                if (teacher.availability_slots) {
                                    teacher.availability_slots.forEach(slot => {
                                        const isWeeklySlot = slot.day_of_week === dayOfWeek && !slot.specific_date;
                                        const isOneTimeSlot = slot.specific_date === selectedDate;
                                        
                                        if ((isWeeklySlot || isOneTimeSlot) && slot.is_available) {
                                            combinedSlots.push({
                                                ...slot,
                                                teacher_id: teacher.id,
                                                teacher_name: teacher.name
                                            });
                                        }
                                    });
                                }
                            });
                            
                            // Sort by time
                            combinedSlots.sort((a, b) => a.start_time.localeCompare(b.start_time));
                            
                            let html = '';
                            if (combinedSlots.length > 0) {
                                html = '<label>Available Times:</label><div class="time-slots">';
                                combinedSlots.forEach(slot => {
                                    html += `<div class="time-slot" onclick="selectCombinedSlot('${selectedDate}', '${slot.start_time.substr(0,5)}', '${slot.end_time.substr(0,5)}', ${slot.teacher_id}, '${slot.teacher_name.replace("'", "\\'")}')">
                                        ${slot.start_time.substr(0,5)} - ${slot.end_time.substr(0,5)}<br>
                                        <small style="color: #666;">with ${slot.teacher_name}</small>
                                    </div>`;
                                });
                                html += '</div>';
                            } else {
                                html = '<p style="color: #dc3545;"><i class="fas fa-info-circle"></i> No available time slots for this date</p>';
                            }
                            
                            document.getElementById('combined-time-slots-container').innerHTML = html;
                        });
                        
                        function selectCombinedSlot(date, startTime, endTime, teacherId, teacherName) {
                            selectedCombinedSlot = { date, startTime, endTime, teacherId, teacherName };
                            document.getElementById('selected-time-display-combined').innerHTML = 
                                `<strong>Date:</strong> ${date}<br><strong>Time:</strong> ${startTime} - ${endTime}<br><strong>Teacher:</strong> ${teacherName}`;
                            document.getElementById('combined-booking-form').style.display = 'block';
                            
                            // Highlight selected slot
                            document.querySelectorAll('.time-slot').forEach(el => el.classList.remove('selected'));
                            event.target.closest('.time-slot').classList.add('selected');
                        }
                        
                        async function bookCombinedSlot() {
                            if (!selectedCombinedSlot) {
                                alert('Please select a time slot');
                                return;
                            }
                            
                            try {
                                const basePath = window.location.pathname.substring(0, window.location.pathname.lastIndexOf('/'));
                                const response = await fetch(basePath + '/schedule.php', {
                                    method: 'POST',
                                    headers: { 'Content-Type': 'application/json' },
                                    body: JSON.stringify({
                                        action: 'book_lesson',
                                        teacher_id: selectedCombinedSlot.teacherId,
                                        lesson_date: selectedCombinedSlot.date,
                                        start_time: selectedCombinedSlot.startTime + ':00',
                                        end_time: selectedCombinedSlot.endTime + ':00'
                                    })
                                });
                                
                                const data = await response.json();
                                
                                if (data.success) {
                                    alert('Lesson booked successfully! The teacher has been assigned to you.');
                                    window.location.reload();
                                } else {
                                    alert('Error: ' + (data.error || 'Booking failed'));
                                }
                            } catch (error) {
                                console.error('Booking error:', error);
                                alert('An error occurred. Please try again.');
                            }
                        }
                        
                        // Trigger initial load
                        document.getElementById('lesson-date-combined').dispatchEvent(new Event('change'));
                        </script>
                    </div>
                <?php elseif (!isset($_GET['teacher']) && ($_SESSION['user_role'] === 'student' || $_SESSION['user_role'] === 'new_student')): ?>
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
                            <?php if (isset($all_available_teachers) && count($all_available_teachers) > 0): ?>
                                <?php foreach ($all_available_teachers as $teacher): ?>
                                    <div class="teacher-card">
                                        <img src="<?php echo htmlspecialchars($teacher['profile_pic'] ?? getAssetPath('images/placeholder-teacher.svg')); ?>" 
                                             style="width: 80px; height: 80px; border-radius: 50%; object-fit: cover; margin-bottom: 10px;" onerror="this.src='<?php echo getAssetPath('images/placeholder-teacher.svg'); ?>'">
                                        <h4><?php echo htmlspecialchars($teacher['name']); ?></h4>
                                        <?php if ($teacher['specialty']): ?>
                                            <p style="color: #666; font-size: 0.9rem;"><?php echo htmlspecialchars($teacher['specialty']); ?></p>
                                        <?php endif; ?>
                                        <?php if ($teacher['avg_rating']): ?>
                                            <p style="color: #ffa500; margin: 5px 0;">
                                                <?php 
                                                $rating = floatval($teacher['avg_rating']);
                                                for ($i = 0; $i < floor($rating); $i++) {
                                                    echo '<i class="fas fa-star"></i>';
                                                }
                                                ?>
                                                <?php echo number_format($rating, 1); ?> (<?php echo intval($teacher['review_count']); ?>)
                                            </p>
                                        <?php endif; ?>
                                        <a href="schedule.php?teacher_id=<?php echo intval($teacher['id']); ?>" class="btn">View Availability & Book</a>
                                    </div>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <p>No teachers available in your category. Please contact support.</p>
                            <?php endif; ?>
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
                                
                                <!-- Timezone and Calendar Info -->
                                <div style="background: linear-gradient(135deg, #e7f3ff 0%, #f0f7ff 100%); padding: 20px; border-radius: 8px; margin: 20px 0; border: 2px solid #0b6cf5;">
                                    <div style="display: flex; align-items: center; gap: 15px; flex-wrap: wrap;">
                                        <div style="flex: 1; min-width: 200px;">
                                            <p style="margin: 0 0 10px 0; color: #004080; font-weight: 600;">
                                                <i class="fas fa-globe"></i> Your Timezone: <strong><?php echo htmlspecialchars($user_timezone ?: 'Not set'); ?></strong>
                                            </p>
                                            <p style="margin: 0; color: #666; font-size: 0.9rem;">
                                                <i class="fas fa-info-circle"></i> All times are displayed in your local timezone. The teacher will see the lesson in their timezone.
                                            </p>
                                        </div>
                                        <button onclick="showTimezoneSelector()" class="btn-outline" style="white-space: nowrap; padding: 10px 20px;">
                                            <i class="fas fa-cog"></i> Change Timezone
                                        </button>
                                    </div>
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
                                    
                                    function showTimezoneSelector() {
                                        const modal = document.getElementById('timezoneModal');
                                        if (!modal) {
                                            createTimezoneModal();
                                        }
                                        document.getElementById('timezoneModal').classList.add('active');
                                    }
                                    
                                    function createTimezoneModal() {
                                        const modal = document.createElement('div');
                                        modal.id = 'timezoneModal';
                                        modal.className = 'modal-overlay';
                                        modal.innerHTML = `
                                            <div class="modal" style="max-width: 500px;">
                                                <div class="modal-header">
                                                    <h3><i class="fas fa-globe"></i> Change Timezone</h3>
                                                    <button class="modal-close" onclick="closeTimezoneModal()">&times;</button>
                                                </div>
                                                <div style="padding: 20px;">
                                                    <p style="color: #666; margin-bottom: 20px;">Select your timezone to see lesson times in your local time.</p>
                                                    <select id="timezoneSelect" style="width: 100%; padding: 12px; border: 2px solid #ddd; border-radius: 8px; font-size: 1rem; margin-bottom: 15px;">
                                                        <option value="America/New_York">Eastern Time (ET)</option>
                                                        <option value="America/Chicago">Central Time (CT)</option>
                                                        <option value="America/Denver">Mountain Time (MT)</option>
                                                        <option value="America/Los_Angeles">Pacific Time (PT)</option>
                                                        <option value="Europe/London">London (GMT)</option>
                                                        <option value="Europe/Paris">Paris (CET)</option>
                                                        <option value="Asia/Tokyo">Tokyo (JST)</option>
                                                        <option value="Asia/Shanghai">Shanghai (CST)</option>
                                                        <option value="Australia/Sydney">Sydney (AEST)</option>
                                                        <option value="UTC">UTC</option>
                                                    </select>
                                                    <button onclick="detectAndSetTimezone()" class="btn-outline" style="width: 100%; margin-bottom: 15px;">
                                                        <i class="fas fa-crosshairs"></i> Auto-detect My Timezone
                                                    </button>
                                                    <div style="display: flex; gap: 10px;">
                                                        <button onclick="updateTimezone()" class="btn-primary" style="flex: 1;">
                                                            <i class="fas fa-save"></i> Save
                                                        </button>
                                                        <button onclick="closeTimezoneModal()" class="btn-outline" style="flex: 1;">
                                                            Cancel
                                                        </button>
                                                    </div>
                                                </div>
                                            </div>
                                        `;
                                        document.body.appendChild(modal);
                                        
                                        // Set current timezone
                                        const select = document.getElementById('timezoneSelect');
                                        const currentTz = '<?php echo htmlspecialchars($user_timezone); ?>';
                                        if (currentTz) {
                                            select.value = currentTz;
                                        }
                                    }
                                    
                                    function closeTimezoneModal() {
                                        document.getElementById('timezoneModal').classList.remove('active');
                                    }
                                    
                                    function detectAndSetTimezone() {
                                        try {
                                            const detectedTz = Intl.DateTimeFormat().resolvedOptions().timeZone;
                                            const select = document.getElementById('timezoneSelect');
                                            
                                            // Try to find exact match
                                            for (let option of select.options) {
                                                if (option.value === detectedTz) {
                                                    select.value = detectedTz;
                                                    if (typeof toast !== 'undefined') {
                                                        toast.success('Timezone detected: ' + detectedTz);
                                                    } else {
                                                        alert('Timezone detected: ' + detectedTz);
                                                    }
                                                    return;
                                                }
                                            }
                                            
                                            // Try partial match
                                            const tzParts = detectedTz.split('/');
                                            for (let option of select.options) {
                                                if (option.value.includes(tzParts[tzParts.length - 1])) {
                                                    select.value = option.value;
                                                    if (typeof toast !== 'undefined') {
                                                        toast.info('Similar timezone selected: ' + option.value);
                                                    } else {
                                                        alert('Similar timezone selected: ' + option.value);
                                                    }
                                                    return;
                                                }
                                            }
                                            
                                            if (typeof toast !== 'undefined') {
                                                toast.warning('Could not find matching timezone. Please select manually.');
                                            } else {
                                                alert('Could not find matching timezone. Please select manually.');
                                            }
                                        } catch (err) {
                                            if (typeof toast !== 'undefined') {
                                                toast.error('Could not detect timezone.');
                                            } else {
                                                alert('Could not detect timezone.');
                                            }
                                        }
                                    }
                                    
                                    function updateTimezone() {
                                        const timezone = document.getElementById('timezoneSelect').value;
                                        
                                        fetch('api/calendar.php?action=update-timezone', {
                                            method: 'POST',
                                            headers: {
                                                'Content-Type': 'application/json'
                                            },
                                            body: JSON.stringify({
                                                timezone: timezone,
                                                auto_detected: false
                                            })
                                        })
                                        .then(res => res.json())
                                        .then(data => {
                                            if (data.success) {
                                                if (typeof toast !== 'undefined') {
                                                    toast.success('Timezone updated! Page will reload.');
                                                } else {
                                                    alert('Timezone updated! Page will reload.');
                                                }
                                                setTimeout(() => location.reload(), 1000);
                                            } else {
                                                if (typeof toast !== 'undefined') {
                                                    toast.error(data.error || 'Failed to update timezone');
                                                } else {
                                                    alert('Error: ' + (data.error || 'Failed to update timezone'));
                                                }
                                            }
                                        })
                                        .catch(err => {
                                            console.error('Error:', err);
                                            if (typeof toast !== 'undefined') {
                                                toast.error('An error occurred. Please try again.');
                                            } else {
                                                alert('An error occurred. Please try again.');
                                            }
                                        });
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
                                                
                                                if (response.ok && data.success) {
                                                    // Show success message with booking details
                                                    const bookingDetails = `
                                                        <div style="text-align: center; padding: 20px;">
                                                            <div style="font-size: 3rem; color: #28a745; margin-bottom: 15px;">
                                                                <i class="fas fa-check-circle"></i>
                                                            </div>
                                                            <h2 style="color: #004080; margin-bottom: 15px;">Lesson Booked Successfully!</h2>
                                                            <div style="background: #f0f7ff; padding: 15px; border-radius: 8px; margin: 20px 0; text-align: left; display: inline-block;">
                                                                <p style="margin: 5px 0;"><strong>Date:</strong> ${selectedSlot.date}</p>
                                                                <p style="margin: 5px 0;"><strong>Time:</strong> ${selectedSlot.startTime} - ${selectedSlot.endTime}</p>
                                                                <p style="margin: 5px 0;"><strong>Teacher:</strong> <?php echo htmlspecialchars($teacher_data['name']); ?></p>
                                                            </div>
                                                            <p style="color: #666; margin: 20px 0;">You will receive a confirmation email shortly.</p>
                                                            <div style="display: flex; gap: 10px; justify-content: center; margin-top: 25px;">
                                                                <a href="student-dashboard.php#bookings" class="btn-primary" style="padding: 12px 24px; text-decoration: none;">
                                                                    <i class="fas fa-calendar"></i> View My Lessons
                                                                </a>
                                                                <button onclick="location.reload()" class="btn-outline" style="padding: 12px 24px;">
                                                                    Book Another
                                                                </button>
                                                            </div>
                                                        </div>
                                                    `;
                                                    
                                                    // Create and show modal
                                                    const modal = document.createElement('div');
                                                    modal.className = 'modal-overlay active';
                                                    modal.style.zIndex = '10000';
                                                    modal.innerHTML = `
                                                        <div class="modal" style="max-width: 600px;">
                                                            <div class="modal-header">
                                                                <h3>Booking Confirmed</h3>
                                                                <button class="modal-close" onclick="this.closest('.modal-overlay').remove(); location.href='student-dashboard.php#bookings';">&times;</button>
                                                            </div>
                                                            ${bookingDetails}
                                                        </div>
                                                    `;
                                                    document.body.appendChild(modal);
                                                    
                                                    // Auto-close after 5 seconds and redirect
                                                    setTimeout(() => {
                                                        modal.remove();
                                                        window.location.href = 'student-dashboard.php#bookings';
                                                    }, 5000);
                                                } else {
                                                    if (typeof toast !== 'undefined') {
                                                        toast.error(data.error || 'Booking failed');
                                                    } else {
                                                        alert('Error: ' + (data.error || 'Booking failed'));
                                                    }
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
