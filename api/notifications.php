<?php
/**
 * Notifications API
 * Handles CRUD operations for user notifications
 */

session_start();
require_once '../db.php';

header('Content-Type: application/json');

// Check authentication
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$user_id = $_SESSION['user_id'];
$action = $_GET['action'] ?? $_POST['action'] ?? '';

switch ($action) {
    case 'recent':
        getRecentNotifications($conn, $user_id);
        break;
        
    case 'all':
        getAllNotifications($conn, $user_id);
        break;
        
    case 'read':
        markAsRead($conn, $user_id);
        break;
        
    case 'read_all':
        markAllAsRead($conn, $user_id);
        break;
        
    case 'delete':
        deleteNotification($conn, $user_id);
        break;
        
    case 'count':
        getUnreadCount($conn, $user_id);
        break;
        
    default:
        echo json_encode(['error' => 'Invalid action']);
}

/**
 * Get recent notifications (for dropdown)
 */
function getRecentNotifications($conn, $user_id) {
    $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 10;
    
    $stmt = $conn->prepare("
        SELECT id, type, title, message, link, is_read, created_at 
        FROM notifications 
        WHERE user_id = ? 
        ORDER BY created_at DESC 
        LIMIT ?
    ");
    
    if (!$stmt) {
        echo json_encode([]);
        return;
    }
    
    $stmt->bind_param("ii", $user_id, $limit);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $notifications = [];
    while ($row = $result->fetch_assoc()) {
        $row['time_ago'] = formatTimeAgo($row['created_at']);
        $row['is_read'] = (bool)$row['is_read'];
        $notifications[] = $row;
    }
    
    $stmt->close();
    echo json_encode($notifications);
}

/**
 * Get all notifications with pagination
 */
function getAllNotifications($conn, $user_id) {
    $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
    $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 20;
    $offset = ($page - 1) * $limit;
    
    // Get total count
    $countStmt = $conn->prepare("SELECT COUNT(*) as total FROM notifications WHERE user_id = ?");
    $countStmt->bind_param("i", $user_id);
    $countStmt->execute();
    $total = $countStmt->get_result()->fetch_assoc()['total'];
    $countStmt->close();
    
    // Get notifications
    $stmt = $conn->prepare("
        SELECT id, type, title, message, link, is_read, created_at 
        FROM notifications 
        WHERE user_id = ? 
        ORDER BY created_at DESC 
        LIMIT ? OFFSET ?
    ");
    
    $stmt->bind_param("iii", $user_id, $limit, $offset);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $notifications = [];
    while ($row = $result->fetch_assoc()) {
        $row['time_ago'] = formatTimeAgo($row['created_at']);
        $row['is_read'] = (bool)$row['is_read'];
        $notifications[] = $row;
    }
    
    $stmt->close();
    
    echo json_encode([
        'notifications' => $notifications,
        'total' => $total,
        'page' => $page,
        'pages' => ceil($total / $limit)
    ]);
}

/**
 * Mark single notification as read
 */
function markAsRead($conn, $user_id) {
    $id = isset($_GET['id']) ? (int)$_GET['id'] : (isset($_POST['id']) ? (int)$_POST['id'] : 0);
    
    if (!$id) {
        echo json_encode(['error' => 'Missing notification ID']);
        return;
    }
    
    $stmt = $conn->prepare("UPDATE notifications SET is_read = 1, read_at = NOW() WHERE id = ? AND user_id = ?");
    $stmt->bind_param("ii", $id, $user_id);
    $success = $stmt->execute();
    $stmt->close();
    
    echo json_encode(['success' => $success]);
}

/**
 * Mark all notifications as read
 */
function markAllAsRead($conn, $user_id) {
    $stmt = $conn->prepare("UPDATE notifications SET is_read = 1, read_at = NOW() WHERE user_id = ? AND is_read = 0");
    $stmt->bind_param("i", $user_id);
    $success = $stmt->execute();
    $affected = $stmt->affected_rows;
    $stmt->close();
    
    echo json_encode(['success' => $success, 'updated' => $affected]);
}

/**
 * Delete a notification
 */
function deleteNotification($conn, $user_id) {
    $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
    
    if (!$id) {
        echo json_encode(['error' => 'Missing notification ID']);
        return;
    }
    
    $stmt = $conn->prepare("DELETE FROM notifications WHERE id = ? AND user_id = ?");
    $stmt->bind_param("ii", $id, $user_id);
    $success = $stmt->execute();
    $stmt->close();
    
    echo json_encode(['success' => $success]);
}

/**
 * Get unread count
 */
function getUnreadCount($conn, $user_id) {
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM notifications WHERE user_id = ? AND is_read = 0");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $count = $stmt->get_result()->fetch_assoc()['count'];
    $stmt->close();
    
    echo json_encode(['count' => $count]);
}

/**
 * Format time ago string
 */
function formatTimeAgo($datetime) {
    $now = new DateTime();
    $date = new DateTime($datetime);
    $diff = $now->diff($date);
    
    if ($diff->y > 0) return $diff->y . ' year' . ($diff->y > 1 ? 's' : '') . ' ago';
    if ($diff->m > 0) return $diff->m . ' month' . ($diff->m > 1 ? 's' : '') . ' ago';
    if ($diff->d > 0) return $diff->d . ' day' . ($diff->d > 1 ? 's' : '') . ' ago';
    if ($diff->h > 0) return $diff->h . ' hour' . ($diff->h > 1 ? 's' : '') . ' ago';
    if ($diff->i > 0) return $diff->i . ' min' . ($diff->i > 1 ? 's' : '') . ' ago';
    return 'Just now';
}

