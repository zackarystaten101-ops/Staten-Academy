<?php
/**
 * Autoloader
 * Automatically loads classes based on namespace and directory structure
 */

spl_autoload_register(function ($class) {
    // Remove namespace prefix if present
    $class = str_replace('App\\', '', $class);
    
    // Convert namespace separators to directory separators
    $class = str_replace('\\', '/', $class);
    
    // Try different possible locations
    $paths = [
        __DIR__ . '/../app/' . $class . '.php',
        __DIR__ . '/' . $class . '.php',
        __DIR__ . '/../' . $class . '.php',
    ];
    
    foreach ($paths as $path) {
        if (file_exists($path)) {
            require_once $path;
            return;
        }
    }
});

