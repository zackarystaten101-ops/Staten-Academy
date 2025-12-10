<?php
/**
 * Slot Requests API
 * Handles admin slot request management (get pending, accept, reject)
 */

header('Content-Type: application/json');

// Start session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Load environment configuration if not already loaded
if (!defined('DB_HOST')) {
    require_once __DIR__ . '/../env.php';
}

// Load dependencies
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../app/Views/components/dashboard-functions.php';

// Check authentication
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Not authenticated']);
    exit();
}

$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? null;
$userId = $_SESSION['user_id'];
$userRole = $_SESSION['user_role'] ?? 'student';

// Route requests
switch ($method) {
    case 'GET':
        handleGet($action, $userId, $userRole);
        break;
    case 'POST':
        handlePost($action, $userId, $userRole);
        break;
    default:
        http_response_code(405);
        echo json_encode(['error' => 'Method not allowed']);
        break;
}

function handleGet($action, $userId, $userRole) {
    global $conn;
    
    switch ($action) {
        case 'get-pending':
            // Teachers can get their pending requests
            if ($userRole !== 'teacher') {
                http_response_code(403);
                echo json_encode(['error' => 'Only teachers can view pending slot requests']);
                return;
            }
            
            $stmt = $conn->prepare("
                SELECT sr.*, 
                       a.name as admin_name, 
                       a.email as admin_email
                FROM admin_slot_requests sr
                JOIN users a ON sr.admin_id = a.id
                WHERE sr.teacher_id = ? AND sr.status = 'pending'
                ORDER BY sr.created_at DESC
            ");
            $stmt->bind_param("i", $userId);
            $stmt->execute();
            $result = $stmt->get_result();
            $requests = [];
            while ($row = $result->fetch_assoc()) {
                $requests[] = $row;
            }
            $stmt->close();
            
            echo json_encode(['success' => true, 'requests' => $requests]);
            break;
            
        default:
            http_response_code(400);
            echo json_encode(['error' => 'Invalid action']);
            break;
    }
}

function handlePost($action, $userId, $userRole) {
    global $conn;
    
    $input = json_decode(file_get_contents('php://input'), true);
    
    switch ($action) {
        case 'accept':
            // Teachers can accept slot requests
            if ($userRole !== 'teacher') {
                http_response_code(403);
                echo json_encode(['error' => 'Only teachers can accept slot requests']);
                return;
            }
            
            $requestId = $input['request_id'] ?? null;
            if (!$requestId) {
                http_response_code(400);
                echo json_encode(['error' => 'request_id required']);
                return;
            }
            
            // Get the request details
            $stmt = $conn->prepare("
                SELECT sr.*, a.name as admin_name
                FROM admin_slot_requests sr
                JOIN users a ON sr.admin_id = a.id
                WHERE sr.id = ? AND sr.teacher_id = ? AND sr.status = 'pending'
            ");
            $stmt->bind_param("ii", $requestId, $userId);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows === 0) {
                $stmt->close();
                http_response_code(404);
                echo json_encode(['error' => 'Slot request not found or already processed']);
                return;
            }
            
            $request = $result->fetch_assoc();
            $stmt->close();
            
            // Create availability slot based on request type
            if ($request['request_type'] === 'time_slot') {
                // Check if specific_date column exists
                $columnCheck = $conn->query("SHOW COLUMNS FROM teacher_availability LIKE 'specific_date'");
                $hasSpecificDate = $columnCheck && $columnCheck->num_rows > 0;
                
                $requestedDate = $request['requested_date'];
                $requestedTime = $request['requested_time'];
                $durationMinutes = $request['duration_minutes'];
                
                // Calculate end time
                $startTimeObj = new DateTime($requestedDate . ' ' . $requestedTime);
                $endTimeObj = clone $startTimeObj;
                $endTimeObj->modify("+{$durationMinutes} minutes");
                
                $startTime = $startTimeObj->format('H:i:s');
                $endTime = $endTimeObj->format('H:i:s');
                
                // Get day of week for the requested date
                $dayOfWeek = date('l', strtotime($requestedDate));
                
                // Create as one-time slot (specific date)
                if ($hasSpecificDate) {
                    $insertStmt = $conn->prepare("
                        INSERT INTO teacher_availability 
                        (teacher_id, day_of_week, specific_date, start_time, end_time, is_available) 
                        VALUES (?, ?, ?, ?, ?, 1)
                    ");
                    $insertStmt->bind_param("issss", $userId, $dayOfWeek, $requestedDate, $startTime, $endTime);
                } else {
                    // Fallback: create as weekly slot if specific_date column doesn't exist
                    $insertStmt = $conn->prepare("
                        INSERT INTO teacher_availability 
                        (teacher_id, day_of_week, start_time, end_time, is_available) 
                        VALUES (?, ?, ?, ?, 1)
                    ");
                    $insertStmt->bind_param("isss", $userId, $dayOfWeek, $startTime, $endTime);
                }
                
                if ($insertStmt->execute()) {
                    $slotId = $insertStmt->insert_id;
                    $insertStmt->close();
                    
                    // Update request status
                    $updateStmt = $conn->prepare("UPDATE admin_slot_requests SET status = 'accepted', responded_at = NOW() WHERE id = ?");
                    $updateStmt->bind_param("i", $requestId);
                    $updateStmt->execute();
                    $updateStmt->close();
                    
                    // Notify admin
                    if (function_exists('createNotification')) {
                        $adminId = $request['admin_id'];
                        createNotification(
                            $conn, 
                            $adminId, 
                            'slot_request_accepted', 
                            'Slot Request Accepted', 
                            "Your slot request for {$requestedDate} at {$requestedTime} has been accepted by the teacher.",
                            'admin-dashboard.php#slot-requests'
                        );
                    }
                    
                    echo json_encode([
                        'success' => true, 
                        'message' => 'Slot request accepted and availability created',
                        'slot_id' => $slotId
                    ]);
                } else {
                    $insertStmt->close();
                    http_response_code(500);
                    echo json_encode(['error' => 'Failed to create availability slot']);
                }
            } else {
                // Group class request - handle differently if needed
                http_response_code(400);
                echo json_encode(['error' => 'Group class requests not yet implemented in this endpoint']);
            }
            break;
            
        case 'reject':
            // Teachers can reject slot requests
            if ($userRole !== 'teacher') {
                http_response_code(403);
                echo json_encode(['error' => 'Only teachers can reject slot requests']);
                return;
            }
            
            $requestId = $input['request_id'] ?? null;
            $reason = $input['reason'] ?? null;
            
            if (!$requestId) {
                http_response_code(400);
                echo json_encode(['error' => 'request_id required']);
                return;
            }
            
            // Verify the request belongs to this teacher and is pending
            $stmt = $conn->prepare("
                SELECT sr.*, a.name as admin_name
                FROM admin_slot_requests sr
                JOIN users a ON sr.admin_id = a.id
                WHERE sr.id = ? AND sr.teacher_id = ? AND sr.status = 'pending'
            ");
            $stmt->bind_param("ii", $requestId, $userId);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows === 0) {
                $stmt->close();
                http_response_code(404);
                echo json_encode(['error' => 'Slot request not found or already processed']);
                return;
            }
            
            $request = $result->fetch_assoc();
            $stmt->close();
            
            // Update request status
            $updateStmt = $conn->prepare("UPDATE admin_slot_requests SET status = 'rejected', responded_at = NOW() WHERE id = ?");
            $updateStmt->bind_param("i", $requestId);
            $updateStmt->execute();
            $updateStmt->close();
            
            // Notify admin
            if (function_exists('createNotification')) {
                $adminId = $request['admin_id'];
                $requestDesc = $request['request_type'] === 'time_slot' 
                    ? "Slot request for {$request['requested_date']} at {$request['requested_time']}"
                    : "Group class request";
                    
                createNotification(
                    $conn, 
                    $adminId, 
                    'slot_request_rejected', 
                    'Slot Request Rejected', 
                    "Your {$requestDesc} has been rejected by the teacher." . ($reason ? " Reason: {$reason}" : ""),
                    'admin-dashboard.php#slot-requests'
                );
            }
            
            echo json_encode(['success' => true, 'message' => 'Slot request rejected']);
            break;
            
        default:
            http_response_code(400);
            echo json_encode(['error' => 'Invalid action']);
            break;
    }
}

