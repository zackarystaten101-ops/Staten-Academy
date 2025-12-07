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

// Check if database connection is successful
if (!isset($conn) || $conn->connect_error) {
    die("Database connection failed. Please check your database configuration in env.php");
}

// Load dashboard functions
require_once __DIR__ . '/app/Views/components/dashboard-functions.php';

// Ensure getAssetPath function is available
if (!function_exists('getAssetPath')) {
    function getAssetPath($asset) {
        // Remove leading slash if present
        $asset = ltrim($asset, '/');
        
        // Build base asset path
        if (strpos($asset, 'assets/') === 0) {
            $assetPath = $asset;
        } else {
            $assetPath = 'assets/' . $asset;
        }
        
        // Get base path from SCRIPT_NAME - more reliable for subdirectories
        $scriptName = $_SERVER['SCRIPT_NAME'] ?? '';
        $basePath = dirname($scriptName);
        $basePath = str_replace('\\', '/', $basePath);
        
        // Handle root case
        if ($basePath === '.' || $basePath === '/' || empty($basePath)) {
            $basePath = '';
        } else {
            // Ensure leading slash and remove trailing
            $basePath = '/' . trim($basePath, '/');
        }
        
        // Check if file exists in public/ directory (local development)
        // Use absolute path from project root
        $publicAssetPath = __DIR__ . DIRECTORY_SEPARATOR . 'public' . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $assetPath);
        
        // If file exists in public/ directory, use that path
        if (file_exists($publicAssetPath)) {
            // For local dev with public/ directory structure
            return $basePath . '/public/' . $assetPath;
        }
        
        // For cPanel flat structure (files directly in public_html/assets/)
        return $basePath . '/' . $assetPath;
    }
}

// Get filter parameters
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$specialty_filter = isset($_GET['specialty']) ? trim($_GET['specialty']) : '';
$min_rating = isset($_GET['min_rating']) ? (float)$_GET['min_rating'] : 0;
$sort = isset($_GET['sort']) ? $_GET['sort'] : 'default';

// Build query with filters
$sql = "SELECT u.id, u.name, u.bio, u.profile_pic, u.specialty, u.hourly_rate,
        COALESCE((SELECT AVG(rating) FROM reviews WHERE teacher_id = u.id), 0) as avg_rating,
        COALESCE((SELECT COUNT(*) FROM reviews WHERE teacher_id = u.id), 0) as review_count
        FROM users u 
        WHERE u.role='teacher' AND u.application_status='approved'";

$params = [];
$types = "";

if ($search) {
    $sql .= " AND (u.name LIKE ? OR u.bio LIKE ? OR u.specialty LIKE ?)";
    $search_param = "%$search%";
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
    $types .= "sss";
}

if ($specialty_filter) {
    $sql .= " AND u.specialty LIKE ?";
    $params[] = "%$specialty_filter%";
    $types .= "s";
}

if ($min_rating > 0) {
    $sql .= " HAVING avg_rating >= ?";
    $params[] = $min_rating;
    $types .= "d";
}

// Sort
switch ($sort) {
    case 'rating':
        $sql .= " ORDER BY avg_rating DESC";
        break;
    case 'reviews':
        $sql .= " ORDER BY review_count DESC";
        break;
    case 'price_low':
        $sql .= " ORDER BY COALESCE(u.hourly_rate, 999) ASC";
        break;
    case 'price_high':
        $sql .= " ORDER BY COALESCE(u.hourly_rate, 0) DESC";
        break;
    default:
        $sql .= " ORDER BY u.id DESC";
}

$stmt = $conn->prepare($sql);
if ($stmt) {
    if (count($params) > 0) {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $teachers_result = $stmt->get_result();
    if (!$teachers_result) {
        // If execute failed, use fallback
        $teachers_result = $conn->query("SELECT u.id, u.name, u.bio, u.profile_pic, u.specialty, u.hourly_rate,
            COALESCE((SELECT AVG(rating) FROM reviews WHERE teacher_id = u.id), 0) as avg_rating,
            COALESCE((SELECT COUNT(*) FROM reviews WHERE teacher_id = u.id), 0) as review_count
            FROM users u 
            WHERE u.role='teacher' AND u.application_status='approved'
            ORDER BY u.id DESC");
    }
} else {
    // Fallback to simple query with all necessary columns
    $teachers_result = $conn->query("SELECT u.id, u.name, u.bio, u.profile_pic, u.specialty, u.hourly_rate,
        COALESCE((SELECT AVG(rating) FROM reviews WHERE teacher_id = u.id), 0) as avg_rating,
        COALESCE((SELECT COUNT(*) FROM reviews WHERE teacher_id = u.id), 0) as review_count
        FROM users u 
        WHERE u.role='teacher' AND u.application_status='approved'
        ORDER BY u.id DESC");
}

// Get unique specialties for filter dropdown
$specialties = [];
$spec_result = $conn->query("SELECT DISTINCT specialty FROM users WHERE role='teacher' AND application_status='approved' AND specialty IS NOT NULL AND specialty != ''");
if ($spec_result) {
    while ($row = $spec_result->fetch_assoc()) {
        if (!empty($row['specialty'])) {
            $specialties[] = $row['specialty'];
        }
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
    <meta name="description" content="Welcome to Staten Academy - Learn English with professional teachers and flexible plans.">
    <title>Staten Academy - Learn English</title>
    <link rel="stylesheet" href="<?php echo getAssetPath('styles.css'); ?>">
    <link rel="stylesheet" href="<?php echo getAssetPath('css/mobile.css'); ?>">
    <!-- MODERN SHADOWS - To disable, comment out the line below -->
    <link rel="stylesheet" href="<?php echo getAssetPath('css/modern-shadows.css'); ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .filter-bar {
            background: white;
            padding: 20px;
            border-radius: 12px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.08);
            margin-bottom: 30px;
            display: flex;
            flex-wrap: wrap;
            gap: 15px;
            align-items: center;
        }
        .filter-bar input[type="text"],
        .filter-bar select {
            padding: 12px 15px;
            border: 2px solid #e1e5e9;
            border-radius: 8px;
            font-size: 0.95rem;
            transition: border-color 0.2s;
        }
        .filter-bar input[type="text"]:focus,
        .filter-bar select:focus {
            outline: none;
            border-color: #0b6cf5;
        }
        .filter-bar input[type="text"] {
            flex: 1;
            min-width: 200px;
        }
        .filter-bar select {
            min-width: 150px;
        }
        .filter-bar button {
            background: linear-gradient(135deg, #0b6cf5 0%, #0056b3 100%);
            color: white;
            border: none;
            padding: 12px 25px;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 600;
            transition: transform 0.2s, box-shadow 0.2s;
        }
        .filter-bar button:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(11, 108, 245, 0.3);
        }
        .filter-bar .clear-btn {
            background: #f0f2f5;
            color: #666;
        }
        .filter-bar .clear-btn:hover {
            background: #e0e2e5;
            box-shadow: none;
        }
        
        .teacher-card {
            position: relative;
        }
        .teacher-rating {
            display: flex;
            align-items: center;
            gap: 5px;
            margin-top: 8px;
            font-size: 0.9rem;
        }
        .teacher-rating i {
            color: #ffc107;
        }
        .teacher-rating span {
            color: #666;
        }
        .teacher-specialty {
            display: inline-block;
            background: #e1f0ff;
            color: #0b6cf5;
            padding: 4px 10px;
            border-radius: 15px;
            font-size: 0.8rem;
            margin-top: 8px;
        }
        .teacher-price {
            position: absolute;
            top: 15px;
            right: 15px;
            background: linear-gradient(135deg, #0b6cf5 0%, #0056b3 100%);
            color: white;
            padding: 5px 12px;
            border-radius: 20px;
            font-weight: bold;
            font-size: 0.85rem;
        }
        .no-results {
            grid-column: 1 / -1;
            text-align: center;
            padding: 60px 20px;
            background: #f9fbff;
            border-radius: 12px;
            border: 2px dashed #0b6cf5;
        }
        .no-results i {
            font-size: 3rem;
            color: #0b6cf5;
            opacity: 0.5;
            margin-bottom: 15px;
        }

        @media (max-width: 768px) {
            .filter-bar {
                flex-direction: column;
            }
            .filter-bar input[type="text"],
            .filter-bar select {
                width: 100%;
            }
        }
        
        /* Plans Section Styles */
        .plans-section {
            background: #f9fbff;
            padding: 60px 20px;
            margin-top: 40px;
        }
        .plans-container {
            max-width: 1200px;
            margin: 0 auto;
        }
        .plans-section h2 {
            color: #004080;
            font-size: 2.5rem;
            margin-bottom: 15px;
            text-align: center;
        }
        .plans-intro {
            text-align: center;
            color: #666;
            font-size: 1.1rem;
            margin-bottom: 40px;
        }
        .single-class-box {
            background: white;
            border-radius: 12px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
            padding: 30px;
            text-align: center;
            margin-bottom: 40px;
            border: 2px solid #0b6cf5;
            max-width: 600px;
            margin-left: auto;
            margin-right: auto;
        }
        .single-class-box h3 {
            color: #004080;
            margin-bottom: 10px;
            font-size: 1.8rem;
        }
        .single-class-box p {
            color: #666;
            margin-bottom: 15px;
        }
        .single-class-price {
            font-size: 2.5rem;
            color: #0b6cf5;
            font-weight: bold;
            margin: 20px 0;
        }
        .single-class-price span {
            font-size: 1rem;
            color: #666;
            font-weight: normal;
        }
        .btn-buy {
            display: inline-block;
            background: #0b6cf5;
            color: white;
            padding: 15px 40px;
            border-radius: 50px;
            text-decoration: none;
            font-weight: bold;
            font-size: 1.1rem;
            border: none;
            cursor: pointer;
            transition: transform 0.2s, background 0.2s;
            margin-top: 10px;
        }
        .btn-buy:hover {
            transform: scale(1.05);
            background: #0056b3;
        }
        .plans-subtitle {
            text-align: center;
            margin-bottom: 30px;
            color: #004080;
            font-size: 1.8rem;
        }
        .plans-disclaimer {
            text-align: center;
            margin-top: 30px;
            color: #666;
            font-size: 0.9rem;
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
            
            <a class="nav-btn" href="#teachers">
                <svg class="nav-icon" viewBox="0 0 24 24"><path fill="#06385a" d="M12 12c2.7 0 5-2.3 5-5s-2.3-5-5-5-5 2.3-5 5 2.3 5 5 5zm0 2c-3.3 0-10 1.7-10 5v3h20v-3c0-3.3-6.7-5-10-5z"/></svg>
                <span class="nav-label">Teachers</span>
            </a>
            
            <a class="nav-btn" href="#plans">
                <svg class="nav-icon" viewBox="0 0 24 24"><path fill="#06385a" d="M7.5 21H2V9h5.5v12zm7.25-18h-5.5v18h5.5V3zM22 11h-5.5v10H22V11z"/></svg>
                <span class="nav-label">Plans</span>
            </a>
            
            <a class="nav-btn" href="#about">
                <svg class="nav-icon" viewBox="0 0 24 24"><path fill="#06385a" d="M11 17h2v-6h-2v6zm0-8h2V7h-2v2z"/></svg>
                <span class="nav-label">About Us</span>
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
    <section id="teachers" class="teachers">
        <h2>Meet Our Teachers</h2>
        
        <!-- Filter Bar -->
        <form class="filter-bar" method="GET" action="index.php#teachers">
            <input type="text" name="search" placeholder="Search teachers..." value="<?php echo htmlspecialchars($search); ?>">
            
            <?php if (count($specialties) > 0): ?>
            <select name="specialty">
                <option value="">All Subjects</option>
                <?php foreach ($specialties as $spec): ?>
                <option value="<?php echo htmlspecialchars($spec); ?>" <?php echo $specialty_filter === $spec ? 'selected' : ''; ?>>
                    <?php echo htmlspecialchars($spec); ?>
                </option>
                <?php endforeach; ?>
            </select>
            <?php endif; ?>
            
            <select name="min_rating">
                <option value="0">Any Rating</option>
                <option value="4" <?php echo $min_rating == 4 ? 'selected' : ''; ?>>4+ Stars</option>
                <option value="4.5" <?php echo $min_rating == 4.5 ? 'selected' : ''; ?>>4.5+ Stars</option>
            </select>
            
            <select name="sort">
                <option value="default">Sort By</option>
                <option value="rating" <?php echo $sort === 'rating' ? 'selected' : ''; ?>>Highest Rated</option>
                <option value="reviews" <?php echo $sort === 'reviews' ? 'selected' : ''; ?>>Most Reviews</option>
                <option value="price_low" <?php echo $sort === 'price_low' ? 'selected' : ''; ?>>Price: Low to High</option>
                <option value="price_high" <?php echo $sort === 'price_high' ? 'selected' : ''; ?>>Price: High to Low</option>
            </select>
            
            <button type="submit"><i class="fas fa-search"></i> Search</button>
            <?php if ($search || $specialty_filter || $min_rating || $sort !== 'default'): ?>
            <a href="index.php#teachers" class="filter-bar button clear-btn" style="text-decoration: none; display: inline-flex; align-items: center; gap: 5px;">
                <i class="fas fa-times"></i> Clear
            </a>
            <?php endif; ?>
        </form>
        
        <div class="teachers-grid">
            <?php 
            $has_teachers = false;
            while($teacher = $teachers_result->fetch_assoc()): 
                $has_teachers = true;
            ?>
            <article class="teacher-card">
                <?php if (!empty($teacher['hourly_rate']) && $teacher['hourly_rate'] > 0): ?>
                <span class="teacher-price">$<?php echo number_format($teacher['hourly_rate'], 0); ?>/hr</span>
                <?php endif; ?>
                <a href="profile.php?id=<?php echo $teacher['id']; ?>" style="text-decoration: none; color: inherit;">
                    <img src="<?php echo htmlspecialchars(!empty($teacher['profile_pic']) ? $teacher['profile_pic'] : getAssetPath('images/placeholder-teacher.svg')); ?>" alt="<?php echo htmlspecialchars($teacher['name']); ?>" onerror="this.onerror=null;this.src='<?php echo getAssetPath('images/placeholder-teacher.svg'); ?>'">
                    <div class="teacher-info">
                        <h3><?php echo htmlspecialchars($teacher['name']); ?></h3>
                        <?php if (!empty($teacher['specialty'])): ?>
                        <span class="teacher-specialty"><?php echo htmlspecialchars($teacher['specialty']); ?></span>
                        <?php endif; ?>
                        <div class="teacher-rating">
                            <?php 
                            $rating = round($teacher['avg_rating'], 1);
                            for ($i = 1; $i <= 5; $i++) {
                                if ($i <= $rating) {
                                    echo '<i class="fas fa-star"></i>';
                                } elseif ($i - 0.5 <= $rating) {
                                    echo '<i class="fas fa-star-half-alt"></i>';
                                } else {
                                    echo '<i class="far fa-star"></i>';
                                }
                            }
                            ?>
                            <span><?php echo $rating; ?> (<?php echo $teacher['review_count']; ?>)</span>
                        </div>
                        <?php if (!empty($teacher['bio'])): ?>
                        <p><?php echo htmlspecialchars(substr($teacher['bio'], 0, 80)); ?><?php echo strlen($teacher['bio']) > 80 ? '...' : ''; ?></p>
                        <?php else: ?>
                        <p style="color: #999; font-style: italic;">No bio available</p>
                        <?php endif; ?>
                    </div>
                </a>
            </article>
            <?php endwhile; ?>
            
            <?php if (!$has_teachers): ?>
            <div class="no-results">
                <?php if ($search || $specialty_filter || $min_rating): ?>
                    <i class="fas fa-search"></i>
                    <h3 style="color: #004080; margin-bottom: 10px;">No Teachers Found</h3>
                    <p style="color: #666; margin-bottom: 15px;">Try adjusting your search filters or browse all teachers.</p>
                    <a href="index.php#teachers" style="color: #0b6cf5; font-weight: bold;">View All Teachers</a>
                <?php else: ?>
                    <i class="fas fa-chalkboard-teacher"></i>
                    <h3 style="color: #0b6cf5; margin-bottom: 10px;">Our Teachers Coming Soon</h3>
                    <p style="color: #666; margin-bottom: 15px;">We're adding qualified teachers to our platform. Check back soon!</p>
                    <p style="color: #999; font-size: 0.9rem;">Interested in teaching? <a href="apply-teacher.php" style="color: #0b6cf5; font-weight: bold;">Apply here</a>.</p>
                <?php endif; ?>
            </div>
            <?php endif; ?>
        </div>
    </section>

    <section id="plans" class="plans-section">
        <div class="plans-container">
            <h2>Our Plans & Pricing</h2>
            <p class="plans-intro">Choose the perfect plan for your English learning journey</p>
            
            <!-- Trial / Individual Class -->
            <div class="single-class-box">
                <h3>Trial Lesson / Individual Class</h3>
                <p>Perfect for trying out a teacher or flexible scheduling.</p>
                <div class="single-class-price">$30 <span>/ hour</span></div>
                <a href="payment.php" class="btn-buy">Book Now</a>
            </div>

            <!-- Subscription Plans -->
            <h3 class="plans-subtitle">Monthly Subscriptions</h3>
            
            <div class="plans-grid">
                
                <a href="payment.php" class="plan">
                    <div class="plan-body">
                        <h3>Economy Plan</h3>
                        <p class="desc">1 class per week with a certified teacher.</p>
                        <p class="desc" style="color: #d9534f; font-weight: 600; margin-top: 8px; font-size: 0.9rem;"><i class="fas fa-info-circle"></i> Teacher will be assigned</p>
                        <p class="price">$85 / month</p>
                    </div>
                </a>

                <a href="payment.php" class="plan">
                    <div class="plan-body">
                        <h3>Basic Plan</h3>
                        <p class="desc">2 classes per week. Choose your own tutor.</p>
                        <p class="price">$240 / month</p>
                    </div>
                </a>

                <a href="payment.php" class="plan">
                    <div class="plan-body">
                        <h3>Standard Plan</h3>
                        <p class="desc">4 classes per week, extra learning resources.</p>
                        <p class="price">$400 / month</p>
                    </div>
                </a>

                <a href="payment.php" class="plan">
                    <div class="plan-body">
                        <h3>Premium Plan</h3>
                        <p class="desc">Unlimited classes, exclusive materials.</p>
                        <p class="price">$850 / month</p>
                    </div>
                </a>

            </div>
            
            <p class="plans-disclaimer">* Plans renew automatically. Cancel anytime.</p>
            
            <!-- Custom Plan Option -->
            <div style="text-align: center; margin-top: 40px; padding: 30px; background: white; border-radius: 12px; box-shadow: 0 4px 15px rgba(0,0,0,0.08);">
                <h3 style="color: #004080; margin-bottom: 15px; font-size: 1.8rem;">Need a Custom Plan?</h3>
                <p style="color: #666; margin-bottom: 20px; max-width: 600px; margin-left: auto; margin-right: auto;">
                    Build your own plan with the exact number of hours and courses you need. Choose your teacher, add extra courses, and include group support classes.
                </p>
                <a href="custom-plan.php" style="display: inline-block; background: linear-gradient(135deg, #0b6cf5 0%, #0056b3 100%); color: white; padding: 15px 40px; border-radius: 50px; text-decoration: none; font-weight: bold; font-size: 1.1rem; transition: transform 0.2s, box-shadow 0.2s;">
                    <i class="fas fa-cog"></i> Create Custom Plan
                </a>
            </div>
        </div>
    </section>

    <section id="about" class="about-us" style="background: white; padding: 60px 20px; margin-top: 40px;">
        <div class="container" style="text-align: center; max-width: 900px; margin: 0 auto;">
            <h2 style="color: #004080; font-size: 2.5rem; margin-bottom: 20px;">About Staten Academy</h2>
            <p style="font-size: 1.1rem; line-height: 1.8; color: #555; margin-bottom: 30px;">
                Staten Academy is a family-owned English school dedicated to providing personalized, high-quality education to students of all ages. Founded on the belief that language learning should be engaging and accessible, we have grown from a small tutoring service into a full academy with a team of passionate educators.
            </p>
            
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 30px; text-align: left; margin-top: 40px;">
                <div style="padding: 20px; background: #f9fbff; border-radius: 10px; box-shadow: 0 4px 10px rgba(0,0,0,0.05);">
                    <h3 style="color: #0b6cf5; margin-bottom: 10px;"><i class="fas fa-book-open"></i> Our Method</h3>
                    <p style="color: #666;">We believe in "Natural Acquisition" through immersion and interaction. Our classes focus on speaking and listening first, mimicking how we learn our native languages.</p>
                </div>
                <div style="padding: 20px; background: #f9fbff; border-radius: 10px; box-shadow: 0 4px 10px rgba(0,0,0,0.05);">
                    <h3 style="color: #0b6cf5; margin-bottom: 10px;"><i class="fas fa-heart"></i> Family Values</h3>
                    <p style="color: #666;">As a family-run business, we treat every student like a member of our community. We offer a supportive, non-judgmental environment for learning.</p>
                </div>
                <div style="padding: 20px; background: #f9fbff; border-radius: 10px; box-shadow: 0 4px 10px rgba(0,0,0,0.05);">
                    <h3 style="color: #0b6cf5; margin-bottom: 10px;"><i class="fas fa-clock"></i> Flexibility</h3>
                    <p style="color: #666;">We understand that every learner is unique. That's why we offer flexible scheduling and customized learning plans to fit your goals.</p>
                </div>
            </div>
        </div>
    </section>
    </main>

    <footer>
        <p>Contact us: info@statenacademy.com | Phone: +1 234 567 890</p>
        <p>&copy; 2023 Staten Academy. All rights reserved.</p>
    </footer>
    <script src="<?php echo getAssetPath('js/menu.js'); ?>" defer></script>
</body>
</html>
