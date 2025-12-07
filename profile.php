<?php
session_start();
require_once 'db.php';
require_once __DIR__ . '/app/Views/components/dashboard-functions.php';

if (!isset($_GET['id'])) {
    header("Location: index.php");
    exit();
}

$teacher_id = intval($_GET['id']);
$stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
$stmt->bind_param("i", $teacher_id);
$stmt->execute();
$teacher = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$teacher || ($teacher['role'] !== 'teacher' && $teacher['role'] !== 'admin')) {
    echo "Teacher not found.";
    exit();
}

// Get user info
$user_role = isset($_SESSION['user_role']) ? $_SESSION['user_role'] : 'guest';
$user_id = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : null;

// Get rating data
$rating_data = getTeacherRating($conn, $teacher_id);

// Get reviews
$reviews = [];
$stmt = $conn->prepare("
    SELECT r.*, u.name as student_name, u.profile_pic as student_pic
    FROM reviews r
    JOIN users u ON r.student_id = u.id
    WHERE r.teacher_id = ?
    ORDER BY r.created_at DESC
    LIMIT 10
");
$stmt->bind_param("i", $teacher_id);
$stmt->execute();
$reviews_result = $stmt->get_result();
if ($reviews_result) {
    while ($row = $reviews_result->fetch_assoc()) {
        $reviews[] = $row;
    }
}
$stmt->close();

// Check if favorite
$is_favorite = false;
if ($user_id && $user_role === 'student') {
    $is_favorite = isTeacherFavorite($conn, $user_id, $teacher_id);
}

// Check if can review
$can_review = false;
$has_reviewed = false;
if ($user_id && $user_role === 'student') {
    // Check if has booking
    $stmt = $conn->prepare("SELECT id FROM bookings WHERE student_id = ? AND teacher_id = ? LIMIT 1");
    $stmt->bind_param("ii", $user_id, $teacher_id);
    $stmt->execute();
    $has_booking = $stmt->get_result()->num_rows > 0;
    $stmt->close();
    
    // Check if already reviewed
    $stmt = $conn->prepare("SELECT id FROM reviews WHERE student_id = ? AND teacher_id = ? LIMIT 1");
    $stmt->bind_param("ii", $user_id, $teacher_id);
    $stmt->execute();
    $has_reviewed = $stmt->get_result()->num_rows > 0;
    $stmt->close();
    
    $can_review = $has_booking && !$has_reviewed;
}

// Handle review submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_review']) && $can_review) {
    $rating = (int)$_POST['rating'];
    $review_text = trim($_POST['review_text']);
    
    if ($rating >= 1 && $rating <= 5) {
        $stmt = $conn->prepare("INSERT INTO reviews (teacher_id, student_id, rating, review_text) VALUES (?, ?, ?, ?)");
        $stmt->bind_param("iiis", $teacher_id, $user_id, $rating, $review_text);
        $stmt->execute();
        $stmt->close();
        
        // Notify teacher
        createNotification($conn, $teacher_id, 'review', 'New Review', 
            $_SESSION['user_name'] . " left you a $rating-star review!", 'teacher-dashboard.php#reviews');
        
        header("Location: profile.php?id=$teacher_id&reviewed=1");
        exit();
    }
}

// Handle favorite toggle
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['toggle_favorite']) && $user_id) {
    if ($is_favorite) {
        $stmt = $conn->prepare("DELETE FROM favorite_teachers WHERE student_id = ? AND teacher_id = ?");
    } else {
        $stmt = $conn->prepare("INSERT IGNORE INTO favorite_teachers (student_id, teacher_id) VALUES (?, ?)");
    }
    if ($stmt) {
        $stmt->bind_param("ii", $user_id, $teacher_id);
        $stmt->execute();
        $stmt->close();
    }
    header("Location: profile.php?id=$teacher_id");
    exit();
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=5.0, user-scalable=yes">
    <meta name="theme-color" content="#004080">
    <meta name="mobile-web-app-capable" content="yes">
    <title><?php echo htmlspecialchars($teacher['name']); ?> - Staten Academy</title>
    <link rel="stylesheet" href="<?php echo getAssetPath('styles.css'); ?>">
    <link rel="stylesheet" href="<?php echo getAssetPath('css/mobile.css'); ?>">
    <!-- MODERN SHADOWS - To disable, comment out the line below -->
    <link rel="stylesheet" href="<?php echo getAssetPath('css/modern-shadows.css'); ?>">
    <link rel="stylesheet" href="<?php echo getAssetPath('css/dashboard.css'); ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .teacher-profile { max-width: 900px; margin: 40px auto; padding: 0 20px; }
        .profile-header { text-align: center; margin-bottom: 40px; position: relative; }
        .profile-header h1 { color: #004080; font-size: 2.5rem; margin-bottom: 10px; }
        .profile-header .tagline { color: #666; font-size: 1.2rem; }
        
        .profile-pic-large {
            width: 180px;
            height: 180px;
            border-radius: 50%;
            object-fit: cover;
            border: 5px solid #fff;
            box-shadow: 0 4px 20px rgba(0,0,0,0.15);
            margin: 0 auto 20px;
            display: block;
        }

        .content-section { 
            background: #fff; 
            padding: 30px; 
            border-radius: 12px; 
            box-shadow: 0 4px 15px rgba(0,0,0,0.05); 
            margin-bottom: 30px; 
        }
        .content-section h2 { 
            color: #004080; 
            margin-bottom: 20px; 
            border-bottom: 2px solid var(--primary-light); 
            padding-bottom: 10px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .content-section h2 i { color: var(--primary); }
        
        .pricing-highlight { 
            background: linear-gradient(135deg, #fff5e6 0%, #ffe8cc 100%); 
            border: 3px solid #ff9800; 
            padding: 25px; 
            border-radius: 12px; 
            text-align: center; 
            margin-top: 20px;
            box-shadow: 0 4px 15px rgba(255, 152, 0, 0.2);
            position: relative;
        }
        .pricing-highlight::before {
            content: "Popular";
            position: absolute;
            top: -12px;
            left: 50%;
            transform: translateX(-50%);
            background: #ff9800;
            color: white;
            padding: 4px 15px;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: bold;
        }
        .price-large { font-size: 2.8rem; color: #ff9800; font-weight: bold; margin: 10px 0; }
        
        .plans-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 20px;
            margin-top: 30px;
        }
        .plan {
            background: linear-gradient(180deg, #ffffff, #f8fbff);
            border: 1px solid rgba(16,24,40,0.04);
            border-radius: 12px;
            padding: 20px;
            box-shadow: 0 6px 18px rgba(16,24,40,0.06);
            transition: transform 0.2s, box-shadow 0.2s;
            cursor: pointer;
        }
        .plan:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 25px rgba(16,24,40,0.12);
        }
        .plan-body h3 {
            color: #004080;
            margin: 0 0 10px 0;
            font-size: 1.2rem;
        }
        .plan-body .desc {
            color: #666;
            font-size: 0.9rem;
            margin: 0 0 15px 0;
            line-height: 1.4;
        }
        .plan-body .price {
            color: #0b6cf5;
            font-weight: 700;
            font-size: 1.3rem;
            background: rgba(11,108,245,0.06);
            display: inline-block;
            padding: 8px 15px;
            border-radius: 8px;
        }
        .plan-button {
            width: 100%;
            background: none;
            border: none;
            text-align: left;
            padding: 0;
            cursor: pointer;
        }
        
        .btn-book {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            background: linear-gradient(135deg, #0b6cf5 0%, #0056b3 100%);
            color: white;
            padding: 14px 35px;
            border-radius: 50px;
            text-decoration: none;
            font-weight: bold;
            font-size: 1rem;
            transition: all 0.3s;
            border: none;
            cursor: pointer;
        }
        .btn-book:hover { 
            transform: translateY(-3px); 
            box-shadow: 0 8px 25px rgba(11, 108, 245, 0.4); 
        }
        
        .btn-message {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            background: linear-gradient(135deg, #28a745 0%, #1e7e34 100%);
            color: white;
            padding: 14px 35px;
            border-radius: 50px;
            text-decoration: none;
            font-weight: bold;
            font-size: 1rem;
            transition: all 0.3s;
        }
        .btn-message:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 25px rgba(40, 167, 69, 0.4);
        }
        
        .favorite-btn {
            position: absolute;
            top: 0;
            right: 0;
            background: white;
            border: 2px solid #ddd;
            width: 50px;
            height: 50px;
            border-radius: 50%;
            cursor: pointer;
            font-size: 1.3rem;
            color: #ddd;
            transition: all 0.3s;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .favorite-btn:hover, .favorite-btn.active {
            border-color: #ff6b6b;
            color: #ff6b6b;
        }
        .favorite-btn.active {
            background: #fff0f0;
        }

        .rating-display {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 15px;
            margin-top: 15px;
        }
        .rating-number {
            font-size: 1.5rem;
            font-weight: bold;
            color: #333;
        }
        .review-count {
            color: #666;
            font-size: 0.95rem;
        }
        
        .specialty-tag {
            display: inline-block;
            background: var(--primary-light);
            color: var(--primary);
            padding: 6px 15px;
            border-radius: 20px;
            font-size: 0.9rem;
            font-weight: 500;
            margin-top: 10px;
        }

        .video-container {
            position: relative;
            padding-bottom: 56.25%;
            height: 0;
            overflow: hidden;
            border-radius: 12px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.15);
            margin-bottom: 40px;
            background: #000;
        }
        .video-container video {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
        }
    </style>
</head>
<body>
    <header class="site-header" role="banner">
        <div class="header-left"><a href="index.php"><img src="<?php echo getAssetPath('logo.png'); ?>" alt="Staten Academy logo" class="site-logo"></a></div>
        <div class="header-center">
            <div class="branding"><h1 class="site-title">Staten Academy</h1></div>
        </div>
        <?php include 'header-user.php'; ?>
        <button id="menu-toggle" class="menu-toggle" aria-controls="mobile-menu" aria-expanded="false" aria-label="Open navigation menu">
            <span class="hamburger" aria-hidden="true"></span>
        </button>
        <div id="mobile-menu" class="mobile-menu" role="menu" aria-hidden="true">
            <button class="close-btn" id="mobile-close" aria-label="Close menu">✕</button>
            <a class="nav-btn" href="index.php">Home</a>
            <a class="nav-btn" href="index.php#teachers">Teachers</a>
            <?php if (isset($_SESSION['user_id'])): ?>
                <?php if ($user_role === 'teacher' || $user_role === 'admin'): ?>
                    <a class="nav-btn" href="teacher-dashboard.php" style="background-color: #28a745; color: white;">Dashboard</a>
                <?php elseif ($user_role === 'student'): ?>
                    <a class="nav-btn" href="student-dashboard.php" style="background-color: #28a745; color: white;">My Dashboard</a>
                <?php endif; ?>
                <a class="nav-btn" href="logout.php" style="background-color: #dc3545; color: white;">Logout</a>
            <?php else: ?>
                <a class="nav-btn login-btn" href="login.php" style="background-color: #0b6cf5; color: white;">Login / Sign Up</a>
            <?php endif; ?>
        </div>
    </header>
    <div id="mobile-backdrop" class="mobile-backdrop" aria-hidden="true"></div>

    <main class="teacher-profile">
        <?php if (isset($_GET['reviewed'])): ?>
        <div class="alert-success" style="margin-bottom: 20px;">
            <i class="fas fa-check-circle"></i> Thank you for your review!
        </div>
        <?php endif; ?>

        <div class="profile-header">
            <?php if ($user_role === 'student' && $user_id): ?>
            <form method="POST" style="display: inline;">
                <button type="submit" name="toggle_favorite" class="favorite-btn <?php echo $is_favorite ? 'active' : ''; ?>" title="<?php echo $is_favorite ? 'Remove from favorites' : 'Add to favorites'; ?>">
                    <i class="fas fa-heart"></i>
                </button>
            </form>
            <?php endif; ?>
            
            <img src="<?php echo htmlspecialchars($teacher['profile_pic']); ?>" alt="<?php echo htmlspecialchars($teacher['name']); ?>" class="profile-pic-large" onerror="this.src='<?php echo getAssetPath('images/placeholder-teacher.svg'); ?>'">
            <h1><?php echo htmlspecialchars($teacher['name']); ?></h1>
            
            <?php if (!empty($teacher['specialty'])): ?>
            <span class="specialty-tag"><i class="fas fa-graduation-cap"></i> <?php echo htmlspecialchars($teacher['specialty']); ?></span>
            <?php else: ?>
            <p class="tagline">Professional Teacher</p>
            <?php endif; ?>
            
            <div class="rating-display">
                <?php echo getStarRatingHtml($rating_data['avg_rating'], false); ?>
                <span class="rating-number"><?php echo $rating_data['avg_rating']; ?></span>
                <span class="review-count">(<?php echo $rating_data['review_count']; ?> reviews)</span>
            </div>
        </div>

        <?php if ($teacher['video_url']): ?>
        <div class="video-container">
            <video controls>
                <source src="<?php echo htmlspecialchars($teacher['video_url']); ?>" type="video/mp4">
                Your browser does not support the video tag.
            </video>
        </div>
        <?php endif; ?>

        <section class="content-section">
            <h2><i class="fas fa-user"></i> About Me</h2>
            <div style="line-height: 1.8; color: #444;">
                <?php 
                $about_content = !empty($teacher['about_text']) ? $teacher['about_text'] : (!empty($teacher['bio']) ? $teacher['bio'] : '');
                if (!empty($about_content)) {
                    echo nl2br(htmlspecialchars($about_content));
                } else {
                    echo '<p style="color: #999; font-style: italic;">No bio available</p>';
                }
                ?>
            </div>
            
            <?php if ($teacher['hours_taught'] > 0): ?>
            <div style="margin-top: 20px; padding-top: 20px; border-top: 1px solid #eee; display: flex; gap: 30px; color: #666;">
                <div><i class="fas fa-clock" style="color: var(--primary);"></i> <strong><?php echo $teacher['hours_taught']; ?></strong> hours taught</div>
                <?php if ($teacher['age_visibility'] === 'public' && $teacher['age']): ?>
                <div><i class="fas fa-birthday-cake" style="color: var(--primary);"></i> Age: <?php echo $teacher['age']; ?></div>
                <?php endif; ?>
            </div>
            <?php endif; ?>
        </section>

        <section class="content-section">
            <h2><i class="fas fa-dollar-sign"></i> Pricing & Booking</h2>
            
            <!-- Single Class Option (Visually Distinct) -->
            <div class="pricing-highlight">
                <h3>Single Class / Trial Lesson</h3>
                <div class="price-large">
                    <?php echo $teacher['hourly_rate'] ? '$' . number_format($teacher['hourly_rate'], 0) : '$30'; ?>
                </div>
                <p style="margin: 10px 0 20px; color: #666;">per hour</p>
                <form action="create_checkout_session.php" method="POST" style="display: inline-block;">
                    <input type="hidden" name="price_id" value="price_1SXv22Fg7Fwmuz0xYimW2nGp">
                    <input type="hidden" name="mode" value="payment">
                    <button type="submit" class="btn-book" style="margin: 0;">
                        <i class="fas fa-calendar-plus"></i> Book Now
                    </button>
                </form>
            </div>
            
            <!-- Subscription Plans -->
            <h3 style="text-align: center; margin: 40px 0 20px; color: #004080; font-size: 1.5rem;">
                <i class="fas fa-calendar-check"></i> Monthly Subscription Plans
            </h3>
            
            <div class="plans-grid">
                <div class="plan">
                    <form action="create_checkout_session.php" method="POST" style="height: 100%;">
                        <input type="hidden" name="price_id" value="price_1SXvP8Fg7Fwmuz0x0bCZPbp2">
                        <input type="hidden" name="mode" value="subscription">
                        <button type="submit" class="plan-button">
                            <div class="plan-body">
                                <h3>Economy Plan</h3>
                                <p class="desc">1 class per week with a certified teacher.</p>
                                <p class="desc" style="color: #d9534f; font-weight: 600; margin-top: 8px; font-size: 0.9rem;"><i class="fas fa-info-circle"></i> Teacher will be assigned</p>
                                <p class="price">$85 / month</p>
                            </div>
                        </button>
                    </form>
                </div>
                
                <div class="plan">
                    <form action="create_checkout_session.php" method="POST" style="height: 100%;">
                        <input type="hidden" name="price_id" value="price_BASIC_PLACEHOLDER">
                        <input type="hidden" name="mode" value="subscription">
                        <button type="submit" class="plan-button">
                            <div class="plan-body">
                                <h3>Basic Plan</h3>
                                <p class="desc">2 classes per week. Choose your own tutor.</p>
                                <p class="price">$240 / month</p>
                            </div>
                        </button>
                    </form>
                </div>
                
                <div class="plan">
                    <form action="create_checkout_session.php" method="POST" style="height: 100%;">
                        <input type="hidden" name="price_id" value="price_STANDARD_PLACEHOLDER">
                        <input type="hidden" name="mode" value="subscription">
                        <button type="submit" class="plan-button">
                            <div class="plan-body">
                                <h3>Standard Plan</h3>
                                <p class="desc">4 classes per week, extra learning resources.</p>
                                <p class="price">$400 / month</p>
                            </div>
                        </button>
                    </form>
                </div>
                
                <div class="plan">
                    <form action="create_checkout_session.php" method="POST" style="height: 100%;">
                        <input type="hidden" name="price_id" value="price_PREMIUM_PLACEHOLDER">
                        <input type="hidden" name="mode" value="subscription">
                        <button type="submit" class="plan-button">
                            <div class="plan-body">
                                <h3>Premium Plan</h3>
                                <p class="desc">Unlimited classes, exclusive materials.</p>
                                <p class="price">$850 / month</p>
                            </div>
                        </button>
                    </form>
                </div>
            </div>
            
            <p style="text-align: center; margin-top: 20px; color: #666; font-size: 0.9rem;">
                <i class="fas fa-info-circle"></i> Plans renew automatically. Cancel anytime.
            </p>
            
            <div style="text-align: center; margin-top: 30px; display: flex; gap: 15px; justify-content: center; flex-wrap: wrap;">
                <a href="schedule.php?teacher=<?php echo urlencode($teacher['name']); ?>" class="btn-book">
                    <i class="fas fa-calendar-plus"></i>
                    Schedule a Lesson
                </a>
                <?php if ($user_role === 'student' && $user_id): ?>
                <a href="message_threads.php?user_id=<?php echo $teacher_id; ?>" class="btn-message">
                    <i class="fas fa-comments"></i> Message
                </a>
                <?php elseif (!$user_id): ?>
                <a href="login.php" style="display: inline-flex; align-items: center; gap: 8px; background: #6c757d; color: white; padding: 14px 35px; border-radius: 50px; text-decoration: none; font-weight: bold;">
                    <i class="fas fa-lock"></i> Login to Message
                </a>
                <?php endif; ?>
            </div>
        </section>

        <!-- Reviews Section -->
        <section class="content-section">
            <h2><i class="fas fa-star"></i> Reviews (<?php echo $rating_data['review_count']; ?>)</h2>
            
            <?php if ($can_review): ?>
            <div class="card" style="background: #f8fbff; margin-bottom: 30px;">
                <h3 style="border: none; padding: 0; margin-bottom: 15px;">Write a Review</h3>
                <form method="POST">
                    <div class="form-group">
                        <label>Your Rating</label>
                        <div class="star-rating-input">
                            <?php for ($i = 5; $i >= 1; $i--): ?>
                            <input type="radio" name="rating" value="<?php echo $i; ?>" id="star<?php echo $i; ?>" required>
                            <label for="star<?php echo $i; ?>"><i class="fas fa-star"></i></label>
                            <?php endfor; ?>
                        </div>
                    </div>
                    <div class="form-group">
                        <label>Your Review</label>
                        <textarea name="review_text" rows="4" placeholder="Share your experience with <?php echo htmlspecialchars(explode(' ', $teacher['name'])[0]); ?>..."></textarea>
                    </div>
                    <button type="submit" name="submit_review" class="btn-primary">
                        <i class="fas fa-paper-plane"></i> Submit Review
                    </button>
                </form>
            </div>
            <?php elseif ($has_reviewed): ?>
            <div class="alert-info" style="margin-bottom: 20px;">
                <i class="fas fa-check-circle"></i> You've already reviewed this teacher.
            </div>
            <?php endif; ?>

            <?php if (count($reviews) > 0): ?>
                <?php foreach ($reviews as $review): ?>
                <div class="review-card">
                    <div class="review-header">
                        <img src="<?php echo htmlspecialchars($review['student_pic']); ?>" alt="" class="review-avatar" onerror="this.src='<?php echo getAssetPath('images/placeholder-teacher.svg'); ?>'">
                        <div class="review-meta">
                            <div class="review-author"><?php echo htmlspecialchars($review['student_name']); ?></div>
                            <div class="review-date"><?php echo formatRelativeTime($review['created_at']); ?></div>
                        </div>
                        <?php echo getStarRatingHtml($review['rating'], false); ?>
                    </div>
                    <?php if ($review['review_text']): ?>
                    <p class="review-text"><?php echo nl2br(htmlspecialchars($review['review_text'])); ?></p>
                    <?php endif; ?>
                </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="empty-state" style="padding: 40px 20px;">
                    <i class="fas fa-star" style="font-size: 3rem; opacity: 0.3;"></i>
                    <h3 style="border: none; padding: 0;">No Reviews Yet</h3>
                    <p>Be the first to review <?php echo htmlspecialchars(explode(' ', $teacher['name'])[0]); ?>!</p>
                </div>
            <?php endif; ?>
        </section>
    </main>

    <footer>
        <p>Contact us: info@statenacademy.com | Phone: +1 234 567 890</p>
        <p>&copy; 2023 Staten Academy. All rights reserved.</p>
    </footer>
    <script src="<?php echo getAssetPath('js/menu.js'); ?>" defer></script>
</body>
</html>
