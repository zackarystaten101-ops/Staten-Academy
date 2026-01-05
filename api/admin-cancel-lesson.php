<?php
/**
 * Admin API: Cancel Lesson
 * Allows admin to cancel lessons
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

$lesson_id = intval($_POST['lesson_id'] ?? 0);

if (!$lesson_id) {
    http_response_code(400);
    echo json_encode(['error' => 'Missing lesson ID']);
    exit();
}

$conn->begin_transaction();
try {
    // Get lesson details
    $lesson_stmt = $conn->prepare("SELECT * FROM lessons WHERE id = ?");
    $lesson_stmt->bind_param("i", $lesson_id);
    $lesson_stmt->execute();
    $lesson_result = $lesson_stmt->get_result();
    $lesson = $lesson_result->fetch_assoc();
    $lesson_stmt->close();
    
    if (!$lesson) {
        $conn->rollback();
        http_response_code(404);
        echo json_encode(['error' => 'Lesson not found']);
        exit();
    }
    
    // Cancel lesson
    $update_stmt = $conn->prepare("UPDATE lessons SET status = 'cancelled', updated_at = NOW() WHERE id = ?");
    $update_stmt->bind_param("i", $lesson_id);
    $update_stmt->execute();
    $update_stmt->close();
    
    // Log to audit log
    $audit_sql = "INSERT INTO admin_audit_log (admin_id, action, target_type, target_id, details, ip_address) 
                 VALUES (?, 'cancel_lesson', 'lesson', ?, ?, ?)";
    $audit_stmt = $conn->prepare($audit_sql);
    $details = json_encode([
        'lesson_id' => $lesson_id,
        'teacher_id' => $lesson['teacher_id'],
        'student_id' => $lesson['student_id'],
        'lesson_date' => $lesson['lesson_date'],
        'start_time' => $lesson['start_time'] ?? $lesson['lesson_time'] ?? null,
        'end_time' => $lesson['end_time'] ?? null
    ]);
    $ip_address = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $admin_id = $_SESSION['user_id'];
    $audit_stmt->bind_param("iiss", $admin_id, $lesson_id, $details, $ip_address);
    $audit_stmt->execute();
    $audit_stmt->close();
    
    $conn->commit();
    
    header('Content-Type: application/json');
    echo json_encode(['success' => true]);
    
} catch (Exception $e) {
    $conn->rollback();
    error_log("Error canceling lesson: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Failed to cancel lesson']);
}







