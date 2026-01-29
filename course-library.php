<?php
session_start();
require_once 'db.php';
require_once __DIR__ . '/app/Views/components/dashboard-functions.php';
require_once __DIR__ . '/app/Models/Course.php';
require_once __DIR__ . '/app/Models/CourseCategory.php';
require_once __DIR__ . '/app/Models/CourseEnrollment.php';

$user_id = $_SESSION['user_id'] ?? null;
$user_role = $_SESSION['user_role'] ?? 'guest';

// Get filter parameters
$category_id = isset($_GET['category']) ? (int)$_GET['category'] : null;
$difficulty = isset($_GET['difficulty']) ? $_GET['difficulty'] : '';
$search = isset($_GET['search']) ? trim($_GET['search']) : '';

// Initialize models
$courseModel = new Course($conn);
$categoryModel = new CourseCategory($conn);
$enrollmentModel = new CourseEnrollment($conn);

// Get all categories
$categories_result = $categoryModel->getAllOrdered();
$categories = [];
if ($categories_result) {
    while ($cat = $categories_result->fetch_assoc()) {
        $categories[] = $cat;
    }
}

// Get courses based on filters
if ($category_id || $difficulty || $search) {
    // Filtered courses
    $sql = "SELECT c.*, 
            cc.name as category_name, 
            cc.icon as category_icon,
            cc.color as category_color,
            u.name as instructor_name,
            (SELECT AVG(rating) FROM course_reviews WHERE course_id = c.id) as avg_rating,
            (SELECT COUNT(*) FROM course_reviews WHERE course_id = c.id) as review_count,
            (SELECT COUNT(*) FROM course_lessons WHERE course_id = c.id) as lesson_count
            FROM courses c
            LEFT JOIN course_categories cc ON c.category_id = cc.id
            LEFT JOIN users u ON c.instructor_id = u.id
            WHERE c.is_active = TRUE";
    
    $params = [];
    $types = "";
    
    if ($category_id) {
        $sql .= " AND c.category_id = ?";
        $params[] = $category_id;
        $types .= "i";
    }
    
    if ($difficulty) {
        $sql .= " AND c.difficulty_level = ?";
        $params[] = $difficulty;
        $types .= "s";
    }
    
    if ($search) {
        $sql .= " AND (c.title LIKE ? OR c.description LIKE ?)";
        $search_param = "%$search%";
        $params[] = $search_param;
        $params[] = $search_param;
        $types .= "ss";
    }
    
    $sql .= " ORDER BY c.is_featured DESC, c.created_at DESC";
    
    if (count($params) > 0) {
        $stmt = $conn->prepare($sql);
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $courses_result = $stmt->get_result();
    } else {
        $courses_result = $conn->query($sql);
    }
} else {
    // Get all active courses
    $courses_result = $courseModel->getActiveCourses();
}

// Check which courses user has access to
$enrolled_courses = [];
$accessible_courses = [];
if ($user_id && $user_role !== 'guest') {
    if ($user_role === 'student') {
        // Get enrolled courses
        $enrollments = $enrollmentModel->getUserEnrollments($user_id);
        while ($enrollment = $enrollments->fetch_assoc()) {
            $enrolled_courses[] = $enrollment['course_id'];
        }
        
        // Get accessible courses via plan
        $accessible = $enrollmentModel->getAccessibleCourses($user_id);
        while ($course = $accessible->fetch_assoc()) {
            $accessible_courses[] = $course['id'];
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Course Library - Staten Academy</title>
    <link rel="stylesheet" href="<?php echo getAssetPath('styles.css'); ?>">
    <link rel="stylesheet" href="<?php echo getAssetPath('css/dashboard.css'); ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .library-header {
            background: linear-gradient(135deg, #004080 0%, #0b6cf5 100%);
            color: white;
            padding: 60px 20px;
            text-align: center;
            margin-bottom: 40px;
        }
        .library-header h1 {
            margin: 0 0 10px 0;
            font-size: 2.5rem;
        }
        .filters-section {
            background: white;
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 30px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }
        .filters-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
        }
        .filter-group {
            display: flex;
            flex-direction: column;
        }
        .filter-group label {
            font-weight: bold;
            margin-bottom: 5px;
            color: #333;
        }
        .filter-group select,
        .filter-group input {
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-size: 1rem;
        }
        .courses-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 25px;
            margin-top: 30px;
        }
        .course-card {
            background: white;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
            transition: transform 0.3s, box-shadow 0.3s;
        }
        .course-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 20px rgba(0,0,0,0.15);
        }
        .course-thumbnail {
            width: 100%;
            height: 200px;
            object-fit: cover;
            background: #f0f0f0;
        }
        .course-content {
            padding: 20px;
        }
        .course-category {
            display: inline-block;
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: bold;
            margin-bottom: 10px;
        }
        .course-title {
            font-size: 1.2rem;
            font-weight: bold;
            margin: 10px 0;
            color: #333;
        }
        .course-meta {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin: 15px 0;
            font-size: 0.9rem;
            color: #666;
        }
        .course-rating {
            display: flex;
            align-items: center;
            gap: 5px;
        }
        .course-actions {
            display: flex;
            gap: 10px;
            margin-top: 15px;
        }
        .btn-enroll {
            flex: 1;
            background: #004080;
            color: white;
            padding: 10px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-weight: bold;
            text-decoration: none;
            text-align: center;
        }
        .btn-preview {
            background: #f0f0f0;
            color: #333;
            padding: 10px 15px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            text-decoration: none;
        }
        .badge-enrolled {
            background: #28a745;
            color: white;
            padding: 5px 10px;
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: bold;
        }
        .badge-locked {
            background: #dc3545;
            color: white;
            padding: 5px 10px;
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: bold;
        }
    </style>
</head>
<body>
    <?php if ($user_id): ?>
        <?php include __DIR__ . '/app/Views/components/dashboard-header.php'; ?>
    <?php else: ?>
        <?php include __DIR__ . '/header-user.php'; ?>
    <?php endif; ?>

    <div class="library-header">
        <h1><i class="fas fa-book-open"></i> Course Library</h1>
        <p>Explore our comprehensive collection of English learning courses</p>
    </div>

    <div style="max-width: 1400px; margin: 0 auto; padding: 0 20px;">
        <!-- Filters -->
        <div class="filters-section">
            <form method="GET" action="course-library.php">
                <div class="filters-grid">
                    <div class="filter-group">
                        <label><i class="fas fa-search"></i> Search</label>
                        <input type="text" name="search" placeholder="Search courses..." value="<?php echo h($search); ?>">
                    </div>
                    <div class="filter-group">
                        <label><i class="fas fa-folder"></i> Category</label>
                        <select name="category">
                            <option value="">All Categories</option>
                            <?php foreach ($categories as $cat): ?>
                                <option value="<?php echo (int)$cat['id']; ?>" <?php echo $category_id == $cat['id'] ? 'selected' : ''; ?>>
                                    <?php echo h($cat['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="filter-group">
                        <label><i class="fas fa-signal"></i> Difficulty</label>
                        <select name="difficulty">
                            <option value="">All Levels</option>
                            <option value="beginner" <?php echo $difficulty === 'beginner' ? 'selected' : ''; ?>>Beginner</option>
                            <option value="intermediate" <?php echo $difficulty === 'intermediate' ? 'selected' : ''; ?>>Intermediate</option>
                            <option value="advanced" <?php echo $difficulty === 'advanced' ? 'selected' : ''; ?>>Advanced</option>
                        </select>
                    </div>
                    <div class="filter-group" style="display: flex; align-items: flex-end;">
                        <button type="submit" class="btn-primary" style="width: 100%;">
                            <i class="fas fa-filter"></i> Apply Filters
                        </button>
                    </div>
                </div>
            </form>
        </div>

        <!-- Courses Grid -->
        <div class="courses-grid">
            <?php if ($courses_result && $courses_result->num_rows > 0): ?>
                <?php while ($course = $courses_result->fetch_assoc()): ?>
                    <?php
                    $is_enrolled = in_array($course['id'], $enrolled_courses);
                    $has_access = in_array($course['id'], $accessible_courses) || $is_enrolled;
                    $can_preview = $user_role === 'visitor' || $user_role === 'guest' || !$has_access;
                    ?>
                    <div class="course-card">
                        <?php if ($course['thumbnail_url']): ?>
                            <img src="<?php echo h($course['thumbnail_url']); ?>" alt="<?php echo h($course['title']); ?>" class="course-thumbnail">
                        <?php else: ?>
                            <div class="course-thumbnail" style="display: flex; align-items: center; justify-content: center; background: <?php echo h($course['category_color'] ?? '#004080'); ?>; color: white;">
                                <i class="fas <?php echo h($course['category_icon'] ?? 'fa-book'); ?>" style="font-size: 4rem;"></i>
                            </div>
                        <?php endif; ?>
                        <div class="course-content">
                            <span class="course-category" style="background: <?php echo h($course['category_color'] ?? '#004080'); ?>20; color: <?php echo h($course['category_color'] ?? '#004080'); ?>;">
                                <i class="fas <?php echo h($course['category_icon'] ?? 'fa-book'); ?>"></i> <?php echo h($course['category_name'] ?? 'Uncategorized'); ?>
                            </span>
                            <h3 class="course-title"><?php echo h($course['title']); ?></h3>
                            <p style="color: #666; font-size: 0.9rem; margin: 10px 0; min-height: 60px;">
                                <?php echo h(substr($course['description'] ?? '', 0, 120)); ?>...
                            </p>
                            <div class="course-meta">
                                <div>
                                    <i class="fas fa-signal"></i> <?php echo ucfirst($course['difficulty_level']); ?>
                                    <span style="margin-left: 15px;"><i class="fas fa-play-circle"></i> <?php echo $course['lesson_count'] ?? 0; ?> lessons</span>
                                </div>
                                <?php if ($course['avg_rating'] > 0): ?>
                                    <div class="course-rating">
                                        <i class="fas fa-star" style="color: #ffc107;"></i>
                                        <span><?php echo number_format($course['avg_rating'], 1); ?></span>
                                        <span style="color: #999;">(<?php echo $course['review_count']; ?>)</span>
                                    </div>
                                <?php endif; ?>
                            </div>
                            <div class="course-actions">
                                <?php if ($is_enrolled): ?>
                                    <a href="course-player.php?course=<?php echo $course['id']; ?>" class="btn-enroll">
                                        <i class="fas fa-play"></i> Continue Learning
                                    </a>
                                    <span class="badge-enrolled">Enrolled</span>
                                <?php elseif ($has_access): ?>
                                    <button onclick="enrollInCourse(<?php echo $course['id']; ?>, '<?php echo h($course['title']); ?>')" class="btn-enroll enroll-btn" data-course-id="<?php echo $course['id']; ?>">
                                        <i class="fas fa-plus-circle"></i> Enroll Now
                                    </button>
                                <?php elseif ($can_preview): ?>
                                    <a href="course-player.php?course=<?php echo $course['id']; ?>&preview=1" class="btn-preview">
                                        <i class="fas fa-eye"></i> Preview
                                    </a>
                                    <?php if ($user_role === 'visitor' || $user_role === 'guest'): ?>
                                        <a href="kids-plans.php" class="btn-enroll">
                                            <i class="fas fa-lock"></i> Subscribe to Access
                                        </a>
                                    <?php else: ?>
                                        <button onclick="enrollInCourse(<?php echo $course['id']; ?>, '<?php echo h($course['title']); ?>')" class="btn-enroll enroll-btn" data-course-id="<?php echo $course['id']; ?>">
                                            <i class="fas fa-plus-circle"></i> Enroll Now
                                        </button>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <a href="kids-plans.php" class="btn-enroll">
                                        <i class="fas fa-lock"></i> Subscribe to Access
                                    </a>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                <?php endwhile; ?>
            <?php else: ?>
                <div style="grid-column: 1 / -1; text-align: center; padding: 60px 20px;">
                    <i class="fas fa-book-open" style="font-size: 4rem; color: #ddd; margin-bottom: 20px;"></i>
                    <h2 style="color: #666;">No courses found</h2>
                    <p style="color: #999;">Try adjusting your filters or check back later for new courses.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <?php if (!$user_id): ?>
        <footer style="margin-top: 60px;">
            <p>Contact us: info@statenacademy.com | Phone: +1 234 567 890</p>
            <p>&copy; <?php echo date('Y'); ?> Staten Academy. All rights reserved.</p>
        </footer>
    <?php endif; ?>

    <!-- Enrollment Modal -->
    <div id="enrollmentModal" class="modal" style="display: none;">
        <div class="modal-content" style="max-width: 500px; margin: 50px auto; background: white; padding: 30px; border-radius: 12px; box-shadow: 0 10px 40px rgba(0,0,0,0.2);">
            <div style="text-align: center; margin-bottom: 20px;">
                <div style="width: 80px; height: 80px; background: linear-gradient(135deg, #28a745 0%, #20c997 100%); border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 20px;">
                    <i class="fas fa-check" style="font-size: 2.5rem; color: white;"></i>
                </div>
                <h2 style="margin: 0; color: #333;">Enroll in Course</h2>
            </div>
            <div id="enrollmentModalContent">
                <p style="text-align: center; color: #666; margin-bottom: 20px;">
                    You are about to enroll in: <strong id="enrollmentCourseTitle"></strong>
                </p>
                <div style="display: flex; gap: 10px; justify-content: center;">
                    <button onclick="closeEnrollmentModal()" class="btn-outline" style="padding: 10px 20px;">Cancel</button>
                    <button onclick="confirmEnrollment()" class="btn-primary" id="confirmEnrollBtn" style="padding: 10px 20px;">
                        <i class="fas fa-check"></i> Confirm Enrollment
                    </button>
                </div>
            </div>
        </div>
    </div>

    <style>
        .modal {
            position: fixed;
            z-index: 10000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            overflow: auto;
            background-color: rgba(0,0,0,0.5);
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .enroll-btn {
            cursor: pointer;
            transition: all 0.3s;
        }
        .enroll-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0,64,128,0.3);
        }
        .enroll-btn.loading {
            opacity: 0.7;
            cursor: not-allowed;
        }
        .enrollment-success {
            text-align: center;
            padding: 20px;
        }
        .enrollment-success i {
            font-size: 4rem;
            color: #28a745;
            margin-bottom: 15px;
        }
    </style>

    <script>
        let currentCourseId = null;
        let currentCourseTitle = null;

        function enrollInCourse(courseId, courseTitle) {
            currentCourseId = courseId;
            currentCourseTitle = courseTitle;
            document.getElementById('enrollmentCourseTitle').textContent = courseTitle;
            document.getElementById('enrollmentModal').style.display = 'flex';
        }

        function closeEnrollmentModal() {
            document.getElementById('enrollmentModal').style.display = 'none';
            currentCourseId = null;
            currentCourseTitle = null;
        }

        function confirmEnrollment() {
            if (!currentCourseId) return;

            const btn = document.getElementById('confirmEnrollBtn');
            const originalText = btn.innerHTML;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Enrolling...';
            btn.disabled = true;
            btn.classList.add('loading');

            // Disable all enroll buttons
            document.querySelectorAll('.enroll-btn').forEach(b => {
                b.disabled = true;
                b.classList.add('loading');
            });

            fetch('api/course-enrollment.php?action=enroll', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'course_id=' + currentCourseId
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Show success message
                    document.getElementById('enrollmentModalContent').innerHTML = `
                        <div class="enrollment-success">
                            <i class="fas fa-check-circle"></i>
                            <h3 style="color: #28a745; margin: 10px 0;">Enrollment Successful!</h3>
                            <p style="color: #666;">You have been enrolled in "${currentCourseTitle}"</p>
                            <div style="display: flex; gap: 10px; justify-content: center; margin-top: 20px;">
                                <button onclick="closeEnrollmentModal(); location.reload();" class="btn-primary" style="padding: 10px 20px;">
                                    <i class="fas fa-check"></i> Continue
                                </button>
                                <a href="course-player.php?course=${currentCourseId}" class="btn-outline" style="padding: 10px 20px; text-decoration: none;">
                                    <i class="fas fa-play"></i> Start Learning
                                </a>
                            </div>
                        </div>
                    `;
                    
                    // Update the button in the course card
                    const courseCard = document.querySelector(`[data-course-id="${currentCourseId}"]`);
                    if (courseCard) {
                        courseCard.outerHTML = `
                            <a href="course-player.php?course=${currentCourseId}" class="btn-enroll">
                                <i class="fas fa-play"></i> Continue Learning
                            </a>
                            <span class="badge-enrolled">Enrolled</span>
                        `;
                    }
                } else {
                    if (data.requires_upgrade) {
                        alert('This course requires a Group Classes subscription. Redirecting to plans page...');
                        window.location.href = 'kids-plans.php';
                    } else {
                        alert(data.error || 'Failed to enroll in course. Please try again.');
                        btn.innerHTML = originalText;
                        btn.disabled = false;
                        btn.classList.remove('loading');
                        document.querySelectorAll('.enroll-btn').forEach(b => {
                            b.disabled = false;
                            b.classList.remove('loading');
                        });
                    }
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('An error occurred. Please try again.');
                btn.innerHTML = originalText;
                btn.disabled = false;
                btn.classList.remove('loading');
                document.querySelectorAll('.enroll-btn').forEach(b => {
                    b.disabled = false;
                    b.classList.remove('loading');
                });
            });
        }

        // Close modal when clicking outside
        document.getElementById('enrollmentModal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeEnrollmentModal();
            }
        });
    </script>

    <script src="<?php echo getAssetPath('js/menu.js'); ?>" defer></script>
</body>
</html>
