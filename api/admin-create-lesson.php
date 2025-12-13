<?php
/**
 * Admin API: Create Lesson Manually
 * Allows admin to create lessons directly
 */

session_start();
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../app/Views/components/dashboard-functions.php';

// Check admin authentication
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
    http_response_code(403);
    echo json_encode(['error' => 'Unauthorized']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit();
}

$teacher_id = intval($_POST['teacher_id'] ?? 0);
$student_id = intval($_POST['student_id'] ?? 0);
$lesson_date = $_POST['lesson_date'] ?? '';
$lesson_time = $_POST['lesson_time'] ?? '';
$duration = intval($_POST['duration'] ?? 60);
$category = $_POST['category'] ?? 'adults';
$notes = $_POST['notes'] ?? '';

if (!$teacher_id || !$student_id || !$lesson_date || !$lesson_time) {
    http_response_code(400);
    echo json_encode(['error' => 'Missing required fields']);
    exit();
}

$conn->begin_transaction();
try {
    // Check for conflicts
    $conflict_check = $conn->prepare("
        SELECT id FROM lessons 
        WHERE teacher_id = ? 
        AND lesson_date = ? 
        AND status != 'cancelled'
        AND (
            (lesson_time <= ? AND ADDTIME(lesson_time, SEC_TO_TIME(? * 60)) > ?)
            OR (? <= lesson_time AND ADDTIME(?, SEC_TO_TIME(? * 60)) > lesson_time)
        )
    ");
    $conflict_check->bind_param("isssssss", $teacher_id, $lesson_date, $lesson_time, $duration, $lesson_time, $lesson_time, $lesson_time, $duration);
    $conflict_check->execute();
    $conflict_result = $conflict_check->get_result();
    
    if ($conflict_result->num_rows > 0) {
        $conflict_check->close();
        $conn->rollback();
        http_response_code(409);
        echo json_encode(['error' => 'Time conflict detected. Another lesson exists at this time.']);
        exit();
    }
    $conflict_check->close();
    
    // Create lesson
    $insert_stmt = $conn->prepare("
        INSERT INTO lessons (teacher_id, student_id, lesson_date, lesson_time, duration, category, notes, status, created_at)
        VALUES (?, ?, ?, ?, ?, ?, ?, 'scheduled', NOW())
    ");
    $insert_stmt->bind_param("iississ", $teacher_id, $student_id, $lesson_date, $lesson_time, $duration, $category, $notes);
    $insert_stmt->execute();
    $lesson_id = $conn->insert_id;
    $insert_stmt->close();
    
    // Log to audit log
    $audit_sql = "INSERT INTO admin_audit_log (admin_id, action, target_type, target_id, details, ip_address) 
                 VALUES (?, 'create_lesson', 'lesson', ?, ?, ?)";
    $audit_stmt = $conn->prepare($audit_sql);
    $details = json_encode([
        'teacher_id' => $teacher_id,
        'student_id' => $student_id,
        'lesson_date' => $lesson_date,
        'lesson_time' => $lesson_time,
        'duration' => $duration,
        'category' => $category
    ]);
    $ip_address = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $admin_id = $_SESSION['user_id'];
    $audit_stmt->bind_param("iiss", $admin_id, $lesson_id, $details, $ip_address);
    $audit_stmt->execute();
    $audit_stmt->close();
    
    $conn->commit();
    
    header('Content-Type: application/json');
    echo json_encode(['success' => true, 'lesson_id' => $lesson_id]);
    
} catch (Exception $e) {
    $conn->rollback();
    error_log("Error creating lesson: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Failed to create lesson']);
}


