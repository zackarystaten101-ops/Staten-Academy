<?php
/**
 * Start or continue admin chat
 * Creates a message thread between user and admin
 */

// Set headers first, before any output
if (!headers_sent()) {
    header('Content-Type: application/json');
    header('Accept: application/json');
}

session_start();
require_once '../db.php';

// Check authentication
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$user_id = $_SESSION['user_id'];
$user_role = $_SESSION['user_role'] ?? 'guest';

// Get admin user ID (first admin found)
$admin_stmt = $conn->prepare("SELECT id FROM users WHERE role = 'admin' LIMIT 1");
$admin_stmt->execute();
$admin_result = $admin_stmt->get_result();
if ($admin_result->num_rows === 0) {
    echo json_encode(['error' => 'No admin found']);
    $admin_stmt->close();
    exit;
}
$admin = $admin_result->fetch_assoc();
$admin_id = $admin['id'];
$admin_stmt->close();

// Check if thread already exists
$thread_stmt = $conn->prepare("
    SELECT id FROM message_threads 
    WHERE ((initiator_id = ? AND recipient_id = ?) OR (initiator_id = ? AND recipient_id = ?))
    AND thread_type = 'user'
    LIMIT 1
");
$thread_stmt->bind_param("iiii", $user_id, $admin_id, $admin_id, $user_id);
$thread_stmt->execute();
$thread_result = $thread_stmt->get_result();

$thread_id = null;
if ($thread_result->num_rows > 0) {
    $thread = $thread_result->fetch_assoc();
    $thread_id = $thread['id'];
} else {
    // Create new thread
    $insert_stmt = $conn->prepare("INSERT INTO message_threads (initiator_id, recipient_id, thread_type) VALUES (?, ?, 'user')");
    $insert_stmt->bind_param("ii", $user_id, $admin_id);
    if ($insert_stmt->execute()) {
        $thread_id = $conn->insert_id;
    } else {
        echo json_encode(['error' => 'Failed to create thread: ' . $conn->error]);
        $insert_stmt->close();
        $thread_stmt->close();
        $conn->close();
        exit;
    }
    $insert_stmt->close();
}
$thread_stmt->close();

// Safety check
if (!$thread_id) {
    echo json_encode(['error' => 'Failed to get or create thread']);
    $conn->close();
    exit;
}

// Return thread ID and admin ID for redirect
echo json_encode([
    'success' => true,
    'thread_id' => $thread_id,
    'admin_id' => $admin_id,
    'redirect_url' => 'message_threads.php?user_id=' . $admin_id
]);

// Close database connection
$conn->close();

