<?php
/**
 * Favorites API
 * Handle adding/removing favorite teachers
 */

session_start();
require_once '../db.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$student_id = $_SESSION['user_id'];
$teacher_id = isset($_POST['teacher_id']) ? (int)$_POST['teacher_id'] : 0;
$action = $_POST['action'] ?? '';

if (!$teacher_id) {
    echo json_encode(['error' => 'Teacher ID required']);
    exit;
}

if ($action === 'add') {
    $stmt = $conn->prepare("INSERT IGNORE INTO favorite_teachers (student_id, teacher_id) VALUES (?, ?)");
    if ($stmt) {
        $stmt->bind_param("ii", $student_id, $teacher_id);
        $stmt->execute();
        $stmt->close();
        echo json_encode(['success' => true, 'action' => 'added']);
    } else {
        echo json_encode(['error' => 'Database error']);
    }
} elseif ($action === 'remove') {
    $stmt = $conn->prepare("DELETE FROM favorite_teachers WHERE student_id = ? AND teacher_id = ?");
    if ($stmt) {
        $stmt->bind_param("ii", $student_id, $teacher_id);
        $stmt->execute();
        $stmt->close();
        echo json_encode(['success' => true, 'action' => 'removed']);
    } else {
        echo json_encode(['error' => 'Database error']);
    }
} else {
    echo json_encode(['error' => 'Invalid action']);
}

