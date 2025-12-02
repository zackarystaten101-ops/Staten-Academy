<?php
session_start();
require_once 'db.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['error' => 'Unauthorized']);
    exit();
}

$user_id = $_SESSION['user_id'];
$other_id = isset($_GET['receiver_id']) ? intval($_GET['receiver_id']) : 0;

if ($other_id <= 0) {
    echo json_encode(['error' => 'Invalid receiver']);
    exit();
}

$stmt = $conn->prepare("
    SELECT m.id, m.sender_id, m.receiver_id, m.message, m.sent_at, u.name, u.profile_pic
    FROM messages m
    JOIN users u ON m.sender_id = u.id
    WHERE m.message_type = 'direct'
    AND ((m.sender_id = ? AND m.receiver_id = ?) OR (m.sender_id = ? AND m.receiver_id = ?))
    ORDER BY m.sent_at ASC
");

if (!$stmt) {
    echo json_encode(['error' => 'Database error']);
    exit();
}

$stmt->bind_param("iiii", $user_id, $other_id, $other_id, $user_id);
$stmt->execute();
$result = $stmt->get_result();

$messages = [];
while ($row = $result->fetch_assoc()) {
    $messages[] = $row;
}

echo json_encode($messages);
$stmt->close();
$conn->close();
?>
