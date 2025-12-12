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

// #region agent log helper
if (!function_exists('agent_debug_log')) {
    function agent_debug_log($hypothesisId, $location, $message, $data = []) {
        $payload = [
            'sessionId' => 'debug-session',
            'runId' => 'run1',
            'hypothesisId' => $hypothesisId,
            'location' => $location,
            'message' => $message,
            'data' => $data,
            'timestamp' => round(microtime(true) * 1000),
        ];
        $line = json_encode($payload);
        if ($line) {
            @file_put_contents(__DIR__ . '/.cursor/debug.log', $line . PHP_EOL, FILE_APPEND);
        }
    }
}
// #endregion

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
        
        // Get track and plan_id from POST or URL parameters
        $track = $_POST['track'] ?? $_GET['track'] ?? null;
        $plan_id = $_POST['plan_id'] ?? $_GET['plan_id'] ?? null;
        
        // Validate track if provided
        if ($track && !in_array($track, ['kids', 'adults', 'coding'])) {
            $track = null;
        }
        
        // Convert plan_id to integer if provided
        if ($plan_id) {
            $plan_id = (int)$plan_id;
            if ($plan_id <= 0) {
                $plan_id = null;
            }
        } else {
            $plan_id = null;
        }
        
        // Check if plan_id column exists
        $col_check = $conn->query("SHOW COLUMNS FROM users LIKE 'plan_id'");
        $plan_id_exists = $col_check && $col_check->num_rows > 0;
        
        // Insert user with new_student role, track, and plan_id (if column exists)
        if ($plan_id_exists && $plan_id) {
            $sql = "INSERT INTO users (name, email, password, role, learning_track, plan_id) VALUES (?, ?, ?, 'new_student', ?, ?)";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("ssssi", $name, $email, $hashed_password, $track, $plan_id);
        } elseif ($plan_id_exists) {
            $sql = "INSERT INTO users (name, email, password, role, learning_track, plan_id) VALUES (?, ?, ?, 'new_student', ?, NULL)";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("ssss", $name, $email, $hashed_password, $track);
        } else {
            // plan_id column doesn't exist yet, insert without it
            $sql = "INSERT INTO users (name, email, password, role, learning_track) VALUES (?, ?, ?, 'new_student', ?)";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("ssss", $name, $email, $hashed_password, $track);
        }

        if ($stmt->execute()) {
            $newUserId = $stmt->insert_id;
            $_SESSION['user_id'] = $newUserId;
            $_SESSION['user_name'] = $name;
            $_SESSION['user_role'] = 'new_student'; // New users start as new_student, become student after purchase
            $_SESSION['profile_pic'] = getAssetPath('images/placeholder-teacher.svg');

            agent_debug_log('H3', 'register.php:success', 'registration success', [
                'user_id' => $newUserId,
                'track' => $track,
                'plan_id' => $plan_id,
            ]);

            $stmt->close();
            // Clear output buffer before redirect
            ob_end_clean();
            
            // Redirect to student dashboard (will show todo list)
            // Plan selection will be handled via todo list or they can complete it from the plan page
            header("Location: student-dashboard.php");
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
                <img src="<?php echo getLogoPath(); ?>" alt="Staten Academy logo" class="site-logo">
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
        
        <?php 
        $track = $_GET['track'] ?? null;
        $plan_id = $_GET['plan_id'] ?? null;
        if ($track): ?>
            <div class="alert-info" style="background: #d1ecf1; color: #0c5460; padding: 12px; border-radius: 8px; margin-bottom: 20px; border-left: 4px solid #17a2b8;">
                <i class="fas fa-info-circle"></i> You're signing up for the <strong><?php echo ucfirst($track); ?></strong> track.
            </div>
        <?php endif; ?>
        
        <form action="register.php<?php echo $track ? '?track=' . urlencode($track) . ($plan_id ? '&plan_id=' . urlencode($plan_id) : '') : ''; ?>" method="POST" id="registerForm">
            <?php if ($track): ?>
                <input type="hidden" name="track" value="<?php echo htmlspecialchars($track); ?>">
            <?php endif; ?>
            <?php if ($plan_id): ?>
                <input type="hidden" name="plan_id" value="<?php echo htmlspecialchars($plan_id); ?>">
            <?php endif; ?>
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
