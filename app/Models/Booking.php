<?php
require_once __DIR__ . '/../../core/Model.php';

class Booking extends Model {
    protected $table = 'bookings';
    
    public function __construct($conn) {
        parent::__construct($conn);
    }
    
    /**
     * Get bookings for a student
     */
    public function getByStudent($studentId) {
        return $this->all(['student_id' => $studentId], 'booking_date DESC');
    }
    
    /**
     * Get bookings for a teacher
     */
    public function getByTeacher($teacherId) {
        return $this->all(['teacher_id' => $teacherId], 'booking_date DESC');
    }
    
    /**
     * Create booking
     */
    public function createBooking($studentId, $teacherId, $bookingDate = null) {
        $data = [
            'student_id' => $studentId,
            'teacher_id' => $teacherId,
            'booking_date' => $bookingDate ?: date('Y-m-d H:i:s')
        ];
        return $this->create($data);
    }
}

