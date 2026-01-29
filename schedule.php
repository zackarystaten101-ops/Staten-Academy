<?php
// Redirect based on user role
session_start();

$user_role = $_SESSION['user_role'] ?? 'guest';

if ($user_role === 'teacher' || $user_role === 'admin') {
    // Teachers/admins go to their dashboard to manage group classes
    header("Location: teacher-dashboard.php#group-classes");
} elseif ($user_role === 'student' || $user_role === 'new_student') {
    // Students go to their dashboard to view group classes
    header("Location: student-dashboard.php#group-classes");
} else {
    // Guests go to sign up for group classes
    header("Location: kids-plans.php");
}
exit();
?>
