<?php
session_start();
require_once 'db.php';
require_once 'google-calendar-config.php';

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
    header("Location: login.php");
    exit();
}

$api = new GoogleCalendarAPI($conn);
$admin_id = $_SESSION['user_id'];
$filter_teacher = $_GET['teacher'] ?? null;
$view_type = $_GET['view'] ?? 'all'; // 'all' or 'individual'

// Fetch all teachers
$stmt = $conn->prepare("SELECT id, name, email FROM users WHERE role='teacher' ORDER BY name");
$stmt->execute();
$all_teachers = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$teachers_data = [];
$selected_teacher = null;

if ($filter_teacher) {
    // Get specific teacher's schedule
    $selected_teacher = (int)$filter_teacher;
    $stmt = $conn->prepare("SELECT id, name, email FROM users WHERE id = ? AND role='teacher'");
    $stmt->bind_param("i", $selected_teacher);
    $stmt->execute();
    $teacher_info = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if ($teacher_info) {
        // Get availability slots
        $stmt = $conn->prepare("
            SELECT * FROM teacher_availability 
            WHERE teacher_id = ? AND is_available = 1
            ORDER BY FIELD(day_of_week, 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'), start_time
        ");
        $stmt->bind_param("i", $selected_teacher);
        $stmt->execute();
        $availability = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();

        // Get upcoming lessons
        $stmt = $conn->prepare("
            SELECT l.*, u.name as student_name, u.email as student_email 
            FROM lessons l 
            JOIN users u ON l.student_id = u.id 
            WHERE l.teacher_id = ? AND l.lesson_date >= CURDATE() AND l.status = 'scheduled'
            ORDER BY l.lesson_date, l.start_time
        ");
        $stmt->bind_param("i", $selected_teacher);
        $stmt->execute();
        $lessons = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();

        $teacher_info['availability'] = $availability;
        $teacher_info['lessons'] = $lessons;
        $teachers_data[] = $teacher_info;
    }
} else {
    // Get all teachers' schedules
    foreach ($all_teachers as $teacher) {
        // Get availability
        $stmt = $conn->prepare("
            SELECT * FROM teacher_availability 
            WHERE teacher_id = ? AND is_available = 1
            ORDER BY FIELD(day_of_week, 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'), start_time
        ");
        $stmt->bind_param("i", $teacher['id']);
        $stmt->execute();
        $availability = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();

        // Get upcoming lessons (next 7 days)
        $stmt = $conn->prepare("
            SELECT l.*, u.name as student_name, u.email as student_email 
            FROM lessons l 
            JOIN users u ON l.student_id = u.id 
            WHERE l.teacher_id = ? AND l.lesson_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 7 DAY) AND l.status = 'scheduled'
            ORDER BY l.lesson_date, l.start_time
        ");
        $stmt->bind_param("i", $teacher['id']);
        $stmt->execute();
        $lessons = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();

        $teacher['availability'] = $availability;
        $teacher['lessons'] = $lessons;
        $teachers_data[] = $teacher;
    }
}

// Fetch user profile for header
$stmt = $conn->prepare("SELECT name, profile_pic FROM users WHERE id = ?");
$stmt->bind_param("i", $admin_id);
$stmt->execute();
$admin_profile = $stmt->get_result()->fetch_assoc();
$stmt->close();
require_once __DIR__ . '/app/Views/components/dashboard-functions.php';
if (!function_exists('getAssetPath')) {
    function getAssetPath($asset) {
        $asset = ltrim($asset, '/');
        if (strpos($asset, 'assets/') === 0) {
            $assetPath = $asset;
        } else {
            $assetPath = 'assets/' . $asset;
        }
        return '/' . $assetPath;
    }
}
$_SESSION['profile_pic'] = $admin_profile['profile_pic'] ?? getAssetPath('images/placeholder-teacher.svg');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Teacher Schedules - Staten Academy Admin</title>
    <link rel="stylesheet" href="styles.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        body { background: #f4f4f9; margin: 0; display: flex; flex-direction: column; height: 100vh; font-family: Arial, sans-serif; }
        .header-bar { background: #004080; color: white; padding: 15px 20px; display: flex; align-items: center; justify-content: space-between; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        .header-bar h2 { margin: 0; }
        .main-wrapper { display: flex; flex: 1; overflow: hidden; }
        .sidebar { width: 250px; background: #2c3e50; color: white; padding-top: 20px; overflow-y: auto; }
        .sidebar a, .sidebar button { display: block; width: 100%; text-align: left; padding: 12px 20px; color: #adb5bd; text-decoration: none; border: none; background: none; cursor: pointer; transition: 0.2s; }
        .sidebar a:hover, .sidebar a.active, .sidebar button:hover { background: #34495e; color: white; }
        .sidebar h3 { text-align: center; margin: 0 0 20px 0; padding: 10px 0; color: white; font-size: 1rem; }
        .sidebar hr { border: none; border-top: 1px solid #444; margin: 15px 0; }
        .content { flex: 1; overflow-y: auto; padding: 30px; }
        .container { max-width: 1400px; }

        .card { background: white; padding: 25px; border-radius: 8px; box-shadow: 0 2px 8px rgba(0,0,0,0.1); margin-bottom: 20px; }
        .card h3 { margin-top: 0; color: #004080; border-bottom: 2px solid #0b6cf5; padding-bottom: 10px; }

        .filter-section { display: grid; grid-template-columns: 1fr auto; gap: 15px; align-items: flex-end; margin-bottom: 20px; }
        select { padding: 10px; border: 1px solid #ddd; border-radius: 4px; }
        button { padding: 10px 20px; background: #0b6cf5; color: white; border: none; border-radius: 4px; cursor: pointer; font-weight: bold; }
        button:hover { background: #004080; }

        .teacher-section { margin-bottom: 40px; padding: 20px; background: #f8f9fa; border-radius: 8px; border-left: 4px solid #0b6cf5; }
        .teacher-section h4 { margin-top: 0; color: #004080; font-size: 1.2rem; display: flex; align-items: center; gap: 10px; }
        .teacher-section-content { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-top: 15px; }

        .availability-box { background: white; padding: 15px; border-radius: 5px; }
        .availability-box h5 { margin: 0 0 10px 0; color: #333; font-size: 1rem; }
        .slot-item { padding: 8px; background: #d4edda; border-left: 3px solid #28a745; margin-bottom: 5px; border-radius: 3px; font-size: 14px; }
        .slot-item strong { display: block; margin-bottom: 2px; }
        .no-data { color: #999; font-style: italic; padding: 10px; }

        .lessons-box { background: white; padding: 15px; border-radius: 5px; }
        .lessons-box h5 { margin: 0 0 10px 0; color: #333; font-size: 1rem; }
        .lesson-item { padding: 10px; background: #e7f3ff; border-left: 3px solid #0b6cf5; margin-bottom: 8px; border-radius: 3px; font-size: 14px; }
        .lesson-item strong { display: block; margin-bottom: 3px; }
        .lesson-item small { color: #666; }

        .stats { display: grid; grid-template-columns: repeat(3, 1fr); gap: 15px; margin-bottom: 20px; }
        .stat-card { background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 8px rgba(0,0,0,0.1); text-align: center; }
        .stat-card .number { font-size: 2rem; font-weight: bold; color: #0b6cf5; }
        .stat-card .label { color: #666; font-size: 0.9rem; margin-top: 5px; }

        @media (max-width: 768px) {
            .sidebar { width: 200px; }
            .teacher-section-content { grid-template-columns: 1fr; }
            .stats { grid-template-columns: 1fr; }
            .filter-section { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>
    <header class="header-bar">
        <h2><i class="fas fa-calendar"></i> Teacher Schedules</h2>
        <div><?php echo htmlspecialchars($admin_profile['name']); ?></div>
    </header>

    <div class="main-wrapper">
        <div class="sidebar">
            <h3>Admin Panel</h3>
            <a href="admin-dashboard.php"><i class="fas fa-home"></i> Dashboard</a>
            <a href="admin-schedule-view.php" class="active"><i class="fas fa-calendar"></i> Schedules</a>
            <a href="classroom.php"><i class="fas fa-book"></i> Classroom</a>
            <hr>
            <a href="message_threads.php"><i class="fas fa-comments"></i> Messages</a>
            <a href="support_contact.php"><i class="fas fa-headset"></i> Support</a>
            <hr>
            <a href="index.php"><i class="fas fa-arrow-left"></i> Home</a>
            <a href="logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a>
        </div>

        <div class="content">
            <div class="container">
                <!-- Statistics -->
                <div class="stats">
                    <div class="stat-card">
                        <div class="number"><?php echo count($all_teachers); ?></div>
                        <div class="label">Total Teachers</div>
                    </div>
                    <div class="stat-card">
                        <div class="number">
                            <?php
                            $total_availability = 0;
                            foreach ($teachers_data as $teacher) {
                                $total_availability += count($teacher['availability']);
                            }
                            echo $total_availability;
                            ?>
                        </div>
                        <div class="label">Available Slots</div>
                    </div>
                    <div class="stat-card">
                        <div class="number">
                            <?php
                            $total_lessons = 0;
                            foreach ($teachers_data as $teacher) {
                                $total_lessons += count($teacher['lessons']);
                            }
                            echo $total_lessons;
                            ?>
                        </div>
                        <div class="label">Upcoming Lessons</div>
                    </div>
                </div>

                <!-- Filter Section -->
                <div class="card">
                    <h3><i class="fas fa-filter"></i> View Schedule By Teacher</h3>
                    <div class="filter-section">
                        <select id="teacher-filter" onchange="filterTeacher()">
                            <option value="">All Teachers</option>
                            <?php foreach ($all_teachers as $teacher): ?>
                                <option value="<?php echo $teacher['id']; ?>" <?php echo $filter_teacher == $teacher['id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($teacher['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <button onclick="exportSchedule()"><i class="fas fa-download"></i> Export</button>
                    </div>
                </div>

                <!-- Teacher Schedules -->
                <?php if (count($teachers_data) > 0): ?>
                    <?php foreach ($teachers_data as $teacher): ?>
                        <div class="teacher-section">
                            <h4>
                                <i class="fas fa-user-tie"></i> <?php echo htmlspecialchars($teacher['name']); ?>
                                <span style="font-size: 0.8rem; color: #666; margin-left: auto;">
                                    <?php if (count($teacher['availability']) > 0): ?>
                                        <span class="status-badge" style="background: #d4edda; color: #155724; padding: 5px 10px; border-radius: 20px;">
                                            ✓ Calendar Configured
                                        </span>
                                    <?php else: ?>
                                        <span class="status-badge" style="background: #f8d7da; color: #721c24; padding: 5px 10px; border-radius: 20px;">
                                            ✕ Not Configured
                                        </span>
                                    <?php endif; ?>
                                </span>
                            </h4>

                            <div class="teacher-section-content">
                                <!-- Availability -->
                                <div class="availability-box">
                                    <h5><i class="fas fa-clock"></i> Weekly Availability</h5>
                                    <?php if (count($teacher['availability']) > 0): ?>
                                        <?php foreach ($teacher['availability'] as $slot): ?>
                                            <div class="slot-item">
                                                <strong><?php echo $slot['day_of_week']; ?></strong>
                                                <?php echo date('H:i', strtotime($slot['start_time'])); ?> - <?php echo date('H:i', strtotime($slot['end_time'])); ?>
                                            </div>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <div class="no-data">No availability set</div>
                                    <?php endif; ?>
                                </div>

                                <!-- Lessons -->
                                <div class="lessons-box">
                                    <h5><i class="fas fa-book"></i> Upcoming Lessons (Next 7 Days)</h5>
                                    <?php if (count($teacher['lessons']) > 0): ?>
                                        <?php foreach ($teacher['lessons'] as $lesson): ?>
                                            <div class="lesson-item">
                                                <strong><?php echo htmlspecialchars($lesson['student_name']); ?></strong>
                                                <small><?php echo date('M d, Y H:i', strtotime($lesson['lesson_date'] . ' ' . $lesson['start_time'])); ?></small>
                                                <small><?php echo htmlspecialchars($lesson['student_email']); ?></small>
                                            </div>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <div class="no-data">No lessons scheduled</div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="card">
                        <p style="color: #999; text-align: center; padding: 40px;">No teachers found</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script>
    function filterTeacher() {
        const teacherId = document.getElementById('teacher-filter').value;
        if (teacherId) {
            window.location.href = 'admin-schedule-view.php?teacher=' + teacherId;
        } else {
            window.location.href = 'admin-schedule-view.php';
        }
    }

    function exportSchedule() {
        let csv = 'Teacher,Day,Start Time,End Time,Booked Lessons\n';
        
        const rows = document.querySelectorAll('.teacher-section');
        rows.forEach(row => {
            const teacherName = row.querySelector('h4').textContent.split('\n')[0].trim();
            const slots = row.querySelectorAll('.slot-item');
            
            if (slots.length === 0) {
                csv += `"${teacherName}","N/A","N/A","N/A","N/A"\n`;
            } else {
                slots.forEach(slot => {
                    const text = slot.textContent.trim();
                    const lines = text.split('\n').map(l => l.trim()).filter(l => l);
                    csv += `"${teacherName}","${lines[0]}","${lines[1] || ''}","",""\n`;
                });
            }
        });

        const blob = new Blob([csv], { type: 'text/csv' });
        const url = window.URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url;
        a.download = 'teacher_schedules.csv';
        a.click();
    }
    </script>
</body>
</html>
