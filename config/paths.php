<?php
/**
 * Application Paths Configuration
 * Centralized path definitions for clean architecture
 */

// Base paths
define('BASE_PATH', dirname(__DIR__));
define('PUBLIC_PATH', BASE_PATH . '/public');
define('APP_PATH', BASE_PATH . '/app');
define('CONFIG_PATH', BASE_PATH . '/config');
define('CORE_PATH', BASE_PATH . '/core');

// Asset paths (public URLs)
define('ASSETS_URL', '/assets');
define('CSS_URL', ASSETS_URL . '/css');
define('JS_URL', ASSETS_URL . '/js');
define('IMAGES_URL', ASSETS_URL . '/images');

// View paths
define('VIEWS_PATH', APP_PATH . '/Views');
define('COMPONENTS_PATH', VIEWS_PATH . '/components');
define('LAYOUTS_PATH', VIEWS_PATH . '/layouts');

// Upload paths
define('UPLOADS_PATH', PUBLIC_PATH . '/uploads');
define('UPLOADS_URL', '/uploads');

