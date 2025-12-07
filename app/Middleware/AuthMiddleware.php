<?php
/**
 * Authentication Middleware
 * Checks if user is logged in
 */

class AuthMiddleware {
    public function handle() {
        if (!isset($_SESSION['user_id'])) {
            header("Location: /auth/login");
            exit;
        }
        return true;
    }
}

