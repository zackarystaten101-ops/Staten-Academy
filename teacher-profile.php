<?php
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

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/app/Views/components/dashboard-functions.php';
require_once __DIR__ . '/app/Services/TeacherService.php';

// Ensure getAssetPath function is available
if (!function_exists('getAssetPath')) {
    require_once __DIR__ . '/app/Views/components/dashboard-functions.php';
}

// Get teacher ID from query parameter
$teacher_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if (!$teacher_id) {
    header("Location: index.php");
    exit();
}

// Get teacher profile
$teacherService = new TeacherService($conn);
$teacher = $teacherService->getTeacherProfile($teacher_id);

if (!$teacher || $teacher['role'] !== 'teacher') {
    header("Location: index.php");
    exit();
}

// Get teacher reviews
$reviews = $teacherService->getTeacherReviews($teacher_id, 5);

// Get teacher availability (next 30 days)
$start_date = date('Y-m-d');
$end_date = date('Y-m-d', strtotime('+30 days'));
$availability = $teacherService->getTeacherAvailability($teacher_id, $start_date, $end_date);

// Get user info
$user_role = $_SESSION['user_role'] ?? 'guest';
$user_id = $_SESSION['user_id'] ?? null;

// Check if user has used trial
$trial_used = false;
if ($user_id) {
    $trial_check = $conn->prepare("SELECT trial_used FROM users WHERE id = ?");
    $trial_check->bind_param("i", $user_id);
    $trial_check->execute();
    $trial_result = $trial_check->get_result();
    if ($trial_result->num_rows > 0) {
        $user_data = $trial_result->fetch_assoc();
        $trial_used = (bool)$user_data['trial_used'];
    }
    $trial_check->close();
}

// Check if student can message this teacher
$can_message = false;
if ($user_id && ($user_role === 'student' || $user_role === 'new_student')) {
    // Check if student has booked trial or lesson with this teacher
    $interaction_check = $conn->prepare("SELECT id FROM lessons WHERE student_id = ? AND teacher_id = ? LIMIT 1");
    $interaction_check->bind_param("ii", $user_id, $teacher_id);
    $interaction_check->execute();
    $interaction_result = $interaction_check->get_result();
    $can_message = $interaction_result->num_rows > 0;
    $interaction_check->close();
    
    // Also check trial_lessons
    if (!$can_message) {
        $trial_check = $conn->prepare("SELECT id FROM trial_lessons WHERE student_id = ? AND teacher_id = ? LIMIT 1");
        $trial_check->bind_param("ii", $user_id, $teacher_id);
        $trial_check->execute();
        $trial_result = $trial_check->get_result();
        $can_message = $trial_result->num_rows > 0;
        $trial_check->close();
    }
}

// Set page title for header
$page_title = 'Teacher Profile: ' . htmlspecialchars($teacher['name']);
$_SESSION['profile_pic'] = $user['profile_pic'] ?? getAssetPath('images/placeholder-teacher.svg');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($teacher['name']); ?> - Staten Academy</title>
    <link rel="stylesheet" href="<?php echo getAssetPath('styles.css'); ?>">
    <link rel="stylesheet" href="<?php echo getAssetPath('css/mobile.css'); ?>">
    <link rel="stylesheet" href="<?php echo getAssetPath('css/dashboard.css'); ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        body {
            background: #f5f7fa;
        }
        .teacher-profile-header {
            background: linear-gradient(135deg, #004080 0%, #0b6cf5 100%);
            color: white;
            padding: 60px 20px;
        }
        .teacher-profile-content {
            max-width: 1200px;
            margin: -40px auto 40px;
            padding: 0 20px;
            position: relative;
            z-index: 10;
        }
        .profile-card {
            background: white;
            border-radius: 16px;
            padding: 40px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.1);
            margin-bottom: 30px;
        }
        .profile-header {
            display: flex;
            gap: 30px;
            align-items: flex-start;
            margin-bottom: 30px;
        }
        .profile-photo {
            width: 150px;
            height: 150px;
            border-radius: 50%;
            object-fit: cover;
            border: 4px solid #0b6cf5;
        }
        .profile-info h1 {
            font-size: 2.5rem;
            color: #004080;
            margin-bottom: 10px;
        }
        .profile-specialty {
            color: #666;
            font-size: 1.1rem;
            margin-bottom: 15px;
        }
        .profile-rating {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 15px;
        }
        .profile-rating .stars {
            color: #ffa500;
            font-size: 1.2rem;
        }
        .profile-stats {
            display: flex;
            gap: 30px;
            margin-top: 20px;
        }
        .profile-stat {
            text-align: center;
        }
        .profile-stat-value {
            font-size: 1.5rem;
            font-weight: 700;
            color: #004080;
        }
        .profile-stat-label {
            font-size: 0.9rem;
            color: #666;
        }
        .profile-actions {
            display: flex;
            gap: 15px;
            flex-wrap: wrap;
            margin-top: 20px;
        }
        .btn-action {
            padding: 12px 30px;
            border-radius: 8px;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            border: none;
            text-decoration: none;
            display: inline-block;
            transition: all 0.3s;
        }
        .btn-book-trial {
            background: #28a745;
            color: white;
        }
        .btn-book-trial:hover:not(:disabled) {
            background: #218838;
        }
        .btn-book-trial:disabled {
            background: #ccc;
            cursor: not-allowed;
        }
        .btn-book-lesson {
            background: #0b6cf5;
            color: white;
        }
        .btn-book-lesson:hover {
            background: #004080;
        }
        .btn-message {
            background: #6c757d;
            color: white;
        }
        .btn-message:hover {
            background: #5a6268;
        }
        .btn-message:disabled {
            background: #ccc;
            cursor: not-allowed;
        }
        .profile-bio {
            color: #555;
            line-height: 1.8;
            font-size: 1.05rem;
            margin-bottom: 30px;
        }
        .video-section {
            margin: 30px 0;
        }
        .video-container {
            position: relative;
            padding-bottom: 56.25%;
            height: 0;
            overflow: hidden;
            border-radius: 12px;
        }
        .video-container iframe {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
        }
        .reviews-section {
            margin-top: 30px;
        }
        .review-item {
            padding: 20px;
            border-bottom: 1px solid #e0e0e0;
        }
        .review-item:last-child {
            border-bottom: none;
        }
        .review-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 10px;
        }
        .review-student {
            font-weight: 600;
            color: #333;
        }
        .review-rating {
            color: #ffa500;
        }
        .review-text {
            color: #555;
            line-height: 1.6;
        }
        .calendar-section {
            margin-top: 30px;
        }
        .calendar-grid {
            display: grid;
            grid-template-columns: repeat(7, 1fr);
            gap: 10px;
            margin-top: 20px;
        }
        .calendar-day {
            padding: 15px;
            text-align: center;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.3s;
        }
        .calendar-day:hover {
            border-color: #0b6cf5;
            background: #f0f7ff;
        }
        .calendar-day.available {
            background: #d4edda;
            border-color: #28a745;
        }
        .calendar-day.booked {
            background: #f8d7da;
            border-color: #dc3545;
            cursor: not-allowed;
        }
        .time-slots {
            margin-top: 20px;
        }
        .time-slot {
            display: inline-block;
            padding: 10px 20px;
            margin: 5px;
            background: #e9ecef;
            border-radius: 6px;
            cursor: pointer;
            transition: all 0.3s;
        }
        .time-slot:hover {
            background: #0b6cf5;
            color: white;
        }
        .time-slot.selected {
            background: #004080;
            color: white;
        }
        @media (max-width: 768px) {
            .profile-header {
                flex-direction: column;
                text-align: center;
            }
            .profile-actions {
                flex-direction: column;
            }
            .btn-action {
                width: 100%;
            }
            .calendar-grid {
                grid-template-columns: repeat(3, 1fr);
            }
        }
    </style>
</head>
<body>
    <?php 
    $user = ['id' => $user_id, 'name' => $_SESSION['user_name'] ?? '', 'role' => $user_role];
    include __DIR__ . '/app/Views/components/dashboard-header.php'; 
    ?>
    
    <div class="teacher-profile-header">
        <div style="max-width: 1200px; margin: 0 auto;">
            <h1><?php echo htmlspecialchars($teacher['name']); ?></h1>
            <?php if ($teacher['specialty']): ?>
                <p style="font-size: 1.2rem; margin-top: 10px;"><?php echo htmlspecialchars($teacher['specialty']); ?></p>
            <?php endif; ?>
        </div>
    </div>
    
    <div class="teacher-profile-content">
        <div class="profile-card">
            <div class="profile-header">
                <img src="<?php echo htmlspecialchars($teacher['profile_pic'] ?? getAssetPath('images/placeholder-teacher.svg')); ?>" 
                     alt="<?php echo htmlspecialchars($teacher['name']); ?>" 
                     class="profile-photo">
                <div class="profile-info" style="flex: 1;">
                    <h1><?php echo htmlspecialchars($teacher['name']); ?></h1>
                    <?php if ($teacher['specialty']): ?>
                        <p class="profile-specialty"><?php echo htmlspecialchars($teacher['specialty']); ?></p>
                    <?php endif; ?>
                    
                    <div class="profile-rating">
                        <?php if ($teacher['avg_rating']): ?>
                            <span class="stars">
                                <?php 
                                $rating = floatval($teacher['avg_rating']);
                                $full_stars = floor($rating);
                                $half_star = ($rating - $full_stars) >= 0.5;
                                for ($i = 0; $i < $full_stars; $i++) {
                                    echo '<i class="fas fa-star"></i>';
                                }
                                if ($half_star) {
                                    echo '<i class="fas fa-star-half-alt"></i>';
                                }
                                for ($i = $full_stars + ($half_star ? 1 : 0); $i < 5; $i++) {
                                    echo '<i class="far fa-star"></i>';
                                }
                                ?>
                            </span>
                            <span><?php echo number_format($rating, 1); ?> (<?php echo intval($teacher['review_count']); ?> reviews)</span>
                        <?php else: ?>
                            <span>No ratings yet</span>
                        <?php endif; ?>
                    </div>
                    
                    <div class="profile-stats">
                        <div class="profile-stat">
                            <div class="profile-stat-value"><?php echo intval($teacher['total_lessons']); ?></div>
                            <div class="profile-stat-label">Lessons Taught</div>
                        </div>
                        <?php if ($teacher['hourly_rate']): ?>
                            <div class="profile-stat">
                                <div class="profile-stat-value">$<?php echo number_format($teacher['hourly_rate'], 0); ?></div>
                                <div class="profile-stat-label">Per Hour</div>
                            </div>
                        <?php endif; ?>
                    </div>
                    
                    <div class="profile-actions">
                        <?php if ($user_role === 'student' || $user_role === 'new_student'): ?>
                            <button class="btn-action btn-book-trial" 
                                    onclick="bookTrial(<?php echo intval($teacher_id); ?>)" 
                                    <?php echo $trial_used ? 'disabled title="Trial already used"' : ''; ?>>
                                <i class="fas fa-gift"></i> <?php echo $trial_used ? 'Trial Used' : 'Book Trial ($25)'; ?>
                            </button>
                            <button class="btn-action btn-book-lesson" onclick="showBookingModal()">
                                <i class="fas fa-calendar-check"></i> Book Lesson
                            </button>
                            <button class="btn-action btn-message" 
                                    onclick="sendMessage(<?php echo intval($teacher_id); ?>)" 
                                    <?php echo !$can_message ? 'disabled title="Book a lesson first to message this teacher"' : ''; ?>>
                                <i class="fas fa-envelope"></i> Send Message
                            </button>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            
            <?php if ($teacher['bio'] || $teacher['about_text']): ?>
                <div class="profile-bio">
                    <h2 style="color: #004080; margin-bottom: 15px;">About</h2>
                    <?php if ($teacher['about_text']): ?>
                        <p><?php echo nl2br(htmlspecialchars($teacher['about_text'])); ?></p>
                    <?php elseif ($teacher['bio']): ?>
                        <p><?php echo nl2br(htmlspecialchars($teacher['bio'])); ?></p>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
            
            <?php if ($teacher['video_url']): ?>
                <div class="video-section">
                    <h2 style="color: #004080; margin-bottom: 15px;">Introduction Video</h2>
                    <div class="video-container">
                        <?php 
                        // Convert YouTube URL to embed format if needed
                        $video_url = $teacher['video_url'];
                        if (strpos($video_url, 'youtube.com/watch') !== false) {
                            parse_str(parse_url($video_url, PHP_URL_QUERY), $params);
                            if (isset($params['v'])) {
                                $video_url = 'https://www.youtube.com/embed/' . $params['v'];
                            }
                        } elseif (strpos($video_url, 'youtu.be/') !== false) {
                            $video_id = substr(parse_url($video_url, PHP_URL_PATH), 1);
                            $video_url = 'https://www.youtube.com/embed/' . $video_id;
                        }
                        ?>
                        <iframe src="<?php echo htmlspecialchars($video_url); ?>" 
                                frameborder="0" 
                                allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture" 
                                allowfullscreen></iframe>
                    </div>
                </div>
            <?php endif; ?>
            
            <?php if (!empty($reviews)): ?>
                <div class="reviews-section">
                    <h2 style="color: #004080; margin-bottom: 20px;">Reviews</h2>
                    <?php foreach ($reviews as $review): ?>
                        <div class="review-item">
                            <div class="review-header">
                                <span class="review-student"><?php echo htmlspecialchars($review['student_name']); ?></span>
                                <span class="review-rating">
                                    <?php 
                                    for ($i = 0; $i < intval($review['rating']); $i++) {
                                        echo '<i class="fas fa-star"></i>';
                                    }
                                    ?>
                                </span>
                            </div>
                            <?php if ($review['review_text']): ?>
                                <p class="review-text"><?php echo nl2br(htmlspecialchars($review['review_text'])); ?></p>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
        
        <?php if ($user_role === 'student' || $user_role === 'new_student'): ?>
            <div class="profile-card calendar-section">
                <h2 style="color: #004080; margin-bottom: 20px;">Book a Lesson</h2>
                <p>Select a date and time to book a lesson with <?php echo htmlspecialchars($teacher['name']); ?>.</p>
                
                <div id="booking-calendar" style="margin-top: 30px;">
                    <!-- Calendar will be populated by JavaScript -->
                    <div class="calendar-grid" id="calendar-grid">
                        <!-- Calendar days will be inserted here -->
                    </div>
                    <div class="time-slots" id="time-slots" style="display: none;">
                        <h3>Available Times</h3>
                        <div id="time-slots-list"></div>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>
    
    <?php include __DIR__ . '/app/Views/components/footer.php'; ?>
    
    <script>
    let selectedDate = null;
    let selectedTime = null;
    
    function bookTrial(teacherId) {
        if (confirm('Book a trial lesson with this teacher for $25?')) {
            window.location.href = 'create_checkout_session.php?type=trial&teacher_id=' + teacherId;
        }
    }
    
    function sendMessage(teacherId) {
        window.location.href = 'message_threads.php?user_id=' + teacherId;
    }
    
    function showBookingModal() {
        document.getElementById('booking-calendar').scrollIntoView({ behavior: 'smooth' });
    }
    
    // Simple calendar implementation
    function initCalendar() {
        const calendarGrid = document.getElementById('calendar-grid');
        if (!calendarGrid) return;
        
        const today = new Date();
        const currentMonth = today.getMonth();
        const currentYear = today.getFullYear();
        
        // Get first day of month
        const firstDay = new Date(currentYear, currentMonth, 1);
        const lastDay = new Date(currentYear, currentMonth + 1, 0);
        
        // Clear grid
        calendarGrid.innerHTML = '';
        
        // Add day headers
        const dayHeaders = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];
        dayHeaders.forEach(day => {
            const header = document.createElement('div');
            header.textContent = day;
            header.style.fontWeight = 'bold';
            header.style.padding = '10px';
            calendarGrid.appendChild(header);
        });
        
        // Add empty cells for days before month starts
        for (let i = 0; i < firstDay.getDay(); i++) {
            const empty = document.createElement('div');
            calendarGrid.appendChild(empty);
        }
        
        // Add days of month
        for (let day = 1; day <= lastDay.getDate(); day++) {
            const dayElement = document.createElement('div');
            dayElement.className = 'calendar-day';
            dayElement.textContent = day;
            
            const dateStr = `${currentYear}-${String(currentMonth + 1).padStart(2, '0')}-${String(day).padStart(2, '0')}`;
            const dateObj = new Date(currentYear, currentMonth, day);
            
            // Disable past dates
            if (dateObj < today) {
                dayElement.style.opacity = '0.5';
                dayElement.style.cursor = 'not-allowed';
            } else {
                dayElement.onclick = () => selectDate(dateStr, dayElement);
            }
            
            calendarGrid.appendChild(dayElement);
        }
    }
    
    function selectDate(dateStr, element) {
        // Remove previous selection
        document.querySelectorAll('.calendar-day').forEach(day => {
            day.classList.remove('selected');
        });
        
        element.classList.add('selected');
        selectedDate = dateStr;
        
        // Load available times for this date
        loadAvailableTimes(dateStr);
    }
    
    function loadAvailableTimes(dateStr) {
        const timeSlotsDiv = document.getElementById('time-slots');
        const timeSlotsList = document.getElementById('time-slots-list');
        
        if (!timeSlotsDiv || !timeSlotsList) return;
        
        timeSlotsDiv.style.display = 'block';
        timeSlotsList.innerHTML = '<p>Loading available times...</p>';
        
        // Fetch available times from server
        fetch(`api/teacher-availability.php?teacher_id=<?php echo intval($teacher_id); ?>&date=${dateStr}`)
            .then(response => response.json())
            .then(data => {
                if (data.success && data.times && data.times.length > 0) {
                    timeSlotsList.innerHTML = '';
                    data.times.forEach(time => {
                        const timeSlot = document.createElement('div');
                        timeSlot.className = 'time-slot';
                        timeSlot.textContent = time;
                        timeSlot.onclick = () => bookLesson(dateStr, time);
                        timeSlotsList.appendChild(timeSlot);
                    });
                } else {
                    timeSlotsList.innerHTML = '<p>No available times for this date.</p>';
                }
            })
            .catch(error => {
                console.error('Error loading times:', error);
                timeSlotsList.innerHTML = '<p>Error loading available times. Please try again.</p>';
            });
    }
    
    function bookLesson(date, time) {
        if (!confirm(`Book lesson on ${date} at ${time}?`)) {
            return;
        }
        
        // Call booking API
        fetch('book-lesson-api.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({
                teacher_id: <?php echo intval($teacher_id); ?>,
                lesson_date: date,
                start_time: time,
                end_time: addHour(time)
            })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert('Lesson booked successfully!');
                window.location.reload();
            } else {
                alert('Error: ' + (data.error || 'Failed to book lesson'));
            }
        })
        .catch(error => {
            console.error('Error booking lesson:', error);
            alert('Error booking lesson. Please try again.');
        });
    }
    
    function addHour(time) {
        const [hours, minutes] = time.split(':');
        const newHour = (parseInt(hours) + 1) % 24;
        return `${String(newHour).padStart(2, '0')}:${minutes}`;
    }
    
    // Initialize calendar on page load
    document.addEventListener('DOMContentLoaded', initCalendar);
    </script>
</body>
</html>



