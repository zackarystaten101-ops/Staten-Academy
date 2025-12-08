<?php
/**
 * Script to create a test student account
 * Run this once to create the test student, then delete this file for security
 */

require_once __DIR__ . '/db.php';

$email = 'student@statenacademy.com';
$password = '123456789';
$name = 'Test Student';
$role = 'student';

// Check if student already exists
$stmt = $conn->prepare("SELECT id FROM users WHERE email = ?");
$stmt->bind_param("s", $email);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    // Update existing user to ensure they're a student
    $user = $result->fetch_assoc();
    $user_id = $user['id'];
    
    $hashed_password = password_hash($password, PASSWORD_DEFAULT);
    $update_stmt = $conn->prepare("UPDATE users SET name = ?, password = ?, role = 'student', has_purchased_class = TRUE WHERE id = ?");
    $update_stmt->bind_param("ssi", $name, $hashed_password, $user_id);
    
    if ($update_stmt->execute()) {
        echo "Test student account updated successfully!\n";
        echo "Email: $email\n";
        echo "Password: $password\n";
        echo "Role: $role\n";
    } else {
        echo "Error updating test student: " . $update_stmt->error . "\n";
    }
    $update_stmt->close();
} else {
    // Create new student with purchased status
    $hashed_password = password_hash($password, PASSWORD_DEFAULT);
    $insert_stmt = $conn->prepare("INSERT INTO users (name, email, password, role, has_purchased_class) VALUES (?, ?, ?, 'student', TRUE)");
    $insert_stmt->bind_param("sss", $name, $hashed_password, $email);
    
    if ($insert_stmt->execute()) {
        echo "Test student account created successfully!\n";
        echo "Email: $email\n";
        echo "Password: $password\n";
        echo "Role: $role\n";
        echo "User ID: " . $insert_stmt->insert_id . "\n";
    } else {
        echo "Error creating test student: " . $insert_stmt->error . "\n";
    }
    $insert_stmt->close();
}

$stmt->close();
$conn->close();

echo "\nNOTE: Please delete this file (create_test_student.php) after running it for security reasons.\n";

