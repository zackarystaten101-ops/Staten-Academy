<?php
/**
 * Calendar Service
 * Handles Google Calendar integration
 */

class CalendarService {
    private $client_id;
    private $client_secret;
    private $redirect_uri;
    private $scopes;
    private $conn;
    
    public function __construct($conn) {
        $this->conn = $conn;
        // Load env.php if constants not defined
        if (!defined('GOOGLE_CLIENT_ID')) {
            require_once __DIR__ . '/../../env.php';
        }
        $this->client_id = defined('GOOGLE_CLIENT_ID') ? GOOGLE_CLIENT_ID : '';
        $this->client_secret = defined('GOOGLE_CLIENT_SECRET') ? GOOGLE_CLIENT_SECRET : '';
        $this->redirect_uri = defined('GOOGLE_REDIRECT_URI') ? GOOGLE_REDIRECT_URI : '';
        $this->scopes = defined('GOOGLE_SCOPES') ? GOOGLE_SCOPES : 'https://www.googleapis.com/auth/calendar';
    }
    
    /**
     * Get Google OAuth authentication URL
     */
    public function getAuthUrl($userId) {
        $state = bin2hex(random_bytes(16));
        $_SESSION['google_oauth_state'] = $state;
        $_SESSION['google_oauth_user_id'] = $userId;
        
        $params = [
            'client_id' => $this->client_id,
            'redirect_uri' => $this->redirect_uri,
            'response_type' => 'code',
            'scope' => $this->scopes,
            'state' => $state,
            'access_type' => 'offline',
            'prompt' => 'consent'
        ];
        
        return 'https://accounts.google.com/o/oauth2/v2/auth?' . http_build_query($params);
    }
    
    /**
     * Exchange authorization code for tokens
     */
    public function exchangeCodeForToken($code) {
        $ch = curl_init('https://oauth2.googleapis.com/token');
        
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POSTFIELDS => http_build_query([
                'code' => $code,
                'client_id' => $this->client_id,
                'client_secret' => $this->client_secret,
                'redirect_uri' => $this->redirect_uri,
                'grant_type' => 'authorization_code'
            ])
        ]);
        
        $response = curl_exec($ch);
        $status_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($status_code !== 200) {
            return ['error' => 'Failed to exchange code for token'];
        }
        
        return json_decode($response, true);
    }
    
    /**
     * Refresh access token
     */
    public function refreshAccessToken($refreshToken) {
        $ch = curl_init('https://oauth2.googleapis.com/token');
        
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POSTFIELDS => http_build_query([
                'client_id' => $this->client_id,
                'client_secret' => $this->client_secret,
                'refresh_token' => $refreshToken,
                'grant_type' => 'refresh_token'
            ])
        ]);
        
        $response = curl_exec($ch);
        $status_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($status_code !== 200) {
            return ['error' => 'Failed to refresh token'];
        }
        
        return json_decode($response, true);
    }
    
    /**
     * Get access token for user
     */
    public function getAccessToken($userId) {
        $stmt = $this->conn->prepare("SELECT google_calendar_token, google_calendar_token_expiry, google_calendar_refresh_token FROM users WHERE id = ?");
        $stmt->bind_param("i", $userId);
        $stmt->execute();
        $result = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        
        if (!$result || !$result['google_calendar_token']) {
            return null;
        }
        
        // Check if token is expired
        if ($result['google_calendar_token_expiry'] && strtotime($result['google_calendar_token_expiry']) <= time()) {
            // Refresh token
            if ($result['google_calendar_refresh_token']) {
                $newToken = $this->refreshAccessToken($result['google_calendar_refresh_token']);
                if (isset($newToken['access_token'])) {
                    $this->saveToken($userId, $newToken['access_token'], $newToken['expires_in'] ?? 3600, $result['google_calendar_refresh_token']);
                    return $newToken['access_token'];
                }
            }
            return null;
        }
        
        return $result['google_calendar_token'];
    }
    
    /**
     * Save token to database
     */
    public function saveToken($userId, $accessToken, $expiresIn, $refreshToken = null) {
        $expiry = date('Y-m-d H:i:s', time() + $expiresIn);
        
        if ($refreshToken) {
            $stmt = $this->conn->prepare("UPDATE users SET google_calendar_token = ?, google_calendar_token_expiry = ?, google_calendar_refresh_token = ? WHERE id = ?");
            $stmt->bind_param("sssi", $accessToken, $expiry, $refreshToken, $userId);
        } else {
            $stmt = $this->conn->prepare("UPDATE users SET google_calendar_token = ?, google_calendar_token_expiry = ? WHERE id = ?");
            $stmt->bind_param("ssi", $accessToken, $expiry, $userId);
        }
        
        $stmt->execute();
        $stmt->close();
    }
    
    /**
     * Create calendar event
     */
    public function createEvent($userId, $summary, $startDateTime, $endDateTime, $description = '') {
        $accessToken = $this->getAccessToken($userId);
        if (!$accessToken) {
            return ['error' => 'No access token available'];
        }
        
        $event = [
            'summary' => $summary,
            'description' => $description,
            'start' => [
                'dateTime' => $startDateTime,
                'timeZone' => 'America/New_York'
            ],
            'end' => [
                'dateTime' => $endDateTime,
                'timeZone' => 'America/New_York'
            ]
        ];
        
        $ch = curl_init('https://www.googleapis.com/calendar/v3/calendars/primary/events');
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POSTFIELDS => json_encode($event),
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . $accessToken,
                'Content-Type: application/json'
            ]
        ]);
        
        $response = curl_exec($ch);
        $status_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($status_code !== 200) {
            return ['error' => 'Failed to create calendar event'];
        }
        
        $eventData = json_decode($response, true);
        return ['success' => true, 'event_id' => $eventData['id']];
    }
    
    /**
     * Check if slot is available
     */
    public function isSlotAvailable($teacherId, $date, $startTime, $endTime) {
        // Check if there's already a lesson at this time
        $stmt = $this->conn->prepare("SELECT id FROM lessons WHERE teacher_id = ? AND lesson_date = ? AND start_time = ? AND status = 'scheduled'");
        $stmt->bind_param("iss", $teacherId, $date, $startTime);
        $stmt->execute();
        $result = $stmt->get_result();
        $exists = $result->num_rows > 0;
        $stmt->close();
        
        if ($exists) {
            return ['available' => false, 'reason' => 'Time slot already booked'];
        }
        
        // Check teacher availability
        $dayOfWeek = date('l', strtotime($date));
        $stmt = $this->conn->prepare("SELECT * FROM teacher_availability WHERE teacher_id = ? AND day_of_week = ? AND is_available = 1");
        $stmt->bind_param("is", $teacherId, $dayOfWeek);
        $stmt->execute();
        $result = $stmt->get_result();
        $available = $result->num_rows > 0;
        $stmt->close();
        
        if (!$available) {
            return ['available' => false, 'reason' => 'Teacher not available on this day'];
        }
        
        return ['available' => true];
    }
    
    /**
     * Get available slots with timezone conversion
     */
    public function getAvailableSlotsWithTimezone($teacherId, $date, $userTimezone = 'UTC') {
        require_once __DIR__ . '/TimezoneService.php';
        $tzService = new TimezoneService($this->conn);
        
        $dayOfWeek = date('l', strtotime($date));
        $stmt = $this->conn->prepare("
            SELECT day_of_week, start_time, end_time, is_available 
            FROM teacher_availability 
            WHERE teacher_id = ? AND day_of_week = ? AND is_available = 1
            ORDER BY start_time
        ");
        $stmt->bind_param("is", $teacherId, $dayOfWeek);
        $stmt->execute();
        $result = $stmt->get_result();
        $slots = [];
        while ($row = $result->fetch_assoc()) {
            // Convert times to user's timezone for display
            $utcDateTime = $date . ' ' . $row['start_time'];
            $localTime = $tzService->convertUTCToLocalDateTime($utcDateTime, $userTimezone);
            $row['display_start_time'] = $localTime['time'];
            $row['display_end_time'] = date('H:i', strtotime($row['end_time']));
            $slots[] = $row;
        }
        $stmt->close();
        return $slots;
    }
    
    /**
     * Check time-off conflicts
     */
    public function checkTimeOffConflicts($teacherId, $startDate, $endDate) {
        require_once __DIR__ . '/../Models/TimeOff.php';
        $timeOffModel = new TimeOff($this->conn);
        return $timeOffModel->hasConflict($teacherId, $startDate, $endDate);
    }
    
    /**
     * Validate booking notice period
     */
    public function validateBookingNotice($teacherId, $lessonDateTime) {
        // Get teacher's booking notice requirement
        $stmt = $this->conn->prepare("SELECT booking_notice_hours FROM users WHERE id = ? AND role = 'teacher'");
        $stmt->bind_param("i", $teacherId);
        $stmt->execute();
        $result = $stmt->get_result();
        $teacher = $result->fetch_assoc();
        $stmt->close();
        
        $noticeHours = $teacher && $teacher['booking_notice_hours'] ? (int)$teacher['booking_notice_hours'] : 24;
        
        $lessonTimestamp = strtotime($lessonDateTime);
        $currentTimestamp = time();
        $hoursUntilLesson = ($lessonTimestamp - $currentTimestamp) / 3600;
        
        if ($hoursUntilLesson < $noticeHours) {
            return [
                'valid' => false,
                'reason' => "Booking must be made at least {$noticeHours} hours in advance. This lesson is only " . round($hoursUntilLesson, 1) . " hours away."
            ];
        }
        
        return ['valid' => true];
    }
    
    /**
     * Create recurring lesson series
     */
    public function createRecurringLesson($teacherId, $studentId, $seriesData) {
        require_once __DIR__ . '/../Models/RecurringLesson.php';
        require_once __DIR__ . '/../Models/Lesson.php';
        
        $recurringModel = new RecurringLesson($this->conn);
        $lessonModel = new Lesson($this->conn);
        
        // Create recurring lesson record
        $recurringId = $recurringModel->createRecurring(
            $teacherId,
            $studentId,
            $seriesData['day_of_week'],
            $seriesData['start_time'],
            $seriesData['end_time'],
            $seriesData['start_date'],
            $seriesData['end_date'] ?? null,
            $seriesData['frequency_weeks'] ?? 1
        );
        
        if (!$recurringId) {
            return ['error' => 'Failed to create recurring lesson'];
        }
        
        // Generate individual lesson dates
        $lessonDates = $recurringModel->generateLessonDates($recurringId, $seriesData['number_of_weeks'] ?? 52);
        $createdLessons = [];
        
        foreach ($lessonDates as $lessonDate) {
            $lessonId = $lessonModel->createLesson(
                $teacherId,
                $studentId,
                $lessonDate,
                $seriesData['start_time'],
                $seriesData['end_time'],
                null, // googleEventId
                'recurring', // lessonType
                $recurringId // recurringLessonId
            );
            
            if ($lessonId) {
                // Update lesson with recurring series info
                $lessonModel->update($lessonId, [
                    'series_start_date' => $seriesData['start_date'],
                    'series_end_date' => $seriesData['end_date'] ?? null,
                    'series_frequency_weeks' => $seriesData['frequency_weeks'] ?? 1
                ]);
                $createdLessons[] = $lessonId;
            }
        }
        
        return ['success' => true, 'recurring_id' => $recurringId, 'lessons_created' => count($createdLessons)];
    }
    
    /**
     * Pause recurring lessons during time-off
     */
    public function pauseRecurringLessons($teacherId, $startDate, $endDate) {
        require_once __DIR__ . '/../Models/RecurringLesson.php';
        $recurringModel = new RecurringLesson($this->conn);
        return $recurringModel->pauseForTimeOff($teacherId, $startDate, $endDate);
    }
    
    /**
     * Get lessons with color codes
     */
    public function getLessonsWithColors($userId, $dateFrom = null, $dateTo = null, $role = 'student') {
        $colorMap = [
            'scheduled' => '#0b6cf5', // Blue
            'completed' => '#28a745', // Green
            'cancelled' => '#dc3545',  // Red
            'pending' => '#ffc107'      // Yellow
        ];
        
        // Build query based on role and date range
        if ($dateFrom && $dateTo) {
            if ($role === 'teacher') {
                $stmt = $this->conn->prepare("
                    SELECT l.*, 
                           CASE 
                               WHEN LOWER(u.email) = 'student@statenacademy.com' THEN 'Test Class'
                               ELSE u.name 
                           END as student_name, 
                           u.email as student_email
                    FROM lessons l
                    JOIN users u ON l.student_id = u.id
                    WHERE l.teacher_id = ? AND l.lesson_date BETWEEN ? AND ?
                    ORDER BY l.lesson_date, l.start_time
                ");
                $stmt->bind_param("iss", $userId, $dateFrom, $dateTo);
            } else {
                $stmt = $this->conn->prepare("
                    SELECT l.*, u.name as teacher_name, u.email as teacher_email
                    FROM lessons l
                    JOIN users u ON l.teacher_id = u.id
                    WHERE l.student_id = ? AND l.lesson_date BETWEEN ? AND ?
                    ORDER BY l.lesson_date, l.start_time
                ");
                $stmt->bind_param("iss", $userId, $dateFrom, $dateTo);
            }
        } else {
            if ($role === 'teacher') {
                $stmt = $this->conn->prepare("
                    SELECT l.*, 
                           CASE 
                               WHEN LOWER(u.email) = 'student@statenacademy.com' THEN 'Test Class'
                               ELSE u.name 
                           END as student_name, 
                           u.email as student_email
                    FROM lessons l
                    JOIN users u ON l.student_id = u.id
                    WHERE l.teacher_id = ?
                    ORDER BY l.lesson_date, l.start_time
                ");
                $stmt->bind_param("i", $userId);
            } else {
                $stmt = $this->conn->prepare("
                    SELECT l.*, u.name as teacher_name, u.email as teacher_email
                    FROM lessons l
                    JOIN users u ON l.teacher_id = u.id
                    WHERE l.student_id = ?
                    ORDER BY l.lesson_date, l.start_time
                ");
                $stmt->bind_param("i", $userId);
            }
        }
        
        $stmt->execute();
        $result = $stmt->get_result();
        $lessons = [];
        while ($row = $result->fetch_assoc()) {
            $row['color_code'] = $row['color_code'] ?? $colorMap[$row['status']] ?? '#0b6cf5';
            $lessons[] = $row;
        }
        $stmt->close();
        return $lessons;
    }
}

