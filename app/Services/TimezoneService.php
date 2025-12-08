<?php
/**
 * Timezone Service
 * Handles timezone detection, conversion, and management
 */

class TimezoneService {
    private $conn;
    
    public function __construct($conn) {
        $this->conn = $conn;
    }
    
    /**
     * Get user's timezone from database or return default
     */
    public function getUserTimezone($userId) {
        $stmt = $this->conn->prepare("SELECT timezone FROM users WHERE id = ?");
        $stmt->bind_param("i", $userId);
        $stmt->execute();
        $result = $stmt->get_result();
        $user = $result->fetch_assoc();
        $stmt->close();
        
        return $user && $user['timezone'] ? $user['timezone'] : 'UTC';
    }
    
    /**
     * Update user's timezone
     */
    public function updateUserTimezone($userId, $timezone, $autoDetected = false) {
        $stmt = $this->conn->prepare("UPDATE users SET timezone = ?, timezone_auto_detected = ? WHERE id = ?");
        $stmt->bind_param("sii", $timezone, $autoDetected, $userId);
        $result = $stmt->execute();
        $stmt->close();
        return $result;
    }
    
    /**
     * Convert UTC datetime to user's timezone
     */
    public function convertToUserTimezone($utcDateTime, $userTimezone) {
        try {
            $utc = new DateTime($utcDateTime, new DateTimeZone('UTC'));
            $utc->setTimezone(new DateTimeZone($userTimezone));
            return $utc->format('Y-m-d H:i:s');
        } catch (Exception $e) {
            // Fallback to original if timezone is invalid
            return $utcDateTime;
        }
    }
    
    /**
     * Convert user's local datetime to UTC
     */
    public function convertToUTC($localDateTime, $userTimezone) {
        try {
            $local = new DateTime($localDateTime, new DateTimeZone($userTimezone));
            $local->setTimezone(new DateTimeZone('UTC'));
            return $local->format('Y-m-d H:i:s');
        } catch (Exception $e) {
            // Fallback to original if timezone is invalid
            return $localDateTime;
        }
    }
    
    /**
     * Format time for display in user's timezone
     */
    public function formatTimeForDisplay($utcDateTime, $userTimezone, $format = 'Y-m-d H:i') {
        try {
            $utc = new DateTime($utcDateTime, new DateTimeZone('UTC'));
            $utc->setTimezone(new DateTimeZone($userTimezone));
            return $utc->format($format);
        } catch (Exception $e) {
            return $utcDateTime;
        }
    }
    
    /**
     * Get timezone offset in hours from UTC
     */
    public function getTimezoneOffset($timezone) {
        try {
            $tz = new DateTimeZone($timezone);
            $utc = new DateTimeZone('UTC');
            $datetime = new DateTime('now', $tz);
            $offset = $tz->getOffset($datetime);
            return $offset / 3600; // Convert seconds to hours
        } catch (Exception $e) {
            return 0;
        }
    }
    
    /**
     * Validate timezone string
     */
    public function isValidTimezone($timezone) {
        try {
            new DateTimeZone($timezone);
            return true;
        } catch (Exception $e) {
            return false;
        }
    }
    
    /**
     * Get list of common timezones
     */
    public function getCommonTimezones() {
        return [
            'UTC' => 'UTC (Coordinated Universal Time)',
            'America/New_York' => 'Eastern Time (US & Canada)',
            'America/Chicago' => 'Central Time (US & Canada)',
            'America/Denver' => 'Mountain Time (US & Canada)',
            'America/Los_Angeles' => 'Pacific Time (US & Canada)',
            'Europe/London' => 'London',
            'Europe/Paris' => 'Paris',
            'Europe/Berlin' => 'Berlin',
            'Asia/Tokyo' => 'Tokyo',
            'Asia/Shanghai' => 'Shanghai',
            'Asia/Dubai' => 'Dubai',
            'Australia/Sydney' => 'Sydney',
            'America/Sao_Paulo' => 'São Paulo',
            'America/Mexico_City' => 'Mexico City',
            'Asia/Kolkata' => 'Mumbai, Kolkata',
        ];
    }
    
    /**
     * Convert date and time separately to UTC
     */
    public function convertDateTimeToUTC($date, $time, $userTimezone) {
        $localDateTime = $date . ' ' . $time . ':00';
        return $this->convertToUTC($localDateTime, $userTimezone);
    }
    
    /**
     * Convert UTC datetime to local date and time separately
     */
    public function convertUTCToLocalDateTime($utcDateTime, $userTimezone) {
        try {
            $utc = new DateTime($utcDateTime, new DateTimeZone('UTC'));
            $utc->setTimezone(new DateTimeZone($userTimezone));
            return [
                'date' => $utc->format('Y-m-d'),
                'time' => $utc->format('H:i'),
                'datetime' => $utc->format('Y-m-d H:i:s')
            ];
        } catch (Exception $e) {
            return [
                'date' => substr($utcDateTime, 0, 10),
                'time' => substr($utcDateTime, 11, 5),
                'datetime' => $utcDateTime
            ];
        }
    }
}

