<?php
require_once __DIR__ . '/../Models/Notification.php';

class NotificationService {
    private $conn;
    private $notificationModel;
    
    public function __construct($conn) {
        $this->conn = $conn;
        $this->notificationModel = new Notification($conn);
    }
    
    /**
     * Create notification
     */
    public function create($userId, $type, $title, $message = '', $link = '') {
        return $this->notificationModel->createNotification($userId, $type, $title, $message, $link);
    }
    
    /**
     * Get notifications for user
     */
    public function getByUser($userId, $limit = null) {
        return $this->notificationModel->getByUser($userId, $limit);
    }
    
    /**
     * Get unread notifications
     */
    public function getUnread($userId) {
        return $this->notificationModel->getUnread($userId);
    }
    
    /**
     * Get unread count
     */
    public function getUnreadCount($userId) {
        return $this->notificationModel->getUnreadCount($userId);
    }
    
    /**
     * Mark as read
     */
    public function markAsRead($id, $userId) {
        // Verify ownership
        $notification = $this->notificationModel->find($id);
        if ($notification && $notification['user_id'] == $userId) {
            return $this->notificationModel->markAsRead($id);
        }
        return false;
    }
    
    /**
     * Mark all as read
     */
    public function markAllAsRead($userId) {
        $stmt = $this->conn->prepare("UPDATE notifications SET is_read = 1 WHERE user_id = ? AND is_read = 0");
        $stmt->bind_param("i", $userId);
        $result = $stmt->execute();
        $stmt->close();
        return $result;
    }
}

