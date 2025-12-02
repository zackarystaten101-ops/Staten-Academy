<?php
// Load environment configuration
require_once __DIR__ . '/env.php';

$servername = DB_HOST;
$username = DB_USERNAME;
$password = DB_PASSWORD;
$dbname = DB_NAME;

// Create connection
$conn = new mysqli($servername, $username, $password);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Create database if not exists
$sql = "CREATE DATABASE IF NOT EXISTS $dbname";
if ($conn->query($sql) === TRUE) {
    $conn->select_db($dbname);
} else {
    die("Error creating database: " . $conn->error);
}

// Create users table if not exists
$sql = "CREATE TABLE IF NOT EXISTS users (
    id INT(6) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    email VARCHAR(50) NOT NULL UNIQUE,
    password VARCHAR(255),
    google_id VARCHAR(255),
    name VARCHAR(50),
    role ENUM('student', 'teacher', 'admin') DEFAULT 'student',
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
?>

