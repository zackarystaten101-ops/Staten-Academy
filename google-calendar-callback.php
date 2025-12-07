<?php
// Start output buffering to prevent "headers already sent" errors
ob_start();

// Load environment configuration first
if (!defined('DB_HOST')) {
    require_once __DIR__ . '/env.php';
}

// Start session before any output
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/google-calendar-config.php';

// Verify state parameter
if (!isset($_GET['state']) || $_GET['state'] !== ($_SESSION['google_oauth_state'] ?? '')) {
    die('Invalid state parameter');
}

// Check for errors
if (isset($_GET['error'])) {
    die('Authorization error: ' . htmlspecialchars($_GET['error']));
}

// Check for authorization code
if (!isset($_GET['code'])) {
    die('No authorization code provided');
}

$user_id = $_SESSION['google_oauth_user_id'] ?? null;
if (!$user_id) {
    die('User ID not found in session');
}

$api = new GoogleCalendarAPI($conn);
$token_response = $api->exchangeCodeForToken($_GET['code']);

if (isset($token_response['error'])) {
    die('Failed to exchange code: ' . $token_response['error']);
}

// Store tokens in database
$access_token = $token_response['access_token'];
$refresh_token = $token_response['refresh_token'] ?? null;
$expires_in = $token_response['expires_in'] ?? 3600;
$token_expiry = date('Y-m-d H:i:s', time() + $expires_in);

$stmt = $conn->prepare("
    UPDATE users 
    SET google_calendar_token = ?, 
        google_calendar_token_expiry = ?,
        google_calendar_refresh_token = ?
    WHERE id = ?
");
$stmt->bind_param("sssi", $access_token, $token_expiry, $refresh_token, $user_id);
$stmt->execute();
$stmt->close();

// Redirect based on user role
$stmt = $conn->prepare("SELECT role FROM users WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();
$stmt->close();

$redirect_url = 'index.php';
if ($user['role'] === 'teacher') {
    $redirect_url = 'teacher-calendar-setup.php';
} elseif ($user['role'] === 'admin') {
    $redirect_url = 'admin-dashboard.php';
}

ob_end_clean(); // Clear output buffer before redirect
header('Location: ' . $redirect_url . '?calendar=connected');
exit();
