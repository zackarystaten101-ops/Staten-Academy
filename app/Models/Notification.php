<?php
require_once __DIR__ . '/../../core/Model.php';

class Notification extends Model {
    protected $table = 'notifications';
    
    public function __construct($conn) {
        parent::__construct($conn);
    }
    
    /**
     * Get notifications for a user
     */
    public function getByUser($userId, $limit = null) {
        $conditions = ['user_id' => $userId];
        return $this->all($conditions, 'created_at DESC', $limit);
    }
    
    /**
     * Get unread notifications
     */
    public function getUnread($userId) {
        return $this->all(['user_id' => $userId, 'is_read' => 0], 'created_at DESC');
    }
    
    /**
     * Get unread count
     */
    public function getUnreadCount($userId) {
        $stmt = $this->conn->prepare("SELECT COUNT(*) as count FROM {$this->table} WHERE user_id = ? AND is_read = 0");
        $stmt->bind_param("i", $userId);
        $stmt->execute();
        $result = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        return $result['count'] ?? 0;
    }
    
    /**
     * Mark as read
     */
    public function markAsRead($id) {
        return $this->update($id, ['is_read' => 1]);
    }
    
    /**
     * Create notification
     */
    public function createNotification($userId, $type, $title, $message = '', $link = '') {
        $data = [
            'user_id' => $userId,
            'type' => $type,
            'title' => $title,
            'message' => $message,
            'link' => $link,
            'is_read' => 0
        ];
        return $this->create($data);
    }
}

