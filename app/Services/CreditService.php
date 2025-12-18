<?php
/**
 * Credit Service
 * Handles all credit operations for students
 */

class CreditService {
    private $conn;
    
    public function __construct($conn) {
        $this->conn = $conn;
    }
    
    /**
     * Add credits to a student's account
     * @param int $student_id
     * @param int $amount
     * @param string $type (admin_add, purchase, subscription_renewal, gift_received)
     * @param string $description
     * @param string $reference_id Optional reference ID (e.g., Stripe payment ID, subscription ID)
     * @return bool
     */
    public function addCredits($student_id, $amount, $type, $description = '', $reference_id = null) {
        $student_id = intval($student_id);
        $amount = intval($amount);
        
        if ($student_id <= 0 || $amount <= 0) {
            return false;
        }
        
        // Validate type
        $valid_types = ['admin_add', 'purchase', 'subscription_renewal', 'gift_received'];
        if (!in_array($type, $valid_types)) {
            error_log("CreditService: Invalid type for addCredits: $type");
            return false;
        }
        
        $this->conn->begin_transaction();
        
        try {
            // Update user's credit balance
            $update_sql = "UPDATE users SET credits_balance = credits_balance + ? WHERE id = ?";
            $stmt = $this->conn->prepare($update_sql);
            if (!$stmt) {
                throw new Exception("Failed to prepare update statement: " . $this->conn->error);
            }
            
            $stmt->bind_param("ii", $amount, $student_id);
            if (!$stmt->execute()) {
                throw new Exception("Failed to update credits balance: " . $stmt->error);
            }
            $stmt->close();
            
            // Record transaction
            $transaction_sql = "INSERT INTO credit_transactions (student_id, type, amount, description, reference_id)
                               VALUES (?, ?, ?, ?, ?)";
            $stmt = $this->conn->prepare($transaction_sql);
            if (!$stmt) {
                throw new Exception("Failed to prepare transaction statement: " . $this->conn->error);
            }
            
            $stmt->bind_param("isiss", $student_id, $type, $amount, $description, $reference_id);
            if (!$stmt->execute()) {
                throw new Exception("Failed to record credit transaction: " . $stmt->error);
            }
            $stmt->close();
            
            $this->conn->commit();
            return true;
        } catch (Exception $e) {
            $this->conn->rollback();
            error_log("CreditService::addCredits error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Remove credits from a student's account
     * @param int $student_id
     * @param int $amount
     * @param string $description
     * @return bool
     */
    public function removeCredits($student_id, $amount, $description = '') {
        $student_id = intval($student_id);
        $amount = intval($amount);
        
        if ($student_id <= 0 || $amount <= 0) {
            return false;
        }
        
        // Check if student has enough credits
        $balance = $this->getCreditsBalance($student_id);
        if ($balance < $amount) {
            return false;
        }
        
        $this->conn->begin_transaction();
        
        try {
            // Update user's credit balance
            $update_sql = "UPDATE users SET credits_balance = credits_balance - ? WHERE id = ?";
            $stmt = $this->conn->prepare($update_sql);
            if (!$stmt) {
                throw new Exception("Failed to prepare update statement: " . $this->conn->error);
            }
            
            $stmt->bind_param("ii", $amount, $student_id);
            if (!$stmt->execute()) {
                throw new Exception("Failed to update credits balance: " . $stmt->error);
            }
            $stmt->close();
            
            // Record transaction
            $transaction_sql = "INSERT INTO credit_transactions (student_id, type, amount, description)
                               VALUES (?, 'admin_remove', ?, ?)";
            $stmt = $this->conn->prepare($transaction_sql);
            if (!$stmt) {
                throw new Exception("Failed to prepare transaction statement: " . $this->conn->error);
            }
            
            $stmt->bind_param("iis", $student_id, $amount, $description);
            if (!$stmt->execute()) {
                throw new Exception("Failed to record credit transaction: " . $stmt->error);
            }
            $stmt->close();
            
            $this->conn->commit();
            return true;
        } catch (Exception $e) {
            $this->conn->rollback();
            error_log("CreditService::removeCredits error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Use a credit when booking a lesson
     * @param int $student_id
     * @param int $lesson_id
     * @return bool
     */
    public function useCredit($student_id, $lesson_id) {
        $student_id = intval($student_id);
        $lesson_id = intval($lesson_id);
        
        if ($student_id <= 0 || $lesson_id <= 0) {
            return false;
        }
        
        // Check if student has at least 1 credit
        $balance = $this->getCreditsBalance($student_id);
        if ($balance < 1) {
            return false;
        }
        
        $this->conn->begin_transaction();
        
        try {
            // Deduct 1 credit
            $update_sql = "UPDATE users SET credits_balance = credits_balance - 1 WHERE id = ?";
            $stmt = $this->conn->prepare($update_sql);
            if (!$stmt) {
                throw new Exception("Failed to prepare update statement: " . $this->conn->error);
            }
            
            $stmt->bind_param("i", $student_id);
            if (!$stmt->execute()) {
                throw new Exception("Failed to update credits balance: " . $stmt->error);
            }
            $stmt->close();
            
            // Record transaction
            $description = "Credit used for lesson #" . $lesson_id;
            $reference_id = 'lesson_' . $lesson_id;
            $transaction_sql = "INSERT INTO credit_transactions (student_id, type, amount, description, reference_id)
                               VALUES (?, 'lesson_used', 1, ?, ?)";
            $stmt = $this->conn->prepare($transaction_sql);
            if (!$stmt) {
                throw new Exception("Failed to prepare transaction statement: " . $this->conn->error);
            }
            
            $stmt->bind_param("iss", $student_id, $description, $reference_id);
            if (!$stmt->execute()) {
                throw new Exception("Failed to record credit transaction: " . $stmt->error);
            }
            $stmt->close();
            
            $this->conn->commit();
            return true;
        } catch (Exception $e) {
            $this->conn->rollback();
            error_log("CreditService::useCredit error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Get current credits balance for a student
     * @param int $student_id
     * @return int
     */
    public function getCreditsBalance($student_id) {
        $student_id = intval($student_id);
        
        $stmt = $this->conn->prepare("SELECT credits_balance FROM users WHERE id = ?");
        if (!$stmt) {
            return 0;
        }
        
        $stmt->bind_param("i", $student_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            $row = $result->fetch_assoc();
            $stmt->close();
            return intval($row['credits_balance'] ?? 0);
        }
        
        $stmt->close();
        return 0;
    }
    
    /**
     * Get credit transaction history for a student
     * @param int $student_id
     * @param int $limit
     * @return array
     */
    public function getCreditHistory($student_id, $limit = 50) {
        $student_id = intval($student_id);
        $limit = intval($limit);
        
        $stmt = $this->conn->prepare("SELECT * FROM credit_transactions 
                                      WHERE student_id = ? 
                                      ORDER BY created_at DESC 
                                      LIMIT ?");
        if (!$stmt) {
            return [];
        }
        
        $stmt->bind_param("ii", $student_id, $limit);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $transactions = [];
        while ($row = $result->fetch_assoc()) {
            $transactions[] = $row;
        }
        
        $stmt->close();
        return $transactions;
    }
    
    /**
     * Transfer credits as a gift
     * @param int $purchaser_id
     * @param string $recipient_email
     * @param int $credits
     * @param string $stripe_payment_id
     * @return bool
     */
    public function transferCreditsGift($purchaser_id, $recipient_email, $credits, $stripe_payment_id) {
        $purchaser_id = intval($purchaser_id);
        $credits = intval($credits);
        
        if ($purchaser_id <= 0 || $credits <= 0 || empty($recipient_email) || empty($stripe_payment_id)) {
            return false;
        }
        
        // Find recipient by email
        $recipient_stmt = $this->conn->prepare("SELECT id FROM users WHERE email = ?");
        $recipient_stmt->bind_param("s", $recipient_email);
        $recipient_stmt->execute();
        $recipient_result = $recipient_stmt->get_result();
        $recipient_id = null;
        if ($recipient_result->num_rows > 0) {
            $recipient_row = $recipient_result->fetch_assoc();
            $recipient_id = intval($recipient_row['id']);
        }
        $recipient_stmt->close();
        
        $this->conn->begin_transaction();
        
        try {
            // Record gift purchase
            $gift_sql = "INSERT INTO gift_credit_purchases (purchaser_id, recipient_email, recipient_id, credits_amount, stripe_payment_id, status)
                        VALUES (?, ?, ?, ?, ?, 'completed')";
            $stmt = $this->conn->prepare($gift_sql);
            if (!$stmt) {
                throw new Exception("Failed to prepare gift purchase statement: " . $this->conn->error);
            }
            
            $stmt->bind_param("isiss", $purchaser_id, $recipient_email, $recipient_id, $credits, $stripe_payment_id);
            if (!$stmt->execute()) {
                throw new Exception("Failed to record gift purchase: " . $stmt->error);
            }
            $stmt->close();
            
            // If recipient exists, add credits to their account
            if ($recipient_id) {
                // Get purchaser email for description
                $purchaser_stmt = $this->conn->prepare("SELECT email, name FROM users WHERE id = ?");
                $purchaser_stmt->bind_param("i", $purchaser_id);
                $purchaser_stmt->execute();
                $purchaser_result = $purchaser_stmt->get_result();
                $purchaser_email = '';
                if ($purchaser_result->num_rows > 0) {
                    $purchaser_data = $purchaser_result->fetch_assoc();
                    $purchaser_email = $purchaser_data['email'] ?? $purchaser_data['name'] ?? 'a friend';
                }
                $purchaser_stmt->close();
                
                $description = "Gift credits received from " . $purchaser_email;
                if (!$this->addCredits($recipient_id, $credits, 'gift_received', $description, $stripe_payment_id)) {
                    throw new Exception("Failed to add credits to recipient");
                }
                
                // Update recipient's credits_gifted counter
                $update_gifted = $this->conn->prepare("UPDATE users SET credits_gifted = credits_gifted + ? WHERE id = ?");
                $update_gifted->bind_param("ii", $credits, $recipient_id);
                $update_gifted->execute();
                $update_gifted->close();
            }
            
            // Record gift_sent transaction for purchaser
            $purchaser_description = "Gift credits sent to " . $recipient_email;
            $purchaser_transaction = "INSERT INTO credit_transactions (student_id, type, amount, description, reference_id)
                                     VALUES (?, 'gift_sent', ?, ?, ?)";
            $stmt = $this->conn->prepare($purchaser_transaction);
            $stmt->bind_param("iiss", $purchaser_id, $credits, $purchaser_description, $stripe_payment_id);
            $stmt->execute();
            $stmt->close();
            
            $this->conn->commit();
            return true;
        } catch (Exception $e) {
            $this->conn->rollback();
            error_log("CreditService::transferCreditsGift error: " . $e->getMessage());
            return false;
        }
    }
}

