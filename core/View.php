<?php
/**
 * View Renderer Class
 * Handles view rendering with layout support
 */

class View {
    private static $basePath = __DIR__ . '/../app/Views/';
    private static $layout = null;
    private static $data = [];
    
    /**
     * Set layout
     */
    public static function layout($layout) {
        self::$layout = $layout;
    }
    
    /**
     * Render a view
     */
    public static function render($view, $data = []) {
        self::$data = array_merge(self::$data, $data);
        extract(self::$data);
        
        $viewFile = self::$basePath . $view . '.php';
        
        if (!file_exists($viewFile)) {
            die("View not found: $view");
        }
        
        ob_start();
        include $viewFile;
        $content = ob_get_clean();
        
        if (self::$layout) {
            $layoutFile = self::$basePath . 'layouts/' . self::$layout . '.php';
            if (file_exists($layoutFile)) {
                include $layoutFile;
            } else {
                echo $content;
            }
        } else {
            echo $content;
        }
    }
    
    /**
     * Get view content without layout
     */
    public static function partial($view, $data = []) {
        extract($data);
        $viewFile = self::$basePath . $view . '.php';
        
        if (!file_exists($viewFile)) {
            return '';
        }
        
        ob_start();
        include $viewFile;
        return ob_get_clean();
    }
    
    /**
     * Escape HTML
     */
    public static function e($string) {
        return htmlspecialchars($string ?? '', ENT_QUOTES, 'UTF-8');
    }
}

