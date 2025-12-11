<?php
/**
 * Migration Script: Reset to Three-Track System
 * 
 * This script resets teacher-student relationships and prepares the database
 * for the new three-track assignment system.
 * 
 * WARNING: This will clear all existing teacher-student relationships!
 * Run this only when transitioning to the new system.
 */

require_once __DIR__ . '/db.php';

if (!isset($conn)) {
    die("Database connection failed.");
}

// Check if running from command line or web
$is_cli = php_sapi_name() === 'cli';

if (!$is_cli) {
    // Web interface - require admin authentication
    session_start();
    if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
        die("Access denied. Admin authentication required.");
    }
}

echo "Starting migration to three-track system...\n\n";

// Step 1: Clear existing teacher assignments
echo "Step 1: Clearing existing teacher assignments...\n";
$conn->query("DELETE FROM teacher_assignments");
echo "✓ Cleared teacher_assignments table\n";

// Step 2: Reset assigned_teacher_id in users table
echo "\nStep 2: Resetting assigned_teacher_id for all students...\n";
$conn->query("UPDATE users SET assigned_teacher_id = NULL WHERE role IN ('student', 'new_student')");
echo "✓ Reset assigned_teacher_id for all students\n";

// Step 3: Clear learning_track if needed (optional - keep existing tracks)
echo "\nStep 3: Preserving existing learning_track values...\n";
echo "✓ Learning tracks preserved\n";

// Step 4: Ensure all required columns exist
echo "\nStep 4: Verifying database schema...\n";

// Check users table columns
$user_cols = $conn->query("SHOW COLUMNS FROM users");
$existing_user_cols = [];
if ($user_cols) {
    while($row = $user_cols->fetch_assoc()) {
        $existing_user_cols[] = $row['Field'];
    }
}

$required_cols = [
    'learning_track' => "ALTER TABLE users ADD COLUMN learning_track ENUM('kids', 'adults', 'coding') NULL",
    'assigned_teacher_id' => "ALTER TABLE users ADD COLUMN assigned_teacher_id INT(6) UNSIGNED NULL",
    'plan_id' => "ALTER TABLE users ADD COLUMN plan_id INT NULL"
];

foreach ($required_cols as $col => $sql) {
    if (!in_array($col, $existing_user_cols)) {
        $conn->query($sql);
        echo "✓ Added column: $col\n";
    } else {
        echo "✓ Column exists: $col\n";
    }
}

// Check subscription_plans table columns
$plan_cols = $conn->query("SHOW COLUMNS FROM subscription_plans");
$existing_plan_cols = [];
if ($plan_cols) {
    while($row = $plan_cols->fetch_assoc()) {
        $existing_plan_cols[] = $row['Field'];
    }
}

$required_plan_cols = [
    'track' => "ALTER TABLE subscription_plans ADD COLUMN track ENUM('kids', 'adults', 'coding') NULL",
    'one_on_one_classes_per_week' => "ALTER TABLE subscription_plans ADD COLUMN one_on_one_classes_per_week INT DEFAULT 0",
    'group_classes_included' => "ALTER TABLE subscription_plans ADD COLUMN group_classes_included BOOLEAN DEFAULT FALSE",
    'track_specific_features' => "ALTER TABLE subscription_plans ADD COLUMN track_specific_features JSON NULL"
];

foreach ($required_plan_cols as $col => $sql) {
    if (!in_array($col, $existing_plan_cols)) {
        $conn->query($sql);
        echo "✓ Added column to subscription_plans: $col\n";
    } else {
        echo "✓ Column exists in subscription_plans: $col\n";
    }
}

// Verify tables exist
$required_tables = ['teacher_assignments', 'group_classes', 'group_class_enrollments'];
foreach ($required_tables as $table) {
    $check = $conn->query("SHOW TABLES LIKE '$table'");
    if ($check && $check->num_rows > 0) {
        echo "✓ Table exists: $table\n";
    } else {
        echo "⚠ Table missing: $table (should be created by db.php)\n";
    }
}

echo "\n✓ Migration completed successfully!\n\n";
echo "Next steps:\n";
echo "1. Assign teachers to students using admin-teacher-assignments.php\n";
echo "2. Create group classes using admin-group-classes.php\n";
echo "3. Update subscription plans with track information\n";

if (!$is_cli) {
    echo "\n<a href='admin-teacher-assignments.php'>Go to Teacher Assignments</a> | ";
    echo "<a href='admin-group-classes.php'>Go to Group Classes</a> | ";
    echo "<a href='admin-dashboard.php'>Back to Admin Dashboard</a>";
}



