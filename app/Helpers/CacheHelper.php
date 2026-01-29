<?php
/**
 * Cache Helper
 * Simple file-based caching for query results
 */

class CacheHelper {
    private static $cacheDir = __DIR__ . '/../../cache/';
    private static $defaultTTL = 3600; // 1 hour
    
    /**
     * Initialize cache directory
     */
    private static function initCacheDir() {
        if (!is_dir(self::$cacheDir)) {
            @mkdir(self::$cacheDir, 0755, true);
        }
    }
    
    /**
     * Get cached value
     */
    public static function get($key) {
        self::initCacheDir();
        $file = self::$cacheDir . md5($key) . '.cache';
        
        if (!file_exists($file)) {
            return null;
        }
        
        $data = unserialize(file_get_contents($file));
        
        // Check if expired
        if ($data['expires'] < time()) {
            @unlink($file);
            return null;
        }
        
        return $data['value'];
    }
    
    /**
     * Set cached value
     */
    public static function set($key, $value, $ttl = null) {
        self::initCacheDir();
        $file = self::$cacheDir . md5($key) . '.cache';
        
        $ttl = $ttl ?? self::$defaultTTL;
        $data = [
            'value' => $value,
            'expires' => time() + $ttl
        ];
        
        file_put_contents($file, serialize($data));
    }
    
    /**
     * Delete cached value
     */
    public static function delete($key) {
        self::initCacheDir();
        $file = self::$cacheDir . md5($key) . '.cache';
        @unlink($file);
    }
    
    /**
     * Clear all cache
     */
    public static function clear() {
        self::initCacheDir();
        $files = glob(self::$cacheDir . '*.cache');
        foreach ($files as $file) {
            @unlink($file);
        }
    }
}
