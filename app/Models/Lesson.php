<?php
require_once __DIR__ . '/../../core/Model.php';

class Lesson extends Model {
    protected $table = 'lessons';
    
    public function __construct($conn) {
        parent::__construct($conn);
    }
    
    /**
     * Get lessons for a student
     */
    public function getByStudent($studentId, $status = null) {
        $conditions = ['student_id' => $studentId];
        if ($status) {
            $conditions['status'] = $status;
        }
        return $this->all($conditions, 'lesson_date DESC, start_time DESC');
    }
    
    /**
     * Get lessons for a teacher
     */
    public function getByTeacher($teacherId, $status = null) {
        $conditions = ['teacher_id' => $teacherId];
        if ($status) {
            $conditions['status'] = $status;
        }
        return $this->all($conditions, 'lesson_date DESC, start_time DESC');
    }
    
    /**
     * Create lesson
     */
    public function createLesson($teacherId, $studentId, $lessonDate, $startTime, $endTime, $googleEventId = null) {
        $data = [
            'teacher_id' => $teacherId,
            'student_id' => $studentId,
            'lesson_date' => $lessonDate,
            'start_time' => $startTime,
            'end_time' => $endTime,
            'status' => 'scheduled',
            'google_calendar_event_id' => $googleEventId
        ];
        return $this->create($data);
    }
    
    /**
     * Update lesson status
     */
    public function updateStatus($id, $status) {
        return $this->update($id, ['status' => $status]);
    }
}

