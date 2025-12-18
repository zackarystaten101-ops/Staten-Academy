<?php
/**
 * Subscription Service
 * Handles subscription-related operations including monthly credit resets
 */

require_once __DIR__ . '/CreditService.php';

class SubscriptionService {
    private $conn;
    private $creditService;
    
    public function __construct($conn) {
        $this->conn = $conn;
        $this->creditService = new CreditService($conn);
    }
    
    /**
     * Process monthly credit reset for a subscription
     * @param int $student_id
     * @param string $subscription_id Stripe subscription ID
     * @return bool
     */
    public function processMonthlyCreditReset($student_id, $subscription_id = null) {
        $student_id = intval($student_id);
        
        if ($student_id <= 0) {
            return false;
        }
        
        // Get user's subscription info
        $user_stmt = $this->conn->prepare("SELECT active_subscription_id, plan_id, subscription_billing_cycle_date FROM users WHERE id = ?");
        $user_stmt->bind_param("i", $student_id);
        $user_stmt->execute();
        $user_result = $user_stmt->get_result();
        
        if ($user_result->num_rows === 0) {
            $user_stmt->close();
            return false;
        }
        
        $user = $user_result->fetch_assoc();
        $user_stmt->close();
        
        // Use provided subscription_id or get from user record
        $sub_id = $subscription_id ?? $user['active_subscription_id'];
        if (empty($sub_id)) {
            return false;
        }
        
        // Get plan details
        $plan_id = $user['plan_id'];
        if (!$plan_id) {
            return false;
        }
        
        $plan_stmt = $this->conn->prepare("SELECT credits_included, name FROM subscription_plans WHERE id = ?");
        $plan_stmt->bind_param("i", $plan_id);
        $plan_stmt->execute();
        $plan_result = $plan_stmt->get_result();
        
        if ($plan_result->num_rows === 0) {
            $plan_stmt->close();
            return false;
        }
        
        $plan = $plan_result->fetch_assoc();
        $plan_stmt->close();
        
        $credits_to_add = intval($plan['credits_included'] ?? 0);
        if ($credits_to_add <= 0) {
            return false;
        }
        
        // Check if rollover is enabled (admin setting)
        $admin_setting_stmt = $this->conn->prepare("SELECT value FROM admin_settings WHERE setting_key = 'credit_rollover_enabled'");
        $admin_setting_stmt->execute();
        $admin_setting_result = $admin_setting_stmt->get_result();
        $rollover_enabled = false;
        if ($admin_setting_result->num_rows > 0) {
            $setting = $admin_setting_result->fetch_assoc();
            $rollover_enabled = ($setting['value'] === '1' || $setting['value'] === 'true');
        }
        $admin_setting_stmt->close();
        
        // If rollover is disabled, reset to plan amount (set balance to credits_to_add)
        // Otherwise, add credits to existing balance
        if (!$rollover_enabled) {
            // Reset balance to plan amount
            $reset_stmt = $this->conn->prepare("UPDATE users SET credits_balance = ? WHERE id = ?");
            $reset_stmt->bind_param("ii", $credits_to_add, $student_id);
            $reset_stmt->execute();
            $reset_stmt->close();
            
            // Record transaction
            $description = "Monthly subscription renewal - " . $plan['name'] . " (reset to " . $credits_to_add . " credits)";
        } else {
            // Add credits to existing balance
            $description = "Monthly subscription renewal - " . $plan['name'] . " (+" . $credits_to_add . " credits)";
        }
        
        // Use CreditService to add credits (which handles transaction logging)
        // If rollover is disabled, we already reset the balance, so we need to log differently
        if ($rollover_enabled) {
            return $this->creditService->addCredits($student_id, $credits_to_add, 'subscription_renewal', $description, $sub_id);
        } else {
            // Log the reset transaction manually
            $transaction_sql = "INSERT INTO credit_transactions (student_id, type, amount, description, reference_id)
                               VALUES (?, 'subscription_renewal', ?, ?, ?)";
            $stmt = $this->conn->prepare($transaction_sql);
            $stmt->bind_param("iiss", $student_id, $credits_to_add, $description, $sub_id);
            $success = $stmt->execute();
            $stmt->close();
            return $success;
        }
    }
    
    /**
     * Get subscription details for a student
     * @param int $student_id
     * @return array|null
     */
    public function getSubscriptionDetails($student_id) {
        $student_id = intval($student_id);
        
        $stmt = $this->conn->prepare("SELECT u.active_subscription_id, u.plan_id, u.subscription_billing_cycle_date, 
                                      sp.name as plan_name, sp.price, sp.credits_included, sp.type as plan_type
                                      FROM users u
                                      LEFT JOIN subscription_plans sp ON u.plan_id = sp.id
                                      WHERE u.id = ?");
        $stmt->bind_param("i", $student_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            $subscription = $result->fetch_assoc();
            $stmt->close();
            return $subscription;
        }
        
        $stmt->close();
        return null;
    }
    
    /**
     * Update subscription details after Stripe webhook
     * @param int $student_id
     * @param string $subscription_id
     * @param int $billing_cycle_date Day of month (1-31)
     * @return bool
     */
    public function updateSubscription($student_id, $subscription_id, $billing_cycle_date = null) {
        $student_id = intval($student_id);
        $billing_cycle_date = $billing_cycle_date ? intval($billing_cycle_date) : null;
        
        $sql = "UPDATE users SET active_subscription_id = ?";
        $params = [$subscription_id];
        $types = "s";
        
        if ($billing_cycle_date !== null) {
            $sql .= ", subscription_billing_cycle_date = ?";
            $params[] = $billing_cycle_date;
            $types .= "i";
        }
        
        $sql .= " WHERE id = ?";
        $params[] = $student_id;
        $types .= "i";
        
        $stmt = $this->conn->prepare($sql);
        if (!$stmt) {
            return false;
        }
        
        $stmt->bind_param($types, ...$params);
        $success = $stmt->execute();
        $stmt->close();
        
        return $success;
    }
    
    /**
     * Cancel subscription
     * @param int $student_id
     * @return bool
     */
    public function cancelSubscription($student_id) {
        $student_id = intval($student_id);
        
        $stmt = $this->conn->prepare("UPDATE users SET active_subscription_id = NULL, subscription_status = 'cancelled' WHERE id = ?");
        $stmt->bind_param("i", $student_id);
        $success = $stmt->execute();
        $stmt->close();
        
        return $success;
    }
}

