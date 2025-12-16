<?php
session_start();
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/app/Views/components/dashboard-functions.php';

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'visitor') {
    // Check if user has purchased a class (should be student now)
    if (isset($_SESSION['user_id'])) {
        $check_stmt = $conn->prepare("SELECT role, has_purchased_class FROM users WHERE id = ?");
        $check_stmt->bind_param("i", $_SESSION['user_id']);
        $check_stmt->execute();
        $check_result = $check_stmt->get_result();
        $user_check = $check_result->fetch_assoc();
        $check_stmt->close();
        
        if ($user_check && ($user_check['role'] === 'student' || $user_check['has_purchased_class'])) {
            header("Location: student-dashboard.php");
            exit();
        }
    } else {
        header("Location: login.php");
        exit();
    }
}

$visitor_id = $_SESSION['user_id'];
$user = getUserById($conn, $visitor_id);
$user_role = 'visitor';

// Get featured courses for preview
$featured_courses = $conn->query("
    SELECT c.*, 
           cc.name as category_name,
           cc.icon as category_icon,
           cc.color as category_color,
           (SELECT COUNT(*) FROM course_lessons WHERE course_id = c.id AND is_preview = TRUE) as preview_lessons
    FROM courses c
    LEFT JOIN course_categories cc ON c.category_id = cc.id
    WHERE c.is_active = TRUE AND c.is_featured = TRUE
    ORDER BY c.created_at DESC
    LIMIT 6
");

// Get available teachers (limited view)
$teachers = $conn->query("
    SELECT u.id, u.name, u.bio, u.profile_pic, u.specialty,
           COALESCE((SELECT AVG(rating) FROM reviews WHERE teacher_id = u.id), 0) as avg_rating,
           COALESCE((SELECT COUNT(*) FROM reviews WHERE teacher_id = u.id), 0) as review_count
    FROM users u
    WHERE u.role = 'teacher' AND u.application_status = 'approved'
    ORDER BY avg_rating DESC, review_count DESC
    LIMIT 6
");

$active_tab = 'overview';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Visitor Dashboard - Staten Academy</title>
    <link rel="stylesheet" href="<?php echo getAssetPath('styles.css'); ?>">
    <link rel="stylesheet" href="<?php echo getAssetPath('css/dashboard.css'); ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .upgrade-banner {
            background: linear-gradient(135deg, #004080 0%, #0b6cf5 100%);
            color: white;
            padding: 30px;
            border-radius: 12px;
            margin-bottom: 30px;
            text-align: center;
        }
        .upgrade-banner h2 {
            margin: 0 0 10px 0;
            font-size: 1.8rem;
        }
        .upgrade-banner p {
            margin: 0 0 20px 0;
            opacity: 0.9;
        }
        .btn-upgrade {
            display: inline-block;
            background: white;
            color: #004080;
            padding: 12px 30px;
            border-radius: 50px;
            text-decoration: none;
            font-weight: bold;
            font-size: 1.1rem;
            transition: transform 0.2s;
        }
        .btn-upgrade:hover {
            transform: scale(1.05);
        }
        .preview-badge {
            display: inline-block;
            background: #ffc107;
            color: #000;
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 0.75rem;
            font-weight: bold;
            margin-left: 10px;
        }
        .course-card {
            background: white;
            border-radius: 10px;
            overflow: hidden;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            transition: transform 0.2s;
        }
        .course-card:hover {
            transform: translateY(-5px);
        }
        .course-thumbnail {
            width: 100%;
            height: 180px;
            object-fit: cover;
            background: #f0f0f0;
        }
        .course-content {
            padding: 20px;
        }
        .course-category {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 0.85rem;
            margin-bottom: 10px;
        }
    </style>
</head>
<body class="dashboard-layout">

<?php include __DIR__ . '/app/Views/components/dashboard-header.php'; ?>

<div class="content-wrapper">
    <?php include __DIR__ . '/app/Views/components/dashboard-sidebar.php'; ?>

    <div class="main">
        
        <!-- Overview Tab -->
        <div id="overview" class="tab-content active">
            <h1>Welcome, <?php echo h($user['name']); ?>! 👋</h1>
            
            <div class="upgrade-banner">
                <h2>Start Your English Learning Journey</h2>
                <p>Upgrade to a student account to access full courses, book classes with teachers, and track your progress!</p>
                <div style="display: flex; gap: 15px; justify-content: center; flex-wrap: wrap; margin-top: 20px;">
                    <a href="payment.php" class="btn-upgrade">View Plans & Upgrade</a>
                    <a href="#" onclick="startAdminChat(event)" style="display: inline-block; background: rgba(255,255,255,0.2); color: white; padding: 12px 30px; border-radius: 50px; text-decoration: none; font-weight: bold; font-size: 1.1rem; transition: transform 0.2s; border: 2px solid rgba(255,255,255,0.3);">
                        <i class="fas fa-headset"></i> Contact Admin
                    </a>
                </div>
            </div>
            
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-icon"><i class="fas fa-eye"></i></div>
                    <div class="stat-info">
                        <h3>Visitor</h3>
                        <p>Account Type</p>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon info"><i class="fas fa-book-open"></i></div>
                    <div class="stat-info">
                        <h3>Preview</h3>
                        <p>Free Content</p>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon success"><i class="fas fa-chalkboard-teacher"></i></div>
                    <div class="stat-info">
                        <h3><?php echo $teachers ? $teachers->num_rows : 0; ?></h3>
                        <p>Available Teachers</p>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon warning"><i class="fas fa-gift"></i></div>
                    <div class="stat-info">
                        <h3>Upgrade</h3>
                        <p>To Unlock More</p>
                    </div>
                </div>
            </div>

            <!-- Featured Courses Preview -->
            <div class="card" style="margin-top: 30px;">
                <h2 style="margin-bottom: 20px;">Featured Courses <span class="preview-badge">PREVIEW</span></h2>
                <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); gap: 20px;">
                    <?php if ($featured_courses && $featured_courses->num_rows > 0): ?>
                        <?php while ($course = $featured_courses->fetch_assoc()): ?>
                            <div class="course-card">
                                <?php if ($course['thumbnail_url']): ?>
                                    <img src="<?php echo h($course['thumbnail_url']); ?>" alt="<?php echo h($course['title']); ?>" class="course-thumbnail">
                                <?php else: ?>
                                    <div class="course-thumbnail" style="display: flex; align-items: center; justify-content: center; background: <?php echo h($course['category_color'] ?? '#004080'); ?>; color: white;">
                                        <i class="fas <?php echo h($course['category_icon'] ?? 'fa-book'); ?>" style="font-size: 3rem;"></i>
                                    </div>
                                <?php endif; ?>
                                <div class="course-content">
                                    <span class="course-category" style="background: <?php echo h($course['category_color'] ?? '#004080'); ?>20; color: <?php echo h($course['category_color'] ?? '#004080'); ?>;">
                                        <?php echo h($course['category_name'] ?? 'Uncategorized'); ?>
                                    </span>
                                    <h3 style="margin: 10px 0; font-size: 1.1rem;"><?php echo h($course['title']); ?></h3>
                                    <p style="color: #666; font-size: 0.9rem; margin: 10px 0;"><?php echo h(substr($course['description'] ?? '', 0, 100)); ?>...</p>
                                    <a href="course-library.php?preview=<?php echo $course['id']; ?>" class="btn-outline btn-sm" style="width: 100%; text-align: center; display: block;">
                                        <i class="fas fa-play"></i> Preview Course
                                    </a>
                                </div>
                            </div>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <p style="grid-column: 1 / -1; text-align: center; color: #666; padding: 40px;">
                            No courses available yet. Check back soon!
                        </p>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Available Teachers -->
            <div class="card" style="margin-top: 30px;">
                <h2 style="margin-bottom: 20px;">Available Teachers</h2>
                <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(250px, 1fr)); gap: 20px;">
                    <?php if ($teachers && $teachers->num_rows > 0): ?>
                        <?php while ($teacher = $teachers->fetch_assoc()): ?>
                            <div style="background: #f9f9f9; padding: 20px; border-radius: 10px; text-align: center;">
                                <img src="<?php echo h($teacher['profile_pic']); ?>" alt="<?php echo h($teacher['name']); ?>" 
                                     style="width: 80px; height: 80px; border-radius: 50%; object-fit: cover; margin-bottom: 10px;"
                                     onerror="this.src='<?php echo getAssetPath('images/placeholder-teacher.svg'); ?>'">
                                <h3 style="margin: 10px 0;"><?php echo h($teacher['name']); ?></h3>
                                <?php if ($teacher['specialty']): ?>
                                    <p style="color: #666; font-size: 0.9rem; margin: 5px 0;"><?php echo h($teacher['specialty']); ?></p>
                                <?php endif; ?>
                                <div style="margin: 10px 0;">
                                    <?php for ($i = 0; $i < 5; $i++): ?>
                                        <i class="fas fa-star" style="color: <?php echo $i < round($teacher['avg_rating']) ? '#ffc107' : '#ddd'; ?>;"></i>
                                    <?php endfor; ?>
                                    <span style="margin-left: 5px; color: #666;">(<?php echo $teacher['review_count']; ?>)</span>
                                </div>
                                <a href="profile.php?id=<?php echo $teacher['id']; ?>" class="btn-outline btn-sm" style="margin-top: 10px;">
                                    View Profile
                                </a>
                            </div>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <p style="grid-column: 1 / -1; text-align: center; color: #666; padding: 40px;">
                            No teachers available at the moment.
                        </p>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Explore Courses Tab -->
        <div id="courses" class="tab-content">
            <h1>Course Library</h1>
            <p style="color: #666; margin-bottom: 30px;">Preview our course catalog. Upgrade to access full courses!</p>
            <a href="course-library.php" class="btn-primary" style="display: inline-block; margin-bottom: 20px;">
                <i class="fas fa-book-open"></i> Browse All Courses
            </a>
            <!-- Course library will be loaded here -->
        </div>

        <!-- Plans Tab -->
        <div id="plans" class="tab-content">
            <h1>Choose Your Plan</h1>
            <p style="color: #666; margin-bottom: 30px;">Select a plan that fits your learning goals and unlock full access to courses and teachers.</p>
            <a href="payment.php" class="btn-primary" style="display: inline-block; margin-bottom: 20px;">
                <i class="fas fa-credit-card"></i> View All Plans
            </a>
            <!-- Plan comparison will be shown here -->
        </div>

        <!-- Profile Tab -->
        <div id="profile" class="tab-content">
            <h1>My Profile</h1>
            <div class="card">
                <form method="POST" enctype="multipart/form-data">
                    <div style="display: flex; gap: 30px; margin-bottom: 25px; align-items: flex-start;">
                        <div style="text-align: center;">
                            <img src="<?php echo h($user['profile_pic']); ?>" alt="Profile" 
                                 style="width: 120px; height: 120px; border-radius: 50%; object-fit: cover; border: 4px solid var(--primary-light);"
                                 onerror="this.src='<?php echo getAssetPath('images/placeholder-teacher.svg'); ?>'">
                            <div style="margin-top: 15px;">
                                <label class="btn-outline btn-sm" style="cursor: pointer;">
                                    <i class="fas fa-camera"></i> Change
                                    <input type="file" name="profile_pic_file" accept="image/*" style="display: none;" onchange="this.form.submit()">
                                </label>
                            </div>
                        </div>
                        <div style="flex: 1;">
                            <div class="form-group">
                                <label>Name</label>
                                <input type="text" name="name" value="<?php echo h($user['name']); ?>" readonly style="background: #f5f5f5;">
                            </div>
                            <div class="form-group">
                                <label>Email</label>
                                <input type="email" value="<?php echo h($user['email']); ?>" readonly style="background: #f5f5f5;">
                            </div>
                            <div class="form-group">
                                <label>Account Type</label>
                                <input type="text" value="Visitor (Upgrade to Student to unlock features)" readonly style="background: #f5f5f5;">
                            </div>
                        </div>
                    </div>
                    <div style="text-align: center; padding: 20px; background: #f9f9f9; border-radius: 8px; margin-top: 20px;">
                        <p style="margin: 0; color: #666;">Upgrade to a student account to edit your full profile and access all features.</p>
                        <a href="payment.php" class="btn-primary" style="margin-top: 15px; display: inline-block;">
                            <i class="fas fa-arrow-up"></i> Upgrade Now
                        </a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
// Tab switching functionality
function switchTab(id) {
    if (event) event.preventDefault();
    
    document.querySelectorAll('.tab-content').forEach(el => el.classList.remove('active'));
    const targetTab = document.getElementById(id);
    if (targetTab) {
        targetTab.classList.add('active');
    }
    
    document.querySelectorAll('.sidebar-menu a').forEach(el => el.classList.remove('active'));
    const activeLink = document.querySelector(`.sidebar-menu a[onclick*="${id}"]`);
    if (activeLink) activeLink.classList.add('active');
    
    // Also check sidebar header button
    const sidebarHeader = document.querySelector('.sidebar-header a');
    if (sidebarHeader && id === 'overview') {
        sidebarHeader.classList.add('active');
    }
    
    // Scroll to top of main content
    const mainContent = document.querySelector('.main');
    if (mainContent) mainContent.scrollTop = 0;
    
    // Update URL hash without triggering page reload
    if (window.location.hash !== '#' + id) {
        window.history.pushState(null, null, '#' + id);
    }
}

document.addEventListener('DOMContentLoaded', function() {
    const tabs = document.querySelectorAll('.tab-link');
    const tabContents = document.querySelectorAll('.tab-content');
    
    tabs.forEach(tab => {
        tab.addEventListener('click', function(e) {
            e.preventDefault();
            const targetId = this.getAttribute('href').substring(1);
            switchTab(targetId);
        });
    });
    
    // Handle URL hash on load
    const hash = window.location.hash.substring(1);
    if (hash && document.getElementById(hash)) {
        switchTab(hash);
    } else {
        // Default to overview if no hash
        const overviewTab = document.getElementById('overview');
        if (overviewTab) {
            overviewTab.classList.add('active');
        }
    }
});

// Handle browser back/forward buttons (hashchange event)
window.addEventListener('hashchange', function() {
    const hash = window.location.hash.substring(1);
    if (hash && document.getElementById(hash)) {
        switchTab(hash);
    }
});

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

