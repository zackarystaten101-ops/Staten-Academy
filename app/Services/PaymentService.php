<?php
/**
 * Payment Service
 * Handles Stripe payment processing
 */

class PaymentService {
    private $secretKey;
    private $publishableKey;
    
    public function __construct() {
        $this->secretKey = STRIPE_SECRET_KEY;
        $this->publishableKey = STRIPE_PUBLISHABLE_KEY;
    }
    
    /**
     * Create checkout session
     */
    public function createCheckoutSession($priceId, $mode = 'payment', $successUrl = null, $cancelUrl = null) {
        $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http";
        $domain = $protocol . "://" . $_SERVER['HTTP_HOST'] . dirname($_SERVER['PHP_SELF']);
        
        // Fix spaces in URL
        if (strpos($domain, ' ') !== false) {
            $domain = str_replace(' ', '%20', $domain);
        }
        
        $successUrl = $successUrl ?: $domain . '/success.php';
        $cancelUrl = $cancelUrl ?: $domain . '/cancel.php';
        
        $data = [
            'line_items' => [
                [
                    'price' => $priceId,
                    'quantity' => 1,
                ],
            ],
            'mode' => $mode,
            'success_url' => $successUrl,
            'cancel_url' => $cancelUrl,
        ];
        
        $ch = curl_init('https://api.stripe.com/v1/checkout/sessions');
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POSTFIELDS => http_build_query($data),
            CURLOPT_USERPWD => $this->secretKey . ':',
            CURLOPT_HTTPHEADER => ['Content-Type: application/x-www-form-urlencoded']
        ]);
        
        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($http_code !== 200) {
            $error = json_decode($response, true);
            return ['success' => false, 'error' => $error['error']['message'] ?? 'Payment processing failed'];
        }
        
        $session = json_decode($response, true);
        return ['success' => true, 'url' => $session['url'], 'session_id' => $session['id']];
    }
    
    /**
     * Get publishable key
     */
    public function getPublishableKey() {
        return $this->publishableKey;
    }
}

