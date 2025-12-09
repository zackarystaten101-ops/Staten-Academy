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
$plans = $planModel->getPlansByTrack('coding');

// If no plans exist, create placeholder plans
if (empty($plans)) {
    $placeholderPlans = [
        ['name' => 'Coding Plan 1', 'one_on_one_classes_per_week' => 1, 'price' => 149.00, 'display_order' => 1],
        ['name' => 'Coding Plan 2', 'one_on_one_classes_per_week' => 2, 'price' => 269.00, 'display_order' => 2],
        ['name' => 'Coding Plan 3', 'one_on_one_classes_per_week' => 3, 'price' => 379.00, 'display_order' => 3],
        ['name' => 'Coding Plan 4', 'one_on_one_classes_per_week' => 4, 'price' => 479.00, 'display_order' => 4],
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
    <title>English for Coding Plans - Staten Academy</title>
    <link rel="stylesheet" href="<?php echo getAssetPath('styles.css'); ?>">
    <link rel="stylesheet" href="<?php echo getAssetPath('css/mobile.css'); ?>">
    <link rel="stylesheet" href="<?php echo getAssetPath('css/tracks.css'); ?>">
    <link rel="stylesheet" href="<?php echo getAssetPath('css/coding-theme.css'); ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        body {
            background: linear-gradient(135deg, #f0f9ff 0%, #e0f2ff 100%);
            min-height: 100vh;
            font-family: 'Courier New', monospace, sans-serif;
        }
        .page-header {
            background: linear-gradient(135deg, #00d4ff 0%, #0066cc 100%);
            color: white;
            padding: 60px 20px;
            text-align: center;
            position: relative;
            overflow: hidden;
        }
        .page-header::before {
            content: '<div class="code-bg">';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            opacity: 0.1;
            background-image: repeating-linear-gradient(
                0deg,
                transparent,
                transparent 2px,
                rgba(255,255,255,0.1) 2px,
                rgba(255,255,255,0.1) 4px
            );
            pointer-events: none;
        }
        .page-header h1 {
            font-size: 3rem;
            margin-bottom: 10px;
            font-weight: 700;
            position: relative;
            z-index: 1;
        }
        .page-header p {
            font-size: 1.2rem;
            opacity: 0.95;
            position: relative;
            z-index: 1;
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
            border-radius: 12px;
            padding: 40px 30px;
            box-shadow: 0 8px 30px rgba(0, 212, 255, 0.2);
            transition: all 0.3s ease;
            text-align: center;
            position: relative;
            border: 2px solid transparent;
        }
        .plan-card:hover {
            transform: translateY(-8px);
            box-shadow: 0 15px 50px rgba(0, 212, 255, 0.3);
            border-color: #00d4ff;
        }
        .plan-card.featured {
            border-color: #00d4ff;
            background: linear-gradient(135deg, #f0f9ff 0%, #ffffff 100%);
        }
        .plan-card.featured::before {
            content: 'POPULAR';
            position: absolute;
            top: -15px;
            left: 50%;
            transform: translateX(-50%);
            background: linear-gradient(135deg, #00d4ff, #0066cc);
            color: white;
            padding: 5px 20px;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: bold;
        }
        .plan-name {
            font-size: 1.8rem;
            font-weight: 700;
            color: #0066cc;
            margin-bottom: 15px;
        }
        .plan-price {
            font-size: 3rem;
            font-weight: 700;
            color: #00d4ff;
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
            color: #555;
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 1rem;
        }
        .plan-features li i {
            color: #00d4ff;
            font-size: 1.2rem;
        }
        .plan-cta {
            display: block;
            background: linear-gradient(135deg, #00d4ff, #0066cc);
            color: white;
            padding: 15px 30px;
            border-radius: 8px;
            font-weight: 600;
            font-size: 1.1rem;
            text-decoration: none;
            margin-top: 25px;
            transition: transform 0.2s, box-shadow 0.2s;
            border: none;
            cursor: pointer;
            width: 100%;
        }
        .plan-cta:hover {
            transform: scale(1.05);
            box-shadow: 0 8px 25px rgba(0, 212, 255, 0.4);
        }
        .back-link {
            display: inline-block;
            color: white;
            text-decoration: none;
            margin-top: 20px;
            font-size: 1.1rem;
            opacity: 0.9;
            position: relative;
            z-index: 1;
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
<body class="track-coding">
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
    </header>

    <div class="page-header">
        <h1><i class="fas fa-code"></i> English for Coding</h1>
        <p>Specialized English training for developers. Technical vocabulary, interview prep, and developer communication.</p>
        <a href="index.php" class="back-link"><i class="fas fa-arrow-left"></i> Back to Tracks</a>
    </div>

    <!-- Video Introduction Section -->
    <section class="video-intro-section" style="background: linear-gradient(135deg, #00d4ff 0%, #0066cc 100%); color: white; padding: 60px 20px; text-align: center; margin-top: -40px; position: relative; z-index: 5;">
        <div style="max-width: 1200px; margin: 0 auto;">
            <h2 style="font-size: 2rem; margin-bottom: 20px; font-weight: 700;">Our Coding English Approach</h2>
            <p style="font-size: 1.1rem; margin-bottom: 40px; opacity: 0.95; max-width: 700px; margin-left: auto; margin-right: auto;">
                Learn about our specialized approach to teaching English for developers and hear from coding professionals about their experience.
            </p>
            <div style="max-width: 900px; margin: 0 auto; position: relative; padding-bottom: 56.25%; height: 0; overflow: hidden; border-radius: 15px; box-shadow: 0 20px 60px rgba(0,0,0,0.3);">
                <iframe 
                    id="coding-intro-video" 
                    style="position: absolute; top: 0; left: 0; width: 100%; height: 100%; border: none;" 
                    src="https://www.youtube.com/embed/YOUR_CODING_VIDEO_ID_HERE" 
                    title="English for Coding Introduction Video"
                    allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture" 
                    allowfullscreen>
                </iframe>
            </div>
            <p style="margin-top: 30px; font-size: 0.95rem; opacity: 0.8;">
                <i class="fas fa-info-circle"></i> Replace "YOUR_CODING_VIDEO_ID_HERE" with your YouTube video ID
            </p>
        </div>
    </section>

    <div class="plans-container">
        <div class="plans-grid">
            <?php foreach ($plans as $index => $plan): ?>
            <div class="plan-card <?php echo $index === 1 ? 'featured' : ''; ?>">
                <h3 class="plan-name"><?php echo htmlspecialchars($plan['name'] ?? 'Plan'); ?></h3>
                <div class="plan-price">
                    $<?php echo number_format($plan['price'] ?? 0, 2); ?>
                    <span>/month</span>
                </div>
                <ul class="plan-features">
                    <li><i class="fas fa-check-circle"></i> <?php echo $plan['one_on_one_classes_per_week'] ?? 1; ?> one-on-one class<?php echo ($plan['one_on_one_classes_per_week'] ?? 1) > 1 ? 'es' : ''; ?> per week</li>
                    <li><i class="fas fa-check-circle"></i> Group classes included</li>
                    <li><i class="fas fa-check-circle"></i> Technical vocabulary focus</li>
                    <li><i class="fas fa-check-circle"></i> Interview preparation</li>
                    <li><i class="fas fa-check-circle"></i> Code documentation practice</li>
                </ul>
                <?php if (isset($_SESSION['user_id'])): ?>
                    <a href="payment.php?track=coding<?php echo isset($plan['id']) ? '&plan_id=' . $plan['id'] : ''; ?>" class="plan-cta">Choose This Plan</a>
                <?php else: ?>
                    <a href="register.php?track=coding<?php echo isset($plan['id']) ? '&plan_id=' . $plan['id'] : ''; ?>" class="plan-cta">Get Started</a>
                <?php endif; ?>
            </div>
            <?php endforeach; ?>
        </div>
    </div>

    <footer>
        <p>&copy; <?php echo date('Y'); ?> Staten Academy. All rights reserved.</p>
    </footer>
</body>
</html>

