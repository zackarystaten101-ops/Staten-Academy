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

// Only logged-in users can access
if (!isset($_SESSION['user_id'])) {
    ob_end_clean(); // Clear output buffer before redirect
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$user_role = $_SESSION['user_role'];

// Fetch user info
$stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
$stmt->close();

$message = '';
$error = '';

// Handle Form Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $subject = trim($_POST['subject'] ?? '');
    $message_text = trim($_POST['message'] ?? '');
    
    if (empty($subject)) {
        $error = 'Subject is required.';
    } elseif (empty($message_text)) {
        $error = 'Message is required.';
    } else {
        // Insert support message
        $stmt = $conn->prepare("INSERT INTO support_messages (sender_id, sender_role, subject, message) VALUES (?, ?, ?, ?)");
        $stmt->bind_param("isss", $user_id, $user_role, $subject, $message_text);
        
        if ($stmt->execute()) {
            $message = 'Your message has been sent to all admins. They will review and respond shortly.';
            // Clear form
            $_POST = [];
        } else {
            $error = 'Error sending message. Please try again.';
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
    <meta name="theme-color" content="#004080">
    <meta name="mobile-web-app-capable" content="yes">
    <title>Contact Support - Staten Academy</title>
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
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        body { 
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); 
            margin: 0; 
            padding: 20px;
            min-height: 100vh;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        .container { 
            max-width: 500px; 
            margin: 0 auto; 
            background: white; 
            padding: 40px; 
            border-radius: 10px; 
            box-shadow: 0 10px 30px rgba(0,0,0,0.2);
        }
        
        .header { 
            text-align: center; 
            margin-bottom: 30px; 
        }
        
        .header h1 { 
            color: #004080; 
            margin: 0 0 10px 0; 
            font-size: 2rem;
        }
        
        .header p { 
            color: #666; 
            margin: 0; 
            font-size: 0.95rem;
        }
        
        .form-group { 
            margin-bottom: 20px; 
        }
        
        label { 
            display: block; 
            margin-bottom: 8px; 
            color: #555; 
            font-weight: 600;
        }
        
        input[type="text"],
        textarea { 
            width: 100%; 
            padding: 12px; 
            border: 2px solid #ddd; 
            border-radius: 5px; 
            font-family: inherit;
            font-size: 1rem;
            box-sizing: border-box;
            transition: border-color 0.3s;
        }
        
        input[type="text"]:focus,
        textarea:focus { 
            outline: none; 
            border-color: #667eea;
        }
        
        textarea { 
            resize: vertical; 
            min-height: 150px;
        }
        
        .btn-submit { 
            width: 100%; 
            padding: 12px; 
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); 
            color: white; 
            border: none; 
            border-radius: 5px; 
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: transform 0.2s, box-shadow 0.2s;
        }
        
        .btn-submit:hover { 
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.2);
        }
        
        .message { 
            padding: 15px; 
            border-radius: 5px; 
            margin-bottom: 20px;
            text-align: center;
        }
        
        .message.success { 
            background: #d4edda; 
            color: #155724; 
            border: 1px solid #c3e6cb;
        }
        
        .message.error { 
            background: #f8d7da; 
            color: #721c24; 
            border: 1px solid #f5c6cb;
        }
        
        .back-link { 
            text-align: center; 
            margin-top: 20px;
        }
        
        .back-link a { 
            color: #667eea; 
            text-decoration: none; 
            font-weight: 600;
        }
        
        .back-link a:hover { 
            text-decoration: underline;
        }
        
        .role-badge { 
            display: inline-block; 
            background: #e1f0ff; 
            color: #004080; 
            padding: 5px 10px; 
            border-radius: 3px; 
            font-size: 0.85rem; 
            margin-left: 10px;
        }
    </style>
</head>
<body>

<div class="container">
    <div class="header">
        <h1><i class="fas fa-envelope"></i> Contact Support</h1>
        <p>Send a message to our admin team</p>
    </div>
    
    <?php if (!empty($message)): ?>
        <div class="message success">
            <strong>Success!</strong><br>
            <?php echo htmlspecialchars($message); ?>
        </div>
    <?php endif; ?>
    
    <?php if (!empty($error)): ?>
        <div class="message error">
            <strong>Error:</strong><br>
            <?php echo htmlspecialchars($error); ?>
        </div>
    <?php endif; ?>
    
    <form method="POST">
        <div class="form-group">
            <label>Your Name</label>
            <input type="text" value="<?php echo htmlspecialchars($user['name']); ?>" disabled>
            <small style="color: #999;">
                <span class="role-badge"><?php echo ucfirst($user_role); ?></span>
            </small>
        </div>
        
        <div class="form-group">
            <label>Subject <span style="color: red;">*</span></label>
            <input type="text" name="subject" placeholder="What is your issue about?" required value="<?php echo htmlspecialchars($_POST['subject'] ?? ''); ?>">
        </div>
        
        <div class="form-group">
            <label>Message <span style="color: red;">*</span></label>
            <textarea name="message" placeholder="Please describe your issue in detail..." required><?php echo htmlspecialchars($_POST['message'] ?? ''); ?></textarea>
        </div>
        
        <button type="submit" class="btn-submit"><i class="fas fa-paper-plane"></i> Send Message</button>
    </form>
    
    <div class="back-link">
        <?php 
        if ($user_role === 'student' || $user_role === 'new_student') {
            echo '<a href="student-dashboard.php"><i class="fas fa-arrow-left"></i> Back to Dashboard</a>';
        } elseif ($user_role === 'teacher') {
            echo '<a href="teacher-dashboard.php"><i class="fas fa-arrow-left"></i> Back to Dashboard</a>';
        } elseif ($user_role === 'admin') {
            echo '<a href="admin-dashboard.php"><i class="fas fa-arrow-left"></i> Back to Dashboard</a>';
        } else {
            echo '<a href="index.php"><i class="fas fa-arrow-left"></i> Back to Home</a>';
        }
        ?>
    </div>
</div>

</body>
</html>
<?php
// End output buffering
ob_end_flush();
?>
