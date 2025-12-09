<?php
// Start output buffering
ob_start();

// Load environment configuration
if (!defined('DB_HOST')) {
    require_once __DIR__ . '/env.php';
}

// Start session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/app/Views/components/dashboard-functions.php';
require_once __DIR__ . '/app/Models/SubscriptionPlan.php';

// Ensure getAssetPath function is available
if (!function_exists('getAssetPath')) {
    require_once __DIR__ . '/app/Views/components/dashboard-functions.php';
}

$planModel = new SubscriptionPlan($conn);
$plans = $planModel->getPlansByTrack('adults');

// If no plans exist, create placeholder plans
if (empty($plans)) {
    $placeholderPlans = [
        ['name' => 'Economy', 'one_on_one_classes_per_week' => 1, 'group_practice_sessions_per_week' => 1, 'price' => 129.00, 'display_order' => 1],
        ['name' => 'Basic', 'one_on_one_classes_per_week' => 2, 'group_practice_sessions_per_week' => 2, 'price' => 229.00, 'display_order' => 2],
        ['name' => 'Pro', 'one_on_one_classes_per_week' => 3, 'group_practice_sessions_per_week' => 3, 'price' => 319.00, 'display_order' => 3],
        ['name' => 'Mega', 'one_on_one_classes_per_week' => 4, 'group_practice_sessions_per_week' => 3, 'price' => 399.00, 'display_order' => 4],
    ];
    $plans = $placeholderPlans;
}

$user_role = $_SESSION['user_role'] ?? 'guest';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Adult Classes Plans - Staten Academy</title>
    <link rel="stylesheet" href="<?php echo getAssetPath('styles.css'); ?>">
    <link rel="stylesheet" href="<?php echo getAssetPath('css/mobile.css'); ?>">
    <link rel="stylesheet" href="<?php echo getAssetPath('css/tracks.css'); ?>">
    <link rel="stylesheet" href="<?php echo getAssetPath('css/adults-theme.css'); ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        body {
            background: linear-gradient(135deg, #f0f7ff 0%, #e8f4ff 100%);
            min-height: 100vh;
        }
        .page-header {
            background: linear-gradient(135deg, #0b6cf5 0%, #004080 100%);
            color: white;
            padding: 60px 20px;
            text-align: center;
        }
        .page-header h1 {
            font-size: 3rem;
            margin-bottom: 10px;
            font-weight: 700;
        }
        .page-header p {
            font-size: 1.2rem;
            opacity: 0.95;
        }
        .plans-container {
            max-width: 1200px;
            margin: -40px auto 80px;
            padding: 0 20px;
            position: relative;
            z-index: 10;
        }
        .plans-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 30px;
            margin-top: 40px;
        }
        .plan-card {
            background: white;
            border-radius: 15px;
            padding: 40px 30px;
            box-shadow: 0 8px 30px rgba(11, 108, 245, 0.15);
            transition: all 0.3s ease;
            text-align: center;
            position: relative;
            border: 2px solid transparent;
        }
        .plan-card:hover {
            transform: translateY(-8px);
            box-shadow: 0 15px 50px rgba(11, 108, 245, 0.25);
            border-color: #0b6cf5;
        }
        .plan-card.featured {
            border-color: #0b6cf5;
            background: linear-gradient(135deg, #f0f7ff 0%, #ffffff 100%);
        }
        .plan-card.featured::before {
            content: 'POPULAR';
            position: absolute;
            top: -15px;
            left: 50%;
            transform: translateX(-50%);
            background: linear-gradient(135deg, #0b6cf5, #004080);
            color: white;
            padding: 5px 20px;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: bold;
        }
        .plan-name {
            font-size: 1.8rem;
            font-weight: 700;
            color: #004080;
            margin-bottom: 15px;
        }
        .plan-price {
            font-size: 3rem;
            font-weight: 700;
            color: #0b6cf5;
            margin: 20px 0;
        }
        .plan-price span {
            font-size: 1.2rem;
            color: #999;
            font-weight: normal;
        }
        .plan-features {
            list-style: none;
            padding: 0;
            margin: 30px 0;
            text-align: left;
        }
        .plan-features li {
            padding: 12px 0;
            color: #2d2d2d; /* Improved contrast: #555 -> #2d2d2d (WCAG AA compliant) */
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 1rem;
        }
        .plan-features li strong {
            color: #1a1a1a; /* Darker for numbers */
            font-weight: 700;
        }
        .plan-features li i {
            color: #0b6cf5;
            font-size: 1.2rem;
        }
        .plan-cta {
            display: block;
            background: linear-gradient(135deg, #0b6cf5, #004080);
            color: white !important;
            padding: 15px 30px;
            border-radius: 8px;
            font-weight: 600;
            font-size: 1.1rem;
            text-decoration: none;
            margin-top: 25px;
            transition: transform 0.2s, box-shadow 0.2s;
            border: none !important;
            cursor: pointer;
            width: 100%;
            text-align: center;
            box-sizing: border-box;
        }
        .plan-cta:hover {
            transform: scale(1.05);
            box-shadow: 0 8px 25px rgba(11, 108, 245, 0.4);
            color: white !important;
        }
        button.plan-cta {
            font-family: inherit;
        }
        .back-link {
            display: inline-block;
            color: white;
            text-decoration: none;
            margin-top: 20px;
            font-size: 1.1rem;
            opacity: 0.9;
        }
        .back-link:hover {
            opacity: 1;
            text-decoration: underline;
        }
        @media (max-width: 768px) {
            .plans-grid {
                grid-template-columns: 1fr;
            }
            .page-header h1 {
                font-size: 2rem;
            }
        }
    </style>
</head>
<body class="track-adults">
    <header class="site-header" role="banner">
        <div class="header-left">
            <a href="index.php"><img src="<?php echo getAssetPath('logo.png'); ?>" alt="Staten Academy logo" class="site-logo"></a>
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
            <a class="nav-btn" href="index.php">
                <svg class="nav-icon" viewBox="0 0 24 24"><path fill="#06385a" d="M10 20v-6h4v6h5v-8h3L12 3 2 12h3v8z"/></svg>
                <span class="nav-label">Home</span>
            </a>
            <?php if (isset($_SESSION['user_id'])): ?>
                <?php if ($user_role === 'teacher' || $user_role === 'admin'): ?>
                    <a class="nav-btn" href="teacher-dashboard.php" style="background-color: #28a745; color: white; border: none;">
                        <span class="nav-label">Dashboard</span>
                    </a>
                <?php elseif ($user_role === 'student' || $user_role === 'new_student'): ?>
                    <a class="nav-btn" href="student-dashboard.php" style="background-color: #28a745; color: white; border: none;">
                        <span class="nav-label">My Dashboard</span>
                    </a>
                <?php elseif ($user_role === 'visitor'): ?>
                    <a class="nav-btn" href="visitor-dashboard.php" style="background-color: #28a745; color: white; border: none;">
                        <span class="nav-label">My Dashboard</span>
                    </a>
                <?php endif; ?>
                <a class="nav-btn" href="logout.php" style="background-color: #dc3545; color: white; border: none;">
                    <span class="nav-label">Logout</span>
                </a>
            <?php else: ?>
                <a class="nav-btn login-btn" href="login.php" style="background-color: #0b6cf5; color: white; border: none;">
                    <span class="nav-label">Login / Sign Up</span>
                </a>
            <?php endif; ?>
        </div>
    </header>
    <div id="mobile-backdrop" class="mobile-backdrop" aria-hidden="true"></div>

    <div class="page-header">
        <h1><i class="fas fa-user-graduate"></i> Adult Classes</h1>
        <p>Professional English training for adults 12+. Focus on fluency, career, and real-world communication.</p>
        <a href="index.php" class="back-link"><i class="fas fa-arrow-left"></i> Back to Tracks</a>
    </div>

    <!-- Video Introduction Section -->
    <section class="video-intro-section" style="background: linear-gradient(135deg, #0b6cf5 0%, #004080 100%); color: white; padding: 60px 20px; text-align: center; margin-top: -40px; position: relative; z-index: 5;">
        <div style="max-width: 1200px; margin: 0 auto;">
            <h2 style="font-size: 2rem; margin-bottom: 20px; font-weight: 700;">Our Adult Teaching Approach</h2>
            <p style="font-size: 1.1rem; margin-bottom: 40px; opacity: 0.95; max-width: 700px; margin-left: auto; margin-right: auto;">
                Discover our professional teaching methodology and hear from adult students about their success stories.
            </p>
            <div style="max-width: 900px; margin: 0 auto; position: relative; padding-bottom: 56.25%; height: 0; overflow: hidden; border-radius: 15px; box-shadow: 0 20px 60px rgba(0,0,0,0.3);">
                <iframe 
                    id="adults-intro-video" 
                    style="position: absolute; top: 0; left: 0; width: 100%; height: 100%; border: none;" 
                    src="https://www.youtube.com/embed/YOUR_ADULTS_VIDEO_ID_HERE" 
                    title="Adult Classes Introduction Video"
                    allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture" 
                    allowfullscreen>
                </iframe>
            </div>
            <p style="margin-top: 30px; font-size: 0.95rem; opacity: 0.8;">
                <i class="fas fa-info-circle"></i> Replace "YOUR_ADULTS_VIDEO_ID_HERE" with your YouTube video ID
            </p>
        </div>
    </section>

    <div class="plans-container">
        <div class="plans-grid">
            <?php foreach ($plans as $index => $plan): ?>
            <div class="plan-card <?php echo $index === 1 ? 'featured' : ''; ?>">
                <h3 class="plan-name"><?php echo htmlspecialchars($plan['name']); ?></h3>
                <div class="plan-price">
                    $<?php echo number_format($plan['price'] ?? 0, 2); ?>
                    <span>/month</span>
                </div>
                <ul class="plan-features">
                    <li><i class="fas fa-check-circle"></i> <strong><?php echo $plan['one_on_one_classes_per_week'] ?? 1; ?></strong> one-on-one class<?php echo ($plan['one_on_one_classes_per_week'] ?? 1) > 1 ? 'es' : ''; ?> per week</li>
                    <?php 
                    $group_sessions = $plan['group_practice_sessions_per_week'] ?? 0;
                    if ($group_sessions > 0): ?>
                        <li><i class="fas fa-check-circle"></i> <strong><?php echo $group_sessions; ?></strong> group practice session<?php echo $group_sessions > 1 ? 's' : ''; ?> per week</li>
                    <?php endif; ?>
                    <li><i class="fas fa-check-circle"></i> Career & business English</li>
                    <li><i class="fas fa-check-circle"></i> Travel & conversation focus</li>
                    <li><i class="fas fa-check-circle"></i> Flexible scheduling</li>
                </ul>
                <?php 
                $plan_id = $plan['id'] ?? null;
                ?>
                <?php if (isset($_SESSION['user_id']) && ($_SESSION['user_role'] === 'student' || $_SESSION['user_role'] === 'new_student' || $_SESSION['user_role'] === 'visitor')): ?>
                    <form action="create_checkout_session.php" method="POST" style="margin: 0; width: 100%;">
                        <?php if ($plan_id): ?>
                            <input type="hidden" name="plan_id" value="<?php echo (int)$plan_id; ?>">
                        <?php endif; ?>
                        <input type="hidden" name="track" value="adults">
                        <input type="hidden" name="price_id" value="<?php echo htmlspecialchars($plan['stripe_price_id'] ?? 'price_PLACEHOLDER'); ?>">
                        <input type="hidden" name="mode" value="subscription">
                        <button type="submit" class="plan-cta">Choose This Plan</button>
                    </form>
                <?php else: ?>
                    <a href="register.php?track=adults<?php echo $plan_id ? '&plan_id=' . (int)$plan_id : ''; ?>" class="plan-cta">Get Started</a>
                <?php endif; ?>
            </div>
            <?php endforeach; ?>
        </div>
    </div>

    <footer>
        <p>&copy; <?php echo date('Y'); ?> Staten Academy. All rights reserved.</p>
    </footer>
    <script src="<?php echo getAssetPath('js/menu.js'); ?>" defer></script>
</body>
</html>

