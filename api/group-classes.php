<?php
/**
 * Group Classes API
 * Handles group class management and enrollment
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
require_once __DIR__ . '/../app/Models/GroupClass.php';

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

$groupClassModel = new GroupClass($conn);

// Route requests
switch ($method) {
    case 'GET':
        handleGet($action, $userId, $userRole, $groupClassModel);
        break;
    case 'POST':
        handlePost($action, $userId, $userRole, $groupClassModel);
        break;
    case 'DELETE':
        handleDelete($action, $userId, $userRole, $groupClassModel);
        break;
    default:
        http_response_code(405);
        echo json_encode(['error' => 'Method not allowed']);
        break;
}

function handleGet($action, $userId, $userRole, $groupClassModel) {
    switch ($action) {
        case 'by-track':
            $track = $_GET['track'] ?? null;
            $dateFrom = $_GET['date_from'] ?? null;
            $dateTo = $_GET['date_to'] ?? null;
            
            if (!$track || !in_array($track, ['kids', 'adults', 'coding'])) {
                http_response_code(400);
                echo json_encode(['error' => 'Valid track required (kids, adults, coding)']);
                return;
            }
            
            $classes = $groupClassModel->getTrackClasses($track, $dateFrom, $dateTo);
            echo json_encode(['success' => true, 'classes' => $classes]);
            break;
            
        case 'student':
            $studentId = $_GET['student_id'] ?? $userId;
            $dateFrom = $_GET['date_from'] ?? null;
            $dateTo = $_GET['date_to'] ?? null;
            
            // Students can view their own classes, admins/teachers can view any
            if ($studentId != $userId && $userRole !== 'admin' && $userRole !== 'teacher') {
                http_response_code(403);
                echo json_encode(['error' => 'Access denied']);
                return;
            }
            
            $classes = $groupClassModel->getStudentClasses($studentId, $dateFrom, $dateTo);
            echo json_encode(['success' => true, 'classes' => $classes]);
            break;
            
        case 'details':
            $classId = $_GET['class_id'] ?? null;
            
            if (!$classId) {
                http_response_code(400);
                echo json_encode(['error' => 'class_id required']);
                return;
            }
            
            $class = $groupClassModel->find($classId);
            if ($class) {
                // Get enrolled students
                $students = $groupClassModel->getClassStudents($classId);
                $class['students'] = $students;
                echo json_encode(['success' => true, 'class' => $class]);
            } else {
                http_response_code(404);
                echo json_encode(['error' => 'Class not found']);
            }
            break;
            
        default:
            http_response_code(400);
            echo json_encode(['error' => 'Invalid action']);
            break;
    }
}

function handlePost($action, $userId, $userRole, $groupClassModel) {
    global $conn;
    
    $input = json_decode(file_get_contents('php://input'), true);
    
    switch ($action) {
        case 'create':
            // Only admins and teachers can create classes
            if ($userRole !== 'admin' && $userRole !== 'teacher') {
                http_response_code(403);
                echo json_encode(['error' => 'Only admins and teachers can create group classes']);
                return;
            }
            
            $track = $input['track'] ?? null;
            $teacherId = $input['teacher_id'] ?? ($userRole === 'teacher' ? $userId : null);
            $scheduledDate = $input['scheduled_date'] ?? null;
            $scheduledTime = $input['scheduled_time'] ?? null;
            $duration = $input['duration'] ?? 60;
            $maxStudents = $input['max_students'] ?? 10;
            $title = $input['title'] ?? null;
            $description = $input['description'] ?? null;
            
            if (!$track || !$teacherId || !$scheduledDate || !$scheduledTime) {
                http_response_code(400);
                echo json_encode(['error' => 'track, teacher_id, scheduled_date, and scheduled_time are required']);
                return;
            }
            
            if (!in_array($track, ['kids', 'adults', 'coding'])) {
                http_response_code(400);
                echo json_encode(['error' => 'Invalid track']);
                return;
            }
            
            // Verify teacher exists
            $teacherCheck = $conn->prepare("SELECT id, role FROM users WHERE id = ?");
            $teacherCheck->bind_param("i", $teacherId);
            $teacherCheck->execute();
            $teacher = $teacherCheck->get_result()->fetch_assoc();
            $teacherCheck->close();
            
            if (!$teacher || $teacher['role'] !== 'teacher') {
                http_response_code(400);
                echo json_encode(['error' => 'Invalid teacher']);
                return;
            }
            
            // Create class
            $classData = [
                'track' => $track,
                'teacher_id' => $teacherId,
                'scheduled_date' => $scheduledDate,
                'scheduled_time' => $scheduledTime,
                'duration' => $duration,
                'max_students' => $maxStudents,
                'title' => $title,
                'description' => $description
            ];
            
            $classId = $groupClassModel->createClass($classData);
            
            if ($classId) {
                echo json_encode(['success' => true, 'class_id' => $classId]);
            } else {
                http_response_code(500);
                echo json_encode(['error' => 'Failed to create group class']);
            }
            break;
            
        case 'enroll':
            // Only students can enroll
            if ($userRole !== 'student' && $userRole !== 'new_student') {
                http_response_code(403);
                echo json_encode(['error' => 'Only students can enroll in group classes']);
                return;
            }
            
            $classId = $input['class_id'] ?? null;
            $studentId = $input['student_id'] ?? $userId;
            
            if (!$classId) {
                http_response_code(400);
                echo json_encode(['error' => 'class_id required']);
                return;
            }
            
            // Students can only enroll themselves
            if ($studentId != $userId) {
                http_response_code(403);
                echo json_encode(['error' => 'You can only enroll yourself']);
                return;
            }
            
            $result = $groupClassModel->enrollStudent($classId, $studentId);
            
            if ($result['success']) {
                echo json_encode(['success' => true, 'message' => 'Successfully enrolled in group class']);
            } else {
                http_response_code(400);
                echo json_encode(['error' => $result['error']]);
            }
            break;
            
        case 'unenroll':
            // Only students can unenroll
            if ($userRole !== 'student') {
                http_response_code(403);
                echo json_encode(['error' => 'Only students can unenroll from group classes']);
                return;
            }
            
            $classId = $input['class_id'] ?? null;
            $studentId = $input['student_id'] ?? $userId;
            
            if (!$classId) {
                http_response_code(400);
                echo json_encode(['error' => 'class_id required']);
                return;
            }
            
            // Students can only unenroll themselves
            if ($studentId != $userId) {
                http_response_code(403);
                echo json_encode(['error' => 'You can only unenroll yourself']);
                return;
            }
            
            $result = $groupClassModel->unenrollStudent($classId, $studentId);
            
            if ($result) {
                echo json_encode(['success' => true, 'message' => 'Successfully unenrolled from group class']);
            } else {
                http_response_code(500);
                echo json_encode(['error' => 'Failed to unenroll']);
            }
            break;
            
        case 'update-status':
            // Only admins and teachers can update status
            if ($userRole !== 'admin' && $userRole !== 'teacher') {
                http_response_code(403);
                echo json_encode(['error' => 'Only admins and teachers can update class status']);
                return;
            }
            
            $classId = $input['class_id'] ?? null;
            $status = $input['status'] ?? null;
            
            if (!$classId || !$status) {
                http_response_code(400);
                echo json_encode(['error' => 'class_id and status are required']);
                return;
            }
            
            if (!in_array($status, ['scheduled', 'in_progress', 'completed', 'cancelled'])) {
                http_response_code(400);
                echo json_encode(['error' => 'Invalid status']);
                return;
            }
            
            $result = $groupClassModel->updateStatus($classId, $status);
            
            if ($result) {
                echo json_encode(['success' => true, 'message' => 'Status updated']);
            } else {
                http_response_code(500);
                echo json_encode(['error' => 'Failed to update status']);
            }
            break;
            
        default:
            http_response_code(400);
            echo json_encode(['error' => 'Invalid action']);
            break;
    }
}

function handleDelete($action, $userId, $userRole, $groupClassModel) {
    // Only admins can delete classes
    if ($userRole !== 'admin') {
        http_response_code(403);
        echo json_encode(['error' => 'Only admins can delete group classes']);
        return;
    }
    
    $classId = $_GET['class_id'] ?? null;
    
    if (!$classId) {
        http_response_code(400);
        echo json_encode(['error' => 'class_id required']);
        return;
    }
    
    // Delete class (cascade will handle enrollments)
    global $conn;
    $stmt = $conn->prepare("DELETE FROM group_classes WHERE id = ?");
    $stmt->bind_param("i", $classId);
    $result = $stmt->execute();
    $stmt->close();
    
    if ($result) {
        echo json_encode(['success' => true, 'message' => 'Class deleted']);
    } else {
        http_response_code(500);
        echo json_encode(['error' => 'Failed to delete class']);
    }
}

