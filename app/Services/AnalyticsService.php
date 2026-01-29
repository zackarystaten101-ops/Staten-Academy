<?php
/**
 * Analytics Service
 * Provides platform usage, teacher performance, and student engagement metrics
 */

class AnalyticsService {
    private $conn;
    
    public function __construct($conn) {
        $this->conn = $conn;
    }
    
    /**
     * Get platform usage metrics
     */
    public function getPlatformMetrics($days = 30) {
        $metrics = [
            'total_users' => 0,
            'active_users' => 0,
            'new_registrations' => 0,
            'total_lessons' => 0,
            'completed_lessons' => 0,
            'cancelled_lessons' => 0,
            'total_bookings' => 0,
            'revenue' => 0
        ];
        
        // Total users
        $total_users = $this->conn->query("SELECT COUNT(*) as count FROM users WHERE role IN ('student', 'new_student', 'teacher')");
        if ($row = $total_users->fetch_assoc()) {
            $metrics['total_users'] = (int)$row['count'];
        }
        
        // Active users (logged in within last 30 days)
        $active_sql = "SELECT COUNT(DISTINCT user_id) as count FROM user_sessions 
                      WHERE last_activity >= DATE_SUB(NOW(), INTERVAL ? DAY)";
        $active_stmt = $this->conn->prepare($active_sql);
        $active_stmt->bind_param("i", $days);
        $active_stmt->execute();
        $active_result = $active_stmt->get_result();
        if ($row = $active_result->fetch_assoc()) {
            $metrics['active_users'] = (int)$row['count'];
        }
        $active_stmt->close();
        
        // New registrations
        $new_reg_sql = "SELECT COUNT(*) as count FROM users 
                       WHERE role IN ('student', 'new_student', 'teacher') 
                       AND created_at >= DATE_SUB(NOW(), INTERVAL ? DAY)";
        $new_reg_stmt = $this->conn->prepare($new_reg_sql);
        $new_reg_stmt->bind_param("i", $days);
        $new_reg_stmt->execute();
        $new_reg_result = $new_reg_stmt->get_result();
        if ($row = $new_reg_result->fetch_assoc()) {
            $metrics['new_registrations'] = (int)$row['count'];
        }
        $new_reg_stmt->close();
        
        // Lesson statistics
        $lessons_sql = "SELECT 
                       COUNT(*) as total,
                       SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed,
                       SUM(CASE WHEN status = 'cancelled' THEN 1 ELSE 0 END) as cancelled
                       FROM lessons 
                       WHERE created_at >= DATE_SUB(NOW(), INTERVAL ? DAY)";
        $lessons_stmt = $this->conn->prepare($lessons_sql);
        $lessons_stmt->bind_param("i", $days);
        $lessons_stmt->execute();
        $lessons_result = $lessons_stmt->get_result();
        if ($row = $lessons_result->fetch_assoc()) {
            $metrics['total_lessons'] = (int)$row['total'];
            $metrics['completed_lessons'] = (int)$row['completed'];
            $metrics['cancelled_lessons'] = (int)$row['cancelled'];
        }
        $lessons_stmt->close();
        
        // Total bookings (same as total lessons)
        $metrics['total_bookings'] = $metrics['total_lessons'];
        
        // Revenue (from wallet transactions)
        $revenue_sql = "SELECT SUM(amount) as total FROM wallet_transactions 
                       WHERE transaction_type = 'top_up' 
                       AND status = 'confirmed'
                       AND created_at >= DATE_SUB(NOW(), INTERVAL ? DAY)";
        $revenue_stmt = $this->conn->prepare($revenue_sql);
        $revenue_stmt->bind_param("i", $days);
        $revenue_stmt->execute();
        $revenue_result = $revenue_stmt->get_result();
        if ($row = $revenue_result->fetch_assoc()) {
            $metrics['revenue'] = (float)($row['total'] ?? 0);
        }
        $revenue_stmt->close();
        
        return $metrics;
    }
    
    /**
     * Get teacher performance metrics
     */
    public function getTeacherPerformance($days = 30) {
        $teachers = [];
        
        $sql = "SELECT 
                u.id, u.name, u.email,
                COUNT(DISTINCT l.id) as total_lessons,
                SUM(CASE WHEN l.status = 'completed' THEN 1 ELSE 0 END) as completed_lessons,
                SUM(CASE WHEN l.status = 'cancelled' THEN 1 ELSE 0 END) as cancelled_lessons,
                AVG(r.rating) as avg_rating,
                COUNT(DISTINCT r.id) as review_count,
                COUNT(DISTINCT l.student_id) as unique_students
                FROM users u
                LEFT JOIN lessons l ON u.id = l.teacher_id 
                    AND l.created_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
                LEFT JOIN reviews r ON u.id = r.teacher_id
                WHERE u.role = 'teacher'
                GROUP BY u.id, u.name, u.email
                HAVING total_lessons > 0
                ORDER BY total_lessons DESC, avg_rating DESC";
        
        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param("i", $days);
        $stmt->execute();
        $result = $stmt->get_result();
        
        while ($row = $result->fetch_assoc()) {
            $completion_rate = $row['total_lessons'] > 0 
                ? round(($row['completed_lessons'] / $row['total_lessons']) * 100, 1) 
                : 0;
            
            $teachers[] = [
                'id' => $row['id'],
                'name' => $row['name'],
                'email' => $row['email'],
                'total_lessons' => (int)$row['total_lessons'],
                'completed_lessons' => (int)$row['completed_lessons'],
                'cancelled_lessons' => (int)$row['cancelled_lessons'],
                'completion_rate' => $completion_rate,
                'avg_rating' => (float)($row['avg_rating'] ?? 0),
                'review_count' => (int)$row['review_count'],
                'unique_students' => (int)$row['unique_students']
            ];
        }
        
        $stmt->close();
        return $teachers;
    }
    
    /**
     * Get student engagement metrics
     */
    public function getStudentEngagement($days = 30) {
        $metrics = [
            'total_students' => 0,
            'active_students' => 0,
            'avg_lessons_per_student' => 0,
            'course_enrollment_rate' => 0,
            'completion_rate' => 0
        ];
        
        // Total students
        $total_sql = "SELECT COUNT(*) as count FROM users WHERE role IN ('student', 'new_student')";
        $total_result = $this->conn->query($total_sql);
        if ($row = $total_result->fetch_assoc()) {
            $metrics['total_students'] = (int)$row['count'];
        }
        
        // Active students (booked lessons in last 30 days)
        $active_sql = "SELECT COUNT(DISTINCT student_id) as count FROM lessons 
                      WHERE student_id IN (SELECT id FROM users WHERE role IN ('student', 'new_student'))
                      AND created_at >= DATE_SUB(NOW(), INTERVAL ? DAY)";
        $active_stmt = $this->conn->prepare($active_sql);
        $active_stmt->bind_param("i", $days);
        $active_stmt->execute();
        $active_result = $active_stmt->get_result();
        if ($row = $active_result->fetch_assoc()) {
            $metrics['active_students'] = (int)$row['count'];
        }
        $active_stmt->close();
        
        // Average lessons per student
        $avg_sql = "SELECT AVG(lesson_count) as avg FROM (
                    SELECT student_id, COUNT(*) as lesson_count 
                    FROM lessons 
                    WHERE created_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
                    GROUP BY student_id
                    ) as student_lessons";
        $avg_stmt = $this->conn->prepare($avg_sql);
        $avg_stmt->bind_param("i", $days);
        $avg_stmt->execute();
        $avg_result = $avg_stmt->get_result();
        if ($row = $avg_result->fetch_assoc()) {
            $metrics['avg_lessons_per_student'] = round((float)($row['avg'] ?? 0), 1);
        }
        $avg_stmt->close();
        
        // Course enrollment rate
        $enrollment_sql = "SELECT 
                          (SELECT COUNT(DISTINCT user_id) FROM course_enrollments) as enrolled,
                          (SELECT COUNT(*) FROM users WHERE role IN ('student', 'new_student')) as total";
        $enrollment_result = $this->conn->query($enrollment_sql);
        if ($row = $enrollment_result->fetch_assoc()) {
            $total = (int)$row['total'];
            $enrolled = (int)$row['enrolled'];
            $metrics['course_enrollment_rate'] = $total > 0 ? round(($enrolled / $total) * 100, 1) : 0;
        }
        
        // Lesson completion rate
        $completion_sql = "SELECT 
                          SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed,
                          COUNT(*) as total
                          FROM lessons 
                          WHERE created_at >= DATE_SUB(NOW(), INTERVAL ? DAY)";
        $completion_stmt = $this->conn->prepare($completion_sql);
        $completion_stmt->bind_param("i", $days);
        $completion_stmt->execute();
        $completion_result = $completion_stmt->get_result();
        if ($row = $completion_result->fetch_assoc()) {
            $total = (int)$row['total'];
            $completed = (int)$row['completed'];
            $metrics['completion_rate'] = $total > 0 ? round(($completed / $total) * 100, 1) : 0;
        }
        $completion_stmt->close();
        
        return $metrics;
    }
    
    /**
     * Get trends over time
     */
    public function getTrends($days = 30) {
        $trends = [];
        
        $sql = "SELECT 
                DATE(created_at) as date,
                COUNT(*) as registrations,
                (SELECT COUNT(*) FROM lessons WHERE DATE(created_at) = DATE(u.created_at)) as lessons,
                (SELECT SUM(amount) FROM wallet_transactions 
                 WHERE transaction_type = 'top_up' AND status = 'confirmed' 
                 AND DATE(created_at) = DATE(u.created_at)) as revenue
                FROM users u
                WHERE created_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
                AND role IN ('student', 'new_student', 'teacher')
                GROUP BY DATE(created_at)
                ORDER BY date ASC";
        
        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param("i", $days);
        $stmt->execute();
        $result = $stmt->get_result();
        
        while ($row = $result->fetch_assoc()) {
            $trends[] = [
                'date' => $row['date'],
                'registrations' => (int)$row['registrations'],
                'lessons' => (int)($row['lessons'] ?? 0),
                'revenue' => (float)($row['revenue'] ?? 0)
            ];
        }
        
        $stmt->close();
        return $trends;
    }
}
