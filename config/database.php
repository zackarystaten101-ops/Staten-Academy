<?php
/**
 * Database Configuration
 * Handles database connection and setup
 */

// Load environment configuration
if (!defined('DB_HOST')) {
    require_once __DIR__ . '/../env.php';
}

$servername = DB_HOST;
$username = DB_USERNAME;
$password = DB_PASSWORD;
$dbname = DB_NAME;

// Create connection
$conn = new mysqli($servername, $username, $password);

// Check connection
if ($conn->connect_error) {
    if (defined('APP_DEBUG') && APP_DEBUG === true) {
        die("Database connection failed: " . $conn->connect_error . "<br>Please check your database credentials in env.php");
    } else {
        die("Database connection failed. Please contact the administrator.");
    }
}

// Create database if not exists
$dbname_escaped = "`" . str_replace("`", "``", $dbname) . "`";
$sql = "CREATE DATABASE IF NOT EXISTS $dbname_escaped";
if ($conn->query($sql) === TRUE) {
    $conn->select_db($dbname);
} else {
    if ($conn->select_db($dbname)) {
        // Database exists, continue
    } else {
        if (defined('APP_DEBUG') && APP_DEBUG === true) {
            die("Error accessing database: " . $conn->error . "<br>Database name: " . htmlspecialchars($dbname));
        } else {
            die("Database error. Please contact the administrator.");
        }
    }
}

// Create users table if not exists
$sql = "CREATE TABLE IF NOT EXISTS users (
    id INT(6) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    email VARCHAR(50) NOT NULL UNIQUE,
    password VARCHAR(255),
    google_id VARCHAR(255),
    name VARCHAR(50),
    role ENUM('visitor', 'student', 'teacher', 'admin') DEFAULT 'visitor',
    dob DATE,
    bio TEXT,
    hours_taught INT DEFAULT 0,
    hours_available INT DEFAULT 0,
    calendly_link VARCHAR(255),
    application_status ENUM('none', 'pending', 'approved', 'rejected') DEFAULT 'none',
    reg_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
)";

if ($conn->query($sql) === FALSE) {
    die("Error creating table: " . $conn->error);
}

// Add new columns if they don't exist (migration)
$cols = $conn->query("SHOW COLUMNS FROM users");
$existing_cols = [];
while($row = $cols->fetch_assoc()) { $existing_cols[] = $row['Field']; }

if (!in_array('dob', $existing_cols)) $conn->query("ALTER TABLE users ADD COLUMN dob DATE AFTER role");
if (!in_array('bio', $existing_cols)) $conn->query("ALTER TABLE users ADD COLUMN bio TEXT AFTER dob");
if (!in_array('hours_taught', $existing_cols)) $conn->query("ALTER TABLE users ADD COLUMN hours_taught INT DEFAULT 0 AFTER bio");
if (!in_array('hours_available', $existing_cols)) $conn->query("ALTER TABLE users ADD COLUMN hours_available INT DEFAULT 0 AFTER hours_taught");
if (!in_array('calendly_link', $existing_cols)) $conn->query("ALTER TABLE users ADD COLUMN calendly_link VARCHAR(255) AFTER hours_available");
if (!in_array('application_status', $existing_cols)) $conn->query("ALTER TABLE users ADD COLUMN application_status ENUM('none', 'pending', 'approved', 'rejected') DEFAULT 'none' AFTER calendly_link");
if (!in_array('profile_pic', $existing_cols)) $conn->query("ALTER TABLE users ADD COLUMN profile_pic VARCHAR(255) DEFAULT 'images/placeholder-teacher.svg' AFTER application_status");
if (!in_array('about_text', $existing_cols)) $conn->query("ALTER TABLE users ADD COLUMN about_text TEXT AFTER profile_pic");
if (!in_array('video_url', $existing_cols)) $conn->query("ALTER TABLE users ADD COLUMN video_url VARCHAR(255) AFTER about_text");
if (!in_array('backup_email', $existing_cols)) $conn->query("ALTER TABLE users ADD COLUMN backup_email VARCHAR(50) AFTER email");
if (!in_array('age', $existing_cols)) $conn->query("ALTER TABLE users ADD COLUMN age INT AFTER dob");
if (!in_array('age_visibility', $existing_cols)) $conn->query("ALTER TABLE users ADD COLUMN age_visibility ENUM('private', 'public') DEFAULT 'private' AFTER age");
if (!in_array('specialty', $existing_cols)) $conn->query("ALTER TABLE users ADD COLUMN specialty VARCHAR(100) DEFAULT NULL AFTER age_visibility");
if (!in_array('hourly_rate', $existing_cols)) $conn->query("ALTER TABLE users ADD COLUMN hourly_rate DECIMAL(10,2) DEFAULT NULL AFTER specialty");

// Phase 1: Visitor role and subscription fields
// Update role ENUM to include 'visitor' if not already updated
$role_check = $conn->query("SHOW COLUMNS FROM users WHERE Field='role'");
if ($role_check && $role_row = $role_check->fetch_assoc()) {
    $role_type = $role_row['Type'];
    if (strpos($role_type, 'visitor') === false) {
        // Need to alter the ENUM - MySQL requires dropping and recreating
        $conn->query("ALTER TABLE users MODIFY COLUMN role ENUM('visitor', 'student', 'teacher', 'admin') DEFAULT 'visitor'");
    }
}

if (!in_array('has_purchased_class', $existing_cols)) $conn->query("ALTER TABLE users ADD COLUMN has_purchased_class BOOLEAN DEFAULT FALSE AFTER role");
if (!in_array('first_purchase_date', $existing_cols)) $conn->query("ALTER TABLE users ADD COLUMN first_purchase_date TIMESTAMP NULL AFTER has_purchased_class");
if (!in_array('subscription_plan_id', $existing_cols)) $conn->query("ALTER TABLE users ADD COLUMN subscription_plan_id INT NULL AFTER first_purchase_date");
if (!in_array('subscription_status', $existing_cols)) $conn->query("ALTER TABLE users ADD COLUMN subscription_status ENUM('none', 'active', 'cancelled', 'expired') DEFAULT 'none' AFTER subscription_plan_id");
if (!in_array('subscription_start_date', $existing_cols)) $conn->query("ALTER TABLE users ADD COLUMN subscription_start_date TIMESTAMP NULL AFTER subscription_status");
if (!in_array('subscription_end_date', $existing_cols)) $conn->query("ALTER TABLE users ADD COLUMN subscription_end_date TIMESTAMP NULL AFTER subscription_start_date");

// Create pending profile updates table
$sql = "CREATE TABLE IF NOT EXISTS pending_updates (
    id INT(6) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT(6) UNSIGNED,
    name VARCHAR(50),
    bio TEXT,
    profile_pic VARCHAR(255),
    about_text TEXT,
    video_url VARCHAR(255),
    requested_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id)
)";
if ($conn->query($sql) === FALSE) { die("Error creating pending updates table: " . $conn->error); }

// Add columns to pending_updates if they don't exist
$pending_cols = $conn->query("SHOW COLUMNS FROM pending_updates");
$existing_pending_cols = [];
while($row = $pending_cols->fetch_assoc()) { $existing_pending_cols[] = $row['Field']; }

if (!in_array('about_text', $existing_pending_cols)) $conn->query("ALTER TABLE pending_updates ADD COLUMN about_text TEXT");
if (!in_array('video_url', $existing_pending_cols)) $conn->query("ALTER TABLE pending_updates ADD COLUMN video_url VARCHAR(255)");

// Create bookings table
$sql = "CREATE TABLE IF NOT EXISTS bookings (
    id INT(6) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    student_id INT(6) UNSIGNED,
    teacher_id INT(6) UNSIGNED,
    booking_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (student_id) REFERENCES users(id),
    FOREIGN KEY (teacher_id) REFERENCES users(id)
)";
if ($conn->query($sql) === FALSE) { die("Error creating bookings table: " . $conn->error); }

// Create messages table
$sql = "CREATE TABLE IF NOT EXISTS messages (
    id INT(6) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    sender_id INT(6) UNSIGNED,
    receiver_id INT(6) UNSIGNED,
    message TEXT,
    sent_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    is_read BOOLEAN DEFAULT FALSE,
    FOREIGN KEY (sender_id) REFERENCES users(id),
    FOREIGN KEY (receiver_id) REFERENCES users(id)
)";
if ($conn->query($sql) === FALSE) { die("Error creating messages table: " . $conn->error); }

// Create classroom materials table
$sql = "CREATE TABLE IF NOT EXISTS classroom_materials (
    id INT(6) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(100) NOT NULL,
    description TEXT,
    file_path VARCHAR(255),
    link_url VARCHAR(255),
    type ENUM('file', 'link', 'video') DEFAULT 'file',
    uploaded_by INT(6) UNSIGNED,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (uploaded_by) REFERENCES users(id)
)";
if ($conn->query($sql) === FALSE) { die("Error creating materials table: " . $conn->error); }

// Create message_threads table (for user-to-user conversations)
$sql = "CREATE TABLE IF NOT EXISTS message_threads (
    id INT(6) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    initiator_id INT(6) UNSIGNED,
    recipient_id INT(6) UNSIGNED,
    last_message_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    thread_type ENUM('user', 'support') DEFAULT 'user',
    UNIQUE KEY unique_thread (initiator_id, recipient_id, thread_type),
    FOREIGN KEY (initiator_id) REFERENCES users(id),
    FOREIGN KEY (recipient_id) REFERENCES users(id)
)";
if ($conn->query($sql) === FALSE) { die("Error creating message_threads table: " . $conn->error); }

// Create support_messages table (for support tickets)
$sql = "CREATE TABLE IF NOT EXISTS support_messages (
    id INT(6) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    sender_id INT(6) UNSIGNED,
    sender_role ENUM('student', 'teacher', 'admin') NOT NULL,
    message TEXT NOT NULL,
    subject VARCHAR(255),
    status ENUM('open', 'read', 'closed') DEFAULT 'open',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (sender_id) REFERENCES users(id)
)";
if ($conn->query($sql) === FALSE) { die("Error creating support_messages table: " . $conn->error); }

// Add columns to messages table if they don't exist (migrate existing table)
$msg_cols = $conn->query("SHOW COLUMNS FROM messages");
$existing_msg_cols = [];
while($row = $msg_cols->fetch_assoc()) { $existing_msg_cols[] = $row['Field']; }

if (!in_array('thread_id', $existing_msg_cols)) $conn->query("ALTER TABLE messages ADD COLUMN thread_id INT(6) UNSIGNED AFTER id");
if (!in_array('message_type', $existing_msg_cols)) $conn->query("ALTER TABLE messages ADD COLUMN message_type ENUM('direct', 'support') DEFAULT 'direct' AFTER message");
if (!in_array('is_read', $existing_msg_cols)) $conn->query("ALTER TABLE messages ADD COLUMN is_read BOOLEAN DEFAULT FALSE AFTER sent_at");

// Add foreign key for thread_id if messages table exists
$check_fk = $conn->query("SELECT CONSTRAINT_NAME FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE WHERE TABLE_NAME='messages' AND COLUMN_NAME='thread_id' AND REFERENCED_TABLE_NAME='message_threads'");
if ($check_fk && $check_fk->num_rows == 0) {
    $conn->query("ALTER TABLE messages ADD FOREIGN KEY (thread_id) REFERENCES message_threads(id) ON DELETE CASCADE");
}

// Create teacher_availability table (for Google Calendar integration)
$sql = "CREATE TABLE IF NOT EXISTS teacher_availability (
    id INT(6) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    teacher_id INT(6) UNSIGNED NOT NULL,
    day_of_week ENUM('Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday') NOT NULL,
    start_time TIME NOT NULL,
    end_time TIME NOT NULL,
    is_available BOOLEAN DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (teacher_id) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE KEY unique_teacher_slot (teacher_id, day_of_week, start_time)
)";
if ($conn->query($sql) === FALSE) { die("Error creating teacher_availability table: " . $conn->error); }

// Create lessons table (for booked lessons)
$sql = "CREATE TABLE IF NOT EXISTS lessons (
    id INT(6) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    teacher_id INT(6) UNSIGNED NOT NULL,
    student_id INT(6) UNSIGNED NOT NULL,
    lesson_date DATE NOT NULL,
    start_time TIME NOT NULL,
    end_time TIME NOT NULL,
    status ENUM('scheduled', 'completed', 'cancelled') DEFAULT 'scheduled',
    google_calendar_event_id VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (teacher_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (student_id) REFERENCES users(id) ON DELETE CASCADE
)";
if ($conn->query($sql) === FALSE) { die("Error creating lessons table: " . $conn->error); }

// Add columns to users table for Google Calendar integration if they don't exist
if (!in_array('google_calendar_token', $existing_cols)) $conn->query("ALTER TABLE users ADD COLUMN google_calendar_token LONGTEXT AFTER video_url");
if (!in_array('google_calendar_token_expiry', $existing_cols)) $conn->query("ALTER TABLE users ADD COLUMN google_calendar_token_expiry DATETIME AFTER google_calendar_token");
if (!in_array('google_calendar_refresh_token', $existing_cols)) $conn->query("ALTER TABLE users ADD COLUMN google_calendar_refresh_token LONGTEXT AFTER google_calendar_token_expiry");

// Phase 2: Course System Tables
// Create course_categories table
$sql = "CREATE TABLE IF NOT EXISTS course_categories (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(100) NOT NULL UNIQUE,
    description TEXT,
    icon VARCHAR(50) DEFAULT 'fa-book',
    color VARCHAR(7) DEFAULT '#004080',
    display_order INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
if ($conn->query($sql) === FALSE) { die("Error creating course_categories table: " . $conn->error); }

// Create courses table
$sql = "CREATE TABLE IF NOT EXISTS courses (
    id INT PRIMARY KEY AUTO_INCREMENT,
    title VARCHAR(255) NOT NULL,
    description TEXT,
    thumbnail_url VARCHAR(500),
    category_id INT,
    difficulty_level ENUM('beginner', 'intermediate', 'advanced') DEFAULT 'beginner',
    duration_minutes INT DEFAULT 0,
    instructor_id INT,
    is_featured BOOLEAN DEFAULT FALSE,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (category_id) REFERENCES course_categories(id) ON DELETE SET NULL,
    FOREIGN KEY (instructor_id) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_category (category_id),
    INDEX idx_instructor (instructor_id),
    INDEX idx_featured (is_featured),
    INDEX idx_active (is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
if ($conn->query($sql) === FALSE) { die("Error creating courses table: " . $conn->error); }

// Create course_lessons table
$sql = "CREATE TABLE IF NOT EXISTS course_lessons (
    id INT PRIMARY KEY AUTO_INCREMENT,
    course_id INT NOT NULL,
    title VARCHAR(255) NOT NULL,
    description TEXT,
    video_url VARCHAR(500),
    video_type ENUM('youtube', 'vimeo', 'self_hosted', 'external') DEFAULT 'youtube',
    duration_minutes INT DEFAULT 0,
    lesson_order INT DEFAULT 0,
    is_preview BOOLEAN DEFAULT FALSE,
    resources JSON,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (course_id) REFERENCES courses(id) ON DELETE CASCADE,
    INDEX idx_course (course_id),
    INDEX idx_order (course_id, lesson_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
if ($conn->query($sql) === FALSE) { die("Error creating course_lessons table: " . $conn->error); }

// Create course_enrollments table
$sql = "CREATE TABLE IF NOT EXISTS course_enrollments (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    course_id INT NOT NULL,
    enrollment_type ENUM('plan', 'purchase', 'free') DEFAULT 'plan',
    plan_id INT NULL,
    enrolled_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    expires_at TIMESTAMP NULL,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (course_id) REFERENCES courses(id) ON DELETE CASCADE,
    UNIQUE KEY unique_enrollment (user_id, course_id),
    INDEX idx_user (user_id),
    INDEX idx_course (course_id),
    INDEX idx_expires (expires_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
if ($conn->query($sql) === FALSE) { die("Error creating course_enrollments table: " . $conn->error); }

// Create user_course_progress table
$sql = "CREATE TABLE IF NOT EXISTS user_course_progress (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    course_id INT NOT NULL,
    lesson_id INT NULL,
    progress_percentage DECIMAL(5,2) DEFAULT 0,
    completed_lessons JSON,
    last_accessed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    completed_at TIMESTAMP NULL,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (course_id) REFERENCES courses(id) ON DELETE CASCADE,
    FOREIGN KEY (lesson_id) REFERENCES course_lessons(id) ON DELETE SET NULL,
    UNIQUE KEY unique_user_course (user_id, course_id),
    INDEX idx_user (user_id),
    INDEX idx_course (course_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
if ($conn->query($sql) === FALSE) { die("Error creating user_course_progress table: " . $conn->error); }

// Create course_reviews table
$sql = "CREATE TABLE IF NOT EXISTS course_reviews (
    id INT PRIMARY KEY AUTO_INCREMENT,
    course_id INT NOT NULL,
    user_id INT NOT NULL,
    rating INT CHECK (rating >= 1 AND rating <= 5),
    review_text TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (course_id) REFERENCES courses(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE KEY unique_review (course_id, user_id),
    INDEX idx_course (course_id),
    INDEX idx_user (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
if ($conn->query($sql) === FALSE) { die("Error creating course_reviews table: " . $conn->error); }

// Phase 3: Subscription Plans Table
$sql = "CREATE TABLE IF NOT EXISTS subscription_plans (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(100) NOT NULL UNIQUE,
    description TEXT,
    price DECIMAL(10,2) NOT NULL,
    stripe_price_id VARCHAR(255),
    classes_per_week INT DEFAULT 0,
    max_course_categories INT DEFAULT 0,
    has_unlimited_classes BOOLEAN DEFAULT FALSE,
    has_all_courses BOOLEAN DEFAULT FALSE,
    features JSON,
    is_active BOOLEAN DEFAULT TRUE,
    display_order INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_active (is_active),
    INDEX idx_order (display_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
if ($conn->query($sql) === FALSE) { die("Error creating subscription_plans table: " . $conn->error); }

// Create user_selected_courses table (tracks which course categories user selected for their plan)
$sql = "CREATE TABLE IF NOT EXISTS user_selected_courses (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    category_id INT NOT NULL,
    plan_id INT NOT NULL,
    selected_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (category_id) REFERENCES course_categories(id) ON DELETE CASCADE,
    FOREIGN KEY (plan_id) REFERENCES subscription_plans(id) ON DELETE CASCADE,
    UNIQUE KEY unique_user_category_plan (user_id, category_id, plan_id),
    INDEX idx_user (user_id),
    INDEX idx_category (category_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
if ($conn->query($sql) === FALSE) { die("Error creating user_selected_courses table: " . $conn->error); }

// Phase 5: Gamification Tables
$sql = "CREATE TABLE IF NOT EXISTS user_points (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    points INT DEFAULT 0,
    total_earned INT DEFAULT 0,
    level INT DEFAULT 1,
    current_streak INT DEFAULT 0,
    longest_streak INT DEFAULT 0,
    last_activity_date DATE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE KEY unique_user (user_id),
    INDEX idx_points (points DESC),
    INDEX idx_level (level DESC)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
if ($conn->query($sql) === FALSE) { die("Error creating user_points table: " . $conn->error); }

$sql = "CREATE TABLE IF NOT EXISTS user_achievements (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    achievement_type VARCHAR(50) NOT NULL,
    achievement_data JSON,
    unlocked_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_user (user_id),
    INDEX idx_type (achievement_type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
if ($conn->query($sql) === FALSE) { die("Error creating user_achievements table: " . $conn->error); }

// Seed initial course categories
$categories = [
    ['name' => 'Grammar Fundamentals', 'description' => 'Basic to advanced grammar rules', 'icon' => 'fa-book', 'color' => '#004080', 'display_order' => 1],
    ['name' => 'Conversational English', 'description' => 'Real-world conversation skills', 'icon' => 'fa-comments', 'color' => '#0b6cf5', 'display_order' => 2],
    ['name' => 'Business English', 'description' => 'Professional communication', 'icon' => 'fa-briefcase', 'color' => '#28a745', 'display_order' => 3],
    ['name' => 'Academic English', 'description' => 'IELTS, TOEFL preparation', 'icon' => 'fa-graduation-cap', 'color' => '#ffc107', 'display_order' => 4],
    ['name' => 'Pronunciation & Accent', 'description' => 'Speaking and pronunciation', 'icon' => 'fa-microphone', 'color' => '#dc3545', 'display_order' => 5],
    ['name' => 'Writing Skills', 'description' => 'Essay writing, emails, reports', 'icon' => 'fa-pen', 'color' => '#6f42c1', 'display_order' => 6],
    ['name' => 'Listening Comprehension', 'description' => 'Audio-based learning', 'icon' => 'fa-headphones', 'color' => '#20c997', 'display_order' => 7],
    ['name' => 'Vocabulary Building', 'description' => 'Word lists and usage', 'icon' => 'fa-book-open', 'color' => '#fd7e14', 'display_order' => 8],
    ['name' => 'Cultural Context', 'description' => 'English-speaking cultures', 'icon' => 'fa-globe', 'color' => '#17a2b8', 'display_order' => 9],
    ['name' => 'Test Preparation', 'description' => 'Exam-specific courses', 'icon' => 'fa-clipboard-check', 'color' => '#e83e8c', 'display_order' => 10]
];

foreach ($categories as $cat) {
    $check = $conn->query("SELECT id FROM course_categories WHERE name = '" . $conn->real_escape_string($cat['name']) . "'");
    if ($check && $check->num_rows == 0) {
        $stmt = $conn->prepare("INSERT INTO course_categories (name, description, icon, color, display_order) VALUES (?, ?, ?, ?, ?)");
        $stmt->bind_param("ssssi", $cat['name'], $cat['description'], $cat['icon'], $cat['color'], $cat['display_order']);
        $stmt->execute();
        $stmt->close();
    }
}

// Seed subscription plans with course limits
$plans = [
    ['name' => 'Single Class', 'description' => 'One-time payment for a single class', 'price' => 30.00, 'classes_per_week' => 0, 'max_course_categories' => 0, 'has_unlimited_classes' => false, 'has_all_courses' => false, 'display_order' => 1],
    ['name' => 'Economy Plan', 'description' => '1 class per week with a certified teacher', 'price' => 85.00, 'classes_per_week' => 1, 'max_course_categories' => 1, 'has_unlimited_classes' => false, 'has_all_courses' => false, 'display_order' => 2],
    ['name' => 'Basic Plan', 'description' => '2 classes per week. Choose your own tutor', 'price' => 240.00, 'classes_per_week' => 2, 'max_course_categories' => 2, 'has_unlimited_classes' => false, 'has_all_courses' => false, 'display_order' => 3],
    ['name' => 'Standard Plan', 'description' => '4 classes per week, extra learning resources', 'price' => 400.00, 'classes_per_week' => 4, 'max_course_categories' => 4, 'has_unlimited_classes' => false, 'has_all_courses' => false, 'display_order' => 4],
    ['name' => 'Premium Plan', 'description' => 'Unlimited classes, exclusive materials', 'price' => 850.00, 'classes_per_week' => 0, 'max_course_categories' => 0, 'has_unlimited_classes' => true, 'has_all_courses' => true, 'display_order' => 5]
];

foreach ($plans as $plan) {
    $check = $conn->query("SELECT id FROM subscription_plans WHERE name = '" . $conn->real_escape_string($plan['name']) . "'");
    if ($check && $check->num_rows == 0) {
        $features = json_encode(['priority_booking', 'progress_tracking', 'certificates']);
        $stmt = $conn->prepare("INSERT INTO subscription_plans (name, description, price, classes_per_week, max_course_categories, has_unlimited_classes, has_all_courses, features, display_order) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("ssdiiissi", $plan['name'], $plan['description'], $plan['price'], $plan['classes_per_week'], $plan['max_course_categories'], $plan['has_unlimited_classes'], $plan['has_all_courses'], $features, $plan['display_order']);
        $stmt->execute();
        $stmt->close();
    }
}

// Make connection globally available
$GLOBALS['conn'] = $conn;
