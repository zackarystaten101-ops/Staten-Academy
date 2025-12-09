<?php
require_once __DIR__ . '/../core/Model.php';

class LearningTrack extends Model {
    protected $table = 'users';
    
    public function __construct($conn) {
        parent::__construct($conn);
    }
    
    /**
     * Get all plans for a specific track
     */
    public function getTrackPlans($track) {
        $sql = "SELECT * FROM subscription_plans 
                WHERE track = ? AND is_active = TRUE 
                ORDER BY display_order ASC, one_on_one_classes_per_week ASC";
        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param("s", $track);
        $stmt->execute();
        $result = $stmt->get_result();
        $plans = [];
        while ($row = $result->fetch_assoc()) {
            $plans[] = $row;
        }
        $stmt->close();
        return $plans;
    }
    
    /**
     * Get all students in a specific track
     */
    public function getTrackStudents($track) {
        $sql = "SELECT * FROM users 
                WHERE learning_track = ? AND role IN ('student', 'new_student')
                ORDER BY name ASC";
        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param("s", $track);
        $stmt->execute();
        $result = $stmt->get_result();
        $students = [];
        while ($row = $result->fetch_assoc()) {
            $students[] = $row;
        }
        $stmt->close();
        return $students;
    }
    
    /**
     * Get available teachers for a specific track
     */
    public function getAvailableTeachers($track) {
        $sql = "SELECT u.*, 
                COUNT(ta.id) as assigned_students_count
                FROM users u
                LEFT JOIN teacher_assignments ta ON u.id = ta.teacher_id AND ta.status = 'active'
                WHERE u.role = 'teacher' 
                AND (u.learning_track = ? OR u.learning_track IS NULL)
                GROUP BY u.id
                ORDER BY assigned_students_count ASC, u.name ASC";
        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param("s", $track);
        $stmt->execute();
        $result = $stmt->get_result();
        $teachers = [];
        while ($row = $result->fetch_assoc()) {
            $teachers[] = $row;
        }
        $stmt->close();
        return $teachers;
    }
    
    /**
     * Set user's learning track
     */
    public function setUserTrack($userId, $track) {
        $sql = "UPDATE users SET learning_track = ? WHERE id = ?";
        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param("si", $track, $userId);
        $result = $stmt->execute();
        $stmt->close();
        return $result;
    }
}

