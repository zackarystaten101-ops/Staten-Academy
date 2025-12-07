<?php
header('Content-Type: application/json');
session_start();
require_once 'db.php';
require_once 'google-calendar-config.php';

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit();
}

// Check if user is logged in and is a student (not new_student)
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Not authenticated']);
    exit();
}

// Only allow students (who have purchased) to book lessons
if ($_SESSION['user_role'] !== 'student') {
    http_response_code(403);
    echo json_encode(['error' => 'Please purchase a lesson plan first to book lessons.']);
    exit();
}

$student_id = $_SESSION['user_id'];

// Get JSON input
$input = json_decode(file_get_contents('php://input'), true);

if (!isset($input['teacher_id']) || !isset($input['lesson_date']) || !isset($input['start_time']) || !isset($input['end_time'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Missing required fields']);
    exit();
}

$teacher_id = (int)$input['teacher_id'];
$lesson_date = $input['lesson_date'];
$start_time = $input['start_time'];
$end_time = $input['end_time'];

// Validate date and time format
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $lesson_date) || 
    !preg_match('/^\d{2}:\d{2}$/', $start_time) || 
    !preg_match('/^\d{2}:\d{2}$/', $end_time)) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid date/time format']);
    exit();
}

// Check if date is in the future
if (strtotime($lesson_date . ' ' . $start_time) <= time()) {
    http_response_code(400);
    echo json_encode(['error' => 'Cannot book past dates']);
    exit();
}

$api = new GoogleCalendarAPI($conn);

// Check availability
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

if (!$teacher) {
    http_response_code(404);
    echo json_encode(['error' => 'Teacher not found']);
    exit();
}

// Get student info
$stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
$stmt->bind_param("i", $student_id);
$stmt->execute();
$student = $stmt->get_result()->fetch_assoc();
$stmt->close();

// Create lesson record in database
$google_event_id = null;
$stmt = $conn->prepare("
    INSERT INTO lessons (teacher_id, student_id, lesson_date, start_time, end_time, status)
    VALUES (?, ?, ?, ?, ?, 'scheduled')
");
$stmt->bind_param("iisss", $teacher_id, $student_id, $lesson_date, $start_time, $end_time);

if (!$stmt->execute()) {
    http_response_code(500);
    echo json_encode(['error' => 'Failed to create lesson: ' . $stmt->error]);
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

// Try to create Google Calendar event if teacher has connected calendar
if (!empty($teacher['google_calendar_token'])) {
    // Check if token has expired and refresh if needed
    if (!empty($teacher['google_calendar_token_expiry'])) {
        if (strtotime($teacher['google_calendar_token_expiry']) <= time()) {
            if (!empty($teacher['google_calendar_refresh_token'])) {
                $token_response = $api->refreshAccessToken($teacher['google_calendar_refresh_token']);
                if (!isset($token_response['error'])) {
                    $teacher['google_calendar_token'] = $token_response['access_token'];
                    // Update in database
                    $new_expiry = date('Y-m-d H:i:s', time() + ($token_response['expires_in'] ?? 3600));
                    $stmt = $conn->prepare("UPDATE users SET google_calendar_token = ?, google_calendar_token_expiry = ? WHERE id = ?");
                    $stmt->bind_param("ssi", $teacher['google_calendar_token'], $new_expiry, $teacher_id);
                    $stmt->execute();
                    $stmt->close();
                }
            }
        }
    }

    // Create calendar event
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
        
        // Update lesson with Google Calendar event ID
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
    
    // Note: Student's event is created in their calendar
}

// Return success response
http_response_code(201);
echo json_encode([
    'success' => true,
    'lesson_id' => $lesson_id,
    'google_calendar_event_id' => $google_event_id,
    'message' => 'Lesson booked successfully'
]);
exit();
?>
