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
    
    // Check if this is a trial payment (prefer URL parameter over session to avoid stale session data)
    $is_trial = (isset($_GET['type']) && $_GET['type'] === 'trial');
    
    if ($is_trial) {
        // Handle trial payment success
        $teacher_id = $_GET['teacher_id'] ?? $_SESSION['trial_teacher_id'] ?? null;
        
        if ($teacher_id) {
            // Trial credit should already be added by webhook, but verify
            require_once __DIR__ . '/app/Services/WalletService.php';
            require_once __DIR__ . '/app/Services/TrialService.php';
            
            $walletService = new WalletService($conn);
            $wallet = $walletService->getWalletBalance($user_id);
        }
        
        // Clear trial session variables after processing to prevent interference with future purchases
        unset($_SESSION['trial_teacher_id']);
        unset($_SESSION['trial_student_id']);
        unset($_SESSION['trial_success']);
    } else {
        // Handle plan purchase
        // Clear any lingering trial session variables to prevent false trial detection
        unset($_SESSION['trial_teacher_id']);
        unset($_SESSION['trial_student_id']);
        unset($_SESSION['trial_success']);
        
        $plan_id = $_GET['plan_id'] ?? $_SESSION['selected_plan_id'] ?? null;
        $track = $_GET['track'] ?? $_SESSION['selected_track'] ?? null;
        
        // Update user's plan_id and track if provided
        if ($plan_id) {
            $col_check = $conn->query("SHOW COLUMNS FROM users LIKE 'plan_id'");
            if ($col_check && $col_check->num_rows > 0) {
                $plan_stmt = $conn->prepare("UPDATE users SET plan_id = ? WHERE id = ?");
                $plan_stmt->bind_param("ii", $plan_id, $user_id);
                $plan_stmt->execute();
                $plan_stmt->close();
            }
        }
        
        if ($track && in_array($track, ['kids', 'adults', 'coding'])) {
            $track_stmt = $conn->prepare("UPDATE users SET learning_track = ? WHERE id = ?");
            $track_stmt->bind_param("si", $track, $user_id);
            $track_stmt->execute();
            $track_stmt->close();
        }
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
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        body {
            background: linear-gradient(135deg, #f0f7ff 0%, #ffffff 100%);
            min-height: 100vh;
            padding: 20px;
        }
        .message-box {
            max-width: 700px;
            margin: 50px auto;
            text-align: center;
            padding: 50px 40px;
            background: white;
            border-radius: 15px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.1);
        }
        .success-icon {
            color: #28a745;
            font-size: 5rem;
            margin-bottom: 20px;
            animation: scaleIn 0.5s ease-out;
        }
        @keyframes scaleIn {
            from { transform: scale(0); opacity: 0; }
            to { transform: scale(1); opacity: 1; }
        }
        .message-box h1 {
            color: #004080;
            margin-bottom: 15px;
            font-size: 2rem;
        }
        .message-box p {
            color: #666;
            font-size: 1.1rem;
            line-height: 1.6;
            margin-bottom: 15px;
        }
        .progress-steps {
            text-align: left;
            margin: 30px 0;
            padding: 25px;
            background: #f8f9fa;
            border-radius: 10px;
        }
        .progress-steps h3 {
            color: #004080;
            margin-bottom: 20px;
            text-align: center;
        }
        .step {
            display: flex;
            align-items: center;
            gap: 15px;
            padding: 15px;
            margin-bottom: 10px;
            background: white;
            border-radius: 8px;
            border-left: 4px solid #ddd;
            transition: all 0.3s;
        }
        .step.completed {
            border-left-color: #28a745;
            background: #f0fff4;
        }
        .step.current {
            border-left-color: #0b6cf5;
            background: #f0f7ff;
            box-shadow: 0 2px 8px rgba(11, 108, 245, 0.2);
        }
        .step-number {
            width: 35px;
            height: 35px;
            border-radius: 50%;
            background: #ddd;
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            flex-shrink: 0;
        }
        .step.completed .step-number {
            background: #28a745;
        }
        .step.current .step-number {
            background: #0b6cf5;
        }
        .step-content {
            flex: 1;
        }
        .step-title {
            font-weight: 600;
            color: #333;
            margin-bottom: 5px;
        }
        .step-desc {
            font-size: 0.9rem;
            color: #666;
        }
        .btn {
            display: inline-block;
            background: #0b6cf5;
            color: white;
            padding: 15px 35px;
            text-decoration: none;
            border-radius: 50px;
            margin-top: 20px;
            font-weight: 600;
            font-size: 1.1rem;
            transition: all 0.3s;
            box-shadow: 0 4px 15px rgba(11, 108, 245, 0.3);
        }
        .btn:hover {
            background: #0056b3;
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(11, 108, 245, 0.4);
        }
        .btn-secondary {
            background: white;
            color: #0b6cf5;
            border: 2px solid #0b6cf5;
            box-shadow: none;
            margin-left: 10px;
        }
        .btn-secondary:hover {
            background: #f0f7ff;
        }
        .test-badge {
            background: #d4edda;
            color: #155724;
            padding: 12px 20px;
            border-radius: 8px;
            margin: 15px 0;
            border-left: 4px solid #28a745;
        }
    </style>
</head>
<body>
    <div class="message-box">
        <div class="success-icon">
            <i class="fas fa-check-circle"></i>
        </div>
        <?php
        // Check if this is a trial payment (prefer URL parameter over session to avoid stale session data)
        $is_trial = (isset($_GET['type']) && $_GET['type'] === 'trial');
        $teacher_id = $_GET['teacher_id'] ?? null;
        $teacher_name = '';
        
        if ($is_trial && $teacher_id) {
            $teacher_stmt = $conn->prepare("SELECT name FROM users WHERE id = ?");
            $teacher_stmt->bind_param("i", $teacher_id);
            $teacher_stmt->execute();
            $teacher_result = $teacher_stmt->get_result();
            if ($teacher_result->num_rows > 0) {
                $teacher_data = $teacher_result->fetch_assoc();
                $teacher_name = $teacher_data['name'];
            }
            $teacher_stmt->close();
        }
        ?>
        
        <?php if ($is_trial): ?>
            <h1>Trial Lesson Purchased! 🎉</h1>
            <p>Your trial lesson credit has been added to your wallet. You can now book a lesson with <?php echo htmlspecialchars($teacher_name ?: 'your selected teacher'); ?>.</p>
        <?php else: ?>
            <h1>Payment Successful! 🎉</h1>
            <?php if (isset($_GET['test_student'])): ?>
                <div class="test-badge">
                    <i class="fas fa-info-circle"></i> <strong>Test Account:</strong> Your plan has been activated without payment.
                </div>
            <?php else: ?>
                <p>Thank you for your purchase! Your subscription is now active.</p>
            <?php endif; ?>
        <?php endif; ?>
        
        <?php if (isset($_SESSION['user_id'])): ?>
            <?php
            $user_id = $_SESSION['user_id'];
            
            if ($is_trial) {
                // For trial, show simple next steps
                $trial_used = false;
                $trial_stmt = $conn->prepare("SELECT trial_used FROM users WHERE id = ?");
                $trial_stmt->bind_param("i", $user_id);
                $trial_stmt->execute();
                $trial_result = $trial_stmt->get_result();
                if ($trial_result->num_rows > 0) {
                    $trial_data = $trial_result->fetch_assoc();
                    $trial_used = (bool)$trial_data['trial_used'];
                }
                $trial_stmt->close();
            } else {
                // Check onboarding status for plan purchases
                $stmt = $conn->prepare("SELECT plan_id, learning_track, assigned_teacher_id FROM users WHERE id = ?");
                $stmt->bind_param("i", $user_id);
                $stmt->execute();
                $user_data = $stmt->get_result()->fetch_assoc();
                $stmt->close();
                
                $has_plan = !empty($user_data['plan_id']);
                $has_needs = false;
                $has_teacher = !empty($user_data['assigned_teacher_id']);
                
                if ($has_plan) {
                    $needs_stmt = $conn->prepare("SELECT id FROM student_learning_needs WHERE student_id = ? AND completed = 1");
                    $needs_stmt->bind_param("i", $user_id);
                    $needs_stmt->execute();
                    $has_needs = $needs_stmt->get_result()->num_rows > 0;
                    $needs_stmt->close();
                }
            }
            ?>
            
            <?php if ($is_trial): ?>
                <div class="progress-steps">
                    <h3><i class="fas fa-list-check"></i> Next Steps</h3>
                    
                    <div class="step completed">
                        <div class="step-number">
                            <i class="fas fa-check"></i>
                        </div>
                        <div class="step-content">
                            <div class="step-title">Trial Lesson Purchased</div>
                            <div class="step-desc">Your $25 trial lesson credit has been added to your wallet</div>
                        </div>
                    </div>
                    
                    <div class="step current">
                        <div class="step-number">2</div>
                        <div class="step-content">
                            <div class="step-title">Book Your Trial Lesson</div>
                            <div class="step-desc">Schedule your trial lesson with <?php echo htmlspecialchars($teacher_name ?: 'your selected teacher'); ?></div>
                        </div>
                    </div>
                </div>
                
                <div style="margin-top: 30px;">
                    <?php if ($teacher_id): ?>
                        <a href="teacher-profile.php?id=<?php echo intval($teacher_id); ?>" class="btn">
                            <i class="fas fa-calendar-plus"></i> Book Trial Lesson Now
                        </a>
                    <?php else: ?>
                        <a href="index.php" class="btn">
                            <i class="fas fa-search"></i> Browse Teachers
                        </a>
                    <?php endif; ?>
                    <a href="student-dashboard.php" class="btn btn-secondary">
                        <i class="fas fa-home"></i> Go to Dashboard
                    </a>
                </div>
            <?php else: ?>
                <div class="progress-steps">
                    <h3><i class="fas fa-list-check"></i> Next Steps</h3>
                    
                    <div class="step <?php echo $has_plan ? 'completed' : 'current'; ?>">
                        <div class="step-number">
                            <?php if ($has_plan): ?>
                                <i class="fas fa-check"></i>
                            <?php else: ?>
                                1
                            <?php endif; ?>
                        </div>
                        <div class="step-content">
                            <div class="step-title">Complete Payment</div>
                            <div class="step-desc">Your payment has been processed successfully</div>
                        </div>
                    </div>
                    
                    <div class="step <?php echo $has_needs ? 'completed' : ($has_plan ? 'current' : ''); ?>">
                        <div class="step-number">
                            <?php if ($has_needs): ?>
                                <i class="fas fa-check"></i>
                            <?php else: ?>
                                2
                            <?php endif; ?>
                        </div>
                        <div class="step-content">
                            <div class="step-title">Add Your Learning Needs</div>
                            <div class="step-desc">Tell us about your goals so we can match you with the perfect teacher</div>
                        </div>
                    </div>
                    
                    <div class="step <?php echo $has_teacher ? 'completed' : ($has_needs ? 'current' : ''); ?>">
                        <div class="step-number">
                            <?php if ($has_teacher): ?>
                                <i class="fas fa-check"></i>
                            <?php else: ?>
                                3
                            <?php endif; ?>
                        </div>
                        <div class="step-content">
                            <div class="step-title">Get Assigned a Teacher</div>
                            <div class="step-desc">We'll match you with a teacher based on your learning needs (usually within 24-48 hours)</div>
                        </div>
                    </div>
                    
                    <div class="step <?php echo $has_teacher ? 'current' : ''; ?>">
                        <div class="step-number">4</div>
                        <div class="step-content">
                            <div class="step-title">Book Your First Lesson</div>
                            <div class="step-desc">Schedule your first class with your assigned teacher</div>
                        </div>
                    </div>
                </div>
                
                <div style="margin-top: 30px;">
                    <?php if (!$has_needs): ?>
                        <a href="student-dashboard.php#learning-needs" class="btn">
                            <i class="fas fa-arrow-right"></i> Add Learning Needs Now
                        </a>
                    <?php elseif (!$has_teacher): ?>
                        <a href="student-dashboard.php" class="btn">
                            <i class="fas fa-home"></i> Go to Dashboard
                        </a>
                        <p style="margin-top: 15px; color: #666; font-size: 0.9rem;">
                            <i class="fas fa-info-circle"></i> We're matching you with a teacher. You'll be notified when assigned!
                        </p>
                    <?php else: ?>
                        <a href="schedule.php" class="btn">
                            <i class="fas fa-calendar-plus"></i> Book Your First Lesson
                        </a>
                        <a href="student-dashboard.php" class="btn btn-secondary">
                            <i class="fas fa-home"></i> Go to Dashboard
                        </a>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        <?php else: ?>
            <p>Please log in to continue with your learning journey.</p>
            <a href="login.php" class="btn">Login</a>
            <a href="index.php" class="btn btn-secondary">Return to Home</a>
        <?php endif; ?>
    </div>
</body>
</html>

