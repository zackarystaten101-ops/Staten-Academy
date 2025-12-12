<?php
header('Content-Type: application/json');
session_start();
require_once 'db.php';
require_once 'google-calendar-config.php';
require_once __DIR__ . '/app/Services/TeacherService.php';
require_once __DIR__ . '/app/Services/WalletService.php';
require_once __DIR__ . '/app/Services/TrialService.php';

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit();
}

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Not authenticated']);
    exit();
}

// Allow students and new_students to book lessons
if ($_SESSION['user_role'] !== 'student' && $_SESSION['user_role'] !== 'new_student') {
    http_response_code(403);
    echo json_encode(['error' => 'Only students can book lessons.']);
    exit();
}

$student_id = $_SESSION['user_id'];

// Get JSON input
$input = json_decode(file_get_contents('php://input'), true);

if (!isset($input['teacher_id']) || !isset($input['lesson_date']) || !isset($input['start_time']) || !isset($input['end_time'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Missing required fields']);
    exit();
}

$teacher_id = (int)$input['teacher_id'];
$lesson_date = $input['lesson_date'];
$start_time = $input['start_time'];
$end_time = $input['end_time'];

// Validate date and time format
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $lesson_date) || 
    !preg_match('/^\d{2}:\d{2}$/', $start_time) || 
    !preg_match('/^\d{2}:\d{2}$/', $end_time)) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid date/time format']);
    exit();
}

// Check if date is in the future
if (strtotime($lesson_date . ' ' . $start_time) <= time()) {
    http_response_code(400);
    echo json_encode(['error' => 'Cannot book past dates']);
    exit();
}

// Initialize services
$teacherService = new TeacherService($conn);
$walletService = new WalletService($conn);
$trialService = new TrialService($conn);
$api = new GoogleCalendarAPI($conn);

// Check if teacher exists and is in student's category (if student has a category)
$teacher = $teacherService->getTeacherProfile($teacher_id);
if (!$teacher || $teacher['role'] !== 'teacher') {
    http_response_code(404);
    echo json_encode(['error' => 'Teacher not found']);
    exit();
}

// Check if student has a preferred category and teacher is in that category
$student_stmt = $conn->prepare("SELECT preferred_category FROM users WHERE id = ?");
$student_stmt->bind_param("i", $student_id);
$student_stmt->execute();
$student_result = $student_stmt->get_result();
$student_data = $student_result->fetch_assoc();
$student_stmt->close();

if ($student_data && !empty($student_data['preferred_category'])) {
    $student_category = $student_data['preferred_category'];
    $teacher_categories = $teacher['categories'] ?? [];
    if (!in_array($student_category, $teacher_categories)) {
        http_response_code(403);
        echo json_encode(['error' => 'This teacher is not available in your selected category.']);
        exit();
    }
}

// Check slot availability using TeacherService
if (!$teacherService->checkSlotAvailability($teacher_id, $lesson_date, $start_time)) {
    http_response_code(409);
    echo json_encode(['error' => 'This time slot is not available or has already been booked.']);
    exit();
}

// Check for double-booking (conflicting lessons)
$conflict_check = $conn->prepare("
    SELECT id FROM lessons 
    WHERE teacher_id = ? 
    AND lesson_date = ? 
    AND start_time = ? 
    AND status != 'cancelled'
    LIMIT 1
");
$conflict_check->bind_param("iss", $teacher_id, $lesson_date, $start_time);
$conflict_check->execute();
$conflict_result = $conflict_check->get_result();
if ($conflict_result->num_rows > 0) {
    $conflict_check->close();
    http_response_code(409);
    echo json_encode(['error' => 'This time slot has already been booked by another student.']);
    exit();
}
$conflict_check->close();

// Check if this is a trial lesson
$is_trial = false;
$eligibility = $trialService->checkTrialEligibility($student_id);
if ($eligibility['eligible']) {
    // Check if student has trial credit in wallet
    $wallet = $walletService->getWalletBalance($student_id);
    if ($wallet['trial_credits'] > 0) {
        $is_trial = true;
    }
}

// Calculate lesson cost (use teacher's hourly rate or default)
$lesson_cost = 0.00;
if ($is_trial) {
    $lesson_cost = 0.00; // Trial is already paid
} else {
    // Get teacher's hourly rate or use default
    $teacher_rate = floatval($teacher['hourly_rate'] ?? 0);
    if ($teacher_rate > 0) {
        // Calculate duration in hours
        $start_timestamp = strtotime($lesson_date . ' ' . $start_time);
        $end_timestamp = strtotime($lesson_date . ' ' . $end_time);
        $duration_hours = ($end_timestamp - $start_timestamp) / 3600;
        $lesson_cost = $teacher_rate * $duration_hours;
    } else {
        // Default cost if no rate set
        $lesson_cost = 50.00; // Default 1-hour lesson cost
    }
    
    // Check wallet balance
    $wallet = $walletService->getWalletBalance($student_id);
    if ($wallet['balance'] < $lesson_cost) {
        http_response_code(402);
        echo json_encode([
            'error' => 'Insufficient wallet balance',
            'required' => $lesson_cost,
            'available' => $wallet['balance']
        ]);
        exit();
    }
}

// Get student info
$stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
$stmt->bind_param("i", $student_id);
$stmt->execute();
$student = $stmt->get_result()->fetch_assoc();
$stmt->close();

// Get student's category for lesson record
$student_category = $student_data['preferred_category'] ?? null;

// Start transaction for booking
$conn->begin_transaction();

try {
    // Deduct funds if not trial
    $wallet_transaction_id = null;
    if (!$is_trial && $lesson_cost > 0) {
        $reference_id = 'lesson_' . time() . '_' . $student_id;
        if (!$walletService->deductFunds($student_id, $lesson_cost, $reference_id, "Lesson booking with " . $teacher['name'])) {
            throw new Exception("Failed to deduct funds from wallet");
        }
        
        // Get the transaction ID - this must exist after a successful deduction
        $txn_stmt = $conn->prepare("SELECT id FROM wallet_transactions WHERE reference_id = ? ORDER BY id DESC LIMIT 1");
        $txn_stmt->bind_param("s", $reference_id);
        $txn_stmt->execute();
        $txn_result = $txn_stmt->get_result();
        if ($txn_result->num_rows > 0) {
            $txn_data = $txn_result->fetch_assoc();
            $wallet_transaction_id = $txn_data['id'];
        } else {
            // Critical error: funds were deducted but transaction record not found
            throw new Exception("Funds deducted but transaction record not found. Reference ID: " . $reference_id);
        }
        $txn_stmt->close();
    } elseif ($is_trial) {
        // Deduct trial credit
        $update_trial = $conn->prepare("UPDATE student_wallet SET trial_credits = trial_credits - 1 WHERE student_id = ? AND trial_credits > 0");
        $update_trial->bind_param("i", $student_id);
        if (!$update_trial->execute() || $update_trial->affected_rows === 0) {
            throw new Exception("Failed to deduct trial credit");
        }
        $update_trial->close();
    }
    
    // Create lesson record in database
    $google_event_id = null;
    // Handle NULL wallet_transaction_id for trial lessons
    if ($wallet_transaction_id === null) {
        $stmt = $conn->prepare("
            INSERT INTO lessons (teacher_id, student_id, lesson_date, start_time, end_time, status, is_trial, wallet_transaction_id, category)
            VALUES (?, ?, ?, ?, ?, 'scheduled', ?, NULL, ?)
        ");
        $is_trial_int = $is_trial ? 1 : 0;
        $stmt->bind_param("iisssis", $teacher_id, $student_id, $lesson_date, $start_time, $end_time, $is_trial_int, $student_category);
    } else {
        $stmt = $conn->prepare("
            INSERT INTO lessons (teacher_id, student_id, lesson_date, start_time, end_time, status, is_trial, wallet_transaction_id, category)
            VALUES (?, ?, ?, ?, ?, 'scheduled', ?, ?, ?)
        ");
        $is_trial_int = $is_trial ? 1 : 0;
        $stmt->bind_param("iisssiis", $teacher_id, $student_id, $lesson_date, $start_time, $end_time, $is_trial_int, $wallet_transaction_id, $student_category);
    }

    if (!$stmt->execute()) {
        throw new Exception("Failed to create lesson: " . $stmt->error);
    }
    
    $lesson_id = $stmt->insert_id;
    $stmt->close();
    
    // If trial, update trial_lessons table
    if ($is_trial) {
        // Get stripe payment id from trial_lessons
        $trial_stmt = $conn->prepare("SELECT stripe_payment_id FROM trial_lessons WHERE student_id = ? ORDER BY created_at DESC LIMIT 1");
        $trial_stmt->bind_param("i", $student_id);
        $trial_stmt->execute();
        $trial_result = $trial_stmt->get_result();
        $stripe_payment_id = null;
        if ($trial_result->num_rows > 0) {
            $trial_data = $trial_result->fetch_assoc();
            $stripe_payment_id = $trial_data['stripe_payment_id'];
        }
        $trial_stmt->close();
        
        // Update trial_lessons with lesson_id
        if ($stripe_payment_id) {
            $update_trial = $conn->prepare("UPDATE trial_lessons SET lesson_id = ?, used_at = NOW() WHERE student_id = ? AND stripe_payment_id = ?");
            $update_trial->bind_param("iis", $lesson_id, $student_id, $stripe_payment_id);
            $update_trial->execute();
            $update_trial->close();
        }
    }
    
    // Commit transaction
    $conn->commit();
    
} catch (Exception $e) {
    // Rollback transaction
    $conn->rollback();
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
    exit();
}

// Also create a booking record for compatibility
$booking_stmt = $conn->prepare("INSERT IGNORE INTO bookings (student_id, teacher_id, booking_date) VALUES (?, ?, ?)");
$booking_date = date('Y-m-d H:i:s');
$booking_stmt->bind_param("iis", $student_id, $teacher_id, $booking_date);
$booking_stmt->execute();
$booking_stmt->close();

// Try to create Google Calendar event if teacher has connected calendar
if (!empty($teacher['google_calendar_token'])) {
    // Check if token has expired and refresh if needed
    if (!empty($teacher['google_calendar_token_expiry'])) {
        if (strtotime($teacher['google_calendar_token_expiry']) <= time()) {
            if (!empty($teacher['google_calendar_refresh_token'])) {
                $token_response = $api->refreshAccessToken($teacher['google_calendar_refresh_token']);
                if (!isset($token_response['error'])) {
                    $teacher['google_calendar_token'] = $token_response['access_token'];
                    // Update in database
                    $new_expiry = date('Y-m-d H:i:s', time() + ($token_response['expires_in'] ?? 3600));
                    $stmt = $conn->prepare("UPDATE users SET google_calendar_token = ?, google_calendar_token_expiry = ? WHERE id = ?");
                    $stmt->bind_param("ssi", $teacher['google_calendar_token'], $new_expiry, $teacher_id);
                    $stmt->execute();
                    $stmt->close();
                }
            }
        }
    }

    // Create calendar event
    $start_datetime = $lesson_date . 'T' . $start_time . ':00';
    $end_datetime = $lesson_date . 'T' . $end_time . ':00';
    
    $event_data = [
        'title' => 'Lesson: ' . htmlspecialchars($student['name']),
        'description' => 'Student: ' . htmlspecialchars($student['name']) . ' (' . htmlspecialchars($student['email']) . ')' . ($is_trial ? ' [Trial Lesson]' : ''),
        'start_datetime' => $start_datetime,
        'end_datetime' => $end_datetime
    ];

    $calendar_result = $api->createEvent($teacher['google_calendar_token'], $event_data);
    
    if (isset($calendar_result['id'])) {
        $google_event_id = $calendar_result['id'];
        
        // Update lesson with Google Calendar event ID
        $stmt = $conn->prepare("UPDATE lessons SET google_calendar_event_id = ? WHERE id = ?");
        $stmt->bind_param("si", $google_event_id, $lesson_id);
        $stmt->execute();
        $stmt->close();
    }
}

// Create Google Calendar event in student's calendar if connected
if (!empty($student['google_calendar_token'])) {
    // Refresh token if expired
    if (!empty($student['google_calendar_token_expiry']) && strtotime($student['google_calendar_token_expiry']) <= time()) {
        if (!empty($student['google_calendar_refresh_token'])) {
            $token_response = $api->refreshAccessToken($student['google_calendar_refresh_token']);
            if (!isset($token_response['error'])) {
                $student['google_calendar_token'] = $token_response['access_token'];
                $new_expiry = date('Y-m-d H:i:s', time() + ($token_response['expires_in'] ?? 3600));
                $stmt = $conn->prepare("UPDATE users SET google_calendar_token = ?, google_calendar_token_expiry = ? WHERE id = ?");
                $stmt->bind_param("ssi", $student['google_calendar_token'], $new_expiry, $student_id);
                $stmt->execute();
                $stmt->close();
            }
        }
    }

    $start_datetime = $lesson_date . 'T' . $start_time . ':00';
    $end_datetime = $lesson_date . 'T' . $end_time . ':00';
    
    $student_event_data = [
        'title' => 'Lesson with ' . htmlspecialchars($teacher['name']),
        'description' => 'Teacher: ' . htmlspecialchars($teacher['name']) . ' (' . htmlspecialchars($teacher['email']) . ')' . ($is_trial ? ' [Trial Lesson]' : ''),
        'start_datetime' => $start_datetime,
        'end_datetime' => $end_datetime,
        'attendees' => [['email' => $teacher['email']]]
    ];

    $student_calendar_result = $api->createEvent($student['google_calendar_token'], $student_event_data);
    
    // Note: Student's event is created in their calendar
}

// Return success response
http_response_code(201);
echo json_encode([
    'success' => true,
    'lesson_id' => $lesson_id,
    'google_calendar_event_id' => $google_event_id,
    'is_trial' => $is_trial,
    'message' => $is_trial ? 'Trial lesson booked successfully!' : 'Lesson booked successfully!'
]);
exit();
?>
