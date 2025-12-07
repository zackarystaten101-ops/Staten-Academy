<?php
/**
 * Application Entry Point
 * All requests are routed through this file
 */

// Start output buffering
ob_start();

// Load configuration
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';

// Load autoloader
require_once __DIR__ . '/../core/Autoloader.php';

// Load routes
$router = require __DIR__ . '/../config/routes.php';

// Dispatch request
$router->dispatch();

// End output buffering
ob_end_flush();

