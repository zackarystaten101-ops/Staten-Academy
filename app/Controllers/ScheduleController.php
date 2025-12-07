<?php
require_once __DIR__ . '/../core/Controller.php';
require_once __DIR__ . '/../Models/Lesson.php';
require_once __DIR__ . '/../Models/User.php';
require_once __DIR__ . '/../Services/CalendarService.php';
require_once __DIR__ . '/../Services/NotificationService.php';

class ScheduleController extends Controller {
    private $lessonModel;
    private $userModel;
    private $calendarService;
    private $notificationService;
    
    public function __construct($conn) {
        parent::__construct($conn);
        $this->lessonModel = new Lesson($conn);
        $this->userModel = new User($conn);
        $this->calendarService = new CalendarService($conn);
        $this->notificationService = new NotificationService($conn);
    }
    
    /**
     * View schedule
     */
    public function index() {
        $this->requireAuth();
        
        $user_id = $_SESSION['user_id'];
        $user_role = $_SESSION['user_role'];
        
        $selected_teacher = isset($_GET['teacher']) ? (int)$_GET['teacher'] : 0;
        $teacher = null;
        
        if ($selected_teacher > 0) {
            $teacher = $this->userModel->find($selected_teacher);
        }
        
        // Get lessons based on role
        if ($user_role === 'teacher') {
            $lessons = $this->lessonModel->getByTeacher($user_id);
        } else {
            $lessons = $this->lessonModel->getByStudent($user_id);
        }
        
        // Get teachers list for students
        $teachers = [];
        if ($user_role === 'student') {
            $teachers = $this->userModel->getTeachers();
        }
        
        $this->render('schedule/index', [
            'lessons' => $lessons,
            'teachers' => $teachers,
            'selected_teacher' => $selected_teacher,
            'teacher' => $teacher
        ]);
    }
    
    /**
     * Book lesson (AJAX)
     */
    public function book() {
        $this->requireAuth();
        header('Content-Type: application/json');
        
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->json(['error' => 'Method not allowed'], 405);
        }
        
        $student_id = $_SESSION['user_id'];
        $teacher_id = isset($_POST['teacher_id']) ? (int)$_POST['teacher_id'] : 0;
        $lesson_date = $_POST['lesson_date'] ?? '';
        $start_time = $_POST['start_time'] ?? '';
        $end_time = $_POST['end_time'] ?? '';
        
        if (!$teacher_id || !$lesson_date || !$start_time || !$end_time) {
            $this->json(['error' => 'Missing required fields'], 400);
        }
        
        // Check availability
        $availability = $this->calendarService->isSlotAvailable($teacher_id, $lesson_date, $start_time, $end_time);
        if (!$availability['available']) {
            $this->json(['error' => $availability['reason']], 409);
        }
        
        // Create lesson
        $googleEventId = null;
        $teacher = $this->userModel->find($teacher_id);
        
        if (!empty($teacher['google_calendar_token'])) {
            $startDateTime = $lesson_date . 'T' . $start_time . ':00';
            $endDateTime = $lesson_date . 'T' . $end_time . ':00';
            
            $eventResult = $this->calendarService->createEvent(
                $teacher_id,
                "Lesson with " . $_SESSION['user_name'],
                $startDateTime,
                $endDateTime
            );
            
            if (isset($eventResult['event_id'])) {
                $googleEventId = $eventResult['event_id'];
            }
        }
        
        $lessonId = $this->lessonModel->createLesson($teacher_id, $student_id, $lesson_date, $start_time, $end_time, $googleEventId);
        
        if ($lessonId) {
            // Notify teacher
            $this->notificationService->create(
                $teacher_id,
                'booking',
                'New Lesson Booking',
                $_SESSION['user_name'] . " booked a lesson on $lesson_date at $start_time",
                '/schedule'
            );
            
            $this->json(['success' => true, 'lesson_id' => $lessonId]);
        } else {
            $this->json(['error' => 'Failed to book lesson'], 500);
        }
    }
}

