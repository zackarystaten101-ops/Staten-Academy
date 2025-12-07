<?php
/**
 * Assignments API
 * Handles homework creation, submission, and grading
 */

session_start();
require_once '../db.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$user_id = $_SESSION['user_id'];
$user_role = $_SESSION['user_role'] ?? '';
$action = $_GET['action'] ?? $_POST['action'] ?? '';

switch ($action) {
    case 'create':
        createAssignment($conn, $user_id, $user_role);
        break;
        
    case 'update':
        updateAssignment($conn, $user_id, $user_role);
        break;
        
    case 'delete':
        deleteAssignment($conn, $user_id, $user_role);
        break;
        
    case 'submit':
        submitAssignment($conn, $user_id);
        break;
        
    case 'grade':
        gradeAssignment($conn, $user_id, $user_role);
        break;
        
    case 'get_teacher':
        getTeacherAssignments($conn, $user_id);
        break;
        
    case 'get_student':
        getStudentAssignments($conn, $user_id);
        break;
        
    case 'get_one':
        getAssignment($conn, $user_id, $user_role);
        break;
        
    default:
        echo json_encode(['error' => 'Invalid action']);
}

/**
 * Create a new assignment (teacher only)
 */
function createAssignment($conn, $teacher_id, $role) {
    if ($role !== 'teacher' && $role !== 'admin') {
        echo json_encode(['error' => 'Only teachers can create assignments']);
        return;
    }
    
    $student_id = isset($_POST['student_id']) ? (int)$_POST['student_id'] : 0;
    $title = trim($_POST['title'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $due_date = $_POST['due_date'] ?? null;
    
    if (!$student_id || empty($title)) {
        echo json_encode(['error' => 'Student ID and title are required']);
        return;
    }
    
    $stmt = $conn->prepare("INSERT INTO assignments (teacher_id, student_id, title, description, due_date) VALUES (?, ?, ?, ?, ?)");
    $stmt->bind_param("iisss", $teacher_id, $student_id, $title, $description, $due_date);
    
    if ($stmt->execute()) {
        $assignment_id = $stmt->insert_id;
        
        // Notify student
        createNotification($conn, $student_id, 'assignment', 'New Assignment', 
            "You have a new assignment: $title", 'student-dashboard.php#homework');
        
        echo json_encode(['success' => true, 'assignment_id' => $assignment_id]);
    } else {
        echo json_encode(['error' => 'Failed to create assignment']);
    }
    
    $stmt->close();
}

/**
 * Update an assignment (teacher only)
 */
function updateAssignment($conn, $teacher_id, $role) {
    if ($role !== 'teacher' && $role !== 'admin') {
        echo json_encode(['error' => 'Unauthorized']);
        return;
    }
    
    $assignment_id = isset($_POST['assignment_id']) ? (int)$_POST['assignment_id'] : 0;
    $title = trim($_POST['title'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $due_date = $_POST['due_date'] ?? null;
    
    // Verify ownership
    $check = $conn->prepare("SELECT id FROM assignments WHERE id = ? AND teacher_id = ?");
    $check->bind_param("ii", $assignment_id, $teacher_id);
    $check->execute();
    if ($check->get_result()->num_rows === 0) {
        echo json_encode(['error' => 'Assignment not found']);
        $check->close();
        return;
    }
    $check->close();
    
    $stmt = $conn->prepare("UPDATE assignments SET title = ?, description = ?, due_date = ? WHERE id = ?");
    $stmt->bind_param("sssi", $title, $description, $due_date, $assignment_id);
    
    echo json_encode(['success' => $stmt->execute()]);
    $stmt->close();
}

/**
 * Delete an assignment (teacher only)
 */
function deleteAssignment($conn, $teacher_id, $role) {
    if ($role !== 'teacher' && $role !== 'admin') {
        echo json_encode(['error' => 'Unauthorized']);
        return;
    }
    
    $assignment_id = isset($_POST['assignment_id']) ? (int)$_POST['assignment_id'] : 0;
    
    $stmt = $conn->prepare("DELETE FROM assignments WHERE id = ? AND teacher_id = ?");
    $stmt->bind_param("ii", $assignment_id, $teacher_id);
    
    echo json_encode(['success' => $stmt->execute()]);
    $stmt->close();
}

/**
 * Submit assignment (student only)
 */
function submitAssignment($conn, $student_id) {
    $assignment_id = isset($_POST['assignment_id']) ? (int)$_POST['assignment_id'] : 0;
    $submission_text = trim($_POST['submission_text'] ?? '');
    
    // Verify this assignment belongs to the student
    $check = $conn->prepare("SELECT teacher_id, title FROM assignments WHERE id = ? AND student_id = ?");
    $check->bind_param("ii", $assignment_id, $student_id);
    $check->execute();
    $result = $check->get_result();
    
    if ($result->num_rows === 0) {
        echo json_encode(['error' => 'Assignment not found']);
        $check->close();
        return;
    }
    
    $assignment = $result->fetch_assoc();
    $check->close();
    
    // Handle file upload if present
    $file_path = null;
    if (isset($_FILES['submission_file']) && $_FILES['submission_file']['error'] === UPLOAD_ERR_OK) {
        $file = $_FILES['submission_file'];
        $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
        $allowed = ['pdf', 'doc', 'docx', 'txt', 'jpg', 'jpeg', 'png', 'zip'];
        
        if (in_array(strtolower($ext), $allowed) && $file['size'] <= 10 * 1024 * 1024) {
            $filename = 'assignment_' . $assignment_id . '_' . time() . '.' . $ext;
            
            // Determine upload directory - works for both localhost and cPanel
            $upload_base = dirname(__DIR__);
            $public_uploads_dir = $upload_base . DIRECTORY_SEPARATOR . 'public' . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'assignments';
            $flat_uploads_dir = $upload_base . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'assignments';
            
            if (is_dir($public_uploads_dir)) {
                $target_dir = $public_uploads_dir;
            } elseif (is_dir($flat_uploads_dir)) {
                $target_dir = $flat_uploads_dir;
            } else {
                $target_dir = is_dir($upload_base . DIRECTORY_SEPARATOR . 'public') ? $public_uploads_dir : $flat_uploads_dir;
                @mkdir($target_dir, 0755, true);
            }
            
            $target = $target_dir . DIRECTORY_SEPARATOR . $filename;
            
            // Security check: verify file was actually uploaded
            if (!is_uploaded_file($file['tmp_name'])) {
                echo json_encode(['error' => 'Invalid upload detected. Security check failed.']);
                return;
            }
            
            if (move_uploaded_file($file['tmp_name'], $target)) {
                $file_path = '/uploads/assignments/' . $filename;
            }
        }
    }
    
    // Update assignment
    $status = 'submitted';
    $stmt = $conn->prepare("UPDATE assignments SET submission_text = ?, submission_file = ?, status = ?, submitted_at = NOW() WHERE id = ?");
    $stmt->bind_param("sssi", $submission_text, $file_path, $status, $assignment_id);
    
    if ($stmt->execute()) {
        // Notify teacher
        $student_name = $_SESSION['user_name'] ?? 'A student';
        createNotification($conn, $assignment['teacher_id'], 'assignment', 'Assignment Submitted', 
            "$student_name submitted: {$assignment['title']}", 'teacher-dashboard.php#assignments');
        
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['error' => 'Failed to submit assignment']);
    }
    
    $stmt->close();
}

/**
 * Grade an assignment (teacher only)
 */
function gradeAssignment($conn, $teacher_id, $role) {
    if ($role !== 'teacher' && $role !== 'admin') {
        echo json_encode(['error' => 'Unauthorized']);
        return;
    }
    
    $assignment_id = isset($_POST['assignment_id']) ? (int)$_POST['assignment_id'] : 0;
    $grade = trim($_POST['grade'] ?? '');
    $feedback = trim($_POST['feedback'] ?? '');
    
    // Verify ownership
    $check = $conn->prepare("SELECT student_id, title FROM assignments WHERE id = ? AND teacher_id = ?");
    $check->bind_param("ii", $assignment_id, $teacher_id);
    $check->execute();
    $result = $check->get_result();
    
    if ($result->num_rows === 0) {
        echo json_encode(['error' => 'Assignment not found']);
        $check->close();
        return;
    }
    
    $assignment = $result->fetch_assoc();
    $check->close();
    
    $status = 'graded';
    $stmt = $conn->prepare("UPDATE assignments SET grade = ?, feedback = ?, status = ?, graded_at = NOW() WHERE id = ?");
    $stmt->bind_param("sssi", $grade, $feedback, $status, $assignment_id);
    
    if ($stmt->execute()) {
        // Notify student
        createNotification($conn, $assignment['student_id'], 'assignment', 'Assignment Graded', 
            "Your assignment '{$assignment['title']}' has been graded: $grade", 'student-dashboard.php#homework');
        
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['error' => 'Failed to grade assignment']);
    }
    
    $stmt->close();
}

/**
 * Get all assignments for a teacher
 */
function getTeacherAssignments($conn, $teacher_id) {
    $status = $_GET['status'] ?? 'all';
    $student_id = isset($_GET['student_id']) ? (int)$_GET['student_id'] : null;
    
    $sql = "SELECT a.*, u.name as student_name, u.profile_pic as student_pic
            FROM assignments a
            JOIN users u ON a.student_id = u.id
            WHERE a.teacher_id = ?";
    
    $params = [$teacher_id];
    $types = "i";
    
    if ($status !== 'all') {
        $sql .= " AND a.status = ?";
        $params[] = $status;
        $types .= "s";
    }
    
    if ($student_id) {
        $sql .= " AND a.student_id = ?";
        $params[] = $student_id;
        $types .= "i";
    }
    
    $sql .= " ORDER BY a.created_at DESC";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $assignments = [];
    while ($row = $result->fetch_assoc()) {
        $row['is_overdue'] = $row['due_date'] && strtotime($row['due_date']) < time() && $row['status'] === 'pending';
        $assignments[] = $row;
    }
    $stmt->close();
    
    echo json_encode($assignments);
}

/**
 * Get all assignments for a student
 */
function getStudentAssignments($conn, $student_id) {
    $status = $_GET['status'] ?? 'all';
    
    $sql = "SELECT a.*, u.name as teacher_name, u.profile_pic as teacher_pic
            FROM assignments a
            JOIN users u ON a.teacher_id = u.id
            WHERE a.student_id = ?";
    
    $params = [$student_id];
    $types = "i";
    
    if ($status !== 'all') {
        $sql .= " AND a.status = ?";
        $params[] = $status;
        $types .= "s";
    }
    
    $sql .= " ORDER BY CASE WHEN a.due_date IS NULL THEN 1 ELSE 0 END, a.due_date ASC";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $assignments = [];
    while ($row = $result->fetch_assoc()) {
        $row['is_overdue'] = $row['due_date'] && strtotime($row['due_date']) < time() && $row['status'] === 'pending';
        $assignments[] = $row;
    }
    $stmt->close();
    
    echo json_encode($assignments);
}

/**
 * Get single assignment
 */
function getAssignment($conn, $user_id, $role) {
    $assignment_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
    
    $stmt = $conn->prepare("
        SELECT a.*, 
               t.name as teacher_name, t.profile_pic as teacher_pic,
               s.name as student_name, s.profile_pic as student_pic
        FROM assignments a
        JOIN users t ON a.teacher_id = t.id
        JOIN users s ON a.student_id = s.id
        WHERE a.id = ? AND (a.teacher_id = ? OR a.student_id = ?)
    ");
    $stmt->bind_param("iii", $assignment_id, $user_id, $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($row = $result->fetch_assoc()) {
        echo json_encode($row);
    } else {
        echo json_encode(['error' => 'Assignment not found']);
    }
    
    $stmt->close();
}

/**
 * Create notification helper
 */
function createNotification($conn, $user_id, $type, $title, $message, $link = '') {
    $stmt = $conn->prepare("INSERT INTO notifications (user_id, type, title, message, link) VALUES (?, ?, ?, ?, ?)");
    if ($stmt) {
        $stmt->bind_param("issss", $user_id, $type, $title, $message, $link);
        $stmt->execute();
        $stmt->close();
    }
}

