<?php
/**
 * Shared Materials API
 * Handle shared materials uploads and management for teachers
 * All teachers can add materials, all teachers can view, only admins can edit/delete
 */

session_start();
require_once '../db.php';

if (!isset($_SESSION['user_id']) || ($_SESSION['user_role'] !== 'teacher' && $_SESSION['user_role'] !== 'admin')) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

$user_id = $_SESSION['user_id'];
$user_role = $_SESSION['user_role'];
$action = $_POST['action'] ?? $_GET['action'] ?? '';

switch ($action) {
    case 'add':
        addMaterial($conn, $user_id);
        break;
    case 'list':
        listMaterials($conn);
        break;
    case 'view':
        viewMaterial($conn);
        break;
    case 'delete':
        if ($user_role === 'admin') {
            deleteMaterial($conn);
        } else {
            http_response_code(403);
            echo json_encode(['success' => false, 'error' => 'Only admins can delete materials']);
        }
        break;
    case 'edit':
        if ($user_role === 'admin') {
            editMaterial($conn);
        } else {
            http_response_code(403);
            echo json_encode(['success' => false, 'error' => 'Only admins can edit materials']);
        }
        break;
    default:
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Invalid action']);
}

function addMaterial($conn, $user_id) {
    $title = trim($_POST['title'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $type = $_POST['type'] ?? 'file';
    $link_url = trim($_POST['link_url'] ?? '');
    $category = trim($_POST['category'] ?? 'general');
    $tags = trim($_POST['tags'] ?? '');
    
    // Validate category
    $valid_categories = ['general', 'kids', 'adults', 'coding'];
    if (!in_array($category, $valid_categories)) {
        $category = 'general';
    }
    
    if (empty($title)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Title is required']);
        exit;
    }
    
    $file_path = null;
    
    // Handle file upload
    if ($type === 'file' && isset($_FILES['file']) && $_FILES['file']['error'] === UPLOAD_ERR_OK) {
        $file = $_FILES['file'];
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $allowed = ['pdf', 'doc', 'docx', 'ppt', 'pptx', 'txt', 'jpg', 'jpeg', 'png', 'gif', 'mp4', 'mov', 'avi', 'webm', 'mp3', 'zip'];
        
        if (in_array($ext, $allowed) && $file['size'] <= 50 * 1024 * 1024) { // 50MB max
            $filename = 'material_' . $user_id . '_' . time() . '.' . $ext;
            
            // Determine upload directory - works for both localhost and cPanel
            $upload_base = dirname(__DIR__);
            $public_uploads_dir = $upload_base . DIRECTORY_SEPARATOR . 'public' . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'materials';
            $flat_uploads_dir = $upload_base . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'materials';
            
            if (is_dir($public_uploads_dir)) {
                $target_dir = $public_uploads_dir;
                $file_path = '/uploads/materials/' . $filename;
            } elseif (is_dir($flat_uploads_dir)) {
                $target_dir = $flat_uploads_dir;
                $file_path = '/uploads/materials/' . $filename;
            } else {
                // Create directory - prefer flat structure for cPanel
                $target_dir = is_dir($upload_base . DIRECTORY_SEPARATOR . 'public') ? $public_uploads_dir : $flat_uploads_dir;
                
                // Check if parent directory is writable before creating
                $parent_dir = dirname($target_dir);
                if (!is_dir($parent_dir)) {
                    http_response_code(500);
                    echo json_encode(['success' => false, 'error' => 'Parent directory does not exist. Please check server configuration.']);
                    error_log("Parent directory missing: " . $parent_dir);
                    exit;
                } elseif (!is_writable($parent_dir) && !@chmod($parent_dir, 0755)) {
                    http_response_code(500);
                    echo json_encode(['success' => false, 'error' => 'Cannot create upload directory. Parent directory is not writable.']);
                    error_log("Parent directory not writable: " . $parent_dir);
                    exit;
                } elseif (!@mkdir($target_dir, 0755, true)) {
                    http_response_code(500);
                    echo json_encode(['success' => false, 'error' => 'Failed to create upload directory. Please check server permissions.']);
                    error_log("Failed to create upload directory: " . $target_dir);
                    error_log("Parent directory: " . dirname($target_dir) . " (writable: " . (is_writable(dirname($target_dir)) ? 'yes' : 'no') . ")");
                    exit;
                }
                $file_path = '/uploads/materials/' . $filename;
            }
            
            // Ensure directory exists and is writable
            if (!is_dir($target_dir)) {
                http_response_code(500);
                echo json_encode(['success' => false, 'error' => 'Upload directory does not exist. Please contact administrator.']);
                error_log("Target directory missing: " . $target_dir);
                exit;
            }
            
            if (!is_writable($target_dir)) {
                // Try to fix permissions
                @chmod($target_dir, 0755);
                if (!is_writable($target_dir)) {
                    http_response_code(500);
                    echo json_encode(['success' => false, 'error' => 'Upload directory is not writable. Please contact administrator.']);
                    error_log("Upload directory not writable: " . $target_dir);
                    error_log("Current permissions: " . substr(sprintf('%o', fileperms($target_dir)), -4));
                    exit;
                }
            }
            
            $target = $target_dir . DIRECTORY_SEPARATOR . $filename;
            
            // Verify the uploaded file is valid
            if (!is_uploaded_file($file['tmp_name'])) {
                http_response_code(500);
                echo json_encode(['success' => false, 'error' => 'Invalid upload detected. Security check failed.']);
                error_log("Security check failed for: " . $file['tmp_name']);
                exit;
            }
            
            // Security check: verify file was actually uploaded
            if (!is_uploaded_file($file['tmp_name'])) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'Invalid upload detected. Security check failed.']);
                exit;
            }
            
            if (!@move_uploaded_file($file['tmp_name'], $target)) {
                http_response_code(500);
                echo json_encode(['success' => false, 'error' => 'Failed to save uploaded file. Please try again or contact administrator.']);
                error_log("Failed to move uploaded file: " . $file['tmp_name'] . " to " . $target);
                error_log("Source exists: " . (file_exists($file['tmp_name']) ? 'yes' : 'no'));
                error_log("Target directory writable: " . (is_writable($target_dir) ? 'yes' : 'no'));
                exit;
            }
            
            // Verify file was actually written
            if (!file_exists($target)) {
                http_response_code(500);
                echo json_encode(['success' => false, 'error' => 'File upload failed. File was not saved.']);
                error_log("File not found after move: " . $target);
                exit;
            }
            
            // Set proper permissions and verify
            @chmod($target, 0644);
            if (!is_readable($target)) {
                http_response_code(500);
                echo json_encode(['success' => false, 'error' => 'File uploaded but cannot be read. Please contact administrator.']);
                error_log("File not readable after upload: " . $target);
                exit;
            }
        } else {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Invalid file type or file too large']);
            exit;
        }
    }
    
    // For video type, use link_url
    if ($type === 'video' && !empty($link_url)) {
        $file_path = null;
    }
    
    // For link type, use link_url
    if ($type === 'link' && !empty($link_url)) {
        $file_path = null;
    }
    
    if ($type === 'file' && !$file_path && empty($link_url)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'File or link URL is required']);
        exit;
    }
    
    $stmt = $conn->prepare("INSERT INTO classroom_materials (title, description, file_path, link_url, type, uploaded_by, category, tags) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
    if ($stmt) {
        $stmt->bind_param("sssssiss", $title, $description, $file_path, $link_url, $type, $user_id, $category, $tags);
        if ($stmt->execute()) {
            $material_id = $conn->insert_id;
            $stmt->close();
            
            // Get the uploaded material with user info
            $result = $conn->query("
                SELECT m.*, u.name as uploaded_by_name 
                FROM classroom_materials m 
                LEFT JOIN users u ON m.uploaded_by = u.id 
                WHERE m.id = $material_id
            ");
            $material = $result->fetch_assoc();
            
            echo json_encode(['success' => true, 'material' => $material]);
        } else {
            $stmt->close();
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'Failed to save material']);
        }
    } else {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'Database error']);
    }
}

function listMaterials($conn) {
    // Get optional filter parameters
    $category = $_GET['category'] ?? null;
    $search = trim($_GET['search'] ?? '');
    $tags_filter = trim($_GET['tags'] ?? '');
    
    // Build query with filters
    $where = "m.is_deleted = 0";
    $params = [];
    $types = [];
    
    if ($category && in_array($category, ['general', 'kids', 'adults', 'coding'])) {
        $where .= " AND m.category = ?";
        $params[] = $category;
        $types[] = 's';
    }
    
    if ($search) {
        $where .= " AND (m.title LIKE ? OR m.description LIKE ?)";
        $search_param = '%' . $search . '%';
        $params[] = $search_param;
        $params[] = $search_param;
        $types[] = 's';
        $types[] = 's';
    }
    
    if ($tags_filter) {
        $where .= " AND m.tags LIKE ?";
        $params[] = '%' . $tags_filter . '%';
        $types[] = 's';
    }
    
    $query = "
        SELECT m.*, u.name as uploaded_by_name 
        FROM classroom_materials m 
        LEFT JOIN users u ON m.uploaded_by = u.id 
        WHERE $where
        ORDER BY m.usage_count DESC, m.created_at DESC
    ";
    
    if (!empty($params)) {
        $stmt = $conn->prepare($query);
        if ($stmt) {
            $stmt->bind_param(implode('', $types), ...$params);
            $stmt->execute();
            $result = $stmt->get_result();
        } else {
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'Database query error']);
            return;
        }
    } else {
        $result = $conn->query($query);
    }
    
    $materials = [];
    while ($row = $result->fetch_assoc()) {
        $materials[] = $row;
    }
    
    if (isset($stmt)) {
        $stmt->close();
    }
    
    echo json_encode(['success' => true, 'materials' => $materials]);
}

function viewMaterial($conn) {
    $material_id = (int)($_GET['id'] ?? 0);
    
    if ($material_id <= 0) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Invalid material ID']);
        exit;
    }
    
    // Only show non-deleted materials
    $stmt = $conn->prepare("
        SELECT m.*, u.name as uploaded_by_name 
        FROM classroom_materials m 
        LEFT JOIN users u ON m.uploaded_by = u.id 
        WHERE m.id = ? AND m.is_deleted = 0
    ");
    $stmt->bind_param("i", $material_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $material = $result->fetch_assoc();
    $stmt->close();
    
    if ($material) {
        echo json_encode(['success' => true, 'material' => $material]);
    } else {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Material not found']);
    }
}

function deleteMaterial($conn) {
    $material_id = (int)($_POST['material_id'] ?? 0);
    
    if ($material_id <= 0) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Invalid material ID']);
        exit;
    }
    
    // Verify material exists and is not already deleted
    $stmt = $conn->prepare("SELECT id FROM classroom_materials WHERE id = ? AND is_deleted = 0");
    $stmt->bind_param("i", $material_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        // Soft delete - DO NOT delete the file, just mark as deleted
        // Files are preserved for recovery and historical purposes
        $stmt->close();
        $stmt = $conn->prepare("UPDATE classroom_materials SET is_deleted = 1, deleted_at = NOW() WHERE id = ?");
        $stmt->bind_param("i", $material_id);
        
        if ($stmt->execute()) {
            $stmt->close();
            echo json_encode(['success' => true, 'message' => 'Material soft-deleted successfully']);
        } else {
            $stmt->close();
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'Failed to delete material']);
        }
    } else {
        $stmt->close();
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Material not found or already deleted']);
    }
}

function editMaterial($conn) {
    $material_id = (int)($_POST['material_id'] ?? 0);
    $title = trim($_POST['title'] ?? '');
    $description = trim($_POST['description'] ?? '');
    
    if ($material_id <= 0 || empty($title)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Invalid data']);
        exit;
    }
    
    $stmt = $conn->prepare("UPDATE classroom_materials SET title = ?, description = ? WHERE id = ?");
    $stmt->bind_param("ssi", $title, $description, $material_id);
    
    if ($stmt->execute()) {
        $stmt->close();
        echo json_encode(['success' => true]);
    } else {
        $stmt->close();
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'Failed to update material']);
    }
}

