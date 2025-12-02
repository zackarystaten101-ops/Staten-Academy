<?php
session_start();
require_once 'db.php';
require_once 'google-calendar-config.php';

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'teacher') {
    header("Location: login.php");
    exit();
}

$teacher_id = $_SESSION['user_id'];
$api = new GoogleCalendarAPI($conn);
$success_msg = '';
$error_msg = '';

// Fetch teacher info
$stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
$stmt->bind_param("i", $teacher_id);
$stmt->execute();
$teacher = $stmt->get_result()->fetch_assoc();
$stmt->close();

// Check if Google Calendar is connected
$has_calendar = !empty($teacher['google_calendar_token']);

// Handle adding availability slots
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_availability'])) {
    $day_of_week = $_POST['day_of_week'];
    $start_time = $_POST['start_time'];
    $end_time = $_POST['end_time'];

    // Validate times
    if (strtotime($start_time) >= strtotime($end_time)) {
        $error_msg = 'End time must be after start time';
    } else {
        $stmt = $conn->prepare("
            INSERT INTO teacher_availability (teacher_id, day_of_week, start_time, end_time) 
            VALUES (?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE updated_at = NOW()
        ");
        $stmt->bind_param("isss", $teacher_id, $day_of_week, $start_time, $end_time);
        
        if ($stmt->execute()) {
            $success_msg = 'Availability slot added successfully';
        } else {
            $error_msg = 'Error adding availability slot: ' . $stmt->error;
        }
        $stmt->close();
    }
}

// Handle removing availability slots
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['remove_availability'])) {
    $slot_id = $_POST['slot_id'];
    $stmt = $conn->prepare("DELETE FROM teacher_availability WHERE id = ? AND teacher_id = ?");
    $stmt->bind_param("ii", $slot_id, $teacher_id);
    
    if ($stmt->execute()) {
        $success_msg = 'Availability slot removed';
    } else {
        $error_msg = 'Error removing slot: ' . $stmt->error;
    }
    $stmt->close();
}

// Handle toggling availability
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['toggle_availability'])) {
    $slot_id = $_POST['slot_id'];
    $new_status = $_POST['new_status'];
    
    $stmt = $conn->prepare("UPDATE teacher_availability SET is_available = ? WHERE id = ? AND teacher_id = ?");
    $stmt->bind_param("iii", $new_status, $slot_id, $teacher_id);
    
    if ($stmt->execute()) {
        $success_msg = 'Availability updated';
    } else {
        $error_msg = 'Error updating availability';
    }
    $stmt->close();
}

// Fetch current availability slots
$stmt = $conn->prepare("
    SELECT * FROM teacher_availability 
    WHERE teacher_id = ? 
    ORDER BY FIELD(day_of_week, 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'), start_time
");
$stmt->bind_param("i", $teacher_id);
$stmt->execute();
$availability_slots = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Fetch upcoming lessons
$stmt = $conn->prepare("
    SELECT l.*, u.name as student_name 
    FROM lessons l 
    JOIN users u ON l.student_id = u.id 
    WHERE l.teacher_id = ? AND l.lesson_date >= CURDATE() AND l.status = 'scheduled'
    ORDER BY l.lesson_date, l.start_time
    LIMIT 10
");
$stmt->bind_param("i", $teacher_id);
$stmt->execute();
$upcoming_lessons = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$days = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Calendar Setup - Staten Academy</title>
    <link rel="stylesheet" href="styles.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        body { background: #f4f4f9; margin: 0; display: flex; flex-direction: column; height: 100vh; font-family: Arial, sans-serif; }
        .header-bar { background: #004080; color: white; padding: 15px 20px; display: flex; align-items: center; justify-content: space-between; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        .header-bar h2 { margin: 0; }
        .main-wrapper { display: flex; flex: 1; overflow: hidden; }
        .sidebar { width: 250px; background: #2c3e50; color: white; padding-top: 20px; overflow-y: auto; }
        .sidebar a { display: block; padding: 12px 20px; color: #adb5bd; text-decoration: none; transition: 0.2s; }
        .sidebar a:hover, .sidebar a.active { background: #34495e; color: white; }
        .content { flex: 1; overflow-y: auto; padding: 30px; }
        .container { max-width: 1000px; }

        .card { background: white; padding: 25px; border-radius: 8px; box-shadow: 0 2px 8px rgba(0,0,0,0.1); margin-bottom: 20px; }
        .card h3 { margin-top: 0; color: #004080; border-bottom: 2px solid #0b6cf5; padding-bottom: 10px; }

        .alert { padding: 15px; border-radius: 5px; margin-bottom: 20px; }
        .alert-success { background: #d4edda; color: #155724; border-left: 4px solid #28a745; }
        .alert-error { background: #f8d7da; color: #721c24; border-left: 4px solid #dc3545; }

        .form-group { margin-bottom: 15px; }
        label { display: block; font-weight: bold; margin-bottom: 5px; color: #333; }
        input, select { width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 4px; font-size: 14px; }
        input:focus, select:focus { outline: none; border-color: #0b6cf5; box-shadow: 0 0 5px rgba(11, 108, 245, 0.3); }

        .form-row { display: grid; grid-template-columns: 1fr 1fr 1fr auto; gap: 10px; align-items: flex-end; }
        @media (max-width: 768px) { .form-row { grid-template-columns: 1fr; } }

        button { padding: 10px 20px; background: #0b6cf5; color: white; border: none; border-radius: 4px; cursor: pointer; font-weight: bold; transition: 0.2s; }
        button:hover { background: #004080; }
        button.danger { background: #dc3545; }
        button.danger:hover { background: #c82333; }
        button.secondary { background: #6c757d; }
        button.secondary:hover { background: #5a6268; }

        .slots-table { width: 100%; border-collapse: collapse; margin-top: 15px; }
        .slots-table th { background: #f8f9fa; padding: 12px; text-align: left; font-weight: bold; border-bottom: 2px solid #dee2e6; }
        .slots-table td { padding: 12px; border-bottom: 1px solid #dee2e6; }
        .slots-table tr:hover { background: #f8f9fa; }

        .status-badge { display: inline-block; padding: 5px 10px; border-radius: 20px; font-size: 12px; font-weight: bold; }
        .status-available { background: #d4edda; color: #155724; }
        .status-unavailable { background: #f8d7da; color: #721c24; }

        .lessons-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(300px, 1fr)); gap: 15px; margin-top: 15px; }
        .lesson-card { background: #f8f9fa; padding: 15px; border-radius: 5px; border-left: 4px solid #0b6cf5; }
        .lesson-card strong { display: block; margin-bottom: 5px; }

        .google-connect { background: #f0f7ff; padding: 20px; border-radius: 8px; border: 2px solid #0b6cf5; text-align: center; }
        .google-connect a { display: inline-block; background: #db4437; color: white; padding: 12px 24px; text-decoration: none; border-radius: 4px; margin-top: 10px; font-weight: bold; }
        .google-connect a:hover { background: #c5221f; }

        .connected-status { background: #d4edda; padding: 15px; border-radius: 5px; border-left: 4px solid #28a745; margin-bottom: 20px; }
        .connected-status strong { color: #155724; }
    </style>
</head>
<body>
    <header class="header-bar">
        <h2><i class="fas fa-calendar"></i> Calendar Setup</h2>
        <div><?php echo htmlspecialchars($teacher['name']); ?></div>
    </header>

    <div class="main-wrapper">
        <div class="sidebar">
            <h3>Teacher Portal</h3>
            <a href="teacher-dashboard.php"><i class="fas fa-home"></i> Dashboard</a>
            <a href="teacher-calendar-setup.php" class="active"><i class="fas fa-calendar"></i> Calendar Setup</a>
            <a href="schedule.php"><i class="fas fa-clock"></i> View Bookings</a>
            <a href="classroom.php"><i class="fas fa-book"></i> Classroom</a>
            <hr style="border: none; border-top: 1px solid #444; margin: 15px 0;">
            <a href="message_threads.php"><i class="fas fa-comments"></i> Messages</a>
            <a href="logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a>
        </div>

        <div class="content">
            <div class="container">
                <!-- Success/Error Messages -->
                <?php if ($success_msg): ?>
                    <div class="alert alert-success"><i class="fas fa-check-circle"></i> <?php echo $success_msg; ?></div>
                <?php endif; ?>
                <?php if ($error_msg): ?>
                    <div class="alert alert-error"><i class="fas fa-exclamation-circle"></i> <?php echo $error_msg; ?></div>
                <?php endif; ?>

                <!-- Google Calendar Connection -->
                <div class="card">
                    <h3><i class="fas fa-google"></i> Google Calendar Connection</h3>
                    <?php if ($has_calendar): ?>
                        <div class="connected-status">
                            <i class="fas fa-check-circle"></i> <strong>Google Calendar Connected</strong>
                            <p style="margin: 10px 0 0 0; font-size: 14px;">Your availability and lessons will sync with your Google Calendar.</p>
                        </div>
                    <?php else: ?>
                        <div class="google-connect">
                            <p>Connect your Google Calendar to sync your availability and lessons automatically.</p>
                            <a href="<?php echo $api->getAuthUrl($teacher_id); ?>">
                                <i class="fab fa-google"></i> Connect Google Calendar
                            </a>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Add Availability -->
                <div class="card">
                    <h3><i class="fas fa-plus-circle"></i> Add Available Time Slot</h3>
                    <form method="POST">
                        <div class="form-row">
                            <div class="form-group">
                                <label>Day of Week</label>
                                <select name="day_of_week" required>
                                    <option value="">Select day...</option>
                                    <?php foreach ($days as $day): ?>
                                        <option value="<?php echo $day; ?>"><?php echo $day; ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="form-group">
                                <label>Start Time</label>
                                <input type="time" name="start_time" required>
                            </div>
                            <div class="form-group">
                                <label>End Time</label>
                                <input type="time" name="end_time" required>
                            </div>
                            <button type="submit" name="add_availability"><i class="fas fa-save"></i> Add</button>
                        </div>
                    </form>
                </div>

                <!-- Current Availability Slots -->
                <div class="card">
                    <h3><i class="fas fa-list"></i> Your Available Time Slots</h3>
                    <?php if (count($availability_slots) > 0): ?>
                        <table class="slots-table">
                            <thead>
                                <tr>
                                    <th>Day</th>
                                    <th>Start Time</th>
                                    <th>End Time</th>
                                    <th>Status</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($availability_slots as $slot): ?>
                                    <tr>
                                        <td><strong><?php echo $slot['day_of_week']; ?></strong></td>
                                        <td><?php echo date('H:i', strtotime($slot['start_time'])); ?></td>
                                        <td><?php echo date('H:i', strtotime($slot['end_time'])); ?></td>
                                        <td>
                                            <span class="status-badge <?php echo $slot['is_available'] ? 'status-available' : 'status-unavailable'; ?>">
                                                <?php echo $slot['is_available'] ? 'Available' : 'Unavailable'; ?>
                                            </span>
                                        </td>
                                        <td>
                                            <form method="POST" style="display: inline;">
                                                <input type="hidden" name="slot_id" value="<?php echo $slot['id']; ?>">
                                                <input type="hidden" name="new_status" value="<?php echo $slot['is_available'] ? 0 : 1; ?>">
                                                <button type="submit" name="toggle_availability" class="secondary" style="padding: 5px 10px; font-size: 12px;">
                                                    <?php echo $slot['is_available'] ? 'Disable' : 'Enable'; ?>
                                                </button>
                                            </form>
                                            <form method="POST" style="display: inline;">
                                                <input type="hidden" name="slot_id" value="<?php echo $slot['id']; ?>">
                                                <button type="submit" name="remove_availability" class="danger" style="padding: 5px 10px; font-size: 12px;" onclick="return confirm('Remove this slot?');">
                                                    Remove
                                                </button>
                                            </form>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php else: ?>
                        <p style="color: #999; padding: 20px; text-align: center;">No availability slots set. Add your first time slot above.</p>
                    <?php endif; ?>
                </div>

                <!-- Upcoming Lessons -->
                <div class="card">
                    <h3><i class="fas fa-book"></i> Upcoming Lessons</h3>
                    <?php if (count($upcoming_lessons) > 0): ?>
                        <div class="lessons-grid">
                            <?php foreach ($upcoming_lessons as $lesson): ?>
                                <div class="lesson-card">
                                    <strong>Student:</strong> <?php echo htmlspecialchars($lesson['student_name']); ?><br>
                                    <strong>Date:</strong> <?php echo date('M d, Y', strtotime($lesson['lesson_date'])); ?><br>
                                    <strong>Time:</strong> <?php echo date('H:i', strtotime($lesson['start_time'])); ?> - <?php echo date('H:i', strtotime($lesson['end_time'])); ?><br>
                                    <span class="status-badge status-available" style="margin-top: 10px; display: inline-block;">Scheduled</span>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <p style="color: #999; padding: 20px; text-align: center;">No upcoming lessons scheduled.</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
