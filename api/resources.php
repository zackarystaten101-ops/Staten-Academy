<?php
/**
 * Teacher Resources API
 * Handle resource uploads and management
 */

session_start();
require_once '../db.php';

if (!isset($_SESSION['user_id']) || ($_SESSION['user_role'] !== 'teacher' && $_SESSION['user_role'] !== 'admin')) {
    header("Location: ../login.php");
    exit;
}

$teacher_id = $_SESSION['user_id'];
$action = $_POST['action'] ?? $_GET['action'] ?? '';

switch ($action) {
    case 'upload':
        uploadResource($conn, $teacher_id);
        break;
    case 'delete':
        deleteResource($conn, $teacher_id);
        break;
    default:
        header("Location: ../teacher-dashboard.php#resources");
}

function uploadResource($conn, $teacher_id) {
    $title = trim($_POST['title'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $category = $_POST['category'] ?? 'general';
    $external_url = trim($_POST['external_url'] ?? '');
    
    if (empty($title)) {
        header("Location: ../teacher-dashboard.php#resources");
        exit;
    }
    
    $file_path = null;
    $file_type = null;
    
    // Handle file upload
    if (isset($_FILES['file']) && $_FILES['file']['error'] === UPLOAD_ERR_OK) {
        $file = $_FILES['file'];
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $allowed = ['pdf', 'doc', 'docx', 'ppt', 'pptx', 'txt', 'jpg', 'jpeg', 'png', 'gif'];
        
        if (in_array($ext, $allowed) && $file['size'] <= 10 * 1024 * 1024) {
            $filename = 'resource_' . $teacher_id . '_' . time() . '.' . $ext;
            $target = '../uploads/resources/' . $filename;
            
            if (!is_dir('../uploads/resources')) {
                mkdir('../uploads/resources', 0755, true);
            }
            
            if (move_uploaded_file($file['tmp_name'], $target)) {
                $file_path = 'uploads/resources/' . $filename;
                $file_type = $ext;
            }
        }
    }
    
    // Use external URL if no file uploaded
    if (!$file_path && $external_url) {
        $external_url = filter_var($external_url, FILTER_SANITIZE_URL);
    } else {
        $external_url = null;
    }
    
    $stmt = $conn->prepare("INSERT INTO teacher_resources (teacher_id, title, description, file_path, file_type, external_url, category) VALUES (?, ?, ?, ?, ?, ?, ?)");
    if ($stmt) {
        $stmt->bind_param("issssss", $teacher_id, $title, $description, $file_path, $file_type, $external_url, $category);
        $stmt->execute();
        $stmt->close();
    }
    
    header("Location: ../teacher-dashboard.php#resources");
    exit;
}

function deleteResource($conn, $teacher_id) {
    $resource_id = isset($_POST['resource_id']) ? (int)$_POST['resource_id'] : 0;
    
    // Get file path first
    $stmt = $conn->prepare("SELECT file_path FROM teacher_resources WHERE id = ? AND teacher_id = ?");
    $stmt->bind_param("ii", $resource_id, $teacher_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($row = $result->fetch_assoc()) {
        // Delete file if exists
        if ($row['file_path'] && file_exists('../' . $row['file_path'])) {
            unlink('../' . $row['file_path']);
        }
        
        // Delete record
        $del = $conn->prepare("DELETE FROM teacher_resources WHERE id = ?");
        $del->bind_param("i", $resource_id);
        $del->execute();
        $del->close();
    }
    $stmt->close();
    
    header("Location: ../teacher-dashboard.php#resources");
    exit;
}

