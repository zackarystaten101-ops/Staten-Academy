<?php
require_once __DIR__ . '/../core/Model.php';

class Course extends Model {
    protected $table = 'courses';
    
    public function __construct($conn) {
        parent::__construct($conn);
    }
    
    /**
     * Get all active courses
     */
    public function getActiveCourses($category_id = null) {
        $sql = "SELECT c.*, 
                cc.name as category_name, 
                cc.icon as category_icon,
                cc.color as category_color,
                u.name as instructor_name,
                (SELECT AVG(rating) FROM course_reviews WHERE course_id = c.id) as avg_rating,
                (SELECT COUNT(*) FROM course_reviews WHERE course_id = c.id) as review_count,
                (SELECT COUNT(*) FROM course_lessons WHERE course_id = c.id) as lesson_count
                FROM courses c
                LEFT JOIN course_categories cc ON c.category_id = cc.id
                LEFT JOIN users u ON c.instructor_id = u.id
                WHERE c.is_active = TRUE";
        
        $params = [];
        $types = "";
        
        if ($category_id) {
            $sql .= " AND c.category_id = ?";
            $params[] = $category_id;
            $types .= "i";
        }
        
        $sql .= " ORDER BY c.is_featured DESC, c.created_at DESC";
        
        if (count($params) > 0) {
            $stmt = $this->conn->prepare($sql);
            $stmt->bind_param($types, ...$params);
            $stmt->execute();
            return $stmt->get_result();
        }
        
        return $this->conn->query($sql);
    }
    
    /**
     * Get featured courses
     */
    public function getFeaturedCourses($limit = 6) {
        $sql = "SELECT c.*, 
                cc.name as category_name, 
                cc.icon as category_icon,
                cc.color as category_color,
                u.name as instructor_name,
                (SELECT AVG(rating) FROM course_reviews WHERE course_id = c.id) as avg_rating,
                (SELECT COUNT(*) FROM course_reviews WHERE course_id = c.id) as review_count
                FROM courses c
                LEFT JOIN course_categories cc ON c.category_id = cc.id
                LEFT JOIN users u ON c.instructor_id = u.id
                WHERE c.is_active = TRUE AND c.is_featured = TRUE
                ORDER BY c.created_at DESC
                LIMIT ?";
        
        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param("i", $limit);
        $stmt->execute();
        return $stmt->get_result();
    }
    
    /**
     * Get course with full details
     */
    public function getCourseDetails($course_id) {
        $sql = "SELECT c.*, 
                cc.name as category_name, 
                cc.icon as category_icon,
                cc.color as category_color,
                u.name as instructor_name,
                u.profile_pic as instructor_pic,
                (SELECT AVG(rating) FROM course_reviews WHERE course_id = c.id) as avg_rating,
                (SELECT COUNT(*) FROM course_reviews WHERE course_id = c.id) as review_count
                FROM courses c
                LEFT JOIN course_categories cc ON c.category_id = cc.id
                LEFT JOIN users u ON c.instructor_id = u.id
                WHERE c.id = ? AND c.is_active = TRUE";
        
        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param("i", $course_id);
        $stmt->execute();
        $result = $stmt->get_result();
        return $result->fetch_assoc();
    }
    
    /**
     * Get courses by instructor
     */
    public function getCoursesByInstructor($instructor_id) {
        $sql = "SELECT c.*, 
                cc.name as category_name,
                (SELECT AVG(rating) FROM course_reviews WHERE course_id = c.id) as avg_rating,
                (SELECT COUNT(*) FROM course_reviews WHERE course_id = c.id) as review_count,
                (SELECT COUNT(*) FROM course_enrollments WHERE course_id = c.id) as enrollment_count
                FROM courses c
                LEFT JOIN course_categories cc ON c.category_id = cc.id
                WHERE c.instructor_id = ?
                ORDER BY c.created_at DESC";
        
        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param("i", $instructor_id);
        $stmt->execute();
        return $stmt->get_result();
    }
}











