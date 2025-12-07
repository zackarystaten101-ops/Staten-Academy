<?php
/**
 * Error Handler for Staten Academy
 * This file helps diagnose white screen issues
 * Include this at the top of files to see errors
 */

// Enable all error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
ini_set('log_errors', 1);

// Custom error handler
function customErrorHandler($errno, $errstr, $errfile, $errline) {
    if (!(error_reporting() & $errno)) {
        return false;
    }
    
    $error_message = "Error [$errno]: $errstr in $errfile on line $errline";
    
    // Log to error log
    error_log($error_message);
    
    // Display if in debug mode
    if (defined('APP_DEBUG') && APP_DEBUG === true) {
        echo "<div style='background: #ffebee; border: 2px solid #f44336; padding: 15px; margin: 10px; border-radius: 5px;'>";
        echo "<strong>PHP Error:</strong><br>";
        echo htmlspecialchars($error_message);
        echo "</div>";
    }
    
    return true;
}

// Set custom error handler
set_error_handler("customErrorHandler");

// Fatal error handler
register_shutdown_function(function() {
    $error = error_get_last();
    if ($error !== NULL && in_array($error['type'], [E_ERROR, E_CORE_ERROR, E_COMPILE_ERROR, E_PARSE])) {
        if (defined('APP_DEBUG') && APP_DEBUG === true) {
            echo "<div style='background: #ffebee; border: 2px solid #f44336; padding: 15px; margin: 10px; border-radius: 5px;'>";
            echo "<strong>Fatal Error:</strong><br>";
            echo htmlspecialchars($error['message']) . "<br>";
            echo "File: " . htmlspecialchars($error['file']) . "<br>";
            echo "Line: " . htmlspecialchars($error['line']);
            echo "</div>";
        } else {
            echo "<div style='background: #fff3cd; border: 2px solid #ffc107; padding: 15px; margin: 10px; border-radius: 5px;'>";
            echo "An error occurred. Please contact the administrator.";
            echo "</div>";
        }
    }
});
?>





