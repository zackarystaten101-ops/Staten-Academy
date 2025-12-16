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
require_once __DIR__ . '/app/Services/TeacherService.php';

// Ensure getAssetPath function is available
if (!function_exists('getAssetPath')) {
    require_once __DIR__ . '/app/Views/components/dashboard-functions.php';
}

// Get approved teachers for coding section
$teacherService = new TeacherService($conn);
$teachers = $teacherService->getTeachersByCategory('coding', []);

$planModel = new SubscriptionPlan($conn);
$plans = $planModel->getPlansByTrack('coding');

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
        .plan-card.best-value {
            border-color: #28a745;
            border-width: 3px;
            background: linear-gradient(135deg, #f0f9ff 0%, #ffffff 100%);
            box-shadow: 0 15px 50px rgba(40, 167, 69, 0.3);
        }
        .plan-card.best-value::before {
            content: 'BEST VALUE';
            position: absolute;
            top: -15px;
            left: 50%;
            transform: translateX(-50%);
            background: linear-gradient(135deg, #28a745, #20c997);
            color: white;
            padding: 6px 24px;
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: bold;
            letter-spacing: 0.5px;
            box-shadow: 0 4px 12px rgba(40, 167, 69, 0.4);
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
            color: #00d4ff;
            font-size: 1.2rem;
        }
        .plan-cta {
            display: block;
            background: linear-gradient(135deg, #00d4ff, #0066cc);
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
            box-shadow: 0 8px 25px rgba(0, 212, 255, 0.4);
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
        <h1><i class="fas fa-code"></i> English for Coding</h1>
        <p>Specialized English training for developers. Technical vocabulary, interview prep, and developer communication.</p>
        <div style="display: flex; gap: 15px; justify-content: center; flex-wrap: wrap; margin-top: 20px;">
            <a href="index.php" class="back-link"><i class="fas fa-arrow-left"></i> Back to Tracks</a>
            <?php if (isset($_SESSION['user_id'])): ?>
                <a href="#" onclick="startAdminChat(event)" class="back-link" style="background: rgba(255,255,255,0.2); padding: 8px 16px; border-radius: 20px;">
                    <i class="fas fa-headset"></i> Contact Admin
                </a>
            <?php endif; ?>
        </div>
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

    <!-- Approved Teachers Section -->
    <?php if (!empty($teachers)): ?>
    <section class="teachers-section" style="background: white; padding: 60px 20px; margin-top: 40px;">
        <div style="max-width: 1200px; margin: 0 auto;">
            <h2 style="text-align: center; font-size: 2.5rem; margin-bottom: 15px; color: #28a745; font-weight: 700;">
                <i class="fas fa-chalkboard-teacher"></i> Our Approved Teachers
            </h2>
            <p style="text-align: center; font-size: 1.1rem; color: #666; margin-bottom: 40px; max-width: 700px; margin-left: auto; margin-right: auto;">
                Meet our experienced teachers approved for English for Coding classes. Click on any teacher to view their profile and select a plan.
            </p>
            <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); gap: 30px; margin-top: 40px;">
                <?php foreach ($teachers as $teacher): ?>
                    <div class="teacher-card" style="background: white; border-radius: 16px; padding: 30px; box-shadow: 0 4px 20px rgba(40, 167, 69, 0.15); transition: all 0.3s ease; text-align: center; cursor: pointer;" onclick="window.location.href='teacher-profile.php?id=<?php echo intval($teacher['id']); ?>'">
                        <img src="<?php echo htmlspecialchars($teacher['profile_pic'] ?? getAssetPath('images/placeholder-teacher.svg')); ?>" 
                             alt="<?php echo htmlspecialchars($teacher['name']); ?>" 
                             style="width: 120px; height: 120px; border-radius: 50%; object-fit: cover; margin: 0 auto 20px; border: 4px solid #28a745;">
                        <h3 style="font-size: 1.3rem; font-weight: 700; color: #28a745; margin-bottom: 10px;">
                            <?php echo htmlspecialchars($teacher['name']); ?>
                        </h3>
                        <?php if ($teacher['specialty']): ?>
                            <p style="color: #666; font-size: 0.95rem; margin-bottom: 15px;">
                                <?php echo htmlspecialchars($teacher['specialty']); ?>
                            </p>
                        <?php endif; ?>
                        <div style="display: flex; align-items: center; justify-content: center; gap: 5px; margin-bottom: 15px;">
                            <?php 
                            $rating = floatval($teacher['avg_rating'] ?? 0);
                            if ($rating > 0): 
                                $full_stars = floor($rating);
                                $half_star = ($rating - $full_stars) >= 0.5;
                                for ($i = 0; $i < $full_stars; $i++) {
                                    echo '<i class="fas fa-star" style="color: #ffa500;"></i>';
                                }
                                if ($half_star) {
                                    echo '<i class="fas fa-star-half-alt" style="color: #ffa500;"></i>';
                                }
                                for ($i = $full_stars + ($half_star ? 1 : 0); $i < 5; $i++) {
                                    echo '<i class="far fa-star" style="color: #ddd;"></i>';
                                }
                            ?>
                                <span style="color: #666; font-size: 0.9rem; margin-left: 5px;">
                                    <?php echo number_format($rating, 1); ?> (<?php echo intval($teacher['review_count'] ?? 0); ?>)
                                </span>
                            <?php else: ?>
                                <span style="color: #999; font-size: 0.9rem;">No ratings yet</span>
                            <?php endif; ?>
                        </div>
                        <?php if ($teacher['bio']): ?>
                            <p style="color: #555; font-size: 0.9rem; line-height: 1.6; margin-bottom: 20px; display: -webkit-box; -webkit-line-clamp: 3; -webkit-box-orient: vertical; overflow: hidden;">
                                <?php echo htmlspecialchars(substr($teacher['bio'], 0, 120)); ?>...
                            </p>
                        <?php endif; ?>
                        <a href="teacher-profile.php?id=<?php echo intval($teacher['id']); ?>" 
                           class="btn-primary" 
                           style="display: inline-block; padding: 12px 24px; border-radius: 25px; text-decoration: none; font-weight: 600; background: linear-gradient(135deg, #28a745, #20c997); color: white; transition: transform 0.2s;">
                            View Profile
                        </a>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </section>
    <?php endif; ?>

    <div class="plans-container">
        <div class="plans-grid">
            <?php if (empty($plans)): ?>
                <div style="grid-column: 1 / -1; text-align: center; padding: 40px;">
                    <p style="font-size: 1.2rem; color: #666;">No plans available at this time. Please check back later.</p>
                </div>
            <?php else: ?>
                <?php foreach ($plans as $plan): ?>
                <div class="plan-card <?php echo ($plan['is_best_value'] ?? false) ? 'best-value' : ''; ?>">
                    <h3 class="plan-name"><?php echo htmlspecialchars($plan['name']); ?></h3>
                    <div class="plan-price">
                        $<?php echo number_format($plan['price'] ?? 0, 2); ?>
                        <span>/month</span>
                    </div>
                    <ul class="plan-features">
                        <li><i class="fas fa-check-circle"></i> <strong><?php echo $plan['one_on_one_classes_per_month'] ?? 0; ?></strong> × 1-on-1 classes per month</li>
                        <li><i class="fas fa-check-circle"></i> <strong><?php echo $plan['group_classes_per_month'] ?? 0; ?></strong> × group classes per month</li>
                        <li><i class="fas fa-check-circle"></i> 50-minute sessions</li>
                        <?php 
                        // Display track-specific features
                        $trackFeatures = [];
                        if (!empty($plan['track_specific_features'])) {
                            if (is_string($plan['track_specific_features'])) {
                                $trackFeatures = json_decode($plan['track_specific_features'], true) ?: [];
                            } else {
                                $trackFeatures = $plan['track_specific_features'];
                            }
                        }
                        foreach ($trackFeatures as $feature): ?>
                            <li><i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($feature); ?></li>
                        <?php endforeach; ?>
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
                            <input type="hidden" name="track" value="coding">
                            <input type="hidden" name="price_id" value="<?php echo htmlspecialchars($plan['stripe_price_id'] ?? ''); ?>">
                            <input type="hidden" name="mode" value="subscription">
                            <button type="submit" class="plan-cta" <?php echo empty($plan['stripe_price_id']) ? 'disabled' : ''; ?>>Choose This Plan</button>
                        </form>
                    <?php else: ?>
                        <!-- Not logged in: Register first, then they'll see todo list -->
                        <a href="register.php?track=coding<?php echo $plan_id ? '&plan_id=' . (int)$plan_id : ''; ?>" class="plan-cta">Get Started</a>
                    <?php endif; ?>
                </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

    <footer>
        <p>&copy; <?php echo date('Y'); ?> Staten Academy. All rights reserved.</p>
    </footer>
    <script src="<?php echo getAssetPath('js/menu.js'); ?>" defer></script>
    <script>
    async function startAdminChat(event) {
        if (event) event.preventDefault();
        
        try {
            const response = await fetch('api/start-admin-chat.php', {
                method: 'GET',
                headers: {
                    'Accept': 'application/json'
                },
                credentials: 'same-origin'
            });
            
            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }
            
            const data = await response.json();
            
            if (data.success) {
                window.location.href = data.redirect_url;
            } else {
                alert('Error: ' + (data.error || 'Failed to start chat'));
            }
        } catch (error) {
            console.error('Error:', error);
            alert('An error occurred. Please try again.');
        }
    }
    </script>
</body>
</html>

