<?php
/**
 * Stripe Webhook Handler
 * Handles Stripe webhook events for payments, trials, and subscriptions
 */

// Load environment configuration
if (!defined('DB_HOST')) {
    require_once __DIR__ . '/env.php';
}

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/app/Services/WalletService.php';
require_once __DIR__ . '/app/Services/TrialService.php';

// Get webhook secret
$webhook_secret = defined('STRIPE_WEBHOOK_SECRET') ? STRIPE_WEBHOOK_SECRET : '';

// Get raw POST data
$payload = @file_get_contents('php://input');
$sig_header = $_SERVER['HTTP_STRIPE_SIGNATURE'] ?? '';

// Verify webhook signature
if (!empty($webhook_secret)) {
    try {
        $event = \Stripe\Webhook::constructEvent($payload, $sig_header, $webhook_secret);
    } catch (\Exception $e) {
        http_response_code(400);
        echo json_encode(['error' => 'Webhook signature verification failed']);
        exit;
    }
} else {
    // If webhook secret not configured, decode without verification (not recommended for production)
    $event = json_decode($payload, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid JSON payload']);
        exit;
    }
}

// Handle the event
$event_type = $event['type'] ?? '';
$event_data = $event['data']['object'] ?? [];

http_response_code(200);

switch ($event_type) {
    case 'checkout.session.completed':
        handleCheckoutCompleted($conn, $event_data);
        break;
    
    case 'payment_intent.succeeded':
        handlePaymentSucceeded($conn, $event_data);
        break;
    
    case 'charge.succeeded':
        handleChargeSucceeded($conn, $event_data);
        break;
    
    default:
        // Log unhandled events
        error_log("Unhandled Stripe webhook event: " . $event_type);
        break;
}

echo json_encode(['received' => true]);

/**
 * Handle checkout.session.completed event
 */
function handleCheckoutCompleted($conn, $session) {
    $session_id = $session['id'] ?? '';
    $payment_intent_id = $session['payment_intent'] ?? '';
    $customer_email = $session['customer_email'] ?? '';
    $metadata = $session['metadata'] ?? [];
    $amount_total = isset($session['amount_total']) ? ($session['amount_total'] / 100) : 0; // Convert from cents
    
    // Check if already processed using idempotency key
    $idempotency_key = 'stripe_' . $session_id;
    $walletService = new WalletService($conn);
    $existing_transaction = $walletService->getTransactionByIdempotencyKey($idempotency_key);
    if ($existing_transaction) {
        error_log("Stripe webhook: Session $session_id already processed (idempotency key: $idempotency_key)");
        return;
    }
    
    // Also check by stripe_payment_id for backward compatibility
    $check_sql = "SELECT id FROM wallet_transactions WHERE stripe_payment_id = ? LIMIT 1";
    $check_stmt = $conn->prepare($check_sql);
    if ($check_stmt) {
        $check_stmt->bind_param("s", $session_id);
        $check_stmt->execute();
        $check_result = $check_stmt->get_result();
        if ($check_result->num_rows > 0) {
            $check_stmt->close();
            error_log("Stripe webhook: Session $session_id already processed");
            return;
        }
        $check_stmt->close();
    }
    
    // Determine payment type from metadata
    $payment_type = $metadata['type'] ?? 'plan';
    
    if ($payment_type === 'trial') {
        handleTrialPayment($conn, $session_id, $payment_intent_id, $metadata, $amount_total);
    } else {
        handlePlanPayment($conn, $session_id, $payment_intent_id, $metadata, $amount_total, $customer_email);
    }
}

/**
 * Handle trial lesson payment
 */
function handleTrialPayment($conn, $session_id, $payment_intent_id, $metadata, $amount_total) {
    $student_id = isset($metadata['student_id']) ? intval($metadata['student_id']) : 0;
    $teacher_id = isset($metadata['teacher_id']) ? intval($metadata['teacher_id']) : 0;
    
    if (!$student_id || !$teacher_id) {
        error_log("Stripe webhook: Missing student_id or teacher_id in trial payment metadata");
        return;
    }
    
    // Verify payment amount is $25
    if (abs($amount_total - 25.00) > 0.01) {
        error_log("Stripe webhook: Trial payment amount mismatch. Expected $25.00, got $" . number_format($amount_total, 2));
        // Still process, but log the discrepancy
    }
    
    $walletService = new WalletService($conn);
    $trialService = new TrialService($conn);
    
    // Add trial credit to wallet
    if ($walletService->addTrialCredit($student_id, $session_id)) {
        // Mark trial as used
        $trialService->markTrialAsUsed($student_id);
        
        error_log("Stripe webhook: Trial payment processed for student $student_id, teacher $teacher_id");
    } else {
        error_log("Stripe webhook: Failed to add trial credit for student $student_id");
    }
}

/**
 * Handle plan purchase payment
 */
function handlePlanPayment($conn, $session_id, $payment_intent_id, $metadata, $amount_total, $customer_email) {
    // Find student by email
    $student_id = null;
    if (!empty($customer_email)) {
        $stmt = $conn->prepare("SELECT id FROM users WHERE email = ? AND role IN ('student', 'new_student')");
        $stmt->bind_param("s", $customer_email);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result->num_rows > 0) {
            $user = $result->fetch_assoc();
            $student_id = intval($user['id']);
        }
        $stmt->close();
    }
    
    // Also check metadata for student_id
    if (!$student_id && isset($metadata['student_id'])) {
        $student_id = intval($metadata['student_id']);
    }
    
    if (!$student_id) {
        error_log("Stripe webhook: Could not find student for plan payment. Email: $customer_email");
        return;
    }
    
    $walletService = new WalletService($conn);
    
    // Add funds to wallet
    $reference_id = isset($metadata['plan_id']) ? 'plan_' . $metadata['plan_id'] : null;
    if ($walletService->addFunds($student_id, $amount_total, $session_id, $reference_id)) {
        // Update user role if needed
        $stmt = $conn->prepare("SELECT role, has_purchased_class FROM users WHERE id = ?");
        $stmt->bind_param("i", $student_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $user = $result->fetch_assoc();
        $stmt->close();
        
        if ($user && ($user['role'] === 'visitor' || $user['role'] === 'new_student' || !$user['has_purchased_class'])) {
            $now = date('Y-m-d H:i:s');
            $update_stmt = $conn->prepare("UPDATE users SET role = 'student', has_purchased_class = TRUE, first_purchase_date = ? WHERE id = ?");
            $update_stmt->bind_param("si", $now, $student_id);
            $update_stmt->execute();
            $update_stmt->close();
        }
        
        // Update plan_id if provided
        if (isset($metadata['plan_id'])) {
            $plan_id = intval($metadata['plan_id']);
            $col_check = $conn->query("SHOW COLUMNS FROM users LIKE 'plan_id'");
            if ($col_check && $col_check->num_rows > 0) {
                $plan_stmt = $conn->prepare("UPDATE users SET plan_id = ? WHERE id = ?");
                $plan_stmt->bind_param("ii", $plan_id, $student_id);
                $plan_stmt->execute();
                $plan_stmt->close();
            }
        }
        
        error_log("Stripe webhook: Plan payment processed for student $student_id, amount: $" . number_format($amount_total, 2));
    } else {
        error_log("Stripe webhook: Failed to add funds for student $student_id");
    }
}

/**
 * Handle payment_intent.succeeded event
 */
function handlePaymentSucceeded($conn, $payment_intent) {
    // This is a backup handler in case checkout.session.completed doesn't fire
    // Most logic is in handleCheckoutCompleted
    error_log("Stripe webhook: payment_intent.succeeded received (ID: " . ($payment_intent['id'] ?? 'unknown') . ")");
}

/**
 * Handle charge.succeeded event
 */
function handleChargeSucceeded($conn, $charge) {
    // This is another backup handler
    error_log("Stripe webhook: charge.succeeded received (ID: " . ($charge['id'] ?? 'unknown') . ")");
}

