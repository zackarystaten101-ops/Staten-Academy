<?php
session_start();
require_once 'db.php';

if (!isset($_SESSION['user_id'])) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

$sender_id = $_SESSION['user_id'];
$sender_role = $_SESSION['user_role'] ?? 'guest';
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

// Check if receiver exists and get their role
$receiver_stmt = $conn->prepare("SELECT id, role FROM users WHERE id = ?");
$receiver_stmt->bind_param("i", $receiver_id);
$receiver_stmt->execute();
$receiver_result = $receiver_stmt->get_result();
if ($receiver_result->num_rows === 0) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Receiver not found']);
    $receiver_stmt->close();
    exit();
}
$receiver = $receiver_result->fetch_assoc();
$receiver_stmt->close();

// Check message permissions
// Admins can message anyone, anyone can message admin
$can_message = false;
if ($sender_role === 'admin' || $receiver['role'] === 'admin') {
    $can_message = true;
}
// Teachers can ONLY reply to messages from students (including new_student)
elseif ($sender_role === 'teacher' && ($receiver['role'] === 'student' || $receiver['role'] === 'new_student')) {
    $check = $conn->prepare("SELECT id FROM messages WHERE sender_id = ? AND receiver_id = ? AND message_type = 'direct'");
    $check->bind_param("ii", $receiver_id, $sender_id);
    $check->execute();
    if ($check->get_result()->num_rows > 0) {
        $can_message = true;
    }
    $check->close();
}
// Students can message teachers and admins
elseif (($sender_role === 'student' || $sender_role === 'new_student') && ($receiver['role'] === 'teacher' || $receiver['role'] === 'admin')) {
    $can_message = true;
}

if (!$can_message) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'You cannot message this user']);
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

$thread_id = null;
if ($thread_result->num_rows > 0) {
    $thread = $thread_result->fetch_assoc();
    $thread_id = $thread['id'];
} else {
    $insert_stmt = $conn->prepare("INSERT INTO message_threads (initiator_id, recipient_id, thread_type) VALUES (?, ?, 'user')");
    $insert_stmt->bind_param("ii", $sender_id, $receiver_id);
    if ($insert_stmt->execute()) {
        $thread_id = $conn->insert_id;
    } else {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Failed to create thread']);
        $insert_stmt->close();
        $stmt->close();
        $conn->close();
        exit();
    }
    $insert_stmt->close();
}
$stmt->close();

// Safety check
if (!$thread_id) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Failed to get or create thread']);
    $conn->close();
    exit();
}

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
