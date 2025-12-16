<?php
/**
 * Stripe Service Helper
 * Handles Stripe API interactions for fetching price IDs from product IDs
 */

class StripeService {
    private $secretKey;
    private $conn;
    
    public function __construct($conn) {
        $this->conn = $conn;
        
        // Get Stripe secret key from environment
        if (!defined('STRIPE_SECRET_KEY') || empty(STRIPE_SECRET_KEY)) {
            throw new Exception('STRIPE_SECRET_KEY is not configured in env.php');
        }
        
        $this->secretKey = STRIPE_SECRET_KEY;
    }
    
    /**
     * Get price ID from Stripe product ID
     * Fetches the first active monthly recurring price for a product
     * 
     * @param string $productId Stripe product ID (starts with prod_)
     * @return string|null Price ID (starts with price_) or null if not found
     */
    public function getPriceIdFromProductId($productId) {
        if (empty($productId) || strpos($productId, 'prod_') !== 0) {
            throw new Exception("Invalid product ID: $productId");
        }
        
        // Check cache first (stored in database)
        $cacheKey = "stripe_price_cache_" . md5($productId);
        $cached = $this->getCachedPriceId($productId);
        if ($cached !== null) {
            return $cached;
        }
        
        // Fetch from Stripe API
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => "https://api.stripe.com/v1/prices?product={$productId}&active=true&type=recurring&recurring[interval]=month",
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => [
                "Authorization: Bearer {$this->secretKey}",
                "Content-Type: application/x-www-form-urlencoded"
            ],
            CURLOPT_SSL_VERIFYPEER => true
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);
        
        if ($curlError) {
            error_log("Stripe API curl error: $curlError");
            throw new Exception("Failed to connect to Stripe API: $curlError");
        }
        
        if ($httpCode !== 200) {
            error_log("Stripe API error (HTTP $httpCode): $response");
            throw new Exception("Stripe API returned error: HTTP $httpCode");
        }
        
        $data = json_decode($response, true);
        
        if (!isset($data['data']) || empty($data['data'])) {
            error_log("No prices found for product: $productId");
            return null;
        }
        
        // Get the first monthly recurring price
        $priceId = null;
        foreach ($data['data'] as $price) {
            if (isset($price['id']) && 
                isset($price['recurring']['interval']) && 
                $price['recurring']['interval'] === 'month' &&
                $price['active'] === true) {
                $priceId = $price['id'];
                break;
            }
        }
        
        if ($priceId === null) {
            error_log("No active monthly recurring price found for product: $productId");
            return null;
        }
        
        // Cache the result
        $this->cachePriceId($productId, $priceId);
        
        return $priceId;
    }
    
    /**
     * Get cached price ID from database
     */
    private function getCachedPriceId($productId) {
        $stmt = $this->conn->prepare("SELECT stripe_price_id FROM subscription_plans WHERE stripe_product_id = ? AND stripe_price_id IS NOT NULL LIMIT 1");
        $stmt->bind_param("s", $productId);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($row = $result->fetch_assoc()) {
            return $row['stripe_price_id'];
        }
        return null;
    }
    
    /**
     * Cache price ID in database
     */
    private function cachePriceId($productId, $priceId) {
        // This will be handled by the migration script when inserting plans
        // This method is here for potential future use
    }
    
    /**
     * Batch fetch price IDs for multiple products
     * 
     * @param array $productIds Array of product IDs
     * @return array Associative array: product_id => price_id
     */
    public function batchGetPriceIds($productIds) {
        $results = [];
        foreach ($productIds as $productId) {
            try {
                $priceId = $this->getPriceIdFromProductId($productId);
                $results[$productId] = $priceId;
                // Small delay to avoid rate limiting
                usleep(100000); // 0.1 second
            } catch (Exception $e) {
                error_log("Error fetching price for product $productId: " . $e->getMessage());
                $results[$productId] = null;
            }
        }
        return $results;
    }
}

