<?php
require_once __DIR__ . '/../Models/User.php';

class AuthService {
    private $conn;
    private $userModel;
    
    public function __construct($conn) {
        $this->conn = $conn;
        $this->userModel = new User($conn);
    }
    
    /**
     * Login user
     */
    public function login($email, $password) {
        $user = $this->userModel->findByEmail($email);
        
        if (!$user) {
            return ['success' => false, 'error' => 'Invalid email or password'];
        }
        
        if (!$this->userModel->verifyPassword($password, $user['password'])) {
            return ['success' => false, 'error' => 'Invalid email or password'];
        }
        
        // Set session
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['user_role'] = $user['role'];
        $_SESSION['user_name'] = $user['name'];
        $_SESSION['user_email'] = $user['email'];
        // Use relative path for placeholder
        $placeholder = 'public/assets/images/placeholder-teacher.svg';
        $_SESSION['user_profile_pic'] = $user['profile_pic'] ?? $placeholder;
        
        return ['success' => true, 'user' => $user];
    }
    
    /**
     * Register user
     */
    public function register($email, $password, $name, $role = 'visitor') {
        // Check if email exists
        $existing = $this->userModel->findByEmail($email);
        if ($existing) {
            return ['success' => false, 'error' => 'Email already registered'];
        }
        
        // Create user
        $data = [
            'email' => $email,
            'password' => $password, // Will be hashed in createWithPassword
            'name' => $name,
            'role' => $role
        ];
        
        $userId = $this->userModel->createWithPassword($data);
        
        if ($userId) {
            // Auto-login
            $user = $this->userModel->find($userId);
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['user_role'] = $user['role'];
            $_SESSION['user_name'] = $user['name'];
            $_SESSION['user_email'] = $user['email'];
            // Use relative path for placeholder
        $placeholder = 'public/assets/images/placeholder-teacher.svg';
        $_SESSION['user_profile_pic'] = $user['profile_pic'] ?? $placeholder;
            
            return ['success' => true, 'user' => $user];
        }
        
        return ['success' => false, 'error' => 'Registration failed'];
    }
    
    /**
     * Logout user
     */
    public function logout() {
        session_destroy();
        return ['success' => true];
    }
    
    /**
     * Check if user is authenticated
     */
    public function isAuthenticated() {
        return isset($_SESSION['user_id']);
    }
    
    /**
     * Get current user
     */
    public function getCurrentUser() {
        if (!$this->isAuthenticated()) {
            return null;
        }
        return $this->userModel->find($_SESSION['user_id']);
    }
}

