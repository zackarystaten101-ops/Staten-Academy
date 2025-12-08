<?php
require_once __DIR__ . '/../../core/Model.php';

class RecurringLesson extends Model {
    protected $table = 'recurring_lessons';
    
    public function __construct($conn) {
        parent::__construct($conn);
    }
    
    /**
     * Get active recurring lessons for a teacher
     */
    public function getByTeacher($teacherId, $status = 'active') {
        $stmt = $this->conn->prepare("
            SELECT rl.*, u.name as student_name 
            FROM recurring_lessons rl
            JOIN users u ON rl.student_id = u.id
            WHERE rl.teacher_id = ? AND rl.status = ?
            ORDER BY rl.start_date ASC
        ");
        $stmt->bind_param("is", $teacherId, $status);
        $stmt->execute();
        $result = $stmt->get_result();
        $lessons = [];
        while ($row = $result->fetch_assoc()) {
            $lessons[] = $row;
        }
        $stmt->close();
        return $lessons;
    }
    
    /**
     * Get active recurring lessons for a student
     */
    public function getByStudent($studentId, $status = 'active') {
        $stmt = $this->conn->prepare("
            SELECT rl.*, u.name as teacher_name 
            FROM recurring_lessons rl
            JOIN users u ON rl.teacher_id = u.id
            WHERE rl.student_id = ? AND rl.status = ?
            ORDER BY rl.start_date ASC
        ");
        $stmt->bind_param("is", $studentId, $status);
        $stmt->execute();
        $result = $stmt->get_result();
        $lessons = [];
        while ($row = $result->fetch_assoc()) {
            $lessons[] = $row;
        }
        $stmt->close();
        return $lessons;
    }
    
    /**
     * Create recurring lesson series
     */
    public function createRecurring($teacherId, $studentId, $dayOfWeek, $startTime, $endTime, $startDate, $endDate = null, $frequencyWeeks = 1) {
        $data = [
            'teacher_id' => $teacherId,
            'student_id' => $studentId,
            'day_of_week' => $dayOfWeek,
            'start_time' => $startTime,
            'end_time' => $endTime,
            'start_date' => $startDate,
            'end_date' => $endDate,
            'frequency_weeks' => $frequencyWeeks,
            'status' => 'active'
        ];
        return $this->create($data);
    }
    
    /**
     * Update recurring lesson status
     */
    public function updateStatus($id, $status) {
        return $this->update($id, ['status' => $status]);
    }
    
    /**
     * Pause recurring lessons during time-off
     */
    public function pauseForTimeOff($teacherId, $startDate, $endDate) {
        $stmt = $this->conn->prepare("
            UPDATE recurring_lessons 
            SET status = 'paused' 
            WHERE teacher_id = ? 
            AND status = 'active'
            AND ((start_date <= ? AND (end_date IS NULL OR end_date >= ?)) OR start_date BETWEEN ? AND ?)
        ");
        $stmt->bind_param("isssss", $teacherId, $endDate, $startDate, $startDate, $endDate);
        $result = $stmt->execute();
        $stmt->close();
        return $result;
    }
    
    /**
     * Resume recurring lessons after time-off
     */
    public function resumeAfterTimeOff($teacherId) {
        $stmt = $this->conn->prepare("
            UPDATE recurring_lessons 
            SET status = 'active' 
            WHERE teacher_id = ? 
            AND status = 'paused'
        ");
        $stmt->bind_param("i", $teacherId);
        $result = $stmt->execute();
        $stmt->close();
        return $result;
    }
    
    /**
     * Generate individual lesson dates from recurring pattern
     */
    public function generateLessonDates($recurringId, $limit = 52) {
        $recurring = $this->find($recurringId);
        if (!$recurring || $recurring['status'] !== 'active') {
            return [];
        }
        
        $dates = [];
        try {
            $startDate = new DateTime($recurring['start_date']);
            $endDate = $recurring['end_date'] ? new DateTime($recurring['end_date']) : null;
            $dayOfWeek = $recurring['day_of_week'];
            $frequencyWeeks = (int)($recurring['frequency_weeks'] ?? 1);
            
            // Map day names to numbers (0 = Sunday, 1 = Monday, etc.)
            $dayMap = [
                'Sunday' => 0,
                'Monday' => 1,
                'Tuesday' => 2,
                'Wednesday' => 3,
                'Thursday' => 4,
                'Friday' => 5,
                'Saturday' => 6
            ];
            
            if (!isset($dayMap[$dayOfWeek])) {
                return [];
            }
            
            $targetDay = $dayMap[$dayOfWeek];
            
            // Find first occurrence of the target day
            $currentDate = clone $startDate;
            $currentDay = (int)$currentDate->format('w');
            $daysToAdd = ($targetDay - $currentDay + 7) % 7;
            if ($daysToAdd > 0) {
                $currentDate->modify("+{$daysToAdd} days");
            }
            
            // If we moved past the start date, check if we should include it
            if ($currentDate > $startDate && $currentDate->format('w') == $targetDay) {
                // Already on the right day, continue
            }
            
            $count = 0;
            while ($count < $limit) {
                if ($endDate && $currentDate > $endDate) {
                    break;
                }
                
                // Only add dates that are in the future or today
                if ($currentDate >= $startDate) {
                    $dates[] = $currentDate->format('Y-m-d');
                    $count++;
                }
                
                $currentDate->modify("+{$frequencyWeeks} weeks");
                
                // Safety check to prevent infinite loops
                if ($count > 0 && $currentDate <= $startDate) {
                    break;
                }
            }
        } catch (Exception $e) {
            error_log("Error generating lesson dates: " . $e->getMessage());
            return [];
        }
        
        return $dates;
    }
}

