<?php
/**
 * Rate Limiter
 * Database-backed rate limiting for API endpoints
 */

class RateLimiter {
    private $conn;
    
    public function __construct($conn) {
        $this->conn = $conn;
        $this->initializeTable();
    }
    
    /**
     * Initialize rate limit table
     */
    private function initializeTable() {
        $sql = "CREATE TABLE IF NOT EXISTS rate_limits (
            id INT AUTO_INCREMENT PRIMARY KEY,
            identifier VARCHAR(255) NOT NULL,
            endpoint VARCHAR(255) NOT NULL,
            request_count INT DEFAULT 1,
            window_start TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_identifier_endpoint (identifier, endpoint),
            INDEX idx_window_start (window_start)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
        
        $this->conn->query($sql);
    }
    
    /**
     * Check rate limit
     */
    public function checkLimit($identifier, $endpoint, $maxRequests = 60, $windowSeconds = 60) {
        $now = time();
        $windowStart = date('Y-m-d H:i:s', $now - $windowSeconds);
        
        // Clean old entries
        $cleanup = $this->conn->prepare("DELETE FROM rate_limits WHERE window_start < ?");
        $cleanup->bind_param("s", $windowStart);
        $cleanup->execute();
        $cleanup->close();
        
        // Get current count
        $check = $this->conn->prepare("
            SELECT request_count, window_start 
            FROM rate_limits 
            WHERE identifier = ? AND endpoint = ? AND window_start >= ?
            ORDER BY window_start DESC 
            LIMIT 1
        ");
        $check->bind_param("sss", $identifier, $endpoint, $windowStart);
        $check->execute();
        $result = $check->get_result();
        $check->close();
        
        if ($row = $result->fetch_assoc()) {
            $count = (int)$row['request_count'];
            
            if ($count >= $maxRequests) {
                return [
                    'allowed' => false,
                    'remaining' => 0,
                    'reset_at' => date('Y-m-d H:i:s', strtotime($row['window_start']) + $windowSeconds)
                ];
            }
            
            // Increment count
            $update = $this->conn->prepare("
                UPDATE rate_limits 
                SET request_count = request_count + 1 
                WHERE identifier = ? AND endpoint = ? AND window_start = ?
            ");
            $update->bind_param("sss", $identifier, $endpoint, $row['window_start']);
            $update->execute();
            $update->close();
            
            return [
                'allowed' => true,
                'remaining' => $maxRequests - ($count + 1),
                'reset_at' => date('Y-m-d H:i:s', strtotime($row['window_start']) + $windowSeconds)
            ];
        } else {
            // Create new entry
            $insert = $this->conn->prepare("
                INSERT INTO rate_limits (identifier, endpoint, request_count, window_start) 
                VALUES (?, ?, 1, NOW())
            ");
            $insert->bind_param("ss", $identifier, $endpoint);
            $insert->execute();
            $insert->close();
            
            return [
                'allowed' => true,
                'remaining' => $maxRequests - 1,
                'reset_at' => date('Y-m-d H:i:s', $now + $windowSeconds)
            ];
        }
    }
}
