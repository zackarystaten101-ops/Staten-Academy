<?php
session_start();
require_once 'db.php';

// Only allow this if already logged in as admin
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
    die("Access denied. Only current admin can transfer role.");
}

$old_admin_email = "zackarystaten101@gmail.com";
$new_admin_email = "statenenglishacademy@gmail.com";

// Step 1: Check if old admin exists
$stmt = $conn->prepare("SELECT id, role FROM users WHERE email = ?");
$stmt->bind_param("s", $old_admin_email);
$stmt->execute();
$old_admin = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$old_admin) {
    die("Error: Account $old_admin_email not found");
}

// Step 2: Check if new admin account exists
$stmt = $conn->prepare("SELECT id, role FROM users WHERE email = ?");
$stmt->bind_param("s", $new_admin_email);
$stmt->execute();
$new_admin = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$new_admin) {
    die("Error: Account $new_admin_email not found. Please create this account first.");
}

// Step 3: Transfer admin role - Change old admin to teacher
$stmt = $conn->prepare("UPDATE users SET role = 'teacher' WHERE email = ?");
$stmt->bind_param("s", $old_admin_email);
if (!$stmt->execute()) {
    die("Error updating old admin: " . $stmt->error);
}
$stmt->close();

// Step 4: Transfer admin role - Change new account to admin
$stmt = $conn->prepare("UPDATE users SET role = 'admin' WHERE email = ?");
$stmt->bind_param("s", $new_admin_email);
if (!$stmt->execute()) {
    die("Error updating new admin: " . $stmt->error);
}
$stmt->close();

// Step 5: Verify changes
$stmt = $conn->prepare("SELECT email, role FROM users WHERE email IN (?, ?)");
$stmt->bind_param("ss", $old_admin_email, $new_admin_email);
$stmt->execute();
$result = $stmt->get_result();

echo "<h2>✅ Admin Role Transfer Complete!</h2>";
echo "<p><strong>Changes Made:</strong></p>";
echo "<ul>";

while ($row = $result->fetch_assoc()) {
    echo "<li>" . htmlspecialchars($row['email']) . " → Role: <strong>" . strtoupper($row['role']) . "</strong></li>";
}

echo "</ul>";

echo "<p style='color: green; font-weight: bold;'>✅ Role transfer successful!</p>";

echo "<p><strong>Next Steps:</strong></p>";
echo "<ol>";
echo "<li>Logout from $old_admin_email account</li>";
echo "<li>Login with $new_admin_email account</li>";
echo "<li>Verify admin features work</li>";
echo "<li>Delete this script: admin_role_transfer.php</li>";
echo "</ol>";

echo "<p><a href='admin-dashboard.php'>Go to Admin Dashboard</a> | <a href='logout.php'>Logout</a></p>";

$stmt->close();
$conn->close();
?>
