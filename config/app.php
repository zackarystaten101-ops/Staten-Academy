<?php
/**
 * Application Configuration
 * Central configuration file
 */

// Load environment configuration
if (!defined('STRIPE_SECRET_KEY')) {
    require_once __DIR__ . '/../env.php';
}

// Application paths
define('APP_ROOT', dirname(__DIR__));
define('APP_PATH', APP_ROOT . '/app');
define('PUBLIC_PATH', APP_ROOT . '/public');
define('VIEW_PATH', APP_PATH . '/Views');
define('UPLOAD_PATH', PUBLIC_PATH . '/uploads');

// Application settings
define('APP_NAME', 'Staten Academy');
define('APP_URL', isset($_SERVER['HTTP_HOST']) ? 
    (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'] . dirname($_SERVER['PHP_SELF']) : 
    'http://localhost');

// Session configuration
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Error reporting
if (defined('APP_DEBUG') && APP_DEBUG === true) {
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
} else {
    error_reporting(0);
    ini_set('display_errors', 0);
}

// Timezone
date_default_timezone_set('America/New_York');

