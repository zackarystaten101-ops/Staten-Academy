<?php
/**
 * Role Middleware
 * Checks if user has required role
 */

class RoleMiddleware {
    private $requiredRole;
    
    public function __construct($role) {
        $this->requiredRole = $role;
    }
    
    public function handle() {
        if (!isset($_SESSION['user_id'])) {
            header("Location: /auth/login");
            exit;
        }
        
        if ($_SESSION['user_role'] !== $this->requiredRole) {
            header("Location: /");
            exit;
        }
        
        return true;
    }
}

