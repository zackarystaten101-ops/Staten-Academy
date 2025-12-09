<?php
require_once __DIR__ . '/../core/Model.php';

class CourseEnrollment extends Model {
    protected $table = 'course_enrollments';
    
    public function __construct($conn) {
        parent::__construct($conn);
    }
    
    /**
     * Check if user is enrolled in course
     */
    public function isEnrolled($user_id, $course_id) {
        $sql = "SELECT * FROM {$this->table} 
                WHERE user_id = ? AND course_id = ? 
                AND (expires_at IS NULL OR expires_at > NOW())";
        
        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param("ii", $user_id, $course_id);
        $stmt->execute();
        $result = $stmt->get_result();
        return $result->num_rows > 0;
    }
    
    /**
     * Enroll user in course
     */
    public function enroll($user_id, $course_id, $enrollment_type = 'plan', $plan_id = null, $expires_at = null) {
        // Check if already enrolled
        if ($this->isEnrolled($user_id, $course_id)) {
            return false;
        }
        
        $sql = "INSERT INTO {$this->table} (user_id, course_id, enrollment_type, plan_id, expires_at) 
                VALUES (?, ?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE 
                enrollment_type = VALUES(enrollment_type),
                plan_id = VALUES(plan_id),
                expires_at = VALUES(expires_at),
                enrolled_at = NOW()";
        
        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param("iisss", $user_id, $course_id, $enrollment_type, $plan_id, $expires_at);
        return $stmt->execute();
    }
    
    /**
     * Get user's enrolled courses
     */
    public function getUserEnrollments($user_id) {
        $sql = "SELECT ce.*, 
                c.title, c.description, c.thumbnail_url, c.difficulty_level,
                cc.name as category_name,
                (SELECT COUNT(*) FROM course_lessons WHERE course_id = c.id) as lesson_count,
                (SELECT progress_percentage FROM user_course_progress WHERE user_id = ? AND course_id = c.id) as progress
                FROM {$this->table} ce
                JOIN courses c ON ce.course_id = c.id
                LEFT JOIN course_categories cc ON c.category_id = cc.id
                WHERE ce.user_id = ? 
                AND (ce.expires_at IS NULL OR ce.expires_at > NOW())
                ORDER BY ce.enrolled_at DESC";
        
        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param("ii", $user_id, $user_id);
        $stmt->execute();
        return $stmt->get_result();
    }
    
    /**
     * Get courses user has access to via plan
     */
    public function getAccessibleCourses($user_id) {
        // Get user's subscription plan
        $user_sql = "SELECT subscription_plan_id, subscription_status FROM users WHERE id = ?";
        $user_stmt = $this->conn->prepare($user_sql);
        $user_stmt->bind_param("i", $user_id);
        $user_stmt->execute();
        $user_result = $user_stmt->get_result();
        $user = $user_result->fetch_assoc();
        $user_stmt->close();
        
        if (!$user || $user['subscription_status'] !== 'active') {
            return [];
        }
        
        $plan_id = $user['subscription_plan_id'];
        
        // Get plan details
        $plan_sql = "SELECT has_all_courses, max_course_categories FROM subscription_plans WHERE id = ?";
        $plan_stmt = $this->conn->prepare($plan_sql);
        $plan_stmt->bind_param("i", $plan_id);
        $plan_stmt->execute();
        $plan_result = $plan_stmt->get_result();
        $plan = $plan_result->fetch_assoc();
        $plan_stmt->close();
        
        if (!$plan) {
            return [];
        }
        
        // If plan has all courses, return all active courses
        if ($plan['has_all_courses']) {
            $sql = "SELECT DISTINCT c.* FROM courses c WHERE c.is_active = TRUE";
            return $this->conn->query($sql);
        }
        
        // Otherwise, get courses from selected categories
        $sql = "SELECT DISTINCT c.* 
                FROM courses c
                JOIN user_selected_courses usc ON c.category_id = usc.category_id
                WHERE usc.user_id = ? AND usc.plan_id = ? AND c.is_active = TRUE";
        
        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param("ii", $user_id, $plan_id);
        $stmt->execute();
        return $stmt->get_result();
    }
}








