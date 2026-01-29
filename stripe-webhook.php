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
require_once __DIR__ . '/app/Services/CreditService.php';
require_once __DIR__ . '/app/Services/SubscriptionService.php';

// Load Stripe PHP library if available
if (file_exists(__DIR__ . '/vendor/autoload.php')) {
    require_once __DIR__ . '/vendor/autoload.php';
}

// Get webhook secret
$webhook_secret = defined('STRIPE_WEBHOOK_SECRET') ? STRIPE_WEBHOOK_SECRET : '';

// Get raw POST data
$payload = @file_get_contents('php://input');
$sig_header = $_SERVER['HTTP_STRIPE_SIGNATURE'] ?? '';

// Verify webhook signature
if (!empty($webhook_secret)) {
    // Check if Stripe library is available
    if (class_exists('\Stripe\Webhook')) {
        try {
            $event = \Stripe\Webhook::constructEvent($payload, $sig_header, $webhook_secret);
        } catch (\Exception $e) {
            http_response_code(400);
            echo json_encode(['error' => 'Webhook signature verification failed: ' . $e->getMessage()]);
            exit;
        }
    } else {
        // If Stripe library not available, decode without verification (not recommended for production)
        error_log("Stripe PHP library not found - webhook verification skipped");
        $event = json_decode($payload, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            http_response_code(400);
            echo json_encode(['error' => 'Invalid JSON payload']);
            exit;
        }
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
    
    case 'invoice.payment_succeeded':
        handleInvoicePaymentSucceeded($conn, $event_data);
        break;
    
    case 'invoice.payment_failed':
        handleInvoicePaymentFailed($conn, $event_data);
        break;
    
    case 'customer.subscription.updated':
        handleSubscriptionUpdated($conn, $event_data);
        break;
    
    case 'customer.subscription.deleted':
        handleSubscriptionDeleted($conn, $event_data);
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
    $subscription_id = $session['subscription'] ?? null; // For subscription mode
    $customer_id = $session['customer'] ?? null; // Stripe customer ID
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
        handlePlanPayment($conn, $session_id, $payment_intent_id, $metadata, $amount_total, $customer_email, $subscription_id, $customer_id);
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
function handlePlanPayment($conn, $session_id, $payment_intent_id, $metadata, $amount_total, $customer_email, $subscription_id = null, $customer_id = null) {
    // Find student by email or metadata
    $student_id = null;
    if (!empty($customer_email)) {
        $stmt = $conn->prepare("SELECT id, stripe_customer_id FROM users WHERE email = ? AND role IN ('student', 'new_student')");
        $stmt->bind_param("s", $customer_email);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result->num_rows > 0) {
            $user = $result->fetch_assoc();
            $student_id = intval($user['id']);
            $stripe_customer_id = $user['stripe_customer_id'];
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
    
    // Determine payment type from metadata
    $payment_type = $metadata['type'] ?? 'plan';
    $plan_id = isset($metadata['plan_id']) ? intval($metadata['plan_id']) : null;
    
    $creditService = new CreditService($conn);
    $subscriptionService = new SubscriptionService($conn);
    
    // Handle gift credits
    if ($payment_type === 'gift_credit') {
        $recipient_email = $metadata['recipient_email'] ?? '';
        $credits_amount = isset($metadata['credits_amount']) ? intval($metadata['credits_amount']) : 0;
        
        if (!empty($recipient_email) && $credits_amount > 0) {
            if ($creditService->transferCreditsGift($student_id, $recipient_email, $credits_amount, $session_id)) {
                error_log("Stripe webhook: Gift credit purchase processed for student $student_id, recipient: $recipient_email, credits: $credits_amount");
            } else {
                error_log("Stripe webhook: Failed to process gift credit purchase for student $student_id");
            }
        }
        return;
    }
    
    // Get plan details if plan_id provided
    $credits_to_add = 0;
    $plan_type = 'subscription';
    if ($plan_id) {
        $plan_stmt = $conn->prepare("SELECT type, credits_included FROM subscription_plans WHERE id = ?");
        $plan_stmt->bind_param("i", $plan_id);
        $plan_stmt->execute();
        $plan_result = $plan_stmt->get_result();
        if ($plan_result->num_rows > 0) {
            $plan = $plan_result->fetch_assoc();
            $plan_type = $plan['type'] ?? 'subscription';
            $credits_to_add = intval($plan['credits_included'] ?? 0);
        }
        $plan_stmt->close();
    }
    
    // Handle subscriptions - store customer ID and subscription ID
    // Use subscription_id from session if not in metadata
    if (empty($subscription_id)) {
        $subscription_id = $metadata['subscription_id'] ?? null;
    }
    // Use customer_id from session if not in metadata
    if (empty($customer_id)) {
        $customer_id = $metadata['customer_id'] ?? null;
    }
    
    if ($plan_type === 'subscription' && !empty($subscription_id)) {
        // Get billing cycle date from current date
        $billing_cycle_date = (int)date('d'); // Day of month (1-31)
        
        // Store Stripe customer ID if not set
        if (!empty($customer_id)) {
            $update_customer = $conn->prepare("UPDATE users SET stripe_customer_id = ? WHERE id = ?");
            $customer_id_str = (string)$customer_id;
            $update_customer->bind_param("si", $customer_id_str, $student_id);
            $update_customer->execute();
            $update_customer->close();
        }
        
        // Update subscription info
        $subscriptionService->updateSubscription($student_id, $subscription_id, $billing_cycle_date);
        
        // Update plan_id and learning_track for Group Classes (always 'kids')
        if ($plan_id) {
            $col_check = $conn->query("SHOW COLUMNS FROM users LIKE 'plan_id'");
            if ($col_check && $col_check->num_rows > 0) {
                $update_plan = $conn->prepare("UPDATE users SET plan_id = ?, learning_track = 'kids' WHERE id = ?");
                $update_plan->bind_param("ii", $plan_id, $student_id);
                $update_plan->execute();
                $update_plan->close();
            } else {
                // If plan_id column doesn't exist, just update track
                $update_track = $conn->prepare("UPDATE users SET learning_track = 'kids' WHERE id = ?");
                $update_track->bind_param("i", $student_id);
                $update_track->execute();
                $update_track->close();
            }
        } else {
            // Ensure track is set to 'kids' even if no plan_id
            $update_track = $conn->prepare("UPDATE users SET learning_track = 'kids' WHERE id = ?");
            $update_track->bind_param("i", $student_id);
            $update_track->execute();
            $update_track->close();
        }
        
        // Add initial credits for subscription
        if ($credits_to_add > 0) {
            $description = "Subscription activated - " . ($plan_id ? "Plan #$plan_id" : "Initial subscription");
            $creditService->addCredits($student_id, $credits_to_add, 'subscription_renewal', $description, $subscription_id);
        }
    } 
    // Handle packages (one-time payments)
    elseif ($plan_type === 'package' && $credits_to_add > 0) {
        $description = "Package purchased - Plan #$plan_id";
        $creditService->addCredits($student_id, $credits_to_add, 'purchase', $description, $session_id);
    }
    // Handle add-ons
    elseif ($plan_type === 'addon' && $credits_to_add > 0) {
        $description = "Add-on purchased - Plan #$plan_id";
        $creditService->addCredits($student_id, $credits_to_add, 'purchase', $description, $session_id);
    }
    
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
    
    // Update plan_id and learning_track (always 'kids' for Group Classes)
    // This is handled above in the subscription section, but also handle here for packages/addons
    if ($plan_id) {
        $col_check = $conn->query("SHOW COLUMNS FROM users LIKE 'plan_id'");
        if ($col_check && $col_check->num_rows > 0) {
            $plan_stmt = $conn->prepare("UPDATE users SET plan_id = ?, learning_track = 'kids' WHERE id = ?");
            $plan_stmt->bind_param("ii", $plan_id, $student_id);
            $plan_stmt->execute();
            $plan_stmt->close();
        } else {
            // If plan_id column doesn't exist, just update track
            $update_track = $conn->prepare("UPDATE users SET learning_track = 'kids' WHERE id = ?");
            $update_track->bind_param("i", $student_id);
            $update_track->execute();
            $update_track->close();
        }
    } else {
        // Ensure track is set to 'kids' even if no plan_id
        $update_track = $conn->prepare("UPDATE users SET learning_track = 'kids' WHERE id = ?");
        $update_track->bind_param("i", $student_id);
        $update_track->execute();
        $update_track->close();
    }
    
    error_log("Stripe webhook: Plan payment processed for student $student_id, type: $plan_type, credits: $credits_to_add");
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
 * Handle invoice.payment_succeeded event (subscription renewal)
 */
function handleInvoicePaymentSucceeded($conn, $invoice) {
    $subscription_id = $invoice['subscription'] ?? null;
    $customer_id = $invoice['customer'] ?? null;
    
    if (empty($subscription_id) || empty($customer_id)) {
        error_log("Stripe webhook: invoice.payment_succeeded missing subscription_id or customer_id");
        return;
    }
    
    // Find student by Stripe customer ID
    $stmt = $conn->prepare("SELECT id FROM users WHERE stripe_customer_id = ?");
    $stmt->bind_param("s", $customer_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        $stmt->close();
        error_log("Stripe webhook: invoice.payment_succeeded - customer not found: $customer_id");
        return;
    }
    
    $user = $result->fetch_assoc();
    $student_id = intval($user['id']);
    $stmt->close();
    
    // Process monthly credit reset
    $subscriptionService = new SubscriptionService($conn);
    if ($subscriptionService->processMonthlyCreditReset($student_id, $subscription_id)) {
        error_log("Stripe webhook: Monthly credit reset processed for student $student_id, subscription $subscription_id");
    } else {
        error_log("Stripe webhook: Failed to process monthly credit reset for student $student_id");
    }
}

/**
 * Handle invoice.payment_failed event
 */
function handleInvoicePaymentFailed($conn, $invoice) {
    $subscription_id = $invoice['subscription'] ?? null;
    $customer_id = $invoice['customer'] ?? null;
    
    if (empty($customer_id)) {
        error_log("Stripe webhook: invoice.payment_failed missing customer_id");
        return;
    }
    
    // Find student by Stripe customer ID
    $stmt = $conn->prepare("SELECT id, email, name FROM users WHERE stripe_customer_id = ?");
    $stmt->bind_param("s", $customer_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        $stmt->close();
        error_log("Stripe webhook: invoice.payment_failed - customer not found: $customer_id");
        return;
    }
    
    $user = $result->fetch_assoc();
    $student_id = intval($user['id']);
    $stmt->close();
    
    // Set payment failed flag
    $update_stmt = $conn->prepare("UPDATE users SET subscription_payment_failed = TRUE WHERE id = ?");
    $update_stmt->bind_param("i", $student_id);
    $update_stmt->execute();
    $update_stmt->close();
    
    // TODO: Send notification email to user
    // For now, just log it
    error_log("Stripe webhook: Payment failed for student $student_id (email: " . ($user['email'] ?? 'unknown') . ")");
}

/**
 * Handle customer.subscription.updated event
 */
function handleSubscriptionUpdated($conn, $subscription) {
    $subscription_id = $subscription['id'] ?? null;
    $customer_id = $subscription['customer'] ?? null;
    $status = $subscription['status'] ?? null;
    
    if (empty($subscription_id) || empty($customer_id)) {
        error_log("Stripe webhook: customer.subscription.updated missing subscription_id or customer_id");
        return;
    }
    
    // Find student by Stripe customer ID
    $stmt = $conn->prepare("SELECT id FROM users WHERE stripe_customer_id = ?");
    $stmt->bind_param("s", $customer_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        $stmt->close();
        error_log("Stripe webhook: customer.subscription.updated - customer not found: $customer_id");
        return;
    }
    
    $user = $result->fetch_assoc();
    $student_id = intval($user['id']);
    $stmt->close();
    
    // Update subscription status
    $subscription_status = 'active';
    if ($status === 'canceled' || $status === 'unpaid' || $status === 'past_due') {
        $subscription_status = 'cancelled';
    }
    
    $update_stmt = $conn->prepare("UPDATE users SET active_subscription_id = ?, subscription_status = ? WHERE id = ?");
    $update_stmt->bind_param("ssi", $subscription_id, $subscription_status, $student_id);
    $update_stmt->execute();
    $update_stmt->close();
    
    error_log("Stripe webhook: Subscription updated for student $student_id, status: $status");
}

/**
 * Handle customer.subscription.deleted event
 */
function handleSubscriptionDeleted($conn, $subscription) {
    $subscription_id = $subscription['id'] ?? null;
    $customer_id = $subscription['customer'] ?? null;
    
    if (empty($customer_id)) {
        error_log("Stripe webhook: customer.subscription.deleted missing customer_id");
        return;
    }
    
    // Find student by Stripe customer ID
    $stmt = $conn->prepare("SELECT id FROM users WHERE stripe_customer_id = ?");
    $stmt->bind_param("s", $customer_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        $stmt->close();
        error_log("Stripe webhook: customer.subscription.deleted - customer not found: $customer_id");
        return;
    }
    
    $user = $result->fetch_assoc();
    $student_id = intval($user['id']);
    $stmt->close();
    
    // Cancel subscription (but keep existing credits)
    $subscriptionService = new SubscriptionService($conn);
    $subscriptionService->cancelSubscription($student_id);
    
    error_log("Stripe webhook: Subscription deleted for student $student_id");
}

/**
 * Handle charge.succeeded event
 */
function handleChargeSucceeded($conn, $charge) {
    // This is another backup handler
    error_log("Stripe webhook: charge.succeeded received (ID: " . ($charge['id'] ?? 'unknown') . ")");
}

