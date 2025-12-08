<?php
/**
 * Calendar API Endpoints
 * Handles calendar-related requests with timezone support
 */

header('Content-Type: application/json');

// Start session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Load environment configuration if not already loaded
if (!defined('DB_HOST')) {
    require_once __DIR__ . '/../env.php';
}

// Load dependencies
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../app/Services/TimezoneService.php';
require_once __DIR__ . '/../app/Services/CalendarService.php';
require_once __DIR__ . '/../app/Models/TimeOff.php';
require_once __DIR__ . '/../app/Models/RecurringLesson.php';
require_once __DIR__ . '/../app/Models/Lesson.php';

// Check authentication
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Not authenticated']);
    exit();
}

$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? null;
$userId = $_SESSION['user_id'];
$userRole = $_SESSION['user_role'] ?? 'student';

$tzService = new TimezoneService($conn);
$calendarService = new CalendarService($conn);

// Route requests
switch ($method) {
    case 'GET':
        handleGet($action, $userId, $userRole, $tzService, $calendarService);
        break;
    case 'POST':
        handlePost($action, $userId, $userRole, $tzService, $calendarService);
        break;
    case 'DELETE':
        handleDelete($action, $userId, $userRole, $tzService, $calendarService);
        break;
    default:
        http_response_code(405);
        echo json_encode(['error' => 'Method not allowed']);
        break;
}

function handleGet($action, $userId, $userRole, $tzService, $calendarService) {
    global $conn;
    
    switch ($action) {
        case 'availability':
            $teacherId = $_GET['teacher_id'] ?? null;
            $date = $_GET['date'] ?? date('Y-m-d');
            $timezone = $_GET['timezone'] ?? 'UTC';
            
            if (!$teacherId) {
                http_response_code(400);
                echo json_encode(['error' => 'teacher_id required']);
                return;
            }
            
            $slots = $calendarService->getAvailableSlotsWithTimezone($teacherId, $date, $timezone);
            echo json_encode(['success' => true, 'slots' => $slots]);
            break;
            
        case 'lessons':
            $targetUserId = $_GET['user_id'] ?? $userId;
            $timezone = $_GET['timezone'] ?? $tzService->getUserTimezone($userId);
            $dateFrom = $_GET['date_from'] ?? null;
            $dateTo = $_GET['date_to'] ?? null;
            $role = $_GET['role'] ?? $userRole;
            
            // Only allow users to view their own lessons or teachers to view their students
            if ($targetUserId != $userId && $userRole !== 'teacher' && $userRole !== 'admin') {
                http_response_code(403);
                echo json_encode(['error' => 'Access denied']);
                return;
            }
            
            $lessons = $calendarService->getLessonsWithColors($targetUserId, $dateFrom, $dateTo, $role);
            
            // Convert times to user's timezone
            foreach ($lessons as &$lesson) {
                $utcDateTime = $lesson['lesson_date'] . ' ' . $lesson['start_time'];
                $localTime = $tzService->convertUTCToLocalDateTime($utcDateTime, $timezone);
                $lesson['display_date'] = $localTime['date'];
                $lesson['display_start_time'] = $localTime['time'];
            }
            
            echo json_encode(['success' => true, 'lessons' => $lessons]);
            break;
            
        case 'time-off':
            if ($userRole !== 'teacher') {
                http_response_code(403);
                echo json_encode(['error' => 'Only teachers can view time-off']);
                return;
            }
            
            $timeOffModel = new TimeOff($conn);
            $timeOffs = $timeOffModel->getByTeacher($userId);
            echo json_encode(['success' => true, 'time_off' => $timeOffs]);
            break;
            
        case 'validate-notice':
            // This is handled in POST, but we'll add it here for consistency
            http_response_code(405);
            echo json_encode(['error' => 'Use POST method for validation']);
            break;
            
        default:
            http_response_code(400);
            echo json_encode(['error' => 'Invalid action']);
            break;
    }
}

function handlePost($action, $userId, $userRole, $tzService, $calendarService) {
    global $conn;
    
    $input = json_decode(file_get_contents('php://input'), true);
    
    switch ($action) {
        case 'update-timezone':
            $timezone = $input['timezone'] ?? null;
            $autoDetected = $input['auto_detected'] ?? false;
            
            if (!$timezone || !$tzService->isValidTimezone($timezone)) {
                http_response_code(400);
                echo json_encode(['error' => 'Invalid timezone']);
                return;
            }
            
            $result = $tzService->updateUserTimezone($userId, $timezone, $autoDetected);
            if ($result) {
                echo json_encode(['success' => true, 'timezone' => $timezone]);
            } else {
                http_response_code(500);
                echo json_encode(['error' => 'Failed to update timezone']);
            }
            break;
            
        case 'time-off':
            if ($userRole !== 'teacher') {
                http_response_code(403);
                echo json_encode(['error' => 'Only teachers can add time-off']);
                return;
            }
            
            $startDate = $input['start_date'] ?? null;
            $endDate = $input['end_date'] ?? null;
            $reason = $input['reason'] ?? null;
            
            if (!$startDate || !$endDate) {
                http_response_code(400);
                echo json_encode(['error' => 'start_date and end_date required']);
                return;
            }
            
            if (strtotime($startDate) > strtotime($endDate)) {
                http_response_code(400);
                echo json_encode(['error' => 'start_date must be before end_date']);
                return;
            }
            
            $timeOffModel = new TimeOff($conn);
            $timeOffId = $timeOffModel->createTimeOff($userId, $startDate, $endDate, $reason);
            
            if ($timeOffId) {
                // Auto-cancel lessons during time-off
                cancelLessonsDuringTimeOff($userId, $startDate, $endDate);
                
                // Pause recurring lessons
                $calendarService->pauseRecurringLessons($userId, $startDate, $endDate);
                
                echo json_encode(['success' => true, 'time_off_id' => $timeOffId]);
            } else {
                http_response_code(500);
                echo json_encode(['error' => 'Failed to create time-off']);
            }
            break;
            
        case 'validate-notice':
            $teacherId = $input['teacher_id'] ?? null;
            $lessonDateTime = $input['lesson_datetime'] ?? null;
            
            if (!$teacherId || !$lessonDateTime) {
                http_response_code(400);
                echo json_encode(['error' => 'teacher_id and lesson_datetime required']);
                return;
            }
            
            $result = $calendarService->validateBookingNotice($teacherId, $lessonDateTime);
            echo json_encode($result);
            break;
            
        case 'book-recurring':
            if ($userRole !== 'student') {
                http_response_code(403);
                echo json_encode(['error' => 'Only students can book lessons']);
                return;
            }
            
            $teacherId = $input['teacher_id'] ?? null;
            $dayOfWeek = $input['day_of_week'] ?? null;
            $startTime = $input['start_time'] ?? null;
            $endTime = $input['end_time'] ?? null;
            $startDate = $input['start_date'] ?? null;
            $endDate = $input['end_date'] ?? null;
            $frequencyWeeks = $input['frequency_weeks'] ?? 1;
            $numberOfWeeks = $input['number_of_weeks'] ?? 12;
            
            if (!$teacherId || !$dayOfWeek || !$startTime || !$endTime || !$startDate) {
                http_response_code(400);
                echo json_encode(['error' => 'Missing required fields']);
                return;
            }
            
            $seriesData = [
                'day_of_week' => $dayOfWeek,
                'start_time' => $startTime,
                'end_time' => $endTime,
                'start_date' => $startDate,
                'end_date' => $endDate,
                'frequency_weeks' => $frequencyWeeks,
                'number_of_weeks' => $numberOfWeeks
            ];
            
            $result = $calendarService->createRecurringLesson($teacherId, $userId, $seriesData);
            
            if (isset($result['error'])) {
                http_response_code(400);
                echo json_encode($result);
            } else {
                echo json_encode($result);
            }
            break;
            
        default:
            http_response_code(400);
            echo json_encode(['error' => 'Invalid action']);
            break;
    }
}

function handleDelete($action, $userId, $userRole, $tzService, $calendarService) {
    global $conn;
    
    $timeOffId = $_GET['id'] ?? null;
    
    switch ($action) {
        case 'time-off':
            if ($userRole !== 'teacher') {
                http_response_code(403);
                echo json_encode(['error' => 'Only teachers can delete time-off']);
                return;
            }
            
            if (!$timeOffId) {
                http_response_code(400);
                echo json_encode(['error' => 'id required']);
                return;
            }
            
            $timeOffModel = new TimeOff($conn);
            $result = $timeOffModel->deleteTimeOff($timeOffId, $userId);
            
            if ($result) {
                echo json_encode(['success' => true]);
            } else {
                http_response_code(500);
                echo json_encode(['error' => 'Failed to delete time-off']);
            }
            break;
            
        default:
            http_response_code(400);
            echo json_encode(['error' => 'Invalid action']);
            break;
    }
}

function cancelLessonsDuringTimeOff($teacherId, $startDate, $endDate) {
    global $conn;
    
    $stmt = $conn->prepare("
        UPDATE lessons 
        SET status = 'cancelled' 
        WHERE teacher_id = ? 
        AND lesson_date BETWEEN ? AND ? 
        AND status = 'scheduled'
    ");
    $stmt->bind_param("iss", $teacherId, $startDate, $endDate);
    $stmt->execute();
    $stmt->close();
}

