<?php
session_start();
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/app/Views/components/dashboard-functions.php';

$page_title = "About Us - Staten Academy";
$page_description = "Learn about Staten Academy, our mission, values, and why we're the best choice for English learning.";

// Set user if logged in
$user = null;
if (isset($_SESSION['user_id'])) {
    $user = getUserById($conn, $_SESSION['user_id']);
}

// Ensure getAssetPath function is available
if (!function_exists('getAssetPath')) {
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
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=5.0, user-scalable=yes">
    <meta name="theme-color" content="#004080">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="description" content="<?php echo htmlspecialchars($page_description); ?>">
    <title><?php echo htmlspecialchars($page_title); ?></title>
    <link rel="stylesheet" href="<?php echo getAssetPath('styles.css'); ?>">
    <link rel="stylesheet" href="<?php echo getAssetPath('css/mobile.css'); ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body>

<header class="site-header" role="banner">
    <div class="header-left">
        <a href="index.php" style="text-decoration: none; display: flex; align-items: center;">
            <img src="<?php echo getLogoPath(); ?>" alt="Staten Academy logo" class="site-logo">
        </a>
    </div>
    <div class="header-center">
        <div class="branding">
            <h1 class="site-title">Staten Academy</h1>
            <p class="site-tag">Learn English with professional teachers and flexible plans.</p>
        </div>
    </div>
    <?php include __DIR__ . '/header-user.php'; ?>
    <button id="menu-toggle" class="menu-toggle" aria-controls="mobile-menu" aria-expanded="false" aria-label="Open navigation menu">
        <span class="hamburger" aria-hidden="true"></span>
    </button>
    <div id="mobile-menu" class="mobile-menu" role="menu" aria-hidden="true">
        <button class="close-btn" id="mobile-close" aria-label="Close menu">✕</button>
        <a class="nav-btn" href="index.php">
            <svg class="nav-icon" viewBox="0 0 24 24"><path fill="#06385a" d="M10 20v-6h4v6h5v-8h3L12 3 2 12h3v8z"/></svg>
            <span class="nav-label">Home</span>
        </a>
        <a class="nav-btn" href="about.php">
            <svg class="nav-icon" viewBox="0 0 24 24"><path fill="#06385a" d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm1 15h-2v-2h2v2zm0-4h-2V7h2v6z"/></svg>
            <span class="nav-label">About</span>
        </a>
        <a class="nav-btn" href="how-we-work.php">
            <svg class="nav-icon" viewBox="0 0 24 24"><path fill="#06385a" d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/></svg>
            <span class="nav-label">How We Work</span>
        </a>
        <a class="nav-btn" href="kids-plans.php">
            <svg class="nav-icon" viewBox="0 0 24 24"><path fill="#06385a" d="M20 4H4c-1.11 0-1.99.89-1.99 2L2 18c0 1.11.89 2 2 2h16c1.11 0 2-.89 2-2V6c0-1.11-.89-2-2-2zm0 14H4v-6h16v6zm0-10H4V6h16v2z"/></svg>
            <span class="nav-label">Plans</span>
        </a>
        <?php if (!isset($_SESSION['user_id'])): ?>
            <a class="nav-btn" href="login.php">
                <svg class="nav-icon" viewBox="0 0 24 24"><path fill="#06385a" d="M10 17l5-5-5-5v10z"/></svg>
                <span class="nav-label">Login</span>
            </a>
        <?php endif; ?>
    </div>
</header>

<main style="padding-top: 80px;">
    <!-- Hero Section -->
    <section style="background: linear-gradient(135deg, #0b6cf5 0%, #004080 100%); color: white; padding: 80px 20px; text-align: center;">
        <div class="container" style="max-width: 1200px; margin: 0 auto;">
            <h1 style="font-size: 3rem; margin-bottom: 20px; font-weight: 700;">About Staten Academy</h1>
            <p style="font-size: 1.3rem; max-width: 800px; margin: 0 auto; line-height: 1.8;">
                Empowering learners worldwide to achieve their English language goals through personalized, 
                interactive, and engaging online education.
            </p>
        </div>
    </section>

    <!-- Mission Section -->
    <section style="padding: 60px 20px; background: white;">
        <div class="container" style="max-width: 1200px; margin: 0 auto;">
            <div style="text-align: center; margin-bottom: 50px;">
                <h2 style="font-size: 2.5rem; color: #004080; margin-bottom: 15px;">Our Mission</h2>
                <p style="font-size: 1.2rem; color: #666; max-width: 900px; margin: 0 auto;">
                    To provide high-quality, accessible English language education that adapts to each learner's 
                    unique needs, learning style, and goals. We believe that language learning should be engaging, 
                    personalized, and effective.
                </p>
            </div>

            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 30px; margin-top: 50px;">
                <div style="text-align: center; padding: 30px; border-radius: 12px; background: #f8f9fa;">
                    <div style="font-size: 3rem; margin-bottom: 20px; color: #0b6cf5;">
                        <i class="fas fa-heart"></i>
                    </div>
                    <h3 style="color: #004080; margin-bottom: 15px;">Passion for Education</h3>
                    <p style="color: #666; line-height: 1.7;">
                        We're passionate about helping students achieve their language learning goals through innovative teaching methods.
                    </p>
                </div>

                <div style="text-align: center; padding: 30px; border-radius: 12px; background: #f8f9fa;">
                    <div style="font-size: 3rem; margin-bottom: 20px; color: #0b6cf5;">
                        <i class="fas fa-users"></i>
                    </div>
                    <h3 style="color: #004080; margin-bottom: 15px;">Student-Centered</h3>
                    <p style="color: #666; line-height: 1.7;">
                        Every student is unique. We tailor our approach to match individual learning styles, preferences, and objectives.
                    </p>
                </div>

                <div style="text-align: center; padding: 30px; border-radius: 12px; background: #f8f9fa;">
                    <div style="font-size: 3rem; margin-bottom: 20px; color: #0b6cf5;">
                        <i class="fas fa-globe"></i>
                    </div>
                    <h3 style="color: #004080; margin-bottom: 15px;">Global Reach</h3>
                    <p style="color: #666; line-height: 1.7;">
                        Connecting students and teachers from around the world to create meaningful learning experiences.
                    </p>
                </div>
            </div>
        </div>
    </section>

    <!-- Why Choose Us Section -->
    <section style="padding: 60px 20px; background: linear-gradient(135deg, #f0f7ff 0%, #ffffff 100%);">
        <div class="container" style="max-width: 1200px; margin: 0 auto;">
            <h2 style="font-size: 2.5rem; color: #004080; text-align: center; margin-bottom: 50px;">Why Choose Staten Academy?</h2>
            
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 25px;">
                <div style="background: white; padding: 30px; border-radius: 12px; box-shadow: 0 4px 15px rgba(0,0,0,0.1); border-left: 4px solid #0b6cf5;">
                    <div style="font-size: 2rem; color: #0b6cf5; margin-bottom: 15px;">
                        <i class="fas fa-user-tie"></i>
                    </div>
                    <h3 style="color: #004080; margin-bottom: 10px;">Expert Teachers</h3>
                    <p style="color: #666; line-height: 1.7;">
                        Our teachers are experienced, certified, and passionate about helping you succeed. 
                        All teachers are pre-approved by administrators for specific learning tracks, and you can browse and select the teacher that best fits your needs.
                    </p>
                </div>

                <div style="background: white; padding: 30px; border-radius: 12px; box-shadow: 0 4px 15px rgba(0,0,0,0.1); border-left: 4px solid #28a745;">
                    <div style="font-size: 2rem; color: #28a745; margin-bottom: 15px;">
                        <i class="fas fa-calendar-check"></i>
                    </div>
                    <h3 style="color: #004080; margin-bottom: 10px;">Flexible Scheduling</h3>
                    <p style="color: #666; line-height: 1.7;">
                        Learn on your schedule. Book classes at times that work for you, whether it's early morning, 
                        late evening, or weekends.
                    </p>
                </div>

                <div style="background: white; padding: 30px; border-radius: 12px; box-shadow: 0 4px 15px rgba(0,0,0,0.1); border-left: 4px solid #ffc107;">
                    <div style="font-size: 2rem; color: #ffc107; margin-bottom: 15px;">
                        <i class="fas fa-laptop"></i>
                    </div>
                    <h3 style="color: #004080; margin-bottom: 10px;">Interactive Platform</h3>
                    <p style="color: #666; line-height: 1.7;">
                        Our modern platform makes learning engaging with interactive materials, real-time collaboration, 
                        and progress tracking.
                    </p>
                </div>

                <div style="background: white; padding: 30px; border-radius: 12px; box-shadow: 0 4px 15px rgba(0,0,0,0.1); border-left: 4px solid #17a2b8;">
                    <div style="font-size: 2rem; color: #17a2b8; margin-bottom: 15px;">
                        <i class="fas fa-chart-line"></i>
                    </div>
                    <h3 style="color: #004080; margin-bottom: 10px;">Progress Tracking</h3>
                    <p style="color: #666; line-height: 1.7;">
                        Monitor your improvement with detailed progress reports, assignments, and personalized feedback 
                        from your teacher.
                    </p>
                </div>

                <div style="background: white; padding: 30px; border-radius: 12px; box-shadow: 0 4px 15px rgba(0,0,0,0.1); border-left: 4px solid #dc3545;">
                    <div style="font-size: 2rem; color: #dc3545; margin-bottom: 15px;">
                        <i class="fas fa-dollar-sign"></i>
                    </div>
                    <h3 style="color: #004080; margin-bottom: 10px;">Affordable Plans</h3>
                    <p style="color: #666; line-height: 1.7;">
                        We offer flexible pricing plans to fit every budget, from single classes to monthly subscriptions 
                        with group and one-on-one options.
                    </p>
                </div>

                <div style="background: white; padding: 30px; border-radius: 12px; box-shadow: 0 4px 15px rgba(0,0,0,0.1); border-left: 4px solid #6f42c1;">
                    <div style="font-size: 2rem; color: #6f42c1; margin-bottom: 15px;">
                        <i class="fas fa-graduation-cap"></i>
                    </div>
                    <h3 style="color: #004080; margin-bottom: 10px;">Group Classes for Kids</h3>
                    <p style="color: #666; line-height: 1.7;">
                        Our Group Classes are designed for children ages 3-11, offering fun, interactive sessions 
                        with peers for just $129.99/month (3 classes per week).
                    </p>
                </div>
            </div>
        </div>
    </section>

    <!-- Values Section -->
    <section style="padding: 60px 20px; background: white;">
        <div class="container" style="max-width: 1200px; margin: 0 auto;">
            <h2 style="font-size: 2.5rem; color: #004080; text-align: center; margin-bottom: 50px;">Our Values</h2>
            
            <div style="max-width: 900px; margin: 0 auto;">
                <div style="margin-bottom: 30px; padding: 25px; background: #f8f9fa; border-radius: 10px; border-left: 5px solid #0b6cf5;">
                    <h3 style="color: #004080; margin-bottom: 10px; display: flex; align-items: center; gap: 10px;">
                        <i class="fas fa-check-circle" style="color: #0b6cf5;"></i>
                        Excellence
                    </h3>
                    <p style="color: #666; line-height: 1.8; margin: 0;">
                        We strive for excellence in everything we do, from teacher selection to curriculum design 
                        and student support.
                    </p>
                </div>

                <div style="margin-bottom: 30px; padding: 25px; background: #f8f9fa; border-radius: 10px; border-left: 5px solid #28a745;">
                    <h3 style="color: #004080; margin-bottom: 10px; display: flex; align-items: center; gap: 10px;">
                        <i class="fas fa-check-circle" style="color: #28a745;"></i>
                        Integrity
                    </h3>
                    <p style="color: #666; line-height: 1.8; margin: 0;">
                        We conduct our business with honesty, transparency, and respect for all students, teachers, 
                        and partners.
                    </p>
                </div>

                <div style="margin-bottom: 30px; padding: 25px; background: #f8f9fa; border-radius: 10px; border-left: 5px solid #ffc107;">
                    <h3 style="color: #004080; margin-bottom: 10px; display: flex; align-items: center; gap: 10px;">
                        <i class="fas fa-check-circle" style="color: #ffc107;"></i>
                        Innovation
                    </h3>
                    <p style="color: #666; line-height: 1.8; margin: 0;">
                        We continuously improve our platform and teaching methods to provide the best possible 
                        learning experience.
                    </p>
                </div>

                <div style="margin-bottom: 30px; padding: 25px; background: #f8f9fa; border-radius: 10px; border-left: 5px solid #17a2b8;">
                    <h3 style="color: #004080; margin-bottom: 10px; display: flex; align-items: center; gap: 10px;">
                        <i class="fas fa-check-circle" style="color: #17a2b8;"></i>
                        Support
                    </h3>
                    <p style="color: #666; line-height: 1.8; margin: 0;">
                        We're committed to supporting our students every step of the way, from enrollment to 
                        achieving their language goals.
                    </p>
                </div>
            </div>
        </div>
    </section>

    <!-- Testimonials Section -->
    <section style="padding: 60px 20px; background: linear-gradient(135deg, #0b6cf5 0%, #004080 100%); color: white;">
        <div class="container" style="max-width: 1200px; margin: 0 auto;">
            <h2 style="font-size: 2.5rem; text-align: center; margin-bottom: 50px;">What Our Students Say</h2>
            
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 30px;">
                <div style="background: rgba(255,255,255,0.1); padding: 30px; border-radius: 12px; backdrop-filter: blur(10px);">
                    <div style="margin-bottom: 15px;">
                        <i class="fas fa-star" style="color: #ffc107;"></i>
                        <i class="fas fa-star" style="color: #ffc107;"></i>
                        <i class="fas fa-star" style="color: #ffc107;"></i>
                        <i class="fas fa-star" style="color: #ffc107;"></i>
                        <i class="fas fa-star" style="color: #ffc107;"></i>
                    </div>
                    <p style="font-style: italic; line-height: 1.8; margin-bottom: 20px;">
                        "Staten Academy has transformed my English skills! The teachers are patient and 
                        the platform is easy to use. Highly recommended!"
                    </p>
                    <p style="font-weight: 600;">— Sarah M., Adult Student</p>
                </div>

                <div style="background: rgba(255,255,255,0.1); padding: 30px; border-radius: 12px; backdrop-filter: blur(10px);">
                    <div style="margin-bottom: 15px;">
                        <i class="fas fa-star" style="color: #ffc107;"></i>
                        <i class="fas fa-star" style="color: #ffc107;"></i>
                        <i class="fas fa-star" style="color: #ffc107;"></i>
                        <i class="fas fa-star" style="color: #ffc107;"></i>
                        <i class="fas fa-star" style="color: #ffc107;"></i>
                    </div>
                    <p style="font-style: italic; line-height: 1.8; margin-bottom: 20px;">
                        "My 8-year-old loves the kids classes! The interactive materials keep him engaged 
                        and he's learning so much faster than in traditional classes."
                    </p>
                    <p style="font-weight: 600;">— Maria L., Parent</p>
                </div>

                <div style="background: rgba(255,255,255,0.1); padding: 30px; border-radius: 12px; backdrop-filter: blur(10px);">
                    <div style="margin-bottom: 15px;">
                        <i class="fas fa-star" style="color: #ffc107;"></i>
                        <i class="fas fa-star" style="color: #ffc107;"></i>
                        <i class="fas fa-star" style="color: #ffc107;"></i>
                        <i class="fas fa-star" style="color: #ffc107;"></i>
                        <i class="fas fa-star" style="color: #ffc107;"></i>
                    </div>
                    <p style="font-style: italic; line-height: 1.8; margin-bottom: 20px;">
                        "The English for Coding track helped me improve my technical communication skills. 
                        Perfect for developers looking to enhance their English!"
                    </p>
                    <p style="font-weight: 600;">— John D., Coding Student</p>
                </div>
            </div>
        </div>
    </section>

    <!-- Contact Section -->
    <section style="padding: 60px 20px; background: white;">
        <div class="container" style="max-width: 1200px; margin: 0 auto; text-align: center;">
            <h2 style="font-size: 2.5rem; color: #004080; margin-bottom: 30px;">Get in Touch</h2>
            <p style="font-size: 1.2rem; color: #666; margin-bottom: 40px; max-width: 700px; margin-left: auto; margin-right: auto;">
                Have questions? We're here to help! Reach out to us through our support system or check out 
                our <a href="how-we-work.php" style="color: #0b6cf5; text-decoration: underline;">How We Work</a> page.
            </p>
            <div style="display: flex; justify-content: center; gap: 30px; flex-wrap: wrap;">
                <a href="support_contact.php" class="btn-primary" style="padding: 15px 40px; font-size: 1.1rem; text-decoration: none; display: inline-block;">
                    <i class="fas fa-envelope"></i> Contact Support
                </a>
                <a href="index.php" class="btn-outline" style="padding: 15px 40px; font-size: 1.1rem; text-decoration: none; display: inline-block;">
                    <i class="fas fa-home"></i> Back to Home
                </a>
            </div>
        </div>
    </section>
</main>

<?php include __DIR__ . '/app/Views/components/footer.php'; ?>


