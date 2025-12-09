<?php
require_once __DIR__ . '/../core/Model.php';

class CourseProgress extends Model {
    protected $table = 'user_course_progress';
    
    public function __construct($conn) {
        parent::__construct($conn);
    }
    
    /**
     * Get or create progress record
     */
    public function getProgress($user_id, $course_id) {
        $sql = "SELECT * FROM {$this->table} WHERE user_id = ? AND course_id = ?";
        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param("ii", $user_id, $course_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $progress = $result->fetch_assoc();
        
        if (!$progress) {
            // Create new progress record
            $insert_sql = "INSERT INTO {$this->table} (user_id, course_id, progress_percentage, completed_lessons) 
                          VALUES (?, ?, 0, '[]')";
            $insert_stmt = $this->conn->prepare($insert_sql);
            $insert_stmt->bind_param("ii", $user_id, $course_id);
            $insert_stmt->execute();
            $insert_stmt->close();
            
            // Fetch the newly created record
            $stmt->execute();
            $result = $stmt->get_result();
            $progress = $result->fetch_assoc();
        }
        
        $stmt->close();
        return $progress;
    }
    
    /**
     * Update progress
     */
    public function updateProgress($user_id, $course_id, $lesson_id, $completed_lessons, $progress_percentage) {
        $sql = "UPDATE {$this->table} 
                SET lesson_id = ?, 
                    completed_lessons = ?, 
                    progress_percentage = ?,
                    last_accessed_at = NOW()
                WHERE user_id = ? AND course_id = ?";
        
        $completed_json = json_encode($completed_lessons);
        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param("isdi", $lesson_id, $completed_json, $progress_percentage, $user_id, $course_id);
        $result = $stmt->execute();
        $stmt->close();
        
        // Check if course is completed
        if ($progress_percentage >= 100) {
            $this->markCompleted($user_id, $course_id);
        }
        
        return $result;
    }
    
    /**
     * Mark course as completed
     */
    public function markCompleted($user_id, $course_id) {
        $sql = "UPDATE {$this->table} 
                SET completed_at = NOW(), progress_percentage = 100
                WHERE user_id = ? AND course_id = ? AND completed_at IS NULL";
        
        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param("ii", $user_id, $course_id);
        $result = $stmt->execute();
        $stmt->close();
        return $result;
    }
    
    /**
     * Get user's course progress summary
     */
    public function getUserProgressSummary($user_id) {
        $sql = "SELECT 
                COUNT(*) as total_courses,
                SUM(CASE WHEN progress_percentage = 100 THEN 1 ELSE 0 END) as completed_courses,
                AVG(progress_percentage) as avg_progress
                FROM {$this->table}
                WHERE user_id = ?";
        
        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        return $result->fetch_assoc();
    }
}








