<?php
require_once __DIR__ . '/../core/Model.php';

class TeacherAssignment extends Model {
    protected $table = 'teacher_assignments';
    
    public function __construct($conn) {
        parent::__construct($conn);
    }
    
    /**
     * Assign a teacher to a student
     */
    public function assignTeacher($studentId, $teacherId, $adminId, $track, $notes = null) {
        // Deactivate any existing active assignments for this student
        $this->deactivateStudentAssignments($studentId);
        
        // Create new assignment
        $sql = "INSERT INTO teacher_assignments (student_id, teacher_id, track, assigned_by, notes, status) 
                VALUES (?, ?, ?, ?, ?, 'active')";
        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param("iisss", $studentId, $teacherId, $track, $adminId, $notes);
        $result = $stmt->execute();
        $assignmentId = $stmt->insert_id;
        $stmt->close();
        
        if ($result) {
            // Update user's assigned_teacher_id
            $updateSql = "UPDATE users SET assigned_teacher_id = ? WHERE id = ?";
            $updateStmt = $this->conn->prepare($updateSql);
            $updateStmt->bind_param("ii", $teacherId, $studentId);
            $updateStmt->execute();
            $updateStmt->close();
        }
        
        return $assignmentId;
    }
    
    /**
     * Get student's assigned teacher
     */
    public function getStudentTeacher($studentId) {
        $sql = "SELECT ta.*, u.name as teacher_name, u.email as teacher_email, 
                u.profile_pic as teacher_pic, u.bio as teacher_bio,
                admin.name as assigned_by_name
                FROM teacher_assignments ta
                JOIN users u ON ta.teacher_id = u.id
                LEFT JOIN users admin ON ta.assigned_by = admin.id
                WHERE ta.student_id = ? AND ta.status = 'active'
                ORDER BY ta.assigned_at DESC
                LIMIT 1";
        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param("i", $studentId);
        $stmt->execute();
        $result = $stmt->get_result();
        $assignment = $result->fetch_assoc();
        $stmt->close();
        return $assignment;
    }
    
    /**
     * Get all students assigned to a teacher
     */
    public function getTeacherStudents($teacherId) {
        $sql = "SELECT ta.*, u.name as student_name, u.email as student_email, 
                u.profile_pic as student_pic, u.learning_track,
                admin.name as assigned_by_name
                FROM teacher_assignments ta
                JOIN users u ON ta.student_id = u.id
                LEFT JOIN users admin ON ta.assigned_by = admin.id
                WHERE ta.teacher_id = ? AND ta.status = 'active'
                ORDER BY ta.assigned_at DESC";
        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param("i", $teacherId);
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
     * Deactivate all active assignments for a student
     */
    private function deactivateStudentAssignments($studentId) {
        $sql = "UPDATE teacher_assignments SET status = 'inactive' 
                WHERE student_id = ? AND status = 'active'";
        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param("i", $studentId);
        $stmt->execute();
        $stmt->close();
    }
    
    /**
     * Transfer assignment (deactivate old, create new)
     */
    public function transferAssignment($studentId, $newTeacherId, $adminId, $notes = null) {
        $oldAssignment = $this->getStudentTeacher($studentId);
        $track = $oldAssignment['track'] ?? null;
        
        if (!$track) {
            // Get track from student record
            $studentSql = "SELECT learning_track FROM users WHERE id = ?";
            $studentStmt = $this->conn->prepare($studentSql);
            $studentStmt->bind_param("i", $studentId);
            $studentStmt->execute();
            $studentResult = $studentStmt->get_result();
            $student = $studentResult->fetch_assoc();
            $track = $student['learning_track'];
            $studentStmt->close();
        }
        
        // Mark old assignment as transferred
        if ($oldAssignment) {
            $updateSql = "UPDATE teacher_assignments SET status = 'transferred' WHERE id = ?";
            $updateStmt = $this->conn->prepare($updateSql);
            $updateStmt->bind_param("i", $oldAssignment['id']);
            $updateStmt->execute();
            $updateStmt->close();
        }
        
        // Create new assignment
        return $this->assignTeacher($studentId, $newTeacherId, $adminId, $track, $notes);
    }
    
    /**
     * Get assignment history for a student
     */
    public function getStudentAssignmentHistory($studentId) {
        $sql = "SELECT ta.*, u.name as teacher_name, u.email as teacher_email,
                admin.name as assigned_by_name
                FROM teacher_assignments ta
                JOIN users u ON ta.teacher_id = u.id
                LEFT JOIN users admin ON ta.assigned_by = admin.id
                WHERE ta.student_id = ?
                ORDER BY ta.assigned_at DESC";
        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param("i", $studentId);
        $stmt->execute();
        $result = $stmt->get_result();
        $history = [];
        while ($row = $result->fetch_assoc()) {
            $history[] = $row;
        }
        $stmt->close();
        return $history;
    }
}

