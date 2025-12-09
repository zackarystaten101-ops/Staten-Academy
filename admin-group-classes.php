<?php
session_start();
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/app/Views/components/dashboard-functions.php';
require_once __DIR__ . '/app/Models/GroupClass.php';
require_once __DIR__ . '/app/Models/LearningTrack.php';

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
    header("Location: login.php");
    exit();
}

$admin_id = $_SESSION['user_id'];
$groupClassModel = new GroupClass($conn);
$trackModel = new LearningTrack($conn);

// Handle group class creation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'create') {
    $classData = [
        'track' => $_POST['track'],
        'teacher_id' => (int)$_POST['teacher_id'],
        'scheduled_date' => $_POST['scheduled_date'],
        'scheduled_time' => $_POST['scheduled_time'],
        'duration' => (int)$_POST['duration'],
        'max_students' => (int)$_POST['max_students'],
        'title' => trim($_POST['title']),
        'description' => trim($_POST['description'] ?? '')
    ];
    
    $classId = $groupClassModel->createClass($classData);
    if ($classId) {
        $success_msg = "Group class created successfully!";
    } else {
        $error_msg = "Failed to create group class.";
    }
}

// Handle status update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_status') {
    $classId = (int)$_POST['class_id'];
    $status = $_POST['status'];
    
    if ($groupClassModel->updateStatus($classId, $status)) {
        $success_msg = "Class status updated!";
    } else {
        $error_msg = "Failed to update status.";
    }
}

// Get all group classes
$all_classes = [];
foreach (['kids', 'adults', 'coding'] as $track) {
    $track_classes = $groupClassModel->getTrackClasses($track);
    foreach ($track_classes as $class) {
        $class['track'] = $track;
        $all_classes[] = $class;
    }
}

// Get all teachers
$all_teachers = [];
foreach (['kids', 'adults', 'coding'] as $track) {
    $track_teachers = $trackModel->getAvailableTeachers($track);
    foreach ($track_teachers as $teacher) {
        if (!isset($all_teachers[$teacher['id']])) {
            $all_teachers[$teacher['id']] = $teacher;
        }
    }
}
$all_teachers = array_values($all_teachers);

// Sort classes by date
usort($all_classes, function($a, $b) {
    return strtotime($a['scheduled_date'] . ' ' . $a['scheduled_time']) - strtotime($b['scheduled_date'] . ' ' . $b['scheduled_time']);
});
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Group Classes Management - Admin Panel</title>
    <link rel="stylesheet" href="<?php echo getAssetPath('styles.css'); ?>">
    <link rel="stylesheet" href="<?php echo getAssetPath('css/dashboard.css'); ?>">
    <link rel="stylesheet" href="<?php echo getAssetPath('css/mobile.css'); ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .create-class-form {
            background: white;
            padding: 25px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            margin-bottom: 30px;
        }
        .classes-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(400px, 1fr));
            gap: 20px;
        }
        .class-card {
            background: white;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            border-left: 4px solid #0b6cf5;
        }
        .class-card.kids { border-left-color: #ff6b9d; }
        .class-card.adults { border-left-color: #0b6cf5; }
        .class-card.coding { border-left-color: #00d4ff; }
        .track-badge {
            display: inline-block;
            padding: 3px 10px;
            border-radius: 12px;
            font-size: 0.75rem;
            font-weight: 600;
            margin-left: 10px;
        }
        .track-badge.kids { background: #ff6b9d; color: white; }
        .track-badge.adults { background: #0b6cf5; color: white; }
        .track-badge.coding { background: #00d4ff; color: #0066cc; }
        .status-badge {
            display: inline-block;
            padding: 5px 12px;
            border-radius: 15px;
            font-size: 0.85rem;
            font-weight: 600;
        }
        .status-badge.scheduled { background: #d1ecf1; color: #0c5460; }
        .status-badge.in_progress { background: #d4edda; color: #155724; }
        .status-badge.completed { background: #d1ecf1; color: #004085; }
        .status-badge.cancelled { background: #f8d7da; color: #721c24; }
    </style>
</head>
<body class="dashboard-layout">
    <?php include __DIR__ . '/app/Views/components/dashboard-header.php'; ?>
    
    <div class="content-wrapper">
        <?php include __DIR__ . '/app/Views/components/dashboard-sidebar.php'; ?>
        
        <div class="main">
            <h1>Group Classes Management</h1>
            
            <?php if (isset($success_msg)): ?>
                <div class="alert-success" style="margin-bottom: 20px;">
                    <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($success_msg); ?>
                </div>
            <?php endif; ?>
            
            <?php if (isset($error_msg)): ?>
                <div class="alert-error" style="margin-bottom: 20px;">
                    <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error_msg); ?>
                </div>
            <?php endif; ?>
            
            <div class="create-class-form">
                <h2><i class="fas fa-plus-circle"></i> Create New Group Class</h2>
                <form method="POST">
                    <input type="hidden" name="action" value="create">
                    <div class="profile-grid">
                        <div class="form-group">
                            <label>Track *</label>
                            <select name="track" required>
                                <option value="">Select track...</option>
                                <option value="kids">Kids</option>
                                <option value="adults">Adults</option>
                                <option value="coding">Coding</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Teacher *</label>
                            <select name="teacher_id" required>
                                <option value="">Select teacher...</option>
                                <?php foreach ($all_teachers as $teacher): ?>
                                    <option value="<?php echo $teacher['id']; ?>">
                                        <?php echo htmlspecialchars($teacher['name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Date *</label>
                            <input type="date" name="scheduled_date" required>
                        </div>
                        <div class="form-group">
                            <label>Time *</label>
                            <input type="time" name="scheduled_time" required>
                        </div>
                        <div class="form-group">
                            <label>Duration (minutes) *</label>
                            <input type="number" name="duration" value="60" min="30" step="15" required>
                        </div>
                        <div class="form-group">
                            <label>Max Students *</label>
                            <input type="number" name="max_students" value="10" min="2" max="50" required>
                        </div>
                    </div>
                    <div class="form-group">
                        <label>Class Title *</label>
                        <input type="text" name="title" placeholder="e.g., Conversation Practice - Intermediate" required>
                    </div>
                    <div class="form-group">
                        <label>Description</label>
                        <textarea name="description" rows="3" placeholder="Describe what students will learn..."></textarea>
                    </div>
                    <button type="submit" class="btn-primary">
                        <i class="fas fa-plus"></i> Create Group Class
                    </button>
                </form>
            </div>
            
            <h2>All Group Classes</h2>
            <div class="classes-grid">
                <?php foreach ($all_classes as $class): ?>
                    <?php
                    $teacher = null;
                    foreach ($all_teachers as $t) {
                        if ($t['id'] == $class['teacher_id']) {
                            $teacher = $t;
                            break;
                        }
                    }
                    $students = $groupClassModel->getClassStudents($class['id']);
                    ?>
                    <div class="class-card <?php echo $class['track']; ?>">
                        <div style="display: flex; justify-content: space-between; align-items: start; margin-bottom: 15px;">
                            <div style="flex: 1;">
                                <h3 style="margin: 0 0 10px; display: flex; align-items: center;">
                                    <?php echo htmlspecialchars($class['title'] ?? 'Group Class'); ?>
                                    <span class="track-badge <?php echo $class['track']; ?>">
                                        <?php echo ucfirst($class['track']); ?>
                                    </span>
                                </h3>
                                <?php if (!empty($class['description'])): ?>
                                    <p style="color: #666; margin: 0 0 10px; font-size: 0.9rem;">
                                        <?php echo htmlspecialchars(substr($class['description'], 0, 100)); ?>
                                        <?php echo strlen($class['description']) > 100 ? '...' : ''; ?>
                                    </p>
                                <?php endif; ?>
                                <div style="font-size: 0.85rem; color: #666;">
                                    <div><i class="fas fa-chalkboard-teacher"></i> Teacher: <?php echo htmlspecialchars($teacher['name'] ?? 'Unknown'); ?></div>
                                    <div style="margin-top: 5px;">
                                        <?php if (!empty($class['scheduled_date'])): ?>
                                            <i class="fas fa-calendar"></i> <?php echo date('M d, Y', strtotime($class['scheduled_date'])); ?>
                                        <?php endif; ?>
                                        <?php if (!empty($class['scheduled_time'])): ?>
                                            <i class="fas fa-clock" style="margin-left: 15px;"></i> <?php echo date('H:i', strtotime($class['scheduled_time'])); ?>
                                        <?php endif; ?>
                                    </div>
                                    <div style="margin-top: 5px;">
                                        <i class="fas fa-hourglass-half"></i> <?php echo $class['duration'] ?? 60; ?> min
                                        <i class="fas fa-users" style="margin-left: 15px;"></i> <?php echo $class['current_enrollment'] ?? 0; ?>/<?php echo $class['max_students'] ?? 10; ?> students
                                    </div>
                                </div>
                            </div>
                            <span class="status-badge <?php echo $class['status']; ?>">
                                <?php echo ucfirst($class['status']); ?>
                            </span>
                        </div>
                        
                        <?php if (count($students) > 0): ?>
                            <div style="background: #f8f9fa; padding: 10px; border-radius: 5px; margin-top: 10px;">
                                <strong>Enrolled Students:</strong>
                                <ul style="margin: 5px 0 0; padding-left: 20px;">
                                    <?php foreach ($students as $student): ?>
                                        <li><?php echo htmlspecialchars($student['name']); ?> (<?php echo htmlspecialchars($student['email']); ?>)</li>
                                    <?php endforeach; ?>
                                </ul>
                            </div>
                        <?php endif; ?>
                        
                        <?php if (isset($class['id'])): ?>
                            <div style="display: flex; gap: 10px; margin-top: 15px; flex-wrap: wrap;">
                                <?php if (($class['status'] ?? 'scheduled') === 'scheduled'): ?>
                                    <form method="POST" style="display: inline;">
                                        <input type="hidden" name="action" value="update_status">
                                        <input type="hidden" name="class_id" value="<?php echo $class['id']; ?>">
                                        <input type="hidden" name="status" value="in_progress">
                                        <button type="submit" class="btn-success btn-sm">
                                            <i class="fas fa-play"></i> Start Class
                                        </button>
                                    </form>
                                    <form method="POST" style="display: inline;">
                                        <input type="hidden" name="action" value="update_status">
                                        <input type="hidden" name="class_id" value="<?php echo $class['id']; ?>">
                                        <input type="hidden" name="status" value="cancelled">
                                        <button type="submit" class="btn-danger btn-sm" onclick="return confirm('Are you sure you want to cancel this class?');">
                                            <i class="fas fa-times"></i> Cancel
                                        </button>
                                    </form>
                                <?php elseif (($class['status'] ?? '') === 'in_progress'): ?>
                                    <form method="POST" style="display: inline;">
                                        <input type="hidden" name="action" value="update_status">
                                        <input type="hidden" name="class_id" value="<?php echo $class['id']; ?>">
                                        <input type="hidden" name="status" value="completed">
                                        <button type="submit" class="btn-primary btn-sm">
                                            <i class="fas fa-check"></i> Mark Complete
                                        </button>
                                    </form>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
            
            <?php if (empty($all_classes)): ?>
                <div class="empty-state">
                    <i class="fas fa-users"></i>
                    <h3>No Group Classes Yet</h3>
                    <p>Create your first group class above.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>

