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
        $this->client_id = GOOGLE_CLIENT_ID;
        $this->client_secret = GOOGLE_CLIENT_SECRET;
        $this->redirect_uri = GOOGLE_REDIRECT_URI;
        $this->scopes = GOOGLE_SCOPES;
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
}

