<?php
// Start output buffering to prevent headers already sent errors
ob_start();
session_start();
require_once 'config.php';
require_once 'db.php';

// TEST STUDENT BYPASS: Check if user is the test student
$test_student_email = 'student@statenacademy.com';
$is_test_student = false;

if (isset($_SESSION['user_id'])) {
    $stmt = $conn->prepare("SELECT email FROM users WHERE id = ?");
    $stmt->bind_param("i", $_SESSION['user_id']);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows > 0) {
        $user = $result->fetch_assoc();
        if ($user['email'] === $test_student_email) {
            $is_test_student = true;
        }
    }
    $stmt->close();
}

// If test student, bypass payment and redirect to success
if ($is_test_student) {
    // Get plan_id if provided (from POST or GET)
    $plan_id = isset($_POST['plan_id']) ? (int)$_POST['plan_id'] : (isset($_GET['plan_id']) ? (int)$_GET['plan_id'] : null);
    $track = isset($_POST['track']) ? $_POST['track'] : (isset($_GET['track']) ? $_GET['track'] : null);
    
    // Update user role if needed
    $user_id = $_SESSION['user_id'];
    $stmt = $conn->prepare("SELECT role, has_purchased_class FROM users WHERE id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();
    $stmt->close();
    
    if ($user && ($user['role'] === 'visitor' || $user['role'] === 'new_student' || !$user['has_purchased_class'])) {
        // Upgrade to student
        $now = date('Y-m-d H:i:s');
        $update_stmt = $conn->prepare("UPDATE users SET role = 'student', has_purchased_class = TRUE, first_purchase_date = ? WHERE id = ?");
        $update_stmt->bind_param("si", $now, $user_id);
        $update_stmt->execute();
        $update_stmt->close();
        
        // Update session
        $_SESSION['user_role'] = 'student';
    }
    
    // Update plan_id if provided and column exists
    if ($plan_id) {
        $col_check = $conn->query("SHOW COLUMNS FROM users LIKE 'plan_id'");
        if ($col_check && $col_check->num_rows > 0) {
            $plan_stmt = $conn->prepare("UPDATE users SET plan_id = ? WHERE id = ?");
            $plan_stmt->bind_param("ii", $plan_id, $user_id);
            $plan_stmt->execute();
            $plan_stmt->close();
        }
    }
    
    // Update track if provided
    if ($track && in_array($track, ['kids', 'adults', 'coding'])) {
        $track_stmt = $conn->prepare("UPDATE users SET learning_track = ? WHERE id = ?");
        $track_stmt->bind_param("si", $track, $user_id);
        $track_stmt->execute();
        $track_stmt->close();
    }
    
    // Store plan_id and track in session for success page
    if ($plan_id) {
        $_SESSION['selected_plan_id'] = $plan_id;
    }
    if ($track) {
        $_SESSION['selected_track'] = $track;
    }
    
    // Redirect to success page
    ob_end_clean(); // Clear output buffer before redirect
    $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http";
    $domain = $protocol . "://" . $_SERVER['HTTP_HOST'] . dirname($_SERVER['PHP_SELF']);
    if (strpos($domain, ' ') !== false) {
        $domain = str_replace(' ', '%20', $domain);
    }
    header("Location: " . $domain . "/success.php?test_student=1" . ($plan_id ? "&plan_id=" . $plan_id : "") . ($track ? "&track=" . urlencode($track) : ""));
    exit;
}

// Check if Stripe API key is configured
if (!defined('STRIPE_SECRET_KEY') || empty(STRIPE_SECRET_KEY) || strpos(STRIPE_SECRET_KEY, 'YOUR_') === 0 || (strpos(STRIPE_SECRET_KEY, 'sk_test_') !== 0 && strpos(STRIPE_SECRET_KEY, 'sk_live_') !== 0)) {
    die("Error: Stripe API key is not configured. Please set STRIPE_SECRET_KEY in env.php with a valid Stripe secret key (sk_test_... or sk_live_...).");
}

// Check if this is a trial lesson checkout
$is_trial = (isset($_GET['type']) && $_GET['type'] === 'trial') || (isset($_POST['type']) && $_POST['type'] === 'trial');
$teacher_id = null;

if ($is_trial) {
    // Trial lesson checkout
    require_once __DIR__ . '/app/Services/TrialService.php';
    
    if (!isset($_SESSION['user_id'])) {
        die("Error: You must be logged in to book a trial lesson.");
    }
    
    $student_id = $_SESSION['user_id'];
    $teacher_id = isset($_GET['teacher_id']) ? intval($_GET['teacher_id']) : (isset($_POST['teacher_id']) ? intval($_POST['teacher_id']) : 0);
    
    if (!$teacher_id) {
        die("Error: Teacher ID is required for trial lesson.");
    }
    
    // Check trial eligibility
    $trialService = new TrialService($conn);
    $eligibility = $trialService->checkTrialEligibility($student_id);
    
    if (!$eligibility['eligible']) {
        die("Error: " . $eligibility['reason']);
    }
    
    // Get trial price ID from env or use default
    $priceId = defined('STRIPE_PRODUCT_TRIAL') && !empty(STRIPE_PRODUCT_TRIAL) 
        ? STRIPE_PRODUCT_TRIAL 
        : null;
    
    if (!$priceId) {
        // Create a one-time price for $25 if not configured
        // For now, we'll require it to be set in env.php
        die("Error: STRIPE_PRODUCT_TRIAL is not configured in env.php. Please set a Stripe Price ID for trial lessons ($25).");
    }
    
    $mode = 'payment';
    $plan_id = null;
    $track = null;
    
    // Store trial info in session
    $_SESSION['trial_teacher_id'] = $teacher_id;
    $_SESSION['trial_student_id'] = $student_id;
} else {
    // Regular plan checkout
    if (!isset($_POST['price_id'])) {
        die("Error: No Price ID provided.");
    }
    
    $priceId = $_POST['price_id'];
    $mode = isset($_POST['mode']) ? $_POST['mode'] : 'payment';
    $plan_id = isset($_POST['plan_id']) ? (int)$_POST['plan_id'] : null;
    $track = isset($_POST['track']) ? $_POST['track'] : null;
}

// Store plan_id and track in session for success page
if ($plan_id) {
    $_SESSION['selected_plan_id'] = $plan_id;
}
if ($track && in_array($track, ['kids', 'adults', 'coding'])) {
    $_SESSION['selected_track'] = $track;
}

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

// Build success URL
$success_params = [];
if ($is_trial) {
    $success_params[] = 'type=trial';
    $success_params[] = 'teacher_id=' . $teacher_id;
} else {
    if ($plan_id) {
        $success_params[] = 'plan_id=' . $plan_id;
    }
    if ($track) {
        $success_params[] = 'track=' . urlencode($track);
    }
}
$success_url = $domain . '/success.php' . (!empty($success_params) ? '?' . implode('&', $success_params) : '');

// Data for the request
$data = [
    'line_items' => [
        [
            'price' => $priceId,
            'quantity' => 1,
        ],
    ],
    'mode' => $mode, // 'subscription' for recurring, 'payment' for one-time
    'success_url' => $success_url,
    'cancel_url' => $domain . '/cancel.php',
];

// Add metadata for trial lessons
if ($is_trial) {
    $data['metadata'] = [
        'type' => 'trial',
        'teacher_id' => (string)$teacher_id,
        'student_id' => (string)$_SESSION['user_id']
    ];
}

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
    $error_message = "Error creating checkout session: ";
    if (isset($session['error']['message'])) {
        $error_message .= $session['error']['message'];
        
        // Provide helpful guidance for common errors
        if (isset($session['error']['type']) && $session['error']['type'] === 'invalid_request_error') {
            if (strpos($session['error']['message'], 'Invalid API Key') !== false) {
                $error_message .= "\n\nPlease check your STRIPE_SECRET_KEY in env.php. Make sure it's a valid Stripe secret key (starts with sk_test_ for test mode or sk_live_ for live mode).";
            }
        }
    } else {
        $error_message .= "HTTP Error " . $http_code . ". Please check your Stripe API configuration.";
    }
    
    // In production, log the error but show user-friendly message
    if (defined('APP_DEBUG') && APP_DEBUG === true) {
        error_log("Stripe API Error: " . json_encode($session));
        echo $error_message;
    } else {
        echo "Error processing payment. Please contact support if this issue persists.";
        error_log("Stripe API Error: " . json_encode($session));
    }
    exit;
}

// Redirect to Stripe Checkout
if (isset($session['url'])) {
    ob_end_clean(); // Clear output buffer before redirect
    header("Location: " . $session['url']);
    exit;
} else {
    ob_end_clean(); // Clear output buffer before error
    die("Error: No checkout URL returned from Stripe.");
}
