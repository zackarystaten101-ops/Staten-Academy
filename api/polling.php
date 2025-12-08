<?php
session_start();
require_once __DIR__ . '/../db.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit();
}

$userId = $_SESSION['user_id'];
$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'POST') {
    handlePost($conn, $userId);
    exit();
}

$sessionId = $_GET['sessionId'] ?? '';
$lastCheck = $_GET['lastCheck'] ?? 0;

if (empty($sessionId)) {
    http_response_code(400);
    echo json_encode(['error' => 'sessionId required']);
    exit();
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
    exit();
}
$stmt->close();

// Get session participants
$stmt = $conn->prepare("
    SELECT teacher_id, student_id FROM video_sessions 
    WHERE session_id = ? AND status = 'active'
");
$stmt->bind_param("s", $sessionId);
$stmt->execute();
$sessionResult = $stmt->get_result();
$session = $sessionResult->fetch_assoc();
$stmt->close();

$otherUserId = ($userId == $session['teacher_id']) ? $session['student_id'] : $session['teacher_id'];

// Cleanup old processed messages (older than 5 minutes)
$cleanupTime = date('Y-m-d H:i:s', time() - 300);
$stmt = $conn->prepare("
    DELETE FROM signaling_queue 
    WHERE processed = TRUE AND timestamp < ?
");
$stmt->bind_param("s", $cleanupTime);
$stmt->execute();
$stmt->close();

$stmt = $conn->prepare("
    DELETE FROM whiteboard_operations 
    WHERE processed = TRUE AND timestamp < ?
");
$stmt->bind_param("s", $cleanupTime);
$stmt->execute();
$stmt->close();

// Get unprocessed signaling messages for this user
$lastCheckTime = $lastCheck > 0 ? date('Y-m-d H:i:s', $lastCheck / 1000) : date('Y-m-d H:i:s', time() - 60);
$stmt = $conn->prepare("
    SELECT id, from_user_id, to_user_id, message_type, message_data, timestamp
    FROM signaling_queue
    WHERE session_id = ? 
    AND to_user_id = ?
    AND processed = FALSE
    AND timestamp > ?
    ORDER BY timestamp ASC
    LIMIT 50
");
$stmt->bind_param("sis", $sessionId, $userId, $lastCheckTime);
$stmt->execute();
$signalingResult = $stmt->get_result();
$signalingMessages = [];
$messageIds = [];

while ($row = $signalingResult->fetch_assoc()) {
    $messageIds[] = $row['id'];
    $signalingMessages[] = [
        'type' => $row['message_type'],
        'userId' => $row['from_user_id'],
        'targetUserId' => $row['to_user_id'],
        'data' => json_decode($row['message_data'], true),
        'timestamp' => strtotime($row['timestamp']) * 1000
    ];
}
$stmt->close();

// Mark signaling messages as processed
if (!empty($messageIds)) {
    $placeholders = implode(',', array_fill(0, count($messageIds), '?'));
    $types = str_repeat('i', count($messageIds));
    $stmt = $conn->prepare("
        UPDATE signaling_queue 
        SET processed = TRUE 
        WHERE id IN ($placeholders)
    ");
    $stmt->bind_param($types, ...$messageIds);
    $stmt->execute();
    $stmt->close();
}

// Get unprocessed whiteboard operations
$stmt = $conn->prepare("
    SELECT id, user_id, operation_data, timestamp
    FROM whiteboard_operations
    WHERE session_id = ? 
    AND user_id != ?
    AND processed = FALSE
    AND timestamp > ?
    ORDER BY timestamp ASC
    LIMIT 100
");
$stmt->bind_param("sis", $sessionId, $userId, $lastCheckTime);
$stmt->execute();
$whiteboardResult = $stmt->get_result();
$whiteboardMessages = [];
$whiteboardIds = [];

while ($row = $whiteboardResult->fetch_assoc()) {
    $whiteboardIds[] = $row['id'];
    $operationData = json_decode($row['operation_data'], true);
    
    // Handle different operation types
    if ($operationData['type'] === 'cursor-move') {
        $whiteboardMessages[] = [
            'type' => 'cursor-move',
            'userId' => $row['user_id'],
            'x' => $operationData['x'],
            'y' => $operationData['y'],
            'timestamp' => strtotime($row['timestamp']) * 1000
        ];
    } else {
        $whiteboardMessages[] = [
            'type' => 'whiteboard-operation',
            'userId' => $row['user_id'],
            'operation' => $operationData,
            'timestamp' => strtotime($row['timestamp']) * 1000
        ];
    }
}
$stmt->close();

// Mark whiteboard operations as processed
if (!empty($whiteboardIds)) {
    $placeholders = implode(',', array_fill(0, count($whiteboardIds), '?'));
    $types = str_repeat('i', count($whiteboardIds));
    $stmt = $conn->prepare("
        UPDATE whiteboard_operations 
        SET processed = TRUE 
        WHERE id IN ($placeholders)
    ");
    $stmt->bind_param($types, ...$whiteboardIds);
    $stmt->execute();
    $stmt->close();
}

// Check for user join/leave events (simplified - check if other user is in active session)
// This is a basic implementation - in production, you might want a separate user_events table
$userEvents = [];

// Get all messages combined
$allMessages = array_merge($signalingMessages, $whiteboardMessages);

// Sort by timestamp (oldest first)
usort($allMessages, function($a, $b) {
    $tsA = $a['timestamp'] ?? 0;
    $tsB = $b['timestamp'] ?? 0;
    return $tsA <=> $tsB;
});

// Ensure messages array is always present (even if empty)
echo json_encode([
    'success' => true,
    'messages' => $allMessages ?: [],
    'timestamp' => time() * 1000
], JSON_UNESCAPED_SLASHES);

function handlePost($conn, $userId) {
    $data = json_decode(file_get_contents('php://input'), true);
    $action = $data['action'] ?? '';
    $sessionId = $data['sessionId'] ?? '';

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

    if ($action === 'whiteboard-operation') {
        $operation = $data['operation'] ?? null;
        if (!$operation) {
            http_response_code(400);
            echo json_encode(['error' => 'operation required']);
            return;
        }

        $operationData = json_encode($operation);
        $stmt = $conn->prepare("
            INSERT INTO whiteboard_operations (session_id, user_id, operation_data)
            VALUES (?, ?, ?)
        ");
        $stmt->bind_param("sis", $sessionId, $userId, $operationData);
        
        if ($stmt->execute()) {
            $stmt->close();
            echo json_encode(['success' => true]);
        } else {
            $stmt->close();
            http_response_code(500);
            echo json_encode(['error' => 'Failed to store operation']);
        }
    } else if ($action === 'cursor-move') {
        $x = $data['x'] ?? 0;
        $y = $data['y'] ?? 0;
        
        // Store cursor move as whiteboard operation
        $operationData = json_encode([
            'type' => 'cursor-move',
            'x' => $x,
            'y' => $y
        ]);
        $stmt = $conn->prepare("
            INSERT INTO whiteboard_operations (session_id, user_id, operation_data)
            VALUES (?, ?, ?)
        ");
        $stmt->bind_param("sis", $sessionId, $userId, $operationData);
        
        if ($stmt->execute()) {
            $stmt->close();
            echo json_encode(['success' => true]);
        } else {
            $stmt->close();
            http_response_code(500);
            echo json_encode(['error' => 'Failed to store cursor move']);
        }
    } else {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid action']);
    }
}

