<?php
require_once __DIR__ . '/../core/Controller.php';
require_once __DIR__ . '/../Services/AuthService.php';

class AuthController extends Controller {
    private $authService;
    
    public function __construct($conn) {
        parent::__construct($conn);
        $this->authService = new AuthService($conn);
    }
    
    /**
     * Show login page
     */
    public function login() {
        if ($this->authService->isAuthenticated()) {
            $this->redirect('/dashboard');
        }
        
        $error = '';
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $email = filter_input(INPUT_POST, 'email', FILTER_SANITIZE_EMAIL);
            $password = $_POST['password'] ?? '';
            
            $result = $this->authService->login($email, $password);
            if ($result['success']) {
                $this->redirect('/dashboard');
            } else {
                $error = $result['error'];
            }
        }
        
        $this->render('auth/login', ['error' => $error]);
    }
    
    /**
     * Show register page
     */
    public function register() {
        if ($this->authService->isAuthenticated()) {
            $this->redirect('/dashboard');
        }
        
        $error = '';
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $email = filter_input(INPUT_POST, 'email', FILTER_SANITIZE_EMAIL);
            $password = $_POST['password'] ?? '';
            $confirm_password = $_POST['confirm_password'] ?? '';
            $name = htmlspecialchars(trim($_POST['name'] ?? ''), ENT_QUOTES, 'UTF-8');
            
            if ($password !== $confirm_password) {
                $error = 'Passwords do not match.';
            } else {
                $result = $this->authService->register($email, $password, $name);
                if ($result['success']) {
                    $this->redirect('/dashboard');
                } else {
                    $error = $result['error'];
                }
            }
        }
        
        $this->render('auth/register', ['error' => $error]);
    }
    
    /**
     * Logout
     */
    public function logout() {
        $this->authService->logout();
        $this->redirect('/');
    }
}

