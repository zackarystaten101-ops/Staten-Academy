<?php
/**
 * Wallet Service
 * Handles wallet operations, interfaces with TypeScript API or MySQL fallback
 */

class WalletService {
    private $conn;
    private $apiBaseUrl;
    private $useApi;
    
    public function __construct($conn) {
        $this->conn = $conn;
        
        // Check if TypeScript API is available
        $this->apiBaseUrl = defined('WALLET_API_URL') ? WALLET_API_URL : 'http://localhost:3001/api';
        $this->useApi = $this->checkApiAvailability();
    }
    
    /**
     * Check if TypeScript API is available
     * @return bool
     */
    private function checkApiAvailability() {
        // Try to ping the API
        $ch = curl_init($this->apiBaseUrl . '/health');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 2);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 2);
        curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        // If API is not available, use MySQL fallback
        return ($httpCode >= 200 && $httpCode < 300);
    }
    
    /**
     * Get wallet balance for a student
     * @param int $student_id
     * @return array ['balance' => float, 'trial_credits' => int]
     */
    public function getWalletBalance($student_id) {
        $student_id = intval($student_id);
        
        if ($this->useApi) {
            return $this->getBalanceFromApi($student_id);
        } else {
            return $this->getBalanceFromMySQL($student_id);
        }
    }
    
    /**
     * Get balance from TypeScript API
     * @param int $student_id
     * @return array
     */
    private function getBalanceFromApi($student_id) {
        // For now, fall back to MySQL since API structure may differ
        // This can be implemented when API endpoints are ready
        return $this->getBalanceFromMySQL($student_id);
    }
    
    /**
     * Get balance from MySQL
     * @param int $student_id
     * @return array
     */
    private function getBalanceFromMySQL($student_id) {
        $stmt = $this->conn->prepare("SELECT balance, trial_credits FROM student_wallet WHERE student_id = ?");
        if (!$stmt) {
            return ['balance' => 0.00, 'trial_credits' => 0];
        }
        
        $stmt->bind_param("i", $student_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            $wallet = $result->fetch_assoc();
            $stmt->close();
            return [
                'balance' => floatval($wallet['balance']),
                'trial_credits' => intval($wallet['trial_credits'])
            ];
        }
        
        $stmt->close();
        
        // Initialize wallet if it doesn't exist
        $this->initializeWallet($student_id);
        return ['balance' => 0.00, 'trial_credits' => 0];
    }
    
    /**
     * Add funds to wallet
     * @param int $student_id
     * @param float $amount
     * @param string $stripe_payment_id
     * @param string $reference_id Optional
     * @return bool
     */
    public function addFunds($student_id, $amount, $stripe_payment_id, $reference_id = null) {
        $student_id = intval($student_id);
        $amount = floatval($amount);
        $stripe_payment_id = $this->conn->real_escape_string($stripe_payment_id);
        $reference_id = $reference_id ? $this->conn->real_escape_string($reference_id) : null;
        
        // Start transaction
        $this->conn->begin_transaction();
        
        try {
            // Initialize wallet if needed
            $this->initializeWallet($student_id);
            
            // Update balance
            $update_sql = "UPDATE student_wallet SET balance = balance + ? WHERE student_id = ?";
            $stmt = $this->conn->prepare($update_sql);
            if (!$stmt) {
                throw new Exception("Failed to prepare update statement: " . $this->conn->error);
            }
            
            $stmt->bind_param("di", $amount, $student_id);
            if (!$stmt->execute()) {
                throw new Exception("Failed to update balance: " . $stmt->error);
            }
            $stmt->close();
            
            // Record transaction
            $transaction_sql = "INSERT INTO wallet_transactions (student_id, type, amount, stripe_payment_id, reference_id, description)
                               VALUES (?, 'purchase', ?, ?, ?, ?)";
            $stmt = $this->conn->prepare($transaction_sql);
            if (!$stmt) {
                throw new Exception("Failed to prepare transaction statement: " . $this->conn->error);
            }
            
            $description = "Funds added via Stripe payment";
            $stmt->bind_param("idsss", $student_id, $amount, $stripe_payment_id, $reference_id, $description);
            if (!$stmt->execute()) {
                throw new Exception("Failed to record transaction: " . $stmt->error);
            }
            $stmt->close();
            
            // Commit transaction
            $this->conn->commit();
            
            // Also call TypeScript API if available
            if ($this->useApi) {
                $this->addFundsToApi($student_id, $amount, $stripe_payment_id);
            }
            
            return true;
            
        } catch (Exception $e) {
            $this->conn->rollback();
            error_log("WalletService::addFunds - Error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Deduct funds from wallet
     * @param int $student_id
     * @param float $amount
     * @param string $reference_id
     * @param string $description Optional
     * @return bool
     */
    public function deductFunds($student_id, $amount, $reference_id, $description = null) {
        $student_id = intval($student_id);
        $amount = floatval($amount);
        $reference_id = $this->conn->real_escape_string($reference_id);
        $description = $description ? $this->conn->real_escape_string($description) : "Lesson booking";
        
        // Start transaction
        $this->conn->begin_transaction();
        
        try {
            // Check balance
            $balance = $this->getWalletBalance($student_id);
            if ($balance['balance'] < $amount) {
                throw new Exception("Insufficient funds. Balance: $" . number_format($balance['balance'], 2) . ", Required: $" . number_format($amount, 2));
            }
            
            // Update balance
            $update_sql = "UPDATE student_wallet SET balance = balance - ? WHERE student_id = ?";
            $stmt = $this->conn->prepare($update_sql);
            if (!$stmt) {
                throw new Exception("Failed to prepare update statement: " . $this->conn->error);
            }
            
            $stmt->bind_param("di", $amount, $student_id);
            if (!$stmt->execute()) {
                throw new Exception("Failed to update balance: " . $stmt->error);
            }
            $stmt->close();
            
            // Record transaction
            $transaction_sql = "INSERT INTO wallet_transactions (student_id, type, amount, reference_id, description)
                               VALUES (?, 'deduction', ?, ?, ?)";
            $stmt = $this->conn->prepare($transaction_sql);
            if (!$stmt) {
                throw new Exception("Failed to prepare transaction statement: " . $this->conn->error);
            }
            
            $stmt->bind_param("idss", $student_id, $amount, $reference_id, $description);
            if (!$stmt->execute()) {
                throw new Exception("Failed to record transaction: " . $stmt->error);
            }
            $stmt->close();
            
            // Commit transaction
            $this->conn->commit();
            
            // Also call TypeScript API if available
            if ($this->useApi) {
                $this->deductFundsFromApi($student_id, $amount, $reference_id);
            }
            
            return true;
            
        } catch (Exception $e) {
            $this->conn->rollback();
            error_log("WalletService::deductFunds - Error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Add trial credit to wallet
     * @param int $student_id
     * @param string $stripe_payment_id
     * @return bool
     */
    public function addTrialCredit($student_id, $stripe_payment_id) {
        $student_id = intval($student_id);
        $stripe_payment_id = $this->conn->real_escape_string($stripe_payment_id);
        
        // Start transaction
        $this->conn->begin_transaction();
        
        try {
            // Initialize wallet if needed
            $this->initializeWallet($student_id);
            
            // Update trial credits
            $update_sql = "UPDATE student_wallet SET trial_credits = trial_credits + 1 WHERE student_id = ?";
            $stmt = $this->conn->prepare($update_sql);
            if (!$stmt) {
                throw new Exception("Failed to prepare update statement: " . $this->conn->error);
            }
            
            $stmt->bind_param("i", $student_id);
            if (!$stmt->execute()) {
                throw new Exception("Failed to update trial credits: " . $stmt->error);
            }
            $stmt->close();
            
            // Record transaction
            $transaction_sql = "INSERT INTO wallet_transactions (student_id, type, amount, stripe_payment_id, description)
                               VALUES (?, 'trial', 25.00, ?, 'Trial lesson credit')";
            $stmt = $this->conn->prepare($transaction_sql);
            if (!$stmt) {
                throw new Exception("Failed to prepare transaction statement: " . $this->conn->error);
            }
            
            $stmt->bind_param("is", $student_id, $stripe_payment_id);
            if (!$stmt->execute()) {
                throw new Exception("Failed to record transaction: " . $stmt->error);
            }
            $stmt->close();
            
            // Commit transaction
            $this->conn->commit();
            
            return true;
            
        } catch (Exception $e) {
            $this->conn->rollback();
            error_log("WalletService::addTrialCredit - Error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Get transaction history
     * @param int $student_id
     * @param int $limit
     * @return array
     */
    public function getTransactionHistory($student_id, $limit = 50) {
        $student_id = intval($student_id);
        $limit = intval($limit);
        
        $sql = "SELECT * FROM wallet_transactions 
                WHERE student_id = ? 
                ORDER BY created_at DESC 
                LIMIT ?";
        
        $stmt = $this->conn->prepare($sql);
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
     * Initialize wallet for student if it doesn't exist
     * @param int $student_id
     * @return bool
     */
    private function initializeWallet($student_id) {
        $check_sql = "SELECT id FROM student_wallet WHERE student_id = ?";
        $stmt = $this->conn->prepare($check_sql);
        if (!$stmt) {
            return false;
        }
        
        $stmt->bind_param("i", $student_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            $stmt->close();
            return true;
        }
        
        $stmt->close();
        
        // Create wallet
        $insert_sql = "INSERT INTO student_wallet (student_id, balance, trial_credits) VALUES (?, 0.00, 0)";
        $stmt = $this->conn->prepare($insert_sql);
        if (!$stmt) {
            return false;
        }
        
        $stmt->bind_param("i", $student_id);
        $result = $stmt->execute();
        $stmt->close();
        
        return $result;
    }
    
    /**
     * Add funds via TypeScript API (placeholder)
     * @param int $student_id
     * @param float $amount
     * @param string $stripe_payment_id
     */
    private function addFundsToApi($student_id, $amount, $stripe_payment_id) {
        // TODO: Implement API call when endpoints are ready
        // For now, MySQL is the source of truth
    }
    
    /**
     * Deduct funds via TypeScript API (placeholder)
     * @param int $student_id
     * @param float $amount
     * @param string $reference_id
     */
    private function deductFundsFromApi($student_id, $amount, $reference_id) {
        // TODO: Implement API call when endpoints are ready
        // For now, MySQL is the source of truth
    }
}

