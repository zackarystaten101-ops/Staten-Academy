<?php
// Start output buffering to prevent "headers already sent" errors
ob_start();

// Load environment configuration first
if (!defined('DB_HOST')) {
    require_once __DIR__ . '/env.php';
}

// Start session before any output
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/app/Views/components/dashboard-functions.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'] ?? null;
$user_role = $_SESSION['user_role'] ?? 'guest';

if (!$user_id) {
    header("Location: login.php");
    exit();
}

// Fetch current user
$stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$current_user = $stmt->get_result()->fetch_assoc();
$stmt->close();

// Set $user for header component
$user = $current_user;

// Set page title for header
$page_title = 'Messages';
$_SESSION['profile_pic'] = $user['profile_pic'] ?? getAssetPath('images/placeholder-teacher.svg');

// Get conversation ID from query parameter
$thread_id = isset($_GET['thread_id']) ? intval($_GET['thread_id']) : 0;
$other_user_id = isset($_GET['user_id']) ? intval($_GET['user_id']) : 0;

$other_user = null;
$messages = [];
$can_message = true;

if ($other_user_id > 0) {
    // Fetch other user info
    $stmt = $conn->prepare("SELECT id, name, role, profile_pic, email FROM users WHERE id = ?");
    $stmt->bind_param("i", $other_user_id);
    $stmt->execute();
    $other_user = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    
    if (!$other_user) {
        die("User not found");
    }
    
    // Check message permissions
    // Admins can message anyone, anyone can message admin
    if ($user_role === 'admin' || $other_user['role'] === 'admin') {
        $can_message = true; // Admins can always message, and anyone can message admin
    }
    // Teachers can ONLY reply to messages from students (including new_student)
    elseif ($user_role === 'teacher' && ($other_user['role'] === 'student' || $other_user['role'] === 'new_student')) {
        $check = $conn->prepare("SELECT id FROM messages WHERE sender_id = ? AND receiver_id = ? AND message_type = 'direct'");
        $check->bind_param("ii", $other_user_id, $user_id);
        $check->execute();
        if ($check->get_result()->num_rows == 0) {
            $can_message = false;
        }
        $check->close();
    }
    
    // Fetch messages (only direct messages, ordered by time)
    $stmt = $conn->prepare("
        SELECT m.id, m.sender_id, m.receiver_id, m.message, m.sent_at, m.is_read, u.name as sender_name, u.profile_pic 
        FROM messages m 
        JOIN users u ON m.sender_id = u.id
        WHERE m.message_type = 'direct'
        AND ((m.sender_id = ? AND m.receiver_id = ?) OR (m.sender_id = ? AND m.receiver_id = ?))
        ORDER BY m.sent_at ASC
    ");
    $stmt->bind_param("iiii", $user_id, $other_user_id, $other_user_id, $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $messages = $result->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    
    // Mark messages as read when user views the conversation
    if (count($messages) > 0) {
        $stmt = $conn->prepare("UPDATE messages SET is_read = 1 WHERE receiver_id = ? AND sender_id = ? AND is_read = 0 AND message_type = 'direct'");
        $stmt->bind_param("ii", $user_id, $other_user_id);
        $stmt->execute();
        $stmt->close();
    }
}

// Handle message sending (AJAX or POST)
$response = ['success' => false, 'message' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $message_text = isset($_POST['message']) ? trim($_POST['message']) : '';
    $receiver_id = isset($_POST['receiver_id']) ? intval($_POST['receiver_id']) : 0;
    
    if (empty($message_text)) {
        $response['message'] = 'Message cannot be empty';
    } elseif ($receiver_id <= 0) {
        $response['message'] = 'Invalid receiver';
    } elseif (!$can_message) {
        $response['message'] = 'You cannot message this user';
    } else {
        // Check/create thread
        $stmt = $conn->prepare("
            SELECT id FROM message_threads 
            WHERE (initiator_id = ? AND recipient_id = ? AND thread_type = 'user') 
            OR (initiator_id = ? AND recipient_id = ? AND thread_type = 'user')
            LIMIT 1
        ");
        $stmt->bind_param("iiii", $user_id, $receiver_id, $receiver_id, $user_id);
        $stmt->execute();
        $thread_result = $stmt->get_result();
        
        if ($thread_result->num_rows > 0) {
            $thread = $thread_result->fetch_assoc();
            $thread_id = $thread['id'];
        } else {
            $insert_stmt = $conn->prepare("INSERT INTO message_threads (initiator_id, recipient_id, thread_type) VALUES (?, ?, 'user')");
            $insert_stmt->bind_param("ii", $user_id, $receiver_id);
            $insert_stmt->execute();
            $thread_id = $conn->insert_id;
            $insert_stmt->close();
        }
        $stmt->close();
        
        // Insert message with thread
        $insert_msg = $conn->prepare("INSERT INTO messages (thread_id, sender_id, receiver_id, message, message_type, sent_at) VALUES (?, ?, ?, ?, 'direct', NOW())");
        $insert_msg->bind_param("iiss", $thread_id, $user_id, $receiver_id, $message_text);
        
        if ($insert_msg->execute()) {
            $response['success'] = true;
            $response['message'] = 'Message sent';
            
            // If AJAX, return JSON
            if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
                header('Content-Type: application/json');
                echo json_encode($response);
                exit();
            }
        } else {
            $response['message'] = 'Error sending message';
        }
        $insert_msg->close();
    }
    
    // If not AJAX, reload page
    if (empty($_SERVER['HTTP_X_REQUESTED_WITH']) || strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) !== 'xmlhttprequest') {
        header("Location: message_threads.php?user_id=$receiver_id");
        exit();
    }
}

// Fetch conversations (with last message preview)
$conversations = [];
// Get all unique conversation partners
$sql = "
    SELECT 
        CASE 
            WHEN sender_id = ? THEN receiver_id 
            ELSE sender_id 
        END as other_user_id,
        MAX(sent_at) as last_message_time
    FROM messages
    WHERE message_type = 'direct'
    AND (sender_id = ? OR receiver_id = ?)
    GROUP BY other_user_id
    ORDER BY last_message_time DESC
";
$stmt = $conn->prepare($sql);
if ($stmt) {
    $stmt->bind_param("iii", $user_id, $user_id, $user_id);
    $stmt->execute();
    $conv_result = $stmt->get_result();
} else {
    // Fallback if prepared statement fails
    $conv_result = false;
    error_log("Failed to prepare conversation query: " . $conn->error);
}

if ($conv_result) {
    while ($conv = $conv_result->fetch_assoc()) {
    $other_id = $conv['other_user_id'];
    
    // Fetch user details
    $user_stmt = $conn->prepare("SELECT id, name, profile_pic, role, email FROM users WHERE id = ?");
    $user_stmt->bind_param("i", $other_id);
    $user_stmt->execute();
    $user_info = $user_stmt->get_result()->fetch_assoc();
    $user_stmt->close();
    
    if ($user_info) {
        // Get last message preview
        $msg_stmt = $conn->prepare("
            SELECT message, sent_at 
            FROM messages 
            WHERE message_type = 'direct'
            AND ((sender_id = ? AND receiver_id = ?) OR (sender_id = ? AND receiver_id = ?))
            ORDER BY sent_at DESC 
            LIMIT 1
        ");
        $msg_stmt->bind_param("iiii", $user_id, $other_id, $other_id, $user_id);
        $msg_stmt->execute();
        $last_msg = $msg_stmt->get_result()->fetch_assoc();
        $msg_stmt->close();
        
        $user_info['last_message'] = $last_msg['message'] ?? '';
        $user_info['last_message_time'] = $last_msg['sent_at'] ?? '';
        $conversations[] = $user_info;
    }
    }
    $stmt->close();
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=5.0, user-scalable=yes">
    <meta name="theme-color" content="#004080">
    <meta name="mobile-web-app-capable" content="yes">
    <title>Messages - Staten Academy</title>
    <link rel="stylesheet" href="<?php echo getAssetPath('styles.css'); ?>">
    <link rel="stylesheet" href="<?php echo getAssetPath('css/dashboard.css'); ?>">
    <link rel="stylesheet" href="<?php echo getAssetPath('css/mobile.css'); ?>">
    <!-- MODERN SHADOWS - To disable, comment out the line below -->
    <link rel="stylesheet" href="<?php echo getAssetPath('css/modern-shadows.css'); ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        /* Messages page specific styles */
        .messages-layout { 
            display: flex; 
            flex: 1; 
            overflow: hidden;
            gap: 0;
        }
        
        .conversations-panel { 
            width: 280px; 
            background: white; 
            border-right: 1px solid #ddd; 
            overflow-y: auto;
            display: flex;
            flex-direction: column;
            flex-shrink: 0;
        }
        
        .conversations-header { 
            padding: 15px; 
            background: #f8f9fa; 
            border-bottom: 1px solid #eee;
        }
        
        .conversations-header h3 { 
            margin: 0; 
            color: #333; 
            font-size: 1rem;
        }
        
        .conversation-item { 
            padding: 12px 15px; 
            border-bottom: 1px solid #eee; 
            cursor: pointer; 
            transition: background 0.2s;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .conversation-item:hover { 
            background: #f5f5f5; 
        }
        
        .conversation-item.active { 
            background: #e1f0ff; 
            border-left: 3px solid #0b6cf5;
        }
        
        .conv-pic { 
            width: 45px; 
            height: 45px; 
            border-radius: 50%; 
            object-fit: cover;
            flex-shrink: 0;
        }
        
        .conv-info { 
            flex: 1;
            min-width: 0;
        }
        
        .conv-header {
            display: flex;
            justify-content: space-between;
            align-items: baseline;
            margin-bottom: 3px;
        }
        
        .conv-name { 
            font-weight: 600; 
            color: #333; 
            font-size: 0.9rem;
            flex: 1;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        
        .conv-time {
            font-size: 0.75rem;
            color: #999;
        }
        
        .conv-role { 
            font-size: 0.75rem; 
            color: #999;
            margin-bottom: 4px;
        }
        
        .conv-preview {
            font-size: 0.8rem;
            color: #666;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        
        .chat-panel { 
            flex: 1; 
            display: flex; 
            flex-direction: column; 
            background: white;
        }
        
        .chat-header { 
            padding: 15px 20px; 
            border-bottom: 1px solid #eee; 
            background: #f8f9fa;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .chat-header img { 
            width: 40px; 
            height: 40px; 
            border-radius: 50%; 
            object-fit: cover; 
        }
        
        .chat-header-info h3 { 
            margin: 0; 
            color: #333; 
            font-size: 0.95rem; 
        }
        
        .chat-header-info p { 
            margin: 0; 
            color: #999; 
            font-size: 0.8rem; 
        }
        
        .messages-container { 
            flex: 1; 
            overflow-y: auto; 
            padding: 20px; 
            display: flex;
            flex-direction: column;
            gap: 12px;
        }
        
        .message { 
            display: flex; 
            gap: 8px; 
            align-items: flex-end;
            max-width: 75%;
        }
        
        .message.sent { 
            margin-left: auto;
            flex-direction: row-reverse;
        }
        
        .message-pic { 
            width: 32px; 
            height: 32px; 
            border-radius: 50%; 
            object-fit: cover; 
            flex-shrink: 0;
        }
        
        .message-bubble { 
            padding: 10px 14px; 
            border-radius: 12px; 
            word-wrap: break-word;
            word-break: break-word;
            line-height: 1.4;
        }
        
        .message.received .message-bubble { 
            background: #e1f0ff; 
            color: #004080;
        }
        
        .message.sent .message-bubble { 
            background: #0b6cf5; 
            color: white;
        }
        
        .message-time { 
            font-size: 0.7rem; 
            color: #999; 
            margin-top: 4px;
            padding: 0 8px;
        }
        
        .input-panel { 
            padding: 15px 20px; 
            border-top: 1px solid #eee; 
            background: #f8f9fa;
            display: flex;
            gap: 10px;
        }
        
        .input-panel textarea { 
            flex: 1; 
            padding: 10px 14px; 
            border: 1px solid #ddd; 
            border-radius: 20px; 
            resize: none; 
            max-height: 100px;
            font-family: inherit;
            font-size: 0.95rem;
            line-height: 1.4;
        }
        
        .input-panel textarea:focus { 
            outline: none; 
            border-color: #0b6cf5;
            box-shadow: 0 0 0 2px rgba(11, 108, 245, 0.1);
        }
        
        .send-btn { 
            padding: 0;
            background: #0b6cf5; 
            color: white; 
            border: none; 
            border-radius: 50%; 
            cursor: pointer; 
            font-size: 1rem;
            display: flex;
            align-items: center;
            justify-content: center;
            width: 40px;
            height: 40px;
            transition: background 0.2s;
            flex-shrink: 0;
        }
        
        .send-btn:hover:not(:disabled) { 
            background: #0056b3; 
        }
        
        .send-btn:disabled { 
            background: #ccc; 
            cursor: not-allowed;
        }
        
        .no-chat { 
            flex: 1; 
            display: flex; 
            align-items: center; 
            justify-content: center; 
            color: #999;
            text-align: center;
            padding: 40px;
        }
        
        .permission-warning { 
            background: #fff3cd; 
            color: #856404; 
            padding: 12px 15px; 
            border-radius: 8px; 
            margin-bottom: 15px; 
            font-size: 0.9rem;
            display: flex;
            align-items: flex-start;
            gap: 10px;
        }
        
        .permission-warning i {
            flex-shrink: 0;
            margin-top: 2px;
        }
        
        .empty-state { 
            text-align: center; 
            padding: 40px 20px; 
            color: #999;
        }
        
        .empty-state i { 
            font-size: 3rem; 
            color: #ddd; 
            margin-bottom: 10px;
        }
        
        .empty-state p {
            margin: 10px 0 0 0;
        }
        
        @media (max-width: 768px) {
            .conversations-panel { width: 200px; }
        }
    </style>
</head>
<body class="dashboard-layout">
<?php include __DIR__ . '/app/Views/components/dashboard-header.php'; ?>

<div class="content-wrapper">
    <?php 
    // Set active tab for sidebar - messages is accessed from sidebar, so we'll handle it differently
    $active_tab = 'messages';
    include __DIR__ . '/app/Views/components/dashboard-sidebar.php'; 
    ?>
    
    <div class="main messages-layout" style="display: flex; flex-direction: column; padding: 0; overflow: hidden;">
        <div class="content" style="display: flex; flex: 1; overflow: hidden; gap: 0;">
        <!-- Conversations List -->
        <div class="conversations-panel">
            <div class="conversations-header">
                <h3><i class="fas fa-comments"></i> Conversations</h3>
            </div>
            
            <?php if (count($conversations) > 0): ?>
                <?php foreach ($conversations as $conv): ?>
                    <div class="conversation-item <?php echo ($other_user && $other_user['id'] == $conv['id']) ? 'active' : ''; ?>" 
                         onclick="location.href='message_threads.php?user_id=<?php echo $conv['id']; ?>'">
                        <img src="<?php echo htmlspecialchars($conv['profile_pic']); ?>" alt="" class="conv-pic" onerror="this.src='<?php echo getAssetPath('images/placeholder-teacher.svg'); ?>'">
                        <div class="conv-info">
                            <div class="conv-header">
                                <div class="conv-name"><?php echo htmlspecialchars($conv['name']); ?></div>
                                <div class="conv-time"><?php echo $conv['last_message_time'] ? date('M d', strtotime($conv['last_message_time'])) : ''; ?></div>
                            </div>
                            <div class="conv-role"><?php echo ucfirst($conv['role']); ?></div>
                            <div class="conv-preview"><?php echo htmlspecialchars(substr($conv['last_message'] ?? '', 0, 40)); ?></div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="empty-state">
                    <i class="fas fa-inbox"></i>
                    <p>No conversations yet</p>
                </div>
            <?php endif; ?>
        </div>
        
        <!-- Chat Panel -->
        <div class="chat-panel">
            <?php if ($other_user): ?>
                <!-- Chat Header -->
                <div class="chat-header">
                    <img src="<?php echo htmlspecialchars($other_user['profile_pic']); ?>" alt="" onerror="this.src='<?php echo getAssetPath('images/placeholder-teacher.svg'); ?>'">
                    <div class="chat-header-info">
                        <h3><?php echo htmlspecialchars($other_user['name']); ?></h3>
                        <p><?php echo ucfirst($other_user['role']); ?> • <?php echo htmlspecialchars($other_user['email']); ?></p>
                    </div>
                </div>
                
                <!-- Messages -->
                <div class="messages-container" id="messagesContainer">
                    <?php if (!$can_message): ?>
                        <div class="permission-warning">
                            <i class="fas fa-info-circle"></i> 
                            <div>You can only reply to messages from students. Initiate contact through their profile.</div>
                        </div>
                    <?php endif; ?>
                    
                    <?php if (count($messages) > 0): ?>
                        <?php foreach ($messages as $msg): ?>
                            <div class="message <?php echo ($msg['sender_id'] == $user_id) ? 'sent' : 'received'; ?>">
                                <img src="<?php echo htmlspecialchars($msg['profile_pic']); ?>" alt="" class="message-pic" onerror="this.src='<?php echo getAssetPath('images/placeholder-teacher.svg'); ?>'">
                                <div>
                                    <div class="message-bubble"><?php echo htmlspecialchars($msg['message']); ?></div>
                                    <div class="message-time"><?php echo date('M d, H:i', strtotime($msg['sent_at'])); ?></div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="empty-state">
                            <i class="fas fa-comments"></i>
                            <p>No messages yet. Start the conversation!</p>
                        </div>
                    <?php endif; ?>
                </div>
                
                <!-- Input -->
                <?php if ($can_message): ?>
                    <form class="input-panel" id="messageForm" method="POST">
                        <textarea id="messageInput" name="message" placeholder="Type your message..." required></textarea>
                        <input type="hidden" name="receiver_id" value="<?php echo $other_user['id']; ?>">
                        <button type="submit" class="send-btn" title="Send message"><i class="fas fa-paper-plane"></i></button>
                    </form>
                <?php else: ?>
                    <div class="input-panel" style="background: #f8d7da; justify-content: center; text-align: center; color: #721c24;">
                        You cannot message this student until they message you first.
                    </div>
                <?php endif; ?>
            <?php else: ?>
                <div class="no-chat">
                    <div class="empty-state">
                        <i class="fas fa-envelope-open"></i>
                        <h3>Select a conversation to start messaging</h3>
                        <p style="font-size: 0.9rem; color: #ccc; margin-top: 10px;">Choose from your existing conversations or wait for new messages.</p>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
    </div>
</div>

<script>
    // Auto-scroll to latest message
    function scrollToBottom() {
        const container = document.getElementById('messagesContainer');
        if (container) {
            container.scrollTop = container.scrollHeight;
        }
    }
    
    scrollToBottom();
    
    // Handle message form submission (NO PAGE RELOAD - AJAX ONLY)
    const form = document.getElementById('messageForm');
    if (form) {
        form.addEventListener('submit', async function(e) {
            e.preventDefault();
            
            const formData = new FormData(form);
            const messageInput = document.getElementById('messageInput');
            const messageText = messageInput.value.trim();
            
            if (!messageText) return;
            
            // Disable send button to prevent double-submit
            const sendBtn = form.querySelector('button[type="submit"]');
            const originalHTML = sendBtn.innerHTML;
            sendBtn.disabled = true;
            sendBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
            
            try {
                const response = await fetch('send_message.php', {
                    method: 'POST',
                    body: formData
                });
                
                if (response.ok) {
                    const data = await response.json();
                    if (data.success) {
                        messageInput.value = '';
                        messageInput.focus();
                        
                        // Fetch and display new messages without reloading
                        setTimeout(() => {
                            fetchAndDisplayMessages();
                        }, 300);
                    } else {
                        alert('Error: ' + data.message);
                    }
                } else {
                    alert('Error sending message');
                }
            } catch (error) {
                console.error('Error:', error);
                alert('Error sending message');
            } finally {
                sendBtn.disabled = false;
                sendBtn.innerHTML = originalHTML;
            }
        });
    }
    
    // Fetch messages via AJAX without page reload
    let currentReceiverId = <?php echo $other_user ? $other_user['id'] : 'null'; ?>;
    
    async function fetchAndDisplayMessages() {
        if (!currentReceiverId) return;
        
        try {
            const response = await fetch('get_messages.php?receiver_id=' + currentReceiverId);
            if (response.ok) {
                const messages = await response.json();
                const container = document.getElementById('messagesContainer');
                
                if (container) {
                    // Clear old messages
                    const oldMessages = container.querySelectorAll('.message');
                    oldMessages.forEach(m => m.remove());
                    
                    // Add new messages
                    messages.forEach(msg => {
                        const isOwn = msg.sender_id == <?php echo $user_id; ?>;
                        const div = document.createElement('div');
                        div.className = 'message ' + (isOwn ? 'sent' : 'received');
                        
                        const timeStr = new Date(msg.sent_at).toLocaleDateString('en-US', {
                            month: 'short',
                            day: 'numeric',
                            hour: '2-digit',
                            minute: '2-digit'
                        });
                        
                        const placeholderImg = '<?php echo getAssetPath('images/placeholder-teacher.svg'); ?>';
                        div.innerHTML = `
                            <img src="${msg.profile_pic || placeholderImg}" alt="" class="message-pic" onerror="this.src='${placeholderImg}'">
                            <div>
                                <div class="message-bubble">${escapeHtml(msg.message)}</div>
                                <div class="message-time">${timeStr}</div>
                            </div>
                        `;
                        
                        container.appendChild(div);
                    });
                    
                    scrollToBottom();
                }
            }
        } catch (error) {
            console.error('Error fetching messages:', error);
        }
    }
    
    // Helper function to escape HTML (make it available globally)
    window.escapeHtml = function(text) {
        const map = {
            '&': '&amp;',
            '<': '&lt;',
            '>': '&gt;',
            '"': '&quot;',
            "'": '&#039;'
        };
        return text.replace(/[&<>"']/g, m => map[m]);
    };
    
    // Keep local reference for backwards compatibility
    function escapeHtml(text) {
        return window.escapeHtml(text);
    }
    
    // Fetch new messages every 3 seconds (SILENT - no page reload)
    setInterval(() => {
        if (document.getElementById('messagesContainer') && currentReceiverId) {
            fetchAndDisplayMessages();
        }
    }, 3000);
</script>

</body>
</html>
<?php
// End output buffering
ob_end_flush();
?>
