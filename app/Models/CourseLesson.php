<?php
require_once __DIR__ . '/../core/Model.php';

class CourseLesson extends Model {
    protected $table = 'course_lessons';
    
    public function __construct($conn) {
        parent::__construct($conn);
    }
    
    /**
     * Get all lessons for a course ordered by lesson_order
     */
    public function getLessonsByCourse($course_id) {
        $sql = "SELECT * FROM {$this->table} WHERE course_id = ? ORDER BY lesson_order ASC";
        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param("i", $course_id);
        $stmt->execute();
        return $stmt->get_result();
    }
    
    /**
     * Get lesson details
     */
    public function getLessonDetails($lesson_id) {
        $sql = "SELECT cl.*, c.title as course_title, c.category_id
                FROM {$this->table} cl
                JOIN courses c ON cl.course_id = c.id
                WHERE cl.id = ?";
        
        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param("i", $lesson_id);
        $stmt->execute();
        $result = $stmt->get_result();
        return $result->fetch_assoc();
    }
    
    /**
     * Get next lesson in course
     */
    public function getNextLesson($course_id, $current_lesson_order) {
        $sql = "SELECT * FROM {$this->table} 
                WHERE course_id = ? AND lesson_order > ? 
                ORDER BY lesson_order ASC 
                LIMIT 1";
        
        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param("ii", $course_id, $current_lesson_order);
        $stmt->execute();
        $result = $stmt->get_result();
        return $result->fetch_assoc();
    }
    
    /**
     * Get previous lesson in course
     */
    public function getPreviousLesson($course_id, $current_lesson_order) {
        $sql = "SELECT * FROM {$this->table} 
                WHERE course_id = ? AND lesson_order < ? 
                ORDER BY lesson_order DESC 
                LIMIT 1";
        
        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param("ii", $course_id, $current_lesson_order);
        $stmt->execute();
        $result = $stmt->get_result();
        return $result->fetch_assoc();
    }
}






