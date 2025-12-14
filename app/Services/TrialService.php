<?php
/**
 * Trial Service
 * Handles trial lesson operations
 */

class TrialService {
    private $conn;
    
    public function __construct($conn) {
        $this->conn = $conn;
    }
    
    /**
     * Check if student is eligible for a trial lesson
     * @param int $student_id
     * @return array ['eligible' => bool, 'reason' => string]
     */
    public function checkTrialEligibility($student_id) {
        $student_id = intval($student_id);
        
        // Check if student has already used trial
        $stmt = $this->conn->prepare("SELECT trial_used FROM users WHERE id = ?");
        $stmt->bind_param("i", $student_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 0) {
            $stmt->close();
            return ['eligible' => false, 'reason' => 'Student not found'];
        }
        
        $user = $result->fetch_assoc();
        $stmt->close();
        
        if ($user['trial_used']) {
            return ['eligible' => false, 'reason' => 'Trial lesson already used'];
        }
        
        // Also check trial_lessons table
        $trial_check = $this->conn->prepare("SELECT id FROM trial_lessons WHERE student_id = ?");
        $trial_check->bind_param("i", $student_id);
        $trial_check->execute();
        $trial_result = $trial_check->get_result();
        
        if ($trial_result->num_rows > 0) {
            $trial_check->close();
            return ['eligible' => false, 'reason' => 'Trial lesson already used'];
        }
        
        $trial_check->close();
        
        return ['eligible' => true, 'reason' => ''];
    }
    
    /**
     * Create a trial lesson booking
     * @param int $student_id
     * @param int $teacher_id
     * @param string $date - Y-m-d format
     * @param string $time - H:i format
     * @param string $stripe_payment_id
     * @return array ['success' => bool, 'lesson_id' => int|null, 'error' => string]
     */
    public function createTrialBooking($student_id, $teacher_id, $date, $time, $stripe_payment_id) {
        $student_id = intval($student_id);
        $teacher_id = intval($teacher_id);
        $date = $this->conn->real_escape_string($date);
        $time = $this->conn->real_escape_string($time);
        $stripe_payment_id = $this->conn->real_escape_string($stripe_payment_id);
        
        // Check eligibility
        $eligibility = $this->checkTrialEligibility($student_id);
        if (!$eligibility['eligible']) {
            return ['success' => false, 'lesson_id' => null, 'error' => $eligibility['reason']];
        }
        
        // Calculate end time (1 hour later)
        $start_datetime = strtotime($date . ' ' . $time);
        $end_datetime = $start_datetime + 3600; // 1 hour
        $end_time = date('H:i', $end_datetime);
        
        // Start transaction
        $this->conn->begin_transaction();
        
        try {
            // Create lesson record
            $lesson_sql = "INSERT INTO lessons (teacher_id, student_id, lesson_date, start_time, end_time, status, is_trial, category)
                          VALUES (?, ?, ?, ?, ?, 'scheduled', TRUE, 
                          (SELECT preferred_category FROM users WHERE id = ?))";
            
            $stmt = $this->conn->prepare($lesson_sql);
            if (!$stmt) {
                throw new Exception("Failed to prepare lesson statement: " . $this->conn->error);
            }
            
            $stmt->bind_param("iisssi", $teacher_id, $student_id, $date, $time, $end_time, $student_id);
            
            if (!$stmt->execute()) {
                throw new Exception("Failed to create lesson: " . $stmt->error);
            }
            
            $lesson_id = $stmt->insert_id;
            $stmt->close();
            
            // Create trial_lessons record
            $trial_sql = "INSERT INTO trial_lessons (student_id, teacher_id, lesson_id, stripe_payment_id, used_at)
                         VALUES (?, ?, ?, ?, NOW())";
            
            $trial_stmt = $this->conn->prepare($trial_sql);
            if (!$trial_stmt) {
                throw new Exception("Failed to prepare trial statement: " . $this->conn->error);
            }
            
            $trial_stmt->bind_param("iiis", $student_id, $teacher_id, $lesson_id, $stripe_payment_id);
            
            if (!$trial_stmt->execute()) {
                throw new Exception("Failed to create trial record: " . $trial_stmt->error);
            }
            
            $trial_stmt->close();
            
            // Mark trial as used in users table
            $update_sql = "UPDATE users SET trial_used = TRUE WHERE id = ?";
            $update_stmt = $this->conn->prepare($update_sql);
            if (!$update_stmt) {
                throw new Exception("Failed to prepare update statement: " . $this->conn->error);
            }
            
            $update_stmt->bind_param("i", $student_id);
            
            if (!$update_stmt->execute()) {
                throw new Exception("Failed to update trial_used: " . $update_stmt->error);
            }
            
            $update_stmt->close();
            
            // Commit transaction
            $this->conn->commit();
            
            return ['success' => true, 'lesson_id' => $lesson_id, 'error' => null];
            
        } catch (Exception $e) {
            // Rollback transaction
            $this->conn->rollback();
            error_log("TrialService::createTrialBooking - Error: " . $e->getMessage());
            return ['success' => false, 'lesson_id' => null, 'error' => $e->getMessage()];
        }
    }
    
    /**
     * Mark trial as used (called after payment confirmation)
     * @param int $student_id
     * @return bool
     */
    public function markTrialAsUsed($student_id) {
        $student_id = intval($student_id);
        
        $stmt = $this->conn->prepare("UPDATE users SET trial_used = TRUE WHERE id = ?");
        if (!$stmt) {
            error_log("TrialService::markTrialAsUsed - Prepare failed: " . $this->conn->error);
            return false;
        }
        
        $stmt->bind_param("i", $student_id);
        $result = $stmt->execute();
        $stmt->close();
        
        return $result;
    }
    
    /**
     * Get trial lesson info for a student
     * @param int $student_id
     * @return array|null
     */
    public function getTrialInfo($student_id) {
        $student_id = intval($student_id);
        
        $sql = "SELECT tl.*, l.lesson_date, l.start_time, l.end_time, l.status,
                       u.name as teacher_name, u.profile_pic as teacher_pic
                FROM trial_lessons tl
                JOIN lessons l ON tl.lesson_id = l.id
                JOIN users u ON tl.teacher_id = u.id
                WHERE tl.student_id = ?
                ORDER BY tl.created_at DESC
                LIMIT 1";
        
        $stmt = $this->conn->prepare($sql);
        if (!$stmt) {
            return null;
        }
        
        $stmt->bind_param("i", $student_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $trial = $result->fetch_assoc();
        $stmt->close();
        
        return $trial;
    }
}



