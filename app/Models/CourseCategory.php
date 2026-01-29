<?php
require_once __DIR__ . '/../core/Model.php';

class CourseCategory extends Model {
    protected $table = 'course_categories';
    
    public function __construct($conn) {
        parent::__construct($conn);
    }
    
    /**
     * Get all categories ordered by display_order
     */
    public function getAllOrdered() {
        $sql = "SELECT * FROM {$this->table} ORDER BY display_order ASC, name ASC";
        return $this->conn->query($sql);
    }
    
    /**
     * Get category with course count
     */
    public function getCategoryWithCount($category_id) {
        $sql = "SELECT cc.*, 
                (SELECT COUNT(*) FROM courses WHERE category_id = cc.id AND is_active = TRUE) as course_count
                FROM {$this->table} cc
                WHERE cc.id = ?";
        
        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param("i", $category_id);
        $stmt->execute();
        $result = $stmt->get_result();
        return $result->fetch_assoc();
    }
}

















