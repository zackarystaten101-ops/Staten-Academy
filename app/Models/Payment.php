<?php
require_once __DIR__ . '/../../core/Model.php';

class Payment extends Model {
    protected $table = 'payments';
    
    public function __construct($conn) {
        parent::__construct($conn);
    }
    
    /**
     * Get payments for a user
     */
    public function getByUser($userId) {
        return $this->all(['user_id' => $userId], 'created_at DESC');
    }
    
    /**
     * Create payment record
     */
    public function createPayment($userId, $amount, $stripeSessionId, $status = 'pending') {
        $data = [
            'user_id' => $userId,
            'amount' => $amount,
            'stripe_session_id' => $stripeSessionId,
            'status' => $status
        ];
        return $this->create($data);
    }
    
    /**
     * Update payment status
     */
    public function updateStatus($stripeSessionId, $status) {
        $stmt = $this->conn->prepare("UPDATE {$this->table} SET status = ? WHERE stripe_session_id = ?");
        $stmt->bind_param("ss", $status, $stripeSessionId);
        $result = $stmt->execute();
        $stmt->close();
        return $result;
    }
}

