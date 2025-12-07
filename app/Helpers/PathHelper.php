<?php
/**
 * Path Helper
 * Centralized path and URL generation for clean architecture
 */

class PathHelper {
    /**
     * Get asset URL
     */
    public static function asset($path) {
        return '/assets/' . ltrim($path, '/');
    }
    
    /**
     * Get CSS URL
     */
    public static function css($file) {
        return self::asset('css/' . $file);
    }
    
    /**
     * Get JS URL
     */
    public static function js($file) {
        return self::asset('js/' . $file);
    }
    
    /**
     * Get image URL
     */
    public static function image($file) {
        return self::asset('images/' . $file);
    }
    
    /**
     * Get upload URL
     */
    public static function upload($file) {
        return '/uploads/' . ltrim($file, '/');
    }
    
    /**
     * Get route URL (for backward compatibility during migration)
     */
    public static function route($path) {
        // During migration, return direct path
        // Later can be updated to use router
        return '/' . ltrim($path, '/');
    }
    
    /**
     * Get component path
     */
    public static function component($name) {
        return __DIR__ . '/../Views/components/' . $name . '.php';
    }
    
    /**
     * Get base URL dynamically based on current server
     * Works for both localhost and production (cPanel)
     */
    public static function baseUrl() {
        $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || $_SERVER['SERVER_PORT'] == 443 ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        $scriptName = $_SERVER['SCRIPT_NAME'] ?? '';
        $basePath = dirname($scriptName);
        
        // Normalize base path
        $basePath = str_replace('\\', '/', $basePath);
        if ($basePath === '.' || $basePath === '/') {
            $basePath = '';
        } else {
            $basePath = rtrim($basePath, '/');
        }
        
        return $protocol . '://' . $host . $basePath;
    }
    
    /**
     * Get full URL for a given path
     */
    public static function url($path) {
        $baseUrl = self::baseUrl();
        $path = ltrim($path, '/');
        return $baseUrl . '/' . $path;
    }
}

