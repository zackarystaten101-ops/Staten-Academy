<?php
/**
 * Lesson Actions API
 * Handles reschedule, cancel, and other lesson actions
 */

session_start();
require_once '../db.php';
require_once '../app/Services/CalendarService.php';
require_once '../app/Services/TimezoneService.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$user_id = $_SESSION['user_id'];
$user_role = $_SESSION['user_role'] ?? '';
$action = $_GET['action'] ?? $_POST['action'] ?? '';

$calendarService = new CalendarService($conn);
$tzService = new TimezoneService($conn);

switch ($action) {
    case 'reschedule':
        rescheduleLesson($conn, $user_id, $user_role, $calendarService, $tzService);
        break;
    case 'cancel':
        cancelLesson($conn, $user_id, $user_role, $calendarService);
        break;
    case 'get_lesson':
        getLesson($conn, $user_id, $user_role);
        break;
    default:
        http_response_code(400);
        echo json_encode(['error' => 'Invalid action']);
}

/**
 * Reschedule a lesson
 */
function rescheduleLesson($conn, $user_id, $user_role, $calendarService, $tzService) {
    $lesson_id = isset($_POST['lesson_id']) ? (int)$_POST['lesson_id'] : 0;
    $new_date = $_POST['new_date'] ?? '';
    $new_start_time = $_POST['new_start_time'] ?? '';
    $new_end_time = $_POST['new_end_time'] ?? '';
    $reason = trim($_POST['reason'] ?? '');
    
    if (!$lesson_id || !$new_date || !$new_start_time || !$new_end_time) {
        http_response_code(400);
        echo json_encode(['error' => 'Missing required fields']);
        return;
    }
    
    // Get lesson details
    $stmt = $conn->prepare("SELECT * FROM lessons WHERE id = ?");
    $stmt->bind_param("i", $lesson_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $lesson = $result->fetch_assoc();
    $stmt->close();
    
    if (!$lesson) {
        http_response_code(404);
        echo json_encode(['error' => 'Lesson not found']);
        return;
    }
    
    // Check permissions
    if ($user_role === 'student' && $lesson['student_id'] != $user_id) {
        http_response_code(403);
        echo json_encode(['error' => 'Access denied']);
        return;
    }
    if ($user_role === 'teacher' && $lesson['teacher_id'] != $user_id) {
        http_response_code(403);
        echo json_encode(['error' => 'Access denied']);
        return;
    }
    
    // Check reschedule policy
    $lesson_datetime = strtotime($lesson['lesson_date'] . ' ' . $lesson['start_time']);
    $hours_until = ($lesson_datetime - time()) / 3600;
    $reschedule_policy = $lesson['reschedule_policy_hours'] ?? 24;
    
    if ($hours_until < $reschedule_policy) {
        http_response_code(400);
        echo json_encode(['error' => "Lesson can only be rescheduled at least {$reschedule_policy} hours in advance. Current time until lesson: " . round($hours_until, 1) . " hours."]);
        return;
    }
    
    // Validate new time slot availability
    $teacher_id = $lesson['teacher_id'];
    require_once '../google-calendar-config.php';
    $api = new GoogleCalendarAPI($conn);
    
    $availability_check = $api->isSlotAvailable($teacher_id, $new_date, $new_start_time, $new_end_time);
    if (!$availability_check['available']) {
        http_response_code(409);
        echo json_encode(['error' => $availability_check['reason']]);
        return;
    }
    
    // Check booking notice
    $new_lesson_datetime = $new_date . ' ' . $new_start_time . ':00';
    $notice_check = $calendarService->validateBookingNotice($teacher_id, $new_lesson_datetime);
    if (!$notice_check['valid']) {
        http_response_code(400);
        echo json_encode(['error' => $notice_check['reason']]);
        return;
    }
    
    // Create new lesson record (marking old one as rescheduled)
    $old_lesson_id = $lesson_id;
    $stmt = $conn->prepare("
        INSERT INTO lessons (teacher_id, student_id, lesson_date, start_time, end_time, status, 
                            lesson_type, color_code, meeting_link, meeting_type, buffer_time_minutes,
                            reschedule_policy_hours, cancel_policy_hours, rescheduled_from)
        VALUES (?, ?, ?, ?, ?, 'scheduled', ?, ?, ?, ?, ?, ?, ?, ?)
    ");
    $stmt->bind_param("iisssssssiiii", 
        $teacher_id, $lesson['student_id'], $new_date, $new_start_time, $new_end_time,
        $lesson['lesson_type'], $lesson['color_code'], $lesson['meeting_link'], 
        $lesson['meeting_type'], $lesson['buffer_time_minutes'],
        $lesson['reschedule_policy_hours'], $lesson['cancel_policy_hours'], $old_lesson_id
    );
    
    if (!$stmt->execute()) {
        http_response_code(500);
        echo json_encode(['error' => 'Failed to reschedule lesson']);
        $stmt->close();
        return;
    }
    
    $new_lesson_id = $stmt->insert_id;
    $stmt->close();
    
    // Update old lesson status
    $update_stmt = $conn->prepare("UPDATE lessons SET status = 'cancelled', cancellation_reason = ? WHERE id = ?");
    $cancel_reason = 'Rescheduled to ' . $new_date . ' ' . $new_start_time . ($reason ? ' - ' . $reason : '');
    $update_stmt->bind_param("si", $cancel_reason, $old_lesson_id);
    $update_stmt->execute();
    $update_stmt->close();
    
    // Update Google Calendar if connected
    if (!empty($lesson['google_calendar_event_id'])) {
        // Delete old event and create new one
        // This would require additional Google Calendar API methods
    }
    
    // Notify both parties
    $other_party_id = $user_role === 'student' ? $teacher_id : $lesson['student_id'];
    $notifier_name = $_SESSION['user_name'] ?? 'User';
    createNotification($conn, $other_party_id, 'lesson', 'Lesson Rescheduled', 
        "$notifier_name rescheduled the lesson to $new_date at $new_start_time", 
        'schedule.php');
    
    echo json_encode(['success' => true, 'new_lesson_id' => $new_lesson_id]);
}

/**
 * Cancel a lesson
 */
function cancelLesson($conn, $user_id, $user_role, $calendarService) {
    $lesson_id = isset($_POST['lesson_id']) ? (int)$_POST['lesson_id'] : 0;
    $reason = trim($_POST['reason'] ?? '');
    
    if (!$lesson_id) {
        http_response_code(400);
        echo json_encode(['error' => 'Missing lesson ID']);
        return;
    }
    
    // Get lesson details
    $stmt = $conn->prepare("SELECT * FROM lessons WHERE id = ?");
    $stmt->bind_param("i", $lesson_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $lesson = $result->fetch_assoc();
    $stmt->close();
    
    if (!$lesson) {
        http_response_code(404);
        echo json_encode(['error' => 'Lesson not found']);
        return;
    }
    
    // Check permissions
    if ($user_role === 'student' && $lesson['student_id'] != $user_id) {
        http_response_code(403);
        echo json_encode(['error' => 'Access denied']);
        return;
    }
    if ($user_role === 'teacher' && $lesson['teacher_id'] != $user_id) {
        http_response_code(403);
        echo json_encode(['error' => 'Access denied']);
        return;
    }
    
    // Check cancel policy
    $lesson_datetime = strtotime($lesson['lesson_date'] . ' ' . $lesson['start_time']);
    $hours_until = ($lesson_datetime - time()) / 3600;
    $cancel_policy = $lesson['cancel_policy_hours'] ?? 24;
    
    if ($hours_until < $cancel_policy) {
        http_response_code(400);
        echo json_encode(['error' => "Lesson can only be cancelled at least {$cancel_policy} hours in advance. Current time until lesson: " . round($hours_until, 1) . " hours."]);
        return;
    }
    
    // Update lesson status
    $update_stmt = $conn->prepare("UPDATE lessons SET status = 'cancelled', cancellation_reason = ? WHERE id = ?");
    $cancel_reason = ($reason ? $reason : 'Cancelled by ' . ($user_role === 'student' ? 'student' : 'teacher'));
    $update_stmt->bind_param("si", $cancel_reason, $lesson_id);
    
    if (!$update_stmt->execute()) {
        http_response_code(500);
        echo json_encode(['error' => 'Failed to cancel lesson']);
        $update_stmt->close();
        return;
    }
    $update_stmt->close();
    
    // Notify both parties
    $other_party_id = $user_role === 'student' ? $lesson['teacher_id'] : $lesson['student_id'];
    $notifier_name = $_SESSION['user_name'] ?? 'User';
    createNotification($conn, $other_party_id, 'lesson', 'Lesson Cancelled', 
        "$notifier_name cancelled the lesson scheduled for " . $lesson['lesson_date'] . " at " . $lesson['start_time'], 
        'schedule.php');
    
    echo json_encode(['success' => true]);
}

/**
 * Create notification
 */
function createNotification($conn, $user_id, $type, $title, $message, $link = null) {
    // Check if notifications table exists
    $table_check = $conn->query("SHOW TABLES LIKE 'notifications'");
    if ($table_check && $table_check->num_rows > 0) {
        $stmt = $conn->prepare("INSERT INTO notifications (user_id, type, title, message, link) VALUES (?, ?, ?, ?, ?)");
        $stmt->bind_param("issss", $user_id, $type, $title, $message, $link);
        $stmt->execute();
        $stmt->close();
    }
}

/**
 * Get lesson details
 */
function getLesson($conn, $user_id, $user_role) {
    $lesson_id = isset($_GET['lesson_id']) ? (int)$_GET['lesson_id'] : 0;
    
    if (!$lesson_id) {
        http_response_code(400);
        echo json_encode(['error' => 'Missing lesson ID']);
        return;
    }
    
    $stmt = $conn->prepare("SELECT * FROM lessons WHERE id = ?");
    $stmt->bind_param("i", $lesson_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $lesson = $result->fetch_assoc();
    $stmt->close();
    
    if (!$lesson) {
        http_response_code(404);
        echo json_encode(['error' => 'Lesson not found']);
        return;
    }
    
    // Check permissions
    if ($user_role === 'student' && $lesson['student_id'] != $user_id) {
        http_response_code(403);
        echo json_encode(['error' => 'Access denied']);
        return;
    }
    if ($user_role === 'teacher' && $lesson['teacher_id'] != $user_id) {
        http_response_code(403);
        echo json_encode(['error' => 'Access denied']);
        return;
    }
    
    echo json_encode($lesson);
}

