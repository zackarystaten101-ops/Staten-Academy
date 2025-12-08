<?php
session_start();
require_once __DIR__ . '/../db.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit();
}

$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';

switch ($method) {
    case 'GET':
        handleGet($conn, $action);
        break;
    case 'POST':
        handlePost($conn);
        break;
    case 'PUT':
        handlePut($conn);
        break;
    default:
        http_response_code(405);
        echo json_encode(['error' => 'Method not allowed']);
        break;
}

function handleGet($conn, $action) {
    $userId = $_SESSION['user_id'];
    
    switch ($action) {
        case 'get-state':
            getWhiteboardState($conn, $userId);
            break;
        case 'active':
            getActiveSession($conn, $userId);
            break;
        case 'get-or-create':
            getOrCreateSessionForLesson($conn, $userId);
            break;
        default:
            http_response_code(400);
            echo json_encode(['error' => 'Invalid action']);
            break;
    }
}

function getActiveSession($conn, $userId) {
    $sessionId = $_GET['sessionId'] ?? '';
    $lessonId = $_GET['lessonId'] ?? '';

    if (empty($sessionId) && empty($lessonId)) {
        http_response_code(400);
        echo json_encode(['error' => 'sessionId or lessonId required']);
        return;
    }

    $sql = "SELECT * FROM video_sessions WHERE status = 'active'";
    $params = [];
    $types = "";

    if (!empty($sessionId)) {
        $sql .= " AND session_id = ?";
        $params[] = $sessionId;
        $types .= "s";
    } else {
        $sql .= " AND lesson_id = ?";
        $params[] = $lessonId;
        $types .= "i";
    }

    $sql .= " AND (teacher_id = ? OR student_id = ?)";
    $params[] = $userId;
    $params[] = $userId;
    $types .= "ii";

    $stmt = $conn->prepare($sql);
    if (!empty($types)) {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $result = $stmt->get_result();
    $session = $result->fetch_assoc();
    $stmt->close();

    if ($session) {
        echo json_encode(['session' => $session]);
    } else {
        http_response_code(404);
        echo json_encode(['error' => 'Session not found']);
    }
}

function getWhiteboardState($conn, $userId) {
    $sessionId = $_GET['sessionId'] ?? '';

    if (empty($sessionId)) {
        http_response_code(400);
        echo json_encode(['error' => 'sessionId required']);
        return;
    }

    // Verify session access
    $stmt = $conn->prepare("
        SELECT id FROM video_sessions 
        WHERE session_id = ? 
        AND (teacher_id = ? OR student_id = ?)
        AND status = 'active'
    ");
    $stmt->bind_param("sii", $sessionId, $userId, $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        $stmt->close();
        http_response_code(403);
        echo json_encode(['error' => 'Access denied']);
        return;
    }
    $stmt->close();

    // Get whiteboard state
    $stmt = $conn->prepare("
        SELECT state_data, version 
        FROM whiteboard_states 
        WHERE session_id = ? 
        ORDER BY last_updated DESC 
        LIMIT 1
    ");
    $stmt->bind_param("s", $sessionId);
    $stmt->execute();
    $result = $stmt->get_result();
    $state = $result->fetch_assoc();
    $stmt->close();

    if ($state) {
        echo json_encode(['state' => json_decode($state['state_data']), 'version' => $state['version']]);
    } else {
        echo json_encode(['state' => null, 'version' => 0]);
    }
}

function handlePost($conn) {
    $userId = $_SESSION['user_id'];
    $data = json_decode(file_get_contents('php://input'), true);
    $action = $data['action'] ?? '';

    switch ($action) {
        case 'create':
            createSession($conn, $userId, $data);
            break;
        case 'join':
            joinSession($conn, $userId, $data);
            break;
        case 'save-state':
            saveWhiteboardState($conn, $userId, $data);
            break;
        case 'end':
            endSession($conn, $userId, $data);
            break;
        default:
            http_response_code(400);
            echo json_encode(['error' => 'Invalid action']);
            break;
    }
}

function createSession($conn, $userId, $data) {
    $userRole = $_SESSION['role'] ?? 'student';
    $teacherId = $userRole === 'teacher' ? $userId : ($data['teacherId'] ?? 0);
    $studentId = $userRole === 'student' ? $userId : ($data['studentId'] ?? 0);
    $lessonId = $data['lessonId'] ?? null;
    $sessionType = $data['sessionType'] ?? 'live'; // 'live' or 'test'

    if (($userRole === 'teacher' && $studentId <= 0) || ($userRole === 'student' && $teacherId <= 0)) {
        http_response_code(400);
        echo json_encode(['error' => 'Missing teacher or student ID']);
        return;
    }

    // For test sessions, only teachers can create them
    if ($sessionType === 'test' && $userRole !== 'teacher') {
        http_response_code(403);
        echo json_encode(['error' => 'Only teachers can create test sessions']);
        return;
    }

    // Check if there's already an active session for this teacher-student pair (for live sessions)
    if ($sessionType === 'live') {
        $checkStmt = $conn->prepare("
            SELECT session_id FROM video_sessions 
            WHERE teacher_id = ? AND student_id = ? AND status = 'active' AND session_type = 'live'
            LIMIT 1
        ");
        $checkStmt->bind_param("ii", $teacherId, $studentId);
        $checkStmt->execute();
        $existingResult = $checkStmt->get_result();
        
        if ($existingResult->num_rows > 0) {
            $existing = $existingResult->fetch_assoc();
            $checkStmt->close();
            echo json_encode(['success' => true, 'sessionId' => $existing['session_id']]);
            return;
        }
        $checkStmt->close();
    }

    // Generate unique session ID
    $sessionId = 'session_' . uniqid() . '_' . time();

    $stmt = $conn->prepare("
        INSERT INTO video_sessions (session_id, teacher_id, student_id, lesson_id, session_type, status)
        VALUES (?, ?, ?, ?, ?, 'active')
    ");
    
    if ($lessonId) {
        $stmt->bind_param("siiis", $sessionId, $teacherId, $studentId, $lessonId, $sessionType);
    } else {
        $stmt->bind_param("siiis", $sessionId, $teacherId, $studentId, $null = null, $sessionType);
    }

    if ($stmt->execute()) {
        $stmt->close();
        echo json_encode(['success' => true, 'sessionId' => $sessionId]);
    } else {
        $stmt->close();
        http_response_code(500);
        echo json_encode(['error' => 'Failed to create session']);
    }
}

function joinSession($conn, $userId, $data) {
    $sessionId = $data['sessionId'] ?? '';

    if (empty($sessionId)) {
        http_response_code(400);
        echo json_encode(['error' => 'sessionId required']);
        return;
    }

    // Verify access
    $stmt = $conn->prepare("
        SELECT * FROM video_sessions 
        WHERE session_id = ? 
        AND (teacher_id = ? OR student_id = ?)
        AND status = 'active'
    ");
    $stmt->bind_param("sii", $sessionId, $userId, $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    $session = $result->fetch_assoc();
    $stmt->close();

    if ($session) {
        echo json_encode(['success' => true, 'session' => $session]);
    } else {
        http_response_code(403);
        echo json_encode(['error' => 'Access denied or session not found']);
    }
}

function saveWhiteboardState($conn, $userId, $data) {
    $sessionId = $data['sessionId'] ?? '';
    $state = $data['state'] ?? '';

    if (empty($sessionId) || empty($state)) {
        http_response_code(400);
        echo json_encode(['error' => 'sessionId and state required']);
        return;
    }

    // Verify access
    $stmt = $conn->prepare("
        SELECT id FROM video_sessions 
        WHERE session_id = ? 
        AND (teacher_id = ? OR student_id = ?)
        AND status = 'active'
    ");
    $stmt->bind_param("sii", $sessionId, $userId, $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        $stmt->close();
        http_response_code(403);
        echo json_encode(['error' => 'Access denied']);
        return;
    }
    $stmt->close();

    // Get current version
    $stmt = $conn->prepare("
        SELECT version FROM whiteboard_states 
        WHERE session_id = ? 
        ORDER BY last_updated DESC 
        LIMIT 1
    ");
    $stmt->bind_param("s", $sessionId);
    $stmt->execute();
    $result = $stmt->get_result();
    $current = $result->fetch_assoc();
    $stmt->close();

    $version = $current ? $current['version'] + 1 : 1;

    // Insert or update state
    $stmt = $conn->prepare("
        INSERT INTO whiteboard_states (session_id, state_data, version)
        VALUES (?, ?, ?)
        ON DUPLICATE KEY UPDATE state_data = ?, version = ?, last_updated = CURRENT_TIMESTAMP
    ");
    $stmt->bind_param("ssissi", $sessionId, $state, $version, $state, $version);

    if ($stmt->execute()) {
        $stmt->close();
        echo json_encode(['success' => true, 'version' => $version]);
    } else {
        $stmt->close();
        http_response_code(500);
        echo json_encode(['error' => 'Failed to save state']);
    }
}

function endSession($conn, $userId, $data) {
    $sessionId = $data['sessionId'] ?? '';

    if (empty($sessionId)) {
        http_response_code(400);
        echo json_encode(['error' => 'sessionId required']);
        return;
    }

    // Verify access (only teacher can end session)
    $userRole = $_SESSION['role'] ?? 'student';
    if ($userRole !== 'teacher') {
        http_response_code(403);
        echo json_encode(['error' => 'Only teachers can end sessions']);
        return;
    }

    $stmt = $conn->prepare("
        UPDATE video_sessions 
        SET status = 'ended', ended_at = CURRENT_TIMESTAMP
        WHERE session_id = ? AND teacher_id = ?
    ");
    $stmt->bind_param("si", $sessionId, $userId);

    if ($stmt->execute()) {
        $stmt->close();
        echo json_encode(['success' => true]);
    } else {
        $stmt->close();
        http_response_code(500);
        echo json_encode(['error' => 'Failed to end session']);
    }
}

function handlePut($conn) {
    // Update session (e.g., extend time)
    http_response_code(501);
    echo json_encode(['error' => 'Not implemented']);
}

function getOrCreateSessionForLesson($conn, $userId) {
    $lessonId = $_GET['lessonId'] ?? '';
    
    if (empty($lessonId)) {
        http_response_code(400);
        echo json_encode(['error' => 'lessonId required']);
        return;
    }
    
    // Get lesson details
    $stmt = $conn->prepare("
        SELECT l.*, 
               t.id as teacher_id, t.name as teacher_name,
               s.id as student_id, s.name as student_name
        FROM lessons l
        JOIN users t ON l.teacher_id = t.id
        JOIN users s ON l.student_id = s.id
        WHERE l.id = ? AND (l.teacher_id = ? OR l.student_id = ?)
        AND l.status = 'scheduled'
    ");
    $stmt->bind_param("iii", $lessonId, $userId, $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    $lesson = $result->fetch_assoc();
    $stmt->close();
    
    if (!$lesson) {
        http_response_code(404);
        echo json_encode(['error' => 'Lesson not found or access denied']);
        return;
    }
    
    // Check if session already exists for this lesson
    $stmt = $conn->prepare("
        SELECT * FROM video_sessions 
        WHERE lesson_id = ? AND status = 'active'
        ORDER BY started_at DESC
        LIMIT 1
    ");
    $stmt->bind_param("i", $lessonId);
    $stmt->execute();
    $result = $stmt->get_result();
    $existingSession = $result->fetch_assoc();
    $stmt->close();
    
    if ($existingSession) {
        // Return existing session
        echo json_encode([
            'success' => true,
            'session' => $existingSession,
            'created' => false
        ]);
        return;
    }
    
    // Create new session for this lesson
    $sessionId = 'session_' . uniqid() . '_' . time();
    $stmt = $conn->prepare("
        INSERT INTO video_sessions (session_id, teacher_id, student_id, lesson_id, session_type, status)
        VALUES (?, ?, ?, ?, 'live', 'active')
    ");
    $stmt->bind_param("siii", $sessionId, $lesson['teacher_id'], $lesson['student_id'], $lessonId);
    
    if ($stmt->execute()) {
        $newSessionId = $stmt->insert_id;
        $stmt->close();
        
        // Fetch the created session
        $stmt = $conn->prepare("SELECT * FROM video_sessions WHERE id = ?");
        $stmt->bind_param("i", $newSessionId);
        $stmt->execute();
        $result = $stmt->get_result();
        $newSession = $result->fetch_assoc();
        $stmt->close();
        
        echo json_encode([
            'success' => true,
            'session' => $newSession,
            'created' => true
        ]);
    } else {
        $stmt->close();
        http_response_code(500);
        echo json_encode(['error' => 'Failed to create session']);
    }
}

