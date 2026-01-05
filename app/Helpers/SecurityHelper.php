<?php
/**
 * Security Helper Functions
 * Provides CSRF protection and other security utilities
 */

/**
 * Generate CSRF token and store in session
 * @return string CSRF token
 */
function generateCSRFToken() {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    
    return $_SESSION['csrf_token'];
}

/**
 * Get CSRF token (generate if doesn't exist)
 * @return string CSRF token
 */
function getCSRFToken() {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    
    return generateCSRFToken();
}

/**
 * Validate CSRF token
 * @param string $token Token to validate
 * @return bool True if valid, false otherwise
 */
function validateCSRFToken($token) {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    
    if (!isset($_SESSION['csrf_token'])) {
        return false;
    }
    
    return hash_equals($_SESSION['csrf_token'], $token);
}

/**
 * Generate CSRF token input field HTML
 * @return string HTML input field
 */
function csrfTokenField() {
    $token = getCSRFToken();
    return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars($token, ENT_QUOTES, 'UTF-8') . '">';
}

/**
 * Validate CSRF token from POST request
 * Dies with error if invalid (for production safety)
 * @return bool True if valid
 */
function requireCSRFToken() {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        return true; // Only validate POST requests
    }
    
    $token = $_POST['csrf_token'] ?? '';
    
    if (!validateCSRFToken($token)) {
        if (defined('APP_DEBUG') && APP_DEBUG === true) {
            die("CSRF token validation failed. Please refresh the page and try again.");
        } else {
            http_response_code(403);
            die("Invalid request. Please refresh the page and try again.");
        }
    }
    
    return true;
}

/**
 * Sanitize file name for uploads
 * @param string $filename Original filename
 * @return string Sanitized filename
 */
function sanitizeFilename($filename) {
    // Remove path information
    $filename = basename($filename);
    
    // Remove any non-alphanumeric characters except dots, dashes, and underscores
    $filename = preg_replace('/[^a-zA-Z0-9._-]/', '', $filename);
    
    // Limit length
    if (strlen($filename) > 255) {
        $ext = pathinfo($filename, PATHINFO_EXTENSION);
        $name = substr(pathinfo($filename, PATHINFO_FILENAME), 0, 255 - strlen($ext) - 1);
        $filename = $name . '.' . $ext;
    }
    
    return $filename;
}

/**
 * Validate file upload
 * @param array $file $_FILES array element
 * @param array $allowedTypes Allowed MIME types
 * @param array $allowedExtensions Allowed file extensions
 * @param int $maxSize Maximum file size in bytes
 * @return array ['valid' => bool, 'error' => string]
 */
function validateFileUpload($file, $allowedTypes = [], $allowedExtensions = [], $maxSize = 10485760) {
    // Check for upload errors
    if (!isset($file['error']) || $file['error'] !== UPLOAD_ERR_OK) {
        return ['valid' => false, 'error' => 'File upload error occurred.'];
    }
    
    // Check file size
    if ($file['size'] > $maxSize) {
        return ['valid' => false, 'error' => 'File size exceeds maximum allowed size.'];
    }
    
    // Check extension
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!empty($allowedExtensions) && !in_array($ext, $allowedExtensions)) {
        return ['valid' => false, 'error' => 'File type not allowed.'];
    }
    
    // Verify MIME type
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mimeType = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);
    
    if (!empty($allowedTypes) && !in_array($mimeType, $allowedTypes)) {
        return ['valid' => false, 'error' => 'File type not allowed.'];
    }
    
    // Additional security: Check file content
    if (in_array($ext, ['php', 'phtml', 'php3', 'php4', 'php5', 'phps', 'jsp', 'asp', 'sh', 'py'])) {
        return ['valid' => false, 'error' => 'Executable files are not allowed.'];
    }
    
    return ['valid' => true, 'error' => ''];
}














