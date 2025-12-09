<?php
session_start();
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/app/Views/components/dashboard-functions.php';
require_once __DIR__ . '/app/Models/TeacherAssignment.php';
require_once __DIR__ . '/app/Models/LearningTrack.php';

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
    header("Location: login.php");
    exit();
}

$admin_id = $_SESSION['user_id'];
$assignmentModel = new TeacherAssignment($conn);
$trackModel = new LearningTrack($conn);

// Handle assignment creation/transfer
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'assign') {
        $student_id = (int)$_POST['student_id'];
        $teacher_id = (int)$_POST['teacher_id'];
        $track = $_POST['track'];
        $notes = trim($_POST['notes'] ?? '');
        
        if ($student_id && $teacher_id && $track) {
            $assignmentId = $assignmentModel->assignTeacher($student_id, $teacher_id, $admin_id, $track, $notes);
            if ($assignmentId) {
                $success_msg = "Teacher assigned successfully!";
            } else {
                $error_msg = "Failed to assign teacher.";
            }
        }
    } elseif ($action === 'transfer') {
        $student_id = (int)$_POST['student_id'];
        $new_teacher_id = (int)$_POST['teacher_id'];
        $notes = trim($_POST['notes'] ?? '');
        
        if ($student_id && $new_teacher_id) {
            $assignmentId = $assignmentModel->transferAssignment($student_id, $new_teacher_id, $admin_id, $notes);
            if ($assignmentId) {
                $success_msg = "Assignment transferred successfully!";
            } else {
                $error_msg = "Failed to transfer assignment.";
            }
        }
    }
}

// Get all students
$students = $trackModel->getTrackStudents(null); // Get all students
$all_students = [];
foreach (['kids', 'adults', 'coding'] as $track) {
    $track_students = $trackModel->getTrackStudents($track);
    foreach ($track_students as $student) {
        $student['track'] = $track;
        $all_students[] = $student;
    }
}

// Get all teachers
$all_teachers = [];
foreach (['kids', 'adults', 'coding'] as $track) {
    $track_teachers = $trackModel->getAvailableTeachers($track);
    foreach ($track_teachers as $teacher) {
        $teacher['track'] = $track;
        if (!isset($all_teachers[$teacher['id']])) {
            $all_teachers[$teacher['id']] = $teacher;
        }
    }
}
$all_teachers = array_values($all_teachers);

// Get assignment statistics
$stats = [
    'total_students' => count($all_students),
    'total_teachers' => count($all_teachers),
    'assigned_students' => 0,
    'unassigned_students' => 0
];

foreach ($all_students as $student) {
    $assignment = $assignmentModel->getStudentTeacher($student['id']);
    if ($assignment) {
        $stats['assigned_students']++;
    } else {
        $stats['unassigned_students']++;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Teacher Assignments - Admin Panel</title>
    <link rel="stylesheet" href="<?php echo getAssetPath('styles.css'); ?>">
    <link rel="stylesheet" href="<?php echo getAssetPath('css/dashboard.css'); ?>">
    <link rel="stylesheet" href="<?php echo getAssetPath('css/mobile.css'); ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        .stat-card {
            background: white;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            text-align: center;
        }
        .stat-card h3 {
            font-size: 2rem;
            margin: 0;
            color: #0b6cf5;
        }
        .stat-card p {
            margin: 5px 0 0;
            color: #666;
        }
        .filter-section {
            background: white;
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .students-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
            gap: 20px;
        }
        .student-card {
            background: white;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            border-left: 4px solid #0b6cf5;
        }
        .student-card.unassigned {
            border-left-color: #dc3545;
        }
        .student-card h3 {
            margin: 0 0 10px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .track-badge {
            display: inline-block;
            padding: 3px 10px;
            border-radius: 12px;
            font-size: 0.75rem;
            font-weight: 600;
        }
        .track-badge.kids { background: #ff6b9d; color: white; }
        .track-badge.adults { background: #0b6cf5; color: white; }
        .track-badge.coding { background: #00d4ff; color: #0066cc; }
        .assign-form {
            margin-top: 15px;
            padding-top: 15px;
            border-top: 1px solid #eee;
        }
        .assign-form select {
            width: 100%;
            padding: 8px;
            margin-bottom: 10px;
            border: 1px solid #ddd;
            border-radius: 5px;
        }
        .assign-form textarea {
            width: 100%;
            padding: 8px;
            margin-bottom: 10px;
            border: 1px solid #ddd;
            border-radius: 5px;
            resize: vertical;
        }
    </style>
</head>
<body class="dashboard-layout">
    <?php include __DIR__ . '/app/Views/components/dashboard-header.php'; ?>
    
    <div class="content-wrapper">
        <?php include __DIR__ . '/app/Views/components/dashboard-sidebar.php'; ?>
        
        <div class="main">
            <h1>Teacher Assignments Management</h1>
            
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
            
            <div class="stats-grid">
                <div class="stat-card">
                    <h3><?php echo $stats['total_students']; ?></h3>
                    <p>Total Students</p>
                </div>
                <div class="stat-card">
                    <h3><?php echo $stats['assigned_students']; ?></h3>
                    <p>Assigned</p>
                </div>
                <div class="stat-card">
                    <h3><?php echo $stats['unassigned_students']; ?></h3>
                    <p>Unassigned</p>
                </div>
                <div class="stat-card">
                    <h3><?php echo $stats['total_teachers']; ?></h3>
                    <p>Available Teachers</p>
                </div>
            </div>
            
            <div class="filter-section">
                <h2>Filter Students</h2>
                <div style="display: flex; gap: 15px; flex-wrap: wrap;">
                    <select id="trackFilter" onchange="filterStudents()">
                        <option value="">All Tracks</option>
                        <option value="kids">Kids</option>
                        <option value="adults">Adults</option>
                        <option value="coding">Coding</option>
                    </select>
                    <select id="assignmentFilter" onchange="filterStudents()">
                        <option value="">All Students</option>
                        <option value="assigned">Assigned</option>
                        <option value="unassigned">Unassigned</option>
                    </select>
                </div>
            </div>
            
            <div class="students-grid" id="studentsGrid">
                <?php foreach ($all_students as $student): ?>
                    <?php 
                    $assignment = $assignmentModel->getStudentTeacher($student['id']);
                    $is_assigned = !empty($assignment);
                    $student_track = $student['track'] ?? $student['learning_track'] ?? null;
                    ?>
                    <div class="student-card <?php echo $is_assigned ? '' : 'unassigned'; ?>" 
                         data-track="<?php echo htmlspecialchars($student_track); ?>"
                         data-assigned="<?php echo $is_assigned ? 'yes' : 'no'; ?>">
                        <h3>
                            <?php echo htmlspecialchars($student['name']); ?>
                            <?php if ($student_track): ?>
                                <span class="track-badge <?php echo $student_track; ?>">
                                    <?php echo ucfirst($student_track); ?>
                                </span>
                            <?php endif; ?>
                        </h3>
                        <p style="color: #666; margin: 5px 0;">
                            <i class="fas fa-envelope"></i> <?php echo htmlspecialchars($student['email']); ?>
                        </p>
                        
                        <?php if ($is_assigned): ?>
                            <div style="background: #e7f3ff; padding: 10px; border-radius: 5px; margin-top: 10px;">
                                <strong>Assigned Teacher:</strong> <?php echo htmlspecialchars($assignment['teacher_name']); ?>
                                <br>
                                <small style="color: #666;">
                                    Assigned: <?php echo date('M d, Y', strtotime($assignment['assigned_at'])); ?>
                                    <?php if ($assignment['assigned_by_name']): ?>
                                        by <?php echo htmlspecialchars($assignment['assigned_by_name']); ?>
                                    <?php endif; ?>
                                </small>
                            </div>
                            <form method="POST" class="assign-form">
                                <input type="hidden" name="action" value="transfer">
                                <input type="hidden" name="student_id" value="<?php echo $student['id']; ?>">
                                <label>Transfer to:</label>
                                <select name="teacher_id" required>
                                    <option value="">Select new teacher...</option>
                                    <?php foreach ($all_teachers as $teacher): ?>
                                        <?php if ($teacher['id'] != $assignment['teacher_id']): ?>
                                            <option value="<?php echo $teacher['id']; ?>">
                                                <?php echo htmlspecialchars($teacher['name']); ?>
                                                (<?php echo $teacher['assigned_students_count'] ?? 0; ?> students)
                                            </option>
                                        <?php endif; ?>
                                    <?php endforeach; ?>
                                </select>
                                <textarea name="notes" placeholder="Transfer notes (optional)"></textarea>
                                <button type="submit" class="btn-primary btn-sm">
                                    <i class="fas fa-exchange-alt"></i> Transfer Assignment
                                </button>
                            </form>
                        <?php else: ?>
                            <form method="POST" class="assign-form">
                                <input type="hidden" name="action" value="assign">
                                <input type="hidden" name="student_id" value="<?php echo $student['id']; ?>">
                                <label>Assign Teacher:</label>
                                <select name="teacher_id" required>
                                    <option value="">Select teacher...</option>
                                    <?php foreach ($all_teachers as $teacher): ?>
                                        <option value="<?php echo $teacher['id']; ?>">
                                            <?php echo htmlspecialchars($teacher['name']); ?>
                                            (<?php echo $teacher['assigned_students_count'] ?? 0; ?> students)
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <input type="hidden" name="track" value="<?php echo htmlspecialchars($student_track ?? 'adults'); ?>">
                                <textarea name="notes" placeholder="Assignment notes (optional)"></textarea>
                                <button type="submit" class="btn-primary btn-sm">
                                    <i class="fas fa-user-plus"></i> Assign Teacher
                                </button>
                            </form>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
    
    <script>
        function filterStudents() {
            const trackFilter = document.getElementById('trackFilter').value;
            const assignmentFilter = document.getElementById('assignmentFilter').value;
            const cards = document.querySelectorAll('.student-card');
            
            cards.forEach(card => {
                const cardTrack = card.dataset.track || '';
                const cardAssigned = card.dataset.assigned;
                
                let show = true;
                
                if (trackFilter && cardTrack !== trackFilter) {
                    show = false;
                }
                
                if (assignmentFilter === 'assigned' && cardAssigned !== 'yes') {
                    show = false;
                } else if (assignmentFilter === 'unassigned' && cardAssigned !== 'no') {
                    show = false;
                }
                
                card.style.display = show ? 'block' : 'none';
            });
        }
    </script>
</body>
</html>

