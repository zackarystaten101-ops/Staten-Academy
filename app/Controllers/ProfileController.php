<?php
require_once __DIR__ . '/../core/Controller.php';
require_once __DIR__ . '/../Models/User.php';
require_once __DIR__ . '/../Models/Review.php';
require_once __DIR__ . '/../Services/NotificationService.php';

class ProfileController extends Controller {
    private $userModel;
    private $reviewModel;
    private $notificationService;
    
    public function __construct($conn) {
        parent::__construct($conn);
        $this->userModel = new User($conn);
        $this->reviewModel = new Review($conn);
        $this->notificationService = new NotificationService($conn);
    }
    
    /**
     * View teacher profile
     */
    public function view($id) {
        $teacher = $this->userModel->find($id);
        
        if (!$teacher || ($teacher['role'] !== 'teacher' && $teacher['role'] !== 'admin')) {
            die("Teacher not found.");
        }
        
        $user_role = isset($_SESSION['user_role']) ? $_SESSION['user_role'] : 'guest';
        $user_id = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : null;
        
        // Get rating data
        $rating_data = $this->reviewModel->getAverageRating($id);
        
        // Get reviews
        $reviews = $this->reviewModel->getByTeacher($id);
        
        // Check if favorite
        $is_favorite = false;
        if ($user_id && $user_role === 'student') {
            $stmt = $this->conn->prepare("SELECT id FROM favorite_teachers WHERE student_id = ? AND teacher_id = ?");
            $stmt->bind_param("ii", $user_id, $id);
            $stmt->execute();
            $is_favorite = $stmt->get_result()->num_rows > 0;
            $stmt->close();
        }
        
        // Handle review submission
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_review']) && $user_id && $user_role === 'student') {
            $rating = (int)$_POST['rating'];
            $review_text = trim($_POST['review_text'] ?? '');
            
            if ($rating >= 1 && $rating <= 5) {
                $this->reviewModel->createReview($id, $user_id, $rating, $review_text);
                $this->notificationService->create($id, 'review', 'New Review', 
                    $_SESSION['user_name'] . " left you a $rating-star review!", '/dashboard/teacher#reviews');
                $this->redirect("/profile/view/$id?reviewed=1");
            }
        }
        
        $this->render('profile/view', [
            'teacher' => $teacher,
            'rating_data' => $rating_data,
            'reviews' => $reviews,
            'is_favorite' => $is_favorite,
            'user_role' => $user_role,
            'user_id' => $user_id
        ]);
    }
}

