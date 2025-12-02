<?php
/**
 * Data Export API
 * Generate CSV exports for admin reports
 */

session_start();
require_once '../db.php';

// Admin only
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
    header("Location: ../login.php");
    exit;
}

$type = $_GET['type'] ?? '';

switch ($type) {
    case 'students':
        exportStudents($conn);
        break;
    case 'teachers':
        exportTeachers($conn);
        break;
    case 'bookings':
        exportBookings($conn);
        break;
    case 'earnings':
        exportEarnings($conn);
        break;
    default:
        header("Location: ../admin-dashboard.php#reports");
        exit;
}

function exportStudents($conn) {
    $result = $conn->query("SELECT id, name, email, bio, reg_date FROM users WHERE role = 'student' ORDER BY id");
    
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="students_' . date('Y-m-d') . '.csv"');
    
    $output = fopen('php://output', 'w');
    fputcsv($output, ['ID', 'Name', 'Email', 'Bio', 'Registration Date']);
    
    while ($row = $result->fetch_assoc()) {
        fputcsv($output, $row);
    }
    
    fclose($output);
    exit;
}

function exportTeachers($conn) {
    $result = $conn->query("
        SELECT u.id, u.name, u.email, u.bio, u.hours_taught, u.reg_date,
               (SELECT AVG(rating) FROM reviews WHERE teacher_id = u.id) as avg_rating,
               (SELECT COUNT(*) FROM reviews WHERE teacher_id = u.id) as review_count
        FROM users u WHERE u.role = 'teacher' ORDER BY u.id
    ");
    
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="teachers_' . date('Y-m-d') . '.csv"');
    
    $output = fopen('php://output', 'w');
    fputcsv($output, ['ID', 'Name', 'Email', 'Bio', 'Hours Taught', 'Registration Date', 'Avg Rating', 'Review Count']);
    
    while ($row = $result->fetch_assoc()) {
        fputcsv($output, $row);
    }
    
    fclose($output);
    exit;
}

function exportBookings($conn) {
    $result = $conn->query("
        SELECT b.id, s.name as student_name, t.name as teacher_name, b.booking_date, b.created_at
        FROM bookings b
        JOIN users s ON b.student_id = s.id
        JOIN users t ON b.teacher_id = t.id
        ORDER BY b.id DESC
    ");
    
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="bookings_' . date('Y-m-d') . '.csv"');
    
    $output = fopen('php://output', 'w');
    fputcsv($output, ['ID', 'Student', 'Teacher', 'Booking Date', 'Created At']);
    
    while ($row = $result->fetch_assoc()) {
        fputcsv($output, $row);
    }
    
    fclose($output);
    exit;
}

function exportEarnings($conn) {
    $result = $conn->query("
        SELECT e.id, t.name as teacher_name, e.amount, e.platform_fee, e.net_amount, e.status, e.created_at, e.paid_at
        FROM earnings e
        JOIN users t ON e.teacher_id = t.id
        ORDER BY e.id DESC
    ");
    
    if (!$result) {
        echo "No earnings data available";
        exit;
    }
    
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="earnings_' . date('Y-m-d') . '.csv"');
    
    $output = fopen('php://output', 'w');
    fputcsv($output, ['ID', 'Teacher', 'Amount', 'Platform Fee', 'Net Amount', 'Status', 'Created At', 'Paid At']);
    
    while ($row = $result->fetch_assoc()) {
        fputcsv($output, $row);
    }
    
    fclose($output);
    exit;
}

