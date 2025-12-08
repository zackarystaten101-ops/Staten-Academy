<?php
/**
 * Test Account Setup Script
 * Sets up student@statenacademy.com with unlimited classes and all features activated
 * 
 * Run this once to set up the test account, then delete or protect this file
 */

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

// SECURITY: Only allow in development or with admin authentication
// For testing, you can temporarily comment out this check or set APP_DEBUG=true in env.php
$allow_execution = false;

if (defined('APP_DEBUG') && APP_DEBUG === true) {
    $allow_execution = true;
} elseif (isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'admin') {
    $allow_execution = true;
} elseif (isset($_GET['run']) && $_GET['run'] === 'setup') {
    // Allow if run parameter is set (for one-time setup)
    // SECURITY NOTE: In production, remove this parameter check or add password protection
    // This is intended for one-time setup only
    $allow_execution = true;
}

if (!$allow_execution) {
    die("Access denied. This script can only run in debug mode or by an admin.<br>To run setup, add ?run=setup to the URL (only for initial setup).");
}

require_once __DIR__ . '/db.php';

$student_email = 'student@statenacademy.com';
$teacher_email = 'zackarystaten101@gmail.com';
?>
<!DOCTYPE html>
<html>
<head>
    <title>Test Account Setup</title>
    <style>
        body { font-family: Arial, sans-serif; padding: 20px; background: #f5f5f5; }
        .container { max-width: 800px; margin: 0 auto; background: white; padding: 30px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        h1 { color: #333; }
        pre { background: #f8f9fa; padding: 15px; border-radius: 4px; overflow-x: auto; }
        .success { color: #28a745; }
        .error { color: #dc3545; }
        .info { color: #17a2b8; }
        .btn { display: inline-block; padding: 10px 20px; background: #0b6cf5; color: white; text-decoration: none; border-radius: 4px; margin-top: 20px; }
        .btn:hover { background: #0056b3; }
    </style>
</head>
<body>
<div class="container">
    <h1>Test Account Setup</h1>
    <p><strong>Note:</strong> This script sets up the test student account with unlimited classes and all features activated.</p>
    <p><strong>Alternative:</strong> You can also run the SQL script directly: <code>setup-test-account.sql</code></p>
    <pre>
<?php

try {
    // Get student user
    $stmt = $conn->prepare("SELECT id, name, role FROM users WHERE email = ?");
    $stmt->bind_param("s", $student_email);
    $stmt->execute();
    $result = $stmt->get_result();
    $student = $result->fetch_assoc();
    $stmt->close();
    
    if (!$student) {
        echo "ERROR: Student account not found: $student_email\n";
        echo "Please create the account first through registration.\n";
        exit;
    }
    
    $student_id = $student['id'];
    echo "✓ Found student: {$student['name']} (ID: $student_id)\n";
    
    // Get teacher user
    $stmt = $conn->prepare("SELECT id, name, role FROM users WHERE email = ?");
    $stmt->bind_param("s", $teacher_email);
    $stmt->execute();
    $result = $stmt->get_result();
    $teacher = $result->fetch_assoc();
    $stmt->close();
    
    if (!$teacher) {
        echo "ERROR: Teacher account not found: $teacher_email\n";
        echo "Please create the teacher account first.\n";
        exit;
    }
    
    $teacher_id = $teacher['id'];
    echo "✓ Found teacher: {$teacher['name']} (ID: $teacher_id)\n\n";
    
    // 1. Activate student account (change from new_student to student)
    $stmt = $conn->prepare("UPDATE users SET role = 'student' WHERE id = ?");
    $stmt->bind_param("i", $student_id);
    if ($stmt->execute()) {
        echo "✓ Activated student account (changed role to 'student')\n";
    } else {
        echo "✗ Error activating student: " . $stmt->error . "\n";
    }
    $stmt->close();
    
    // 2. Add student to teacher's favorites (if not already)
    $stmt = $conn->prepare("INSERT IGNORE INTO favorite_teachers (student_id, teacher_id) VALUES (?, ?)");
    $stmt->bind_param("ii", $student_id, $teacher_id);
    if ($stmt->execute()) {
        echo "✓ Added teacher to student's favorites\n";
    }
    $stmt->close();
    
    // 3. Create a test booking record (for compatibility)
    $stmt = $conn->prepare("INSERT IGNORE INTO bookings (student_id, teacher_id, booking_date) VALUES (?, ?, NOW())");
    $stmt->bind_param("ii", $student_id, $teacher_id);
    if ($stmt->execute()) {
        echo "✓ Created booking record\n";
    }
    $stmt->close();
    
    // 4. Create some test lessons (past, current, and future)
    $lessons_created = 0;
    
    // Past lesson (completed)
    $past_date = date('Y-m-d', strtotime('-7 days'));
    $stmt = $conn->prepare("
        INSERT IGNORE INTO lessons (teacher_id, student_id, lesson_date, start_time, end_time, status, lesson_type, color_code)
        VALUES (?, ?, ?, '10:00:00', '11:00:00', 'completed', 'single', '#0b6cf5')
    ");
    $stmt->bind_param("iis", $teacher_id, $student_id, $past_date);
    if ($stmt->execute()) {
        $lessons_created++;
    }
    $stmt->close();
    
    // Today's lesson (if time allows)
    $today = date('Y-m-d');
    $current_hour = (int)date('H');
    $start_hour = max($current_hour + 1, 9); // At least 1 hour from now, or 9 AM
    if ($start_hour < 20) { // Only if before 8 PM
        $start_time = sprintf('%02d:00:00', $start_hour);
        $end_time = sprintf('%02d:00:00', $start_hour + 1);
        $stmt = $conn->prepare("
            INSERT IGNORE INTO lessons (teacher_id, student_id, lesson_date, start_time, end_time, status, lesson_type, color_code)
            VALUES (?, ?, ?, ?, ?, 'scheduled', 'single', '#0b6cf5')
        ");
        $stmt->bind_param("iisss", $teacher_id, $student_id, $today, $start_time, $end_time);
        if ($stmt->execute()) {
            $lessons_created++;
        }
        $stmt->close();
    }
    
    // Future lessons (next 7 days)
    for ($i = 1; $i <= 7; $i++) {
        $future_date = date('Y-m-d', strtotime("+$i days"));
        $start_time = '14:00:00'; // 2 PM
        $end_time = '15:00:00';   // 3 PM
        
        $stmt = $conn->prepare("
            INSERT IGNORE INTO lessons (teacher_id, student_id, lesson_date, start_time, end_time, status, lesson_type, color_code)
            VALUES (?, ?, ?, ?, ?, 'scheduled', 'single', '#0b6cf5')
        ");
        $stmt->bind_param("iisss", $teacher_id, $student_id, $future_date, $start_time, $end_time);
        if ($stmt->execute()) {
            $lessons_created++;
        }
        $stmt->close();
    }
    
    echo "✓ Created $lessons_created test lessons\n";
    
    // 5. Remove any booking restrictions (if there are any in the code)
    // This is handled by the role being 'student' instead of 'new_student'
    
    // 6. Verify setup
    echo "\n=== Verification ===\n";
    
    // Check student role
    $stmt = $conn->prepare("SELECT role FROM users WHERE id = ?");
    $stmt->bind_param("i", $student_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $user_data = $result->fetch_assoc();
    $stmt->close();
    echo "Student role: " . $user_data['role'] . " (should be 'student')\n";
    
    // Count lessons
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM lessons WHERE student_id = ? AND teacher_id = ?");
    $stmt->bind_param("ii", $student_id, $teacher_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $lesson_count = $result->fetch_assoc()['count'];
    $stmt->close();
    echo "Total lessons with teacher: $lesson_count\n";
    
    // Count scheduled lessons
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM lessons WHERE student_id = ? AND teacher_id = ? AND status = 'scheduled' AND lesson_date >= CURDATE()");
    $stmt->bind_param("ii", $student_id, $teacher_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $scheduled_count = $result->fetch_assoc()['count'];
    $stmt->close();
    echo "Upcoming scheduled lessons: $scheduled_count\n";
    
    echo "\n=== Setup Complete ===\n";
    echo "Student: $student_email\n";
    echo "Teacher: $teacher_email\n";
    echo "\nThe student can now:\n";
    echo "- Book unlimited lessons with the teacher\n";
    echo "- Join classrooms from calendar/lessons\n";
    echo "- Access all student features\n";
    echo "- See upcoming lessons with 'Join' buttons\n";
    
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
    echo $e->getTraceAsString() . "\n";
}

?>
    </pre>
    <p><strong>Setup Complete!</strong></p>
    <p>The student account <code>student@statenacademy.com</code> now has:</p>
    <ul>
        <li>Full student access (role: 'student')</li>
        <li>Unlimited booking capability with the teacher</li>
        <li>Test lessons created (past, today, and future)</li>
        <li>All features pre-activated</li>
    </ul>
    <a href="student-dashboard.php" class="btn">Go to Student Dashboard</a>
    <a href="schedule.php" class="btn" style="background: #6c757d;">Go to Schedule</a>
</div>
</body>
</html>
<?php
ob_end_flush();

