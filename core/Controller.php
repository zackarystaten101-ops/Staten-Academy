<?php
/**
 * Base Controller Class
 * Provides common functionality for all controllers
 */

class Controller {
    protected $conn;
    protected $viewPath;
    
    public function __construct($conn) {
        $this->conn = $conn;
        $this->viewPath = __DIR__ . '/../app/Views/';
    }
    
    /**
     * Render a view
     */
    protected function view($view, $data = []) {
        extract($data);
        $viewFile = $this->viewPath . $view . '.php';
        
        if (!file_exists($viewFile)) {
            // Try to find in old location for backward compatibility
            $oldViewFile = __DIR__ . '/../../' . str_replace('.php', '', $view) . '.php';
            if (file_exists($oldViewFile)) {
                ob_start();
                include $oldViewFile;
                return ob_get_clean();
            }
            die("View not found: $view");
        }
        
        ob_start();
        include $viewFile;
        return ob_get_clean();
    }
    
    /**
     * Render view and output
     */
    protected function render($view, $data = []) {
        $content = $this->view($view, $data);
        
        // Check if view wants to use a layout
        $layout = $data['layout'] ?? 'main';
        $layoutFile = $this->viewPath . 'layouts/' . $layout . '.php';
        
        if (file_exists($layoutFile)) {
            extract($data);
            include $layoutFile;
        } else {
            echo $content;
        }
    }
    
    /**
     * Return JSON response
     */
    protected function json($data, $statusCode = 200) {
        http_response_code($statusCode);
        header('Content-Type: application/json');
        echo json_encode($data);
        exit;
    }
    
    /**
     * Redirect to URL
     */
    protected function redirect($url) {
        header("Location: $url");
        exit;
    }
    
    /**
     * Check if user is authenticated
     */
    protected function requireAuth() {
        if (!isset($_SESSION['user_id'])) {
            $this->redirect('/auth/login');
        }
    }
    
    /**
     * Check if user has specific role
     */
    protected function requireRole($role) {
        $this->requireAuth();
        if ($_SESSION['user_role'] !== $role) {
            $this->redirect('/');
        }
    }
    
    /**
     * Get current user
     */
    protected function getUser() {
        if (!isset($_SESSION['user_id'])) {
            return null;
        }
        $stmt = $this->conn->prepare("SELECT * FROM users WHERE id = ?");
        $stmt->bind_param("i", $_SESSION['user_id']);
        $stmt->execute();
        $result = $stmt->get_result();
        $user = $result->fetch_assoc();
        $stmt->close();
        return $user;
    }
}

