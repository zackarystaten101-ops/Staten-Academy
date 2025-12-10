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

// ACCESS CONTROL: Restrict teacher profiles
$has_access = false;

if ($user_id) {
    // Teachers/admins viewing their own profile
    if (($user_role === 'teacher' || $user_role === 'admin') && $user_id == $teacher_id) {
        $has_access = true;
    }
    // Admins viewing any teacher profile
    elseif ($user_role === 'admin') {
        $has_access = true;
    }
    // Students assigned to this teacher
    elseif ($user_role === 'student' || $user_role === 'new_student') {
        // Check if student is assigned to this teacher
        require_once __DIR__ . '/app/Models/TeacherAssignment.php';
        $assignmentModel = new TeacherAssignment($conn);
        $assignment = $assignmentModel->getStudentTeacher($user_id);
        
        if ($assignment && $assignment['teacher_id'] == $teacher_id) {
            $has_access = true;
        }
        // Also check if student has this teacher in assigned_teacher_id field
        $stmt = $conn->prepare("SELECT assigned_teacher_id FROM users WHERE id = ?");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($row = $result->fetch_assoc()) {
            if ($row['assigned_teacher_id'] == $teacher_id) {
                $has_access = true;
            }
        }
        $stmt->close();
    }
}

// Deny access if not authorized
if (!$has_access) {
    http_response_code(403);
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Access Denied - Staten Academy</title>
        <link rel="stylesheet" href="<?php echo getAssetPath('styles.css'); ?>">
        <link rel="stylesheet" href="<?php echo getAssetPath('css/auth.css'); ?>">
        <style>
            .access-denied {
                max-width: 600px;
                margin: 100px auto;
                padding: 40px;
                background: white;
                border-radius: 12px;
                box-shadow: 0 4px 20px rgba(0,0,0,0.1);
                text-align: center;
            }
            .access-denied h1 {
                color: #dc3545;
                margin-bottom: 20px;
            }
            .access-denied p {
                color: #404040;
                margin-bottom: 30px;
                line-height: 1.6;
            }
        </style>
    </head>
    <body>
        <div class="access-denied">
            <h1><i class="fas fa-lock"></i> Access Denied</h1>
            <p>Teacher profiles are only accessible to assigned students, the teacher themselves, or administrators.</p>
            <p>If you believe you should have access to this profile, please contact support.</p>
            <a href="index.php" class="btn-submit" style="display: inline-block; text-decoration: none; margin-top: 20px;">Return to Home</a>
        </div>
    </body>
    </html>
    <?php
    exit();
}

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

// Get student's learning track to determine which plans page to link to
$student_track = null;
if ($user_id && ($user_role === 'student' || $user_role === 'new_student')) {
    $track_stmt = $conn->prepare("SELECT learning_track FROM users WHERE id = ?");
    $track_stmt->bind_param("i", $user_id);
    $track_stmt->execute();
    $track_result = $track_stmt->get_result();
    if ($track_row = $track_result->fetch_assoc()) {
        $student_track = $track_row['learning_track'];
    }
    $track_stmt->close();
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
    <link rel="stylesheet" href="<?php echo getAssetPath('css/modern-shadows.css'); ?>">
    <link rel="stylesheet" href="<?php echo getAssetPath('css/dashboard.css'); ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        /* Profile Page Specific Styles */
        body {
            background: linear-gradient(135deg, #f5f7fa 0%, #e4e8eb 100%);
        }
        
        .profile-container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 30px 20px;
        }
        
        /* Profile Header Card */
        .profile-header-card {
            background: white;
            border-radius: var(--radius);
            box-shadow: var(--shadow);
            padding: 40px;
            margin-bottom: 30px;
            text-align: center;
            position: relative;
        }
        
        .profile-pic-wrapper {
            position: relative;
            display: inline-block;
            margin-bottom: 20px;
        }
        
        .profile-pic-large {
            width: 160px;
            height: 160px;
            border-radius: 50%;
            object-fit: cover;
            border: 4px solid white;
            box-shadow: 0 8px 30px rgba(0,0,0,0.15);
        }
        
        .favorite-btn {
            position: absolute;
            top: 10px;
            right: 10px;
            background: white;
            border: 2px solid #ddd;
            width: 45px;
            height: 45px;
            border-radius: 50%;
            cursor: pointer;
            font-size: 1.2rem;
            color: #ddd;
            transition: all 0.3s;
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        .favorite-btn:hover {
            border-color: #ff6b6b;
            color: #ff6b6b;
            transform: scale(1.1);
        }
        
        .favorite-btn.active {
            border-color: #ff6b6b;
            color: #ff6b6b;
            background: #fff0f0;
        }
        
        .profile-name {
            font-size: 2rem;
            font-weight: 700;
            color: var(--secondary);
            margin: 15px 0 10px;
        }
        
        .profile-specialty {
            display: inline-block;
            background: var(--primary-light);
            color: var(--primary);
            padding: 8px 20px;
            border-radius: 25px;
            font-size: 0.95rem;
            font-weight: 600;
            margin: 10px 0 20px;
        }
        
        .profile-rating {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 15px;
            margin: 20px 0;
            flex-wrap: wrap;
        }
        
        .rating-number {
            font-size: 1.8rem;
            font-weight: 700;
            color: var(--dark);
        }
        
        .review-count {
            color: var(--gray);
            font-size: 1rem;
        }
        
        /* Action Buttons */
        .profile-actions {
            display: flex;
            gap: 15px;
            justify-content: center;
            margin-top: 30px;
            flex-wrap: wrap;
        }
        
        .profile-action-btn {
            display: inline-flex;
            align-items: center;
            gap: 10px;
            padding: 14px 28px;
            border-radius: 50px;
            text-decoration: none;
            font-weight: 600;
            font-size: 1rem;
            transition: all 0.3s;
            border: none;
            cursor: pointer;
            white-space: nowrap;
        }
        
        .btn-primary-action {
            background: linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 100%);
            color: white;
        }
        
        .btn-primary-action:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(11, 108, 245, 0.4);
        }
        
        .btn-secondary-action {
            background: linear-gradient(135deg, var(--success) 0%, #1e7e34 100%);
            color: white;
        }
        
        .btn-secondary-action:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(40, 167, 69, 0.4);
        }
        
        .btn-outline-action {
            background: white;
            color: var(--primary);
            border: 2px solid var(--primary);
        }
        
        .btn-outline-action:hover {
            background: var(--primary-light);
        }
        
        /* Stats Grid */
        .profile-stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin: 30px 0;
        }
        
        .stat-item {
            background: var(--light-gray);
            padding: 20px;
            border-radius: var(--radius-sm);
            text-align: center;
        }
        
        .stat-item i {
            font-size: 1.8rem;
            color: var(--primary);
            margin-bottom: 10px;
        }
        
        .stat-item .stat-value {
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--dark);
            display: block;
        }
        
        .stat-item .stat-label {
            font-size: 0.9rem;
            color: var(--gray);
            margin-top: 5px;
        }
        
        /* Content Sections */
        .profile-content-grid {
            display: grid;
            grid-template-columns: 1fr;
            gap: 30px;
        }
        
        @media (min-width: 992px) {
            .profile-content-grid {
                grid-template-columns: 2fr 1fr;
            }
        }
        
        .profile-section {
            background: white;
            border-radius: var(--radius);
            box-shadow: var(--shadow);
            padding: 30px;
        }
        
        .profile-section h2 {
            color: var(--secondary);
            font-size: 1.5rem;
            font-weight: 700;
            margin: 0 0 20px 0;
            padding-bottom: 15px;
            border-bottom: 2px solid var(--primary-light);
            display: flex;
            align-items: center;
            gap: 12px;
        }
        
        .profile-section h2 i {
            color: var(--primary);
            font-size: 1.3rem;
        }
        
        /* Video Container */
        .video-container {
            position: relative;
            padding-bottom: 56.25%;
            height: 0;
            overflow: hidden;
            border-radius: var(--radius);
            box-shadow: var(--shadow);
            margin-bottom: 30px;
            background: #000;
        }
        
        .video-container video,
        .video-container iframe {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
        }
        
        /* About Content */
        .about-content {
            line-height: 1.8;
            color: var(--dark);
            font-size: 1rem;
        }
        
        /* Reviews */
        .review-card {
            background: var(--light-gray);
            border-radius: var(--radius-sm);
            padding: 20px;
            margin-bottom: 20px;
        }
        
        .review-header {
            display: flex;
            align-items: center;
            gap: 15px;
            margin-bottom: 15px;
        }
        
        .review-avatar {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            object-fit: cover;
        }
        
        .review-meta {
            flex: 1;
        }
        
        .review-author {
            font-weight: 600;
            color: var(--dark);
            margin-bottom: 5px;
        }
        
        .review-date {
            font-size: 0.85rem;
            color: var(--gray);
        }
        
        .review-text {
            color: var(--dark);
            line-height: 1.6;
            margin: 0;
        }
        
        /* Plans Link Card */
        .plans-link-card {
            background: linear-gradient(135deg, var(--primary-light) 0%, #d4e7ff 100%);
            border: 2px solid var(--primary);
            border-radius: var(--radius);
            padding: 25px;
            text-align: center;
            margin-top: 30px;
        }
        
        .plans-link-card h3 {
            color: var(--secondary);
            margin: 0 0 15px 0;
            font-size: 1.3rem;
        }
        
        .plans-link-card p {
            color: var(--gray);
            margin: 0 0 20px 0;
            line-height: 1.6;
        }
        
        /* Empty State */
        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: var(--gray);
        }
        
        .empty-state i {
            font-size: 4rem;
            opacity: 0.2;
            margin-bottom: 20px;
        }
        
        .empty-state h3 {
            color: var(--dark);
            margin: 0 0 10px 0;
        }
        
        /* Responsive */
        @media (max-width: 768px) {
            .profile-container {
                padding: 20px 15px;
            }
            
            .profile-header-card {
                padding: 30px 20px;
            }
            
            .profile-name {
                font-size: 1.6rem;
            }
            
            .profile-actions {
                flex-direction: column;
            }
            
            .profile-action-btn {
                width: 100%;
                justify-content: center;
            }
        }
    </style>
</head>
<body>
    <?php include 'header-user.php'; ?>
    
    <div class="profile-container">
        <?php if (isset($_GET['reviewed'])): ?>
        <div class="alert-success" style="margin-bottom: 20px; padding: 15px 20px; background: #d4edda; color: #155724; border-radius: var(--radius-sm); border-left: 4px solid var(--success);">
            <i class="fas fa-check-circle"></i> Thank you for your review!
        </div>
        <?php endif; ?>

        <!-- Profile Header -->
        <div class="profile-header-card">
            <?php if ($user_role === 'student' && $user_id): ?>
            <form method="POST" style="display: inline;">
                <button type="submit" name="toggle_favorite" class="favorite-btn <?php echo $is_favorite ? 'active' : ''; ?>" title="<?php echo $is_favorite ? 'Remove from favorites' : 'Add to favorites'; ?>">
                    <i class="fas fa-heart"></i>
                </button>
            </form>
            <?php endif; ?>
            
            <div class="profile-pic-wrapper">
                <img src="<?php echo htmlspecialchars($teacher['profile_pic']); ?>" 
                     alt="<?php echo htmlspecialchars($teacher['name']); ?>" 
                     class="profile-pic-large" 
                     onerror="this.src='<?php echo getAssetPath('images/placeholder-teacher.svg'); ?>'">
            </div>
            
            <h1 class="profile-name"><?php echo htmlspecialchars($teacher['name']); ?></h1>
            
            <?php if (!empty($teacher['specialty'])): ?>
            <span class="profile-specialty">
                <i class="fas fa-graduation-cap"></i> <?php echo htmlspecialchars($teacher['specialty']); ?>
            </span>
            <?php endif; ?>
            
            <div class="profile-rating">
                <?php echo getStarRatingHtml($rating_data['avg_rating'], false); ?>
                <span class="rating-number"><?php echo $rating_data['avg_rating']; ?></span>
                <span class="review-count">(<?php echo $rating_data['review_count']; ?> reviews)</span>
            </div>
            
            <!-- Stats -->
            <?php if ($teacher['hours_taught'] > 0 || ($teacher['age_visibility'] === 'public' && $teacher['age'])): ?>
            <div class="profile-stats">
                <?php if ($teacher['hours_taught'] > 0): ?>
                <div class="stat-item">
                    <i class="fas fa-clock"></i>
                    <span class="stat-value"><?php echo $teacher['hours_taught']; ?></span>
                    <span class="stat-label">Hours Taught</span>
                </div>
                <?php endif; ?>
                <?php if ($teacher['age_visibility'] === 'public' && $teacher['age']): ?>
                <div class="stat-item">
                    <i class="fas fa-birthday-cake"></i>
                    <span class="stat-value"><?php echo $teacher['age']; ?></span>
                    <span class="stat-label">Years Old</span>
                </div>
                <?php endif; ?>
            </div>
            <?php endif; ?>
            
            <!-- Action Buttons -->
            <div class="profile-actions">
                <a href="schedule.php?teacher=<?php echo urlencode($teacher['name']); ?>" class="profile-action-btn btn-primary-action">
                    <i class="fas fa-calendar-plus"></i> Schedule Lesson
                </a>
                <?php if ($user_role === 'student' && $user_id): ?>
                <a href="message_threads.php?user_id=<?php echo $teacher_id; ?>" class="profile-action-btn btn-secondary-action">
                    <i class="fas fa-comments"></i> Message
                </a>
                <?php elseif (!$user_id): ?>
                <a href="login.php" class="profile-action-btn btn-outline-action">
                    <i class="fas fa-lock"></i> Login to Message
                </a>
                <?php endif; ?>
            </div>
        </div>

        <!-- Main Content Grid -->
        <div class="profile-content-grid">
            <!-- Left Column: Main Content -->
            <div>
                <!-- Video Section -->
                <?php if ($teacher['video_url']): ?>
                <div class="profile-section">
                    <h2><i class="fas fa-video"></i> Introduction Video</h2>
                    <div class="video-container">
                        <?php if (strpos($teacher['video_url'], 'youtube.com') !== false || strpos($teacher['video_url'], 'youtu.be') !== false): ?>
                            <?php
                            // Extract YouTube video ID
                            preg_match('/(?:youtube\.com\/(?:[^\/]+\/.+\/|(?:v|e(?:mbed)?)\/|.*[?&]v=)|youtu\.be\/)([^"&?\/\s]{11})/', $teacher['video_url'], $matches);
                            $youtube_id = isset($matches[1]) ? $matches[1] : '';
                            if ($youtube_id):
                            ?>
                            <iframe src="https://www.youtube.com/embed/<?php echo $youtube_id; ?>" frameborder="0" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture" allowfullscreen></iframe>
                            <?php else: ?>
                            <video controls>
                                <source src="<?php echo htmlspecialchars($teacher['video_url']); ?>" type="video/mp4">
                                Your browser does not support the video tag.
                            </video>
                            <?php endif; ?>
                        <?php else: ?>
                        <video controls>
                            <source src="<?php echo htmlspecialchars($teacher['video_url']); ?>" type="video/mp4">
                            Your browser does not support the video tag.
                        </video>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endif; ?>

                <!-- About Section -->
                <div class="profile-section">
                    <h2><i class="fas fa-user"></i> About Me</h2>
                    <div class="about-content">
                        <?php 
                        $about_content = !empty($teacher['about_text']) ? $teacher['about_text'] : (!empty($teacher['bio']) ? $teacher['bio'] : '');
                        if (!empty($about_content)) {
                            echo nl2br(htmlspecialchars($about_content));
                        } else {
                            echo '<p style="color: var(--gray); font-style: italic;">No bio available</p>';
                        }
                        ?>
                    </div>
                </div>

                <!-- Reviews Section -->
                <div class="profile-section">
                    <h2><i class="fas fa-star"></i> Reviews (<?php echo $rating_data['review_count']; ?>)</h2>
                    
                    <?php if ($can_review): ?>
                    <div class="card" style="background: var(--primary-light); margin-bottom: 30px; padding: 25px; border-radius: var(--radius-sm);">
                        <h3 style="margin: 0 0 20px 0; color: var(--secondary); font-size: 1.2rem;">Write a Review</h3>
                        <form method="POST">
                            <div class="form-group">
                                <label style="display: block; margin-bottom: 10px; font-weight: 600; color: var(--dark);">Your Rating</label>
                                <div class="star-rating-input" style="display: flex; gap: 5px; justify-content: center; flex-direction: row-reverse;">
                                    <?php for ($i = 5; $i >= 1; $i--): ?>
                                    <input type="radio" name="rating" value="<?php echo $i; ?>" id="star<?php echo $i; ?>" required style="display: none;">
                                    <label for="star<?php echo $i; ?>" style="cursor: pointer; font-size: 2rem; color: #ddd; transition: color 0.2s;"><i class="fas fa-star"></i></label>
                                    <?php endfor; ?>
                                </div>
                            </div>
                            <div class="form-group" style="margin-top: 20px;">
                                <label style="display: block; margin-bottom: 10px; font-weight: 600; color: var(--dark);">Your Review</label>
                                <textarea name="review_text" rows="4" placeholder="Share your experience with <?php echo htmlspecialchars(explode(' ', $teacher['name'])[0]); ?>..." style="width: 100%; padding: 12px; border: 2px solid var(--border); border-radius: var(--radius-sm); font-family: inherit; font-size: 1rem; resize: vertical;"></textarea>
                            </div>
                            <button type="submit" name="submit_review" class="btn-primary" style="margin-top: 15px;">
                                <i class="fas fa-paper-plane"></i> Submit Review
                            </button>
                        </form>
                    </div>
                    <?php elseif ($has_reviewed): ?>
                    <div class="alert-info" style="margin-bottom: 20px; padding: 15px; background: #d1ecf1; color: #0c5460; border-radius: var(--radius-sm); border-left: 4px solid var(--info);">
                        <i class="fas fa-check-circle"></i> You've already reviewed this teacher.
                    </div>
                    <?php endif; ?>

                    <?php if (count($reviews) > 0): ?>
                        <?php foreach ($reviews as $review): ?>
                        <div class="review-card">
                            <div class="review-header">
                                <img src="<?php echo htmlspecialchars($review['student_pic']); ?>" 
                                     alt="" 
                                     class="review-avatar" 
                                     onerror="this.src='<?php echo getAssetPath('images/placeholder-teacher.svg'); ?>'">
                                <div class="review-meta">
                                    <div class="review-author"><?php echo htmlspecialchars($review['student_name']); ?></div>
                                    <div class="review-date"><?php echo formatRelativeTime($review['created_at']); ?></div>
                                </div>
                                <div style="margin-left: auto;">
                                    <?php echo getStarRatingHtml($review['rating'], false); ?>
                                </div>
                            </div>
                            <?php if ($review['review_text']): ?>
                            <p class="review-text"><?php echo nl2br(htmlspecialchars($review['review_text'])); ?></p>
                            <?php endif; ?>
                        </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="empty-state">
                            <i class="fas fa-star"></i>
                            <h3>No Reviews Yet</h3>
                            <p>Be the first to review <?php echo htmlspecialchars(explode(' ', $teacher['name'])[0]); ?>!</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Right Column: Sidebar -->
            <div>
                <!-- Plans Link Card -->
                <div class="plans-link-card">
                    <h3><i class="fas fa-credit-card"></i> View Plans & Pricing</h3>
                    <p>Explore our flexible subscription plans designed to fit your learning needs and schedule.</p>
                    <?php
                    // Determine which plans page to link to based on student's track
                    $plans_url = 'payment.php';
                    if ($student_track) {
                        switch(strtolower($student_track)) {
                            case 'kids':
                                $plans_url = 'kids-plans.php';
                                break;
                            case 'adults':
                                $plans_url = 'adults-plans.php';
                                break;
                            case 'coding':
                                $plans_url = 'coding-plans.php';
                                break;
                        }
                    }
                    ?>
                    <a href="<?php echo $plans_url; ?>" class="profile-action-btn btn-primary-action" style="display: inline-flex;">
                        <i class="fas fa-arrow-right"></i> View Plans
                    </a>
                </div>
            </div>
        </div>
    </div>

    <footer>
        <p>Contact us: info@statenacademy.com | Phone: +1 234 567 890</p>
        <p>&copy; <?php echo date('Y'); ?> Staten Academy. All rights reserved.</p>
    </footer>
    <script src="<?php echo getAssetPath('js/menu.js'); ?>" defer></script>
    <script>
    // Star rating interaction
    document.querySelectorAll('.star-rating-input input[type="radio"]').forEach(radio => {
        radio.addEventListener('change', function() {
            const rating = parseInt(this.value);
            const labels = document.querySelectorAll('.star-rating-input label');
            labels.forEach((label, index) => {
                const starIndex = 5 - index;
                if (starIndex <= rating) {
                    label.querySelector('i').style.color = '#ffc107';
                } else {
                    label.querySelector('i').style.color = '#ddd';
                }
            });
        });
        
        // Hover effect
        radio.addEventListener('mouseenter', function() {
            const rating = parseInt(this.value);
            const labels = document.querySelectorAll('.star-rating-input label');
            labels.forEach((label, index) => {
                const starIndex = 5 - index;
                if (starIndex <= rating) {
                    label.querySelector('i').style.color = '#ffc107';
                }
            });
        });
    });
    
    document.querySelector('.star-rating-input')?.addEventListener('mouseleave', function() {
        const checked = document.querySelector('.star-rating-input input[type="radio"]:checked');
        const labels = document.querySelectorAll('.star-rating-input label');
        labels.forEach((label, index) => {
            const starIndex = 5 - index;
            if (checked && starIndex <= parseInt(checked.value)) {
                label.querySelector('i').style.color = '#ffc107';
            } else {
                label.querySelector('i').style.color = '#ddd';
            }
        });
    });
    </script>
</body>
</html>
