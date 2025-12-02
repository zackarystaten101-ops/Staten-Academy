<?php
session_start();
require_once 'db.php';

if (!isset($_SESSION['user_id'])) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

$sender_id = $_SESSION['user_id'];
$receiver_id = isset($_POST['receiver_id']) ? intval($_POST['receiver_id']) : 0;
$message = isset($_POST['message']) ? trim($_POST['message']) : '';

if (empty($message)) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Message cannot be empty']);
    exit();
}

if ($receiver_id <= 0) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Invalid receiver']);
    exit();
}

// Check/create thread
$stmt = $conn->prepare("
    SELECT id FROM message_threads 
    WHERE (initiator_id = ? AND recipient_id = ? AND thread_type = 'user') 
    OR (initiator_id = ? AND recipient_id = ? AND thread_type = 'user')
    LIMIT 1
");
$stmt->bind_param("iiii", $sender_id, $receiver_id, $receiver_id, $sender_id);
$stmt->execute();
$thread_result = $stmt->get_result();

if ($thread_result->num_rows > 0) {
    $thread = $thread_result->fetch_assoc();
    $thread_id = $thread['id'];
} else {
    $insert_stmt = $conn->prepare("INSERT INTO message_threads (initiator_id, recipient_id, thread_type) VALUES (?, ?, 'user')");
    $insert_stmt->bind_param("ii", $sender_id, $receiver_id);
    if ($insert_stmt->execute()) {
        $thread_id = $conn->insert_id;
    }
    $insert_stmt->close();
}
$stmt->close();

// Insert message
$insert_msg = $conn->prepare("INSERT INTO messages (thread_id, sender_id, receiver_id, message, message_type, sent_at) VALUES (?, ?, ?, ?, 'direct', NOW())");
$insert_msg->bind_param("iiss", $thread_id, $sender_id, $receiver_id, $message);

if ($insert_msg->execute()) {
    header('Content-Type: application/json');
    echo json_encode(['success' => true, 'message' => 'Message sent']);
} else {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Error sending message']);
}

$insert_msg->close();
$conn->close();
?>
