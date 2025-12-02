<?php
session_start();
require_once 'db.php';

// Check if user with email zackarystaten101@gmail.com exists
$email = 'zackarystaten101@gmail.com';
$stmt = $conn->prepare("SELECT id, name, role FROM users WHERE email = ?");
$stmt->bind_param("s", $email);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    $user = $result->fetch_assoc();
    echo "User found: " . htmlspecialchars($user['name']) . "<br>";
    echo "Current Role: " . htmlspecialchars($user['role']) . "<br>";
    echo "User ID: " . $user['id'] . "<br><br>";
    
    if ($user['role'] !== 'admin') {
        echo "Updating role to admin...<br>";
        $update_stmt = $conn->prepare("UPDATE users SET role = 'admin' WHERE id = ?");
        $update_stmt->bind_param("i", $user['id']);
        if ($update_stmt->execute()) {
            echo "✓ Successfully updated user to admin role!<br>";
            echo "You can now access the Admin Dashboard and add classroom materials.";
        } else {
            echo "Error: " . $conn->error;
        }
        $update_stmt->close();
    } else {
        echo "✓ User is already an admin!<br>";
    }
} else {
    echo "User with email zackarystaten101@gmail.com not found in database.";
}

$stmt->close();
$conn->close();
?>
