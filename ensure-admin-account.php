<?php
/**
 * Ensure Admin Account Exists
 * Creates or updates the admin account with specified credentials
 * Run this once to set up the admin account
 */

require_once 'db.php';

$admin_email = 'statenenglishacademy@gmail.com';
$admin_password = '123456789';
$admin_name = 'Admin';

// Check if admin account exists
$stmt = $conn->prepare("SELECT id, role, password FROM users WHERE email = ?");
$stmt->bind_param("s", $admin_email);
$stmt->execute();
$result = $stmt->get_result();
$admin = $result->fetch_assoc();
$stmt->close();

if ($admin) {
    // Admin exists - update password and role if needed
    $hashed_password = password_hash($admin_password, PASSWORD_DEFAULT);
    
    $update_stmt = $conn->prepare("UPDATE users SET password = ?, role = 'admin', name = ? WHERE email = ?");
    $update_stmt->bind_param("sss", $hashed_password, $admin_name, $admin_email);
    
    if ($update_stmt->execute()) {
        echo "✓ Admin account updated successfully!\n";
        echo "Email: $admin_email\n";
        echo "Password: $admin_password\n";
        echo "Role: admin\n";
    } else {
        echo "✗ Error updating admin account: " . $update_stmt->error . "\n";
    }
    $update_stmt->close();
} else {
    // Admin doesn't exist - create it
    $hashed_password = password_hash($admin_password, PASSWORD_DEFAULT);
    
    $insert_stmt = $conn->prepare("INSERT INTO users (email, password, name, role, application_status) VALUES (?, ?, ?, 'admin', 'approved')");
    $insert_stmt->bind_param("sss", $admin_email, $hashed_password, $admin_name);
    
    if ($insert_stmt->execute()) {
        echo "✓ Admin account created successfully!\n";
        echo "Email: $admin_email\n";
        echo "Password: $admin_password\n";
        echo "Role: admin\n";
    } else {
        echo "✗ Error creating admin account: " . $insert_stmt->error . "\n";
    }
    $insert_stmt->close();
}

$conn->close();
?>



