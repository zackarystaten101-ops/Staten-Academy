<?php
/**
 * Video Sessions API
 * Handles video session creation, joining, and management for classroom
 */

session_start();
require_once __DIR__ . '/../db.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit();
}

$user_id = $_SESSION['user_id'];
$user_role = $_SESSION['user_role'] ?? 'student';
$action = $_GET['action'] ?? $_POST['action'] ?? '';

switch ($action) {
    case 'get-or-create':
        getOrCreateSession($conn, $user_id, $user_role);
        break;
    
    case 'create':
        createSession($conn, $user_id, $user_role);
        break;
    
    case 'active':
        checkActiveSession($conn, $user_id);
        break;
    
    case 'get-state':
        getWhiteboardState($conn, $user_id);
        break;
    
    case 'save-state':
        saveWhiteboardState($conn, $user_id);
        break;
    
    case 'end':
        endSession($conn, $user_id, $user_role);
        break;
    
    default:
        http_response_code(400);
        echo json_encode(['error' => 'Invalid action']);
}

/**
 * Get or create a session for a lesson
 */
function getOrCreateSession($conn, $user_id, $user_role) {
    $lesson_id = isset($_GET['lessonId']) ? (int)$_GET['lessonId'] : 0;
    
    if (!$lesson_id) {
        http_response_code(400);
        echo json_encode(['error' => 'Lesson ID required']);
        return;
    }
    
    // Verify user has access to this lesson
    if ($user_role === 'student') {
        $stmt = $conn->prepare("SELECT id, teacher_id, student_id FROM lessons WHERE id = ? AND student_id = ?");
    } else if ($user_role === 'teacher') {
        $stmt = $conn->prepare("SELECT id, teacher_id, student_id FROM lessons WHERE id = ? AND teacher_id = ?");
    } else {
        http_response_code(403);
        echo json_encode(['error' => 'Invalid role']);
        return;
    }
    
    $stmt->bind_param("ii", $lesson_id, $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $lesson = $result->fetch_assoc();
    $stmt->close();
    
    if (!$lesson) {
        http_response_code(403);
        echo json_encode(['error' => 'Access denied to this lesson']);
        return;
    }
    
    // Check if session already exists
    $stmt = $conn->prepare("SELECT session_id, status FROM video_sessions WHERE lesson_id = ? AND status = 'active' ORDER BY created_at DESC LIMIT 1");
    $stmt->bind_param("i", $lesson_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $existing_session = $result->fetch_assoc();
    $stmt->close();
    
    if ($existing_session) {
        echo json_encode([
            'success' => true,
            'session' => [
                'session_id' => $existing_session['session_id'],
                'status' => $existing_session['status']
            ]
        ]);
        return;
    }
    
    // Create new session
    $session_id = 'session_' . $lesson_id . '_' . time() . '_' . bin2hex(random_bytes(4));
    
    $stmt = $conn->prepare("INSERT INTO video_sessions (session_id, lesson_id, teacher_id, student_id, status) VALUES (?, ?, ?, ?, 'active')");
    $stmt->bind_param("siii", $session_id, $lesson_id, $lesson['teacher_id'], $lesson['student_id']);
    
    if ($stmt->execute()) {
        $stmt->close();
        echo json_encode([
            'success' => true,
            'session' => [
                'session_id' => $session_id,
                'status' => 'active'
            ]
        ]);
    } else {
        $stmt->close();
        http_response_code(500);
        echo json_encode(['error' => 'Failed to create session']);
    }
}

/**
 * Create a test session for teachers
 */
function createSession($conn, $user_id, $user_role) {
    if ($user_role !== 'teacher') {
        http_response_code(403);
        echo json_encode(['error' => 'Only teachers can create test sessions']);
        return;
    }
    
    $is_test = isset($_POST['testMode']) && $_POST['testMode'] === 'true';
    $lesson_id = isset($_POST['lessonId']) ? (int)$_POST['lessonId'] : null;
    
    if ($is_test) {
        // Create test session
        $session_id = 'test_teacher_' . $user_id . '_' . time() . '_' . bin2hex(random_bytes(4));
        
        $stmt = $conn->prepare("INSERT INTO video_sessions (session_id, lesson_id, teacher_id, student_id, status, is_test_session) VALUES (?, NULL, ?, ?, 'active', TRUE)");
        $stmt->bind_param("sii", $session_id, $user_id, $user_id);
    } else if ($lesson_id) {
        // Create session for specific lesson
        $stmt = $conn->prepare("SELECT teacher_id, student_id FROM lessons WHERE id = ?");
        $stmt->bind_param("i", $lesson_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $lesson = $result->fetch_assoc();
        $stmt->close();
        
        if (!$lesson || $lesson['teacher_id'] != $user_id) {
            http_response_code(403);
            echo json_encode(['error' => 'Access denied']);
            return;
        }
        
        $session_id = 'session_' . $lesson_id . '_' . time() . '_' . bin2hex(random_bytes(4));
        $stmt = $conn->prepare("INSERT INTO video_sessions (session_id, lesson_id, teacher_id, student_id, status) VALUES (?, ?, ?, ?, 'active')");
        $stmt->bind_param("siii", $session_id, $lesson_id, $lesson['teacher_id'], $lesson['student_id']);
    } else {
        http_response_code(400);
        echo json_encode(['error' => 'Lesson ID or test mode required']);
        return;
    }
    
    if ($stmt->execute()) {
        $stmt->close();
        echo json_encode([
            'success' => true,
            'session' => [
                'session_id' => $session_id,
                'status' => 'active'
            ]
        ]);
    } else {
        $stmt->close();
        http_response_code(500);
        echo json_encode(['error' => 'Failed to create session']);
    }
}

/**
 * Check if session is active
 */
function checkActiveSession($conn, $user_id) {
    $session_id = isset($_GET['sessionId']) ? $_GET['sessionId'] : '';
    
    if (!$session_id) {
        http_response_code(400);
        echo json_encode(['error' => 'Session ID required']);
        return;
    }
    
    $stmt = $conn->prepare("SELECT status, teacher_id, student_id, lesson_id, is_test_session FROM video_sessions WHERE session_id = ? AND (teacher_id = ? OR student_id = ?)");
    $stmt->bind_param("sii", $session_id, $user_id, $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $session = $result->fetch_assoc();
    $stmt->close();
    
    if (!$session) {
        http_response_code(404);
        echo json_encode(['error' => 'Session not found']);
        return;
    }
    
    echo json_encode([
        'success' => true,
        'active' => $session['status'] === 'active',
        'session' => [
            'session_id' => $session_id,
            'teacher_id' => $session['teacher_id'],
            'student_id' => $session['student_id'],
            'lesson_id' => $session['lesson_id'],
            'is_test_session' => (bool)$session['is_test_session'],
            'status' => $session['status']
        ]
    ]);
}

/**
 * Get whiteboard state
 */
function getWhiteboardState($conn, $user_id) {
    $session_id = isset($_GET['sessionId']) ? $_GET['sessionId'] : '';
    
    if (!$session_id) {
        http_response_code(400);
        echo json_encode(['error' => 'Session ID required']);
        return;
    }
    
    // Verify access
    $stmt = $conn->prepare("SELECT id FROM video_sessions WHERE session_id = ? AND (teacher_id = ? OR student_id = ?)");
    $stmt->bind_param("sii", $session_id, $user_id, $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows === 0) {
        $stmt->close();
        http_response_code(403);
        echo json_encode(['error' => 'Access denied']);
        return;
    }
    $stmt->close();
    
    // For now, return empty state (can be enhanced with actual storage)
    echo json_encode([
        'success' => true,
        'state' => null
    ]);
}

/**
 * Save whiteboard state
 */
function saveWhiteboardState($conn, $user_id) {
    $session_id = isset($_POST['sessionId']) ? $_POST['sessionId'] : '';
    $state = isset($_POST['state']) ? $_POST['state'] : '';
    
    if (!$session_id) {
        http_response_code(400);
        echo json_encode(['error' => 'Session ID required']);
        return;
    }
    
    // Verify access
    $stmt = $conn->prepare("SELECT id FROM video_sessions WHERE session_id = ? AND (teacher_id = ? OR student_id = ?)");
    $stmt->bind_param("sii", $session_id, $user_id, $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows === 0) {
        $stmt->close();
        http_response_code(403);
        echo json_encode(['error' => 'Access denied']);
        return;
    }
    $stmt->close();
    
    // For now, just acknowledge (can be enhanced with actual storage)
    echo json_encode([
        'success' => true,
        'message' => 'State saved'
    ]);
}

/**
 * End a session
 */
function endSession($conn, $user_id, $user_role) {
    $session_id = isset($_POST['sessionId']) ? $_POST['sessionId'] : '';
    
    if (!$session_id) {
        http_response_code(400);
        echo json_encode(['error' => 'Session ID required']);
        return;
    }
    
    // Verify access (teachers can end any session, students can end their own)
    if ($user_role === 'teacher') {
        $stmt = $conn->prepare("UPDATE video_sessions SET status = 'ended', ended_at = NOW() WHERE session_id = ? AND teacher_id = ?");
        $stmt->bind_param("si", $session_id, $user_id);
    } else {
        $stmt = $conn->prepare("UPDATE video_sessions SET status = 'ended', ended_at = NOW() WHERE session_id = ? AND (teacher_id = ? OR student_id = ?)");
        $stmt->bind_param("sii", $session_id, $user_id, $user_id);
    }
    
    if ($stmt->execute()) {
        $stmt->close();
        echo json_encode([
            'success' => true,
            'message' => 'Session ended'
        ]);
    } else {
        $stmt->close();
        http_response_code(500);
        echo json_encode(['error' => 'Failed to end session']);
    }
}
