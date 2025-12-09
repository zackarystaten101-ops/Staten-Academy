<?php
session_start();
require_once 'db.php';

// Upgrade visitor to student on successful payment
if (isset($_SESSION['user_id'])) {
    $user_id = $_SESSION['user_id'];
    
    // Check if user is a visitor
    $stmt = $conn->prepare("SELECT role, has_purchased_class FROM users WHERE id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();
    $stmt->close();
    
    if ($user && ($user['role'] === 'visitor' || $user['role'] === 'new_student' || !$user['has_purchased_class'])) {
        // Upgrade to student after purchase
        $now = date('Y-m-d H:i:s');
        $stmt = $conn->prepare("UPDATE users SET role = 'student', has_purchased_class = TRUE, first_purchase_date = ? WHERE id = ?");
        $stmt->bind_param("si", $now, $user_id);
        $stmt->execute();
        $stmt->close();
        
        // Update session
        $_SESSION['user_role'] = 'student';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payment Successful - Staten Academy</title>
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
    <style>
        .message-box {
            max-width: 600px;
            margin: 100px auto;
            text-align: center;
            padding: 40px;
            background: white;
            border-radius: 10px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
        }
        .success-icon {
            color: #28a745;
            font-size: 4rem;
            margin-bottom: 20px;
        }
        .btn {
            display: inline-block;
            background: #004080;
            color: white;
            padding: 10px 20px;
            text-decoration: none;
            border-radius: 5px;
            margin-top: 20px;
        }
        .btn:hover {
            background: #0056b3;
        }
    </style>
</head>
<body>
    <div class="message-box">
        <div class="success-icon">✓</div>
        <h1>Payment Successful!</h1>
        <?php if (isset($_GET['test_student'])): ?>
            <p style="background: #d4edda; color: #155724; padding: 10px; border-radius: 5px; margin: 10px 0;">
                <strong>Test Account:</strong> Your plan has been activated without payment.
            </p>
        <?php else: ?>
            <p>Thank you for your purchase. We have received your payment.</p>
            <p>We will contact you shortly to schedule your lesson.</p>
        <?php endif; ?>
        <?php if (isset($_SESSION['user_id'])): ?>
            <a href="student-dashboard.php" class="btn">Go to Dashboard</a>
        <?php else: ?>
            <a href="index.php" class="btn">Return to Home</a>
        <?php endif; ?>
    </div>
</body>
</html>

