<?php
/**
 * Reviews API
 * Handles review submission and retrieval
 */

session_start();
require_once '../db.php';

header('Content-Type: application/json');

// Check authentication
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$user_id = $_SESSION['user_id'];
$user_role = $_SESSION['user_role'] ?? '';
$action = $_GET['action'] ?? $_POST['action'] ?? '';

switch ($action) {
    case 'submit':
        submitReview($conn, $user_id);
        break;
        
    case 'update':
        updateReview($conn, $user_id);
        break;
        
    case 'delete':
        deleteReview($conn, $user_id);
        break;
        
    case 'get_teacher':
        getTeacherReviews($conn);
        break;
        
    case 'get_student':
        getStudentReviews($conn, $user_id);
        break;
        
    case 'can_review':
        canReview($conn, $user_id);
        break;
        
    default:
        echo json_encode(['error' => 'Invalid action']);
}

/**
 * Submit a new review
 */
function submitReview($conn, $student_id) {
    // Only students can submit reviews
    if ($_SESSION['user_role'] !== 'student') {
        echo json_encode(['error' => 'Only students can submit reviews']);
        return;
    }
    
    $teacher_id = isset($_POST['teacher_id']) ? (int)$_POST['teacher_id'] : 0;
    $booking_id = isset($_POST['booking_id']) ? (int)$_POST['booking_id'] : null;
    $rating = isset($_POST['rating']) ? (int)$_POST['rating'] : 0;
    $review_text = trim($_POST['review_text'] ?? '');
    
    // Validation
    if (!$teacher_id || $rating < 1 || $rating > 5) {
        echo json_encode(['error' => 'Invalid teacher ID or rating']);
        return;
    }
    
    // Check if already reviewed this teacher (without booking)
    if (!$booking_id) {
        $check = $conn->prepare("SELECT id FROM reviews WHERE teacher_id = ? AND student_id = ? AND booking_id IS NULL");
        $check->bind_param("ii", $teacher_id, $student_id);
        $check->execute();
        if ($check->get_result()->num_rows > 0) {
            echo json_encode(['error' => 'You have already reviewed this teacher']);
            $check->close();
            return;
        }
        $check->close();
    }
    
    // Insert review
    $stmt = $conn->prepare("INSERT INTO reviews (teacher_id, student_id, booking_id, rating, review_text) VALUES (?, ?, ?, ?, ?)");
    $stmt->bind_param("iiiis", $teacher_id, $student_id, $booking_id, $rating, $review_text);
    
    if ($stmt->execute()) {
        $review_id = $stmt->insert_id;
        
        // Update teacher's average rating cache
        updateTeacherRating($conn, $teacher_id);
        
        // Create notification for teacher
        $student_name = $_SESSION['user_name'] ?? 'A student';
        createNotification($conn, $teacher_id, 'review', 'New Review Received', 
            "$student_name left you a $rating-star review", 'teacher-dashboard.php#reviews');
        
        echo json_encode(['success' => true, 'review_id' => $review_id]);
    } else {
        echo json_encode(['error' => 'Failed to submit review']);
    }
    
    $stmt->close();
}

/**
 * Update an existing review
 */
function updateReview($conn, $student_id) {
    $review_id = isset($_POST['review_id']) ? (int)$_POST['review_id'] : 0;
    $rating = isset($_POST['rating']) ? (int)$_POST['rating'] : 0;
    $review_text = trim($_POST['review_text'] ?? '');
    
    if (!$review_id || $rating < 1 || $rating > 5) {
        echo json_encode(['error' => 'Invalid review ID or rating']);
        return;
    }
    
    // Verify ownership
    $check = $conn->prepare("SELECT teacher_id FROM reviews WHERE id = ? AND student_id = ?");
    $check->bind_param("ii", $review_id, $student_id);
    $check->execute();
    $result = $check->get_result();
    
    if ($result->num_rows === 0) {
        echo json_encode(['error' => 'Review not found or unauthorized']);
        $check->close();
        return;
    }
    
    $teacher_id = $result->fetch_assoc()['teacher_id'];
    $check->close();
    
    // Update review
    $stmt = $conn->prepare("UPDATE reviews SET rating = ?, review_text = ?, updated_at = NOW() WHERE id = ?");
    $stmt->bind_param("isi", $rating, $review_text, $review_id);
    
    if ($stmt->execute()) {
        updateTeacherRating($conn, $teacher_id);
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['error' => 'Failed to update review']);
    }
    
    $stmt->close();
}

/**
 * Delete a review
 */
function deleteReview($conn, $student_id) {
    $review_id = isset($_POST['review_id']) ? (int)$_POST['review_id'] : 0;
    
    // Verify ownership and get teacher_id
    $check = $conn->prepare("SELECT teacher_id FROM reviews WHERE id = ? AND student_id = ?");
    $check->bind_param("ii", $review_id, $student_id);
    $check->execute();
    $result = $check->get_result();
    
    if ($result->num_rows === 0) {
        echo json_encode(['error' => 'Review not found or unauthorized']);
        $check->close();
        return;
    }
    
    $teacher_id = $result->fetch_assoc()['teacher_id'];
    $check->close();
    
    $stmt = $conn->prepare("DELETE FROM reviews WHERE id = ?");
    $stmt->bind_param("i", $review_id);
    
    if ($stmt->execute()) {
        updateTeacherRating($conn, $teacher_id);
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['error' => 'Failed to delete review']);
    }
    
    $stmt->close();
}

/**
 * Get reviews for a teacher
 */
function getTeacherReviews($conn) {
    $teacher_id = isset($_GET['teacher_id']) ? (int)$_GET['teacher_id'] : 0;
    $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
    $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 10;
    $offset = ($page - 1) * $limit;
    
    if (!$teacher_id) {
        echo json_encode(['error' => 'Teacher ID required']);
        return;
    }
    
    // Get stats
    $statsStmt = $conn->prepare("SELECT AVG(rating) as avg_rating, COUNT(*) as total FROM reviews WHERE teacher_id = ?");
    $statsStmt->bind_param("i", $teacher_id);
    $statsStmt->execute();
    $stats = $statsStmt->get_result()->fetch_assoc();
    $statsStmt->close();
    
    // Get reviews
    $stmt = $conn->prepare("
        SELECT r.*, u.name as student_name, u.profile_pic as student_pic
        FROM reviews r
        JOIN users u ON r.student_id = u.id
        WHERE r.teacher_id = ?
        ORDER BY r.created_at DESC
        LIMIT ? OFFSET ?
    ");
    $stmt->bind_param("iii", $teacher_id, $limit, $offset);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $reviews = [];
    while ($row = $result->fetch_assoc()) {
        $row['time_ago'] = formatTimeAgo($row['created_at']);
        $reviews[] = $row;
    }
    $stmt->close();
    
    echo json_encode([
        'reviews' => $reviews,
        'avg_rating' => round($stats['avg_rating'] ?? 0, 1),
        'total' => $stats['total'] ?? 0,
        'page' => $page
    ]);
}

/**
 * Get reviews written by a student
 */
function getStudentReviews($conn, $student_id) {
    $stmt = $conn->prepare("
        SELECT r.*, u.name as teacher_name, u.profile_pic as teacher_pic
        FROM reviews r
        JOIN users u ON r.teacher_id = u.id
        WHERE r.student_id = ?
        ORDER BY r.created_at DESC
    ");
    $stmt->bind_param("i", $student_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $reviews = [];
    while ($row = $result->fetch_assoc()) {
        $row['time_ago'] = formatTimeAgo($row['created_at']);
        $reviews[] = $row;
    }
    $stmt->close();
    
    echo json_encode($reviews);
}

/**
 * Check if student can review a teacher
 */
function canReview($conn, $student_id) {
    $teacher_id = isset($_GET['teacher_id']) ? (int)$_GET['teacher_id'] : 0;
    
    // Check if student has had a booking with this teacher
    $stmt = $conn->prepare("SELECT id FROM bookings WHERE student_id = ? AND teacher_id = ? LIMIT 1");
    $stmt->bind_param("ii", $student_id, $teacher_id);
    $stmt->execute();
    $hasBooking = $stmt->get_result()->num_rows > 0;
    $stmt->close();
    
    // Check if already reviewed
    $stmt = $conn->prepare("SELECT id FROM reviews WHERE student_id = ? AND teacher_id = ? LIMIT 1");
    $stmt->bind_param("ii", $student_id, $teacher_id);
    $stmt->execute();
    $hasReviewed = $stmt->get_result()->num_rows > 0;
    $stmt->close();
    
    echo json_encode([
        'can_review' => $hasBooking && !$hasReviewed,
        'has_booking' => $hasBooking,
        'has_reviewed' => $hasReviewed
    ]);
}

/**
 * Update teacher's cached rating
 */
function updateTeacherRating($conn, $teacher_id) {
    $stmt = $conn->prepare("
        UPDATE users SET 
            avg_rating = (SELECT AVG(rating) FROM reviews WHERE teacher_id = ?),
            review_count = (SELECT COUNT(*) FROM reviews WHERE teacher_id = ?)
        WHERE id = ?
    ");
    $stmt->bind_param("iii", $teacher_id, $teacher_id, $teacher_id);
    $stmt->execute();
    $stmt->close();
}

/**
 * Create notification helper
 */
function createNotification($conn, $user_id, $type, $title, $message, $link = '') {
    $stmt = $conn->prepare("INSERT INTO notifications (user_id, type, title, message, link) VALUES (?, ?, ?, ?, ?)");
    if ($stmt) {
        $stmt->bind_param("issss", $user_id, $type, $title, $message, $link);
        $stmt->execute();
        $stmt->close();
    }
}

/**
 * Format time ago
 */
function formatTimeAgo($datetime) {
    $now = new DateTime();
    $date = new DateTime($datetime);
    $diff = $now->diff($date);
    
    if ($diff->y > 0) return $diff->y . ' year' . ($diff->y > 1 ? 's' : '') . ' ago';
    if ($diff->m > 0) return $diff->m . ' month' . ($diff->m > 1 ? 's' : '') . ' ago';
    if ($diff->d > 0) return $diff->d . ' day' . ($diff->d > 1 ? 's' : '') . ' ago';
    if ($diff->h > 0) return $diff->h . ' hour' . ($diff->h > 1 ? 's' : '') . ' ago';
    if ($diff->i > 0) return $diff->i . ' min' . ($diff->i > 1 ? 's' : '') . ' ago';
    return 'Just now';
}

