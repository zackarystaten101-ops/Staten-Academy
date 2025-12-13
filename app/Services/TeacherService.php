<?php
/**
 * Teacher Service
 * Handles teacher-related operations for student-selects-teacher model
 */

class TeacherService {
    private $conn;
    
    public function __construct($conn) {
        $this->conn = $conn;
    }
    
    /**
     * Get all teachers in a specific category
     * @param string $category - young_learners, adults, or coding
     * @param array $filters - Optional filters (availability, min_rating, max_price)
     * @return array
     */
    public function getTeachersByCategory($category, $filters = []) {
        $category = $this->conn->real_escape_string($category);
        
        $sql = "SELECT DISTINCT u.id, u.name, u.email, u.profile_pic, u.bio, u.about_text, 
                       u.video_url, u.specialty, u.hourly_rate, u.total_lessons, 
                       u.avg_rating, u.review_count,
                       tc.category, tc.is_active
                FROM users u
                INNER JOIN teacher_categories tc ON u.id = tc.teacher_id
                WHERE u.role = 'teacher'
                AND tc.category = ?
                AND tc.is_active = TRUE
                AND u.application_status = 'approved'";
        
        // Apply filters
        if (isset($filters['min_rating']) && is_numeric($filters['min_rating'])) {
            $sql .= " AND (u.avg_rating IS NULL OR u.avg_rating >= " . floatval($filters['min_rating']) . ")";
        }
        
        if (isset($filters['max_price']) && is_numeric($filters['max_price'])) {
            $sql .= " AND (u.hourly_rate IS NULL OR u.hourly_rate <= " . floatval($filters['max_price']) . ")";
        }
        
        $sql .= " ORDER BY u.avg_rating DESC, u.review_count DESC, u.name ASC";
        
        $stmt = $this->conn->prepare($sql);
        if (!$stmt) {
            error_log("TeacherService::getTeachersByCategory - Prepare failed: " . $this->conn->error);
            return [];
        }
        
        $stmt->bind_param("s", $category);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $teachers = [];
        while ($row = $result->fetch_assoc()) {
            $teachers[] = $row;
        }
        
        $stmt->close();
        
        // Filter by availability if requested
        if (isset($filters['has_availability']) && $filters['has_availability']) {
            $teachers = array_filter($teachers, function($teacher) {
                return $this->hasAvailableSlots($teacher['id']);
            });
        }
        
        return $teachers;
    }
    
    /**
     * Get full teacher profile
     * @param int $teacher_id
     * @return array|null
     */
    public function getTeacherProfile($teacher_id) {
        $teacher_id = intval($teacher_id);
        
        $sql = "SELECT u.*, 
                       GROUP_CONCAT(DISTINCT tc.category) as categories
                FROM users u
                LEFT JOIN teacher_categories tc ON u.id = tc.teacher_id AND tc.is_active = TRUE
                WHERE u.id = ? AND u.role = 'teacher'
                GROUP BY u.id";
        
        $stmt = $this->conn->prepare($sql);
        if (!$stmt) {
            error_log("TeacherService::getTeacherProfile - Prepare failed: " . $this->conn->error);
            return null;
        }
        
        $stmt->bind_param("i", $teacher_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $teacher = $result->fetch_assoc();
        $stmt->close();
        
        if ($teacher) {
            $teacher['categories'] = $teacher['categories'] ? explode(',', $teacher['categories']) : [];
        }
        
        return $teacher;
    }
    
    /**
     * Get teacher availability for a date range
     * @param int $teacher_id
     * @param string $start_date - Y-m-d format
     * @param string $end_date - Y-m-d format
     * @return array
     */
    public function getTeacherAvailability($teacher_id, $start_date = null, $end_date = null) {
        $teacher_id = intval($teacher_id);
        
        // Get recurring availability slots
        $sql = "SELECT day_of_week, start_time, end_time, timezone, is_recurring, specific_date, is_available
                FROM teacher_availability_slots
                WHERE teacher_id = ? AND is_available = TRUE";
        
        $params = [$teacher_id];
        $types = "i";
        
        if ($start_date && $end_date) {
            $sql .= " AND (specific_date IS NULL OR (specific_date >= ? AND specific_date <= ?))";
            $params[] = $start_date;
            $params[] = $end_date;
            $types .= "ss";
        } elseif ($start_date) {
            $sql .= " AND (specific_date IS NULL OR specific_date >= ?)";
            $params[] = $start_date;
            $types .= "s";
        }
        
        $sql .= " ORDER BY day_of_week, start_time";
        
        $stmt = $this->conn->prepare($sql);
        if (!$stmt) {
            error_log("TeacherService::getTeacherAvailability - Prepare failed: " . $this->conn->error);
            return [];
        }
        
        if (count($params) > 1) {
            $stmt->bind_param($types, ...$params);
        } else {
            $stmt->bind_param($types, $teacher_id);
        }
        
        $stmt->execute();
        $result = $stmt->get_result();
        
        $slots = [];
        while ($row = $result->fetch_assoc()) {
            $slots[] = $row;
        }
        
        $stmt->close();
        
        // Also check legacy teacher_availability table
        $legacy_sql = "SELECT day_of_week, start_time, end_time
                      FROM teacher_availability
                      WHERE teacher_id = ? AND is_available = TRUE
                      ORDER BY day_of_week, start_time";
        
        $legacy_stmt = $this->conn->prepare($legacy_sql);
        if ($legacy_stmt) {
            $legacy_stmt->bind_param("i", $teacher_id);
            $legacy_stmt->execute();
            $legacy_result = $legacy_stmt->get_result();
            
            while ($row = $legacy_result->fetch_assoc()) {
                // Convert to new format
                $row['timezone'] = 'UTC';
                $row['is_recurring'] = true;
                $row['specific_date'] = null;
                $row['is_available'] = true;
                $slots[] = $row;
            }
            
            $legacy_stmt->close();
        }
        
        return $slots;
    }
    
    /**
     * Check if a specific slot is available
     * @param int $teacher_id
     * @param string $date - Y-m-d format
     * @param string $time - H:i format
     * @return bool
     */
    public function checkSlotAvailability($teacher_id, $date, $time) {
        $teacher_id = intval($teacher_id);
        $date = $this->conn->real_escape_string($date);
        $time = $this->conn->real_escape_string($time);
        
        // Check if there's a conflicting lesson
        $conflict_sql = "SELECT id FROM lessons 
                        WHERE teacher_id = ? 
                        AND lesson_date = ? 
                        AND start_time = ? 
                        AND status != 'cancelled'";
        
        $stmt = $this->conn->prepare($conflict_sql);
        if (!$stmt) {
            return false;
        }
        
        $stmt->bind_param("iss", $teacher_id, $date, $time);
        $stmt->execute();
        $result = $stmt->get_result();
        $has_conflict = $result->num_rows > 0;
        $stmt->close();
        
        if ($has_conflict) {
            return false;
        }
        
        // Check if teacher has availability for this day/time
        $day_name = date('l', strtotime($date));
        
        $availability_sql = "SELECT id FROM teacher_availability_slots
                            WHERE teacher_id = ?
                            AND day_of_week = ?
                            AND start_time <= ?
                            AND end_time > ?
                            AND is_available = TRUE
                            AND (specific_date IS NULL OR specific_date = ?)";
        
        $stmt = $this->conn->prepare($availability_sql);
        if (!$stmt) {
            return false;
        }
        
        $stmt->bind_param("issss", $teacher_id, $day_name, $time, $time, $date);
        $stmt->execute();
        $result = $stmt->get_result();
        $has_availability = $result->num_rows > 0;
        $stmt->close();
        
        // Also check legacy table
        if (!$has_availability) {
            $legacy_sql = "SELECT id FROM teacher_availability
                          WHERE teacher_id = ?
                          AND day_of_week = ?
                          AND start_time <= ?
                          AND end_time > ?
                          AND is_available = TRUE";
            
            $legacy_stmt = $this->conn->prepare($legacy_sql);
            if ($legacy_stmt) {
                $legacy_stmt->bind_param("isss", $teacher_id, $day_name, $time, $time);
                $legacy_stmt->execute();
                $legacy_result = $legacy_stmt->get_result();
                $has_availability = $legacy_result->num_rows > 0;
                $legacy_stmt->close();
            }
        }
        
        return $has_availability;
    }
    
    /**
     * Check if teacher has any available slots
     * @param int $teacher_id
     * @return bool
     */
    private function hasAvailableSlots($teacher_id) {
        $teacher_id = intval($teacher_id);
        
        $sql = "SELECT COUNT(*) as count FROM teacher_availability_slots
                WHERE teacher_id = ? AND is_available = TRUE";
        
        $stmt = $this->conn->prepare($sql);
        if (!$stmt) {
            return false;
        }
        
        $stmt->bind_param("i", $teacher_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        $stmt->close();
        
        if ($row['count'] > 0) {
            return true;
        }
        
        // Check legacy table
        $legacy_sql = "SELECT COUNT(*) as count FROM teacher_availability
                      WHERE teacher_id = ? AND is_available = TRUE";
        
        $legacy_stmt = $this->conn->prepare($legacy_sql);
        if ($legacy_stmt) {
            $legacy_stmt->bind_param("i", $teacher_id);
            $legacy_stmt->execute();
            $legacy_result = $legacy_stmt->get_result();
            $legacy_row = $legacy_result->fetch_assoc();
            $legacy_stmt->close();
            
            return $legacy_row['count'] > 0;
        }
        
        return false;
    }
    
    /**
     * Get teacher reviews
     * @param int $teacher_id
     * @param int $limit
     * @return array
     */
    public function getTeacherReviews($teacher_id, $limit = 10) {
        $teacher_id = intval($teacher_id);
        $limit = intval($limit);
        
        $sql = "SELECT r.*, u.name as student_name, u.profile_pic as student_pic
                FROM reviews r
                JOIN users u ON r.student_id = u.id
                WHERE r.teacher_id = ? AND r.is_public = TRUE
                ORDER BY r.created_at DESC
                LIMIT ?";
        
        $stmt = $this->conn->prepare($sql);
        if (!$stmt) {
            return [];
        }
        
        $stmt->bind_param("ii", $teacher_id, $limit);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $reviews = [];
        while ($row = $result->fetch_assoc()) {
            $reviews[] = $row;
        }
        
        $stmt->close();
        return $reviews;
    }
}


