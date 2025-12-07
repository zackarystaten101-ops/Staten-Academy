<?php
session_start();
require_once 'db.php';

// Only allow this if already logged in as admin
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
    die("Access denied. Only admin can access this.");
}

$admin_email = "statenenglishacademy@gmail.com";

// Get admin user ID
$stmt = $conn->prepare("SELECT id FROM users WHERE email = ?");
$stmt->bind_param("s", $admin_email);
$stmt->execute();
$admin = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$admin) {
    die("Error: Admin account not found");
}

$admin_id = $admin['id'];

// Step 1: Remove teacher profile data (calendly_link, bio, application_status)
$stmt = $conn->prepare("UPDATE users SET calendly_link = NULL, bio = NULL, application_status = 'none', hours_taught = 0, hours_available = 0 WHERE id = ?");
$stmt->bind_param("i", $admin_id);
$stmt->execute();
$stmt->close();

// Step 2: Verify changes
$stmt = $conn->prepare("SELECT email, role, calendly_link, bio, application_status FROM users WHERE id = ?");
$stmt->bind_param("i", $admin_id);
$stmt->execute();
$result = $stmt->get_result()->fetch_assoc();
$stmt->close();

echo "<h2>✅ Admin Profile Cleaned Up!</h2>";
echo "<p><strong>Changes Made:</strong></p>";
echo "<ul>";
echo "<li>Role: <strong>" . strtoupper($result['role']) . "</strong> (unchanged)</li>";
echo "<li>Calendly Link: <strong>" . ($result['calendly_link'] ? $result['calendly_link'] : 'REMOVED') . "</strong></li>";
echo "<li>Bio: <strong>" . ($result['bio'] ? $result['bio'] : 'REMOVED') . "</strong></li>";
echo "<li>Application Status: <strong>" . $result['application_status'] . "</strong></li>";
echo "<li>Hours Taught: <strong>0</strong></li>";
echo "<li>Hours Available: <strong>0</strong></li>";
echo "</ul>";

echo "<p style='color: green; font-weight: bold;'>✅ Admin profile cleaned! Will NOT appear as teacher!</p>";

echo "<p><strong>What happened:</strong></p>";
echo "<ol>";
echo "<li>" . htmlspecialchars($admin_email) . " is now ADMIN only</li>";
echo "<li>Teacher profile fields removed (Calendly, Bio, Hours)</li>";
echo "<li>Will NOT show in teacher lists</li>";
echo "<li>Will NOT show on homepage teacher grid</li>";
echo "<li>Can still access all admin features</li>";
echo "</ol>";

echo "<p><a href='admin-dashboard.php'>Go to Admin Dashboard</a> | <a href='index.php'>Go to Homepage</a></p>";

$conn->close();
?>
