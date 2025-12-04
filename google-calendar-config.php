<?php
/**
 * Google Calendar API Configuration
 * 
 * To set up Google Calendar integration:
 * 1. Go to https://console.cloud.google.com/
 * 2. Create a new project
 * 3. Enable Google Calendar API
 * 4. Create OAuth 2.0 credentials (Web Application)
 * 5. Add authorized redirect URIs:
 *    - http://localhost/Web%20page/Staten-Academy/google-calendar-callback.php
 *    - https://yourdomain.com/Web%20page/Staten-Academy/google-calendar-callback.php
 *    (Adjust the path based on your actual server setup)
 * 6. Download the OAuth 2.0 Client ID JSON file
 * 7. Update credentials in env.php
 */

// Load environment configuration (contains GOOGLE_CLIENT_ID, GOOGLE_CLIENT_SECRET, etc.)
if (!defined('GOOGLE_CLIENT_ID')) {
    require_once __DIR__ . '/env.php';
}

class GoogleCalendarAPI {
    private $client_id;
    private $client_secret;
    private $redirect_uri;
    private $scopes;
    private $access_token;
    private $conn;

    public function __construct($conn) {
        $this->client_id = GOOGLE_CLIENT_ID;
        $this->client_secret = GOOGLE_CLIENT_SECRET;
        $this->redirect_uri = GOOGLE_REDIRECT_URI;
        $this->scopes = GOOGLE_SCOPES;
        $this->conn = $conn;
    }

    /**
     * Generate Google OAuth authentication URL
     */
    public function getAuthUrl($user_id) {
        $state = bin2hex(random_bytes(16));
        $_SESSION['google_oauth_state'] = $state;
        $_SESSION['google_oauth_user_id'] = $user_id;

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
     * Refresh access token using refresh token
     */
    public function refreshAccessToken($refresh_token) {
        $ch = curl_init('https://oauth2.googleapis.com/token');
        
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POSTFIELDS => http_build_query([
                'client_id' => $this->client_id,
                'client_secret' => $this->client_secret,
                'refresh_token' => $refresh_token,
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
     * Create an event on Google Calendar
     */
    public function createEvent($access_token, $lesson_data) {
        $event = [
            'summary' => $lesson_data['title'],
            'description' => $lesson_data['description'] ?? '',
            'start' => [
                'dateTime' => $lesson_data['start_datetime'],
                'timeZone' => 'UTC'
            ],
            'end' => [
                'dateTime' => $lesson_data['end_datetime'],
                'timeZone' => 'UTC'
            ],
            'attendees' => $lesson_data['attendees'] ?? []
        ];

        $ch = curl_init('https://www.googleapis.com/calendar/v3/calendars/primary/events');
        
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . $access_token,
                'Content-Type: application/json'
            ],
            CURLOPT_POSTFIELDS => json_encode($event)
        ]);

        $response = curl_exec($ch);
        $status_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($status_code >= 400) {
            return ['error' => 'Failed to create calendar event'];
        }

        return json_decode($response, true);
    }

    /**
     * Update an event on Google Calendar
     */
    public function updateEvent($access_token, $event_id, $lesson_data) {
        $event = [
            'summary' => $lesson_data['title'],
            'description' => $lesson_data['description'] ?? '',
            'start' => [
                'dateTime' => $lesson_data['start_datetime'],
                'timeZone' => 'UTC'
            ],
            'end' => [
                'dateTime' => $lesson_data['end_datetime'],
                'timeZone' => 'UTC'
            ]
        ];

        $ch = curl_init('https://www.googleapis.com/calendar/v3/calendars/primary/events/' . $event_id);
        
        curl_setopt_array($ch, [
            CURLOPT_CUSTOMREQUEST => 'PUT',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . $access_token,
                'Content-Type: application/json'
            ],
            CURLOPT_POSTFIELDS => json_encode($event)
        ]);

        $response = curl_exec($ch);
        $status_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($status_code >= 400) {
            return ['error' => 'Failed to update calendar event'];
        }

        return json_decode($response, true);
    }

    /**
     * Delete an event from Google Calendar
     */
    public function deleteEvent($access_token, $event_id) {
        $ch = curl_init('https://www.googleapis.com/calendar/v3/calendars/primary/events/' . $event_id);
        
        curl_setopt_array($ch, [
            CURLOPT_CUSTOMREQUEST => 'DELETE',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . $access_token
            ]
        ]);

        $response = curl_exec($ch);
        $status_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        return $status_code === 204;
    }

    /**
     * Get available time slots for a teacher
     * Returns booked and available slots for a given date range
     */
    public function getTeacherAvailability($teacher_id, $date_from, $date_to) {
        $stmt = $this->conn->prepare("
            SELECT day_of_week, start_time, end_time, is_available 
            FROM teacher_availability 
            WHERE teacher_id = ? AND is_available = 1
            ORDER BY FIELD(day_of_week, 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'), start_time
        ");
        $stmt->bind_param("i", $teacher_id);
        $stmt->execute();
        return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }

    /**
     * Get booked lessons for a teacher
     */
    public function getTeacherLessons($teacher_id, $date_from = null, $date_to = null) {
        if ($date_from && $date_to) {
            $stmt = $this->conn->prepare("
                SELECT l.*, u.name as student_name, u.email as student_email 
                FROM lessons l 
                JOIN users u ON l.student_id = u.id 
                WHERE l.teacher_id = ? AND l.lesson_date BETWEEN ? AND ? AND l.status = 'scheduled'
                ORDER BY l.lesson_date, l.start_time
            ");
            $stmt->bind_param("iss", $teacher_id, $date_from, $date_to);
        } else {
            $stmt = $this->conn->prepare("
                SELECT l.*, u.name as student_name, u.email as student_email 
                FROM lessons l 
                JOIN users u ON l.student_id = u.id 
                WHERE l.teacher_id = ? AND l.status = 'scheduled'
                ORDER BY l.lesson_date, l.start_time
            ");
            $stmt->bind_param("i", $teacher_id);
        }
        $stmt->execute();
        return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }

    /**
     * Check if a time slot is available for booking
     */
    public function isSlotAvailable($teacher_id, $lesson_date, $start_time, $end_time) {
        // Check if teacher is available on this day
        $day_of_week = date('l', strtotime($lesson_date));
        
        $stmt = $this->conn->prepare("
            SELECT id FROM teacher_availability 
            WHERE teacher_id = ? AND day_of_week = ? AND is_available = 1
            AND start_time <= ? AND end_time >= ?
        ");
        $stmt->bind_param("isss", $teacher_id, $day_of_week, $start_time, $end_time);
        $stmt->execute();
        $availability_check = $stmt->get_result();

        if ($availability_check->num_rows === 0) {
            return ['available' => false, 'reason' => 'Teacher not available at this time'];
        }

        // Check if slot is already booked
        $stmt = $this->conn->prepare("
            SELECT id FROM lessons 
            WHERE teacher_id = ? AND lesson_date = ? 
            AND status = 'scheduled'
            AND (
                (start_time <= ? AND end_time > ?) OR
                (start_time < ? AND end_time >= ?) OR
                (start_time >= ? AND end_time <= ?)
            )
        ");
        $stmt->bind_param("isssssss", $teacher_id, $lesson_date, $start_time, $start_time, $end_time, $end_time, $start_time, $end_time);
        $stmt->execute();
        $booking_check = $stmt->get_result();

        if ($booking_check->num_rows > 0) {
            return ['available' => false, 'reason' => 'Time slot already booked'];
        }

        return ['available' => true];
    }
}

?>
