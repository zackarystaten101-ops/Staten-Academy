<?php
/**
 * Admin API: Create Lesson Manually
 * Allows admin to create lessons directly
 */

// Start output buffering to prevent header issues
ob_start();

session_start();
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../app/Views/components/dashboard-functions.php';

// Set headers early, before any output
if (!headers_sent()) {
    header('Content-Type: application/json');
}

// Check admin authentication
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
    ob_end_clean();
    http_response_code(403);
    if (!headers_sent()) {
        header('Content-Type: application/json');
    }
    echo json_encode(['error' => 'Unauthorized']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    ob_end_clean();
    http_response_code(405);
    if (!headers_sent()) {
        header('Content-Type: application/json');
    }
    echo json_encode(['error' => 'Method not allowed']);
    exit();
}

$teacher_id = intval($_POST['teacher_id'] ?? 0);
$student_id = intval($_POST['student_id'] ?? 0);
$lesson_date = $_POST['lesson_date'] ?? '';
$start_time = $_POST['start_time'] ?? $_POST['lesson_time'] ?? ''; // Support both field names
$duration = intval($_POST['duration'] ?? 60); // Duration in minutes
$category = $_POST['category'] ?? 'adults';
$notes = $_POST['notes'] ?? '';

// Calculate end_time from start_time and duration
if (empty($start_time)) {
    ob_end_clean();
    http_response_code(400);
    if (!headers_sent()) {
        header('Content-Type: application/json');
    }
    echo json_encode(['error' => 'Missing start_time or lesson_time field']);
    exit();
}

if (!$teacher_id || !$student_id || !$lesson_date) {
    ob_end_clean();
    http_response_code(400);
    if (!headers_sent()) {
        header('Content-Type: application/json');
    }
    echo json_encode(['error' => 'Missing required fields']);
    exit();
}

// Validate category
if (!in_array($category, ['young_learners', 'adults', 'coding'])) {
    $category = null; // Allow NULL if invalid
}

// Calculate end_time
$start_timestamp = strtotime($lesson_date . ' ' . $start_time);
$end_timestamp = $start_timestamp + ($duration * 60);
$end_time = date('H:i:s', $end_timestamp);

$conn->begin_transaction();
try {
    // Check for conflicts using start_time and end_time
    $conflict_check = $conn->prepare("
        SELECT id FROM lessons 
        WHERE teacher_id = ? 
        AND lesson_date = ? 
        AND status != 'cancelled'
        AND (
            (start_time < ? AND end_time > ?)
            OR (start_time < ? AND end_time > ?)
        )
    ");
    $conflict_check->bind_param("isssss", $teacher_id, $lesson_date, $end_time, $start_time, $start_time, $end_time);
    $conflict_check->execute();
    $conflict_result = $conflict_check->get_result();
    
    if ($conflict_result->num_rows > 0) {
        $conflict_check->close();
        $conn->rollback();
        ob_end_clean();
        http_response_code(409);
        if (!headers_sent()) {
            header('Content-Type: application/json');
        }
        echo json_encode(['error' => 'Time conflict detected. Another lesson exists at this time.']);
        exit();
    }
    $conflict_check->close();
    
    // Create lesson - use start_time and end_time instead of lesson_time and duration
    $is_trial = 0; // Admin-created lessons are not trials
    $wallet_transaction_id = null; // Admin-created lessons don't use wallet/credits
    
    $insert_stmt = $conn->prepare("
        INSERT INTO lessons (teacher_id, student_id, lesson_date, start_time, end_time, status, is_trial, wallet_transaction_id, category, student_notes)
        VALUES (?, ?, ?, ?, ?, 'scheduled', ?, ?, ?, ?)
    ");
    
    if (!$insert_stmt) {
        throw new Exception("Failed to prepare statement: " . $conn->error);
    }
    
    // bind_param: i (teacher_id), i (student_id), s (lesson_date), s (start_time), s (end_time), i (is_trial), i (wallet_transaction_id), s (category), s (notes)
    $bind_result = $insert_stmt->bind_param("iisssisss", $teacher_id, $student_id, $lesson_date, $start_time, $end_time, $is_trial, $wallet_transaction_id, $category, $notes);
    
    if (!$bind_result) {
        throw new Exception("Failed to bind parameters: " . $insert_stmt->error);
    }
    
    if (!$insert_stmt->execute()) {
        error_log("Admin lesson creation failed - SQL Error: " . $insert_stmt->error . " | Teacher ID: $teacher_id | Student ID: $student_id | Date: $lesson_date | Start: $start_time");
        throw new Exception("Failed to create lesson: " . $insert_stmt->error);
    }
    
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
        'start_time' => $start_time,
        'end_time' => $end_time,
        'duration' => $duration,
        'category' => $category
    ]);
    $ip_address = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $admin_id = $_SESSION['user_id'];
    $audit_stmt->bind_param("iiss", $admin_id, $lesson_id, $details, $ip_address);
    $audit_stmt->execute();
    $audit_stmt->close();
    
    $conn->commit();
    
    // Clear any output buffer before sending JSON
    ob_end_clean();
    
    if (!headers_sent()) {
        header('Content-Type: application/json');
    }
    echo json_encode(['success' => true, 'lesson_id' => $lesson_id]);
    
} catch (Exception $e) {
    $conn->rollback();
    error_log("Admin lesson creation error: " . $e->getMessage() . " | Trace: " . $e->getTraceAsString());
    
    // Clear any output buffer before sending error
    ob_end_clean();
    
    http_response_code(500);
    if (!headers_sent()) {
        header('Content-Type: application/json');
    }
    echo json_encode([
        'error' => 'Failed to create lesson',
        'details' => $e->getMessage() // Include actual error for debugging
    ]);
}



