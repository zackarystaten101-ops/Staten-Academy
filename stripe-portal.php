<?php
/**
 * Stripe Customer Portal
 * Creates a Stripe billing portal session and redirects the customer
 */

ob_start();
session_start();
require_once 'config.php';
require_once 'db.php';

// Check if user is logged in
if (!isset($_SESSION['user_id']) || ($_SESSION['user_role'] !== 'student' && $_SESSION['user_role'] !== 'new_student')) {
    ob_end_clean();
    header("Location: login.php");
    exit;
}

$student_id = $_SESSION['user_id'];

// Get user's Stripe customer ID
$stmt = $conn->prepare("SELECT stripe_customer_id, email FROM users WHERE id = ?");
$stmt->bind_param("i", $student_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    $stmt->close();
    ob_end_clean();
    header("Location: student-dashboard.php?error=user_not_found");
    exit;
}

$user = $result->fetch_assoc();
$stmt->close();

$stripe_customer_id = $user['stripe_customer_id'] ?? null;

// If customer doesn't have a Stripe customer ID yet, we need to create one
if (empty($stripe_customer_id)) {
    // Check if Stripe API key is configured
    if (!defined('STRIPE_SECRET_KEY') || empty(STRIPE_SECRET_KEY)) {
        ob_end_clean();
        header("Location: student-dashboard.php?error=stripe_not_configured");
        exit;
    }
    
    // Create Stripe customer via API
    $ch = curl_init('https://api.stripe.com/v1/customers');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_USERPWD, STRIPE_SECRET_KEY . ':');
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query([
        'email' => $user['email'],
        'metadata' => [
            'user_id' => (string)$student_id
        ]
    ]));
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/x-www-form-urlencoded']);
    
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    
    if ($http_code !== 200) {
        curl_close($ch);
        ob_end_clean();
        header("Location: student-dashboard.php?error=stripe_customer_creation_failed");
        exit;
    }
    
    curl_close($ch);
    $customer_data = json_decode($response, true);
    
    if (!isset($customer_data['id'])) {
        ob_end_clean();
        header("Location: student-dashboard.php?error=stripe_customer_creation_failed");
        exit;
    }
    
    $stripe_customer_id = $customer_data['id'];
    
    // Store customer ID in database
    $update_stmt = $conn->prepare("UPDATE users SET stripe_customer_id = ? WHERE id = ?");
    $update_stmt->bind_param("si", $stripe_customer_id, $student_id);
    $update_stmt->execute();
    $update_stmt->close();
}

// Check if Stripe API key is configured
if (!defined('STRIPE_SECRET_KEY') || empty(STRIPE_SECRET_KEY)) {
    ob_end_clean();
    header("Location: student-dashboard.php?error=stripe_not_configured");
    exit;
}

// Domain URL
$protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http";
$domain = $protocol . "://" . $_SERVER['HTTP_HOST'] . dirname($_SERVER['PHP_SELF']);

// Create billing portal session
$ch = curl_init('https://api.stripe.com/v1/billing_portal/sessions');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_USERPWD, STRIPE_SECRET_KEY . ':');
curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query([
    'customer' => $stripe_customer_id,
    'return_url' => $domain . '/student-dashboard.php#billing'
]));
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/x-www-form-urlencoded']);

$response = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($http_code !== 200) {
    error_log("Stripe portal creation failed: HTTP $http_code - $response");
    ob_end_clean();
    header("Location: student-dashboard.php?error=portal_creation_failed");
    exit;
}

$portal_data = json_decode($response, true);

if (!isset($portal_data['url'])) {
    error_log("Stripe portal creation failed: No URL in response");
    ob_end_clean();
    header("Location: student-dashboard.php?error=portal_creation_failed");
    exit;
}

// Redirect to Stripe portal
ob_end_clean();
header("Location: " . $portal_data['url']);
exit;

