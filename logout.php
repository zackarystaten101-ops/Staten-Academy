<?php
// Start output buffering to prevent "headers already sent" errors
ob_start();

// Load environment configuration first
if (!defined('DB_HOST')) {
    require_once __DIR__ . '/env.php';
}

session_start();
session_unset();
session_destroy();

ob_end_clean(); // Clear output buffer before redirect
header("Location: index.php");
exit();
