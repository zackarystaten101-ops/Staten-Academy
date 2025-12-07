<?php
require_once 'config.php';

// Check if Price ID is provided
if (!isset($_POST['price_id'])) {
    die("Error: No Price ID provided.");
}

$priceId = $_POST['price_id'];
$mode = isset($_POST['mode']) ? $_POST['mode'] : 'payment';

// Domain URL (Dynamic based on server)
$protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http";
$domain = $protocol . "://" . $_SERVER['HTTP_HOST'] . dirname($_SERVER['PHP_SELF']);

// FIX: Ensure domain does not contain spaces which breaks Stripe
// Use rawurlencode for path segments if needed, but for now, let's hardcode localhost if detecting spaces issues.
// Or better, just ensure URL is valid.
if (strpos($domain, ' ') !== false) {
    // Replace spaces with %20
    $domain = str_replace(' ', '%20', $domain);
}

// Stripe API Endpoint
$api_url = 'https://api.stripe.com/v1/checkout/sessions';

// Data for the request
$data = [
    'line_items' => [
        [
            'price' => $priceId,
            'quantity' => 1,
        ],
    ],
    'mode' => $mode, // 'subscription' for recurring, 'payment' for one-time
    'success_url' => $domain . '/success.php',
    'cancel_url' => $domain . '/cancel.php',
];

// If it's a subscription plan (you can check price ID or pass a mode param)
// For now, defaulting to 'payment' as requested for the $30 one-time.
// If you add monthly plans later, we can make this dynamic.

// Convert data to query string for curl
$post_fields = http_build_query($data);
// Note: Stripe API expects nested arrays (like line_items[0][price]) to be standard HTTP query params, 
// which http_build_query handles correctly.

// Initialize cURL
$ch = curl_init();

curl_setopt($ch, CURLOPT_URL, $api_url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, $post_fields);
curl_setopt($ch, CURLOPT_USERPWD, STRIPE_SECRET_KEY . ':'); // Basic Auth with Secret Key
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/x-www-form-urlencoded']);

// Execute request
$response = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);

if (curl_errno($ch)) {
    die('cURL Error: ' . curl_error($ch));
}

curl_close($ch);

// Decode response
$session = json_decode($response, true);

if ($http_code !== 200) {
    // Handle API Error
    echo "Error creating checkout session: ";
    if (isset($session['error']['message'])) {
        echo $session['error']['message'];
    } else {
        // Debug: Session created successfully
        // print_r($session); // Commented out for production
    }
    exit;
}

// Redirect to Stripe Checkout
if (isset($session['url'])) {
    header("Location: " . $session['url']);
    exit;
} else {
    die("Error: No checkout URL returned from Stripe.");
}
?>
