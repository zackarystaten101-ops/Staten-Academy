<?php
require_once __DIR__ . '/../../core/Model.php';

class Assignment extends Model {
    protected $table = 'assignments';
    
    public function __construct($conn) {
        parent::__construct($conn);
    }
    
    /**
     * Get assignments for a student
     */
    public function getByStudent($studentId, $status = null) {
        $conditions = ['student_id' => $studentId];
        if ($status) {
            $conditions['status'] = $status;
        }
        return $this->all($conditions, 'due_date DESC');
    }
    
    /**
     * Get assignments for a teacher
     */
    public function getByTeacher($teacherId, $status = null) {
        $conditions = ['teacher_id' => $teacherId];
        if ($status) {
            $conditions['status'] = $status;
        }
        return $this->all($conditions, 'due_date DESC');
    }
    
    /**
     * Get pending assignments count for teacher
     */
    public function getPendingCount($teacherId) {
        $stmt = $this->conn->prepare("SELECT COUNT(*) as count FROM {$this->table} WHERE teacher_id = ? AND status = 'submitted'");
        $stmt->bind_param("i", $teacherId);
        $stmt->execute();
        $result = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        return $result['count'] ?? 0;
    }
}

