<?php
require_once __DIR__ . '/../../core/Model.php';

class Message extends Model {
    protected $table = 'messages';
    
    public function __construct($conn) {
        parent::__construct($conn);
    }
    
    /**
     * Get conversation between two users
     */
    public function getConversation($userId1, $userId2) {
        $sql = "SELECT * FROM {$this->table} 
                WHERE (sender_id = ? AND receiver_id = ?) OR (sender_id = ? AND receiver_id = ?)
                ORDER BY sent_at ASC";
        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param("iiii", $userId1, $userId2, $userId2, $userId1);
        $stmt->execute();
        $result = $stmt->get_result();
        $messages = [];
        while ($row = $result->fetch_assoc()) {
            $messages[] = $row;
        }
        $stmt->close();
        return $messages;
    }
    
    /**
     * Get unread messages for a user
     */
    public function getUnread($userId) {
        return $this->all(['receiver_id' => $userId, 'is_read' => 0], 'sent_at DESC');
    }
    
    /**
     * Get unread count
     */
    public function getUnreadCount($userId) {
        $stmt = $this->conn->prepare("SELECT COUNT(*) as count FROM {$this->table} WHERE receiver_id = ? AND is_read = 0");
        $stmt->bind_param("i", $userId);
        $stmt->execute();
        $result = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        return $result['count'] ?? 0;
    }
    
    /**
     * Mark messages as read
     */
    public function markAsRead($senderId, $receiverId) {
        $stmt = $this->conn->prepare("UPDATE {$this->table} SET is_read = 1 WHERE sender_id = ? AND receiver_id = ? AND is_read = 0");
        $stmt->bind_param("ii", $senderId, $receiverId);
        $result = $stmt->execute();
        $stmt->close();
        return $result;
    }
    
    /**
     * Send message
     */
    public function send($senderId, $receiverId, $message) {
        $data = [
            'sender_id' => $senderId,
            'receiver_id' => $receiverId,
            'message' => $message,
            'is_read' => 0
        ];
        return $this->create($data);
    }
}

