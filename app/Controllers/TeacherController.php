<?php
require_once __DIR__ . '/../core/Controller.php';
require_once __DIR__ . '/../Models/User.php';
require_once __DIR__ . '/../Models/Lesson.php';
require_once __DIR__ . '/../Models/Review.php';
require_once __DIR__ . '/../Models/Assignment.php';
require_once __DIR__ . '/../Models/Material.php';
require_once __DIR__ . '/../Services/NotificationService.php';

class TeacherController extends Controller {
    private $userModel;
    private $lessonModel;
    private $reviewModel;
    private $assignmentModel;
    private $materialModel;
    private $notificationService;
    
    public function __construct($conn) {
        parent::__construct($conn);
        $this->requireRole('teacher');
        $this->userModel = new User($conn);
        $this->lessonModel = new Lesson($conn);
        $this->reviewModel = new Review($conn);
        $this->assignmentModel = new Assignment($conn);
        $this->materialModel = new Material($conn);
        $this->notificationService = new NotificationService($conn);
    }
    
    /**
     * Teacher dashboard
     */
    public function dashboard() {
        $teacher_id = $_SESSION['user_id'];
        $user = $this->userModel->find($teacher_id);
        
        // Get dashboard data
        $lessons = $this->lessonModel->getByTeacher($teacher_id, 'scheduled');
        $reviews = $this->reviewModel->getByTeacher($teacher_id);
        $rating_data = $this->reviewModel->getAverageRating($teacher_id);
        $pending_assignments = $this->assignmentModel->getPendingCount($teacher_id);
        $materials = $this->materialModel->getAll();
        $unread_messages = 0; // Will be calculated from Message model
        $unread_notifications = $this->notificationService->getUnreadCount($teacher_id);
        
        $this->render('dashboard/teacher/index', [
            'user' => $user,
            'lessons' => $lessons,
            'reviews' => $reviews,
            'rating_data' => $rating_data,
            'pending_assignments' => $pending_assignments,
            'materials' => $materials,
            'unread_messages' => $unread_messages,
            'unread_notifications' => $unread_notifications
        ]);
    }
}

