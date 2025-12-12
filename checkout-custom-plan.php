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

// Check if user is logged in
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'student') {
    header('Location: login.php');
    exit;
}

// Load environment and database
if (!defined('DB_HOST')) {
    require_once __DIR__ . '/env.php';
}
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/app/Views/components/dashboard-functions.php';

$user_id = $_SESSION['user_id'];
$plan_id = isset($_GET['plan_id']) ? (int)$_GET['plan_id'] : 0;

// Fetch custom plan
$plan = null;
if ($plan_id > 0) {
    $stmt = $conn->prepare("SELECT * FROM custom_plans WHERE id = ? AND user_id = ? AND status = 'draft'");
    $stmt->bind_param("ii", $plan_id, $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $plan = $result->fetch_assoc();
    $stmt->close();
}

if (!$plan) {
    ob_end_clean(); // Clear output buffer before redirect`n    header('Location: custom-plan.php');
    exit;
}

// Fetch course details
$selected_course_ids = json_decode($plan['selected_course_ids'], true) ?: [];
$courses = [];
if (!empty($selected_course_ids)) {
    $placeholders = implode(',', array_fill(0, count($selected_course_ids), '?'));
    $stmt = $conn->prepare("SELECT * FROM course_categories WHERE id IN ($placeholders)");
    $stmt->bind_param(str_repeat('i', count($selected_course_ids)), ...$selected_course_ids);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $courses[] = $row;
    }
    $stmt->close();
}

$user_role = $_SESSION['user_role'] ?? null;

// Handle checkout submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['confirm_checkout'])) {
    // For now, we'll need to create a Stripe subscription manually
    // Since custom plans have variable pricing, we may need to use Stripe's API to create a subscription
    // For this implementation, we'll redirect to a Stripe payment link or create a checkout session
    
    // Update plan status to active (will be completed after payment)
    $stmt = $conn->prepare("UPDATE custom_plans SET status = 'active' WHERE id = ?");
    $stmt->bind_param("i", $plan_id);
    $stmt->execute();
    $stmt->close();
    
    // TODO: Integrate with Stripe to create subscription with custom amount
    // For now, redirect to payment success
    header('Location: success.php?plan_type=custom&plan_id=' . $plan_id);
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Checkout - Custom Plan - Staten Academy</title>
    <link rel="stylesheet" href="<?php echo getAssetPath('styles.css'); ?>">
    <link rel="stylesheet" href="<?php echo getAssetPath('css/mobile.css'); ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .checkout-container {
            max-width: 800px;
            margin: 40px auto;
            padding: 20px;
        }
        .checkout-header {
            text-align: center;
            padding: 40px 20px;
            background: linear-gradient(135deg, #0b6cf5 0%, #0056b3 100%);
            color: white;
            border-radius: 12px;
            margin-bottom: 30px;
        }
        .checkout-header h1 {
            margin: 0 0 10px 0;
            font-size: 2.5rem;
        }
        .plan-summary {
            background: white;
            border-radius: 12px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.08);
            padding: 30px;
            margin-bottom: 20px;
        }
        .summary-section {
            margin-bottom: 25px;
            padding-bottom: 25px;
            border-bottom: 1px solid #e1e5e9;
        }
        .summary-section:last-child {
            border-bottom: none;
        }
        .summary-section h3 {
            color: #004080;
            margin-bottom: 15px;
        }
        .summary-row {
            display: flex;
            justify-content: space-between;
            padding: 8px 0;
            color: #666;
        }
        .summary-row strong {
            color: #333;
        }
        .price-breakdown {
            background: #f9fbff;
            border: 2px solid #0b6cf5;
            border-radius: 12px;
            padding: 25px;
            margin-top: 20px;
        }
        .price-row {
            display: flex;
            justify-content: space-between;
            padding: 12px 0;
            border-bottom: 1px solid #e1e5e9;
        }
        .price-row.total {
            border-top: 2px solid #0b6cf5;
            border-bottom: none;
            margin-top: 15px;
            padding-top: 20px;
            font-size: 1.5rem;
            font-weight: bold;
            color: #0b6cf5;
        }
        .btn-confirm {
            width: 100%;
            background: linear-gradient(135deg, #0b6cf5 0%, #0056b3 100%);
            color: white;
            padding: 18px;
            border: none;
            border-radius: 8px;
            font-size: 1.2rem;
            font-weight: bold;
            cursor: pointer;
            transition: transform 0.2s, box-shadow 0.2s;
            margin-top: 20px;
        }
        .btn-confirm:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(11, 108, 245, 0.3);
        }
        .course-item {
            padding: 8px 0;
            color: #666;
        }
    </style>
</head>
<body>
    <header class="site-header" role="banner">
        <div class="header-left">
            <a href="index.php"><img src="<?php echo getLogoPath(); ?>" alt="Staten Academy logo" class="site-logo"></a>
        </div>
        <div class="header-center">
            <div class="branding">
                <h1 class="site-title">Staten Academy</h1>
            </div>
        </div>
        
        <?php include 'header-user.php'; ?>
        
        <button id="menu-toggle" class="menu-toggle" aria-controls="mobile-menu" aria-expanded="false" aria-label="Open navigation menu">
            <span class="hamburger" aria-hidden="true"></span>
        </button>
        <div id="mobile-menu" class="mobile-menu" role="menu" aria-hidden="true">
            <button class="close-btn" id="mobile-close" aria-label="Close menu">✕</button>
            <a class="nav-btn" href="index.php">Home</a>
            <a class="nav-btn" href="student-dashboard.php">My Dashboard</a>
            <a class="nav-btn" href="logout.php">Logout</a>
        </div>
    </header>
    <div id="mobile-backdrop" class="mobile-backdrop" aria-hidden="true"></div>

    <div class="checkout-container">
        <div class="checkout-header">
            <h1>Review Your Custom Plan</h1>
            <p>Please review your plan details before proceeding to payment</p>
        </div>

        <form method="POST" action="">
            <div class="plan-summary">
                <div class="summary-section">
                    <h3><i class="fas fa-clock"></i> Plan Details</h3>
                    <div class="summary-row">
                        <span>Hours per week:</span>
                        <strong><?php echo $plan['hours_per_week']; ?> hours</strong>
                    </div>
                    <div class="summary-row">
                        <span>Teacher selection:</span>
                        <strong><?php echo $plan['choose_own_teacher'] ? 'Choose my own teacher ($30/hr)' : 'Teacher assigned ($28/hr)'; ?></strong>
                    </div>
                    <div class="summary-row">
                        <span>Base monthly rate:</span>
                        <strong>$<?php echo number_format($plan['base_monthly_price'], 2); ?></strong>
                    </div>
                </div>

                <?php if (!empty($courses)): ?>
                <div class="summary-section">
                    <h3><i class="fas fa-book"></i> Extra Courses (<?php echo count($courses); ?>)</h3>
                    <?php foreach ($courses as $course): ?>
                        <div class="course-item">
                            <i class="fas <?php echo htmlspecialchars($course['icon']); ?>" style="color: <?php echo htmlspecialchars($course['color']); ?>;"></i>
                            <?php echo htmlspecialchars($course['name']); ?>
                        </div>
                    <?php endforeach; ?>
                    <div class="summary-row" style="margin-top: 15px;">
                        <span>Extra courses total:</span>
                        <strong>$<?php echo number_format($plan['courses_extra'], 2); ?></strong>
                    </div>
                </div>
                <?php endif; ?>

                <?php if ($plan['group_classes_count'] > 0): ?>
                <div class="summary-section">
                    <h3><i class="fas fa-users"></i> Group Support Classes</h3>
                    <div class="summary-row">
                        <span>Number of group classes:</span>
                        <strong><?php echo $plan['group_classes_count']; ?> classes/month</strong>
                    </div>
                    <div class="summary-row">
                        <span>Group classes total:</span>
                        <strong>$<?php echo number_format($plan['group_classes_extra'], 2); ?></strong>
                    </div>
                </div>
                <?php endif; ?>

                <div class="price-breakdown">
                    <h3 style="margin-top: 0; color: #004080;">Price Breakdown</h3>
                    <div class="price-row">
                        <span>Base Plan:</span>
                        <span>$<?php echo number_format($plan['base_monthly_price'], 2); ?></span>
                    </div>
                    <?php if ($plan['courses_extra'] > 0): ?>
                    <div class="price-row">
                        <span>Extra Courses:</span>
                        <span>$<?php echo number_format($plan['courses_extra'], 2); ?></span>
                    </div>
                    <?php endif; ?>
                    <?php if ($plan['group_classes_extra'] > 0): ?>
                    <div class="price-row">
                        <span>Group Classes:</span>
                        <span>$<?php echo number_format($plan['group_classes_extra'], 2); ?></span>
                    </div>
                    <?php endif; ?>
                    <div class="price-row total">
                        <span>Total Monthly:</span>
                        <span>$<?php echo number_format($plan['total_monthly_price'], 2); ?></span>
                    </div>
                    <p style="text-align: center; margin-top: 15px; color: #666; font-size: 0.9rem;">
                        <i class="fas fa-info-circle"></i> This plan will be billed monthly. Cancel anytime.
                    </p>
                </div>

                <button type="submit" name="confirm_checkout" class="btn-confirm">
                    <i class="fas fa-lock"></i> Proceed to Payment
                </button>
            </div>
        </form>
    </div>

    <footer>
        <p>Contact us: info@statenacademy.com | Phone: +1 234 567 890</p>
        <p>&copy; <?php echo date('Y'); ?> Staten Academy. All rights reserved.</p>
    </footer>
    <script src="<?php echo getAssetPath('js/menu.js'); ?>" defer></script>
</body>
</html>

