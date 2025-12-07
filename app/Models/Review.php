<?php
require_once __DIR__ . '/../../core/Model.php';

class Review extends Model {
    protected $table = 'reviews';
    
    public function __construct($conn) {
        parent::__construct($conn);
    }
    
    /**
     * Get reviews for a teacher
     */
    public function getByTeacher($teacherId) {
        $sql = "SELECT r.*, u.name as student_name, u.profile_pic as student_pic 
                FROM {$this->table} r 
                JOIN users u ON r.student_id = u.id 
                WHERE r.teacher_id = ? 
                ORDER BY r.created_at DESC";
        return $this->query($sql, [$teacherId]);
    }
    
    /**
     * Get average rating for a teacher
     */
    public function getAverageRating($teacherId) {
        $stmt = $this->conn->prepare("SELECT AVG(rating) as avg_rating, COUNT(*) as count FROM {$this->table} WHERE teacher_id = ?");
        $stmt->bind_param("i", $teacherId);
        $stmt->execute();
        $result = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        return [
            'avg_rating' => round($result['avg_rating'] ?? 0, 1),
            'count' => $result['count'] ?? 0
        ];
    }
    
    /**
     * Create review
     */
    public function createReview($teacherId, $studentId, $rating, $comment = '') {
        $data = [
            'teacher_id' => $teacherId,
            'student_id' => $studentId,
            'rating' => $rating,
            'comment' => $comment
        ];
        return $this->create($data);
    }
}

