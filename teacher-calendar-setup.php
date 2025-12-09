<?php
// Start output buffering to prevent "headers already sent" errors
ob_start();

// Load environment configuration first
if (!defined('DB_HOST')) {
    require_once __DIR__ . '/env.php';
}

// Start session before any output
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/google-calendar-config.php';
require_once __DIR__ . '/app/Services/TimezoneService.php';
require_once __DIR__ . '/app/Models/TimeOff.php';

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'teacher') {
    ob_end_clean(); // Clear output buffer before redirect
    header("Location: login.php");
    exit();
}

$teacher_id = $_SESSION['user_id'];
$api = new GoogleCalendarAPI($conn);
$tzService = new TimezoneService($conn);
$timeOffModel = new TimeOff($conn);
$success_msg = '';
$error_msg = '';

// Get user's timezone
$user_timezone = $tzService->getUserTimezone($teacher_id);

// Fetch teacher info
$stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
$stmt->bind_param("i", $teacher_id);
$stmt->execute();
$teacher = $stmt->get_result()->fetch_assoc();
$stmt->close();

// Set $user for header component
$user = $teacher;

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

// Handle booking notice update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_booking_notice'])) {
    $booking_notice_hours = (int)$_POST['booking_notice_hours'];
    
    if ($booking_notice_hours < 1 || $booking_notice_hours > 168) {
        $error_msg = 'Booking notice must be between 1 and 168 hours';
    } else {
        $stmt = $conn->prepare("UPDATE users SET booking_notice_hours = ? WHERE id = ?");
        $stmt->bind_param("ii", $booking_notice_hours, $teacher_id);
        
        if ($stmt->execute()) {
            $success_msg = 'Booking notice period updated';
            $teacher['booking_notice_hours'] = $booking_notice_hours;
        } else {
            $error_msg = 'Error updating booking notice';
        }
        $stmt->close();
    }
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

// Handle time-off management
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_time_off'])) {
    $start_date = $_POST['start_date'];
    $end_date = $_POST['end_date'];
    $reason = $_POST['reason'] ?? null;
    
    if (strtotime($start_date) > strtotime($end_date)) {
        $error_msg = 'Start date must be before end date';
    } else {
        $timeOffId = $timeOffModel->createTimeOff($teacher_id, $start_date, $end_date, $reason);
        if ($timeOffId) {
            $success_msg = 'Time-off period added successfully. Lessons during this period will be automatically cancelled.';
        } else {
            $error_msg = 'Error adding time-off period';
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['remove_time_off'])) {
    $time_off_id = $_POST['time_off_id'];
    if ($timeOffModel->deleteTimeOff($time_off_id, $teacher_id)) {
        $success_msg = 'Time-off period removed';
    } else {
        $error_msg = 'Error removing time-off period';
    }
}

// Fetch time-off periods
$time_off_periods = $timeOffModel->getByTeacher($teacher_id);

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

// Set page title for header
$page_title = 'Calendar Setup';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Calendar Setup - Staten Academy</title>
    <?php
    // Ensure getAssetPath is available
    if (!function_exists('getAssetPath')) {
        if (file_exists(__DIR__ . '/app/Views/components/dashboard-functions.php')) {
            require_once __DIR__ . '/app/Views/components/dashboard-functions.php';
        } else {
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
    }
    ?>
    <link rel="stylesheet" href="<?php echo getAssetPath('styles.css'); ?>">
    <link rel="stylesheet" href="<?php echo getAssetPath('css/dashboard.css'); ?>">
    <link rel="stylesheet" href="<?php echo getAssetPath('css/mobile.css'); ?>">
    <!-- MODERN SHADOWS - To disable, comment out the line below -->
    <link rel="stylesheet" href="<?php echo getAssetPath('css/modern-shadows.css'); ?>">
    <link rel="stylesheet" href="<?php echo getAssetPath('css/calendar.css'); ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        /* Calendar page specific styles */
        .calendar-container { max-width: 100%; margin: 0; }
        #teacher-calendar { 
            width: 100% !important; 
            min-width: calc(70px + 140px * 7) !important;
            overflow-x: auto !important;
        }
        .teacher-calendar-grid {
            display: flex !important;
            flex-direction: row !important;
            flex-wrap: nowrap !important;
            min-width: calc(70px + 140px * 7) !important;
        }
        .calendar-days-container {
            display: flex !important;
            flex-direction: row !important;
            flex-wrap: nowrap !important;
        }
        .calendar-day-column {
            flex: 0 0 auto !important;
            width: calc((100% - 70px) / 7) !important;
            min-width: 140px !important;
            display: flex !important;
            flex-direction: column !important;
        }

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
<body class="dashboard-layout">
<?php include __DIR__ . '/app/Views/components/dashboard-header.php'; ?>

<div class="content-wrapper">
    <?php 
    // Set active tab for sidebar
    $active_tab = 'calendar-setup';
    include __DIR__ . '/app/Views/components/dashboard-sidebar.php'; 
    ?>

    <div class="main">
        <div class="calendar-container">
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

                <!-- Preply-Style Calendar -->
                <div class="card" style="padding: 0; overflow-x: auto; overflow-y: visible; width: 100%;">
                    <div style="padding: 20px; border-bottom: 2px solid #dee2e6;">
                        <h3 style="margin: 0;"><i class="fas fa-calendar-alt"></i> Manage Your Availability</h3>
                        <div style="background: #e7f3ff; padding: 15px; border-radius: 5px; margin-top: 15px; border-left: 4px solid #0b6cf5;">
                            <p style="margin: 0 0 10px 0; color: #004080; font-weight: bold;"><i class="fas fa-lightbulb"></i> Quick Tips:</p>
                            <ul style="margin: 0; padding-left: 20px; color: #666;">
                                <li>Click and drag on the calendar to create time slots</li>
                                <li>Use "Add Weekly Slot" for recurring availability (same time every week)</li>
                                <li>Use "Add One-Time Slot" for specific dates</li>
                                <li>Hover over slots to edit or delete them</li>
                                <li>Blue blocks show your scheduled lessons</li>
                            </ul>
                        </div>
                    </div>
                    <div id="teacher-calendar" style="padding: 20px; position: relative; width: 100%; overflow-x: auto; min-width: calc(70px + 140px * 7);">
                        <div id="calendar-loading" style="display: none; position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); text-align: center; z-index: 100;">
                            <i class="fas fa-spinner fa-spin" style="font-size: 2rem; color: #0b6cf5;"></i>
                            <p style="margin-top: 10px; color: #666;">Loading calendar...</p>
                        </div>
                    </div>
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

                <!-- Time-Off Management -->
                <div class="card">
                    <h3><i class="fas fa-calendar-times"></i> Schedule Time Off</h3>
                    <p style="color: #666; margin-bottom: 15px;">Mark periods when you're unavailable. Scheduled lessons during this time will be automatically cancelled, and recurring lessons will be paused.</p>
                    
                    <form method="POST" style="margin-bottom: 20px;">
                        <div class="form-row">
                            <div class="form-group">
                                <label>Start Date</label>
                                <input type="date" name="start_date" required min="<?php echo date('Y-m-d'); ?>">
                            </div>
                            <div class="form-group">
                                <label>End Date</label>
                                <input type="date" name="end_date" required min="<?php echo date('Y-m-d'); ?>">
                            </div>
                            <div class="form-group">
                                <label>Reason (optional)</label>
                                <input type="text" name="reason" placeholder="e.g., Holiday, Vacation">
                            </div>
                            <button type="submit" name="add_time_off"><i class="fas fa-plus"></i> Add Time Off</button>
                        </div>
                    </form>
                    
                    <?php if (count($time_off_periods) > 0): ?>
                        <h4 style="margin-top: 20px;">Your Time-Off Periods</h4>
                        <table class="slots-table">
                            <thead>
                                <tr>
                                    <th>Start Date</th>
                                    <th>End Date</th>
                                    <th>Reason</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($time_off_periods as $timeOff): ?>
                                    <tr>
                                        <td><?php echo date('M d, Y', strtotime($timeOff['start_date'])); ?></td>
                                        <td><?php echo date('M d, Y', strtotime($timeOff['end_date'])); ?></td>
                                        <td><?php echo htmlspecialchars($timeOff['reason'] ?? 'N/A'); ?></td>
                                        <td>
                                            <form method="POST" style="display: inline;">
                                                <input type="hidden" name="time_off_id" value="<?php echo $timeOff['id']; ?>">
                                                <button type="submit" name="remove_time_off" class="danger" style="padding: 5px 10px; font-size: 12px;" onclick="return confirm('Remove this time-off period?');">
                                                    Remove
                                                </button>
                                            </form>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php else: ?>
                        <p style="color: #999; padding: 20px; text-align: center;">No time-off periods scheduled.</p>
                    <?php endif; ?>
                </div>

                <!-- Booking Notice Settings -->
                <div class="card">
                    <h3><i class="fas fa-clock"></i> Booking Notice Period</h3>
                    <p style="color: #666; margin-bottom: 15px;">Set the minimum advance notice required for lesson bookings.</p>
                    
                    <?php
                    $booking_notice_hours = $teacher['booking_notice_hours'] ?? 24;
                    ?>
                    <form method="POST" id="booking-notice-form">
                        <div class="form-group">
                            <label>Minimum Notice (hours)</label>
                            <input type="number" name="booking_notice_hours" value="<?php echo $booking_notice_hours; ?>" min="1" max="168" required>
                            <small style="color: #666;">Students must book at least this many hours in advance</small>
                        </div>
                        <button type="submit" name="update_booking_notice"><i class="fas fa-save"></i> Save Settings</button>
                    </form>
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
    <script src="<?php echo getAssetPath('js/teacher-calendar.js'); ?>"></script>
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        if (typeof TeacherCalendar !== 'undefined') {
            const teacherId = <?php echo json_encode($teacher_id); ?>;
            const userTimezone = '<?php echo htmlspecialchars($user_timezone); ?>';
            
            const calendar = new TeacherCalendar('teacher-calendar', {
                teacherId: teacherId,
                timezone: userTimezone || window.userTimezone || 'UTC',
                slotDuration: 30,
                onSlotCreated: function(slot) {
                    // Reload page to show success message
                    window.location.reload();
                },
                onSlotUpdated: function(slot) {
                    window.location.reload();
                },
                onSlotDeleted: function(slotId) {
                    window.location.reload();
                }
            });
        }
    });
    </script>
</body>
</html>
<?php
// End output buffering
ob_end_flush();
?>
