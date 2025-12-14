<?php
/**
 * Teacher Assignments API
 * Handles teacher-student assignment management (admin only)
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
require_once __DIR__ . '/../app/Models/TeacherAssignment.php';

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

$assignmentModel = new TeacherAssignment($conn);

// Route requests
switch ($method) {
    case 'GET':
        handleGet($action, $userId, $userRole, $assignmentModel);
        break;
    case 'POST':
        handlePost($action, $userId, $userRole, $assignmentModel);
        break;
    default:
        http_response_code(405);
        echo json_encode(['error' => 'Method not allowed']);
        break;
}

function handleGet($action, $userId, $userRole, $assignmentModel) {
    switch ($action) {
        case 'student':
            $studentId = $_GET['student_id'] ?? $userId;
            
            // Students can view their own assignment, admins can view any
            if ($studentId != $userId && $userRole !== 'admin' && $userRole !== 'teacher') {
                http_response_code(403);
                echo json_encode(['error' => 'Access denied']);
                return;
            }
            
            $assignment = $assignmentModel->getStudentTeacher($studentId);
            if ($assignment) {
                echo json_encode(['success' => true, 'assignment' => $assignment]);
            } else {
                echo json_encode(['success' => true, 'assignment' => null]);
            }
            break;
            
        case 'teacher':
            $teacherId = $_GET['teacher_id'] ?? $userId;
            
            // Teachers can view their own students, admins can view any
            if ($teacherId != $userId && $userRole !== 'admin') {
                http_response_code(403);
                echo json_encode(['error' => 'Access denied']);
                return;
            }
            
            $students = $assignmentModel->getTeacherStudents($teacherId);
            echo json_encode(['success' => true, 'students' => $students]);
            break;
            
        case 'history':
            $studentId = $_GET['student_id'] ?? null;
            
            if (!$studentId) {
                http_response_code(400);
                echo json_encode(['error' => 'student_id required']);
                return;
            }
            
            // Only admins and the student themselves can view history
            if ($studentId != $userId && $userRole !== 'admin') {
                http_response_code(403);
                echo json_encode(['error' => 'Access denied']);
                return;
            }
            
            $history = $assignmentModel->getStudentAssignmentHistory($studentId);
            echo json_encode(['success' => true, 'history' => $history]);
            break;
            
        default:
            http_response_code(400);
            echo json_encode(['error' => 'Invalid action']);
            break;
    }
}

function handlePost($action, $userId, $userRole, $assignmentModel) {
    global $conn;
    
    // Only admins can assign teachers
    if ($userRole !== 'admin') {
        http_response_code(403);
        echo json_encode(['error' => 'Only admins can assign teachers']);
        return;
    }
    
    $input = json_decode(file_get_contents('php://input'), true);
    
    switch ($action) {
        case 'assign':
            $studentId = $input['student_id'] ?? null;
            $teacherId = $input['teacher_id'] ?? null;
            $track = $input['track'] ?? null;
            $notes = $input['notes'] ?? null;
            
            if (!$studentId || !$teacherId || !$track) {
                http_response_code(400);
                echo json_encode(['error' => 'student_id, teacher_id, and track are required']);
                return;
            }
            
            if (!in_array($track, ['kids', 'adults', 'coding'])) {
                http_response_code(400);
                echo json_encode(['error' => 'Invalid track']);
                return;
            }
            
            // Verify student exists and is a student
            $studentCheck = $conn->prepare("SELECT id, role FROM users WHERE id = ?");
            $studentCheck->bind_param("i", $studentId);
            $studentCheck->execute();
            $student = $studentCheck->get_result()->fetch_assoc();
            $studentCheck->close();
            
            if (!$student || !in_array($student['role'], ['student', 'new_student'])) {
                http_response_code(400);
                echo json_encode(['error' => 'Invalid student']);
                return;
            }
            
            // Verify teacher exists and is a teacher
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
            
            // Assign teacher
            $assignmentId = $assignmentModel->assignTeacher($studentId, $teacherId, $userId, $track, $notes);
            
            if ($assignmentId) {
                echo json_encode(['success' => true, 'assignment_id' => $assignmentId]);
            } else {
                http_response_code(500);
                echo json_encode(['error' => 'Failed to assign teacher']);
            }
            break;
            
        case 'transfer':
            $studentId = $input['student_id'] ?? null;
            $newTeacherId = $input['teacher_id'] ?? null;
            $notes = $input['notes'] ?? null;
            
            if (!$studentId || !$newTeacherId) {
                http_response_code(400);
                echo json_encode(['error' => 'student_id and teacher_id are required']);
                return;
            }
            
            // Verify new teacher exists and is a teacher
            $teacherCheck = $conn->prepare("SELECT id, role FROM users WHERE id = ?");
            $teacherCheck->bind_param("i", $newTeacherId);
            $teacherCheck->execute();
            $teacher = $teacherCheck->get_result()->fetch_assoc();
            $teacherCheck->close();
            
            if (!$teacher || $teacher['role'] !== 'teacher') {
                http_response_code(400);
                echo json_encode(['error' => 'Invalid teacher']);
                return;
            }
            
            // Transfer assignment
            $assignmentId = $assignmentModel->transferAssignment($studentId, $newTeacherId, $userId, $notes);
            
            if ($assignmentId) {
                echo json_encode(['success' => true, 'assignment_id' => $assignmentId]);
            } else {
                http_response_code(500);
                echo json_encode(['error' => 'Failed to transfer assignment']);
            }
            break;
            
        default:
            http_response_code(400);
            echo json_encode(['error' => 'Invalid action']);
            break;
    }
}





