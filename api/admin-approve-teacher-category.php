<?php
/**
 * Admin API: Approve Teacher Category
 * Allows admin to approve/reject teachers for specific categories
 */

session_start();
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../app/Views/components/dashboard-functions.php';

header('Content-Type: application/json');

// Check admin authentication
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
    http_response_code(403);
    echo json_encode(['error' => 'Unauthorized']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit();
}

$admin_id = $_SESSION['user_id'];
$teacher_id = intval($_POST['teacher_id'] ?? 0);
$category = $_POST['category'] ?? '';
$action = $_POST['action'] ?? 'approve'; // 'approve' or 'reject'

$allowed_categories = ['young_learners', 'adults', 'coding'];

if (!$teacher_id || !in_array($category, $allowed_categories)) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid teacher ID or category']);
    exit();
}

$conn->begin_transaction();
try {
    if ($action === 'approve') {
        // Check if category already exists
        $check_stmt = $conn->prepare("SELECT id, is_active FROM teacher_categories WHERE teacher_id = ? AND category = ?");
        $check_stmt->bind_param("is", $teacher_id, $category);
        $check_stmt->execute();
        $result = $check_stmt->get_result();
        $existing = $result->fetch_assoc();
        $check_stmt->close();
        
        if ($existing) {
            // Update existing category
            $stmt = $conn->prepare("UPDATE teacher_categories SET is_active = TRUE, approved_by = ?, approved_at = NOW() WHERE teacher_id = ? AND category = ?");
            $stmt->bind_param("iis", $admin_id, $teacher_id, $category);
        } else {
            // Insert new category
            $stmt = $conn->prepare("INSERT INTO teacher_categories (teacher_id, category, is_active, approved_by, approved_at) VALUES (?, ?, TRUE, ?, NOW())");
            $stmt->bind_param("isi", $teacher_id, $category, $admin_id);
        }
        $stmt->execute();
        $stmt->close();
        
        // Log to audit log
        $audit_sql = "INSERT INTO admin_audit_log (admin_id, action, target_type, target_id, details, ip_address) 
                     VALUES (?, 'category_approve', 'teacher_category', ?, ?, ?)";
        $audit_stmt = $conn->prepare($audit_sql);
        $details = json_encode(['teacher_id' => $teacher_id, 'category' => $category, 'action' => 'approved']);
        $ip_address = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        $audit_stmt->bind_param("iiss", $admin_id, $teacher_id, $details, $ip_address);
        $audit_stmt->execute();
        $audit_stmt->close();
        
        $conn->commit();
        echo json_encode(['success' => true, 'message' => 'Category approved successfully']);
    } else {
        // Reject/deactivate category
        $stmt = $conn->prepare("UPDATE teacher_categories SET is_active = FALSE WHERE teacher_id = ? AND category = ?");
        $stmt->bind_param("is", $teacher_id, $category);
        $stmt->execute();
        $stmt->close();
        
        // Log to audit log
        $audit_sql = "INSERT INTO admin_audit_log (admin_id, action, target_type, target_id, details, ip_address) 
                     VALUES (?, 'category_reject', 'teacher_category', ?, ?, ?)";
        $audit_stmt = $conn->prepare($audit_sql);
        $details = json_encode(['teacher_id' => $teacher_id, 'category' => $category, 'action' => 'rejected']);
        $ip_address = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        $audit_stmt->bind_param("iiss", $admin_id, $teacher_id, $details, $ip_address);
        $audit_stmt->execute();
        $audit_stmt->close();
        
        $conn->commit();
        echo json_encode(['success' => true, 'message' => 'Category rejected successfully']);
    }
} catch (Exception $e) {
    $conn->rollback();
    error_log("Category approval error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Failed to update category: ' . $e->getMessage()]);
}

