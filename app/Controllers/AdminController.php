<?php
require_once __DIR__ . '/../core/Controller.php';
require_once __DIR__ . '/../Models/User.php';

class AdminController extends Controller {
    private $userModel;
    
    public function __construct($conn) {
        parent::__construct($conn);
        $this->requireRole('admin');
        $this->userModel = new User($conn);
    }
    
    /**
     * Admin dashboard
     */
    public function dashboard() {
        $admin_id = $_SESSION['user_id'];
        $user = $this->userModel->find($admin_id);
        
        // Get admin stats
        $stats = [
            'students' => count($this->userModel->getStudents()),
            'teachers' => count($this->userModel->getTeachers()),
            'pending_apps' => 0,
            'pending_updates' => 0
        ];
        
        $this->render('dashboard/admin/index', [
            'user' => $user,
            'stats' => $stats
        ]);
    }
}

