<?php
// Load environment configuration
if (!defined('DB_HOST')) {
    require_once __DIR__ . '/env.php';
}

$servername = DB_HOST;
$username = DB_USERNAME;
$password = DB_PASSWORD;
$dbname = DB_NAME;

// Create connection
$conn = new mysqli($servername, $username, $password);

// Check connection
if ($conn->connect_error) {
    // Better error message for production
    if (defined('APP_DEBUG') && APP_DEBUG === true) {
        die("Database connection failed: " . $conn->connect_error . "<br>Please check your database credentials in env.php");
    } else {
        die("Database connection failed. Please contact the administrator.");
    }
}

// Create database if not exists
// Escape database name with backticks to handle spaces and special characters
$dbname_escaped = "`" . str_replace("`", "``", $dbname) . "`";
$sql = "CREATE DATABASE IF NOT EXISTS $dbname_escaped";
if ($conn->query($sql) === TRUE) {
    $conn->select_db($dbname);
} else {
    // Try to select existing database instead of dying
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
    role ENUM('visitor', 'new_student', 'student', 'teacher', 'admin') DEFAULT 'visitor',
    dob DATE,
    bio TEXT,
    hours_taught INT DEFAULT 0,
    hours_available INT DEFAULT 0,
    calendly_link VARCHAR(255),
    application_status ENUM('none', 'pending', 'approved', 'rejected') DEFAULT 'none',
    reg_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

if ($conn->query($sql) === FALSE) {
    if (defined('APP_DEBUG') && APP_DEBUG === true) {
        error_log("Error creating users table: " . $conn->error);
        die("Error creating table: " . $conn->error);
    } else {
        error_log("Error creating users table: " . $conn->error);
        die("Database error. Please contact the administrator.");
    }
}

// Ensure users table is using InnoDB engine (required for foreign keys)
$engine_check = $conn->query("SHOW TABLE STATUS WHERE Name='users'");
if ($engine_check && $engine_row = $engine_check->fetch_assoc()) {
    if (strtoupper($engine_row['Engine']) !== 'INNODB') {
        $conn->query("ALTER TABLE users ENGINE=InnoDB");
    }
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
if (!in_array('learning_track', $existing_cols)) $conn->query("ALTER TABLE users ADD COLUMN learning_track ENUM('kids', 'adults', 'coding') NULL AFTER hourly_rate");
// Note: assigned_teacher_id is deprecated - migration will remove it
if (!in_array('assigned_teacher_id', $existing_cols)) $conn->query("ALTER TABLE users ADD COLUMN assigned_teacher_id INT(6) UNSIGNED NULL AFTER learning_track");
if (!in_array('preferred_category', $existing_cols)) $conn->query("ALTER TABLE users ADD COLUMN preferred_category ENUM('young_learners', 'adults', 'coding') NULL AFTER learning_track");
if (!in_array('trial_used', $existing_cols)) $conn->query("ALTER TABLE users ADD COLUMN trial_used BOOLEAN DEFAULT FALSE AFTER preferred_category");
if (!in_array('last_active', $existing_cols)) $conn->query("ALTER TABLE users ADD COLUMN last_active TIMESTAMP NULL AFTER reg_date");
// Preply-style calendar features (check if already added later in file)
if (!in_array('default_buffer_minutes', $existing_cols)) $conn->query("ALTER TABLE users ADD COLUMN default_buffer_minutes INT DEFAULT 15 AFTER assigned_teacher_id");
if (!in_array('preferred_meeting_type', $existing_cols)) $conn->query("ALTER TABLE users ADD COLUMN preferred_meeting_type ENUM('zoom', 'google_meet', 'other') DEFAULT 'zoom' AFTER default_buffer_minutes");
if (!in_array('zoom_link', $existing_cols)) $conn->query("ALTER TABLE users ADD COLUMN zoom_link VARCHAR(500) NULL AFTER preferred_meeting_type");
if (!in_array('google_meet_link', $existing_cols)) $conn->query("ALTER TABLE users ADD COLUMN google_meet_link VARCHAR(500) NULL AFTER zoom_link");
// Add plan_id column if it doesn't exist
if (!in_array('plan_id', $existing_cols) && !in_array('subscription_plan_id', $existing_cols)) {
    $add_col_result = $conn->query("ALTER TABLE users ADD COLUMN plan_id INT NULL AFTER assigned_teacher_id");
    if ($add_col_result) {
        // Refresh the existing_cols array to include the new column
        $existing_cols[] = 'plan_id';
    }
} elseif (!in_array('plan_id', $existing_cols) && in_array('subscription_plan_id', $existing_cols)) {
    // If subscription_plan_id exists but plan_id doesn't, add plan_id as an alias column
    $add_col_result = $conn->query("ALTER TABLE users ADD COLUMN plan_id INT NULL AFTER assigned_teacher_id");
    if ($add_col_result) {
        $existing_cols[] = 'plan_id';
    }
}

// Phase 1: Visitor role and subscription fields
// Update role ENUM to include 'visitor' if not already updated
$role_check = $conn->query("SHOW COLUMNS FROM users WHERE Field='role'");
if ($role_check && $role_row = $role_check->fetch_assoc()) {
    $role_type = $role_row['Type'];
    if (strpos($role_type, 'new_student') === false) {
        // Need to alter the ENUM - MySQL requires dropping and recreating
        $conn->query("ALTER TABLE users MODIFY COLUMN role ENUM('visitor', 'new_student', 'student', 'teacher', 'admin') DEFAULT 'visitor'");
    }
}

if (!in_array('has_purchased_class', $existing_cols)) $conn->query("ALTER TABLE users ADD COLUMN has_purchased_class BOOLEAN DEFAULT FALSE AFTER role");
if (!in_array('first_purchase_date', $existing_cols)) $conn->query("ALTER TABLE users ADD COLUMN first_purchase_date TIMESTAMP NULL AFTER has_purchased_class");
if (!in_array('subscription_plan_id', $existing_cols)) $conn->query("ALTER TABLE users ADD COLUMN subscription_plan_id INT NULL AFTER first_purchase_date");
if (!in_array('subscription_status', $existing_cols)) $conn->query("ALTER TABLE users ADD COLUMN subscription_status ENUM('none', 'active', 'cancelled', 'expired') DEFAULT 'none' AFTER subscription_plan_id");
if (!in_array('subscription_start_date', $existing_cols)) $conn->query("ALTER TABLE users ADD COLUMN subscription_start_date TIMESTAMP NULL AFTER subscription_status");
if (!in_array('subscription_end_date', $existing_cols)) $conn->query("ALTER TABLE users ADD COLUMN subscription_end_date TIMESTAMP NULL AFTER subscription_start_date");

// Credit system columns
if (!in_array('stripe_customer_id', $existing_cols)) $conn->query("ALTER TABLE users ADD COLUMN stripe_customer_id VARCHAR(255) NULL");
if (!in_array('credits_balance', $existing_cols)) $conn->query("ALTER TABLE users ADD COLUMN credits_balance INT DEFAULT 0");
if (!in_array('credits_gifted', $existing_cols)) $conn->query("ALTER TABLE users ADD COLUMN credits_gifted INT DEFAULT 0");
if (!in_array('active_subscription_id', $existing_cols)) $conn->query("ALTER TABLE users ADD COLUMN active_subscription_id VARCHAR(255) NULL");
if (!in_array('subscription_billing_cycle_date', $existing_cols)) $conn->query("ALTER TABLE users ADD COLUMN subscription_billing_cycle_date INT NULL");
if (!in_array('subscription_payment_failed', $existing_cols)) $conn->query("ALTER TABLE users ADD COLUMN subscription_payment_failed BOOLEAN DEFAULT FALSE");

// Create pending profile updates table
$sql = "CREATE TABLE IF NOT EXISTS pending_updates (
    id INT(6) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT(6) UNSIGNED,
    name VARCHAR(50),
    bio TEXT,
    profile_pic VARCHAR(255),
    about_text TEXT,
    video_url VARCHAR(255),
    requested_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
$conn->query($sql);

// Add foreign key separately
$fk_check = $conn->query("SELECT CONSTRAINT_NAME FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE WHERE TABLE_NAME='pending_updates' AND COLUMN_NAME='user_id' AND REFERENCED_TABLE_NAME='users'");
if (!$fk_check || $fk_check->num_rows == 0) {
    $conn->query("ALTER TABLE pending_updates ADD CONSTRAINT fk_pending_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE");
}

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
    booking_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
$conn->query($sql);

// Add foreign keys separately
$fk_check = $conn->query("SELECT CONSTRAINT_NAME FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE WHERE TABLE_NAME='bookings' AND COLUMN_NAME='student_id' AND REFERENCED_TABLE_NAME='users'");
if (!$fk_check || $fk_check->num_rows == 0) {
    $conn->query("ALTER TABLE bookings ADD CONSTRAINT fk_bookings_student FOREIGN KEY (student_id) REFERENCES users(id) ON DELETE CASCADE");
}
$fk_check = $conn->query("SELECT CONSTRAINT_NAME FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE WHERE TABLE_NAME='bookings' AND COLUMN_NAME='teacher_id' AND REFERENCED_TABLE_NAME='users'");
if (!$fk_check || $fk_check->num_rows == 0) {
    $conn->query("ALTER TABLE bookings ADD CONSTRAINT fk_bookings_teacher FOREIGN KEY (teacher_id) REFERENCES users(id) ON DELETE CASCADE");
}

// Create messages table
$sql = "CREATE TABLE IF NOT EXISTS messages (
    id INT(6) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    sender_id INT(6) UNSIGNED,
    receiver_id INT(6) UNSIGNED,
    message TEXT,
    sent_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    is_read BOOLEAN DEFAULT FALSE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
$conn->query($sql);

// Add foreign keys separately
$fk_check = $conn->query("SELECT CONSTRAINT_NAME FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE WHERE TABLE_NAME='messages' AND COLUMN_NAME='sender_id' AND REFERENCED_TABLE_NAME='users'");
if (!$fk_check || $fk_check->num_rows == 0) {
    $conn->query("ALTER TABLE messages ADD CONSTRAINT fk_messages_sender FOREIGN KEY (sender_id) REFERENCES users(id) ON DELETE CASCADE");
}
$fk_check = $conn->query("SELECT CONSTRAINT_NAME FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE WHERE TABLE_NAME='messages' AND COLUMN_NAME='receiver_id' AND REFERENCED_TABLE_NAME='users'");
if (!$fk_check || $fk_check->num_rows == 0) {
    $conn->query("ALTER TABLE messages ADD CONSTRAINT fk_messages_receiver FOREIGN KEY (receiver_id) REFERENCES users(id) ON DELETE CASCADE");
}

// Create classroom materials table
$sql = "CREATE TABLE IF NOT EXISTS classroom_materials (
    id INT(6) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(100) NOT NULL,
    description TEXT,
    file_path VARCHAR(255),
    link_url VARCHAR(255),
    type ENUM('file', 'link', 'video') DEFAULT 'file',
    uploaded_by INT(6) UNSIGNED,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
$conn->query($sql);

// Add foreign key separately
$fk_check = $conn->query("SELECT CONSTRAINT_NAME FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE WHERE TABLE_NAME='classroom_materials' AND COLUMN_NAME='uploaded_by' AND REFERENCED_TABLE_NAME='users'");
if (!$fk_check || $fk_check->num_rows == 0) {
    $conn->query("ALTER TABLE classroom_materials ADD CONSTRAINT fk_materials_uploaded FOREIGN KEY (uploaded_by) REFERENCES users(id) ON DELETE CASCADE");
}

// Add soft delete columns to classroom_materials if they don't exist
$materials_cols = $conn->query("SHOW COLUMNS FROM classroom_materials");
$existing_materials_cols = [];
if ($materials_cols) {
    while($row = $materials_cols->fetch_assoc()) { $existing_materials_cols[] = $row['Field']; }
}
if (!in_array('is_deleted', $existing_materials_cols)) {
    $conn->query("ALTER TABLE classroom_materials ADD COLUMN is_deleted BOOLEAN DEFAULT 0 AFTER uploaded_by");
}
if (!in_array('deleted_at', $existing_materials_cols)) {
    $conn->query("ALTER TABLE classroom_materials ADD COLUMN deleted_at TIMESTAMP NULL AFTER is_deleted");
}
if (!in_array('category', $existing_materials_cols)) {
    $conn->query("ALTER TABLE classroom_materials ADD COLUMN category ENUM('general', 'kids', 'adults', 'coding') DEFAULT 'general' AFTER is_deleted");
}
if (!in_array('tags', $existing_materials_cols)) {
    $conn->query("ALTER TABLE classroom_materials ADD COLUMN tags VARCHAR(255) NULL AFTER category");
}
if (!in_array('usage_count', $existing_materials_cols)) {
    $conn->query("ALTER TABLE classroom_materials ADD COLUMN usage_count INT DEFAULT 0 AFTER tags");
}

// Create teacher_resources table if it doesn't exist
$tables = $conn->query("SHOW TABLES LIKE 'teacher_resources'");
if (!$tables || $tables->num_rows == 0) {
    $sql = "CREATE TABLE IF NOT EXISTS teacher_resources (
        id INT(6) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        teacher_id INT(6) UNSIGNED NOT NULL,
        title VARCHAR(255) NOT NULL,
        description TEXT,
        file_path VARCHAR(255) DEFAULT NULL,
        file_type VARCHAR(50) DEFAULT NULL,
        external_url VARCHAR(500) DEFAULT NULL,
        category VARCHAR(100) DEFAULT 'general',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_teacher (teacher_id),
        FOREIGN KEY (teacher_id) REFERENCES users(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
    $conn->query($sql);
} else {
    // Add soft delete columns to teacher_resources if they don't exist
    $resources_cols = $conn->query("SHOW COLUMNS FROM teacher_resources");
    $existing_resources_cols = [];
    if ($resources_cols) {
        while($row = $resources_cols->fetch_assoc()) { $existing_resources_cols[] = $row['Field']; }
    }
    if (!in_array('is_deleted', $existing_resources_cols)) {
        $conn->query("ALTER TABLE teacher_resources ADD COLUMN is_deleted BOOLEAN DEFAULT 0 AFTER category");
    }
    if (!in_array('deleted_at', $existing_resources_cols)) {
        $conn->query("ALTER TABLE teacher_resources ADD COLUMN deleted_at TIMESTAMP NULL AFTER is_deleted");
    }
}

// Create message_threads table (for user-to-user conversations)
$sql = "CREATE TABLE IF NOT EXISTS message_threads (
    id INT(6) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    initiator_id INT(6) UNSIGNED,
    recipient_id INT(6) UNSIGNED,
    last_message_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    thread_type ENUM('user', 'support') DEFAULT 'user',
    UNIQUE KEY unique_thread (initiator_id, recipient_id, thread_type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
$conn->query($sql);

// Add foreign keys separately
$fk_check = $conn->query("SELECT CONSTRAINT_NAME FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE WHERE TABLE_NAME='message_threads' AND COLUMN_NAME='initiator_id' AND REFERENCED_TABLE_NAME='users'");
if (!$fk_check || $fk_check->num_rows == 0) {
    $conn->query("ALTER TABLE message_threads ADD CONSTRAINT fk_threads_initiator FOREIGN KEY (initiator_id) REFERENCES users(id) ON DELETE CASCADE");
}
$fk_check = $conn->query("SELECT CONSTRAINT_NAME FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE WHERE TABLE_NAME='message_threads' AND COLUMN_NAME='recipient_id' AND REFERENCED_TABLE_NAME='users'");
if (!$fk_check || $fk_check->num_rows == 0) {
    $conn->query("ALTER TABLE message_threads ADD CONSTRAINT fk_threads_recipient FOREIGN KEY (recipient_id) REFERENCES users(id) ON DELETE CASCADE");
}

// Create support_messages table (for support tickets)
$sql = "CREATE TABLE IF NOT EXISTS support_messages (
    id INT(6) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    sender_id INT(6) UNSIGNED,
    sender_role ENUM('student', 'teacher', 'admin') NOT NULL,
    message TEXT NOT NULL,
    subject VARCHAR(255),
    status ENUM('open', 'read', 'closed') DEFAULT 'open',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
$conn->query($sql);

// Add foreign key separately
$fk_check = $conn->query("SELECT CONSTRAINT_NAME FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE WHERE TABLE_NAME='support_messages' AND COLUMN_NAME='sender_id' AND REFERENCED_TABLE_NAME='users'");
if (!$fk_check || $fk_check->num_rows == 0) {
    $conn->query("ALTER TABLE support_messages ADD CONSTRAINT fk_support_sender FOREIGN KEY (sender_id) REFERENCES users(id) ON DELETE CASCADE");
}

// Add columns to messages table if they don't exist (migrate existing table)
$msg_cols = $conn->query("SHOW COLUMNS FROM messages");
$existing_msg_cols = [];
while($row = $msg_cols->fetch_assoc()) { $existing_msg_cols[] = $row['Field']; }

if (!in_array('thread_id', $existing_msg_cols)) $conn->query("ALTER TABLE messages ADD COLUMN thread_id INT(6) UNSIGNED AFTER id");
if (!in_array('message_type', $existing_msg_cols)) $conn->query("ALTER TABLE messages ADD COLUMN message_type ENUM('direct', 'support') DEFAULT 'direct' AFTER message");
if (!in_array('attachment_path', $existing_msg_cols)) $conn->query("ALTER TABLE messages ADD COLUMN attachment_path VARCHAR(500) DEFAULT NULL AFTER message_type");
if (!in_array('attachment_type', $existing_msg_cols)) $conn->query("ALTER TABLE messages ADD COLUMN attachment_type ENUM('image', 'video', 'file') DEFAULT NULL AFTER attachment_path");
if (!in_array('is_read', $existing_msg_cols)) $conn->query("ALTER TABLE messages ADD COLUMN is_read BOOLEAN DEFAULT FALSE AFTER sent_at");
if (!in_array('read_at', $existing_msg_cols)) $conn->query("ALTER TABLE messages ADD COLUMN read_at TIMESTAMP NULL AFTER is_read");

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
    UNIQUE KEY unique_teacher_slot (teacher_id, day_of_week, start_time)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
$conn->query($sql);

// Add Preply-style columns to teacher_availability
$avail_cols = $conn->query("SHOW COLUMNS FROM teacher_availability");
$existing_avail_cols = [];
if ($avail_cols) {
    while($row = $avail_cols->fetch_assoc()) { $existing_avail_cols[] = $row['Field']; }
}
if (!in_array('buffer_time_minutes', $existing_avail_cols)) $conn->query("ALTER TABLE teacher_availability ADD COLUMN buffer_time_minutes INT DEFAULT 15 AFTER is_available");
if (!in_array('specific_date', $existing_avail_cols)) $conn->query("ALTER TABLE teacher_availability ADD COLUMN specific_date DATE NULL AFTER day_of_week");

// Add foreign key separately
$fk_check = $conn->query("SELECT CONSTRAINT_NAME FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE WHERE TABLE_NAME='teacher_availability' AND COLUMN_NAME='teacher_id' AND REFERENCED_TABLE_NAME='users'");
if (!$fk_check || $fk_check->num_rows == 0) {
    $conn->query("ALTER TABLE teacher_availability ADD CONSTRAINT fk_availability_teacher FOREIGN KEY (teacher_id) REFERENCES users(id) ON DELETE CASCADE");
}

// =====================================================
// TEACHER_AVAILABILITY_SLOTS TABLE
// Available time slots per teacher (more detailed than teacher_availability)
// =====================================================
$sql = "CREATE TABLE IF NOT EXISTS teacher_availability_slots (
    id INT(6) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    teacher_id INT(6) UNSIGNED NOT NULL,
    day_of_week ENUM('Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday') NOT NULL,
    start_time TIME NOT NULL,
    end_time TIME NOT NULL,
    timezone VARCHAR(50) DEFAULT 'UTC',
    is_recurring BOOLEAN DEFAULT TRUE,
    specific_date DATE DEFAULT NULL,
    is_available BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_teacher (teacher_id),
    INDEX idx_day_time (day_of_week, start_time),
    INDEX idx_specific_date (specific_date),
    INDEX idx_available (is_available),
    FOREIGN KEY (teacher_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
$conn->query($sql);

// =====================================================
// TRIAL_LESSONS TABLE
// Track trial lesson usage (one per student)
// =====================================================
$sql = "CREATE TABLE IF NOT EXISTS trial_lessons (
    id INT(6) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    student_id INT(6) UNSIGNED NOT NULL,
    teacher_id INT(6) UNSIGNED NOT NULL,
    lesson_id INT(6) UNSIGNED DEFAULT NULL,
    stripe_payment_id VARCHAR(255) NOT NULL,
    used_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_student_trial (student_id),
    INDEX idx_student (student_id),
    INDEX idx_teacher (teacher_id),
    INDEX idx_lesson (lesson_id),
    INDEX idx_stripe_payment (stripe_payment_id),
    FOREIGN KEY (student_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (teacher_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (lesson_id) REFERENCES lessons(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
$conn->query($sql);

// =====================================================
// TEACHER_CATEGORIES TABLE
// Links teachers to categories (young_learners, adults, coding)
// =====================================================
$sql = "CREATE TABLE IF NOT EXISTS teacher_categories (
    id INT(6) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    teacher_id INT(6) UNSIGNED NOT NULL,
    category ENUM('young_learners', 'adults', 'coding') NOT NULL,
    is_active BOOLEAN DEFAULT TRUE,
    approved_by INT(6) UNSIGNED NULL,
    approved_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY unique_teacher_category (teacher_id, category),
    INDEX idx_teacher (teacher_id),
    INDEX idx_category (category),
    INDEX idx_active (is_active),
    INDEX idx_approved_by (approved_by),
    FOREIGN KEY (teacher_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (approved_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
$conn->query($sql);

// Add approved_by and approved_at columns if they don't exist (migration)
$cols = $conn->query("SHOW COLUMNS FROM teacher_categories");
$existing_cols = [];
if ($cols) {
    while($row = $cols->fetch_assoc()) { 
        $existing_cols[] = $row['Field']; 
    }
}

if (!in_array('approved_by', $existing_cols)) {
    $conn->query("ALTER TABLE teacher_categories ADD COLUMN approved_by INT(6) UNSIGNED NULL AFTER is_active");
    $conn->query("ALTER TABLE teacher_categories ADD INDEX idx_approved_by (approved_by)");
    $fk_check = $conn->query("SELECT CONSTRAINT_NAME FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE WHERE TABLE_NAME='teacher_categories' AND COLUMN_NAME='approved_by' AND REFERENCED_TABLE_NAME='users'");
    if (!$fk_check || $fk_check->num_rows == 0) {
        $conn->query("ALTER TABLE teacher_categories ADD CONSTRAINT fk_teacher_categories_approved_by FOREIGN KEY (approved_by) REFERENCES users(id) ON DELETE SET NULL");
    }
}

if (!in_array('approved_at', $existing_cols)) {
    $conn->query("ALTER TABLE teacher_categories ADD COLUMN approved_at TIMESTAMP NULL AFTER approved_by");
}

// =====================================================
// STUDENT_WALLET TABLE
// Wallet balance tracking (PHP side, syncs with TypeScript)
// =====================================================
$sql = "CREATE TABLE IF NOT EXISTS student_wallet (
    id INT(6) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    student_id INT(6) UNSIGNED NOT NULL UNIQUE,
    balance DECIMAL(10,2) DEFAULT 0.00,
    trial_credits INT DEFAULT 0,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_student (student_id),
    FOREIGN KEY (student_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
$conn->query($sql);

// =====================================================
// WALLET_TRANSACTIONS TABLE
// Transaction history for wallet operations
// =====================================================
$sql = "CREATE TABLE IF NOT EXISTS wallet_transactions (
    id INT(6) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    student_id INT(6) UNSIGNED NOT NULL,
    type ENUM('purchase', 'deduction', 'refund', 'trial', 'adjustment') NOT NULL,
    amount DECIMAL(10,2) NOT NULL,
    stripe_payment_id VARCHAR(255) DEFAULT NULL,
    reference_id VARCHAR(255) DEFAULT NULL,
    lesson_id INT(6) UNSIGNED DEFAULT NULL,
    description TEXT,
    status ENUM('pending', 'confirmed', 'failed', 'cancelled') DEFAULT 'confirmed',
    idempotency_key VARCHAR(255) DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_student (student_id),
    INDEX idx_type (type),
    INDEX idx_status (status),
    INDEX idx_stripe_payment (stripe_payment_id),
    INDEX idx_reference (reference_id),
    INDEX idx_lesson (lesson_id),
    INDEX idx_idempotency (idempotency_key),
    INDEX idx_created (created_at),
    FOREIGN KEY (student_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
$conn->query($sql);

// Add status and idempotency_key columns if they don't exist
$wallet_trans_cols = $conn->query("SHOW COLUMNS FROM wallet_transactions");
$existing_wallet_trans_cols = [];
if ($wallet_trans_cols) {
    while($row = $wallet_trans_cols->fetch_assoc()) { 
        $existing_wallet_trans_cols[] = $row['Field']; 
    }
}

if (!in_array('status', $existing_wallet_trans_cols)) {
    $conn->query("ALTER TABLE wallet_transactions ADD COLUMN status ENUM('pending', 'confirmed', 'failed', 'cancelled') DEFAULT 'confirmed' AFTER description");
}
if (!in_array('idempotency_key', $existing_wallet_trans_cols)) {
    $conn->query("ALTER TABLE wallet_transactions ADD COLUMN idempotency_key VARCHAR(255) DEFAULT NULL AFTER status");
    $conn->query("ALTER TABLE wallet_transactions ADD INDEX idx_idempotency (idempotency_key)");
}
if (!in_array('lesson_id', $existing_wallet_trans_cols)) {
    $conn->query("ALTER TABLE wallet_transactions ADD COLUMN lesson_id INT(6) UNSIGNED DEFAULT NULL AFTER reference_id");
    $conn->query("ALTER TABLE wallet_transactions ADD INDEX idx_lesson (lesson_id)");
}
if (!in_array('updated_at', $existing_wallet_trans_cols)) {
    $conn->query("ALTER TABLE wallet_transactions ADD COLUMN updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP AFTER created_at");
}

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
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
$conn->query($sql);

// Add new columns to lessons table for enhanced calendar features
$lessons_cols = $conn->query("SHOW COLUMNS FROM lessons");
$existing_lessons_cols = [];
if ($lessons_cols) {
    while($row = $lessons_cols->fetch_assoc()) { $existing_lessons_cols[] = $row['Field']; }
}
if (!in_array('recurring_lesson_id', $existing_lessons_cols)) $conn->query("ALTER TABLE lessons ADD COLUMN recurring_lesson_id INT(6) UNSIGNED NULL AFTER id");
if (!in_array('lesson_type', $existing_lessons_cols)) $conn->query("ALTER TABLE lessons ADD COLUMN lesson_type ENUM('single', 'recurring', 'series') DEFAULT 'single' AFTER status");
if (!in_array('color_code', $existing_lessons_cols)) $conn->query("ALTER TABLE lessons ADD COLUMN color_code VARCHAR(7) DEFAULT '#0b6cf5' AFTER lesson_type");
if (!in_array('series_start_date', $existing_lessons_cols)) $conn->query("ALTER TABLE lessons ADD COLUMN series_start_date DATE NULL AFTER color_code");
if (!in_array('series_end_date', $existing_lessons_cols)) $conn->query("ALTER TABLE lessons ADD COLUMN series_end_date DATE NULL AFTER series_end_date");
if (!in_array('series_frequency_weeks', $existing_lessons_cols)) $conn->query("ALTER TABLE lessons ADD COLUMN series_frequency_weeks INT DEFAULT 1 AFTER series_end_date");
// Preply-style features
if (!in_array('meeting_link', $existing_lessons_cols)) $conn->query("ALTER TABLE lessons ADD COLUMN meeting_link VARCHAR(500) NULL AFTER google_calendar_event_id");
if (!in_array('meeting_type', $existing_lessons_cols)) $conn->query("ALTER TABLE lessons ADD COLUMN meeting_type ENUM('zoom', 'google_meet', 'other') DEFAULT 'zoom' AFTER meeting_link");
if (!in_array('buffer_time_minutes', $existing_lessons_cols)) $conn->query("ALTER TABLE lessons ADD COLUMN buffer_time_minutes INT DEFAULT 15 AFTER meeting_type");
if (!in_array('reschedule_policy_hours', $existing_lessons_cols)) $conn->query("ALTER TABLE lessons ADD COLUMN reschedule_policy_hours INT DEFAULT 24 AFTER buffer_time_minutes");
if (!in_array('cancel_policy_hours', $existing_lessons_cols)) $conn->query("ALTER TABLE lessons ADD COLUMN cancel_policy_hours INT DEFAULT 24 AFTER reschedule_policy_hours");
if (!in_array('reminder_sent', $existing_lessons_cols)) $conn->query("ALTER TABLE lessons ADD COLUMN reminder_sent BOOLEAN DEFAULT FALSE AFTER cancel_policy_hours");
if (!in_array('rescheduled_from', $existing_lessons_cols)) $conn->query("ALTER TABLE lessons ADD COLUMN rescheduled_from INT(6) UNSIGNED NULL AFTER reminder_sent");
if (!in_array('cancellation_reason', $existing_lessons_cols)) $conn->query("ALTER TABLE lessons ADD COLUMN cancellation_reason TEXT NULL AFTER rescheduled_from");
// Attendance tracking fields
if (!in_array('attendance_status', $existing_lessons_cols)) $conn->query("ALTER TABLE lessons ADD COLUMN attendance_status ENUM('attended', 'no_show', 'cancelled') NULL AFTER status");
if (!in_array('student_notes', $existing_lessons_cols)) $conn->query("ALTER TABLE lessons ADD COLUMN student_notes TEXT NULL AFTER attendance_status");
if (!in_array('completion_notes', $existing_lessons_cols)) $conn->query("ALTER TABLE lessons ADD COLUMN completion_notes TEXT NULL AFTER student_notes");
if (!in_array('confirmed_at', $existing_lessons_cols)) $conn->query("ALTER TABLE lessons ADD COLUMN confirmed_at TIMESTAMP NULL AFTER completion_notes");
// Student-selects-teacher model columns
if (!in_array('is_trial', $existing_lessons_cols)) $conn->query("ALTER TABLE lessons ADD COLUMN is_trial BOOLEAN DEFAULT FALSE AFTER status");
if (!in_array('wallet_transaction_id', $existing_lessons_cols)) $conn->query("ALTER TABLE lessons ADD COLUMN wallet_transaction_id INT(6) UNSIGNED NULL AFTER is_trial");
if (!in_array('category', $existing_lessons_cols)) $conn->query("ALTER TABLE lessons ADD COLUMN category ENUM('young_learners', 'adults', 'coding') NULL AFTER wallet_transaction_id");

// Add indexes for new columns
$index_check = $conn->query("SELECT COUNT(*) as count FROM INFORMATION_SCHEMA.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'lessons' AND INDEX_NAME = 'idx_wallet_transaction'");
if ($index_check) {
    $index_result = $index_check->fetch_assoc();
    if ($index_result['count'] == 0) {
        $conn->query("ALTER TABLE lessons ADD INDEX idx_wallet_transaction (wallet_transaction_id)");
    }
}
$index_check = $conn->query("SELECT COUNT(*) as count FROM INFORMATION_SCHEMA.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'lessons' AND INDEX_NAME = 'idx_category'");
if ($index_check) {
    $index_result = $index_check->fetch_assoc();
    if ($index_result['count'] == 0) {
        $conn->query("ALTER TABLE lessons ADD INDEX idx_category (category)");
    }
}
$index_check = $conn->query("SELECT COUNT(*) as count FROM INFORMATION_SCHEMA.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'lessons' AND INDEX_NAME = 'idx_is_trial'");
if ($index_check) {
    $index_result = $index_check->fetch_assoc();
    if ($index_result['count'] == 0) {
        $conn->query("ALTER TABLE lessons ADD INDEX idx_is_trial (is_trial)");
    }
}

// Add foreign keys separately
$fk_check = $conn->query("SELECT CONSTRAINT_NAME FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE WHERE TABLE_NAME='lessons' AND COLUMN_NAME='teacher_id' AND REFERENCED_TABLE_NAME='users'");
if (!$fk_check || $fk_check->num_rows == 0) {
    $conn->query("ALTER TABLE lessons ADD CONSTRAINT fk_lessons_teacher FOREIGN KEY (teacher_id) REFERENCES users(id) ON DELETE CASCADE");
}
$fk_check = $conn->query("SELECT CONSTRAINT_NAME FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE WHERE TABLE_NAME='lessons' AND COLUMN_NAME='student_id' AND REFERENCED_TABLE_NAME='users'");
if (!$fk_check || $fk_check->num_rows == 0) {
    $conn->query("ALTER TABLE lessons ADD CONSTRAINT fk_lessons_student FOREIGN KEY (student_id) REFERENCES users(id) ON DELETE CASCADE");
}
// Add foreign key for wallet_transaction_id
$fk_check = $conn->query("SELECT CONSTRAINT_NAME FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE WHERE TABLE_NAME='lessons' AND COLUMN_NAME='wallet_transaction_id' AND REFERENCED_TABLE_NAME='wallet_transactions'");
if (!$fk_check || $fk_check->num_rows == 0) {
    $col_check = $conn->query("SHOW COLUMNS FROM lessons LIKE 'wallet_transaction_id'");
    if ($col_check && $col_check->num_rows > 0) {
        $wallet_table_check = $conn->query("SHOW TABLES LIKE 'wallet_transactions'");
        if ($wallet_table_check && $wallet_table_check->num_rows > 0) {
            $conn->query("ALTER TABLE lessons ADD CONSTRAINT fk_lesson_wallet_transaction FOREIGN KEY (wallet_transaction_id) REFERENCES wallet_transactions(id) ON DELETE SET NULL");
        }
    }
}

// Create time_off table (for teacher time-off periods)
$sql = "CREATE TABLE IF NOT EXISTS time_off (
    id INT(6) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    teacher_id INT(6) UNSIGNED NOT NULL,
    start_date DATE NOT NULL,
    end_date DATE NOT NULL,
    reason VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_teacher (teacher_id),
    INDEX idx_dates (start_date, end_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
$conn->query($sql);

// Add foreign key for time_off
$fk_check = $conn->query("SELECT CONSTRAINT_NAME FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE WHERE TABLE_NAME='time_off' AND COLUMN_NAME='teacher_id' AND REFERENCED_TABLE_NAME='users'");
if (!$fk_check || $fk_check->num_rows == 0) {
    $conn->query("ALTER TABLE time_off ADD CONSTRAINT fk_timeoff_teacher FOREIGN KEY (teacher_id) REFERENCES users(id) ON DELETE CASCADE");
}

// Create recurring_lessons table (for recurring lesson series)
$sql = "CREATE TABLE IF NOT EXISTS recurring_lessons (
    id INT(6) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    teacher_id INT(6) UNSIGNED NOT NULL,
    student_id INT(6) UNSIGNED NOT NULL,
    day_of_week ENUM('Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday') NOT NULL,
    start_time TIME NOT NULL,
    end_time TIME NOT NULL,
    start_date DATE NOT NULL,
    end_date DATE NULL,
    frequency_weeks INT DEFAULT 1,
    status ENUM('active', 'paused', 'cancelled') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_teacher (teacher_id),
    INDEX idx_student (student_id),
    INDEX idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
$conn->query($sql);

// Add foreign keys for recurring_lessons
$fk_check = $conn->query("SELECT CONSTRAINT_NAME FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE WHERE TABLE_NAME='recurring_lessons' AND COLUMN_NAME='teacher_id' AND REFERENCED_TABLE_NAME='users'");
if (!$fk_check || $fk_check->num_rows == 0) {
    $conn->query("ALTER TABLE recurring_lessons ADD CONSTRAINT fk_recurring_teacher FOREIGN KEY (teacher_id) REFERENCES users(id) ON DELETE CASCADE");
}
$fk_check = $conn->query("SELECT CONSTRAINT_NAME FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE WHERE TABLE_NAME='recurring_lessons' AND COLUMN_NAME='student_id' AND REFERENCED_TABLE_NAME='users'");
if (!$fk_check || $fk_check->num_rows == 0) {
    $conn->query("ALTER TABLE recurring_lessons ADD CONSTRAINT fk_recurring_student FOREIGN KEY (student_id) REFERENCES users(id) ON DELETE CASCADE");
}

// Add columns to users table for Google Calendar integration if they don't exist
// Use INFORMATION_SCHEMA for reliable column existence checking
if (!function_exists('columnExists')) {
    function columnExists($conn, $table, $column) {
        $stmt = $conn->prepare("SELECT COUNT(*) as cnt FROM INFORMATION_SCHEMA.COLUMNS 
                                WHERE TABLE_SCHEMA = DATABASE() 
                                AND TABLE_NAME = ? 
                                AND COLUMN_NAME = ?");
        if (!$stmt) return false;
        $stmt->bind_param("ss", $table, $column);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        $stmt->close();
        return ($row && $row['cnt'] > 0);
    }
}

// Helper function to safely add column with error handling
function safeAddColumn($conn, $table, $column, $definition) {
    if (!columnExists($conn, $table, $column)) {
        $result = @$conn->query($definition);
        if (!$result && $conn->errno != 1060) { // 1060 = Duplicate column name
            error_log("Failed to add column $column to $table: " . $conn->error);
        }
        return $result;
    }
    return true;
}

safeAddColumn($conn, 'users', 'google_calendar_token', "ALTER TABLE users ADD COLUMN google_calendar_token LONGTEXT AFTER video_url");
safeAddColumn($conn, 'users', 'google_calendar_token_expiry', "ALTER TABLE users ADD COLUMN google_calendar_token_expiry DATETIME AFTER google_calendar_token");
safeAddColumn($conn, 'users', 'google_calendar_refresh_token', "ALTER TABLE users ADD COLUMN google_calendar_refresh_token LONGTEXT AFTER google_calendar_token_expiry");

// Add timezone support columns to users table
safeAddColumn($conn, 'users', 'timezone', "ALTER TABLE users ADD COLUMN timezone VARCHAR(255) DEFAULT 'UTC' AFTER google_calendar_refresh_token");
safeAddColumn($conn, 'users', 'timezone_auto_detected', "ALTER TABLE users ADD COLUMN timezone_auto_detected BOOLEAN DEFAULT FALSE AFTER timezone");
safeAddColumn($conn, 'users', 'booking_notice_hours', "ALTER TABLE users ADD COLUMN booking_notice_hours INT DEFAULT 24 AFTER timezone_auto_detected");

// Create reviews table (for teacher reviews by students)
// Drop existing table if it has bad foreign keys
$reviews_exists = $conn->query("SHOW TABLES LIKE 'reviews'");
if ($reviews_exists && $reviews_exists->num_rows > 0) {
    // Drop the table and recreate it cleanly to avoid foreign key constraint errors
    $conn->query("SET FOREIGN_KEY_CHECKS=0");
    $conn->query("DROP TABLE IF EXISTS reviews");
    $conn->query("SET FOREIGN_KEY_CHECKS=1");
}

$sql = "CREATE TABLE reviews (
    id INT PRIMARY KEY AUTO_INCREMENT,
    teacher_id INT(6) UNSIGNED NOT NULL,
    student_id INT(6) UNSIGNED NOT NULL,
    booking_id INT(6) UNSIGNED DEFAULT NULL,
    rating TINYINT NOT NULL,
    review_text TEXT,
    is_public BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_teacher (teacher_id),
    INDEX idx_student (student_id),
    INDEX idx_booking (booking_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

$create_result = $conn->query($sql);
if ($create_result === FALSE) {
    if (defined('APP_DEBUG') && APP_DEBUG === true) {
        error_log("Error creating reviews table: " . $conn->error);
    }
}

// Add foreign keys separately after table creation
$fk_check = $conn->query("SELECT CONSTRAINT_NAME FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE WHERE TABLE_NAME='reviews' AND COLUMN_NAME='teacher_id' AND REFERENCED_TABLE_NAME='users'");
if (!$fk_check || $fk_check->num_rows == 0) {
    $fk_result = $conn->query("ALTER TABLE reviews ADD CONSTRAINT fk_reviews_teacher FOREIGN KEY (teacher_id) REFERENCES users(id) ON DELETE CASCADE");
    if ($fk_result === FALSE && defined('APP_DEBUG') && APP_DEBUG === true) {
        error_log("Warning: Could not add foreign key fk_reviews_teacher: " . $conn->error);
    }
}
$fk_check = $conn->query("SELECT CONSTRAINT_NAME FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE WHERE TABLE_NAME='reviews' AND COLUMN_NAME='student_id' AND REFERENCED_TABLE_NAME='users'");
if (!$fk_check || $fk_check->num_rows == 0) {
    $fk_result = $conn->query("ALTER TABLE reviews ADD CONSTRAINT fk_reviews_student FOREIGN KEY (student_id) REFERENCES users(id) ON DELETE CASCADE");
    if ($fk_result === FALSE && defined('APP_DEBUG') && APP_DEBUG === true) {
        error_log("Warning: Could not add foreign key fk_reviews_student: " . $conn->error);
    }
}
$fk_check = $conn->query("SELECT CONSTRAINT_NAME FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE WHERE TABLE_NAME='reviews' AND COLUMN_NAME='booking_id' AND REFERENCED_TABLE_NAME='bookings'");
if (!$fk_check || $fk_check->num_rows == 0) {
    $fk_result = $conn->query("ALTER TABLE reviews ADD CONSTRAINT fk_reviews_booking FOREIGN KEY (booking_id) REFERENCES bookings(id) ON DELETE SET NULL");
    if ($fk_result === FALSE && defined('APP_DEBUG') && APP_DEBUG === true) {
        error_log("Warning: Could not add foreign key fk_reviews_booking: " . $conn->error);
    }
}

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
if ($conn->query($sql) === FALSE) {
    if (defined('APP_DEBUG') && APP_DEBUG === true) {
        error_log("Error creating course_categories table: " . $conn->error);
    }
}

// Create courses table (after course_categories exists)
// First, ensure course_categories table exists and is InnoDB
$cat_table = $conn->query("SHOW TABLE STATUS WHERE Name='course_categories'");
if ($cat_table && $cat_row = $cat_table->fetch_assoc()) {
    if (strtoupper($cat_row['Engine']) !== 'INNODB') {
        $conn->query("ALTER TABLE course_categories ENGINE=InnoDB");
    }
}

// Create courses table without inline foreign keys
// Drop existing table if it has bad foreign keys (will recreate below)
$courses_exists = $conn->query("SHOW TABLES LIKE 'courses'");
if ($courses_exists && $courses_exists->num_rows > 0) {
    // Drop the table and recreate it cleanly to avoid foreign key constraint errors
    $conn->query("SET FOREIGN_KEY_CHECKS=0");
    $conn->query("DROP TABLE IF EXISTS courses");
    $conn->query("SET FOREIGN_KEY_CHECKS=1");
}

$sql = "CREATE TABLE courses (
    id INT PRIMARY KEY AUTO_INCREMENT,
    title VARCHAR(255) NOT NULL,
    description TEXT,
    thumbnail_url VARCHAR(500),
    category_id INT NULL,
    difficulty_level ENUM('beginner', 'intermediate', 'advanced') DEFAULT 'beginner',
    duration_minutes INT DEFAULT 0,
    instructor_id INT(6) UNSIGNED NULL,
    is_featured BOOLEAN DEFAULT FALSE,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_category (category_id),
    INDEX idx_instructor (instructor_id),
    INDEX idx_featured (is_featured),
    INDEX idx_active (is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

$create_result = $conn->query($sql);
if ($create_result === FALSE) {
    if (defined('APP_DEBUG') && APP_DEBUG === true) {
        error_log("Error creating courses table: " . $conn->error);
    }
}

// Add foreign keys separately after table creation to avoid constraint errors
// Wait a moment to ensure table is fully created
$fk_check = $conn->query("SELECT CONSTRAINT_NAME FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE WHERE TABLE_NAME='courses' AND COLUMN_NAME='category_id' AND REFERENCED_TABLE_NAME='course_categories'");
if (!$fk_check || $fk_check->num_rows == 0) {
    $fk_result = $conn->query("ALTER TABLE courses ADD CONSTRAINT fk_courses_category FOREIGN KEY (category_id) REFERENCES course_categories(id) ON DELETE SET NULL");
    if ($fk_result === FALSE && defined('APP_DEBUG') && APP_DEBUG === true) {
        error_log("Warning: Could not add foreign key fk_courses_category: " . $conn->error);
    }
}

$fk_check2 = $conn->query("SELECT CONSTRAINT_NAME FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE WHERE TABLE_NAME='courses' AND COLUMN_NAME='instructor_id' AND REFERENCED_TABLE_NAME='users'");
if (!$fk_check2 || $fk_check2->num_rows == 0) {
    $fk_result = $conn->query("ALTER TABLE courses ADD CONSTRAINT fk_courses_instructor FOREIGN KEY (instructor_id) REFERENCES users(id) ON DELETE SET NULL");
    if ($fk_result === FALSE && defined('APP_DEBUG') && APP_DEBUG === true) {
        error_log("Warning: Could not add foreign key fk_courses_instructor: " . $conn->error);
    }
}

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
    INDEX idx_course (course_id),
    INDEX idx_order (course_id, lesson_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
$conn->query($sql);

// Add foreign key separately
$fk_check = $conn->query("SELECT CONSTRAINT_NAME FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE WHERE TABLE_NAME='course_lessons' AND CONSTRAINT_NAME='fk_lessons_course'");
if (!$fk_check || $fk_check->num_rows == 0) {
    $conn->query("ALTER TABLE course_lessons ADD CONSTRAINT fk_lessons_course FOREIGN KEY (course_id) REFERENCES courses(id) ON DELETE CASCADE");
}
if ($conn->query($sql) === FALSE) {
    if (defined('APP_DEBUG') && APP_DEBUG === true) {
        error_log("Error creating course_lessons table: " . $conn->error);
    }
}

// Create course_enrollments table
$sql = "CREATE TABLE IF NOT EXISTS course_enrollments (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT(6) UNSIGNED NOT NULL,
    course_id INT NOT NULL,
    enrollment_type ENUM('plan', 'purchase', 'free') DEFAULT 'plan',
    plan_id INT NULL,
    enrolled_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    expires_at TIMESTAMP NULL,
    UNIQUE KEY unique_enrollment (user_id, course_id),
    INDEX idx_user (user_id),
    INDEX idx_course (course_id),
    INDEX idx_expires (expires_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
$conn->query($sql);

// Add foreign keys separately
$fk_check = $conn->query("SELECT CONSTRAINT_NAME FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE WHERE TABLE_NAME='course_enrollments' AND CONSTRAINT_NAME='fk_enrollments_user'");
if (!$fk_check || $fk_check->num_rows == 0) {
    $conn->query("ALTER TABLE course_enrollments ADD CONSTRAINT fk_enrollments_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE");
}
$fk_check = $conn->query("SELECT CONSTRAINT_NAME FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE WHERE TABLE_NAME='course_enrollments' AND CONSTRAINT_NAME='fk_enrollments_course'");
if (!$fk_check || $fk_check->num_rows == 0) {
    $conn->query("ALTER TABLE course_enrollments ADD CONSTRAINT fk_enrollments_course FOREIGN KEY (course_id) REFERENCES courses(id) ON DELETE CASCADE");
}
if ($conn->query($sql) === FALSE) {
    if (defined('APP_DEBUG') && APP_DEBUG === true) {
        error_log("Error creating course_enrollments table: " . $conn->error);
    }
}

// Create user_course_progress table
$sql = "CREATE TABLE IF NOT EXISTS user_course_progress (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT(6) UNSIGNED NOT NULL,
    course_id INT NOT NULL,
    lesson_id INT NULL,
    progress_percentage DECIMAL(5,2) DEFAULT 0,
    completed_lessons JSON,
    last_accessed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    completed_at TIMESTAMP NULL,
    UNIQUE KEY unique_user_course (user_id, course_id),
    INDEX idx_user (user_id),
    INDEX idx_course (course_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
$conn->query($sql);

// Add foreign keys separately
$fk_check = $conn->query("SELECT CONSTRAINT_NAME FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE WHERE TABLE_NAME='user_course_progress' AND CONSTRAINT_NAME='fk_progress_user'");
if (!$fk_check || $fk_check->num_rows == 0) {
    $conn->query("ALTER TABLE user_course_progress ADD CONSTRAINT fk_progress_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE");
}
$fk_check = $conn->query("SELECT CONSTRAINT_NAME FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE WHERE TABLE_NAME='user_course_progress' AND CONSTRAINT_NAME='fk_progress_course'");
if (!$fk_check || $fk_check->num_rows == 0) {
    $conn->query("ALTER TABLE user_course_progress ADD CONSTRAINT fk_progress_course FOREIGN KEY (course_id) REFERENCES courses(id) ON DELETE CASCADE");
}
$fk_check = $conn->query("SELECT CONSTRAINT_NAME FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE WHERE TABLE_NAME='user_course_progress' AND CONSTRAINT_NAME='fk_progress_lesson'");
if (!$fk_check || $fk_check->num_rows == 0) {
    $conn->query("ALTER TABLE user_course_progress ADD CONSTRAINT fk_progress_lesson FOREIGN KEY (lesson_id) REFERENCES course_lessons(id) ON DELETE SET NULL");
}
if ($conn->query($sql) === FALSE) {
    if (defined('APP_DEBUG') && APP_DEBUG === true) {
        error_log("Error creating user_course_progress table: " . $conn->error);
    }
}

// Create course_reviews table
$sql = "CREATE TABLE IF NOT EXISTS course_reviews (
    id INT PRIMARY KEY AUTO_INCREMENT,
    course_id INT NOT NULL,
    user_id INT(6) UNSIGNED NOT NULL,
    rating INT CHECK (rating >= 1 AND rating <= 5),
    review_text TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_review (course_id, user_id),
    INDEX idx_course (course_id),
    INDEX idx_user (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
$conn->query($sql);

// Add foreign keys separately
$fk_check = $conn->query("SELECT CONSTRAINT_NAME FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE WHERE TABLE_NAME='course_reviews' AND CONSTRAINT_NAME='fk_reviews_course'");
if (!$fk_check || $fk_check->num_rows == 0) {
    $conn->query("ALTER TABLE course_reviews ADD CONSTRAINT fk_reviews_course FOREIGN KEY (course_id) REFERENCES courses(id) ON DELETE CASCADE");
}
$fk_check = $conn->query("SELECT CONSTRAINT_NAME FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE WHERE TABLE_NAME='course_reviews' AND CONSTRAINT_NAME='fk_reviews_user'");
if (!$fk_check || $fk_check->num_rows == 0) {
    $conn->query("ALTER TABLE course_reviews ADD CONSTRAINT fk_reviews_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE");
}

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
if ($conn->query($sql) === FALSE) {
    if (defined('APP_DEBUG') && APP_DEBUG === true) {
        error_log("Error creating subscription_plans table: " . $conn->error);
    }
}

// Add track-specific columns to subscription_plans table
$plan_cols = $conn->query("SHOW COLUMNS FROM subscription_plans");
$existing_plan_cols = [];
if ($plan_cols) {
    while($row = $plan_cols->fetch_assoc()) { $existing_plan_cols[] = $row['Field']; }
}
if (!in_array('track', $existing_plan_cols)) $conn->query("ALTER TABLE subscription_plans ADD COLUMN track ENUM('kids', 'adults', 'coding') NULL AFTER display_order");
if (!in_array('one_on_one_classes_per_week', $existing_plan_cols)) $conn->query("ALTER TABLE subscription_plans ADD COLUMN one_on_one_classes_per_week INT DEFAULT 0 AFTER track");
if (!in_array('group_classes_included', $existing_plan_cols)) $conn->query("ALTER TABLE subscription_plans ADD COLUMN group_classes_included BOOLEAN DEFAULT FALSE AFTER one_on_one_classes_per_week");
if (!in_array('group_classes_per_month', $existing_plan_cols)) $conn->query("ALTER TABLE subscription_plans ADD COLUMN group_classes_per_month INT DEFAULT 0 AFTER group_classes_included");
if (!in_array('track_specific_features', $existing_plan_cols)) $conn->query("ALTER TABLE subscription_plans ADD COLUMN track_specific_features JSON NULL AFTER group_classes_per_month");
if (!in_array('stripe_product_id', $existing_plan_cols)) $conn->query("ALTER TABLE subscription_plans ADD COLUMN stripe_product_id VARCHAR(255) NULL AFTER stripe_price_id");
if (!in_array('one_on_one_classes_per_month', $existing_plan_cols)) $conn->query("ALTER TABLE subscription_plans ADD COLUMN one_on_one_classes_per_month INT DEFAULT 0 AFTER one_on_one_classes_per_week");
if (!in_array('is_best_value', $existing_plan_cols)) $conn->query("ALTER TABLE subscription_plans ADD COLUMN is_best_value BOOLEAN DEFAULT FALSE AFTER track_specific_features");

// Create user_selected_courses table
$sql = "CREATE TABLE IF NOT EXISTS user_selected_courses (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT(6) UNSIGNED NOT NULL,
    category_id INT NOT NULL,
    plan_id INT NOT NULL,
    selected_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_user_category_plan (user_id, category_id, plan_id),
    INDEX idx_user (user_id),
    INDEX idx_category (category_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
$conn->query($sql);

// Add foreign keys separately
$fk_check = $conn->query("SELECT CONSTRAINT_NAME FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE WHERE TABLE_NAME='user_selected_courses' AND COLUMN_NAME='user_id' AND REFERENCED_TABLE_NAME='users'");
if (!$fk_check || $fk_check->num_rows == 0) {
    $conn->query("ALTER TABLE user_selected_courses ADD CONSTRAINT fk_selected_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE");
}
$fk_check = $conn->query("SELECT CONSTRAINT_NAME FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE WHERE TABLE_NAME='user_selected_courses' AND COLUMN_NAME='category_id' AND REFERENCED_TABLE_NAME='course_categories'");
if (!$fk_check || $fk_check->num_rows == 0) {
    $conn->query("ALTER TABLE user_selected_courses ADD CONSTRAINT fk_selected_category FOREIGN KEY (category_id) REFERENCES course_categories(id) ON DELETE CASCADE");
}
$fk_check = $conn->query("SELECT CONSTRAINT_NAME FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE WHERE TABLE_NAME='user_selected_courses' AND COLUMN_NAME='plan_id' AND REFERENCED_TABLE_NAME='subscription_plans'");
if (!$fk_check || $fk_check->num_rows == 0) {
    $conn->query("ALTER TABLE user_selected_courses ADD CONSTRAINT fk_selected_plan FOREIGN KEY (plan_id) REFERENCES subscription_plans(id) ON DELETE CASCADE");
}

// Seed initial course categories (if table exists and is empty)
$category_check = $conn->query("SELECT COUNT(*) as count FROM course_categories");
if ($category_check) {
    $cat_count = $category_check->fetch_assoc()['count'] ?? 0;
    if ($cat_count == 0) {
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
    }
}

// Seed subscription plans (if table exists and is empty)
$plan_check = $conn->query("SELECT COUNT(*) as count FROM subscription_plans");
if ($plan_check) {
    $plan_count = $plan_check->fetch_assoc()['count'] ?? 0;
    if ($plan_count == 0) {
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
    }
}

// Create custom_plans table for user-defined custom subscription plans
$sql = "CREATE TABLE IF NOT EXISTS custom_plans (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT(6) UNSIGNED NOT NULL,
    plan_name VARCHAR(100) DEFAULT 'Custom Plan',
    hours_per_week INT NOT NULL DEFAULT 1,
    choose_own_teacher BOOLEAN DEFAULT TRUE,
    hourly_rate DECIMAL(10,2) NOT NULL DEFAULT 30.00,
    extra_courses_count INT DEFAULT 0,
    group_classes_count INT DEFAULT 0,
    base_monthly_price DECIMAL(10,2) NOT NULL,
    courses_extra DECIMAL(10,2) DEFAULT 0.00,
    group_classes_extra DECIMAL(10,2) DEFAULT 0.00,
    total_monthly_price DECIMAL(10,2) NOT NULL,
    selected_course_ids JSON,
    stripe_price_id VARCHAR(255) DEFAULT NULL,
    stripe_subscription_id VARCHAR(255) DEFAULT NULL,
    status ENUM('draft', 'active', 'cancelled', 'expired') DEFAULT 'draft',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_user (user_id),
    INDEX idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
if ($conn->query($sql) === FALSE) {
    if (defined('APP_DEBUG') && APP_DEBUG === true) {
        error_log("Error creating custom_plans table: " . $conn->error);
    }
}

// Add foreign key for custom_plans
$fk_check = $conn->query("SELECT CONSTRAINT_NAME FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE WHERE TABLE_NAME='custom_plans' AND COLUMN_NAME='user_id' AND REFERENCED_TABLE_NAME='users'");
if (!$fk_check || $fk_check->num_rows == 0) {
    $conn->query("ALTER TABLE custom_plans ADD CONSTRAINT fk_custom_plan_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE");
}

// Phase 4: Three-Track Platform Tables
// Create teacher_assignments table
$sql = "CREATE TABLE IF NOT EXISTS teacher_assignments (
    id INT(6) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    student_id INT(6) UNSIGNED NOT NULL,
    teacher_id INT(6) UNSIGNED NOT NULL,
    track ENUM('kids', 'adults', 'coding') NOT NULL,
    assigned_by INT(6) UNSIGNED NOT NULL,
    assigned_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    status ENUM('active', 'inactive', 'transferred') DEFAULT 'active',
    notes TEXT,
    INDEX idx_student (student_id),
    INDEX idx_teacher (teacher_id),
    INDEX idx_track (track),
    INDEX idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
$conn->query($sql);

// Add foreign keys for teacher_assignments
$fk_check = $conn->query("SELECT CONSTRAINT_NAME FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE WHERE TABLE_NAME='teacher_assignments' AND COLUMN_NAME='student_id' AND REFERENCED_TABLE_NAME='users'");
if (!$fk_check || $fk_check->num_rows == 0) {
    $conn->query("ALTER TABLE teacher_assignments ADD CONSTRAINT fk_assignment_student FOREIGN KEY (student_id) REFERENCES users(id) ON DELETE CASCADE");
}
$fk_check = $conn->query("SELECT CONSTRAINT_NAME FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE WHERE TABLE_NAME='teacher_assignments' AND COLUMN_NAME='teacher_id' AND REFERENCED_TABLE_NAME='users'");
if (!$fk_check || $fk_check->num_rows == 0) {
    $conn->query("ALTER TABLE teacher_assignments ADD CONSTRAINT fk_assignment_teacher FOREIGN KEY (teacher_id) REFERENCES users(id) ON DELETE CASCADE");
}
$fk_check = $conn->query("SELECT CONSTRAINT_NAME FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE WHERE TABLE_NAME='teacher_assignments' AND COLUMN_NAME='assigned_by' AND REFERENCED_TABLE_NAME='users'");
if (!$fk_check || $fk_check->num_rows == 0) {
    $conn->query("ALTER TABLE teacher_assignments ADD CONSTRAINT fk_assignment_admin FOREIGN KEY (assigned_by) REFERENCES users(id) ON DELETE CASCADE");
}

// Create group_classes table
$sql = "CREATE TABLE IF NOT EXISTS group_classes (
    id INT(6) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    track ENUM('kids', 'adults', 'coding') NOT NULL,
    teacher_id INT(6) UNSIGNED NOT NULL,
    scheduled_date DATE NOT NULL,
    scheduled_time TIME NOT NULL,
    duration INT DEFAULT 60,
    max_students INT DEFAULT 10,
    current_enrollment INT DEFAULT 0,
    status ENUM('scheduled', 'in_progress', 'completed', 'cancelled') DEFAULT 'scheduled',
    title VARCHAR(255),
    description TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_track (track),
    INDEX idx_teacher (teacher_id),
    INDEX idx_date (scheduled_date),
    INDEX idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
$conn->query($sql);

// Add foreign key for group_classes
$fk_check = $conn->query("SELECT CONSTRAINT_NAME FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE WHERE TABLE_NAME='group_classes' AND COLUMN_NAME='teacher_id' AND REFERENCED_TABLE_NAME='users'");
if (!$fk_check || $fk_check->num_rows == 0) {
    $conn->query("ALTER TABLE group_classes ADD CONSTRAINT fk_groupclass_teacher FOREIGN KEY (teacher_id) REFERENCES users(id) ON DELETE CASCADE");
}

// Create group_class_enrollments table
$sql = "CREATE TABLE IF NOT EXISTS group_class_enrollments (
    id INT(6) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    group_class_id INT(6) UNSIGNED NOT NULL,
    student_id INT(6) UNSIGNED NOT NULL,
    enrolled_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    attendance_status ENUM('enrolled', 'attended', 'absent', 'cancelled') DEFAULT 'enrolled',
    UNIQUE KEY unique_enrollment (group_class_id, student_id),
    INDEX idx_class (group_class_id),
    INDEX idx_student (student_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
$conn->query($sql);

// Add foreign keys for group_class_enrollments
$fk_check = $conn->query("SELECT CONSTRAINT_NAME FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE WHERE TABLE_NAME='group_class_enrollments' AND COLUMN_NAME='group_class_id' AND REFERENCED_TABLE_NAME='group_classes'");
if (!$fk_check || $fk_check->num_rows == 0) {
    $conn->query("ALTER TABLE group_class_enrollments ADD CONSTRAINT fk_enrollment_class FOREIGN KEY (group_class_id) REFERENCES group_classes(id) ON DELETE CASCADE");
}
$fk_check = $conn->query("SELECT CONSTRAINT_NAME FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE WHERE TABLE_NAME='group_class_enrollments' AND COLUMN_NAME='student_id' AND REFERENCED_TABLE_NAME='users'");
if (!$fk_check || $fk_check->num_rows == 0) {
    $conn->query("ALTER TABLE group_class_enrollments ADD CONSTRAINT fk_enrollment_student FOREIGN KEY (student_id) REFERENCES users(id) ON DELETE CASCADE");
}

// Add foreign key for assigned_teacher_id in users table (deprecated but kept for migration)
$fk_check = $conn->query("SELECT CONSTRAINT_NAME FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE WHERE TABLE_NAME='users' AND COLUMN_NAME='assigned_teacher_id' AND REFERENCED_TABLE_NAME='users'");
if (!$fk_check || $fk_check->num_rows == 0) {
    $col_check = $conn->query("SHOW COLUMNS FROM users LIKE 'assigned_teacher_id'");
    if ($col_check && $col_check->num_rows > 0) {
        $conn->query("ALTER TABLE users ADD CONSTRAINT fk_user_assigned_teacher FOREIGN KEY (assigned_teacher_id) REFERENCES users(id) ON DELETE SET NULL");
    }
}

// =====================================================
// MIGRATION: Student Selects Teacher Model
// Migrate existing students to new model
// =====================================================

// Migrate learning_track to preferred_category
$migrate_check = $conn->query("SELECT COUNT(*) as count FROM users WHERE learning_track IS NOT NULL AND (preferred_category IS NULL OR preferred_category = '')");
if ($migrate_check && $migrate_check->num_rows > 0) {
    $migrate_result = $migrate_check->fetch_assoc();
    if ($migrate_result['count'] > 0) {
        // Map learning_track to preferred_category
        $conn->query("UPDATE users SET preferred_category = CASE 
            WHEN learning_track = 'kids' THEN 'young_learners'
            WHEN learning_track = 'adults' THEN 'adults'
            WHEN learning_track = 'coding' THEN 'coding'
            ELSE NULL
        END WHERE learning_track IS NOT NULL AND (preferred_category IS NULL OR preferred_category = '')");
    }
}

// Initialize trial_used for all students
$trial_check = $conn->query("SELECT COUNT(*) as count FROM users WHERE role IN ('student', 'new_student') AND trial_used IS NULL");
if ($trial_check && $trial_check->num_rows > 0) {
    $trial_result = $trial_check->fetch_assoc();
    if ($trial_result['count'] > 0) {
        $conn->query("UPDATE users SET trial_used = FALSE WHERE role IN ('student', 'new_student') AND trial_used IS NULL");
    }
}

// Add foreign key for plan_id in users table (only if column exists)
$col_check = $conn->query("SHOW COLUMNS FROM users LIKE 'plan_id'");
if ($col_check && $col_check->num_rows > 0) {
    $fk_check = $conn->query("SELECT CONSTRAINT_NAME FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE WHERE TABLE_NAME='users' AND COLUMN_NAME='plan_id' AND REFERENCED_TABLE_NAME='subscription_plans'");
    if (!$fk_check || $fk_check->num_rows == 0) {
        // Check if subscription_plans table exists and has id column
        $plans_table_check = $conn->query("SHOW TABLES LIKE 'subscription_plans'");
        if ($plans_table_check && $plans_table_check->num_rows > 0) {
            $plans_id_check = $conn->query("SHOW COLUMNS FROM subscription_plans LIKE 'id'");
            if ($plans_id_check && $plans_id_check->num_rows > 0) {
                $conn->query("ALTER TABLE users ADD CONSTRAINT fk_user_plan FOREIGN KEY (plan_id) REFERENCES subscription_plans(id) ON DELETE SET NULL");
            }
        }
    }
}

// Create notifications table (if not exists)
$sql = "CREATE TABLE IF NOT EXISTS notifications (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT(6) UNSIGNED NOT NULL,
    type VARCHAR(50) NOT NULL,
    title VARCHAR(255) NOT NULL,
    message TEXT,
    link VARCHAR(255) DEFAULT NULL,
    is_read BOOLEAN DEFAULT FALSE,
    read_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_user_unread (user_id, is_read),
    INDEX idx_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
$conn->query($sql);

// Add foreign key for notifications
$fk_check = $conn->query("SELECT CONSTRAINT_NAME FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE WHERE TABLE_NAME='notifications' AND COLUMN_NAME='user_id' AND REFERENCED_TABLE_NAME='users'");
if (!$fk_check || $fk_check->num_rows == 0) {
    $conn->query("ALTER TABLE notifications ADD CONSTRAINT fk_notifications_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE");
}

// Create admin slot requests table
$sql = "CREATE TABLE IF NOT EXISTS admin_slot_requests (
    id INT(6) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    admin_id INT(6) UNSIGNED NOT NULL,
    teacher_id INT(6) UNSIGNED NOT NULL,
    request_type ENUM('time_slot', 'group_class') DEFAULT 'time_slot',
    requested_date DATE,
    requested_time TIME,
    duration_minutes INT DEFAULT 60,
    group_class_track ENUM('kids', 'adults', 'coding') NULL,
    group_class_date DATE NULL,
    group_class_time TIME NULL,
    status ENUM('pending', 'accepted', 'rejected', 'completed') DEFAULT 'pending',
    message TEXT,
    response_message TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_teacher (teacher_id),
    INDEX idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
$conn->query($sql);

// Add foreign keys
$fk_check = $conn->query("SELECT CONSTRAINT_NAME FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE WHERE TABLE_NAME='admin_slot_requests' AND COLUMN_NAME='admin_id' AND REFERENCED_TABLE_NAME='users'");
if (!$fk_check || $fk_check->num_rows == 0) {
    $conn->query("ALTER TABLE admin_slot_requests ADD CONSTRAINT fk_slot_request_admin FOREIGN KEY (admin_id) REFERENCES users(id) ON DELETE CASCADE");
}
$fk_check = $conn->query("SELECT CONSTRAINT_NAME FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE WHERE TABLE_NAME='admin_slot_requests' AND COLUMN_NAME='teacher_id' AND REFERENCED_TABLE_NAME='users'");
if (!$fk_check || $fk_check->num_rows == 0) {
    $conn->query("ALTER TABLE admin_slot_requests ADD CONSTRAINT fk_slot_request_teacher FOREIGN KEY (teacher_id) REFERENCES users(id) ON DELETE CASCADE");
}

// Create preferred_times table for student time preferences
$sql = "CREATE TABLE IF NOT EXISTS preferred_times (
    id INT(6) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    student_id INT(6) UNSIGNED NOT NULL,
    day_of_week ENUM('Monday','Tuesday','Wednesday','Thursday','Friday','Saturday','Sunday') NOT NULL,
    start_time TIME NOT NULL,
    end_time TIME NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_student (student_id),
    INDEX idx_day_time (day_of_week, start_time),
    FOREIGN KEY (student_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
$conn->query($sql);

// Check if foreign key exists for preferred_times
$fk_check = $conn->query("SELECT CONSTRAINT_NAME FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE WHERE TABLE_NAME='preferred_times' AND COLUMN_NAME='student_id' AND REFERENCED_TABLE_NAME='users'");
if (!$fk_check || $fk_check->num_rows == 0) {
    $conn->query("ALTER TABLE preferred_times ADD CONSTRAINT fk_preferred_times_student FOREIGN KEY (student_id) REFERENCES users(id) ON DELETE CASCADE");
}

// Create student_learning_needs table
$sql = "CREATE TABLE IF NOT EXISTS student_learning_needs (
    id INT(6) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    student_id INT(6) UNSIGNED NOT NULL,
    track ENUM('kids', 'adults', 'coding') NOT NULL,
    age_range VARCHAR(50),
    current_level VARCHAR(100),
    learning_goals TEXT,
    preferred_schedule TEXT,
    special_requirements TEXT,
    completed BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_student (student_id),
    UNIQUE KEY unique_student_needs (student_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
$conn->query($sql);

// Add foreign key
$fk_check = $conn->query("SELECT CONSTRAINT_NAME FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE WHERE TABLE_NAME='student_learning_needs' AND COLUMN_NAME='student_id' AND REFERENCED_TABLE_NAME='users'");
if (!$fk_check || $fk_check->num_rows == 0) {
    $conn->query("ALTER TABLE student_learning_needs ADD CONSTRAINT fk_learning_needs_student FOREIGN KEY (student_id) REFERENCES users(id) ON DELETE CASCADE");
}

// Create signaling_queue table for WebRTC signaling
$sql = "CREATE TABLE IF NOT EXISTS signaling_queue (
    id INT AUTO_INCREMENT PRIMARY KEY,
    session_id VARCHAR(255) NOT NULL,
    from_user_id INT NOT NULL,
    to_user_id INT NOT NULL,
    message_type ENUM('webrtc-offer', 'webrtc-answer', 'webrtc-ice-candidate') NOT NULL,
    message_data TEXT NOT NULL,
    timestamp TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    processed BOOLEAN DEFAULT FALSE,
    INDEX idx_session (session_id),
    INDEX idx_users (from_user_id, to_user_id),
    INDEX idx_processed (processed, timestamp)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
$conn->query($sql);

// Create whiteboard_operations table for collaborative whiteboard
$sql = "CREATE TABLE IF NOT EXISTS whiteboard_operations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    session_id VARCHAR(255) NOT NULL,
    user_id INT NOT NULL,
    operation_data TEXT NOT NULL,
    timestamp TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    processed BOOLEAN DEFAULT FALSE,
    INDEX idx_session (session_id),
    INDEX idx_user (user_id),
    INDEX idx_processed (processed, timestamp)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
$conn->query($sql);

// Create video_sessions table for classroom sessions
// #region agent log
$log_data = ['step' => 'before_video_sessions_create', 'timestamp' => time()];
file_put_contents(__DIR__ . '/.cursor/debug.log', json_encode($log_data) . "\n", FILE_APPEND);
// #endregion

// Check if table exists and get column types
$table_exists = $conn->query("SHOW TABLES LIKE 'video_sessions'");
$existing_video_cols = [];
if ($table_exists && $table_exists->num_rows > 0) {
    $cols_result = $conn->query("SHOW COLUMNS FROM video_sessions");
    if ($cols_result) {
        while($row = $cols_result->fetch_assoc()) {
            $existing_video_cols[$row['Field']] = $row['Type'];
        }
    }
}

// #region agent log
$log_data = ['step' => 'check_existing_video_sessions', 'table_exists' => ($table_exists && $table_exists->num_rows > 0), 'columns' => $existing_video_cols, 'timestamp' => time()];
file_put_contents(__DIR__ . '/.cursor/debug.log', json_encode($log_data) . "\n", FILE_APPEND);
// #endregion

// Check users and lessons table column types
$users_cols = $conn->query("SHOW COLUMNS FROM users WHERE Field='id'");
$users_id_type = null;
if ($users_cols && $users_cols->num_rows > 0) {
    $row = $users_cols->fetch_assoc();
    $users_id_type = $row['Type'];
}

$lessons_cols = $conn->query("SHOW COLUMNS FROM lessons WHERE Field='id'");
$lessons_id_type = null;
if ($lessons_cols && $lessons_cols->num_rows > 0) {
    $row = $lessons_cols->fetch_assoc();
    $lessons_id_type = $row['Type'];
}

// #region agent log
$log_data = ['step' => 'check_referenced_types', 'users_id_type' => $users_id_type, 'lessons_id_type' => $lessons_id_type, 'timestamp' => time()];
file_put_contents(__DIR__ . '/.cursor/debug.log', json_encode($log_data) . "\n", FILE_APPEND);
// #endregion

$sql = "CREATE TABLE IF NOT EXISTS video_sessions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    session_id VARCHAR(255) UNIQUE NOT NULL,
    lesson_id INT(6) UNSIGNED NULL,
    teacher_id INT(6) UNSIGNED NOT NULL,
    student_id INT(6) UNSIGNED NOT NULL,
    status ENUM('active', 'ended', 'cancelled') DEFAULT 'active',
    is_test_session BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    ended_at TIMESTAMP NULL,
    INDEX idx_lesson (lesson_id),
    INDEX idx_users (teacher_id, student_id),
    INDEX idx_status (status),
    INDEX idx_session (session_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
$create_result = $conn->query($sql);

// #region agent log
$log_data = ['step' => 'create_video_sessions_table', 'success' => ($create_result !== false), 'error' => $conn->error ?? null, 'timestamp' => time()];
file_put_contents(__DIR__ . '/.cursor/debug.log', json_encode($log_data) . "\n", FILE_APPEND);
// #endregion

// If table exists with wrong column types, alter them
if ($table_exists && $table_exists->num_rows > 0) {
    if (isset($existing_video_cols['teacher_id']) && $existing_video_cols['teacher_id'] !== 'int(6) unsigned') {
        $alter_result = $conn->query("ALTER TABLE video_sessions MODIFY COLUMN teacher_id INT(6) UNSIGNED NOT NULL");
        // #region agent log
        $log_data = ['step' => 'alter_teacher_id', 'success' => ($alter_result !== false), 'error' => $conn->error ?? null, 'old_type' => $existing_video_cols['teacher_id'] ?? 'none', 'timestamp' => time()];
        file_put_contents(__DIR__ . '/.cursor/debug.log', json_encode($log_data) . "\n", FILE_APPEND);
        // #endregion
    }
    if (isset($existing_video_cols['student_id']) && $existing_video_cols['student_id'] !== 'int(6) unsigned') {
        $alter_result = $conn->query("ALTER TABLE video_sessions MODIFY COLUMN student_id INT(6) UNSIGNED NOT NULL");
        // #region agent log
        $log_data = ['step' => 'alter_student_id', 'success' => ($alter_result !== false), 'error' => $conn->error ?? null, 'old_type' => $existing_video_cols['student_id'] ?? 'none', 'timestamp' => time()];
        file_put_contents(__DIR__ . '/.cursor/debug.log', json_encode($log_data) . "\n", FILE_APPEND);
        // #endregion
    }
    if (isset($existing_video_cols['lesson_id']) && $existing_video_cols['lesson_id'] !== 'int(6) unsigned') {
        $alter_result = $conn->query("ALTER TABLE video_sessions MODIFY COLUMN lesson_id INT(6) UNSIGNED NULL");
        // #region agent log
        $log_data = ['step' => 'alter_lesson_id', 'success' => ($alter_result !== false), 'error' => $conn->error ?? null, 'old_type' => $existing_video_cols['lesson_id'] ?? 'none', 'timestamp' => time()];
        file_put_contents(__DIR__ . '/.cursor/debug.log', json_encode($log_data) . "\n", FILE_APPEND);
        // #endregion
    }
}

// Add foreign keys for video_sessions
$fk_check = $conn->query("SELECT CONSTRAINT_NAME FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE WHERE TABLE_NAME='video_sessions' AND COLUMN_NAME='teacher_id' AND REFERENCED_TABLE_NAME='users'");
// #region agent log
$log_data = ['step' => 'check_fk_teacher', 'fk_exists' => ($fk_check && $fk_check->num_rows > 0), 'timestamp' => time()];
file_put_contents(__DIR__ . '/.cursor/debug.log', json_encode($log_data) . "\n", FILE_APPEND);
// #endregion

if (!$fk_check || $fk_check->num_rows == 0) {
    $fk_result = $conn->query("ALTER TABLE video_sessions ADD CONSTRAINT fk_video_sessions_teacher FOREIGN KEY (teacher_id) REFERENCES users(id) ON DELETE CASCADE");
    // #region agent log
    $log_data = ['step' => 'add_fk_teacher', 'success' => ($fk_result !== false), 'error' => $conn->error ?? null, 'timestamp' => time()];
    file_put_contents(__DIR__ . '/.cursor/debug.log', json_encode($log_data) . "\n", FILE_APPEND);
    // #endregion
}

$fk_check = $conn->query("SELECT CONSTRAINT_NAME FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE WHERE TABLE_NAME='video_sessions' AND COLUMN_NAME='student_id' AND REFERENCED_TABLE_NAME='users'");
// #region agent log
$log_data = ['step' => 'check_fk_student', 'fk_exists' => ($fk_check && $fk_check->num_rows > 0), 'timestamp' => time()];
file_put_contents(__DIR__ . '/.cursor/debug.log', json_encode($log_data) . "\n", FILE_APPEND);
// #endregion

if (!$fk_check || $fk_check->num_rows == 0) {
    $fk_result = $conn->query("ALTER TABLE video_sessions ADD CONSTRAINT fk_video_sessions_student FOREIGN KEY (student_id) REFERENCES users(id) ON DELETE CASCADE");
    // #region agent log
    $log_data = ['step' => 'add_fk_student', 'success' => ($fk_result !== false), 'error' => $conn->error ?? null, 'timestamp' => time()];
    file_put_contents(__DIR__ . '/.cursor/debug.log', json_encode($log_data) . "\n", FILE_APPEND);
    // #endregion
}

$fk_check = $conn->query("SELECT CONSTRAINT_NAME FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE WHERE TABLE_NAME='video_sessions' AND COLUMN_NAME='lesson_id' AND REFERENCED_TABLE_NAME='lessons'");
// #region agent log
$log_data = ['step' => 'check_fk_lesson', 'fk_exists' => ($fk_check && $fk_check->num_rows > 0), 'timestamp' => time()];
file_put_contents(__DIR__ . '/.cursor/debug.log', json_encode($log_data) . "\n", FILE_APPEND);
// #endregion

if (!$fk_check || $fk_check->num_rows == 0) {
    $lesson_table_check = $conn->query("SHOW TABLES LIKE 'lessons'");
    if ($lesson_table_check && $lesson_table_check->num_rows > 0) {
        $fk_result = $conn->query("ALTER TABLE video_sessions ADD CONSTRAINT fk_video_sessions_lesson FOREIGN KEY (lesson_id) REFERENCES lessons(id) ON DELETE SET NULL");
        // #region agent log
        $log_data = ['step' => 'add_fk_lesson', 'success' => ($fk_result !== false), 'error' => $conn->error ?? null, 'timestamp' => time()];
        file_put_contents(__DIR__ . '/.cursor/debug.log', json_encode($log_data) . "\n", FILE_APPEND);
        // #endregion
    }
}

// Create admin_audit_log table for tracking admin actions
// Check if table exists first
$admin_audit_log_exists = $conn->query("SHOW TABLES LIKE 'admin_audit_log'");
$admin_audit_log_current_cols = [];
if ($admin_audit_log_exists && $admin_audit_log_exists->num_rows > 0) {
    $cols_result = $conn->query("SHOW COLUMNS FROM admin_audit_log");
    if ($cols_result) {
        while($row = $cols_result->fetch_assoc()) {
            $admin_audit_log_current_cols[$row['Field']] = [
                'Type' => $row['Type'],
                'Null' => $row['Null'],
                'Key' => $row['Key']
            ];
        }
    }
    
    // Check for existing foreign keys
    $existing_fks = $conn->query("SELECT CONSTRAINT_NAME FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE WHERE TABLE_NAME='admin_audit_log' AND TABLE_SCHEMA=DATABASE()");
    $fk_names = [];
    if ($existing_fks) {
        while($fk_row = $existing_fks->fetch_assoc()) {
            if (!empty($fk_row['CONSTRAINT_NAME'])) {
                $fk_names[] = $fk_row['CONSTRAINT_NAME'];
            }
        }
    }
    
    // If admin_id column needs fixing, drop foreign keys first, then alter
    if (isset($admin_audit_log_current_cols['admin_id']) && 
        strpos(strtolower($admin_audit_log_current_cols['admin_id']['Type']), 'unsigned') === false) {
        // Drop foreign keys first
        foreach ($fk_names as $fk_name) {
            @$conn->query("ALTER TABLE admin_audit_log DROP FOREIGN KEY " . $conn->real_escape_string($fk_name));
        }
        
        // Alter column
        $alter_result = @$conn->query("ALTER TABLE admin_audit_log MODIFY COLUMN admin_id INT(6) UNSIGNED NOT NULL");
    }
}

$sql = "CREATE TABLE IF NOT EXISTS admin_audit_log (
    id INT AUTO_INCREMENT PRIMARY KEY,
    admin_id INT(6) UNSIGNED NOT NULL,
    action VARCHAR(100) NOT NULL,
    target_type VARCHAR(50),
    target_id INT,
    details TEXT,
    ip_address VARCHAR(45),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_admin (admin_id),
    INDEX idx_action (action),
    INDEX idx_target (target_type, target_id),
    INDEX idx_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

$create_result = @$conn->query($sql);

// If CREATE TABLE fails with foreign key error, try recovery
if ($create_result === false && ($conn->errno == 150 || strpos($conn->error ?? '', 'Foreign key constraint') !== false)) {
    // Drop all foreign keys
    $all_fks = $conn->query("SELECT CONSTRAINT_NAME FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE WHERE TABLE_NAME='admin_audit_log' AND TABLE_SCHEMA=DATABASE() AND CONSTRAINT_NAME IS NOT NULL");
    if ($all_fks) {
        while($fk_row = $all_fks->fetch_assoc()) {
            if (!empty($fk_row['CONSTRAINT_NAME'])) {
                @$conn->query("ALTER TABLE admin_audit_log DROP FOREIGN KEY " . $conn->real_escape_string($fk_row['CONSTRAINT_NAME']));
            }
        }
    }
    
    // Drop and recreate
    @$conn->query("DROP TABLE IF EXISTS admin_audit_log");
    $create_result = @$conn->query($sql);
}

// Add foreign key separately (like other tables)
$fk_check = $conn->query("SELECT CONSTRAINT_NAME FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE WHERE TABLE_NAME='admin_audit_log' AND COLUMN_NAME='admin_id' AND REFERENCED_TABLE_NAME='users'");
if (!$fk_check || $fk_check->num_rows == 0) {
    $fk_result = @$conn->query("ALTER TABLE admin_audit_log ADD CONSTRAINT fk_admin_audit_log_admin FOREIGN KEY (admin_id) REFERENCES users(id) ON DELETE CASCADE");
}

// Ensure admin account exists with correct credentials
$admin_email = 'statenenglishacademy@gmail.com';
$admin_password = '123456789';
$admin_name = 'Admin';
$admin_check = $conn->prepare("SELECT id, role, password FROM users WHERE email = ?");
if ($admin_check) {
    $admin_check->bind_param("s", $admin_email);
    $admin_check->execute();
    $admin_result = $admin_check->get_result();
    $admin_exists = $admin_result->fetch_assoc();
    $admin_check->close();
    
    if ($admin_exists) {
        // Update admin password and role if needed
        $hashed_password = password_hash($admin_password, PASSWORD_DEFAULT);
        $update_admin = $conn->prepare("UPDATE users SET password = ?, role = 'admin', name = ? WHERE email = ?");
        if ($update_admin) {
            $update_admin->bind_param("sss", $hashed_password, $admin_name, $admin_email);
            $update_admin->execute();
            $update_admin->close();
        }
    } else {
        // Create admin account
        $hashed_password = password_hash($admin_password, PASSWORD_DEFAULT);
        $create_admin = $conn->prepare("INSERT INTO users (email, password, name, role, application_status) VALUES (?, ?, ?, 'admin', 'approved')");
        if ($create_admin) {
            $create_admin->bind_param("sss", $admin_email, $hashed_password, $admin_name);
            $create_admin->execute();
            $create_admin->close();
        }
    }
}

// Create beta_feedback table
$beta_feedback_check = $conn->query("SHOW TABLES LIKE 'beta_feedback'");
if (!$beta_feedback_check || $beta_feedback_check->num_rows == 0) {
    $sql = "CREATE TABLE IF NOT EXISTS beta_feedback (
        id INT(6) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        user_id INT(6) UNSIGNED NULL,
        feedback_type ENUM('bug', 'feature_request', 'ui_issue', 'performance', 'general') DEFAULT 'general',
        category VARCHAR(50) DEFAULT 'other',
        title VARCHAR(255) NOT NULL,
        description TEXT NOT NULL,
        priority ENUM('low', 'medium', 'high', 'critical') DEFAULT 'medium',
        page_url VARCHAR(500),
        user_agent TEXT,
        ip_address VARCHAR(45),
        status ENUM('pending', 'reviewed', 'in_progress', 'resolved', 'rejected') DEFAULT 'pending',
        admin_notes TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_user (user_id),
        INDEX idx_status (status),
        INDEX idx_category (category),
        INDEX idx_created (created_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
    $conn->query($sql);
    
    // Add foreign key
    $fk_check = $conn->query("SELECT CONSTRAINT_NAME FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE WHERE TABLE_NAME='beta_feedback' AND COLUMN_NAME='user_id' AND REFERENCED_TABLE_NAME='users'");
    if (!$fk_check || $fk_check->num_rows == 0) {
        $conn->query("ALTER TABLE beta_feedback ADD CONSTRAINT fk_beta_feedback_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL");
    }
}

// Create rate_limits table
$rate_limits_check = $conn->query("SHOW TABLES LIKE 'rate_limits'");
if (!$rate_limits_check || $rate_limits_check->num_rows == 0) {
    $sql = "CREATE TABLE IF NOT EXISTS rate_limits (
        id INT AUTO_INCREMENT PRIMARY KEY,
        identifier VARCHAR(255) NOT NULL,
        endpoint VARCHAR(255) NOT NULL,
        request_count INT DEFAULT 1,
        window_start TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_identifier_endpoint (identifier, endpoint),
        INDEX idx_window_start (window_start)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
    $conn->query($sql);
}

// Create admin_settings table if it doesn't exist
$admin_settings_check = $conn->query("SHOW TABLES LIKE 'admin_settings'");
if (!$admin_settings_check || $admin_settings_check->num_rows == 0) {
    $sql = "CREATE TABLE IF NOT EXISTS admin_settings (
        id INT PRIMARY KEY AUTO_INCREMENT,
        setting_key VARCHAR(255) NOT NULL UNIQUE,
        value TEXT,
        description TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_key (setting_key)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
    $conn->query($sql);
}

// Create credit_transactions table if it doesn't exist
$credit_transactions_check = $conn->query("SHOW TABLES LIKE 'credit_transactions'");
if (!$credit_transactions_check || $credit_transactions_check->num_rows == 0) {
    $sql = "CREATE TABLE IF NOT EXISTS credit_transactions (
        id INT PRIMARY KEY AUTO_INCREMENT,
        student_id INT(6) UNSIGNED NOT NULL,
        type ENUM('admin_add', 'admin_remove', 'purchase', 'subscription_renewal', 'gift_received', 'gift_sent', 'lesson_used') NOT NULL,
        amount INT NOT NULL,
        description TEXT,
        reference_id VARCHAR(255) NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_student (student_id),
        INDEX idx_type (type),
        FOREIGN KEY (student_id) REFERENCES users(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
    $conn->query($sql);
}

// Create gift_credit_purchases table if it doesn't exist
$gift_purchases_check = $conn->query("SHOW TABLES LIKE 'gift_credit_purchases'");
if (!$gift_purchases_check || $gift_purchases_check->num_rows == 0) {
    $sql = "CREATE TABLE IF NOT EXISTS gift_credit_purchases (
        id INT PRIMARY KEY AUTO_INCREMENT,
        purchaser_id INT(6) UNSIGNED NOT NULL,
        recipient_email VARCHAR(255) NOT NULL,
        recipient_id INT(6) UNSIGNED NULL,
        credits_amount INT NOT NULL,
        stripe_payment_id VARCHAR(255) NOT NULL,
        status ENUM('pending', 'completed', 'failed') DEFAULT 'pending',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_purchaser (purchaser_id),
        INDEX idx_recipient (recipient_id),
        FOREIGN KEY (purchaser_id) REFERENCES users(id) ON DELETE CASCADE,
        FOREIGN KEY (recipient_id) REFERENCES users(id) ON DELETE SET NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
    $conn->query($sql);
}

// Create gift_credit_products table if it doesn't exist
$gift_products_check = $conn->query("SHOW TABLES LIKE 'gift_credit_products'");
if (!$gift_products_check || $gift_products_check->num_rows == 0) {
    $sql = "CREATE TABLE IF NOT EXISTS gift_credit_products (
        id INT PRIMARY KEY AUTO_INCREMENT,
        name VARCHAR(255) NOT NULL,
        credits_amount INT NOT NULL,
        price DECIMAL(10,2) NOT NULL,
        stripe_product_id VARCHAR(255) NOT NULL,
        stripe_price_id VARCHAR(255) NOT NULL,
        is_active BOOLEAN DEFAULT TRUE,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
    $conn->query($sql);
}

// Update subscription_plans table to add credit system columns
$plan_cols_check = $conn->query("SHOW TABLES LIKE 'subscription_plans'");
if ($plan_cols_check && $plan_cols_check->num_rows > 0) {
    $plan_cols = $conn->query("SHOW COLUMNS FROM subscription_plans");
    $existing_plan_cols = [];
    if ($plan_cols) {
        while($row = $plan_cols->fetch_assoc()) { 
            $existing_plan_cols[] = $row['Field']; 
        }
    }
    
    if (!in_array('type', $existing_plan_cols)) {
        $conn->query("ALTER TABLE subscription_plans ADD COLUMN type ENUM('package', 'subscription', 'addon') DEFAULT 'subscription'");
    }
    if (!in_array('credits_included', $existing_plan_cols)) {
        $conn->query("ALTER TABLE subscription_plans ADD COLUMN credits_included INT DEFAULT 0");
    }
    if (!in_array('billing_cycle_days', $existing_plan_cols)) {
        $conn->query("ALTER TABLE subscription_plans ADD COLUMN billing_cycle_days INT NULL");
    }
}
