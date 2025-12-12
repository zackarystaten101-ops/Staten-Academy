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

if (empty($message) && empty($_FILES['attachment']['name'])) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Message or attachment is required']);
    exit();
}

// Handle file attachment
$attachment_path = null;
$attachment_type = null;

if (isset($_FILES['attachment']) && $_FILES['attachment']['error'] === UPLOAD_ERR_OK) {
    $upload_dir = __DIR__ . '/public/uploads/messages/';
    
    // Create directory if it doesn't exist
    if (!is_dir($upload_dir)) {
        mkdir($upload_dir, 0755, true);
    }
    
    $file = $_FILES['attachment'];
    $file_name = $file['name'];
    $file_tmp = $file['tmp_name'];
    $file_size = $file['size'];
    $file_type_raw = $file['type'];
    
    // Validate file size (max 10MB)
    if ($file_size > 10 * 1024 * 1024) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'File size exceeds 10MB limit']);
        exit();
    }
    
    // Validate file type
    $allowed_types = [
        'image/jpeg', 'image/png', 'image/gif', 'image/webp',
        'application/pdf', 'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'video/mp4', 'video/quicktime', 'video/x-msvideo'
    ];
    
    if (!in_array($file_type_raw, $allowed_types)) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'File type not allowed']);
        exit();
    }
    
    // Determine attachment type
    if (strpos($file_type_raw, 'image/') === 0) {
        $attachment_type = 'image';
    } elseif (strpos($file_type_raw, 'video/') === 0) {
        $attachment_type = 'video';
    } else {
        $attachment_type = 'file';
    }
    
    // Generate unique filename
    $file_ext = pathinfo($file_name, PATHINFO_EXTENSION);
    $unique_name = uniqid('msg_', true) . '_' . time() . '.' . $file_ext;
    $upload_path = $upload_dir . $unique_name;
    
    // Move uploaded file
    if (move_uploaded_file($file_tmp, $upload_path)) {
        $attachment_path = 'uploads/messages/' . $unique_name;
    } else {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Failed to upload file']);
        exit();
    }
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
// Teachers can message students who:
// - Booked trial with them
// - Booked paid lesson with them
// - Previously messaged them
elseif ($sender_role === 'teacher' && ($receiver['role'] === 'student' || $receiver['role'] === 'new_student')) {
    // Check if student has booked trial or lesson with this teacher
    $check_booking = $conn->prepare("SELECT id FROM lessons WHERE student_id = ? AND teacher_id = ? LIMIT 1");
    $check_booking->bind_param("ii", $receiver_id, $sender_id);
    $check_booking->execute();
    $has_booking = $check_booking->get_result()->num_rows > 0;
    $check_booking->close();
    
    // Check if student has trial with this teacher
    if (!$has_booking) {
        $check_trial = $conn->prepare("SELECT id FROM trial_lessons WHERE student_id = ? AND teacher_id = ? LIMIT 1");
        $check_trial->bind_param("ii", $receiver_id, $sender_id);
        $check_trial->execute();
        $has_trial = $check_trial->get_result()->num_rows > 0;
        $check_trial->close();
        $has_booking = $has_trial;
    }
    
    // Check if student previously messaged this teacher
    if (!$has_booking) {
        $check_msg = $conn->prepare("SELECT id FROM messages WHERE sender_id = ? AND receiver_id = ? AND message_type = 'direct' LIMIT 1");
        $check_msg->bind_param("ii", $receiver_id, $sender_id);
        $check_msg->execute();
        $has_booking = $check_msg->get_result()->num_rows > 0;
        $check_msg->close();
    }
    
    $can_message = $has_booking;
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

// Verify thread exists (for foreign key constraint)
$verify_thread = $conn->prepare("SELECT id FROM message_threads WHERE id = ?");
$verify_thread->bind_param("i", $thread_id);
$verify_thread->execute();
$verify_result = $verify_thread->get_result();
if ($verify_result->num_rows === 0) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Thread not found. Please try again.']);
    $verify_thread->close();
    $conn->close();
    exit();
}
$verify_thread->close();

// Insert message with thread_id (including attachment if present)
if ($attachment_path) {
    $insert_msg = $conn->prepare("INSERT INTO messages (thread_id, sender_id, receiver_id, message, message_type, attachment_path, attachment_type, sent_at) VALUES (?, ?, ?, ?, 'direct', ?, ?, NOW())");
    if (!$insert_msg) {
        header('Content-Type: application/json');
        $error_detail = defined('APP_DEBUG') && APP_DEBUG ? $conn->error : 'Database error';
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $error_detail]);
        $conn->close();
        exit();
    }
    $insert_msg->bind_param("iiisss", $thread_id, $sender_id, $receiver_id, $message, $attachment_path, $attachment_type);
} else {
    $insert_msg = $conn->prepare("INSERT INTO messages (thread_id, sender_id, receiver_id, message, message_type, sent_at) VALUES (?, ?, ?, ?, 'direct', NOW())");
    if (!$insert_msg) {
        header('Content-Type: application/json');
        $error_detail = defined('APP_DEBUG') && APP_DEBUG ? $conn->error : 'Database error';
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $error_detail]);
        $conn->close();
        exit();
    }
    $insert_msg->bind_param("iiis", $thread_id, $sender_id, $receiver_id, $message);
}

if ($insert_msg->execute()) {
    // Update thread's last_message_at timestamp
    $update_thread = $conn->prepare("UPDATE message_threads SET last_message_at = NOW() WHERE id = ?");
    if ($update_thread) {
        $update_thread->bind_param("i", $thread_id);
        $update_thread->execute();
        $update_thread->close();
    }
    
    header('Content-Type: application/json');
    echo json_encode(['success' => true, 'message' => 'Message sent']);
} else {
    header('Content-Type: application/json');
    $error_msg = 'Error sending message';
    // Log detailed error for debugging (only in development)
    if (defined('APP_DEBUG') && APP_DEBUG === true) {
        $error_msg .= ': ' . $insert_msg->error . ' (SQL: ' . $conn->error . ')';
    }
    echo json_encode(['success' => false, 'message' => $error_msg]);
}

$insert_msg->close();
$conn->close();
?>
