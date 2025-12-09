<?php
/**
 * Plans API Endpoints
 * Handles plan-related requests for the three-track system
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
require_once __DIR__ . '/../app/Models/SubscriptionPlan.php';

// Check authentication for POST requests
$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? null;

if ($method === 'POST' && !isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Not authenticated']);
    exit();
}

$planModel = new SubscriptionPlan($conn);

// Route requests
switch ($method) {
    case 'GET':
        handleGet($action, $planModel);
        break;
    case 'POST':
        handlePost($action, $planModel);
        break;
    default:
        http_response_code(405);
        echo json_encode(['error' => 'Method not allowed']);
        break;
}

function handleGet($action, $planModel) {
    global $conn;
    
    switch ($action) {
        case 'by-track':
            $track = $_GET['track'] ?? null;
            
            if (!$track || !in_array($track, ['kids', 'adults', 'coding'])) {
                http_response_code(400);
                echo json_encode(['error' => 'Valid track required (kids, adults, coding)']);
                return;
            }
            
            $plans = $planModel->getPlansByTrack($track);
            echo json_encode(['success' => true, 'plans' => $plans]);
            break;
            
        case 'details':
            $planId = $_GET['plan_id'] ?? null;
            
            if (!$planId) {
                http_response_code(400);
                echo json_encode(['error' => 'plan_id required']);
                return;
            }
            
            $plan = $planModel->getPlan($planId);
            if ($plan) {
                echo json_encode(['success' => true, 'plan' => $plan]);
            } else {
                http_response_code(404);
                echo json_encode(['error' => 'Plan not found']);
            }
            break;
            
        default:
            http_response_code(400);
            echo json_encode(['error' => 'Invalid action']);
            break;
    }
}

function handlePost($action, $planModel) {
    global $conn;
    
    $input = json_decode(file_get_contents('php://input'), true);
    $userId = $_SESSION['user_id'];
    $userRole = $_SESSION['user_role'] ?? 'student';
    
    switch ($action) {
        case 'enroll':
            // Only students can enroll
            if ($userRole !== 'student' && $userRole !== 'new_student') {
                http_response_code(403);
                echo json_encode(['error' => 'Only students can enroll in plans']);
                return;
            }
            
            $planId = $input['plan_id'] ?? null;
            $track = $input['track'] ?? null;
            
            if (!$planId || !$track) {
                http_response_code(400);
                echo json_encode(['error' => 'plan_id and track required']);
                return;
            }
            
            // Verify plan exists and matches track
            $plan = $planModel->getPlan($planId);
            if (!$plan) {
                http_response_code(404);
                echo json_encode(['error' => 'Plan not found']);
                return;
            }
            
            if ($plan['track'] !== $track) {
                http_response_code(400);
                echo json_encode(['error' => 'Plan does not match selected track']);
                return;
            }
            
            // Check if plan_id column exists
            $col_check = $conn->query("SHOW COLUMNS FROM users LIKE 'plan_id'");
            $plan_id_exists = $col_check && $col_check->num_rows > 0;
            
            // Update user's plan and track
            if ($plan_id_exists) {
                $stmt = $conn->prepare("UPDATE users SET plan_id = ?, learning_track = ?, subscription_status = 'active', subscription_start_date = NOW() WHERE id = ?");
                $stmt->bind_param("isi", $planId, $track, $userId);
            } else {
                $stmt = $conn->prepare("UPDATE users SET learning_track = ?, subscription_status = 'active', subscription_start_date = NOW() WHERE id = ?");
                $stmt->bind_param("si", $track, $userId);
            }
            
            if ($stmt->execute()) {
                // Update role if new_student
                if ($userRole === 'new_student') {
                    $updateRoleStmt = $conn->prepare("UPDATE users SET role = 'student' WHERE id = ?");
                    $updateRoleStmt->bind_param("i", $userId);
                    $updateRoleStmt->execute();
                    $updateRoleStmt->close();
                    $_SESSION['user_role'] = 'student';
                }
                
                echo json_encode(['success' => true, 'message' => 'Plan enrollment successful']);
            } else {
                http_response_code(500);
                echo json_encode(['error' => 'Failed to enroll in plan']);
            }
            $stmt->close();
            break;
            
        default:
            http_response_code(400);
            echo json_encode(['error' => 'Invalid action']);
            break;
    }
}

