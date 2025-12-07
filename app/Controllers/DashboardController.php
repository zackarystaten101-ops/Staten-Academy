<?php
require_once __DIR__ . '/../core/Controller.php';

class DashboardController extends Controller {
    public function __construct($conn) {
        parent::__construct($conn);
    }
    
    /**
     * Redirect to appropriate dashboard
     */
    public function index() {
        $this->requireAuth();
        
        $role = $_SESSION['user_role'];
        switch ($role) {
            case 'teacher':
                $this->redirect('/dashboard/teacher');
                break;
            case 'student':
                $this->redirect('/dashboard/student');
                break;
            case 'admin':
                $this->redirect('/dashboard/admin');
                break;
            default:
                $this->redirect('/');
        }
    }
}

