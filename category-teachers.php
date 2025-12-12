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
require_once __DIR__ . '/app/Services/TeacherService.php';

// Ensure getAssetPath function is available
if (!function_exists('getAssetPath')) {
    require_once __DIR__ . '/app/Views/components/dashboard-functions.php';
}

// Get category from query parameter
$category = isset($_GET['category']) ? $_GET['category'] : 'adults';
$valid_categories = ['young_learners', 'adults', 'coding'];
if (!in_array($category, $valid_categories)) {
    $category = 'adults';
}

// Get filters
$filters = [
    'min_rating' => isset($_GET['min_rating']) ? floatval($_GET['min_rating']) : null,
    'max_price' => isset($_GET['max_price']) ? floatval($_GET['max_price']) : null,
    'has_availability' => isset($_GET['has_availability']) && $_GET['has_availability'] == '1'
];

// Get teachers
$teacherService = new TeacherService($conn);
$teachers = $teacherService->getTeachersByCategory($category, $filters);

// Category display names
$category_names = [
    'young_learners' => 'Young Learners (0-11)',
    'adults' => 'Adults (12+)',
    'coding' => 'English for Coding / Tech'
];

$category_name = $category_names[$category] ?? 'Teachers';

$user_role = $_SESSION['user_role'] ?? 'guest';
$user_id = $_SESSION['user_id'] ?? null;

// Check if user has used trial
$trial_used = false;
if ($user_id) {
    $trial_check = $conn->prepare("SELECT trial_used FROM users WHERE id = ?");
    $trial_check->bind_param("i", $user_id);
    $trial_check->execute();
    $trial_result = $trial_check->get_result();
    if ($trial_result->num_rows > 0) {
        $user_data = $trial_result->fetch_assoc();
        $trial_used = (bool)$user_data['trial_used'];
    }
    $trial_check->close();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($category_name); ?> - Staten Academy</title>
    <link rel="stylesheet" href="<?php echo getAssetPath('styles.css'); ?>">
    <link rel="stylesheet" href="<?php echo getAssetPath('css/mobile.css'); ?>">
    <link rel="stylesheet" href="<?php echo getAssetPath('css/tracks.css'); ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        body {
            background: linear-gradient(135deg, #f5f7fa 0%, #e8ecf1 100%);
            min-height: 100vh;
        }
        .page-header {
            background: linear-gradient(135deg, #004080 0%, #0b6cf5 100%);
            color: white;
            padding: 60px 20px;
            text-align: center;
        }
        .page-header h1 {
            font-size: 2.5rem;
            margin-bottom: 10px;
            font-weight: 700;
        }
        .filters-container {
            max-width: 1200px;
            margin: 30px auto;
            padding: 0 20px;
        }
        .filters-card {
            background: white;
            border-radius: 12px;
            padding: 25px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
            margin-bottom: 30px;
        }
        .filters-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            align-items: end;
        }
        .filter-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: #333;
        }
        .filter-group input,
        .filter-group select {
            width: 100%;
            padding: 10px;
            border: 2px solid #e0e0e0;
            border-radius: 6px;
            font-size: 1rem;
        }
        .filter-group input:focus,
        .filter-group select:focus {
            outline: none;
            border-color: #0b6cf5;
        }
        .filter-checkbox {
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .filter-checkbox input[type="checkbox"] {
            width: auto;
        }
        .btn-apply-filters {
            background: #0b6cf5;
            color: white;
            border: none;
            padding: 12px 30px;
            border-radius: 6px;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: background 0.3s;
        }
        .btn-apply-filters:hover {
            background: #004080;
        }
        .teachers-container {
            max-width: 1200px;
            margin: 0 auto 80px;
            padding: 0 20px;
        }
        .teachers-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 30px;
            margin-top: 30px;
        }
        .teacher-card {
            background: white;
            border-radius: 16px;
            padding: 30px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.1);
            transition: all 0.3s ease;
            text-align: center;
        }
        .teacher-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 30px rgba(0,0,0,0.15);
        }
        .teacher-photo {
            width: 120px;
            height: 120px;
            border-radius: 50%;
            object-fit: cover;
            margin: 0 auto 20px;
            border: 4px solid #0b6cf5;
        }
        .teacher-name {
            font-size: 1.5rem;
            font-weight: 700;
            color: #004080;
            margin-bottom: 10px;
        }
        .teacher-specialty {
            color: #666;
            font-size: 0.95rem;
            margin-bottom: 15px;
        }
        .teacher-rating {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 5px;
            margin-bottom: 15px;
        }
        .teacher-rating .stars {
            color: #ffa500;
        }
        .teacher-rating .rating-text {
            color: #666;
            font-size: 0.9rem;
        }
        .teacher-bio {
            color: #555;
            font-size: 0.95rem;
            line-height: 1.6;
            margin-bottom: 20px;
            display: -webkit-box;
            -webkit-line-clamp: 3;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }
        .teacher-stats {
            display: flex;
            justify-content: space-around;
            margin-bottom: 20px;
            padding: 15px 0;
            border-top: 1px solid #e0e0e0;
            border-bottom: 1px solid #e0e0e0;
        }
        .teacher-stat {
            text-align: center;
        }
        .teacher-stat-value {
            font-size: 1.3rem;
            font-weight: 700;
            color: #004080;
        }
        .teacher-stat-label {
            font-size: 0.85rem;
            color: #666;
        }
        .teacher-actions {
            display: flex;
            gap: 10px;
            flex-direction: column;
        }
        .btn-view-profile {
            background: #0b6cf5;
            color: white;
            border: none;
            padding: 12px 20px;
            border-radius: 6px;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
            transition: background 0.3s;
        }
        .btn-view-profile:hover {
            background: #004080;
        }
        .btn-book-trial {
            background: #28a745;
            color: white;
            border: none;
            padding: 12px 20px;
            border-radius: 6px;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
            transition: background 0.3s;
        }
        .btn-book-trial:hover:not(:disabled) {
            background: #218838;
        }
        .btn-book-trial:disabled {
            background: #ccc;
            cursor: not-allowed;
        }
        .no-teachers {
            text-align: center;
            padding: 60px 20px;
            color: #666;
        }
        .no-teachers i {
            font-size: 4rem;
            margin-bottom: 20px;
            color: #ccc;
        }
        @media (max-width: 768px) {
            .teachers-grid {
                grid-template-columns: 1fr;
            }
            .filters-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <?php include __DIR__ . '/app/Views/components/dashboard-header.php'; ?>
    
    <div class="page-header">
        <h1><?php echo htmlspecialchars($category_name); ?></h1>
        <p>Choose from our experienced teachers</p>
    </div>
    
    <div class="filters-container">
        <div class="filters-card">
            <form method="GET" action="">
                <input type="hidden" name="category" value="<?php echo htmlspecialchars($category); ?>">
                <div class="filters-grid">
                    <div class="filter-group">
                        <label for="min_rating">Minimum Rating</label>
                        <select name="min_rating" id="min_rating">
                            <option value="">Any Rating</option>
                            <option value="4.5" <?php echo $filters['min_rating'] == 4.5 ? 'selected' : ''; ?>>4.5+ Stars</option>
                            <option value="4.0" <?php echo $filters['min_rating'] == 4.0 ? 'selected' : ''; ?>>4.0+ Stars</option>
                            <option value="3.5" <?php echo $filters['min_rating'] == 3.5 ? 'selected' : ''; ?>>3.5+ Stars</option>
                        </select>
                    </div>
                    <div class="filter-group">
                        <label for="max_price">Max Price/Hour</label>
                        <input type="number" name="max_price" id="max_price" 
                               value="<?php echo $filters['max_price'] ? htmlspecialchars($filters['max_price']) : ''; ?>" 
                               placeholder="Any price" min="0" step="0.01">
                    </div>
                    <div class="filter-group">
                        <div class="filter-checkbox">
                            <input type="checkbox" name="has_availability" id="has_availability" value="1" 
                                   <?php echo $filters['has_availability'] ? 'checked' : ''; ?>>
                            <label for="has_availability">Available Now</label>
                        </div>
                    </div>
                    <div class="filter-group">
                        <button type="submit" class="btn-apply-filters">Apply Filters</button>
                    </div>
                </div>
            </form>
        </div>
    </div>
    
    <div class="teachers-container">
        <?php if (empty($teachers)): ?>
            <div class="no-teachers">
                <i class="fas fa-user-slash"></i>
                <h2>No teachers found</h2>
                <p>Try adjusting your filters or check back later.</p>
            </div>
        <?php else: ?>
            <div class="teachers-grid">
                <?php foreach ($teachers as $teacher): ?>
                    <div class="teacher-card">
                        <img src="<?php echo htmlspecialchars($teacher['profile_pic'] ?? getAssetPath('images/placeholder-teacher.svg')); ?>" 
                             alt="<?php echo htmlspecialchars($teacher['name']); ?>" 
                             class="teacher-photo">
                        <h3 class="teacher-name"><?php echo htmlspecialchars($teacher['name']); ?></h3>
                        <?php if ($teacher['specialty']): ?>
                            <p class="teacher-specialty"><?php echo htmlspecialchars($teacher['specialty']); ?></p>
                        <?php endif; ?>
                        
                        <div class="teacher-rating">
                            <?php if ($teacher['avg_rating']): ?>
                                <span class="stars">
                                    <?php 
                                    $rating = floatval($teacher['avg_rating']);
                                    $full_stars = floor($rating);
                                    $half_star = ($rating - $full_stars) >= 0.5;
                                    for ($i = 0; $i < $full_stars; $i++) {
                                        echo '<i class="fas fa-star"></i>';
                                    }
                                    if ($half_star) {
                                        echo '<i class="fas fa-star-half-alt"></i>';
                                    }
                                    for ($i = $full_stars + ($half_star ? 1 : 0); $i < 5; $i++) {
                                        echo '<i class="far fa-star"></i>';
                                    }
                                    ?>
                                </span>
                                <span class="rating-text">
                                    <?php echo number_format($rating, 1); ?> 
                                    (<?php echo intval($teacher['review_count']); ?> reviews)
                                </span>
                            <?php else: ?>
                                <span class="rating-text">No ratings yet</span>
                            <?php endif; ?>
                        </div>
                        
                        <?php if ($teacher['bio']): ?>
                            <p class="teacher-bio"><?php echo htmlspecialchars(substr($teacher['bio'], 0, 150)); ?><?php echo strlen($teacher['bio']) > 150 ? '...' : ''; ?></p>
                        <?php endif; ?>
                        
                        <div class="teacher-stats">
                            <div class="teacher-stat">
                                <div class="teacher-stat-value"><?php echo intval($teacher['total_lessons']); ?></div>
                                <div class="teacher-stat-label">Lessons</div>
                            </div>
                            <?php if ($teacher['hourly_rate']): ?>
                                <div class="teacher-stat">
                                    <div class="teacher-stat-value">$<?php echo number_format($teacher['hourly_rate'], 0); ?></div>
                                    <div class="teacher-stat-label">Per Hour</div>
                                </div>
                            <?php endif; ?>
                        </div>
                        
                        <div class="teacher-actions">
                            <a href="teacher-profile.php?id=<?php echo intval($teacher['id']); ?>" 
                               class="btn-view-profile">View Profile</a>
                            <?php if ($user_role === 'student' || $user_role === 'new_student'): ?>
                                <button class="btn-book-trial" 
                                        onclick="bookTrial(<?php echo intval($teacher['id']); ?>)" 
                                        <?php echo $trial_used ? 'disabled title="Trial already used"' : ''; ?>>
                                    <?php echo $trial_used ? 'Trial Used' : 'Book Trial ($25)'; ?>
                                </button>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
    
    <?php include __DIR__ . '/app/Views/components/footer.php'; ?>
    
    <script>
    function bookTrial(teacherId) {
        if (confirm('Book a trial lesson with this teacher for $25?')) {
            window.location.href = 'create_checkout_session.php?type=trial&teacher_id=' + teacherId;
        }
    }
    </script>
</body>
</html>

