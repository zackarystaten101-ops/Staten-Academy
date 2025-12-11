<?php
// Start output buffering to prevent "headers already sent" errors
ob_start();

// Load environment configuration first to check APP_DEBUG
if (!defined('DB_HOST')) {
    require_once __DIR__ . '/env.php';
}

// Enable error reporting based on APP_DEBUG setting
if (defined('APP_DEBUG') && APP_DEBUG === true) {
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
    ini_set('display_startup_errors', 1);
} else {
    // In production, log errors but don't display them
    error_reporting(E_ALL);
    ini_set('display_errors', 0);
    ini_set('log_errors', 1);
}

// Start session (must be before any output)
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Load database connection
require_once __DIR__ . '/db.php';

// Load dashboard functions
require_once __DIR__ . '/app/Views/components/dashboard-functions.php';

// Ensure getAssetPath function is available
if (!function_exists('getAssetPath')) {
    function getAssetPath($asset) {
        $asset = ltrim($asset, '/');
        if (strpos($asset, 'assets/') === 0) {
            $assetPath = $asset;
        } else {
            $assetPath = 'assets/' . $asset;
        }
        $scriptName = $_SERVER['SCRIPT_NAME'] ?? '';
        $basePath = dirname($scriptName);
        $basePath = str_replace('\\', '/', $basePath);
        if ($basePath === '.' || $basePath === '/' || empty($basePath)) {
            $basePath = '';
        } else {
            $basePath = '/' . trim($basePath, '/');
        }
        return $basePath . '/' . $assetPath;
    }
}

$user_role = $_SESSION['user_role'] ?? 'guest';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=5.0, user-scalable=yes, viewport-fit=cover">
    <meta name="theme-color" content="#004080">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="description" content="Staten Academy - Choose your English learning track: Kids, Adults, or English for Coding">
    <title>Staten Academy - Choose Your Learning Track</title>
    <link rel="stylesheet" href="<?php echo getAssetPath('styles.css'); ?>">
    <link rel="stylesheet" href="<?php echo getAssetPath('css/mobile.css'); ?>">
    <link rel="stylesheet" href="<?php echo getAssetPath('css/tracks.css'); ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        /* Prevent horizontal scroll and remove body padding for homepage */
        html {
            overflow-x: hidden;
            max-width: 100vw;
        }
        
        body.homepage {
            padding: 0 !important;
            margin: 0;
            overflow-x: hidden;
            max-width: 100vw;
        }
        
        /* Wrapper to contain content with proper padding */
        .homepage-wrapper {
            width: 100%;
            max-width: 100vw;
            overflow-x: hidden;
        }
        
        .hero-section {
            background: linear-gradient(135deg, #004080 0%, #0b6cf5 100%);
            color: white;
            padding: 80px 20px;
            text-align: center;
            width: 100%;
            position: relative;
        }
        .hero-section h1 {
            font-size: 3rem;
            margin-bottom: 20px;
            font-weight: 700;
        }
        .hero-section p {
            font-size: 1.3rem;
            opacity: 0.95;
            max-width: 600px;
            margin: 0 auto 40px;
        }
        .tracks-container {
            max-width: 1400px;
            margin: -60px auto 80px;
            padding: 0 20px;
            position: relative;
            z-index: 10;
            width: 100%;
        }
        .tracks-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(350px, 1fr));
            gap: 30px;
            margin-top: 40px;
        }
        .track-card {
            background: white;
            border-radius: 20px;
            padding: 40px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.15);
            transition: all 0.3s ease;
            cursor: pointer;
            text-decoration: none;
            color: inherit;
            display: block;
            position: relative;
            overflow: hidden;
        }
        .track-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 6px;
            background: linear-gradient(90deg, var(--track-color-1), var(--track-color-2));
            transform: scaleX(0);
            transition: transform 0.3s ease;
        }
        .track-card:hover {
            transform: translateY(-10px);
            box-shadow: 0 20px 60px rgba(0,0,0,0.2);
        }
        .track-card:hover::before {
            transform: scaleX(1);
        }
        .track-card.kids {
            --track-color-1: #ff6b9d;
            --track-color-2: #ffa500;
        }
        .track-card.adults {
            --track-color-1: #0b6cf5;
            --track-color-2: #004080;
        }
        .track-card.coding {
            --track-color-1: #00d4ff;
            --track-color-2: #0066cc;
        }
        .track-icon {
            font-size: 4rem;
            margin-bottom: 20px;
            display: block;
        }
        .track-card.kids .track-icon {
            color: #ff6b9d;
        }
        .track-card.adults .track-icon {
            color: #0b6cf5;
        }
        .track-card.coding .track-icon {
            color: #00d4ff;
        }
        .track-title {
            font-size: 2rem;
            font-weight: 700;
            margin-bottom: 15px;
            color: #004080;
        }
        .track-description {
            font-size: 1.1rem;
            color: #666;
            line-height: 1.6;
            margin-bottom: 25px;
        }
        .track-features {
            list-style: none;
            padding: 0;
            margin: 20px 0;
        }
        .track-features li {
            padding: 10px 0;
            color: #555;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .track-features li i {
            color: var(--track-color-1);
            font-size: 1.2rem;
        }
        .track-cta {
            display: inline-block;
            background: linear-gradient(135deg, var(--track-color-1), var(--track-color-2));
            color: white;
            padding: 15px 35px;
            border-radius: 50px;
            font-weight: 600;
            font-size: 1.1rem;
            text-decoration: none;
            margin-top: 20px;
            transition: transform 0.2s, box-shadow 0.2s;
            border: none;
            cursor: pointer;
        }
        .track-cta:hover {
            transform: scale(1.05);
            box-shadow: 0 8px 25px rgba(0,0,0,0.2);
        }
        .features-section {
            background: #f9fbff;
            padding: 80px 20px;
            text-align: center;
        }
        .features-section h2 {
            font-size: 2.5rem;
            color: #004080;
            margin-bottom: 50px;
        }
        .features-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 30px;
            max-width: 1200px;
            margin: 0 auto;
        }
        .feature-item {
            background: white;
            padding: 30px;
            border-radius: 15px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.08);
        }
        .feature-item i {
            font-size: 3rem;
            color: #0b6cf5;
            margin-bottom: 20px;
        }
        .feature-item h3 {
            color: #004080;
            margin-bottom: 10px;
        }
        .feature-item p {
            color: #666;
            line-height: 1.6;
        }
        /* Mobile Optimizations */
        @media (max-width: 768px) {
            .hero-section {
                padding: 50px 15px;
            }
            .hero-section h1 {
                font-size: 1.8rem;
                margin-bottom: 15px;
                line-height: 1.2;
            }
            .hero-section p {
                font-size: 1rem;
                margin-bottom: 30px;
                padding: 0 10px;
            }
            .tracks-container {
                margin: -40px auto 50px;
                padding: 0 15px;
            }
            .tracks-grid {
                grid-template-columns: 1fr;
                gap: 20px;
                margin-top: 30px;
            }
            .track-card {
                padding: 25px 20px;
                border-radius: 16px;
            }
            .track-icon {
                font-size: 3rem;
                margin-bottom: 15px;
            }
            .track-title {
                font-size: 1.4rem;
                margin-bottom: 12px;
            }
            .track-description {
                font-size: 0.95rem;
                margin-bottom: 20px;
                line-height: 1.5;
            }
            .track-features {
                margin: 15px 0;
            }
            .track-features li {
                padding: 8px 0;
                font-size: 0.9rem;
            }
            .track-features li i {
                font-size: 1rem;
            }
            .track-cta {
                padding: 12px 28px;
                font-size: 1rem;
                margin-top: 15px;
                width: 100%;
                text-align: center;
                display: flex;
                align-items: center;
                justify-content: center;
                min-height: 48px;
            }
            .features-section {
                padding: 50px 15px;
            }
            .features-section h2 {
                font-size: 1.8rem;
                margin-bottom: 30px;
            }
            .features-grid {
                grid-template-columns: 1fr;
                gap: 20px;
            }
            .feature-item {
                padding: 25px 20px;
            }
            .feature-item i {
                font-size: 2.5rem;
            }
        }
        
        @media (max-width: 480px) {
            .hero-section {
                padding: 40px 12px;
            }
            .hero-section h1 {
                font-size: 1.5rem;
                margin-bottom: 12px;
            }
            .hero-section p {
                font-size: 0.95rem;
                margin-bottom: 25px;
            }
            .tracks-container {
                margin: -30px auto 40px;
                padding: 0 12px;
            }
            .tracks-grid {
                gap: 15px;
                margin-top: 25px;
            }
            .track-card {
                padding: 20px 16px;
                border-radius: 12px;
            }
            .track-icon {
                font-size: 2.5rem;
                margin-bottom: 12px;
            }
            .track-title {
                font-size: 1.25rem;
                margin-bottom: 10px;
            }
            .track-description {
                font-size: 0.9rem;
                margin-bottom: 15px;
            }
            .track-features li {
                padding: 6px 0;
                font-size: 0.85rem;
            }
            .track-cta {
                padding: 14px 24px;
                font-size: 0.95rem;
                margin-top: 12px;
            }
            .features-section {
                padding: 40px 12px;
            }
            .features-section h2 {
                font-size: 1.5rem;
                margin-bottom: 25px;
            }
            .feature-item {
                padding: 20px 16px;
            }
            .feature-item i {
                font-size: 2rem;
            }
        }
        
        @media (max-width: 360px) {
            .hero-section {
                padding: 35px 10px;
            }
            .hero-section h1 {
                font-size: 1.3rem;
            }
            .hero-section p {
                font-size: 0.9rem;
            }
            .track-card {
                padding: 18px 14px;
            }
            .track-icon {
                font-size: 2rem;
            }
            .track-title {
                font-size: 1.1rem;
            }
            .track-description {
                font-size: 0.85rem;
            }
            .track-features li {
                font-size: 0.8rem;
            }
        }
    </style>
</head>
<body class="homepage">
<div class="homepage-wrapper">
    <header class="site-header" role="banner">
        <div class="header-left">
            <a href="index.php" style="text-decoration: none; display: flex; align-items: center;">
                <img src="<?php echo getAssetPath('logo.png'); ?>" alt="Staten Academy logo" class="site-logo">
            </a>
        </div>
        <div class="header-center">
            <div class="branding">
                <h1 class="site-title">Staten Academy</h1>
                <p class="site-tag">Learn English with professional teachers and flexible plans.</p>
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

    <main id="main-content">
        <section class="hero-section">
            <h1>Choose Your Learning Track</h1>
            <p>Select the perfect English learning path for you. Each track is designed with your goals in mind.</p>
        </section>

        <div class="tracks-container">
            <div class="tracks-grid">
                <a href="kids-plans.php" class="track-card kids">
                    <i class="fas fa-child track-icon"></i>
                    <h2 class="track-title">Kids Classes</h2>
                    <p class="track-description">Fun, interactive English lessons designed for children ages 3-11. Engaging activities, games, and age-appropriate content.</p>
                    <ul class="track-features">
                        <li><i class="fas fa-check-circle"></i> Ages 3-11</li>
                        <li><i class="fas fa-check-circle"></i> Interactive games & activities</li>
                        <li><i class="fas fa-check-circle"></i> Parent progress reports</li>
                        <li><i class="fas fa-check-circle"></i> Kid-friendly teachers</li>
                    </ul>
                    <span class="track-cta">View Kids Plans →</span>
                </a>

                <a href="adults-plans.php" class="track-card adults">
                    <i class="fas fa-user-graduate track-icon"></i>
                    <h2 class="track-title">Adult Classes</h2>
                    <p class="track-description">Professional English training for adults 12+. Focus on fluency, career advancement, travel, and real-world communication.</p>
                    <ul class="track-features">
                        <li><i class="fas fa-check-circle"></i> Ages 12+</li>
                        <li><i class="fas fa-check-circle"></i> Career & business English</li>
                        <li><i class="fas fa-check-circle"></i> Travel & conversation</li>
                        <li><i class="fas fa-check-circle"></i> Flexible scheduling</li>
                    </ul>
                    <span class="track-cta">View Adult Plans →</span>
                </a>

                <a href="coding-plans.php" class="track-card coding">
                    <i class="fas fa-code track-icon"></i>
                    <h2 class="track-title">English for Coding</h2>
                    <p class="track-description">Specialized English training for developers. Technical vocabulary, interview prep, documentation, and developer communication.</p>
                    <ul class="track-features">
                        <li><i class="fas fa-check-circle"></i> Technical vocabulary</li>
                        <li><i class="fas fa-check-circle"></i> Interview preparation</li>
                        <li><i class="fas fa-check-circle"></i> Code documentation</li>
                        <li><i class="fas fa-check-circle"></i> Developer scenarios</li>
                    </ul>
                    <span class="track-cta">View Coding Plans →</span>
                </a>
            </div>
        </div>

        <!-- Video Introduction Section -->
        <section class="video-intro-section" style="background: linear-gradient(135deg, #004080 0%, #0b6cf5 100%); color: white; padding: 80px 20px; text-align: center;">
            <div style="max-width: 1200px; margin: 0 auto;">
                <h2 style="font-size: 2.5rem; margin-bottom: 20px; font-weight: 700;">Discover Our Teaching Style</h2>
                <p style="font-size: 1.2rem; margin-bottom: 40px; opacity: 0.95; max-width: 700px; margin-left: auto; margin-right: auto;">
                    Watch our introduction video to learn about our unique teaching approach and hear from our students about their learning experience.
                </p>
                <div style="max-width: 900px; margin: 0 auto; position: relative; padding-bottom: 56.25%; height: 0; overflow: hidden; border-radius: 15px; box-shadow: 0 20px 60px rgba(0,0,0,0.3);">
                    <iframe 
                        id="intro-video" 
                        style="position: absolute; top: 0; left: 0; width: 100%; height: 100%; border: none;" 
                        src="https://www.youtube.com/embed/YOUR_VIDEO_ID_HERE" 
                        title="Staten Academy Introduction Video"
                        allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture" 
                        allowfullscreen>
                    </iframe>
                </div>
                <p style="margin-top: 30px; font-size: 0.95rem; opacity: 0.8;">
                    <i class="fas fa-info-circle"></i> Replace "YOUR_VIDEO_ID_HERE" with your YouTube video ID in the code
                </p>
            </div>
        </section>

        <section class="features-section">
            <h2>Why Choose Staten Academy?</h2>
            <div class="features-grid">
                <div class="feature-item">
                    <i class="fas fa-user-tie"></i>
                    <h3>Expert Teachers</h3>
                    <p>All our teachers are certified and experienced in their specialized tracks.</p>
                </div>
                <div class="feature-item">
                    <i class="fas fa-calendar-check"></i>
                    <h3>Flexible Scheduling</h3>
                    <p>Book classes that fit your schedule with our easy-to-use calendar system.</p>
                </div>
                <div class="feature-item">
                    <i class="fas fa-chart-line"></i>
                    <h3>Track Your Progress</h3>
                    <p>Monitor your improvement with detailed progress reports and analytics.</p>
                </div>
                <div class="feature-item">
                    <i class="fas fa-users"></i>
                    <h3>Group Classes</h3>
                    <p>Join group sessions to practice with peers and enhance your learning.</p>
                </div>
            </div>
        </section>

        <!-- How We Work Section -->
        <section style="background: linear-gradient(135deg, #f0f7ff 0%, #ffffff 100%); padding: 80px 20px;">
            <div style="max-width: 1200px; margin: 0 auto;">
                <div style="text-align: center; margin-bottom: 50px;">
                    <h2 style="font-size: 2.5rem; color: #004080; margin-bottom: 15px;">How We Work</h2>
                    <p style="font-size: 1.2rem; color: #666; max-width: 800px; margin: 0 auto; line-height: 1.8;">
                        Getting started with Staten Academy is simple and straightforward. Follow these steps to begin your English learning journey.
                    </p>
                </div>

                <!-- Visual Process Timeline -->
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 30px; margin-bottom: 40px; position: relative;">
                    <div style="text-align: center; position: relative;">
                        <div style="width: 80px; height: 80px; background: linear-gradient(135deg, #0b6cf5 0%, #004080 100%); border-radius: 50%; display: flex; align-items: center; justify-content: center; color: white; font-size: 2rem; font-weight: bold; margin: 0 auto 20px;">
                            1
                        </div>
                        <h3 style="color: #004080; margin-bottom: 10px; font-size: 1.3rem;">Sign Up</h3>
                        <p style="color: #666; line-height: 1.6;">Create your account and choose your learning track</p>
                    </div>

                    <div style="text-align: center; position: relative;">
                        <div style="width: 80px; height: 80px; background: linear-gradient(135deg, #28a745 0%, #1e7e34 100%); border-radius: 50%; display: flex; align-items: center; justify-content: center; color: white; font-size: 2rem; font-weight: bold; margin: 0 auto 20px;">
                            2
                        </div>
                        <h3 style="color: #004080; margin-bottom: 10px; font-size: 1.3rem;">Assessment</h3>
                        <p style="color: #666; line-height: 1.6;">Complete your learning needs assessment</p>
                    </div>

                    <div style="text-align: center; position: relative;">
                        <div style="width: 80px; height: 80px; background: linear-gradient(135deg, #ffc107 0%, #e0a800 100%); border-radius: 50%; display: flex; align-items: center; justify-content: center; color: white; font-size: 2rem; font-weight: bold; margin: 0 auto 20px;">
                            3
                        </div>
                        <h3 style="color: #004080; margin-bottom: 10px; font-size: 1.3rem;">Get Matched</h3>
                        <p style="color: #666; line-height: 1.6;">We match you with the perfect teacher</p>
                    </div>

                    <div style="text-align: center; position: relative;">
                        <div style="width: 80px; height: 80px; background: linear-gradient(135deg, #dc3545 0%, #c82333 100%); border-radius: 50%; display: flex; align-items: center; justify-content: center; color: white; font-size: 2rem; font-weight: bold; margin: 0 auto 20px;">
                            4
                        </div>
                        <h3 style="color: #004080; margin-bottom: 10px; font-size: 1.3rem;">Start Learning</h3>
                        <p style="color: #666; line-height: 1.6;">Book classes and start your journey!</p>
                    </div>
                </div>

                <div style="text-align: center; margin-top: 40px;">
                    <a href="how-we-work.php" class="btn-primary" style="padding: 15px 40px; font-size: 1.1rem; text-decoration: none; display: inline-block; border-radius: 8px;">
                        <i class="fas fa-info-circle"></i> Learn More About How We Work
                    </a>
                </div>
            </div>
        </section>

        <!-- About Us Section -->
        <section style="background: white; padding: 80px 20px;">
            <div style="max-width: 1200px; margin: 0 auto;">
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 60px; align-items: center;">
                    <div>
                        <h2 style="font-size: 2.5rem; color: #004080; margin-bottom: 20px;">About Staten Academy</h2>
                        <p style="color: #666; line-height: 1.8; font-size: 1.1rem; margin-bottom: 20px;">
                            Staten Academy is dedicated to providing high-quality, personalized English language education 
                            that adapts to each learner's unique needs and goals.
                        </p>
                        <p style="color: #666; line-height: 1.8; font-size: 1.1rem; margin-bottom: 30px;">
                            We believe that language learning should be engaging, accessible, and effective. Our platform 
                            connects students with expert teachers through interactive online classes, flexible scheduling, 
                            and comprehensive progress tracking.
                        </p>
                        <div style="display: flex; gap: 15px; flex-wrap: wrap;">
                            <a href="about.php" class="btn-primary" style="padding: 12px 30px; font-size: 1rem; text-decoration: none; display: inline-block; border-radius: 8px;">
                                <i class="fas fa-info-circle"></i> Learn More About Us
                            </a>
                            <a href="how-we-work.php" class="btn-outline" style="padding: 12px 30px; font-size: 1rem; text-decoration: none; display: inline-block; border: 2px solid #0b6cf5; color: #0b6cf5; border-radius: 8px;">
                                <i class="fas fa-arrow-right"></i> Our Process
                            </a>
                        </div>
                    </div>
                    <div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 20px;">
                        <div style="background: linear-gradient(135deg, #f0f7ff 0%, #ffffff 100%); padding: 30px; border-radius: 12px; text-align: center; border: 2px solid #e0e7ff;">
                            <div style="font-size: 2.5rem; color: #0b6cf5; margin-bottom: 10px;">
                                <i class="fas fa-user-tie"></i>
                            </div>
                            <h3 style="color: #004080; margin-bottom: 10px;">Expert Teachers</h3>
                            <p style="color: #666; font-size: 0.9rem;">Certified and experienced</p>
                        </div>
                        <div style="background: linear-gradient(135deg, #f0fff4 0%, #ffffff 100%); padding: 30px; border-radius: 12px; text-align: center; border: 2px solid #c3e6cb;">
                            <div style="font-size: 2.5rem; color: #28a745; margin-bottom: 10px;">
                                <i class="fas fa-laptop"></i>
                            </div>
                            <h3 style="color: #004080; margin-bottom: 10px;">Online Platform</h3>
                            <p style="color: #666; font-size: 0.9rem;">Interactive and modern</p>
                        </div>
                        <div style="background: linear-gradient(135deg, #fff4f0 0%, #ffffff 100%); padding: 30px; border-radius: 12px; text-align: center; border: 2px solid #ffc3b3;">
                            <div style="font-size: 2.5rem; color: #dc3545; margin-bottom: 10px;">
                                <i class="fas fa-globe"></i>
                            </div>
                            <h3 style="color: #004080; margin-bottom: 10px;">Global Reach</h3>
                            <p style="color: #666; font-size: 0.9rem;">Students worldwide</p>
                        </div>
                        <div style="background: linear-gradient(135deg, #f0f9ff 0%, #ffffff 100%); padding: 30px; border-radius: 12px; text-align: center; border: 2px solid #b3d9ff;">
                            <div style="font-size: 2.5rem; color: #17a2b8; margin-bottom: 10px;">
                                <i class="fas fa-chart-line"></i>
                            </div>
                            <h3 style="color: #004080; margin-bottom: 10px;">Track Progress</h3>
                            <p style="color: #666; font-size: 0.9rem;">Monitor improvement</p>
                        </div>
                    </div>
                </div>
            </div>
        </section>
    </main>

    <footer style="background: #004080; color: white; padding: 40px 20px; text-align: center;">
        <div style="max-width: 1200px; margin: 0 auto;">
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 30px; margin-bottom: 30px; text-align: left;">
                <div>
                    <h3 style="margin-bottom: 15px; color: white;">Staten Academy</h3>
                    <p style="color: rgba(255,255,255,0.8); line-height: 1.8; font-size: 0.95rem;">
                        Empowering learners worldwide to achieve their English language goals.
                    </p>
                </div>
                <div>
                    <h3 style="margin-bottom: 15px; color: white;">Quick Links</h3>
                    <ul style="list-style: none; padding: 0; line-height: 2;">
                        <li><a href="index.php" style="color: rgba(255,255,255,0.8); text-decoration: none;">Home</a></li>
                        <li><a href="about.php" style="color: rgba(255,255,255,0.8); text-decoration: none;">About Us</a></li>
                        <li><a href="how-we-work.php" style="color: rgba(255,255,255,0.8); text-decoration: none;">How We Work</a></li>
                        <li><a href="payment.php" style="color: rgba(255,255,255,0.8); text-decoration: none;">Plans & Pricing</a></li>
                    </ul>
                </div>
                <div>
                    <h3 style="margin-bottom: 15px; color: white;">Learning Tracks</h3>
                    <ul style="list-style: none; padding: 0; line-height: 2;">
                        <li><a href="kids-plans.php" style="color: rgba(255,255,255,0.8); text-decoration: none;">Kids Classes</a></li>
                        <li><a href="adults-plans.php" style="color: rgba(255,255,255,0.8); text-decoration: none;">Adult Classes</a></li>
                        <li><a href="coding-plans.php" style="color: rgba(255,255,255,0.8); text-decoration: none;">English for Coding</a></li>
                    </ul>
                </div>
                <div>
                    <h3 style="margin-bottom: 15px; color: white;">Support</h3>
                    <ul style="list-style: none; padding: 0; line-height: 2;">
                        <li><a href="support_contact.php" style="color: rgba(255,255,255,0.8); text-decoration: none;">Contact Support</a></li>
                        <?php if (!isset($_SESSION['user_id'])): ?>
                            <li><a href="login.php" style="color: rgba(255,255,255,0.8); text-decoration: none;">Login</a></li>
                            <li><a href="register.php" style="color: rgba(255,255,255,0.8); text-decoration: none;">Sign Up</a></li>
                        <?php endif; ?>
                    </ul>
                </div>
            </div>
            <hr style="border: none; border-top: 1px solid rgba(255,255,255,0.2); margin: 30px 0;">
            <p style="color: rgba(255,255,255,0.8); margin: 0;">
                &copy; <?php echo date('Y'); ?> Staten Academy. All rights reserved.
            </p>
        </div>
    </footer>
    <script src="<?php echo getAssetPath('js/menu.js'); ?>" defer></script>
    <script>
    // #region agent log - Debug header overlap
    (function() {
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', runHeaderDebug);
        } else {
            runHeaderDebug();
        }
        
        function runHeaderDebug() {
            setTimeout(function() {
                const header = document.querySelector('header.site-header');
                const headerCenter = document.querySelector('.header-center');
                const menuToggle = document.querySelector('.menu-toggle');
                const headerLeft = document.querySelector('.header-left');
                
                if (header) {
                    const headerStyles = window.getComputedStyle(header);
                    const headerRect = header.getBoundingClientRect();
                    fetch('http://127.0.0.1:7242/ingest/19b51a8d-24f8-49a9-92f3-0619a89fb936',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({location:'index.php:header-debug',message:'Header layout',data:{width:headerRect.width,left:headerRect.left,right:headerRect.right,computedPadding:headerStyles.padding,computedPaddingRight:headerStyles.paddingRight,viewportWidth:window.innerWidth},timestamp:Date.now(),sessionId:'debug-session',runId:'run1',hypothesisId:'A'})}).catch(()=>{});
                }
                
                if (headerCenter) {
                    const centerStyles = window.getComputedStyle(headerCenter);
                    const centerRect = headerCenter.getBoundingClientRect();
                    fetch('http://127.0.0.1:7242/ingest/19b51a8d-24f8-49a9-92f3-0619a89fb936',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({location:'index.php:header-debug',message:'Header center layout',data:{width:centerRect.width,left:centerRect.left,right:centerRect.right,computedWidth:centerStyles.width,computedMaxWidth:centerStyles.maxWidth,computedLeft:centerStyles.left,computedTransform:centerStyles.transform,viewportWidth:window.innerWidth},timestamp:Date.now(),sessionId:'debug-session',runId:'run1',hypothesisId:'B'})}).catch(()=>{});
                }
                
                if (menuToggle) {
                    const toggleStyles = window.getComputedStyle(menuToggle);
                    const toggleRect = menuToggle.getBoundingClientRect();
                    fetch('http://127.0.0.1:7242/ingest/19b51a8d-24f8-49a9-92f3-0619a89fb936',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({location:'index.php:header-debug',message:'Menu toggle layout',data:{width:toggleRect.width,left:toggleRect.left,right:toggleRect.right,computedRight:toggleStyles.right,computedPosition:toggleStyles.position,computedZIndex:toggleStyles.zIndex,viewportWidth:window.innerWidth},timestamp:Date.now(),sessionId:'debug-session',runId:'run1',hypothesisId:'C'})}).catch(()=>{});
                }
                
                if (headerLeft) {
                    const leftStyles = window.getComputedStyle(headerLeft);
                    const leftRect = headerLeft.getBoundingClientRect();
                    fetch('http://127.0.0.1:7242/ingest/19b51a8d-24f8-49a9-92f3-0619a89fb936',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({location:'index.php:header-debug',message:'Header left layout',data:{width:leftRect.width,left:leftRect.left,right:leftRect.right,computedWidth:leftStyles.width,viewportWidth:window.innerWidth},timestamp:Date.now(),sessionId:'debug-session',runId:'run1',hypothesisId:'D'})}).catch(()=>{});
                }
                
                // Check for overlap
                if (headerCenter && menuToggle) {
                    const centerRect = headerCenter.getBoundingClientRect();
                    const toggleRect = menuToggle.getBoundingClientRect();
                    const overlap = centerRect.right > toggleRect.left;
                    fetch('http://127.0.0.1:7242/ingest/19b51a8d-24f8-49a9-92f3-0619a89fb936',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({location:'index.php:header-debug',message:'Overlap detection',data:{overlap:overlap,centerRight:centerRect.right,toggleLeft:toggleRect.left,gap:toggleRect.left - centerRect.right,viewportWidth:window.innerWidth},timestamp:Date.now(),sessionId:'debug-session',runId:'run1',hypothesisId:'E'})}).catch(()=>{});
                }
                
                // Check hamburger vertical centering
                if (header && menuToggle) {
                    const headerRect = header.getBoundingClientRect();
                    const toggleRect = menuToggle.getBoundingClientRect();
                    const headerCenterY = headerRect.top + (headerRect.height / 2);
                    const toggleCenterY = toggleRect.top + (toggleRect.height / 2);
                    const verticalOffset = Math.abs(headerCenterY - toggleCenterY);
                    const toggleStyles = window.getComputedStyle(menuToggle);
                    const hamburgerIcon = menuToggle.querySelector('.hamburger');
                    let iconData = {};
                    if (hamburgerIcon) {
                        const iconRect = hamburgerIcon.getBoundingClientRect();
                        const iconStyles = window.getComputedStyle(hamburgerIcon);
                        const buttonCenterY = toggleRect.top + (toggleRect.height / 2);
                        const iconCenterY = iconRect.top + (iconRect.height / 2);
                        const iconVerticalOffset = Math.abs(buttonCenterY - iconCenterY);
                        iconData = {
                            iconWidth: iconRect.width,
                            iconHeight: iconRect.height,
                            iconTop: iconRect.top,
                            iconCenterY: iconCenterY,
                            buttonCenterY: buttonCenterY,
                            iconVerticalOffset: iconVerticalOffset,
                            computedMargin: iconStyles.margin,
                            computedPosition: iconStyles.position
                        };
                    }
                    fetch('http://127.0.0.1:7242/ingest/19b51a8d-24f8-49a9-92f3-0619a89fb936',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({location:'index.php:header-debug',message:'Hamburger centering',data:{headerHeight:headerRect.height,headerCenterY:headerCenterY,toggleHeight:toggleRect.height,toggleCenterY:toggleCenterY,verticalOffset:verticalOffset,computedTop:toggleStyles.top,computedTransform:toggleStyles.transform,computedPadding:toggleStyles.padding,computedDisplay:toggleStyles.display,computedAlignItems:toggleStyles.alignItems,computedJustifyContent:toggleStyles.justifyContent,viewportWidth:window.innerWidth,...iconData},timestamp:Date.now(),sessionId:'debug-session',runId:'run1',hypothesisId:'F'})}).catch(()=>{});
                }
                
                // Check header balance
                if (header && headerLeft && menuToggle) {
                    const headerRect = header.getBoundingClientRect();
                    const leftRect = headerLeft.getBoundingClientRect();
                    const toggleRect = menuToggle.getBoundingClientRect();
                    const leftSpace = leftRect.width + (leftRect.left - headerRect.left);
                    const rightSpace = headerRect.right - toggleRect.right;
                    const balance = Math.abs(leftSpace - rightSpace);
                    fetch('http://127.0.0.1:7242/ingest/19b51a8d-24f8-49a9-92f3-0619a89fb936',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({location:'index.php:header-debug',message:'Header balance',data:{leftSpace:leftSpace,rightSpace:rightSpace,balance:balance,headerWidth:headerRect.width,logoWidth:leftRect.width,hamburgerWidth:toggleRect.width,viewportWidth:window.innerWidth},timestamp:Date.now(),sessionId:'debug-session',runId:'run1',hypothesisId:'G'})}).catch(()=>{});
                }
            }, 500);
        }
    })();
    // #endregion
    </script>
</div>
</body>
</html>
