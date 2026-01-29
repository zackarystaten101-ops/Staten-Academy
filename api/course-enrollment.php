<?php
/**
 * Course Enrollment API
 * Handles one-click course enrollment for students
 */

session_start();
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../app/Models/CourseEnrollment.php';
require_once __DIR__ . '/../app/Models/Course.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Not authenticated']);
    exit;
}

$user_id = $_SESSION['user_id'];
$user_role = $_SESSION['user_role'] ?? '';

// Only students can enroll
if ($user_role !== 'student' && $user_role !== 'new_student') {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Only students can enroll in courses']);
    exit;
}

$action = $_GET['action'] ?? $_POST['action'] ?? '';

switch ($action) {
    case 'enroll':
        enrollInCourse($conn, $user_id);
        break;
    
    case 'check':
        checkEnrollment($conn, $user_id);
        break;
    
    default:
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Invalid action']);
}

/**
 * Enroll user in a course
 */
function enrollInCourse($conn, $user_id) {
    $course_id = isset($_POST['course_id']) ? (int)$_POST['course_id'] : (isset($_GET['course_id']) ? (int)$_GET['course_id'] : 0);
    
    if (!$course_id) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Course ID is required']);
        exit;
    }
    
    // Verify course exists and is active
    $courseModel = new Course($conn);
    $course = $courseModel->getById($course_id);
    
    if (!$course || !$course['is_active']) {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Course not found or not available']);
        exit;
    }
    
    // Check enrollment
    $enrollmentModel = new CourseEnrollment($conn);
    
    // Check if already enrolled
    if ($enrollmentModel->isEnrolled($user_id, $course_id)) {
        echo json_encode([
            'success' => true,
            'already_enrolled' => true,
            'message' => 'You are already enrolled in this course',
            'course' => [
                'id' => $course['id'],
                'title' => $course['title']
            ]
        ]);
        exit;
    }
    
    // Check if user has access via subscription plan
    $accessible = $enrollmentModel->getAccessibleCourses($user_id);
    $has_access = false;
    while ($accessible_course = $accessible->fetch_assoc()) {
        if ($accessible_course['id'] == $course_id) {
            $has_access = true;
            break;
        }
    }
    
    if (!$has_access) {
        // Check if course is free or available for enrollment
        $is_free = empty($course['price']) || $course['price'] == 0;
        
        if (!$is_free) {
            http_response_code(403);
            echo json_encode([
                'success' => false,
                'error' => 'This course is not included in your subscription plan',
                'requires_upgrade' => true
            ]);
            exit;
        }
    }
    
    // Enroll user
    $enrolled = $enrollmentModel->enroll($user_id, $course_id, 'manual', null, null);
    
    if ($enrolled) {
        // Initialize progress tracking
        $progress_sql = "INSERT INTO user_course_progress (user_id, course_id, progress_percentage, last_accessed_at) 
                        VALUES (?, ?, 0, NOW())
                        ON DUPLICATE KEY UPDATE last_accessed_at = NOW()";
        $progress_stmt = $conn->prepare($progress_sql);
        $progress_stmt->bind_param("ii", $user_id, $course_id);
        $progress_stmt->execute();
        $progress_stmt->close();
        
        echo json_encode([
            'success' => true,
            'message' => 'Successfully enrolled in course!',
            'course' => [
                'id' => $course['id'],
                'title' => $course['title'],
                'thumbnail_url' => $course['thumbnail_url'] ?? null
            ]
        ]);
    } else {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'Failed to enroll in course. Please try again.']);
    }
}

/**
 * Check enrollment status for a course
 */
function checkEnrollment($conn, $user_id) {
    $course_id = isset($_POST['course_id']) ? (int)$_POST['course_id'] : (isset($_GET['course_id']) ? (int)$_GET['course_id'] : 0);
    
    if (!$course_id) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Course ID is required']);
        exit;
    }
    
    $enrollmentModel = new CourseEnrollment($conn);
    $is_enrolled = $enrollmentModel->isEnrolled($user_id, $course_id);
    
    // Get progress if enrolled
    $progress = 0;
    if ($is_enrolled) {
        $progress_sql = "SELECT progress_percentage FROM user_course_progress WHERE user_id = ? AND course_id = ?";
        $progress_stmt = $conn->prepare($progress_sql);
        $progress_stmt->bind_param("ii", $user_id, $course_id);
        $progress_stmt->execute();
        $progress_result = $progress_stmt->get_result();
        if ($progress_row = $progress_result->fetch_assoc()) {
            $progress = (float)$progress_row['progress_percentage'];
        }
        $progress_stmt->close();
    }
    
    echo json_encode([
        'success' => true,
        'enrolled' => $is_enrolled,
        'progress' => $progress
    ]);
}
