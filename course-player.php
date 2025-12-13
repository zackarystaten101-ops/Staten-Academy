<?php
session_start();
require_once 'db.php';
require_once __DIR__ . '/app/Views/components/dashboard-functions.php';
require_once __DIR__ . '/app/Models/Course.php';
require_once __DIR__ . '/app/Models/CourseLesson.php';
require_once __DIR__ . '/app/Models/CourseEnrollment.php';
require_once __DIR__ . '/app/Models/CourseProgress.php';

$user_id = $_SESSION['user_id'] ?? null;
$user_role = $_SESSION['user_role'] ?? 'guest';
$course_id = isset($_GET['course']) ? (int)$_GET['course'] : 0;
$lesson_id = isset($_GET['lesson']) ? (int)$_GET['lesson'] : 0;
$is_preview = isset($_GET['preview']) && $_GET['preview'] == '1';

if (!$course_id) {
    header("Location: course-library.php");
    exit();
}

// Initialize models
$courseModel = new Course($conn);
$lessonModel = new CourseLesson($conn);
$enrollmentModel = new CourseEnrollment($conn);
$progressModel = new CourseProgress($conn);

// Get course details
$course = $courseModel->getCourseDetails($course_id);
if (!$course) {
    header("Location: course-library.php");
    exit();
}

// Get all lessons
$lessons_result = $lessonModel->getLessonsByCourse($course_id);
$lessons = [];
while ($lesson = $lessons_result->fetch_assoc()) {
    $lessons[] = $lesson;
}

// Check access
$has_access = false;
$is_enrolled = false;

if ($user_id && $user_role !== 'guest') {
    if ($user_role === 'student') {
        $is_enrolled = $enrollmentModel->isEnrolled($user_id, $course_id);
        $accessible = $enrollmentModel->getAccessibleCourses($user_id);
        while ($acc_course = $accessible->fetch_assoc()) {
            if ($acc_course['id'] == $course_id) {
                $has_access = true;
                break;
            }
        }
        $has_access = $has_access || $is_enrolled;
    }
}

// If preview mode, only show preview lessons
if ($is_preview || (!$has_access && $user_role !== 'student')) {
    $lessons = array_filter($lessons, function($lesson) {
        return $lesson['is_preview'] == 1;
    });
    $lessons = array_values($lessons); // Re-index array
}

// Get current lesson
$current_lesson = null;
if ($lesson_id > 0) {
    foreach ($lessons as $lesson) {
        if ($lesson['id'] == $lesson_id) {
            $current_lesson = $lesson;
            break;
        }
    }
}

// If no lesson specified or lesson not found, use first lesson
if (!$current_lesson && count($lessons) > 0) {
    $current_lesson = $lessons[0];
    $lesson_id = $current_lesson['id'];
}

// Get progress if user is enrolled
$progress = null;
$completed_lessons = [];
if ($user_id && $is_enrolled) {
    $progress = $progressModel->getProgress($user_id, $course_id);
    if ($progress && $progress['completed_lessons']) {
        $completed_lessons = json_decode($progress['completed_lessons'], true) ?? [];
    }
    
    // Update last accessed lesson
    if ($current_lesson) {
        $total_lessons = count($lessons);
        $completed_count = count($completed_lessons);
        $progress_percentage = $total_lessons > 0 ? ($completed_count / $total_lessons) * 100 : 0;
        
        $progressModel->updateProgress(
            $user_id,
            $course_id,
            $current_lesson['id'],
            $completed_lessons,
            $progress_percentage
        );
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo h($course['title']); ?> - Staten Academy</title>
    <link rel="stylesheet" href="<?php echo getAssetPath('styles.css'); ?>">
    <link rel="stylesheet" href="<?php echo getAssetPath('css/dashboard.css'); ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .player-container {
            display: flex;
            height: calc(100vh - 80px);
            margin-top: 80px;
        }
        .player-sidebar {
            width: 350px;
            background: #f8f9fa;
            border-right: 1px solid #ddd;
            overflow-y: auto;
            padding: 20px;
        }
        .player-main {
            flex: 1;
            display: flex;
            flex-direction: column;
            background: #000;
        }
        .video-container {
            flex: 1;
            display: flex;
            align-items: center;
            justify-content: center;
            background: #000;
            position: relative;
        }
        .video-wrapper {
            width: 100%;
            max-width: 1200px;
            position: relative;
            padding-bottom: 56.25%; /* 16:9 aspect ratio */
            height: 0;
        }
        .video-wrapper iframe,
        .video-wrapper video {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
        }
        .lesson-info {
            background: white;
            padding: 20px;
            border-top: 1px solid #ddd;
        }
        .lesson-list {
            list-style: none;
            padding: 0;
            margin: 0;
        }
        .lesson-item {
            padding: 12px;
            border-bottom: 1px solid #e0e0e0;
            cursor: pointer;
            transition: background 0.2s;
        }
        .lesson-item:hover {
            background: #f0f0f0;
        }
        .lesson-item.active {
            background: #e3f2fd;
            border-left: 4px solid #004080;
        }
        .lesson-item.completed {
            color: #28a745;
        }
        .lesson-item.completed::before {
            content: "✓ ";
            font-weight: bold;
        }
        .course-header {
            padding: 15px;
            background: white;
            border-bottom: 1px solid #ddd;
        }
        .progress-bar {
            height: 4px;
            background: #e0e0e0;
            margin-top: 10px;
            border-radius: 2px;
            overflow: hidden;
        }
        .progress-fill {
            height: 100%;
            background: #004080;
            transition: width 0.3s;
        }
        .locked-overlay {
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0,0,0,0.8);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            flex-direction: column;
        }
    </style>
</head>
<body>
    <?php if ($user_id): ?>
        <?php include __DIR__ . '/app/Views/components/dashboard-header.php'; ?>
    <?php else: ?>
        <?php include __DIR__ . '/header-user.php'; ?>
    <?php endif; ?>

    <div class="player-container">
        <!-- Sidebar with lesson list -->
        <div class="player-sidebar">
            <div class="course-header">
                <h2 style="margin: 0 0 10px 0; font-size: 1.2rem;"><?php echo h($course['title']); ?></h2>
                <?php if ($progress): ?>
                    <div class="progress-bar">
                        <div class="progress-fill" style="width: <?php echo $progress['progress_percentage']; ?>%;"></div>
                    </div>
                    <p style="margin: 5px 0 0 0; font-size: 0.85rem; color: #666;">
                        <?php echo number_format($progress['progress_percentage'], 1); ?>% Complete
                    </p>
                <?php endif; ?>
            </div>
            <ul class="lesson-list">
                <?php foreach ($lessons as $index => $lesson): ?>
                    <?php
                    $is_completed = in_array($lesson['id'], $completed_lessons);
                    $is_active = $lesson['id'] == $lesson_id;
                    $is_locked = !$has_access && !$is_preview && !$lesson['is_preview'];
                    ?>
                    <li class="lesson-item <?php echo $is_active ? 'active' : ''; ?> <?php echo $is_completed ? 'completed' : ''; ?>"
                        onclick="<?php echo $is_locked ? '' : "location.href='course-player.php?course=$course_id&lesson={$lesson['id']}" . ($is_preview ? '&preview=1' : '') . "'"; ?>">
                        <div style="display: flex; justify-content: space-between; align-items: center;">
                            <div>
                                <strong><?php echo h($lesson['title']); ?></strong>
                                <?php if ($lesson['is_preview']): ?>
                                    <span style="background: #ffc107; color: #000; padding: 2px 6px; border-radius: 3px; font-size: 0.7rem; margin-left: 5px;">PREVIEW</span>
                                <?php endif; ?>
                                <?php if ($is_locked): ?>
                                    <i class="fas fa-lock" style="margin-left: 5px; color: #999;"></i>
                                <?php endif; ?>
                            </div>
                            <?php if ($lesson['duration_minutes'] > 0): ?>
                                <span style="color: #999; font-size: 0.85rem;">
                                    <i class="fas fa-clock"></i> <?php echo $lesson['duration_minutes']; ?>m
                                </span>
                            <?php endif; ?>
                        </div>
                    </li>
                <?php endforeach; ?>
            </ul>
            <?php if (!$has_access && !$is_preview): ?>
                <div style="padding: 20px; background: #fff3cd; border-radius: 5px; margin-top: 20px;">
                    <p style="margin: 0; font-size: 0.9rem; color: #856404;">
                        <i class="fas fa-info-circle"></i> Upgrade to access all lessons in this course.
                    </p>
                    <a href="payment.php" class="btn-primary" style="display: block; text-align: center; margin-top: 10px;">
                        Upgrade Now
                    </a>
                </div>
            <?php endif; ?>
        </div>

        <!-- Main video player area -->
        <div class="player-main">
            <?php if ($current_lesson): ?>
                <div class="video-container">
                    <?php if (!$has_access && !$is_preview && !$current_lesson['is_preview']): ?>
                        <div class="locked-overlay">
                            <i class="fas fa-lock" style="font-size: 4rem; margin-bottom: 20px;"></i>
                            <h2>This lesson is locked</h2>
                            <p>Upgrade your account to access this lesson</p>
                            <a href="payment.php" class="btn-primary" style="margin-top: 20px;">
                                Upgrade Now
                            </a>
                        </div>
                    <?php else: ?>
                        <div class="video-wrapper">
                            <?php if ($current_lesson['video_type'] === 'youtube'): ?>
                                <?php
                                // Extract YouTube video ID
                                $youtube_id = '';
                                if (preg_match('/(?:youtube\.com\/(?:[^\/]+\/.+\/|(?:v|e(?:mbed)?)\/|.*[?&]v=)|youtu\.be\/)([^"&?\/\s]{11})/', $current_lesson['video_url'], $matches)) {
                                    $youtube_id = $matches[1];
                                }
                                ?>
                                <iframe src="https://www.youtube.com/embed/<?php echo $youtube_id; ?>?rel=0" 
                                        frameborder="0" 
                                        allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture" 
                                        allowfullscreen></iframe>
                            <?php elseif ($current_lesson['video_type'] === 'vimeo'): ?>
                                <?php
                                // Extract Vimeo video ID
                                $vimeo_id = '';
                                if (preg_match('/vimeo\.com\/(\d+)/', $current_lesson['video_url'], $matches)) {
                                    $vimeo_id = $matches[1];
                                }
                                ?>
                                <iframe src="https://player.vimeo.com/video/<?php echo $vimeo_id; ?>?title=0&byline=0&portrait=0" 
                                        frameborder="0" 
                                        allow="autoplay; fullscreen; picture-in-picture" 
                                        allowfullscreen></iframe>
                            <?php else: ?>
                                <video controls style="width: 100%; height: 100%;">
                                    <source src="<?php echo h($current_lesson['video_url']); ?>" type="video/mp4">
                                    Your browser does not support the video tag.
                                </video>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                </div>
                <div class="lesson-info">
                    <h2 style="margin: 0 0 10px 0;"><?php echo h($current_lesson['title']); ?></h2>
                    <?php if ($current_lesson['description']): ?>
                        <p style="color: #666; margin: 10px 0;"><?php echo nl2br(h($current_lesson['description'])); ?></p>
                    <?php endif; ?>
                    <div style="display: flex; gap: 20px; margin-top: 20px;">
                        <?php if ($index > 0): ?>
                            <a href="course-player.php?course=<?php echo $course_id; ?>&lesson=<?php echo $lessons[$index-1]['id']; ?><?php echo $is_preview ? '&preview=1' : ''; ?>" class="btn-outline">
                                <i class="fas fa-arrow-left"></i> Previous
                            </a>
                        <?php endif; ?>
                        <?php if ($index < count($lessons) - 1): ?>
                            <a href="course-player.php?course=<?php echo $course_id; ?>&lesson=<?php echo $lessons[$index+1]['id']; ?><?php echo $is_preview ? '&preview=1' : ''; ?>" class="btn-primary">
                                Next <i class="fas fa-arrow-right"></i>
                            </a>
                        <?php endif; ?>
                        <?php if ($is_enrolled && !in_array($current_lesson['id'], $completed_lessons)): ?>
                            <button onclick="markComplete(<?php echo $current_lesson['id']; ?>)" class="btn-primary">
                                <i class="fas fa-check"></i> Mark Complete
                            </button>
                        <?php endif; ?>
                    </div>
                </div>
            <?php else: ?>
                <div class="video-container" style="color: white; text-align: center;">
                    <div>
                        <i class="fas fa-exclamation-circle" style="font-size: 4rem; margin-bottom: 20px;"></i>
                        <h2>No lessons available</h2>
                        <p>This course doesn't have any lessons yet.</p>
                        <a href="course-library.php" class="btn-primary" style="margin-top: 20px; display: inline-block;">
                            Back to Library
                        </a>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <script>
    function markComplete(lessonId) {
        fetch('api/course-progress.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                action: 'mark_complete',
                course_id: <?php echo $course_id; ?>,
                lesson_id: lessonId
            })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                location.reload();
            } else {
                alert('Error marking lesson as complete');
            }
        });
    }
    </script>
</body>
</html>











