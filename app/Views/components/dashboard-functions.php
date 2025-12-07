<?php
/**
 * Dashboard Helper Functions
 * Shared utility functions for all dashboard pages
 */

// Load Security Helper if available
if (file_exists(__DIR__ . '/../../Helpers/SecurityHelper.php')) {
    require_once __DIR__ . '/../../Helpers/SecurityHelper.php';
}

/**
 * Get user data by ID
 */
function getUserById($conn, $user_id) {
    $stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return $result;
}

/**
 * Get count of users by role
 */
function getUserCountByRole($conn, $role) {
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM users WHERE role = ?");
    $stmt->bind_param("s", $role);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return $result['count'] ?? 0;
}

/**
 * Get unread notification count for a user
 */
function getUnreadNotificationCount($conn, $user_id) {
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM notifications WHERE user_id = ? AND is_read = 0");
    if (!$stmt) return 0;
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return $result['count'] ?? 0;
}

/**
 * Get recent notifications for a user
 */
function getRecentNotifications($conn, $user_id, $limit = 5) {
    $stmt = $conn->prepare("SELECT * FROM notifications WHERE user_id = ? ORDER BY created_at DESC LIMIT ?");
    if (!$stmt) return [];
    $stmt->bind_param("ii", $user_id, $limit);
    $stmt->execute();
    $result = $stmt->get_result();
    $notifications = [];
    while ($row = $result->fetch_assoc()) {
        $notifications[] = $row;
    }
    $stmt->close();
    return $notifications;
}

/**
 * Create a notification
 */
function createNotification($conn, $user_id, $type, $title, $message = '', $link = '') {
    $stmt = $conn->prepare("INSERT INTO notifications (user_id, type, title, message, link) VALUES (?, ?, ?, ?, ?)");
    if (!$stmt) return false;
    $stmt->bind_param("issss", $user_id, $type, $title, $message, $link);
    $result = $stmt->execute();
    $stmt->close();
    return $result;
}

/**
 * Get teacher's average rating
 */
function getTeacherRating($conn, $teacher_id) {
    $stmt = $conn->prepare("SELECT AVG(rating) as avg_rating, COUNT(*) as review_count FROM reviews WHERE teacher_id = ?");
    if (!$stmt) return ['avg_rating' => 0, 'review_count' => 0];
    $stmt->bind_param("i", $teacher_id);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return [
        'avg_rating' => round($result['avg_rating'] ?? 0, 1),
        'review_count' => $result['review_count'] ?? 0
    ];
}

/**
 * Get teacher's earnings summary
 */
function getTeacherEarnings($conn, $teacher_id) {
    $stmt = $conn->prepare("
        SELECT 
            COALESCE(SUM(CASE WHEN status = 'paid' THEN net_amount ELSE 0 END), 0) as total_paid,
            COALESCE(SUM(CASE WHEN status = 'pending' THEN net_amount ELSE 0 END), 0) as total_pending,
            COALESCE(SUM(net_amount), 0) as total_earnings
        FROM earnings WHERE teacher_id = ?
    ");
    if (!$stmt) return ['total_paid' => 0, 'total_pending' => 0, 'total_earnings' => 0];
    $stmt->bind_param("i", $teacher_id);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return $result;
}

/**
 * Get student's learning stats
 */
function getStudentStats($conn, $student_id) {
    // Total lessons
    $stmt = $conn->prepare("SELECT COUNT(*) as total FROM bookings WHERE student_id = ?");
    $stmt->bind_param("i", $student_id);
    $stmt->execute();
    $lessons = $stmt->get_result()->fetch_assoc()['total'] ?? 0;
    $stmt->close();
    
    // Unique teachers
    $stmt = $conn->prepare("SELECT COUNT(DISTINCT teacher_id) as total FROM bookings WHERE student_id = ?");
    $stmt->bind_param("i", $student_id);
    $stmt->execute();
    $teachers = $stmt->get_result()->fetch_assoc()['total'] ?? 0;
    $stmt->close();
    
    // Active goals
    $goals = 0;
    $stmt = $conn->prepare("SELECT COUNT(*) as total FROM learning_goals WHERE student_id = ? AND completed = 0");
    if ($stmt) {
        $stmt->bind_param("i", $student_id);
        $stmt->execute();
        $goals = $stmt->get_result()->fetch_assoc()['total'] ?? 0;
        $stmt->close();
    }
    
    // Pending assignments
    $assignments = 0;
    $stmt = $conn->prepare("SELECT COUNT(*) as total FROM assignments WHERE student_id = ? AND status IN ('pending', 'submitted')");
    if ($stmt) {
        $stmt->bind_param("i", $student_id);
        $stmt->execute();
        $assignments = $stmt->get_result()->fetch_assoc()['total'] ?? 0;
        $stmt->close();
    }
    
    return [
        'total_lessons' => $lessons,
        'unique_teachers' => $teachers,
        'active_goals' => $goals,
        'pending_assignments' => $assignments,
        'hours_learned' => $lessons // Assuming 1 hour per lesson
    ];
}

/**
 * Get teacher's student count
 */
function getTeacherStudentCount($conn, $teacher_id) {
    // Only count students who have actually booked lessons with this teacher
    $stmt = $conn->prepare("SELECT COUNT(DISTINCT l.student_id) as count FROM lessons l JOIN users u ON l.student_id = u.id WHERE l.teacher_id = ? AND u.role IN ('student', 'new_student')");
    $stmt->bind_param("i", $teacher_id);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return $result['count'] ?? 0;
}

/**
 * Get pending assignments count for teacher
 */
function getPendingAssignmentsCount($conn, $teacher_id) {
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM assignments WHERE teacher_id = ? AND status = 'submitted'");
    if (!$stmt) return 0;
    $stmt->bind_param("i", $teacher_id);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return $result['count'] ?? 0;
}

/**
 * Get unread messages count
 */
function getUnreadMessagesCount($conn, $user_id) {
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM messages WHERE receiver_id = ? AND is_read = 0");
    if (!$stmt) return 0;
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return $result['count'] ?? 0;
}

/**
 * Format currency
 */
function formatCurrency($amount, $symbol = '$') {
    return $symbol . number_format($amount, 2);
}

/**
 * Format date relative (e.g., "2 hours ago")
 */
function formatRelativeTime($datetime) {
    $now = new DateTime();
    $date = new DateTime($datetime);
    $diff = $now->diff($date);
    
    if ($diff->y > 0) return $diff->y . ' year' . ($diff->y > 1 ? 's' : '') . ' ago';
    if ($diff->m > 0) return $diff->m . ' month' . ($diff->m > 1 ? 's' : '') . ' ago';
    if ($diff->d > 0) return $diff->d . ' day' . ($diff->d > 1 ? 's' : '') . ' ago';
    if ($diff->h > 0) return $diff->h . ' hour' . ($diff->h > 1 ? 's' : '') . ' ago';
    if ($diff->i > 0) return $diff->i . ' minute' . ($diff->i > 1 ? 's' : '') . ' ago';
    return 'Just now';
}

/**
 * Get star rating HTML
 */
function getStarRatingHtml($rating, $showNumber = true) {
    $fullStars = floor($rating);
    $halfStar = ($rating - $fullStars) >= 0.5;
    $emptyStars = 5 - $fullStars - ($halfStar ? 1 : 0);
    
    $html = '<span class="star-rating">';
    for ($i = 0; $i < $fullStars; $i++) {
        $html .= '<i class="fas fa-star"></i>';
    }
    if ($halfStar) {
        $html .= '<i class="fas fa-star-half-alt"></i>';
    }
    for ($i = 0; $i < $emptyStars; $i++) {
        $html .= '<i class="far fa-star"></i>';
    }
    if ($showNumber && $rating > 0) {
        $html .= ' <span class="rating-number">' . number_format($rating, 1) . '</span>';
    }
    $html .= '</span>';
    return $html;
}

/**
 * Get progress bar HTML
 */
function getProgressBarHtml($current, $target, $color = '#0b6cf5') {
    $percentage = $target > 0 ? min(100, ($current / $target) * 100) : 0;
    return '<div class="progress-bar-container">
        <div class="progress-bar-fill" style="width: ' . $percentage . '%; background: ' . $color . ';"></div>
    </div>
    <span class="progress-text">' . $current . ' / ' . $target . '</span>';
}

/**
 * Get notification icon by type
 */
function getNotificationIcon($type) {
    $icons = [
        'booking' => 'fa-calendar-check',
        'message' => 'fa-envelope',
        'review' => 'fa-star',
        'assignment' => 'fa-tasks',
        'payment' => 'fa-dollar-sign',
        'system' => 'fa-bell',
        'reminder' => 'fa-clock',
        'achievement' => 'fa-trophy'
    ];
    return $icons[$type] ?? 'fa-bell';
}

/**
 * Sanitize output for HTML
 */
function h($string) {
    return htmlspecialchars($string ?? '', ENT_QUOTES, 'UTF-8');
}

/**
 * Get asset path - works for both local development and cPanel deployment
 * @param string $asset Relative path from assets directory (e.g., 'styles.css', 'css/dashboard.css', 'logo.png')
 * @return string Correct URL path to asset (always uses forward slashes)
 */
function getAssetPath($asset) {
    // Remove leading slash if present
    $asset = ltrim($asset, '/');
    
    // Build base asset path
    if (strpos($asset, 'assets/') === 0) {
        $assetPath = $asset;
    } else {
        $assetPath = 'assets/' . $asset;
    }
    
    // Get base path from REQUEST_URI - more reliable for subdirectories
    $requestUri = $_SERVER['REQUEST_URI'] ?? '';
    $scriptName = $_SERVER['SCRIPT_NAME'] ?? '';
    
    // Extract the directory path from SCRIPT_NAME
    $basePath = dirname($scriptName);
    $basePath = str_replace('\\', '/', $basePath);
    
    // Handle root case
    if ($basePath === '.' || $basePath === '/' || empty($basePath)) {
        $basePath = '';
    } else {
        // Ensure leading slash and remove trailing
        $basePath = '/' . trim($basePath, '/');
    }
    
    // Check if we're in local development (public/ directory exists)
    // Go up 3 levels from app/Views/components to project root
    $baseDir = dirname(dirname(dirname(__DIR__)));
    $publicDir = $baseDir . DIRECTORY_SEPARATOR . 'public';
    $publicAssetsDir = $publicDir . DIRECTORY_SEPARATOR . 'assets';
    
    // For local development: if public/assets/ directory exists, always use /public/ path
    // This handles the case where files are in public/assets/ directory
    if (is_dir($publicAssetsDir)) {
        return $basePath . '/public/' . $assetPath;
    }
    
    // For cPanel flat structure (files directly in public_html/assets/)
    return $basePath . '/' . $assetPath;
}

/**
 * Check if user is favorite teacher for current student
 */
function isTeacherFavorite($conn, $student_id, $teacher_id) {
    $stmt = $conn->prepare("SELECT id FROM favorite_teachers WHERE student_id = ? AND teacher_id = ?");
    if (!$stmt) return false;
    $stmt->bind_param("ii", $student_id, $teacher_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $isFavorite = $result->num_rows > 0;
    $stmt->close();
    return $isFavorite;
}

/**
 * Get upload directory path for file system operations
 * Works for both localhost (with public/ directory) and cPanel (flat structure)
 * @param string $subdir Subdirectory within uploads (e.g., 'images', 'resources', 'assignments')
 * @return string Absolute file system path to upload directory
 */
function getUploadDir($subdir = 'images') {
    $base_dir = dirname(dirname(dirname(__DIR__)));
    $public_uploads = $base_dir . DIRECTORY_SEPARATOR . 'public' . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . $subdir;
    $flat_uploads = $base_dir . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . $subdir;
    
    // Check which structure exists
    if (is_dir($public_uploads)) {
        return $public_uploads;
    } elseif (is_dir($flat_uploads)) {
        return $flat_uploads;
    } else {
        // Create directory based on what exists at base level
        if (is_dir($base_dir . DIRECTORY_SEPARATOR . 'public')) {
            $target_dir = $public_uploads;
        } else {
            $target_dir = $flat_uploads;
        }
        @mkdir($target_dir, 0755, true);
        return $target_dir;
    }
}

/**
 * Get upload URL path for web access
 * Works for both localhost (with public/ directory) and cPanel (flat structure)
 * @param string $filepath Relative file path from uploads directory
 * @return string URL path to uploaded file
 */
function getUploadUrl($filepath) {
    $filepath = ltrim($filepath, '/');
    $base_dir = dirname(dirname(dirname(__DIR__)));
    $public_uploads = $base_dir . DIRECTORY_SEPARATOR . 'public' . DIRECTORY_SEPARATOR . 'uploads';
    
    // Check if public/uploads exists
    if (is_dir($public_uploads)) {
        return '/uploads/' . $filepath;
    } else {
        // Flat structure
        return '/uploads/' . $filepath;
    }
}

/**
 * Get admin statistics
 */
function getAdminStats($conn) {
    $stats = [];
    
    // User counts
    $stats['students'] = getUserCountByRole($conn, 'student');
    $stats['teachers'] = getUserCountByRole($conn, 'teacher');
    
    // Pending applications
    $result = $conn->query("SELECT COUNT(*) as count FROM users WHERE application_status = 'pending'");
    $stats['pending_apps'] = $result ? $result->fetch_assoc()['count'] : 0;
    
    // Pending profile updates
    $result = $conn->query("SELECT COUNT(*) as count FROM pending_updates");
    $stats['pending_updates'] = $result ? $result->fetch_assoc()['count'] : 0;
    
    // Total bookings
    $result = $conn->query("SELECT COUNT(*) as count FROM bookings");
    $stats['total_bookings'] = $result ? $result->fetch_assoc()['count'] : 0;
    
    // Total revenue (if earnings table exists)
    $result = $conn->query("SELECT COALESCE(SUM(amount), 0) as total FROM earnings");
    $stats['total_revenue'] = $result ? $result->fetch_assoc()['total'] : 0;
    
    // Open support messages
    $result = $conn->query("SELECT COUNT(*) as count FROM support_messages WHERE status = 'open'");
    $stats['open_support'] = $result ? $result->fetch_assoc()['count'] : 0;
    
    // Total pending requests (applications + profile updates)
    $stats['total_pending_requests'] = $stats['pending_apps'] + $stats['pending_updates'];
    
    return $stats;
}

