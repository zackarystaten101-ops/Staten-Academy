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
    case 'POST':
        handlePost($conn, $action);
        break;
    default:
        http_response_code(405);
        echo json_encode(['error' => 'Method not allowed']);
        break;
}

function handlePost($conn, $action) {
    $data = json_decode(file_get_contents('php://input'), true);
    $userId = $_SESSION['user_id'];

    switch ($action) {
        case 'offer':
            handleOffer($conn, $userId, $data);
            break;
        case 'answer':
            handleAnswer($conn, $userId, $data);
            break;
        case 'ice-candidate':
            handleIceCandidate($conn, $userId, $data);
            break;
        default:
            http_response_code(400);
            echo json_encode(['error' => 'Invalid action']);
            break;
    }
}

function handleOffer($conn, $userId, $data) {
    $sessionId = $data['sessionId'] ?? '';
    $targetUserId = $data['targetUserId'] ?? '';
    $offer = $data['offer'] ?? null;

    if (empty($sessionId) || empty($targetUserId) || !$offer) {
        http_response_code(400);
        echo json_encode(['error' => 'Missing required fields']);
        return;
    }

    // Verify session access
    if (!verifySessionAccess($conn, $userId, $sessionId)) {
        http_response_code(403);
        echo json_encode(['error' => 'Access denied']);
        return;
    }

    // Store offer in signaling queue
    $messageData = json_encode(['offer' => $offer]);
    $stmt = $conn->prepare("
        INSERT INTO signaling_queue (session_id, from_user_id, to_user_id, message_type, message_data)
        VALUES (?, ?, ?, 'webrtc-offer', ?)
    ");
    $stmt->bind_param("siis", $sessionId, $userId, $targetUserId, $messageData);
    
    if ($stmt->execute()) {
        $stmt->close();
        echo json_encode([
            'success' => true,
            'message' => 'Offer queued'
        ]);
    } else {
        $stmt->close();
        http_response_code(500);
        echo json_encode(['error' => 'Failed to queue offer']);
    }
}

function handleAnswer($conn, $userId, $data) {
    $sessionId = $data['sessionId'] ?? '';
    $targetUserId = $data['targetUserId'] ?? '';
    $answer = $data['answer'] ?? null;

    if (empty($sessionId) || empty($targetUserId) || !$answer) {
        http_response_code(400);
        echo json_encode(['error' => 'Missing required fields']);
        return;
    }

    // Verify session access
    if (!verifySessionAccess($conn, $userId, $sessionId)) {
        http_response_code(403);
        echo json_encode(['error' => 'Access denied']);
        return;
    }

    // Store answer in signaling queue
    $messageData = json_encode(['answer' => $answer]);
    $stmt = $conn->prepare("
        INSERT INTO signaling_queue (session_id, from_user_id, to_user_id, message_type, message_data)
        VALUES (?, ?, ?, 'webrtc-answer', ?)
    ");
    $stmt->bind_param("siis", $sessionId, $userId, $targetUserId, $messageData);
    
    if ($stmt->execute()) {
        $stmt->close();
        echo json_encode([
            'success' => true,
            'message' => 'Answer queued'
        ]);
    } else {
        $stmt->close();
        http_response_code(500);
        echo json_encode(['error' => 'Failed to queue answer']);
    }
}

function handleIceCandidate($conn, $userId, $data) {
    $sessionId = $data['sessionId'] ?? '';
    $targetUserId = $data['targetUserId'] ?? '';
    $candidate = $data['candidate'] ?? null;

    if (empty($sessionId) || empty($targetUserId) || !$candidate) {
        http_response_code(400);
        echo json_encode(['error' => 'Missing required fields']);
        return;
    }

    // Verify session access
    if (!verifySessionAccess($conn, $userId, $sessionId)) {
        http_response_code(403);
        echo json_encode(['error' => 'Access denied']);
        return;
    }

    // Store ICE candidate in signaling queue
    $messageData = json_encode(['candidate' => $candidate]);
    $stmt = $conn->prepare("
        INSERT INTO signaling_queue (session_id, from_user_id, to_user_id, message_type, message_data)
        VALUES (?, ?, ?, 'webrtc-ice-candidate', ?)
    ");
    $stmt->bind_param("siis", $sessionId, $userId, $targetUserId, $messageData);
    
    if ($stmt->execute()) {
        $stmt->close();
        echo json_encode([
            'success' => true,
            'message' => 'ICE candidate queued'
        ]);
    } else {
        $stmt->close();
        http_response_code(500);
        echo json_encode(['error' => 'Failed to queue ICE candidate']);
    }
}

function verifySessionAccess($conn, $userId, $sessionId) {
    $stmt = $conn->prepare("
        SELECT id FROM video_sessions 
        WHERE session_id = ? 
        AND (teacher_id = ? OR student_id = ?)
        AND status = 'active'
    ");
    $stmt->bind_param("sii", $sessionId, $userId, $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    $stmt->close();
    
    return $result->num_rows > 0;
}

