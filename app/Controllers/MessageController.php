<?php
require_once __DIR__ . '/../core/Controller.php';
require_once __DIR__ . '/../Models/Message.php';
require_once __DIR__ . '/../Models/User.php';

class MessageController extends Controller {
    private $messageModel;
    private $userModel;
    
    public function __construct($conn) {
        parent::__construct($conn);
        $this->messageModel = new Message($conn);
        $this->userModel = new User($conn);
    }
    
    /**
     * View message threads
     */
    public function threads() {
        $this->requireAuth();
        $user_id = $_SESSION['user_id'];
        
        $other_user_id = isset($_GET['user_id']) ? (int)$_GET['user_id'] : 0;
        
        // Get all threads
        $sql = "SELECT DISTINCT 
                    CASE 
                        WHEN m.sender_id = ? THEN m.receiver_id 
                        ELSE m.sender_id 
                    END as other_user_id,
                    u.name as other_user_name,
                    u.profile_pic as other_user_pic,
                    u.role as other_user_role,
                    MAX(m.sent_at) as last_message_at
                FROM messages m
                JOIN users u ON (CASE WHEN m.sender_id = ? THEN m.receiver_id ELSE m.sender_id END) = u.id
                WHERE m.sender_id = ? OR m.receiver_id = ?
                GROUP BY other_user_id, u.name, u.profile_pic, u.role
                ORDER BY last_message_at DESC";
        
        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param("iiii", $user_id, $user_id, $user_id, $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $threads = [];
        while ($row = $result->fetch_assoc()) {
            $threads[] = $row;
        }
        $stmt->close();
        
        // Get messages for selected thread
        $messages = [];
        $other_user = null;
        if ($other_user_id > 0) {
            $other_user = $this->userModel->find($other_user_id);
            $messages = $this->messageModel->getConversation($user_id, $other_user_id);
            
            // Mark messages as read
            $this->messageModel->markAsRead($other_user_id, $user_id);
        }
        
        $this->render('messages/threads', [
            'threads' => $threads,
            'messages' => $messages,
            'other_user' => $other_user,
            'other_user_id' => $other_user_id
        ]);
    }
    
    /**
     * Send message (AJAX)
     */
    public function send() {
        $this->requireAuth();
        header('Content-Type: application/json');
        
        $sender_id = $_SESSION['user_id'];
        $receiver_id = isset($_POST['receiver_id']) ? (int)$_POST['receiver_id'] : 0;
        $message = trim($_POST['message'] ?? '');
        
        if (!$receiver_id || empty($message)) {
            $this->json(['error' => 'Missing required fields'], 400);
        }
        
        $messageId = $this->messageModel->send($sender_id, $receiver_id, $message);
        
        if ($messageId) {
            $this->json(['success' => true, 'message_id' => $messageId]);
        } else {
            $this->json(['error' => 'Failed to send message'], 500);
        }
    }
}

