<?php
require_once __DIR__ . '/../core/Controller.php';

class SupportController extends Controller {
    public function __construct($conn) {
        parent::__construct($conn);
    }
    
    /**
     * Contact support
     */
    public function contact() {
        $this->requireAuth();
        
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $user_id = $_SESSION['user_id'];
            $user_role = $_SESSION['user_role'];
            $subject = trim($_POST['subject'] ?? '');
            $message = trim($_POST['message'] ?? '');
            
            if (!empty($subject) && !empty($message)) {
                $stmt = $this->conn->prepare("INSERT INTO support_messages (sender_id, sender_role, subject, message, status) VALUES (?, ?, ?, ?, 'open')");
                $stmt->bind_param("isss", $user_id, $user_role, $subject, $message);
                $stmt->execute();
                $stmt->close();
                
                $this->redirect('/support/contact?sent=1');
            }
        }
        
        $this->render('support/contact');
    }
}

