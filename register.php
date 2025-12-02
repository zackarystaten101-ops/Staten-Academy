<?php
session_start();
require_once 'db.php';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $email = filter_input(INPUT_POST, 'email', FILTER_SANITIZE_EMAIL);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    $name = htmlspecialchars(trim($_POST['name']), ENT_QUOTES, 'UTF-8');

    if ($password !== $confirm_password) {
        die("Passwords do not match.");
    }

    // Check if email exists
    $stmt = $conn->prepare("SELECT id FROM users WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $stmt->store_result();

    if ($stmt->num_rows > 0) {
         die("Email already registered. <a href='login.php'>Login here</a>");
     }
    $stmt->close();

    // Hash password
    $hashed_password = password_hash($password, PASSWORD_DEFAULT);

    // Insert user
    $stmt = $conn->prepare("INSERT INTO users (name, email, password) VALUES (?, ?, ?)");
    $stmt->bind_param("sss", $name, $email, $hashed_password);

    if ($stmt->execute()) {
         $_SESSION['user_id'] = $stmt->insert_id;
         $_SESSION['user_name'] = $name;
         $_SESSION['user_role'] = 'student';
         header("Location: schedule.php");
         exit();
     } else {
         echo "Error: " . $stmt->error;
     }

    $stmt->close();
    $conn->close();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sign Up - Staten Academy</title>
    <link rel="stylesheet" href="styles.css">
    <link rel="stylesheet" href="css/mobile.css">
    <link rel="stylesheet" href="css/auth.css">
</head>
<body>
    <header class="site-header">
        <div class="header-left"><a href="index.php"><img src="logo.png" alt="Logo" class="site-logo"></a></div>
    </header>

    <div class="auth-container">
        <h2>Create Account</h2>
        
        <form action="register.php" method="POST">
            <div class="form-group">
                <label for="name">Full Name</label>
                <input type="text" id="name" name="name" required>
            </div>
            <div class="form-group">
                <label for="email">Email</label>
                <input type="email" id="email" name="email" required>
            </div>
            <div class="form-group">
                <label for="password">Password</label>
                <input type="password" id="password" name="password" required>
            </div>
            <div class="form-group">
                <label for="confirm_password">Confirm Password</label>
                <input type="password" id="confirm_password" name="confirm_password" required>
            </div>
            <button type="submit" class="btn-submit">Sign Up</button>
        </form>

        <p style="margin-top: 20px;">Already have an account? <a href="login.php">Login here</a></p>
    </div>
</body>
</html>
