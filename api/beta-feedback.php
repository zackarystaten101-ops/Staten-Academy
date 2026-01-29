<?php
/**
 * Beta Feedback API
 * Handles feedback collection from beta testers
 */

session_start();
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../app/Helpers/SecurityHelper.php';

header('Content-Type: application/json');

// Set security headers
SecurityHelper::setSecurityHeaders();

// Rate limiting
$identifier = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
if (!SecurityHelper::checkRateLimit($identifier, 10, 60)) {
    http_response_code(429);
    echo json_encode(['success' => false, 'error' => 'Too many requests. Please try again later.']);
    exit;
}

$action = $_GET['action'] ?? $_POST['action'] ?? '';

switch ($action) {
    case 'submit':
        submitFeedback($conn);
        break;
    
    case 'list':
        listFeedback($conn);
        break;
    
    default:
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Invalid action']);
}

/**
 * Submit feedback
 */
function submitFeedback($conn) {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        echo json_encode(['success' => false, 'error' => 'Method not allowed']);
        exit;
    }
    
    // Verify CSRF token if user is logged in
    if (isset($_SESSION['user_id'])) {
        $token = $_POST['csrf_token'] ?? '';
        if (!SecurityHelper::verifyCSRFToken($token)) {
            http_response_code(403);
            echo json_encode(['success' => false, 'error' => 'Invalid CSRF token']);
            exit;
        }
    }
    
    $user_id = $_SESSION['user_id'] ?? null;
    $feedback_type = SecurityHelper::sanitizeInput($_POST['feedback_type'] ?? 'general');
    $category = SecurityHelper::sanitizeInput($_POST['category'] ?? 'other');
    $title = SecurityHelper::sanitizeInput($_POST['title'] ?? '');
    $description = SecurityHelper::sanitizeInput($_POST['description'] ?? '');
    $priority = SecurityHelper::sanitizeInput($_POST['priority'] ?? 'medium');
    $page_url = SecurityHelper::sanitizeInput($_POST['page_url'] ?? '');
    $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';
    $ip_address = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    
    if (empty($title) || empty($description)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Title and description are required']);
        exit;
    }
    
    // Insert feedback
    $sql = "INSERT INTO beta_feedback 
            (user_id, feedback_type, category, title, description, priority, page_url, user_agent, ip_address, status) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending')";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("issssssss", $user_id, $feedback_type, $category, $title, $description, $priority, $page_url, $user_agent, $ip_address);
    
    if ($stmt->execute()) {
        $feedback_id = $conn->insert_id;
        echo json_encode([
            'success' => true,
            'message' => 'Thank you for your feedback!',
            'feedback_id' => $feedback_id
        ]);
    } else {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'Failed to submit feedback']);
    }
    
    $stmt->close();
}

/**
 * List feedback (admin only)
 */
function listFeedback($conn) {
    if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'Unauthorized']);
        exit;
    }
    
    $status = $_GET['status'] ?? 'all';
    $category = $_GET['category'] ?? 'all';
    $limit = (int)($_GET['limit'] ?? 50);
    
    $sql = "SELECT bf.*, u.name as user_name, u.email as user_email 
            FROM beta_feedback bf
            LEFT JOIN users u ON bf.user_id = u.id
            WHERE 1=1";
    
    $params = [];
    $types = "";
    
    if ($status !== 'all') {
        $sql .= " AND bf.status = ?";
        $params[] = $status;
        $types .= "s";
    }
    
    if ($category !== 'all') {
        $sql .= " AND bf.category = ?";
        $params[] = $category;
        $types .= "s";
    }
    
    $sql .= " ORDER BY bf.created_at DESC LIMIT ?";
    $params[] = $limit;
    $types .= "i";
    
    $stmt = $conn->prepare($sql);
    if (count($params) > 0) {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $result = $stmt->get_result();
    
    $feedback = [];
    while ($row = $result->fetch_assoc()) {
        $feedback[] = $row;
    }
    
    $stmt->close();
    
    echo json_encode([
        'success' => true,
        'feedback' => $feedback
    ]);
}
