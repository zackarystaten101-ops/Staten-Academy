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

$user_role = $_SESSION['user_role'] ?? 'guest';

// Get track and plan_id from URL parameters
$track = isset($_GET['track']) ? $_GET['track'] : null;
$plan_id = isset($_GET['plan_id']) ? (int)$_GET['plan_id'] : null;

// Validate track
if ($track && !in_array($track, ['kids', 'adults', 'coding'])) {
    $track = null;
}

$planModel = new SubscriptionPlan($conn);

// If track is specified, show plans for that track only
// Otherwise, show all tracks with track selection
if ($track) {
    $plans = $planModel->getPlansByTrack($track);
    // If no plans exist, create placeholder plans
    if (empty($plans)) {
        $placeholderPlans = [
            ['name' => 'Economy', 'one_on_one_classes_per_week' => 1, 'price' => 129.99, 'display_order' => 1],
            ['name' => 'Basic', 'one_on_one_classes_per_week' => 2, 'price' => 179.00, 'display_order' => 2],
            ['name' => 'Pro', 'one_on_one_classes_per_week' => 3, 'price' => 249.00, 'display_order' => 3],
            ['name' => 'Mega', 'one_on_one_classes_per_week' => 4, 'price' => 319.00, 'display_order' => 4],
        ];
        $plans = $placeholderPlans;
    }
} else {
    // Get all active plans grouped by track
    $allPlans = $planModel->getActivePlans();
    $plansByTrack = [
        'kids' => [],
        'adults' => [],
        'coding' => []
    ];
    
    if ($allPlans) {
        while ($plan = $allPlans->fetch_assoc()) {
            if (isset($plan['track']) && isset($plansByTrack[$plan['track']])) {
                $plansByTrack[$plan['track']][] = $plan;
            }
        }
    }
    
    // If no plans in database, use empty arrays (will show track selection)
    $plans = null;
}

// Determine track theme colors
$trackThemes = [
    'kids' => ['primary' => '#ff6b9d', 'secondary' => '#ffa500', 'bg' => 'linear-gradient(135deg, #fff5f8 0%, #ffeef5 100%)'],
    'adults' => ['primary' => '#0b6cf5', 'secondary' => '#004080', 'bg' => 'linear-gradient(135deg, #f0f7ff 0%, #e8f4ff 100%)'],
    'coding' => ['primary' => '#00d4aa', 'secondary' => '#00a67e', 'bg' => 'linear-gradient(135deg, #f0fff4 0%, #e8f8f0 100%)']
];

$currentTheme = $trackThemes['kids']; // Always use kids theme for Group Classes
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Plans & Pricing - Staten Academy</title>
    <link rel="stylesheet" href="<?php echo getAssetPath('styles.css'); ?>">
    <link rel="stylesheet" href="<?php echo getAssetPath('css/mobile.css'); ?>">
    <link rel="stylesheet" href="<?php echo getAssetPath('css/tracks.css'); ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        body {
            background: <?php echo $currentTheme['bg']; ?>;
            min-height: 100vh;
        }
        .page-header {
            background: linear-gradient(135deg, <?php echo $currentTheme['primary']; ?> 0%, <?php echo $currentTheme['secondary']; ?> 100%);
            color: white;
            padding: 60px 20px;
            text-align: center;
        }
        .page-header h1 {
            font-size: 3rem;
            margin-bottom: 10px;
            font-weight: 700;
            color: #ffffff;
            text-shadow: 0 2px 8px rgba(0,0,0,0.3), 0 1px 3px rgba(0,0,0,0.5);
        }
        .page-header p {
            font-size: 1.2rem;
            color: #ffffff;
            text-shadow: 0 1px 4px rgba(0,0,0,0.3);
            opacity: 1;
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
            border-radius: 20px;
            padding: 40px 30px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.15);
            transition: all 0.3s ease;
            text-align: center;
            position: relative;
            border: 3px solid transparent;
        }
        .plan-card:hover {
            transform: translateY(-10px);
            box-shadow: 0 20px 60px rgba(0,0,0,0.2);
            border-color: <?php echo $currentTheme['primary']; ?>;
        }
        .plan-card.featured {
            border-color: <?php echo $currentTheme['primary']; ?>;
            background: linear-gradient(135deg, #ffffff 0%, #f8f9fa 100%);
        }
        .plan-card.featured::before {
            content: 'POPULAR';
            position: absolute;
            top: -15px;
            left: 50%;
            transform: translateX(-50%);
            background: linear-gradient(135deg, <?php echo $currentTheme['primary']; ?>, <?php echo $currentTheme['secondary']; ?>);
            color: white;
            padding: 5px 20px;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: bold;
        }
        .plan-name {
            font-size: 1.8rem;
            font-weight: 700;
            color: <?php echo $currentTheme['primary']; ?>;
            margin-bottom: 15px;
        }
        .plan-price {
            font-size: 3rem;
            font-weight: 700;
            color: <?php echo $currentTheme['primary']; ?>;
            margin: 20px 0;
        }
        .plan-price span {
            font-size: 1.2rem;
            color: #666666;
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
            color: #2d2d2d;
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 1rem;
        }
        .plan-features li strong {
            color: #1a1a1a;
            font-weight: 700;
        }
        .plan-features li i {
            color: <?php echo $currentTheme['primary']; ?>;
            font-size: 1.2rem;
        }
        .plan-cta {
            display: block;
            background: linear-gradient(135deg, <?php echo $currentTheme['primary']; ?>, <?php echo $currentTheme['secondary']; ?>);
            color: white !important;
            padding: 15px 30px;
            border-radius: 50px;
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
            box-shadow: 0 8px 25px rgba(0,0,0,0.3);
            color: white !important;
        }
        button.plan-cta {
            font-family: inherit;
        }
        .back-link {
            display: inline-block;
            color: #ffffff;
            text-decoration: none;
            margin-top: 20px;
            font-size: 1.1rem;
            text-shadow: 0 1px 3px rgba(0,0,0,0.3);
            font-weight: 600;
            opacity: 1;
        }
        .back-link:hover {
            opacity: 1;
            text-decoration: underline;
            text-shadow: 0 2px 5px rgba(0,0,0,0.4);
        }
        .track-selector {
            display: flex;
            gap: 15px;
            justify-content: center;
            flex-wrap: wrap;
            margin: 30px 0;
        }
        .track-btn {
            padding: 12px 24px;
            border-radius: 25px;
            text-decoration: none;
            font-weight: 600;
            transition: all 0.3s;
            border: 2px solid white;
            color: white;
            background: rgba(255,255,255,0.2);
        }
        .track-btn:hover {
            background: rgba(255,255,255,0.3);
            transform: translateY(-2px);
        }
        .track-btn.active {
            background: white;
            color: <?php echo $currentTheme['primary']; ?>;
        }
        .single-class-box {
            background: white;
            border-radius: 20px;
            padding: 40px 30px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.15);
            text-align: center;
            margin-bottom: 40px;
            border: 3px solid <?php echo $currentTheme['primary']; ?>;
        }
        .single-class-box h2 {
            color: <?php echo $currentTheme['primary']; ?>;
            margin-bottom: 15px;
            font-size: 2rem;
        }
        .single-class-price {
            font-size: 3rem;
            color: <?php echo $currentTheme['primary']; ?>;
            font-weight: bold;
            margin: 20px 0;
        }
        .single-class-price span {
            font-size: 1.2rem;
            color: #666;
            font-weight: normal;
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
<body class="<?php echo $track ? 'track-' . $track : ''; ?>">
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
        <h1><i class="fas fa-<?php echo $track === 'kids' ? 'child' : ($track === 'coding' ? 'code' : 'user-graduate'); ?>"></i> 
            <?php 
            if ($track === 'kids' || !$track) {
                echo 'Group Classes';
            } else {
                echo 'Group Classes';
            }
            ?>
        </h1>
        <p>
            <?php 
            echo 'Join interactive group sessions with peers. Perfect for kids ages 3-11 who learn best in a fun, social environment.';
            ?>
        </p>
        <div style="display: flex; gap: 15px; justify-content: center; flex-wrap: wrap; margin-top: 20px;">
            <a href="index.php" class="back-link"><i class="fas fa-arrow-left"></i> Back to Home</a>
            <a href="kids-plans.php" class="back-link" style="background: rgba(255,255,255,0.2); padding: 8px 16px; border-radius: 20px;">
                <i class="fas fa-users"></i> Group Classes
            </a>
        </div>
    </div>

    <div class="plans-container">
        <!-- Note: Single class/trial options removed - focusing on Group Classes only -->

        <?php if ($track && !empty($plans)): ?>
            <!-- Show plans for specific track -->
            <h2 style="text-align: center; margin-bottom: 30px; color: <?php echo $currentTheme['primary']; ?>; font-size: 2rem;">
                <i class="fas fa-calendar-alt"></i> Monthly Subscriptions
            </h2>
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
                        if ($track === 'kids') {
                            $group_classes = $plan['group_classes_per_week'] ?? 0;
                            if ($group_classes > 0): ?>
                                <li><i class="fas fa-check-circle"></i> <strong><?php echo $group_classes; ?></strong> group class<?php echo $group_classes > 1 ? 'es' : ''; ?> per week</li>
                            <?php endif;
                        } elseif ($track === 'adults') {
                            $group_sessions = $plan['group_practice_sessions_per_week'] ?? 0;
                            if ($group_sessions > 0): ?>
                                <li><i class="fas fa-check-circle"></i> <strong><?php echo $group_sessions; ?></strong> group practice session<?php echo $group_sessions > 1 ? 's' : ''; ?> per week</li>
                            <?php endif;
                        } elseif ($track === 'coding') {
                            $video_courses = $plan['video_courses'] ?? 0;
                            if ($video_courses > 0): ?>
                                <li><i class="fas fa-check-circle"></i> <strong><?php echo $video_courses; ?></strong> video course<?php echo $video_courses > 1 ? 's' : ''; ?> access</li>
                            <?php endif;
                        }
                        ?>
                        <?php if ($track === 'kids'): ?>
                            <li><i class="fas fa-check-circle"></i> Interactive games & activities</li>
                            <li><i class="fas fa-check-circle"></i> Parent progress reports</li>
                            <li><i class="fas fa-check-circle"></i> Kid-friendly certified teachers</li>
                        <?php elseif ($track === 'adults'): ?>
                            <li><i class="fas fa-check-circle"></i> Career-focused curriculum</li>
                            <li><i class="fas fa-check-circle"></i> Flexible scheduling</li>
                            <li><i class="fas fa-check-circle"></i> Professional certified teachers</li>
                        <?php elseif ($track === 'coding'): ?>
                            <li><i class="fas fa-check-circle"></i> Technical vocabulary focus</li>
                            <li><i class="fas fa-check-circle"></i> Industry-specific content</li>
                            <li><i class="fas fa-check-circle"></i> Tech-savvy certified teachers</li>
                        <?php endif; ?>
                    </ul>
                    <?php 
                    $plan_id = $plan['id'] ?? null;
                    ?>
                    <?php if (isset($_SESSION['user_id']) && ($_SESSION['user_role'] === 'student' || $_SESSION['user_role'] === 'new_student' || $_SESSION['user_role'] === 'visitor')): ?>
                        <!-- Logged in: Go directly to checkout -->
                        <form action="create_checkout_session.php" method="POST" style="margin: 0; width: 100%;">
                            <?php if ($plan_id): ?>
                                <input type="hidden" name="plan_id" value="<?php echo (int)$plan_id; ?>">
                            <?php endif; ?>
                            <input type="hidden" name="track" value="<?php echo htmlspecialchars($track); ?>">
                            <input type="hidden" name="price_id" value="<?php echo htmlspecialchars($plan['stripe_price_id'] ?? 'price_PLACEHOLDER'); ?>">
                            <input type="hidden" name="mode" value="subscription">
                            <button type="submit" class="plan-cta">Choose This Plan</button>
                        </form>
                    <?php else: ?>
                        <!-- Not logged in: Register first -->
                        <a href="register.php?track=<?php echo urlencode($track); ?><?php echo $plan_id ? '&plan_id=' . (int)$plan_id : ''; ?>" class="plan-cta">Get Started</a>
                    <?php endif; ?>
                </div>
                <?php endforeach; ?>
            </div>
        <?php elseif (!$track): ?>
            <!-- Show track selection if no track specified -->
            <div style="text-align: center; margin: 40px 0;">
                <h2 style="color: <?php echo $currentTheme['primary']; ?>; font-size: 2rem; margin-bottom: 20px;">
                    <i class="fas fa-graduation-cap"></i> Select Your Learning Track
                </h2>
                <p style="font-size: 1.1rem; color: #666; margin-bottom: 30px;">
                    Choose a track to see available subscription plans, or browse all plans below.
                </p>
                <div class="track-selector">
                    <a href="kids-plans.php" class="track-btn active">
                        <i class="fas fa-users"></i> Group Classes
                    </a>
                </div>
            </div>
        <?php else: ?>
            <!-- No plans available for this track -->
            <div style="text-align: center; padding: 40px; background: white; border-radius: 20px; box-shadow: 0 10px 40px rgba(0,0,0,0.1);">
                <h3 style="color: <?php echo $currentTheme['primary']; ?>; margin-bottom: 15px;">No Plans Available</h3>
                <p style="color: #666; margin-bottom: 20px;">Plans for this track are currently being set up. Please check back soon!</p>
                <a href="index.php" class="plan-cta" style="max-width: 200px; margin: 0 auto;">Back to Home</a>
            </div>
        <?php endif; ?>
        
        <p style="text-align: center; margin-top: 40px; color: #666; font-size: 0.9rem;">
            <i class="fas fa-info-circle"></i> Plans renew automatically. Cancel anytime.
        </p>
    </div>

    <footer>
        <p>Contact us: info@statenacademy.com | Phone: +1 234 567 890</p>
        <p>&copy; <?php echo date('Y'); ?> Staten Academy. All rights reserved.</p>
    </footer>
    <script src="<?php echo getAssetPath('js/menu.js'); ?>" defer></script>
</body>
</html>
