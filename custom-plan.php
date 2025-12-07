<?php
// Start output buffering to prevent "headers already sent" errors
ob_start();

// Load environment configuration first
if (!defined('DB_HOST')) {
    require_once __DIR__ . '/env.php';
}

// Start session before any output
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check if user is logged in
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'student') {
    ob_end_clean(); // Clear output buffer before redirect
    header('Location: login.php');
    exit;
}

// Load environment and database
if (!defined('DB_HOST')) {
    require_once __DIR__ . '/env.php';
}
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/app/Views/components/dashboard-functions.php';

$user_id = $_SESSION['user_id'];
$user_role = $_SESSION['user_role'] ?? null;

// Fetch course categories
$course_categories = [];
$cat_result = $conn->query("SELECT * FROM course_categories ORDER BY display_order ASC");
if ($cat_result) {
    while ($row = $cat_result->fetch_assoc()) {
        $course_categories[] = $row;
    }
}

// Handle form submission
$errors = [];
$success_message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_custom_plan'])) {
    $hours_per_week = isset($_POST['hours_per_week']) ? (int)$_POST['hours_per_week'] : 1;
    $choose_own_teacher = isset($_POST['choose_own_teacher']) && $_POST['choose_own_teacher'] === 'yes' ? true : false;
    $selected_courses = isset($_POST['courses']) && is_array($_POST['courses']) ? $_POST['courses'] : [];
    $group_classes = isset($_POST['group_classes']) ? (int)$_POST['group_classes'] : 0;
    
    // Validation
    if ($hours_per_week < 1 || $hours_per_week > 40) {
        $errors[] = "Hours per week must be between 1 and 40.";
    }
    
    if ($group_classes < 0 || $group_classes > 10) {
        $errors[] = "Group classes must be between 0 and 10.";
    }
    
    // Calculate pricing
    $hourly_rate = $choose_own_teacher ? 30.00 : 28.00;
    $base_monthly = $hours_per_week * $hourly_rate * 4; // 4 weeks per month
    $courses_extra = count($selected_courses) * 50.00;
    $group_classes_extra = $group_classes * 12.00;
    $total_monthly = $base_monthly + $courses_extra + $group_classes_extra;
    
    if (empty($errors)) {
        // Save to database
        $selected_courses_json = json_encode($selected_courses);
        $extra_courses_count = count($selected_courses);
        $stmt = $conn->prepare("INSERT INTO custom_plans (user_id, hours_per_week, choose_own_teacher, hourly_rate, extra_courses_count, group_classes_count, base_monthly_price, courses_extra, group_classes_extra, total_monthly_price, selected_course_ids, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'draft')");
        
        $choose_own_teacher_int = $choose_own_teacher ? 1 : 0;
        $stmt->bind_param("iiidiiiddds", $user_id, $hours_per_week, $choose_own_teacher_int, $hourly_rate, $extra_courses_count, $group_classes, $base_monthly, $courses_extra, $group_classes_extra, $total_monthly, $selected_courses_json);
        
        if ($stmt->execute()) {
            $custom_plan_id = $conn->insert_id;
            // Redirect to checkout or show success
            ob_end_clean(); // Clear output buffer before redirect
            header("Location: checkout-custom-plan.php?plan_id=" . $custom_plan_id);
            exit;
        } else {
            $errors[] = "Error creating custom plan. Please try again.";
            if (defined('APP_DEBUG') && APP_DEBUG === true) {
                $errors[] = $conn->error;
            }
        }
        $stmt->close();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create Custom Plan - Staten Academy</title>
    <link rel="stylesheet" href="<?php echo getAssetPath('styles.css'); ?>">
    <link rel="stylesheet" href="<?php echo getAssetPath('css/mobile.css'); ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .custom-plan-container {
            max-width: 900px;
            margin: 40px auto;
            padding: 20px;
        }
        .custom-plan-header {
            text-align: center;
            padding: 40px 20px;
            background: linear-gradient(135deg, #0b6cf5 0%, #0056b3 100%);
            color: white;
            border-radius: 12px;
            margin-bottom: 30px;
        }
        .custom-plan-header h1 {
            margin: 0 0 10px 0;
            font-size: 2.5rem;
        }
        .custom-plan-header p {
            margin: 0;
            opacity: 0.9;
        }
        .plan-builder-wrapper {
            display: grid;
            grid-template-columns: 1fr 400px;
            gap: 30px;
            margin-bottom: 30px;
        }
        .plan-options {
            background: white;
            border-radius: 12px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.08);
            padding: 30px;
        }
        .plan-options-section {
            margin-bottom: 35px;
            padding-bottom: 30px;
            border-bottom: 1px solid #e1e5e9;
        }
        .plan-options-section:last-of-type {
            border-bottom: none;
            margin-bottom: 0;
        }
        .plan-options-section h3 {
            color: #004080;
            margin-bottom: 20px;
            font-size: 1.3rem;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .option-card {
            background: #f9fbff;
            border: 2px solid #e1e5e9;
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 15px;
            transition: all 0.3s;
        }
        .option-card:hover {
            border-color: #0b6cf5;
            box-shadow: 0 4px 12px rgba(11, 108, 245, 0.1);
        }
        .option-card.selected {
            border-color: #0b6cf5;
            background: #e1f0ff;
        }
        .hours-selector {
            display: flex;
            align-items: center;
            gap: 15px;
        }
        .hours-selector input[type="number"] {
            width: 100px;
            padding: 15px;
            border: 2px solid #e1e5e9;
            border-radius: 8px;
            font-size: 1.2rem;
            font-weight: bold;
            text-align: center;
            transition: border-color 0.2s;
        }
        .hours-selector input[type="number"]:focus {
            outline: none;
            border-color: #0b6cf5;
        }
        .hours-selector .hours-info {
            flex: 1;
        }
        .hours-selector .hours-info strong {
            display: block;
            font-size: 1.1rem;
            color: #004080;
            margin-bottom: 5px;
        }
        .hours-selector .hours-info small {
            color: #666;
            font-size: 0.9rem;
        }
        .teacher-options {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
        }
        .teacher-option {
            padding: 20px;
            border: 2px solid #e1e5e9;
            border-radius: 12px;
            cursor: pointer;
            transition: all 0.3s;
            text-align: center;
            background: white;
        }
        .teacher-option:hover {
            border-color: #0b6cf5;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(11, 108, 245, 0.15);
        }
        .teacher-option input[type="radio"] {
            display: none;
        }
        .teacher-option.selected {
            border-color: #0b6cf5;
            background: #e1f0ff;
        }
        .teacher-option strong {
            display: block;
            font-size: 1.1rem;
            color: #004080;
            margin-bottom: 8px;
        }
        .teacher-option .price-badge {
            display: inline-block;
            background: #0b6cf5;
            color: white;
            padding: 5px 15px;
            border-radius: 20px;
            font-size: 0.9rem;
            margin-top: 8px;
        }
        .courses-section {
            max-height: 400px;
            overflow-y: auto;
            padding-right: 10px;
        }
        .courses-section::-webkit-scrollbar {
            width: 8px;
        }
        .courses-section::-webkit-scrollbar-track {
            background: #f1f1f1;
            border-radius: 4px;
        }
        .courses-section::-webkit-scrollbar-thumb {
            background: #0b6cf5;
            border-radius: 4px;
        }
        .courses-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(150px, 1fr));
            gap: 12px;
            margin-top: 15px;
        }
        .course-checkbox {
            display: none;
        }
        .course-label {
            display: flex;
            flex-direction: column;
            align-items: center;
            padding: 15px 10px;
            border: 2px solid #e1e5e9;
            border-radius: 10px;
            cursor: pointer;
            transition: all 0.2s;
            text-align: center;
            background: white;
        }
        .course-label:hover {
            border-color: #0b6cf5;
            transform: translateY(-2px);
            box-shadow: 0 2px 8px rgba(11, 108, 245, 0.2);
        }
        .course-checkbox:checked + .course-label {
            border-color: #0b6cf5;
            background: #e1f0ff;
            font-weight: 600;
        }
        .course-label i {
            font-size: 1.5rem;
            margin-bottom: 8px;
        }
        .course-label span {
            font-size: 0.85rem;
            line-height: 1.3;
        }
        .group-classes-input {
            display: flex;
            align-items: center;
            gap: 15px;
        }
        .group-classes-input input[type="number"] {
            width: 100px;
            padding: 12px;
            border: 2px solid #e1e5e9;
            border-radius: 8px;
            font-size: 1.1rem;
            text-align: center;
            transition: border-color 0.2s;
        }
        .group-classes-input input[type="number"]:focus {
            outline: none;
            border-color: #0b6cf5;
        }
        .group-classes-input .input-info {
            flex: 1;
        }
        .price-summary-sticky {
            position: sticky;
            top: 20px;
            background: white;
            border-radius: 12px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.08);
            padding: 25px;
            border: 2px solid #0b6cf5;
        }
        .price-summary-sticky h3 {
            color: #004080;
            margin-bottom: 20px;
            text-align: center;
            font-size: 1.3rem;
        }
        .price-row {
            display: flex;
            justify-content: space-between;
            padding: 12px 0;
            border-bottom: 1px solid #e1e5e9;
        }
        .price-row.total {
            border-top: 2px solid #0b6cf5;
            border-bottom: none;
            margin-top: 15px;
            padding-top: 20px;
            font-size: 1.5rem;
            font-weight: bold;
            color: #0b6cf5;
        }
        .price-row:last-child:not(.total) {
            border-bottom: none;
        }
        .btn-submit {
            width: 100%;
            background: linear-gradient(135deg, #0b6cf5 0%, #0056b3 100%);
            color: white;
            padding: 18px;
            border: none;
            border-radius: 8px;
            font-size: 1.1rem;
            font-weight: bold;
            cursor: pointer;
            transition: transform 0.2s, box-shadow 0.2s;
            margin-top: 20px;
        }
        .btn-submit:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(11, 108, 245, 0.3);
        }
        .error-message {
            background: #fee;
            color: #c33;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            border-left: 4px solid #c33;
        }
        .info-box {
            background: #e1f0ff;
            padding: 12px 15px;
            border-radius: 8px;
            margin-bottom: 15px;
            border-left: 4px solid #0b6cf5;
            font-size: 0.9rem;
        }
        .info-box i {
            color: #0b6cf5;
            margin-right: 8px;
        }
        .section-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
        }
        .section-header h4 {
            margin: 0;
            color: #333;
            font-size: 1rem;
        }
        
        @media (max-width: 1024px) {
            .plan-builder-wrapper {
                grid-template-columns: 1fr;
            }
            .price-summary-sticky {
                position: relative;
                top: 0;
            }
        }
        @media (max-width: 768px) {
            .teacher-options {
                grid-template-columns: 1fr;
            }
            .courses-grid {
                grid-template-columns: repeat(auto-fill, minmax(120px, 1fr));
            }
        }
    </style>
</head>
<body>
    <header class="site-header" role="banner">
        <div class="header-left">
            <a href="index.php"><img src="<?php echo getAssetPath('logo.png'); ?>" alt="Staten Academy logo" class="site-logo"></a>
        </div>
        <div class="header-center">
            <div class="branding">
                <h1 class="site-title">Staten Academy</h1>
            </div>
        </div>
        
        <?php include 'header-user.php'; ?>
        
        <button id="menu-toggle" class="menu-toggle" aria-controls="mobile-menu" aria-expanded="false" aria-label="Open navigation menu">
            <span class="hamburger" aria-hidden="true"></span>
        </button>
        <div id="mobile-menu" class="mobile-menu" role="menu" aria-hidden="true">
            <button class="close-btn" id="mobile-close" aria-label="Close menu">✕</button>
            <a class="nav-btn" href="index.php">Home</a>
            <a class="nav-btn" href="student-dashboard.php">My Dashboard</a>
            <a class="nav-btn" href="logout.php">Logout</a>
        </div>
    </header>
    <div id="mobile-backdrop" class="mobile-backdrop" aria-hidden="true"></div>

    <div class="custom-plan-container">
        <div class="custom-plan-header">
            <h1>Create Your Custom Plan</h1>
            <p>Build a plan tailored to your learning needs</p>
        </div>

        <?php if (!empty($errors)): ?>
            <div class="error-message">
                <i class="fas fa-exclamation-circle"></i>
                <ul style="margin: 10px 0 0 20px; padding: 0;">
                    <?php foreach ($errors as $error): ?>
                        <li><?php echo htmlspecialchars($error); ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <form method="POST" action="" id="customPlanForm">
            <div class="plan-builder-wrapper">
                <!-- Left side: Plan Options -->
                <div class="plan-options">
                    <!-- Hours per week -->
                    <div class="plan-options-section">
                        <h3><i class="fas fa-clock"></i> Hours Per Week</h3>
                        <div class="option-card">
                            <div class="hours-selector">
                                <input type="number" id="hours_per_week" name="hours_per_week" min="1" max="40" value="2" required onchange="calculatePrice()">
                                <div class="hours-info">
                                    <strong>Hours per week</strong>
                                    <small>Select between 1-40 hours. Your monthly base rate is calculated as hours × rate × 4 weeks.</small>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Teacher choice -->
                    <div class="plan-options-section">
                        <h3><i class="fas fa-chalkboard-teacher"></i> Teacher Selection</h3>
                        <div class="teacher-options">
                            <label class="teacher-option selected" onclick="selectTeacher(this)">
                                <input type="radio" name="choose_own_teacher" value="yes" checked>
                                <i class="fas fa-user-check" style="font-size: 2rem; color: #0b6cf5; margin-bottom: 10px;"></i>
                                <strong>Choose My Teacher</strong>
                                <div class="price-badge">$30/hour</div>
                                <small style="display: block; margin-top: 10px; color: #666; font-size: 0.85rem;">Select your preferred teacher from our qualified instructors</small>
                            </label>
                            <label class="teacher-option" onclick="selectTeacher(this)">
                                <input type="radio" name="choose_own_teacher" value="no">
                                <i class="fas fa-user-friends" style="font-size: 2rem; color: #666; margin-bottom: 10px;"></i>
                                <strong>Assign a Teacher</strong>
                                <div class="price-badge" style="background: #666;">$28/hour</div>
                                <small style="display: block; margin-top: 10px; color: #666; font-size: 0.85rem;">We'll match you with the best teacher for your needs</small>
                            </label>
                        </div>
                    </div>

                    <!-- Extra courses -->
                    <div class="plan-options-section">
                        <div class="section-header">
                            <h3><i class="fas fa-book"></i> Extra Courses</h3>
                            <h4 style="color: #0b6cf5; margin: 0;"><span id="selected_courses_count">0</span> selected</h4>
                        </div>
                        <div class="info-box">
                            <i class="fas fa-info-circle"></i>
                            <strong>$50/month per course.</strong> Add specialized courses to enhance your learning experience.
                        </div>
                        <div class="courses-section">
                            <div class="courses-grid">
                                <?php foreach ($course_categories as $course): ?>
                                    <input type="checkbox" id="course_<?php echo $course['id']; ?>" name="courses[]" value="<?php echo $course['id']; ?>" class="course-checkbox" onchange="calculatePrice(); updateCourseCount()">
                                    <label for="course_<?php echo $course['id']; ?>" class="course-label">
                                        <i class="fas <?php echo htmlspecialchars($course['icon']); ?>" style="color: <?php echo htmlspecialchars($course['color']); ?>;"></i>
                                        <span><?php echo htmlspecialchars($course['name']); ?></span>
                                    </label>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>

                    <!-- Group support classes -->
                    <div class="plan-options-section">
                        <h3><i class="fas fa-users"></i> Group Support Classes</h3>
                        <div class="info-box">
                            <i class="fas fa-info-circle"></i>
                            <strong>$12 per class/month.</strong> Join group sessions for extra practice and peer interaction.
                        </div>
                        <div class="option-card">
                            <div class="group-classes-input">
                                <input type="number" id="group_classes" name="group_classes" min="0" max="10" value="0" onchange="calculatePrice()">
                                <div class="input-info">
                                    <strong>Group classes per month</strong>
                                    <small>Select 0-10 additional group support classes for collaborative learning.</small>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Right side: Price Summary (Sticky) -->
                <div class="price-summary-sticky">
                    <h3><i class="fas fa-calculator"></i> Price Summary</h3>
                    <div class="price-row">
                        <span>Base Plan</span>
                        <span id="base_price">$240.00</span>
                    </div>
                    <div class="price-row">
                        <small style="color: #666; display: block; margin-top: 3px;"><span id="summary_hours">2</span> hours/week × $30/hr × 4 weeks</small>
                    </div>
                    <div class="price-row">
                        <span>Extra Courses</span>
                        <span id="courses_price">$0.00</span>
                    </div>
                    <div class="price-row">
                        <small style="color: #666;"><span id="summary_courses">0</span> courses × $50</small>
                    </div>
                    <div class="price-row">
                        <span>Group Classes</span>
                        <span id="group_price">$0.00</span>
                    </div>
                    <div class="price-row">
                        <small style="color: #666;"><span id="summary_group">0</span> classes × $12</small>
                    </div>
                    <div class="price-row total">
                        <span>Total Monthly</span>
                        <span id="total_price">$240.00</span>
                    </div>
                    <button type="submit" name="create_custom_plan" class="btn-submit">
                        <i class="fas fa-check-circle"></i> Create Plan & Continue
                    </button>
                    <p style="text-align: center; margin-top: 15px; color: #666; font-size: 0.85rem;">
                        <i class="fas fa-info-circle"></i> Billed monthly. Cancel anytime.
                    </p>
                </div>
            </div>
        </form>
    </div>

    <footer>
        <p>Contact us: info@statenacademy.com | Phone: +1 234 567 890</p>
        <p>&copy; <?php echo date('Y'); ?> Staten Academy. All rights reserved.</p>
    </footer>
    <script src="<?php echo getAssetPath('js/menu.js'); ?>" defer></script>
    <script>
        function selectTeacher(element) {
            const teacherOptions = document.querySelectorAll('.teacher-option');
            teacherOptions.forEach(opt => opt.classList.remove('selected'));
            element.classList.add('selected');
            element.querySelector('input[type="radio"]').checked = true;
            
            // Update icon colors
            const icons = element.querySelectorAll('i');
            if (element.classList.contains('selected')) {
                icons.forEach(icon => icon.style.color = '#0b6cf5');
            }
            
            // Reset other option icon color
            teacherOptions.forEach(opt => {
                if (!opt.classList.contains('selected')) {
                    opt.querySelectorAll('i').forEach(icon => icon.style.color = '#666');
                }
            });
            
            calculatePrice();
        }

        function updateCourseCount() {
            const selectedCourses = document.querySelectorAll('input[name="courses[]"]:checked').length;
            document.getElementById('selected_courses_count').textContent = selectedCourses;
        }

        function calculatePrice() {
            const hours = parseInt(document.getElementById('hours_per_week').value) || 0;
            const chooseTeacher = document.querySelector('input[name="choose_own_teacher"]:checked').value === 'yes';
            const hourlyRate = chooseTeacher ? 30 : 28;
            const baseMonthly = hours * hourlyRate * 4; // 4 weeks per month
            
            const selectedCourses = document.querySelectorAll('input[name="courses[]"]:checked').length;
            const coursesExtra = selectedCourses * 50;
            
            const groupClasses = parseInt(document.getElementById('group_classes').value) || 0;
            const groupExtra = groupClasses * 12;
            
            const total = baseMonthly + coursesExtra + groupExtra;
            
            // Update display
            document.getElementById('summary_hours').textContent = hours;
            document.getElementById('base_price').textContent = '$' + baseMonthly.toFixed(2);
            
            // Update rate display
            const rateDisplays = document.querySelectorAll('.price-row small');
            if (rateDisplays.length > 0) {
                rateDisplays[0].innerHTML = '<span id="summary_hours_calc">' + hours + '</span> hours/week × $<span id="summary_rate">' + hourlyRate + '</span>/hr × 4 weeks';
            }
            
            document.getElementById('summary_courses').textContent = selectedCourses;
            document.getElementById('courses_price').textContent = '$' + coursesExtra.toFixed(2);
            document.getElementById('summary_group').textContent = groupClasses;
            document.getElementById('group_price').textContent = '$' + groupExtra.toFixed(2);
            document.getElementById('total_price').textContent = '$' + total.toFixed(2);
        }

        // Initialize teacher selection styling
        document.querySelectorAll('.teacher-option').forEach(opt => {
            if (opt.querySelector('input[type="radio"]').checked) {
                opt.classList.add('selected');
                opt.querySelectorAll('i').forEach(icon => icon.style.color = '#0b6cf5');
            }
        });

        // Calculate on page load and form changes
        document.getElementById('customPlanForm').addEventListener('change', calculatePrice);
        document.getElementById('customPlanForm').addEventListener('input', calculatePrice);
        calculatePrice();
        updateCourseCount();
    </script>
</body>
</html>
<?php
// End output buffering
ob_end_flush();
?>
