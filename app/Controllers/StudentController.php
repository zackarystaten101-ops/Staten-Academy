<?php
require_once __DIR__ . '/../core/Controller.php';
require_once __DIR__ . '/../Models/User.php';
require_once __DIR__ . '/../Models/Lesson.php';
require_once __DIR__ . '/../Services/NotificationService.php';

class StudentController extends Controller {
    private $userModel;
    private $lessonModel;
    private $notificationService;
    
    public function __construct($conn) {
        parent::__construct($conn);
        $this->requireRole('student');
        $this->userModel = new User($conn);
        $this->lessonModel = new Lesson($conn);
        $this->notificationService = new NotificationService($conn);
    }
    
    /**
     * Student dashboard
     */
    public function dashboard() {
        $student_id = $_SESSION['user_id'];
        $user = $this->userModel->find($student_id);
        
        // Get dashboard data
        $lessons = $this->lessonModel->getByStudent($student_id, 'scheduled');
        $unread_notifications = $this->notificationService->getUnreadCount($student_id);
        
        $this->render('dashboard/student/index', [
            'user' => $user,
            'lessons' => $lessons,
            'unread_notifications' => $unread_notifications
        ]);
    }
}

