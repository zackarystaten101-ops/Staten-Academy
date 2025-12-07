<?php
require_once __DIR__ . '/../core/Controller.php';
require_once __DIR__ . '/../Services/PaymentService.php';

class PaymentController extends Controller {
    private $paymentService;
    
    public function __construct($conn) {
        parent::__construct($conn);
        $this->paymentService = new PaymentService();
    }
    
    /**
     * Show payment page
     */
    public function index() {
        $this->render('payment/index');
    }
    
    /**
     * Create checkout session
     */
    public function checkout() {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect('/payment');
        }
        
        $priceId = $_POST['price_id'] ?? '';
        $mode = $_POST['mode'] ?? 'payment';
        
        if (empty($priceId)) {
            die("Error: No Price ID provided.");
        }
        
        $result = $this->paymentService->createCheckoutSession($priceId, $mode);
        
        if ($result['success']) {
            header("Location: " . $result['url']);
            exit;
        } else {
            die("Error: " . $result['error']);
        }
    }
}

