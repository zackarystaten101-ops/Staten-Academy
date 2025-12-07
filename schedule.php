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
    if (isset($_GET['teacher'])) {
        $_SESSION['redirect_teacher'] = $_GET['teacher'];
    }
    header("Location: login.php");
    exit();
}

$api = new GoogleCalendarAPI($conn);
$selected_teacher = null;
$teacher_data = null;
$availability_slots = [];
$current_lessons = [];
$user_role = $_SESSION['user_role'] ?? 'student';

// If teacher parameter is set, fetch teacher details
if (isset($_GET['teacher'])) {
    $teacher_param = trim($_GET['teacher']);
    $teacher_search = "%{$teacher_param}%";
    
    $stmt = $conn->prepare("SELECT id, name, email, profile_pic FROM users WHERE role='teacher' AND name LIKE ? LIMIT 1");
    $stmt->bind_param("s", $teacher_search);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $teacher_data = $result->fetch_assoc();
        $selected_teacher = $teacher_data['id'];
        $stmt->close();
        
        // Fetch teacher's availability slots
        $availability_slots = $api->getTeacherAvailability($selected_teacher, null, null);
        
        // Fetch booked lessons (if user is teacher viewing own lessons)
        if ($user_role === 'teacher' && $selected_teacher === $_SESSION['user_id']) {
            $current_lessons = $api->getTeacherLessons($selected_teacher);
        }
    }
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
        echo json_encode(['error' => 'No teacher selected']);
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

    // Create lesson record
    $google_event_id = null;
    $stmt = $conn->prepare("
        INSERT INTO lessons (teacher_id, student_id, lesson_date, start_time, end_time, status)
        VALUES (?, ?, ?, ?, ?, 'scheduled')
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
        
        $event_data = [
            'title' => 'Lesson: ' . htmlspecialchars($student['name']),
            'description' => 'Student: ' . htmlspecialchars($student['name']) . ' (' . htmlspecialchars($student['email']) . ')',
            'start_datetime' => $start_datetime,
            'end_datetime' => $end_datetime
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
        
        $student_event_data = [
            'title' => 'Lesson with ' . htmlspecialchars($teacher['name']),
            'description' => 'Teacher: ' . htmlspecialchars($teacher['name']) . ' (' . htmlspecialchars($teacher['email']) . ')',
            'start_datetime' => $start_datetime,
            'end_datetime' => $end_datetime,
            'attendees' => [['email' => $teacher['email']]]
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
$user_id = $_SESSION['user_id'];
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

                                    <script>
                                    const teacherId = <?php echo $selected_teacher; ?>;
                                    const availabilitySlots = <?php echo json_encode($availability_slots); ?>;
                                    let selectedSlot = null;

                                    document.getElementById('lesson-date').addEventListener('change', function() {
                                        const selectedDate = this.value;
                                        const dayOfWeek = new Date(selectedDate + 'T00:00:00').toLocaleDateString('en-US', { weekday: 'long' });
                                        
                                        const slotsForDay = availabilitySlots.filter(slot => slot.day_of_week === dayOfWeek && slot.is_available);
                                        
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

                                    function selectSlot(date, startTime, endTime) {
                                        selectedSlot = { date, startTime, endTime };
                                        document.getElementById('selected-time-display').textContent = `${date} from ${startTime} to ${endTime}`;
                                        document.getElementById('booking-form').style.display = 'block';
                                        
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
                                        <strong>Student:</strong> <?php echo htmlspecialchars($lesson['student_name']); ?><br>
                                        <strong>Date:</strong> <?php echo date('M d, Y', strtotime($lesson['lesson_date'])); ?><br>
                                        <strong>Time:</strong> <?php echo date('H:i', strtotime($lesson['start_time'])); ?> - <?php echo date('H:i', strtotime($lesson['end_time'])); ?>
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
</body>
</html>
<?php
// End output buffering
ob_end_flush();
?>
