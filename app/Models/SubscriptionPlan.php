<?php
require_once __DIR__ . '/../core/Model.php';

class SubscriptionPlan extends Model {
    protected $table = 'subscription_plans';
    
    public function __construct($conn) {
        parent::__construct($conn);
    }
    
    /**
     * Get all active plans ordered by display_order
     */
    public function getActivePlans() {
        $sql = "SELECT * FROM {$this->table} WHERE is_active = TRUE ORDER BY display_order ASC";
        return $this->conn->query($sql);
    }
    
    /**
     * Get plan by ID
     */
    public function getPlan($plan_id) {
        return $this->find($plan_id);
    }
    
    /**
     * Get plan by Stripe price ID
     */
    public function getPlanByStripePriceId($stripe_price_id) {
        $sql = "SELECT * FROM {$this->table} WHERE stripe_price_id = ? AND is_active = TRUE";
        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param("s", $stripe_price_id);
        $stmt->execute();
        $result = $stmt->get_result();
        return $result->fetch_assoc();
    }
    
    /**
     * Get plans by track
     */
    public function getPlansByTrack($track) {
        $sql = "SELECT * FROM {$this->table} 
                WHERE track = ? AND is_active = TRUE 
                ORDER BY display_order ASC, one_on_one_classes_per_week ASC";
        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param("s", $track);
        $stmt->execute();
        $result = $stmt->get_result();
        $plans = [];
        while ($row = $result->fetch_assoc()) {
            $plans[] = $row;
        }
        $stmt->close();
        return $plans;
    }
}








