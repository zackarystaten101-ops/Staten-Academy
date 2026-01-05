<?php
require_once __DIR__ . '/../../core/Model.php';

class TimeOff extends Model {
    protected $table = 'time_off';
    
    public function __construct($conn) {
        parent::__construct($conn);
    }
    
    /**
     * Get all time-off periods for a teacher
     */
    public function getByTeacher($teacherId, $dateFrom = null, $dateTo = null) {
        if ($dateFrom && $dateTo) {
            $stmt = $this->conn->prepare("
                SELECT * FROM time_off 
                WHERE teacher_id = ? 
                AND ((start_date <= ? AND end_date >= ?) OR (start_date BETWEEN ? AND ?) OR (end_date BETWEEN ? AND ?))
                ORDER BY start_date ASC
            ");
            $stmt->bind_param("issssss", $teacherId, $dateTo, $dateFrom, $dateFrom, $dateTo, $dateFrom, $dateTo);
        } else {
            $stmt = $this->conn->prepare("
                SELECT * FROM time_off 
                WHERE teacher_id = ? 
                ORDER BY start_date ASC
            ");
            $stmt->bind_param("i", $teacherId);
        }
        $stmt->execute();
        $result = $stmt->get_result();
        $timeOffs = [];
        while ($row = $result->fetch_assoc()) {
            $timeOffs[] = $row;
        }
        $stmt->close();
        return $timeOffs;
    }
    
    /**
     * Check if a date range conflicts with time-off
     */
    public function hasConflict($teacherId, $startDate, $endDate) {
        $stmt = $this->conn->prepare("
            SELECT id FROM time_off 
            WHERE teacher_id = ? 
            AND ((start_date <= ? AND end_date >= ?) OR (start_date BETWEEN ? AND ?) OR (end_date BETWEEN ? AND ?))
        ");
        $stmt->bind_param("issssss", $teacherId, $endDate, $startDate, $startDate, $endDate, $startDate, $endDate);
        $stmt->execute();
        $result = $stmt->get_result();
        $hasConflict = $result->num_rows > 0;
        $stmt->close();
        return $hasConflict;
    }
    
    /**
     * Create time-off period
     */
    public function createTimeOff($teacherId, $startDate, $endDate, $reason = null) {
        $data = [
            'teacher_id' => $teacherId,
            'start_date' => $startDate,
            'end_date' => $endDate,
            'reason' => $reason
        ];
        return $this->create($data);
    }
    
    /**
     * Delete time-off period
     */
    public function deleteTimeOff($id, $teacherId) {
        $stmt = $this->conn->prepare("DELETE FROM time_off WHERE id = ? AND teacher_id = ?");
        $stmt->bind_param("ii", $id, $teacherId);
        $result = $stmt->execute();
        $stmt->close();
        return $result;
    }
}











