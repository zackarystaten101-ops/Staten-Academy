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

// Get approved teachers for kids section (young_learners category)
$teacherService = new TeacherService($conn);
$teachers = $teacherService->getTeachersByCategory('young_learners', []);

$planModel = new SubscriptionPlan($conn);
$plans = $planModel->getPlansByTrack('kids');

$user_role = $_SESSION['user_role'] ?? 'guest';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Group Classes - Staten Academy</title>
    <link rel="stylesheet" href="<?php echo getAssetPath('styles.css'); ?>">
    <link rel="stylesheet" href="<?php echo getAssetPath('css/mobile.css'); ?>">
    <link rel="stylesheet" href="<?php echo getAssetPath('css/tracks.css'); ?>">
    <link rel="stylesheet" href="<?php echo getAssetPath('css/kids-theme.css'); ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        body {
            background: linear-gradient(135deg, #fff5f8 0%, #ffeef5 100%);
            min-height: 100vh;
        }
        .page-header {
            background: linear-gradient(135deg, #ff6b9d 0%, #ffa500 100%);
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
            box-shadow: 0 10px 40px rgba(255, 107, 157, 0.2);
            transition: all 0.3s ease;
            text-align: center;
            position: relative;
            border: 3px solid transparent;
        }
        .plan-card:hover {
            transform: translateY(-10px);
            box-shadow: 0 20px 60px rgba(255, 107, 157, 0.3);
            border-color: #ff6b9d;
        }
        .plan-card.best-value {
            border-color: #ff6b9d;
            border-width: 3px;
            background: linear-gradient(135deg, #fff5f8 0%, #ffffff 100%);
            box-shadow: 0 15px 50px rgba(255, 107, 157, 0.3);
        }
        .plan-card.best-value::before {
            content: 'BEST VALUE';
            position: absolute;
            top: -15px;
            left: 50%;
            transform: translateX(-50%);
            background: linear-gradient(135deg, #ff6b9d, #ffa500);
            color: white;
            padding: 6px 24px;
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: bold;
            letter-spacing: 0.5px;
            box-shadow: 0 4px 12px rgba(255, 107, 157, 0.4);
        }
        .plan-name {
            font-size: 1.8rem;
            font-weight: 700;
            color: #ff6b9d;
            margin-bottom: 15px;
        }
        .plan-price {
            font-size: 3rem;
            font-weight: 700;
            color: #ff6b9d;
            margin: 20px 0;
        }
        .plan-price span {
            font-size: 1.2rem;
            color: #666666; /* Improved contrast: #999 -> #666666 (WCAG AA compliant) */
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
            color: #ff6b9d;
            font-size: 1.2rem;
        }
        .plan-cta {
            display: block;
            background: linear-gradient(135deg, #ff6b9d, #ffa500);
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
            box-shadow: 0 8px 25px rgba(255, 107, 157, 0.4);
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
<body class="track-kids">
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
        <h1><i class="fas fa-users"></i> Group Classes</h1>
        <p>Join interactive group sessions with peers. Perfect for kids ages 3-11 who learn best in a fun, social environment.</p>
        <div style="display: flex; gap: 15px; justify-content: center; flex-wrap: wrap; margin-top: 20px;">
            <a href="index.php" class="back-link"><i class="fas fa-arrow-left"></i> Back to Home</a>
            <?php if (isset($_SESSION['user_id'])): ?>
                <a href="#" onclick="startAdminChat(event)" class="back-link" style="background: rgba(255,255,255,0.2); padding: 8px 16px; border-radius: 20px;">
                    <i class="fas fa-headset"></i> Contact Admin
                </a>
            <?php endif; ?>
        </div>
    </div>

    <!-- Video Introduction Section -->
    <section class="video-intro-section" style="background: linear-gradient(135deg, #ff6b9d 0%, #ffa500 100%); color: white; padding: 60px 20px; text-align: center; margin-top: -40px; position: relative; z-index: 5;">
        <div style="max-width: 1200px; margin: 0 auto;">
            <h2 style="font-size: 2rem; margin-bottom: 20px; font-weight: 700; color: #ffffff; text-shadow: 0 2px 8px rgba(0,0,0,0.4), 0 1px 3px rgba(0,0,0,0.6);">Our Group Classes Teaching Approach</h2>
            <p style="font-size: 1.1rem; margin-bottom: 40px; color: #ffffff; text-shadow: 0 1px 4px rgba(0,0,0,0.4); opacity: 1; max-width: 700px; margin-left: auto; margin-right: auto; font-weight: 500;">
                Learn about our fun and engaging group teaching style for kids, and hear from parents and students about their experience.
            </p>
            <div style="max-width: 900px; margin: 0 auto; position: relative; padding-bottom: 56.25%; height: 0; overflow: hidden; border-radius: 15px; box-shadow: 0 20px 60px rgba(0,0,0,0.3);">
                <iframe 
                    id="kids-intro-video" 
                    style="position: absolute; top: 0; left: 0; width: 100%; height: 100%; border: none;" 
                    src="https://www.youtube.com/embed/YOUR_KIDS_VIDEO_ID_HERE" 
                    title="Group Classes Introduction Video"
                    allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture" 
                    allowfullscreen>
                </iframe>
            </div>
            <p style="margin-top: 30px; font-size: 0.95rem; color: #ffffff; text-shadow: 0 1px 3px rgba(0,0,0,0.4); opacity: 1; font-weight: 500;">
                <i class="fas fa-info-circle"></i> Replace "YOUR_KIDS_VIDEO_ID_HERE" with your YouTube video ID
            </p>
        </div>
    </section>

    <!-- Meet Our Teachers Section -->
    <?php if (!empty($teachers)): ?>
    <section class="teachers-section" style="background: white; padding: 60px 20px; margin-top: 40px;">
        <div style="max-width: 1200px; margin: 0 auto;">
            <h2 style="text-align: center; font-size: 2.5rem; margin-bottom: 15px; color: #ff6b9d; font-weight: 700;">
                <i class="fas fa-chalkboard-teacher"></i> Meet Our Teachers
            </h2>
            <p style="text-align: center; font-size: 1.1rem; color: #666; margin-bottom: 40px; max-width: 700px; margin-left: auto; margin-right: auto;">
                Our certified teachers are experienced in teaching kids and leading engaging group classes. When you subscribe, you'll be assigned to classes taught by our expert instructors.
            </p>
            <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); gap: 30px; margin-top: 40px;">
                <?php foreach (array_slice($teachers, 0, 4) as $teacher): ?>
                    <div class="teacher-card" style="background: white; border-radius: 16px; padding: 30px; box-shadow: 0 4px 20px rgba(255, 107, 157, 0.15); text-align: center;">
                        <img src="<?php echo htmlspecialchars($teacher['profile_pic'] ?? getAssetPath('images/placeholder-teacher.svg')); ?>" 
                             alt="<?php echo htmlspecialchars($teacher['name']); ?>" 
                             style="width: 120px; height: 120px; border-radius: 50%; object-fit: cover; margin: 0 auto 20px; border: 4px solid #ff6b9d;">
                        <h3 style="font-size: 1.3rem; font-weight: 700; color: #ff6b9d; margin-bottom: 10px;">
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
                                    <?php echo number_format($rating, 1); ?>
                                </span>
                            <?php else: ?>
                                <span style="color: #999; font-size: 0.9rem;">New Teacher</span>
                            <?php endif; ?>
                        </div>
                        <span style="display: inline-block; padding: 8px 16px; border-radius: 20px; background: linear-gradient(135deg, #ff6b9d, #ffa500); color: white; font-size: 0.85rem; font-weight: 600;">
                            <i class="fas fa-check"></i> Certified for Kids
                        </span>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </section>
    <?php endif; ?>

    <div class="plans-container">
        <!-- Main Group Classes Plan -->
        <div class="plans-grid" style="grid-template-columns: 1fr; max-width: 500px; margin: 0 auto;">
            <?php
            // Find the group classes plan ($129.99/month, 12 classes/month)
            // If no plan exists, show a default plan card
            $groupPlan = null;
            foreach ($plans as $plan) {
                // Look for plan with 12 group classes per month or closest match
                if (($plan['group_classes_per_month'] ?? 0) >= 12) {
                    $groupPlan = $plan;
                    break;
                }
            }
            // If no plan found, use first plan or create default
            if (!$groupPlan && !empty($plans)) {
                $groupPlan = $plans[0];
            }
            ?>
            
            <?php if ($groupPlan): ?>
                <div class="plan-card best-value">
                    <h3 class="plan-name">Group Classes</h3>
                    <div class="plan-price">
                        $<?php echo number_format($groupPlan['price'] ?? 129.99, 2); ?>
                        <span>/month</span>
                    </div>
                    <ul class="plan-features">
                        <li><i class="fas fa-check-circle"></i> <strong>3</strong> classes per week</li>
                        <li><i class="fas fa-check-circle"></i> <strong>12</strong> group classes per month</li>
                        <li><i class="fas fa-check-circle"></i> 50-minute sessions</li>
                        <li><i class="fas fa-check-circle"></i> Interactive games & activities</li>
                        <li><i class="fas fa-check-circle"></i> Parent progress reports</li>
                        <li><i class="fas fa-check-circle"></i> Kid-friendly certified teachers</li>
                        <li><i class="fas fa-check-circle"></i> Ages 3-11</li>
                    </ul>
                    <?php 
                    $plan_id = $groupPlan['id'] ?? null;
                    ?>
                    <?php if (isset($_SESSION['user_id']) && ($_SESSION['user_role'] === 'student' || $_SESSION['user_role'] === 'new_student' || $_SESSION['user_role'] === 'visitor')): ?>
                        <!-- Logged in: Go directly to checkout -->
                        <form action="create_checkout_session.php" method="POST" style="margin: 0; width: 100%;">
                            <?php if ($plan_id): ?>
                                <input type="hidden" name="plan_id" value="<?php echo (int)$plan_id; ?>">
                            <?php endif; ?>
                            <input type="hidden" name="track" value="kids">
                            <input type="hidden" name="price_id" value="<?php echo htmlspecialchars($groupPlan['stripe_price_id'] ?? ''); ?>">
                            <input type="hidden" name="mode" value="subscription">
                            <button type="submit" class="plan-cta" <?php echo empty($groupPlan['stripe_price_id']) ? 'disabled' : ''; ?>>Subscribe Now</button>
                        </form>
                    <?php else: ?>
                        <!-- Not logged in: Register first -->
                        <a href="register.php?track=kids<?php echo $plan_id ? '&plan_id=' . (int)$plan_id : ''; ?>" class="plan-cta">Get Started</a>
                    <?php endif; ?>
                </div>
            <?php else: ?>
                <!-- Fallback: Show default plan if none found in database -->
                <div class="plan-card best-value">
                    <h3 class="plan-name">Group Classes</h3>
                    <div class="plan-price">
                        $129.99
                        <span>/month</span>
                    </div>
                    <ul class="plan-features">
                        <li><i class="fas fa-check-circle"></i> <strong>3</strong> classes per week</li>
                        <li><i class="fas fa-check-circle"></i> <strong>12</strong> group classes per month</li>
                        <li><i class="fas fa-check-circle"></i> 50-minute sessions</li>
                        <li><i class="fas fa-check-circle"></i> Interactive games & activities</li>
                        <li><i class="fas fa-check-circle"></i> Parent progress reports</li>
                        <li><i class="fas fa-check-circle"></i> Kid-friendly certified teachers</li>
                        <li><i class="fas fa-check-circle"></i> Ages 3-11</li>
                    </ul>
                    <a href="register.php?track=kids" class="plan-cta">Get Started</a>
                </div>
            <?php endif; ?>
        </div>
        
        <!-- Coming Soon: One-on-One Classes -->
        <div style="max-width: 500px; margin: 60px auto 0; padding: 40px 30px; background: white; border-radius: 20px; box-shadow: 0 10px 40px rgba(255, 107, 157, 0.1); text-align: center; border: 2px dashed #ff6b9d; opacity: 0.7;">
            <h3 style="color: #ff6b9d; margin-bottom: 15px; font-size: 1.8rem;">
                <i class="fas fa-user"></i> One-on-One Classes
            </h3>
            <p style="color: #666; margin-bottom: 20px; font-size: 1.1rem;">
                Coming Soon!
            </p>
            <p style="color: #999; font-size: 0.95rem; line-height: 1.6;">
                We're working on adding one-on-one classes for personalized learning. Stay tuned!
            </p>
            <button disabled style="margin-top: 20px; padding: 12px 30px; border-radius: 50px; background: #ddd; color: #999; border: none; font-weight: 600; font-size: 1rem; cursor: not-allowed;">
                Coming Soon
            </button>
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

