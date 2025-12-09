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
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=5.0, user-scalable=yes">
    <meta name="theme-color" content="#004080">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="description" content="Staten Academy - Choose your English learning track: Kids, Adults, or English for Coding">
    <title>Staten Academy - Choose Your Learning Track</title>
    <link rel="stylesheet" href="<?php echo getAssetPath('styles.css'); ?>">
    <link rel="stylesheet" href="<?php echo getAssetPath('css/mobile.css'); ?>">
    <link rel="stylesheet" href="<?php echo getAssetPath('css/tracks.css'); ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .hero-section {
            background: linear-gradient(135deg, #004080 0%, #0b6cf5 100%);
            color: white;
            padding: 80px 20px;
            text-align: center;
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
        @media (max-width: 768px) {
            .hero-section h1 {
                font-size: 2rem;
            }
            .hero-section p {
                font-size: 1.1rem;
            }
            .tracks-grid {
                grid-template-columns: 1fr;
                gap: 20px;
            }
            .track-card {
                padding: 30px;
            }
            .track-title {
                font-size: 1.5rem;
            }
        }
    </style>
</head>
<body>
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
                <?php elseif ($user_role === 'student'): ?>
                    <a class="nav-btn" href="student-dashboard.php" style="background-color: #28a745; color: white; border: none;">
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
    </main>

    <footer>
        <p>&copy; <?php echo date('Y'); ?> Staten Academy. All rights reserved.</p>
    </footer>
    <script src="<?php echo getAssetPath('js/menu.js'); ?>" defer></script>
</body>
</html>
