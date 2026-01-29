<?php
/**
 * Security Helper
 * Provides security functions including CSRF protection, rate limiting, and input validation
 */

class SecurityHelper {
    private static $rateLimitStore = [];
    
    /**
     * Generate CSRF token
     */
    public static function generateCSRFToken() {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        if (!isset($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        
        return $_SESSION['csrf_token'];
    }
    
    /**
     * Verify CSRF token
     */
    public static function verifyCSRFToken($token) {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
    }
    
    /**
     * Rate limiting check
     */
    public static function checkRateLimit($identifier, $maxRequests = 60, $windowSeconds = 60) {
        $key = 'rate_limit_' . md5($identifier);
        $now = time();
        
        // Clean old entries (simple cleanup)
        if (rand(1, 100) === 1) {
            self::cleanRateLimitStore($windowSeconds);
        }
        
        if (!isset(self::$rateLimitStore[$key])) {
            self::$rateLimitStore[$key] = [
                'count' => 1,
                'reset_time' => $now + $windowSeconds
            ];
            return true;
        }
        
        $limit = self::$rateLimitStore[$key];
        
        // Reset if window expired
        if ($now > $limit['reset_time']) {
            self::$rateLimitStore[$key] = [
                'count' => 1,
                'reset_time' => $now + $windowSeconds
            ];
            return true;
        }
        
        // Check if limit exceeded
        if ($limit['count'] >= $maxRequests) {
            return false;
        }
        
        // Increment count
        self::$rateLimitStore[$key]['count']++;
        return true;
    }
    
    /**
     * Clean old rate limit entries
     */
    private static function cleanRateLimitStore($windowSeconds) {
        $now = time();
        foreach (self::$rateLimitStore as $key => $limit) {
            if ($now > $limit['reset_time']) {
                unset(self::$rateLimitStore[$key]);
            }
        }
    }
    
    /**
     * Sanitize input
     */
    public static function sanitizeInput($input, $type = 'string') {
        if (is_array($input)) {
            return array_map(function($item) use ($type) {
                return self::sanitizeInput($item, $type);
            }, $input);
        }
        
        switch ($type) {
            case 'email':
                return filter_var(trim($input), FILTER_SANITIZE_EMAIL);
            case 'int':
                return filter_var($input, FILTER_SANITIZE_NUMBER_INT);
            case 'float':
                return filter_var($input, FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION);
            case 'url':
                return filter_var(trim($input), FILTER_SANITIZE_URL);
            default:
                return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
        }
    }
    
    /**
     * Validate email
     */
    public static function validateEmail($email) {
        return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
    }
    
    /**
     * Validate password strength
     */
    public static function validatePassword($password) {
        return strlen($password) >= 6; // Minimum 6 characters
    }
    
    /**
     * Generate secure random string
     */
    public static function generateRandomString($length = 32) {
        return bin2hex(random_bytes($length / 2));
    }
    
    /**
     * Check if request is from same origin
     */
    public static function isSameOrigin() {
        $origin = $_SERVER['HTTP_ORIGIN'] ?? $_SERVER['HTTP_REFERER'] ?? '';
        $host = $_SERVER['HTTP_HOST'] ?? '';
        
        if (empty($origin) || empty($host)) {
            return false;
        }
        
        $originHost = parse_url($origin, PHP_URL_HOST);
        return $originHost === $host;
    }
    
    /**
     * Set security headers
     */
    public static function setSecurityHeaders() {
        if (headers_sent()) {
            return;
        }
        
        header('X-Content-Type-Options: nosniff');
        header('X-Frame-Options: SAMEORIGIN');
        header('X-XSS-Protection: 1; mode=block');
        header('Referrer-Policy: strict-origin-when-cross-origin');
        
        // Content Security Policy (adjust as needed)
        // header("Content-Security-Policy: default-src 'self'; script-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net https://cdnjs.cloudflare.com; style-src 'self' 'unsafe-inline' https://cdnjs.cloudflare.com; img-src 'self' data: https:;");
    }
}
