<?php
/**
 * Progress Service
 * Handles student progress tracking and analytics
 */

class ProgressService {
    private $conn;
    
    public function __construct($conn) {
        $this->conn = $conn;
    }
    
    /**
     * Get overall progress statistics for a student
     */
    public function getOverallProgress($student_id) {
        $stats = [
            'total_courses' => 0,
            'enrolled_courses' => 0,
            'completed_courses' => 0,
            'in_progress_courses' => 0,
            'total_lessons' => 0,
            'completed_lessons' => 0,
            'total_assignments' => 0,
            'completed_assignments' => 0,
            'learning_streak' => 0,
            'total_study_time' => 0
        ];
        
        // Get enrolled courses
        $enrollments_sql = "SELECT ce.*, c.title, 
                          (SELECT progress_percentage FROM user_course_progress WHERE user_id = ? AND course_id = c.id) as progress
                          FROM course_enrollments ce
                          JOIN courses c ON ce.course_id = c.id
                          WHERE ce.user_id = ? AND (ce.expires_at IS NULL OR ce.expires_at > NOW())";
        $enroll_stmt = $this->conn->prepare($enrollments_sql);
        $enroll_stmt->bind_param("ii", $student_id, $student_id);
        $enroll_stmt->execute();
        $enrollments = $enroll_stmt->get_result();
        
        $stats['enrolled_courses'] = $enrollments->num_rows;
        
        while ($enrollment = $enrollments->fetch_assoc()) {
            $progress = (float)($enrollment['progress'] ?? 0);
            if ($progress >= 100) {
                $stats['completed_courses']++;
            } elseif ($progress > 0) {
                $stats['in_progress_courses']++;
            }
        }
        $enroll_stmt->close();
        
        // Get lesson statistics
        $lessons_sql = "SELECT 
                        COUNT(*) as total,
                        SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed
                        FROM lessons 
                        WHERE student_id = ?";
        $lessons_stmt = $this->conn->prepare($lessons_sql);
        $lessons_stmt->bind_param("i", $student_id);
        $lessons_stmt->execute();
        $lessons_result = $lessons_stmt->get_result();
        if ($lessons_row = $lessons_result->fetch_assoc()) {
            $stats['total_lessons'] = (int)$lessons_row['total'];
            $stats['completed_lessons'] = (int)$lessons_row['completed'];
        }
        $lessons_stmt->close();
        
        // Get assignment statistics
        $assignments_sql = "SELECT 
                           COUNT(*) as total,
                           SUM(CASE WHEN status = 'completed' OR status = 'graded' THEN 1 ELSE 0 END) as completed
                           FROM assignments 
                           WHERE student_id = ?";
        $assign_stmt = $this->conn->prepare($assignments_sql);
        $assign_stmt->bind_param("i", $student_id);
        $assign_stmt->execute();
        $assign_result = $assign_stmt->get_result();
        if ($assign_row = $assign_result->fetch_assoc()) {
            $stats['total_assignments'] = (int)$assign_row['total'];
            $stats['completed_assignments'] = (int)$assign_row['completed'];
        }
        $assign_stmt->close();
        
        // Calculate learning streak (consecutive days with activity)
        // Use appropriate date columns for each table
        // Note: Using lesson_date for lessons, last_accessed_at for course progress, created_at for assignments
        $streak_sql = "SELECT COUNT(DISTINCT DATE(activity_date)) as streak_days
                      FROM (
                          SELECT lesson_date as activity_date FROM lessons WHERE student_id = ? AND status = 'completed' AND lesson_date IS NOT NULL
                          UNION ALL
                          SELECT last_accessed_at as activity_date FROM user_course_progress WHERE user_id = ? AND progress_percentage > 0 AND last_accessed_at IS NOT NULL
                          UNION ALL
                          SELECT created_at as activity_date FROM assignments WHERE student_id = ? AND status IN ('completed', 'graded') AND created_at IS NOT NULL
                      ) as activities
                      WHERE activity_date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
                      ORDER BY activity_date DESC";
        $streak_stmt = $this->conn->prepare($streak_sql);
        $streak_stmt->bind_param("iii", $student_id, $student_id, $student_id);
        $streak_stmt->execute();
        $streak_result = $streak_stmt->get_result();
        if ($streak_row = $streak_result->fetch_assoc()) {
            $stats['learning_streak'] = (int)$streak_row['streak_days'];
        }
        $streak_stmt->close();
        
        return $stats;
    }
    
    /**
     * Get course progress details
     */
    public function getCourseProgress($student_id) {
        $courses = [];
        
        $sql = "SELECT c.id, c.title, c.thumbnail_url, c.difficulty_level,
                cc.name as category_name, cc.color as category_color,
                ucp.progress_percentage,
                (SELECT COUNT(*) FROM course_lessons WHERE course_id = c.id) as total_lessons,
                (SELECT COUNT(*) FROM user_lesson_progress WHERE user_id = ? AND course_id = c.id AND completed = 1) as completed_lessons,
                ce.enrolled_at
                FROM course_enrollments ce
                JOIN courses c ON ce.course_id = c.id
                LEFT JOIN course_categories cc ON c.category_id = cc.id
                LEFT JOIN user_course_progress ucp ON ucp.user_id = ? AND ucp.course_id = c.id
                WHERE ce.user_id = ? AND (ce.expires_at IS NULL OR ce.expires_at > NOW())
                ORDER BY ucp.last_accessed_at DESC, ce.enrolled_at DESC";
        
        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param("iii", $student_id, $student_id, $student_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        while ($row = $result->fetch_assoc()) {
            $progress = (float)($row['progress_percentage'] ?? 0);
            $total_lessons = (int)($row['total_lessons'] ?? 0);
            $completed_lessons = (int)($row['completed_lessons'] ?? 0);
            
            $courses[] = [
                'id' => $row['id'],
                'title' => $row['title'],
                'thumbnail_url' => $row['thumbnail_url'],
                'difficulty_level' => $row['difficulty_level'],
                'category_name' => $row['category_name'],
                'category_color' => $row['category_color'],
                'progress_percentage' => $progress,
                'total_lessons' => $total_lessons,
                'completed_lessons' => $completed_lessons,
                'enrolled_at' => $row['enrolled_at'],
                'status' => $progress >= 100 ? 'completed' : ($progress > 0 ? 'in_progress' : 'not_started')
            ];
        }
        
        $stmt->close();
        return $courses;
    }
    
    /**
     * Get progress over time (for charts)
     */
    public function getProgressOverTime($student_id, $days = 30) {
        $data = [];
        
        $sql = "SELECT DATE(lesson_date) as date,
                COUNT(DISTINCT CASE WHEN status = 'completed' THEN id END) as lessons_completed
                FROM lessons
                WHERE student_id = ? AND lesson_date >= DATE_SUB(CURDATE(), INTERVAL ? DAY)
                GROUP BY DATE(lesson_date)
                ORDER BY date ASC";
        
        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param("ii", $student_id, $days);
        $stmt->execute();
        $result = $stmt->get_result();
        
        while ($row = $result->fetch_assoc()) {
            $data[] = [
                'date' => $row['date'],
                'lessons_completed' => (int)$row['lessons_completed']
            ];
        }
        
        $stmt->close();
        return $data;
    }
    
    /**
     * Get upcoming milestones
     */
    public function getUpcomingMilestones($student_id) {
        $milestones = [];
        
        // Course completion milestones
        $courses_sql = "SELECT c.id, c.title, ucp.progress_percentage,
                       (SELECT COUNT(*) FROM course_lessons WHERE course_id = c.id) as total_lessons
                       FROM course_enrollments ce
                       JOIN courses c ON ce.course_id = c.id
                       LEFT JOIN user_course_progress ucp ON ucp.user_id = ? AND ucp.course_id = c.id
                       WHERE ce.user_id = ? 
                       AND (ce.expires_at IS NULL OR ce.expires_at > NOW())
                       AND (ucp.progress_percentage IS NULL OR ucp.progress_percentage < 100)
                       ORDER BY ucp.progress_percentage DESC
                       LIMIT 5";
        
        $stmt = $this->conn->prepare($courses_sql);
        $stmt->bind_param("ii", $student_id, $student_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        while ($row = $result->fetch_assoc()) {
            $progress = (float)($row['progress_percentage'] ?? 0);
            $next_milestone = ceil($progress / 25) * 25; // Next 25% milestone
            
            if ($next_milestone > $progress && $next_milestone <= 100) {
                $milestones[] = [
                    'type' => 'course',
                    'title' => $row['title'],
                    'current' => $progress,
                    'target' => $next_milestone,
                    'description' => "Complete {$next_milestone}% of " . $row['title']
                ];
            }
        }
        
        $stmt->close();
        return $milestones;
    }
}
