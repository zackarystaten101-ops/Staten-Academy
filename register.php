<?php
// Start output buffering to prevent headers already sent errors
ob_start();

// Load environment configuration first
if (!defined('DB_HOST')) {
    require_once __DIR__ . '/env.php';
}

// Start session before any output
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Enable error reporting based on APP_DEBUG (after session start)
if (defined('APP_DEBUG') && APP_DEBUG === true) {
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
}

// Load database connection
require_once __DIR__ . '/db.php';

// Check if database connection is successful
if (!isset($conn) || $conn->connect_error) {
    die("Database connection failed. Please check your database configuration.");
}

$register_error = '';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $email = filter_input(INPUT_POST, 'email', FILTER_SANITIZE_EMAIL);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    $name = htmlspecialchars(trim($_POST['name']), ENT_QUOTES, 'UTF-8');

    $register_error = '';
    
    // Validate inputs
    if (empty($email) || empty($password) || empty($confirm_password) || empty($name)) {
        $register_error = "All fields are required.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $register_error = "Please enter a valid email address.";
    } elseif (strlen($password) < 6) {
        $register_error = "Password must be at least 6 characters long.";
    } elseif ($password !== $confirm_password) {
        $register_error = "Passwords do not match.";
    } elseif (strlen($name) < 2) {
        $register_error = "Name must be at least 2 characters long.";
    }
    
    // Check if email exists
    if (empty($register_error)) {
        $stmt = $conn->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $stmt->store_result();

        if ($stmt->num_rows > 0) {
            $register_error = "Email already registered. <a href='login.php'>Login here</a>";
        }
        $stmt->close();
    }

    // Hash password and create account
    if (empty($register_error)) {
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);

            // Insert user with new_student role
            $stmt = $conn->prepare("INSERT INTO users (name, email, password, role) VALUES (?, ?, ?, 'new_student')");
            $stmt->bind_param("sss", $name, $email, $hashed_password);

        if ($stmt->execute()) {
            $_SESSION['user_id'] = $stmt->insert_id;
            $_SESSION['user_name'] = $name;
            $_SESSION['user_role'] = 'new_student'; // New users start as new_student, become student after purchase
            $_SESSION['profile_pic'] = getAssetPath('images/placeholder-teacher.svg');
            $stmt->close();
            // Clear output buffer before redirect
            ob_end_clean();
            header("Location: schedule.php");
            exit();
        } else {
            $register_error = "Error creating account. Please try again or contact support.";
            error_log("Registration error: " . $stmt->error);
        }
        $stmt->close();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=5.0, user-scalable=yes">
    <title>Sign Up - Staten Academy</title>
    <?php
    // Ensure getAssetPath is available
    if (!function_exists('getAssetPath')) {
        if (file_exists(__DIR__ . '/app/Views/components/dashboard-functions.php')) {
            require_once __DIR__ . '/app/Views/components/dashboard-functions.php';
        } else {
            function getAssetPath($asset) {
                $asset = ltrim($asset, '/');
                if (strpos($asset, 'assets/') === 0) {
                    $assetPath = $asset;
                } else {
                    $assetPath = 'assets/' . $asset;
                }
                return '/' . $assetPath;
            }
        }
    }
    ?>
    <link rel="stylesheet" href="<?php echo getAssetPath('styles.css'); ?>">
    <link rel="stylesheet" href="<?php echo getAssetPath('css/mobile.css'); ?>">
    <link rel="stylesheet" href="<?php echo getAssetPath('css/auth.css'); ?>">
    <!-- MODERN SHADOWS - To disable, comment out the line below -->
    <link rel="stylesheet" href="<?php echo getAssetPath('css/modern-shadows.css'); ?>">
</head>
<body>
    <header class="site-header">
        <div class="header-left">
            <a href="index.php" style="text-decoration: none; display: flex; align-items: center;">
                <img src="<?php echo getAssetPath('logo.png'); ?>" alt="Staten Academy logo" class="site-logo">
            </a>
        </div>
        <div class="header-center">
            <div class="branding">
                <h1 class="site-title">Staten Academy</h1>
            </div>
        </div>
    </header>

    <div class="auth-container">
        <h2>Create Account</h2>
        
        <?php if (!empty($register_error)): ?>
            <div class="alert-error" style="background: #f8d7da; color: #721c24; padding: 12px; border-radius: 8px; margin-bottom: 20px; border-left: 4px solid #dc3545;">
                <i class="fas fa-exclamation-circle"></i> <?php echo $register_error; ?>
            </div>
        <?php endif; ?>
        
        <form action="register.php" method="POST" id="registerForm">
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
