<?php
/**
 * Lesson Confirmation API
 * Handles attendance confirmation for completed lessons
 */

session_start();
require_once '../db.php';
require_once '../app/Views/components/dashboard-functions.php';

header('Content-Type: application/json');

// Check authentication
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$user_id = $_SESSION['user_id'];
$user_role = $_SESSION['user_role'] ?? 'student';
$action = $_GET['action'] ?? $_POST['action'] ?? '';

switch ($action) {
    case 'confirm':
        confirmAttendance($conn, $user_id, $user_role);
        break;
        
    case 'get_pending':
        getPendingConfirmations($conn, $user_id, $user_role);
        break;
        
    case 'add_note':
        addCompletionNote($conn, $user_id, $user_role);
        break;
        
    default:
        echo json_encode(['error' => 'Invalid action']);
}

/**
 * Confirm attendance for a lesson
 */
function confirmAttendance($conn, $user_id, $user_role) {
    $lesson_id = isset($_POST['lesson_id']) ? (int)$_POST['lesson_id'] : 0;
    $attendance_status = isset($_POST['attendance_status']) ? $_POST['attendance_status'] : 'attended';
    $student_notes = isset($_POST['student_notes']) ? trim($_POST['student_notes']) : '';
    
    if (!$lesson_id) {
        echo json_encode(['error' => 'Missing lesson ID']);
        return;
    }
    
    // Validate attendance status
    if (!in_array($attendance_status, ['attended', 'no_show', 'cancelled'])) {
        echo json_encode(['error' => 'Invalid attendance status']);
        return;
    }
    
    // Get lesson details and verify ownership
    $stmt = $conn->prepare("
        SELECT l.*, u.name as teacher_name, u.email as teacher_email
        FROM lessons l
        JOIN users u ON l.teacher_id = u.id
        WHERE l.id = ? AND l.student_id = ?
    ");
    $stmt->bind_param("ii", $lesson_id, $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        echo json_encode(['error' => 'Lesson not found or access denied']);
        $stmt->close();
        return;
    }
    
    $lesson = $result->fetch_assoc();
    $stmt->close();
    
    // Check if lesson is in the past
    $lesson_datetime = strtotime($lesson['lesson_date'] . ' ' . $lesson['start_time']);
    if ($lesson_datetime > time()) {
        echo json_encode(['error' => 'Cannot confirm attendance for future lessons']);
        return;
    }
    
    // Check if already confirmed
    if ($lesson['status'] === 'completed' && !empty($lesson['attendance_status'])) {
        echo json_encode(['error' => 'Attendance already confirmed']);
        return;
    }
    
    // Update lesson with attendance status
    $update_stmt = $conn->prepare("
        UPDATE lessons 
        SET status = 'completed',
            attendance_status = ?,
            student_notes = ?,
            confirmed_at = NOW(),
            updated_at = NOW()
        WHERE id = ?
    ");
    $update_stmt->bind_param("ssi", $attendance_status, $student_notes, $lesson_id);
    
    if (!$update_stmt->execute()) {
        echo json_encode(['error' => 'Failed to confirm attendance: ' . $update_stmt->error]);
        $update_stmt->close();
        return;
    }
    
    $update_stmt->close();
    
    // Create notification for teacher
    if (function_exists('createNotification')) {
        $message = $attendance_status === 'attended' 
            ? "Student confirmed attendance for lesson on " . date('M d, Y', strtotime($lesson['lesson_date']))
            : "Student marked lesson as " . $attendance_status . " for " . date('M d, Y', strtotime($lesson['lesson_date']));
        
        createNotification($conn, $lesson['teacher_id'], 'lesson_confirmation', 'Lesson Confirmed', 
            $message, 'teacher-dashboard.php#students');
    }
    
    // Update student stats
    if ($attendance_status === 'attended') {
        $duration = (strtotime($lesson['end_time']) - strtotime($lesson['start_time'])) / 3600;
        $stats_stmt = $conn->prepare("
            UPDATE users 
            SET total_lessons = total_lessons + 1,
                hours_taught = hours_taught + ?
            WHERE id = ?
        ");
        $stats_stmt->bind_param("di", $duration, $user_id);
        $stats_stmt->execute();
        $stats_stmt->close();
    }
    
    echo json_encode([
        'success' => true,
        'message' => 'Attendance confirmed successfully'
    ]);
}

/**
 * Get pending confirmations for student
 */
function getPendingConfirmations($conn, $user_id, $user_role) {
    if ($user_role !== 'student' && $user_role !== 'new_student') {
        echo json_encode(['error' => 'Unauthorized']);
        return;
    }
    
    $stmt = $conn->prepare("
        SELECT l.*, u.name as teacher_name, u.profile_pic as teacher_pic
        FROM lessons l
        JOIN users u ON l.teacher_id = u.id
        WHERE l.student_id = ?
        AND l.status = 'scheduled'
        AND CONCAT(l.lesson_date, ' ', l.end_time) < NOW()
        AND (l.attendance_status IS NULL OR l.attendance_status = '')
        ORDER BY l.lesson_date DESC, l.start_time DESC
        LIMIT 10
    ");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $lessons = [];
    while ($row = $result->fetch_assoc()) {
        $row['lesson_datetime'] = $row['lesson_date'] . ' ' . $row['start_time'];
        $row['formatted_date'] = date('M d, Y', strtotime($row['lesson_date']));
        $row['formatted_time'] = date('g:i A', strtotime($row['start_time'])) . ' - ' . date('g:i A', strtotime($row['end_time']));
        $lessons[] = $row;
    }
    
    $stmt->close();
    echo json_encode(['lessons' => $lessons]);
}

/**
 * Add completion note (for teachers)
 */
function addCompletionNote($conn, $user_id, $user_role) {
    if ($user_role !== 'teacher') {
        echo json_encode(['error' => 'Unauthorized']);
        return;
    }
    
    $lesson_id = isset($_POST['lesson_id']) ? (int)$_POST['lesson_id'] : 0;
    $completion_note = isset($_POST['completion_note']) ? trim($_POST['completion_note']) : '';
    
    if (!$lesson_id) {
        echo json_encode(['error' => 'Missing lesson ID']);
        return;
    }
    
    // Verify lesson belongs to teacher
    $stmt = $conn->prepare("SELECT id FROM lessons WHERE id = ? AND teacher_id = ?");
    $stmt->bind_param("ii", $lesson_id, $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        echo json_encode(['error' => 'Lesson not found or access denied']);
        $stmt->close();
        return;
    }
    
    $stmt->close();
    
    // Update lesson with completion note
    $update_stmt = $conn->prepare("
        UPDATE lessons 
        SET completion_notes = ?,
            updated_at = NOW()
        WHERE id = ?
    ");
    $update_stmt->bind_param("si", $completion_note, $lesson_id);
    
    if (!$update_stmt->execute()) {
        echo json_encode(['error' => 'Failed to save note: ' . $update_stmt->error]);
        $update_stmt->close();
        return;
    }
    
    $update_stmt->close();
    
    echo json_encode([
        'success' => true,
        'message' => 'Note saved successfully'
    ]);
}









