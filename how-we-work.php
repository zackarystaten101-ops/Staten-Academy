<?php
session_start();
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/app/Views/components/dashboard-functions.php';

$page_title = "How We Work - Staten Academy";
$page_description = "Learn how Staten Academy works - from signup to classes, discover our learning process.";

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
            <img src="<?php echo getAssetPath('logo.png'); ?>" alt="Staten Academy logo" class="site-logo">
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
        <a class="nav-btn" href="payment.php">
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
            <h1 style="font-size: 3rem; margin-bottom: 20px; font-weight: 700;">How We Work</h1>
            <p style="font-size: 1.3rem; max-width: 800px; margin: 0 auto; line-height: 1.8;">
                Your journey to English fluency starts here. Simple, personalized, and effective.
            </p>
        </div>
    </section>

    <!-- Process Steps -->
    <section style="padding: 60px 20px; background: white;">
        <div class="container" style="max-width: 1200px; margin: 0 auto;">
            <h2 style="font-size: 2.5rem; color: #004080; text-align: center; margin-bottom: 50px;">Our Simple Process</h2>
            
            <div style="max-width: 900px; margin: 0 auto;">
                <!-- Step 1 -->
                <div style="display: flex; gap: 30px; margin-bottom: 50px; align-items: flex-start;">
                    <div style="flex-shrink: 0; width: 80px; height: 80px; background: linear-gradient(135deg, #0b6cf5 0%, #004080 100%); border-radius: 50%; display: flex; align-items: center; justify-content: center; color: white; font-size: 2rem; font-weight: bold;">
                        1
                    </div>
                    <div style="flex: 1;">
                        <h3 style="font-size: 1.8rem; color: #004080; margin-bottom: 15px;">Sign Up & Choose Your Track</h3>
                        <p style="color: #666; line-height: 1.8; font-size: 1.1rem; margin-bottom: 15px;">
                            Create your account and select your learning track:
                        </p>
                        <ul style="color: #666; line-height: 2; padding-left: 20px;">
                            <li><strong style="color: #004080;">Kids Classes:</strong> For ages 3-11, fun and interactive learning</li>
                            <li><strong style="color: #004080;">Adult General English:</strong> For adults of all levels</li>
                            <li><strong style="color: #004080;">English for Coding:</strong> Specialized for developers and tech professionals</li>
                        </ul>
                    </div>
                </div>

                <!-- Step 2 -->
                <div style="display: flex; gap: 30px; margin-bottom: 50px; align-items: flex-start;">
                    <div style="flex-shrink: 0; width: 80px; height: 80px; background: linear-gradient(135deg, #28a745 0%, #1e7e34 100%); border-radius: 50%; display: flex; align-items: center; justify-content: center; color: white; font-size: 2rem; font-weight: bold;">
                        2
                    </div>
                    <div style="flex: 1;">
                        <h3 style="font-size: 1.8rem; color: #004080; margin-bottom: 15px;">Complete Your Learning Needs Assessment</h3>
                        <p style="color: #666; line-height: 1.8; font-size: 1.1rem; margin-bottom: 15px;">
                            Tell us about your:
                        </p>
                        <ul style="color: #666; line-height: 2; padding-left: 20px;">
                            <li>Current English level</li>
                            <li>Learning goals and objectives</li>
                            <li>Preferred schedule and availability</li>
                            <li>Specific time slots you prefer for lessons</li>
                            <li>Special requirements or learning style preferences</li>
                        </ul>
                        <p style="color: #666; line-height: 1.8; font-size: 1.1rem; margin-top: 15px;">
                            This helps us match you with the perfect teacher for your needs!
                        </p>
                    </div>
                </div>

                <!-- Step 3 -->
                <div style="display: flex; gap: 30px; margin-bottom: 50px; align-items: flex-start;">
                    <div style="flex-shrink: 0; width: 80px; height: 80px; background: linear-gradient(135deg, #ffc107 0%, #e0a800 100%); border-radius: 50%; display: flex; align-items: center; justify-content: center; color: white; font-size: 2rem; font-weight: bold;">
                        3
                    </div>
                    <div style="flex: 1;">
                        <h3 style="font-size: 1.8rem; color: #004080; margin-bottom: 15px;">Get Matched with a Teacher</h3>
                        <p style="color: #666; line-height: 1.8; font-size: 1.1rem; margin-bottom: 15px;">
                            Our smart matching system connects you with an expert teacher based on:
                        </p>
                        <ul style="color: #666; line-height: 2; padding-left: 20px;">
                            <li>Your preferred time slots</li>
                            <li>Your learning track</li>
                            <li>Teacher availability and capacity</li>
                            <li>Teacher ratings and expertise</li>
                        </ul>
                        <p style="color: #666; line-height: 1.8; font-size: 1.1rem; margin-top: 15px;">
                            Most students are matched within 24-48 hours!
                        </p>
                    </div>
                </div>

                <!-- Step 4 -->
                <div style="display: flex; gap: 30px; margin-bottom: 50px; align-items: flex-start;">
                    <div style="flex-shrink: 0; width: 80px; height: 80px; background: linear-gradient(135deg, #dc3545 0%, #c82333 100%); border-radius: 50%; display: flex; align-items: center; justify-content: center; color: white; font-size: 2rem; font-weight: bold;">
                        4
                    </div>
                    <div style="flex: 1;">
                        <h3 style="font-size: 1.8rem; color: #004080; margin-bottom: 15px;">Book Your Classes</h3>
                        <p style="color: #666; line-height: 1.8; font-size: 1.1rem; margin-bottom: 15px;">
                            Browse your teacher's availability and book classes that fit your schedule:
                        </p>
                        <ul style="color: #666; line-height: 2; padding-left: 20px;">
                            <li>View available time slots in your timezone</li>
                            <li>Book one-on-one or group classes</li>
                            <li>Reschedule or cancel with notice</li>
                            <li>Set up recurring lessons for consistency</li>
                        </ul>
                    </div>
                </div>

                <!-- Step 5 -->
                <div style="display: flex; gap: 30px; margin-bottom: 50px; align-items: flex-start;">
                    <div style="flex-shrink: 0; width: 80px; height: 80px; background: linear-gradient(135deg, #6f42c1 0%, #5a32a3 100%); border-radius: 50%; display: flex; align-items: center; justify-content: center; color: white; font-size: 2rem; font-weight: bold;">
                        5
                    </div>
                    <div style="flex: 1;">
                        <h3 style="font-size: 1.8rem; color: #004080; margin-bottom: 15px;">Attend Classes on Zoom</h3>
                        <p style="color: #666; line-height: 1.8; font-size: 1.1rem; margin-bottom: 15px;">
                            All classes are conducted via Zoom for a seamless, interactive learning experience:
                        </p>
                        <ul style="color: #666; line-height: 2; padding-left: 20px;">
                            <li>High-quality video and audio</li>
                            <li>Interactive whiteboard and screen sharing</li>
                            <li>Real-time collaboration tools</li>
                            <li>Record lessons for review (optional)</li>
                            <li>Access from anywhere with internet</li>
                        </ul>
                    </div>
                </div>

                <!-- Step 6 -->
                <div style="display: flex; gap: 30px; align-items: flex-start;">
                    <div style="flex-shrink: 0; width: 80px; height: 80px; background: linear-gradient(135deg, #17a2b8 0%, #138496 100%); border-radius: 50%; display: flex; align-items: center; justify-content: center; color: white; font-size: 2rem; font-weight: bold;">
                        6
                    </div>
                    <div style="flex: 1;">
                        <h3 style="font-size: 1.8rem; color: #004080; margin-bottom: 15px;">Track Your Progress</h3>
                        <p style="color: #666; line-height: 1.8; font-size: 1.1rem; margin-bottom: 15px;">
                            Monitor your improvement with:
                        </p>
                        <ul style="color: #666; line-height: 2; padding-left: 20px;">
                            <li>Regular assignments and feedback</li>
                            <li>Progress reports and statistics</li>
                            <li>Learning goals tracking</li>
                            <li>Teacher notes and recommendations</li>
                            <li>Review and rate your teacher</li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Class Types -->
    <section style="padding: 60px 20px; background: linear-gradient(135deg, #f0f7ff 0%, #ffffff 100%);">
        <div class="container" style="max-width: 1200px; margin: 0 auto;">
            <h2 style="font-size: 2.5rem; color: #004080; text-align: center; margin-bottom: 50px;">Class Types</h2>
            
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 30px;">
                <!-- One-on-One Classes -->
                <div style="background: white; padding: 40px; border-radius: 12px; box-shadow: 0 4px 15px rgba(0,0,0,0.1); text-align: center;">
                    <div style="font-size: 3rem; color: #0b6cf5; margin-bottom: 20px;">
                        <i class="fas fa-user-friends"></i>
                    </div>
                    <h3 style="color: #004080; font-size: 1.8rem; margin-bottom: 15px;">One-on-One Classes</h3>
                    <p style="color: #666; line-height: 1.8; margin-bottom: 20px;">
                        Personalized, focused attention from your teacher. Perfect for:
                    </p>
                    <ul style="color: #666; line-height: 2; text-align: left; padding-left: 20px;">
                        <li>Individualized learning pace</li>
                        <li>Focused attention on your weaknesses</li>
                        <li>Customized lesson content</li>
                        <li>Flexible scheduling</li>
                        <li>Maximum speaking practice</li>
                    </ul>
                </div>

                <!-- Group Classes -->
                <div style="background: white; padding: 40px; border-radius: 12px; box-shadow: 0 4px 15px rgba(0,0,0,0.1); text-align: center;">
                    <div style="font-size: 3rem; color: #28a745; margin-bottom: 20px;">
                        <i class="fas fa-users"></i>
                    </div>
                    <h3 style="color: #004080; font-size: 1.8rem; margin-bottom: 15px;">Group Classes</h3>
                    <p style="color: #666; line-height: 1.8; margin-bottom: 20px;">
                        Learn with peers in an interactive group setting. Great for:
                    </p>
                    <ul style="color: #666; line-height: 2; text-align: left; padding-left: 20px;">
                        <li>Collaborative learning</li>
                        <li>Peer interaction and practice</li>
                        <li>Lower cost per class</li>
                        <li>Motivation from group dynamics</li>
                        <li>Diverse perspectives and discussions</li>
                    </ul>
                </div>

                <!-- Kids Classes -->
                <div style="background: white; padding: 40px; border-radius: 12px; box-shadow: 0 4px 15px rgba(0,0,0,0.1); text-align: center;">
                    <div style="font-size: 3rem; color: #ffc107; margin-bottom: 20px;">
                        <i class="fas fa-child"></i>
                    </div>
                    <h3 style="color: #004080; font-size: 1.8rem; margin-bottom: 15px;">Kids Classes</h3>
                    <p style="color: #666; line-height: 1.8; margin-bottom: 20px;">
                        Specially designed for young learners (ages 3-11). Features:
                    </p>
                    <ul style="color: #666; line-height: 2; text-align: left; padding-left: 20px;">
                        <li>Age-appropriate materials</li>
                        <li>Fun, interactive activities</li>
                        <li>Games and songs</li>
                        <li>Visual learning aids</li>
                        <li>Parent progress updates</li>
                    </ul>
                </div>
            </div>
        </div>
    </section>

    <!-- Teacher Matching Process -->
    <section style="padding: 60px 20px; background: white;">
        <div class="container" style="max-width: 1000px; margin: 0 auto;">
            <h2 style="font-size: 2.5rem; color: #004080; text-align: center; margin-bottom: 30px;">How Teacher Matching Works</h2>
            <p style="color: #666; line-height: 1.8; font-size: 1.1rem; text-align: center; margin-bottom: 40px;">
                Our intelligent matching system considers multiple factors to find your perfect teacher:
            </p>
            
            <div style="background: #f8f9fa; padding: 40px; border-radius: 12px; border-left: 5px solid #0b6cf5;">
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px;">
                    <div style="text-align: center;">
                        <div style="font-size: 2.5rem; color: #0b6cf5; margin-bottom: 10px;">
                            <i class="fas fa-clock"></i>
                        </div>
                        <h4 style="color: #004080; margin-bottom: 8px;">Preferred Times</h4>
                        <p style="color: #666; font-size: 0.9rem;">Matches teachers with availability during your preferred time slots</p>
                    </div>
                    <div style="text-align: center;">
                        <div style="font-size: 2.5rem; color: #28a745; margin-bottom: 10px;">
                            <i class="fas fa-graduation-cap"></i>
                        </div>
                        <h4 style="color: #004080; margin-bottom: 8px;">Learning Track</h4>
                        <p style="color: #666; font-size: 0.9rem;">Connects you with teachers specializing in your chosen track</p>
                    </div>
                    <div style="text-align: center;">
                        <div style="font-size: 2.5rem; color: #ffc107; margin-bottom: 10px;">
                            <i class="fas fa-star"></i>
                        </div>
                        <h4 style="color: #004080; margin-bottom: 8px;">Ratings</h4>
                        <p style="color: #666; font-size: 0.9rem;">Prioritizes highly-rated teachers with proven track records</p>
                    </div>
                    <div style="text-align: center;">
                        <div style="font-size: 2.5rem; color: #dc3545; margin-bottom: 10px;">
                            <i class="fas fa-users"></i>
                        </div>
                        <h4 style="color: #004080; margin-bottom: 8px;">Availability</h4>
                        <p style="color: #666; font-size: 0.9rem;">Ensures teachers have capacity for new students</p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- CTA Section -->
    <section style="padding: 60px 20px; background: linear-gradient(135deg, #0b6cf5 0%, #004080 100%); color: white; text-align: center;">
        <div class="container" style="max-width: 800px; margin: 0 auto;">
            <h2 style="font-size: 2.5rem; margin-bottom: 20px;">Ready to Start Learning?</h2>
            <p style="font-size: 1.2rem; margin-bottom: 40px; line-height: 1.8;">
                Join thousands of students already improving their English with Staten Academy!
            </p>
            <div style="display: flex; justify-content: center; gap: 20px; flex-wrap: wrap;">
                <?php if (!isset($_SESSION['user_id'])): ?>
                    <a href="register.php" class="btn-primary" style="padding: 15px 40px; font-size: 1.1rem; text-decoration: none; display: inline-block; background: white; color: #0b6cf5; border-radius: 8px;">
                        <i class="fas fa-user-plus"></i> Sign Up Now
                    </a>
                <?php else: ?>
                    <a href="student-dashboard.php" class="btn-primary" style="padding: 15px 40px; font-size: 1.1rem; text-decoration: none; display: inline-block; background: white; color: #0b6cf5; border-radius: 8px;">
                        <i class="fas fa-tachometer-alt"></i> Go to Dashboard
                    </a>
                <?php endif; ?>
                <a href="payment.php" class="btn-outline" style="padding: 15px 40px; font-size: 1.1rem; text-decoration: none; display: inline-block; border: 2px solid white; color: white; border-radius: 8px;">
                    <i class="fas fa-dollar-sign"></i> View Plans
                </a>
            </div>
        </div>
    </section>
</main>

<?php include __DIR__ . '/app/Views/components/footer.php'; ?>


