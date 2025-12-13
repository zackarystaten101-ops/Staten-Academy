<?php
require_once __DIR__ . '/../core/Model.php';

class GroupClass extends Model {
    protected $table = 'group_classes';
    
    public function __construct($conn) {
        parent::__construct($conn);
    }
    
    /**
     * Create a new group class
     */
    public function createClass($data) {
        $sql = "INSERT INTO group_classes 
                (track, teacher_id, scheduled_date, scheduled_time, duration, 
                 max_students, title, description, status) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'scheduled')";
        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param("sissiiss", 
            $data['track'],
            $data['teacher_id'],
            $data['scheduled_date'],
            $data['scheduled_time'],
            $data['duration'],
            $data['max_students'],
            $data['title'],
            $data['description']
        );
        $result = $stmt->execute();
        $classId = $stmt->insert_id;
        $stmt->close();
        return $classId;
    }
    
    /**
     * Enroll a student in a group class
     */
    public function enrollStudent($classId, $studentId) {
        // Check if class is full
        $class = $this->find($classId);
        if (!$class) {
            return ['success' => false, 'error' => 'Class not found'];
        }
        
        if ($class['current_enrollment'] >= $class['max_students']) {
            return ['success' => false, 'error' => 'Class is full'];
        }
        
        // Check if already enrolled
        $checkSql = "SELECT id FROM group_class_enrollments 
                     WHERE group_class_id = ? AND student_id = ?";
        $checkStmt = $this->conn->prepare($checkSql);
        $checkStmt->bind_param("ii", $classId, $studentId);
        $checkStmt->execute();
        $checkResult = $checkStmt->get_result();
        if ($checkResult->num_rows > 0) {
            $checkStmt->close();
            return ['success' => false, 'error' => 'Already enrolled'];
        }
        $checkStmt->close();
        
        // Enroll student
        $sql = "INSERT INTO group_class_enrollments (group_class_id, student_id) 
                VALUES (?, ?)";
        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param("ii", $classId, $studentId);
        $result = $stmt->execute();
        $enrollmentId = $stmt->insert_id;
        $stmt->close();
        
        if ($result) {
            // Update enrollment count
            $updateSql = "UPDATE group_classes 
                         SET current_enrollment = current_enrollment + 1 
                         WHERE id = ?";
            $updateStmt = $this->conn->prepare($updateSql);
            $updateStmt->bind_param("i", $classId);
            $updateStmt->execute();
            $updateStmt->close();
        }
        
        return ['success' => true, 'enrollment_id' => $enrollmentId];
    }
    
    /**
     * Get group classes for a specific track
     */
    public function getTrackClasses($track, $dateFrom = null, $dateTo = null) {
        if ($dateFrom && $dateTo) {
            $sql = "SELECT gc.*, u.name as teacher_name, u.profile_pic as teacher_pic
                    FROM group_classes gc
                    JOIN users u ON gc.teacher_id = u.id
                    WHERE gc.track = ? 
                    AND gc.scheduled_date BETWEEN ? AND ?
                    AND gc.status = 'scheduled'
                    ORDER BY gc.scheduled_date ASC, gc.scheduled_time ASC";
            $stmt = $this->conn->prepare($sql);
            $stmt->bind_param("sss", $track, $dateFrom, $dateTo);
        } else {
            $sql = "SELECT gc.*, u.name as teacher_name, u.profile_pic as teacher_pic
                    FROM group_classes gc
                    JOIN users u ON gc.teacher_id = u.id
                    WHERE gc.track = ? 
                    AND gc.status = 'scheduled'
                    AND gc.scheduled_date >= CURDATE()
                    ORDER BY gc.scheduled_date ASC, gc.scheduled_time ASC";
            $stmt = $this->conn->prepare($sql);
            $stmt->bind_param("s", $track);
        }
        
        $stmt->execute();
        $result = $stmt->get_result();
        $classes = [];
        while ($row = $result->fetch_assoc()) {
            $classes[] = $row;
        }
        $stmt->close();
        return $classes;
    }
    
    /**
     * Get student's enrolled group classes
     */
    public function getStudentClasses($studentId, $dateFrom = null, $dateTo = null) {
        if ($dateFrom && $dateTo) {
            $sql = "SELECT gc.*, u.name as teacher_name, u.profile_pic as teacher_pic,
                    gce.enrolled_at, gce.attendance_status
                    FROM group_class_enrollments gce
                    JOIN group_classes gc ON gce.group_class_id = gc.id
                    JOIN users u ON gc.teacher_id = u.id
                    WHERE gce.student_id = ?
                    AND gc.scheduled_date BETWEEN ? AND ?
                    ORDER BY gc.scheduled_date ASC, gc.scheduled_time ASC";
            $stmt = $this->conn->prepare($sql);
            $stmt->bind_param("iss", $studentId, $dateFrom, $dateTo);
        } else {
            $sql = "SELECT gc.*, u.name as teacher_name, u.profile_pic as teacher_pic,
                    gce.enrolled_at, gce.attendance_status
                    FROM group_class_enrollments gce
                    JOIN group_classes gc ON gce.group_class_id = gc.id
                    JOIN users u ON gc.teacher_id = u.id
                    WHERE gce.student_id = ?
                    AND gc.scheduled_date >= CURDATE()
                    ORDER BY gc.scheduled_date ASC, gc.scheduled_time ASC";
            $stmt = $this->conn->prepare($sql);
            $stmt->bind_param("i", $studentId);
        }
        
        $stmt->execute();
        $result = $stmt->get_result();
        $classes = [];
        while ($row = $result->fetch_assoc()) {
            $classes[] = $row;
        }
        $stmt->close();
        return $classes;
    }
    
    /**
     * Unenroll a student from a group class
     */
    public function unenrollStudent($classId, $studentId) {
        $sql = "DELETE FROM group_class_enrollments 
                WHERE group_class_id = ? AND student_id = ?";
        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param("ii", $classId, $studentId);
        $result = $stmt->execute();
        $stmt->close();
        
        if ($result) {
            // Update enrollment count
            $updateSql = "UPDATE group_classes 
                         SET current_enrollment = GREATEST(0, current_enrollment - 1) 
                         WHERE id = ?";
            $updateStmt = $this->conn->prepare($updateSql);
            $updateStmt->bind_param("i", $classId);
            $updateStmt->execute();
            $updateStmt->close();
        }
        
        return $result;
    }
    
    /**
     * Update class status
     */
    public function updateStatus($classId, $status) {
        $sql = "UPDATE group_classes SET status = ? WHERE id = ?";
        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param("si", $status, $classId);
        $result = $stmt->execute();
        $stmt->close();
        return $result;
    }
    
    /**
     * Get enrolled students for a class
     */
    public function getClassStudents($classId) {
        $sql = "SELECT u.*, gce.enrolled_at, gce.attendance_status
                FROM group_class_enrollments gce
                JOIN users u ON gce.student_id = u.id
                WHERE gce.group_class_id = ?
                ORDER BY gce.enrolled_at ASC";
        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param("i", $classId);
        $stmt->execute();
        $result = $stmt->get_result();
        $students = [];
        while ($row = $result->fetch_assoc()) {
            $students[] = $row;
        }
        $stmt->close();
        return $students;
    }
}




