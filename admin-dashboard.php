<?php
// Start output buffering to prevent "headers already sent" errors
ob_start();

// Load environment configuration first
if (!defined('DB_HOST')) {
    require_once __DIR__ . '/env.php';
}

// Start session before any output
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/app/Views/components/dashboard-functions.php';

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
    ob_end_clean(); // Clear output buffer before redirect
    header("Location: login.php");
    exit();
}

$admin_id = $_SESSION['user_id'];
$user = getUserById($conn, $admin_id);
$user_role = 'admin';

// Initialize search/filter variables early (before any HTML output)
$user_search = $_GET['user_search'] ?? '';
$user_role_filter = $_GET['user_role_filter'] ?? '';

// Get admin stats
$admin_stats = getAdminStats($conn);

// Handle Profile Update (Admin can update directly)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
    $dob = $_POST['dob'];
    $bio = $_POST['bio'];
    $calendly = $_POST['calendly'];
    $name = $_POST['name'];
    $backup_email = filter_input(INPUT_POST, 'backup_email', FILTER_SANITIZE_EMAIL);
    $age = !empty($_POST['age']) ? (int)$_POST['age'] : NULL;
    $age_visibility = $_POST['age_visibility'] ?? 'private';
    $profile_pic = $user['profile_pic'];

    if (isset($_FILES['profile_pic_file']) && $_FILES['profile_pic_file']['error'] === UPLOAD_ERR_OK) {
        $file = $_FILES['profile_pic_file'];
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $allowed = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
        if (in_array($ext, $allowed) && $file['size'] <= 5 * 1024 * 1024) {
            $filename = 'user_' . $admin_id . '_' . time() . '.' . $ext;
            
            // Determine upload directory - works for both localhost and cPanel
            $upload_base = __DIR__;
            $public_images_dir = $upload_base . DIRECTORY_SEPARATOR . 'public' . DIRECTORY_SEPARATOR . 'assets' . DIRECTORY_SEPARATOR . 'images';
            $flat_images_dir = $upload_base . DIRECTORY_SEPARATOR . 'assets' . DIRECTORY_SEPARATOR . 'images';
            
            if (is_dir($public_images_dir)) {
                $target_dir = $public_images_dir;
            } elseif (is_dir($flat_images_dir)) {
                $target_dir = $flat_images_dir;
            } else {
                $target_dir = is_dir($upload_base . DIRECTORY_SEPARATOR . 'public') ? $public_images_dir : $flat_images_dir;
                @mkdir($target_dir, 0755, true);
            }
            
            $target_path = $target_dir . DIRECTORY_SEPARATOR . $filename;
            if (move_uploaded_file($file['tmp_name'], $target_path)) {
                $profile_pic = '/assets/images/' . $filename;
            }
        }
    } elseif (!empty($_POST['profile_pic_url'])) {
        $profile_pic = $_POST['profile_pic_url'];
    }

    $stmt = $conn->prepare("UPDATE users SET name = ?, dob = ?, bio = ?, calendly_link = ?, profile_pic = ?, backup_email = ?, age = ?, age_visibility = ? WHERE id = ?");
    $stmt->bind_param("sssssisis", $name, $dob, $bio, $calendly, $profile_pic, $backup_email, $age, $age_visibility, $admin_id);
    $stmt->execute();
    $stmt->close();
    
    ob_end_clean(); // Clear output buffer before redirect
    header("Location: admin-dashboard.php#my-profile");
    exit();
}

// Handle Password Change
$password_error = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_password'])) {
    $current_password = $_POST['current_password'];
    $new_password = $_POST['new_password'];
    $confirm_password = $_POST['confirm_password'];
    
    if (!password_verify($current_password, $user['password'])) {
        $password_error = 'Current password is incorrect.';
    } elseif (strlen($new_password) < 6) {
        $password_error = 'New password must be at least 6 characters.';
    } elseif ($new_password !== $confirm_password) {
        $password_error = 'New passwords do not match.';
    } else {
        $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
        $stmt = $conn->prepare("UPDATE users SET password = ? WHERE id = ?");
        $stmt->bind_param("si", $hashed_password, $admin_id);
        $stmt->execute();
        $stmt->close();
        $password_error = 'password_changed';
    }
}

// Handle Material Upload
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['upload_material'])) {
    $title = $_POST['title'];
    $type = $_POST['type'];
    $content = $_POST['content_url'];
    
    $stmt = $conn->prepare("INSERT INTO classroom_materials (title, type, link_url, uploaded_by) VALUES (?, ?, ?, ?)");
    $stmt->bind_param("sssi", $title, $type, $content, $admin_id);
    $stmt->execute();
    $stmt->close();
    
    ob_end_clean(); // Clear output buffer before redirect
    header("Location: admin-dashboard.php#classroom");
    exit();
}

// Handle Wallet Adjustment
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['adjust_wallet'])) {
    require_once __DIR__ . '/app/Services/WalletService.php';
    
    $student_id = intval($_POST['student_id']);
    $amount = floatval($_POST['amount']);
    $reason = $_POST['reason'] ?? 'Manual adjustment by admin';
    $adjustment_type = $_POST['adjustment_type'] ?? 'adjustment'; // 'add' or 'deduct'
    
    $walletService = new WalletService($conn);
    $ip_address = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    
    $conn->begin_transaction();
    try {
        if ($adjustment_type === 'add') {
            $reference_id = 'admin_adjustment_' . time() . '_' . $student_id;
            $result = $walletService->addFunds($student_id, $amount, 'admin_adjustment', $reference_id);
        } else {
            $reference_id = 'admin_deduction_' . time() . '_' . $student_id;
            $result = $walletService->deductFunds($student_id, $amount, $reference_id, $reason);
        }
        
        if ($result) {
            // Log to audit log
            $audit_sql = "INSERT INTO admin_audit_log (admin_id, action, target_type, target_id, details, ip_address) 
                         VALUES (?, 'wallet_adjustment', 'student', ?, ?, ?)";
            $audit_stmt = $conn->prepare($audit_sql);
            $details = json_encode([
                'type' => $adjustment_type,
                'amount' => $amount,
                'reason' => $reason,
                'reference_id' => $reference_id
            ]);
            $audit_stmt->bind_param("iiss", $admin_id, $student_id, $details, $ip_address);
            $audit_stmt->execute();
            $audit_stmt->close();
            
            $conn->commit();
            $user_msg = "Wallet adjusted successfully";
        } else {
            throw new Exception("Failed to adjust wallet");
        }
    } catch (Exception $e) {
        $conn->rollback();
        $user_error = "Error: " . $e->getMessage();
    }
    
    ob_end_clean();
    header("Location: admin-dashboard.php#users");
    exit();
}

// Handle User Role Change
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_user_role'])) {
    $target_user_id = intval($_POST['user_id']);
    $new_role = $_POST['new_role'];
    $ip_address = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    
    $allowed_roles = ['student', 'teacher', 'admin'];
    if (!in_array($new_role, $allowed_roles)) {
        $user_msg = "Invalid role";
    } else {
        $conn->begin_transaction();
        try {
            $stmt = $conn->prepare("UPDATE users SET role = ? WHERE id = ?");
            $stmt->bind_param("si", $new_role, $target_user_id);
            $stmt->execute();
            $stmt->close();
            
            // Log to audit log
            $audit_sql = "INSERT INTO admin_audit_log (admin_id, action, target_type, target_id, details, ip_address) 
                         VALUES (?, 'role_change', 'user', ?, ?, ?)";
            $audit_stmt = $conn->prepare($audit_sql);
            $details = json_encode(['old_role' => 'unknown', 'new_role' => $new_role]);
            $audit_stmt->bind_param("iiss", $admin_id, $target_user_id, $details, $ip_address);
            $audit_stmt->execute();
            $audit_stmt->close();
            
            $conn->commit();
            $user_msg = "User role updated successfully";
        } catch (Exception $e) {
            $conn->rollback();
            $user_error = "Error: " . $e->getMessage();
        }
    }
    
    ob_end_clean();
    header("Location: admin-dashboard.php#users");
    exit();
}

// Handle Teacher Category Assignment
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['assign_category'])) {
    $teacher_id = intval($_POST['teacher_id']);
    $category = $_POST['category'];
    $ip_address = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    
    $allowed_categories = ['young_learners', 'adults', 'coding'];
    if (!in_array($category, $allowed_categories)) {
        $user_msg = "Invalid category";
    } else {
        $conn->begin_transaction();
        try {
            // Remove existing category assignments
            $stmt = $conn->prepare("DELETE FROM teacher_categories WHERE teacher_id = ?");
            $stmt->bind_param("i", $teacher_id);
            $stmt->execute();
            $stmt->close();
            
            // Add new category assignment
            $stmt = $conn->prepare("INSERT INTO teacher_categories (teacher_id, category) VALUES (?, ?)");
            $stmt->bind_param("is", $teacher_id, $category);
            $stmt->execute();
            $stmt->close();
            
            // Log to audit log
            $audit_sql = "INSERT INTO admin_audit_log (admin_id, action, target_type, target_id, details, ip_address) 
                         VALUES (?, 'category_assignment', 'teacher', ?, ?, ?)";
            $audit_stmt = $conn->prepare($audit_sql);
            $details = json_encode(['category' => $category]);
            $audit_stmt->bind_param("iiss", $admin_id, $teacher_id, $details, $ip_address);
            $audit_stmt->execute();
            $audit_stmt->close();
            
            $conn->commit();
            $user_msg = "Category assigned successfully";
        } catch (Exception $e) {
            $conn->rollback();
            $user_error = "Error: " . $e->getMessage();
        }
    }
    
    ob_end_clean();
    header("Location: admin-dashboard.php#users");
    exit();
}

// Handle Section Approval Management
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['manage_section_approvals'])) {
    $teacher_id = intval($_POST['teacher_id']);
    $kids_status = $_POST['kids_status'] ?? 'none';
    $adults_status = $_POST['adults_status'] ?? 'none';
    $coding_status = $_POST['coding_status'] ?? 'none';
    $ip_address = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    
    $conn->begin_transaction();
    try {
        // Map section statuses to categories
        $sections = [
            'kids' => ['status' => $kids_status, 'category' => 'young_learners'],
            'adults' => ['status' => $adults_status, 'category' => 'adults'],
            'coding' => ['status' => $coding_status, 'category' => 'coding']
        ];
        
        // Remove all existing category assignments for this teacher
        $stmt = $conn->prepare("DELETE FROM teacher_categories WHERE teacher_id = ?");
        $stmt->bind_param("i", $teacher_id);
        $stmt->execute();
        $stmt->close();
        
        // Add approved categories
        $approved_categories = [];
        foreach ($sections as $section => $data) {
            if ($data['status'] === 'approved') {
                $stmt = $conn->prepare("INSERT INTO teacher_categories (teacher_id, category, is_active) VALUES (?, ?, TRUE)");
                $stmt->bind_param("is", $teacher_id, $data['category']);
                $stmt->execute();
                $stmt->close();
                $approved_categories[] = $data['category'];
            }
        }
        
        // Log to audit log
        $audit_sql = "INSERT INTO admin_audit_log (admin_id, action, target_type, target_id, details, ip_address) 
                     VALUES (?, 'section_approval', 'teacher', ?, ?, ?)";
        $audit_stmt = $conn->prepare($audit_sql);
        $details = json_encode([
            'kids' => $kids_status,
            'adults' => $adults_status,
            'coding' => $coding_status,
            'approved_categories' => $approved_categories
        ]);
        $audit_stmt->bind_param("iiss", $admin_id, $teacher_id, $details, $ip_address);
        $audit_stmt->execute();
        $audit_stmt->close();
        
        $conn->commit();
        $user_msg = "Section approvals updated successfully";
    } catch (Exception $e) {
        $conn->rollback();
        $user_error = "Error: " . $e->getMessage();
    }
    
    ob_end_clean();
    header("Location: admin-dashboard.php#users");
    exit();
}

// Handle Plan Price Updates
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_plan_prices'])) {
    $plan_ids = $_POST['plan_ids'] ?? [];
    $plan_prices = $_POST['plan_prices'] ?? [];
    $ip_address = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    
    if (count($plan_ids) !== count($plan_prices)) {
        $user_error = "Mismatch between plan IDs and prices";
    } else {
        $conn->begin_transaction();
        try {
            $updated_count = 0;
            foreach ($plan_ids as $index => $plan_id) {
                $plan_id = intval($plan_id);
                $price = floatval($plan_prices[$index]);
                
                if ($plan_id > 0 && $price >= 0) {
                    $stmt = $conn->prepare("UPDATE subscription_plans SET price = ? WHERE id = ?");
                    $stmt->bind_param("di", $price, $plan_id);
                    $stmt->execute();
                    $stmt->close();
                    $updated_count++;
                }
            }
            
            // Log to audit log
            $audit_sql = "INSERT INTO admin_audit_log (admin_id, action, target_type, target_id, details, ip_address) 
                         VALUES (?, 'plan_price_update', 'plans', NULL, ?, ?)";
            $audit_stmt = $conn->prepare($audit_sql);
            $details = json_encode(['updated_count' => $updated_count, 'plan_ids' => $plan_ids, 'prices' => $plan_prices]);
            $audit_stmt->bind_param("iss", $admin_id, $details, $ip_address);
            $audit_stmt->execute();
            $audit_stmt->close();
            
            $conn->commit();
            $user_msg = "Updated prices for {$updated_count} plan(s) successfully";
        } catch (Exception $e) {
            $conn->rollback();
            $user_error = "Error: " . $e->getMessage();
        }
    }
    
    ob_end_clean();
    header("Location: admin-dashboard.php#settings");
    exit();
}

// Handle Teacher Salary Updates
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_teacher_salaries'])) {
    $default_commission_rate = floatval($_POST['default_commission_rate'] ?? 50);
    $teacher_ids = $_POST['teacher_ids'] ?? [];
    $commission_rates = $_POST['commission_rates'] ?? [];
    $ip_address = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    
    // Ensure default commission is between 0-100
    if ($default_commission_rate < 0 || $default_commission_rate > 100) {
        $user_error = "Default commission rate must be between 0 and 100";
    } else {
        $conn->begin_transaction();
        try {
            // Ensure admin_settings table exists
            $conn->query("CREATE TABLE IF NOT EXISTS admin_settings (
                id INT AUTO_INCREMENT PRIMARY KEY,
                setting_key VARCHAR(100) UNIQUE NOT NULL,
                value TEXT,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
            )");
            
            // Save default commission rate to admin_settings
            $check_stmt = $conn->prepare("SELECT id FROM admin_settings WHERE setting_key = 'default_commission_rate'");
            $check_stmt->execute();
            $exists = $check_stmt->get_result()->num_rows > 0;
            $check_stmt->close();
            
            if ($exists) {
                $stmt = $conn->prepare("UPDATE admin_settings SET value = ? WHERE setting_key = 'default_commission_rate'");
                $stmt->bind_param("d", $default_commission_rate);
                $stmt->execute();
                $stmt->close();
            } else {
                $stmt = $conn->prepare("INSERT INTO admin_settings (setting_key, value) VALUES ('default_commission_rate', ?)");
                $stmt->bind_param("d", $default_commission_rate);
                $stmt->execute();
                $stmt->close();
            }
            
            // Ensure teacher_salary_settings table exists
            $conn->query("CREATE TABLE IF NOT EXISTS teacher_salary_settings (
                id INT AUTO_INCREMENT PRIMARY KEY,
                teacher_id INT NOT NULL,
                commission_rate DECIMAL(5,2),
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                UNIQUE KEY unique_teacher (teacher_id),
                FOREIGN KEY (teacher_id) REFERENCES users(id) ON DELETE CASCADE
            )");
            
            // Update individual teacher commission rates
            $updated_count = 0;
            foreach ($teacher_ids as $index => $teacher_id) {
                $teacher_id = intval($teacher_id);
                $commission_rate = trim($commission_rates[$index]);
                
                if ($teacher_id > 0) {
                    if ($commission_rate !== '' && is_numeric($commission_rate)) {
                        $rate = floatval($commission_rate);
                        if ($rate >= 0 && $rate <= 100) {
                            // Use INSERT ... ON DUPLICATE KEY UPDATE
                            $stmt = $conn->prepare("INSERT INTO teacher_salary_settings (teacher_id, commission_rate) VALUES (?, ?) 
                                                   ON DUPLICATE KEY UPDATE commission_rate = ?");
                            $stmt->bind_param("idd", $teacher_id, $rate, $rate);
                            $stmt->execute();
                            $stmt->close();
                            $updated_count++;
                        }
                    } else {
                        // Remove custom rate (use default)
                        $stmt = $conn->prepare("DELETE FROM teacher_salary_settings WHERE teacher_id = ?");
                        $stmt->bind_param("i", $teacher_id);
                        $stmt->execute();
                        $stmt->close();
                    }
                }
            }
            
            // Log to audit log
            $audit_sql = "INSERT INTO admin_audit_log (admin_id, action, target_type, target_id, details, ip_address) 
                         VALUES (?, 'salary_update', 'teachers', NULL, ?, ?)";
            $audit_stmt = $conn->prepare($audit_sql);
            $details = json_encode([
                'default_commission_rate' => $default_commission_rate,
                'updated_teachers' => $updated_count
            ]);
            $audit_stmt->bind_param("iss", $admin_id, $details, $ip_address);
            $audit_stmt->execute();
            $audit_stmt->close();
            
            $conn->commit();
            $user_msg = "Salary settings updated successfully. Default commission: {$default_commission_rate}%, Updated {$updated_count} teacher(s)";
        } catch (Exception $e) {
            $conn->rollback();
            $user_error = "Error: " . $e->getMessage();
        }
    }
    
    ob_end_clean();
    header("Location: admin-dashboard.php#settings");
    exit();
}

// Handle Account Suspension/Activation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['toggle_account_status'])) {
    $target_user_id = intval($_POST['user_id']);
    $action = $_POST['action_type']; // 'suspend' or 'activate'
    $ip_address = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    
    $conn->begin_transaction();
    try {
        if ($action === 'suspend') {
            // Add suspended flag (you may need to add this column to users table)
            $stmt = $conn->prepare("UPDATE users SET application_status = 'rejected' WHERE id = ?");
            $stmt->bind_param("i", $target_user_id);
            $stmt->execute();
            $stmt->close();
            $status_msg = "Account suspended";
        } else {
            $stmt = $conn->prepare("UPDATE users SET application_status = 'approved' WHERE id = ?");
            $stmt->bind_param("i", $target_user_id);
            $stmt->execute();
            $stmt->close();
            $status_msg = "Account activated";
        }
        
        // Log to audit log
        $audit_sql = "INSERT INTO admin_audit_log (admin_id, action, target_type, target_id, details, ip_address) 
                     VALUES (?, 'account_status_change', 'user', ?, ?, ?)";
        $audit_stmt = $conn->prepare($audit_sql);
        $details = json_encode(['action' => $action]);
        $audit_stmt->bind_param("iiss", $admin_id, $target_user_id, $details, $ip_address);
        $audit_stmt->execute();
        $audit_stmt->close();
        
        $conn->commit();
        $user_msg = $status_msg;
    } catch (Exception $e) {
        $conn->rollback();
        $user_error = "Error: " . $e->getMessage();
    }
    
    ob_end_clean();
    header("Location: admin-dashboard.php#users");
    exit();
}

// Handle CSV Export
if (isset($_GET['export_wallet_csv'])) {
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="wallet_transactions_' . date('Y-m-d') . '.csv"');
    
    $output = fopen('php://output', 'w');
    fputcsv($output, ['ID', 'Student ID', 'Student Name', 'Type', 'Amount', 'Status', 'Stripe Payment ID', 'Reference ID', 'Description', 'Created At']);
    
    $date_from = $_GET['date_from'] ?? date('Y-m-01');
    $date_to = $_GET['date_to'] ?? date('Y-m-d');
    
    $export_sql = "SELECT wt.*, u.name as student_name 
                   FROM wallet_transactions wt 
                   JOIN users u ON wt.student_id = u.id 
                   WHERE DATE(wt.created_at) BETWEEN ? AND ? 
                   ORDER BY wt.created_at DESC";
    $export_stmt = $conn->prepare($export_sql);
    $export_stmt->bind_param("ss", $date_from, $date_to);
    $export_stmt->execute();
    $export_result = $export_stmt->get_result();
    
    while ($row = $export_result->fetch_assoc()) {
        fputcsv($output, [
            $row['id'],
            $row['student_id'],
            $row['student_name'],
            $row['type'],
            $row['amount'],
            $row['status'] ?? 'confirmed',
            $row['stripe_payment_id'] ?? '',
            $row['reference_id'] ?? '',
            $row['description'] ?? '',
            $row['created_at']
        ]);
    }
    
    $export_stmt->close();
    fclose($output);
    exit();
}

// Fetch data
$pending_updates = $conn->query("SELECT p.*, u.email as user_email FROM pending_updates p JOIN users u ON p.user_id = u.id");
if (!$pending_updates) {
    error_log("Error fetching pending updates: " . $conn->error);
    $pending_updates = new mysqli_result($conn);
}

$applications = $conn->query("SELECT * FROM users WHERE application_status='pending'");
if (!$applications) {
    error_log("Error fetching applications: " . $conn->error);
    $applications = new mysqli_result($conn);
}

// Build students query with wallet balance
$students_sql = "SELECT u.*, 
    COALESCE((SELECT SUM(amount) FROM wallet_transactions WHERE student_id = u.id AND status = 'confirmed'), 0) as wallet_balance
    FROM users u WHERE u.role='student'";
    
// Apply search filter if provided
if (!empty($user_search)) {
    $search_term = $conn->real_escape_string($user_search);
    $students_sql .= " AND (u.name LIKE '%{$search_term}%' OR u.email LIKE '%{$search_term}%')";
}

$students_sql .= " ORDER BY u.reg_date DESC";

$students = $conn->query($students_sql);
if (!$students) {
    error_log("Error fetching students: " . $conn->error);
    $students = new mysqli_result($conn);
}

// Build all users query (for unified view)
$all_users_sql = "SELECT u.*,
    (SELECT AVG(rating) FROM reviews WHERE teacher_id = u.id) as avg_rating,
    (SELECT COUNT(*) FROM reviews WHERE teacher_id = u.id) as review_count,
    COALESCE((SELECT GROUP_CONCAT(category SEPARATOR ',') FROM teacher_categories WHERE teacher_id = u.id AND is_active = TRUE), '') as categories,
    COALESCE((SELECT SUM(amount) FROM wallet_transactions WHERE student_id = u.id AND status = 'confirmed'), 0) as wallet_balance
    FROM users u WHERE 1=1";
    
// Apply search filter if provided
if (!empty($user_search)) {
    $search_term = $conn->real_escape_string($user_search);
    $all_users_sql .= " AND (u.name LIKE '%{$search_term}%' OR u.email LIKE '%{$search_term}%')";
}

// Apply role filter if provided
if (!empty($user_role_filter)) {
    $role_filter = $conn->real_escape_string($user_role_filter);
    $all_users_sql .= " AND u.role = '{$role_filter}'";
}

$all_users_sql .= " ORDER BY u.reg_date DESC";

$all_users = $conn->query($all_users_sql);
if (!$all_users) {
    error_log("Error fetching all users: " . $conn->error);
    $all_users = new mysqli_result($conn);
}

// Build teachers query with categories
$teachers_sql = "SELECT u.*, 
    (SELECT AVG(rating) FROM reviews WHERE teacher_id = u.id) as avg_rating,
    (SELECT COUNT(*) FROM reviews WHERE teacher_id = u.id) as review_count,
    (SELECT COUNT(DISTINCT student_id) FROM bookings WHERE teacher_id = u.id) as student_count,
    COALESCE((SELECT GROUP_CONCAT(category SEPARATOR ',') FROM teacher_categories WHERE teacher_id = u.id AND is_active = TRUE), '') as categories
    FROM users u WHERE u.role='teacher'";
    
// Apply search filter if provided
if (!empty($user_search)) {
    $search_term = $conn->real_escape_string($user_search);
    $teachers_sql .= " AND (u.name LIKE '%{$search_term}%' OR u.email LIKE '%{$search_term}%')";
}

// Apply role filter if provided (though teachers query already filters by role)
if (!empty($user_role_filter) && $user_role_filter !== 'teacher') {
    // If filtering for students, we'll handle that separately
}

$teachers_sql .= " ORDER BY u.id DESC";

$teachers = $conn->query($teachers_sql);
if (!$teachers) {
    error_log("Error fetching teachers: " . $conn->error);
    $teachers = new mysqli_result($conn);
}

$materials = $conn->query("SELECT * FROM classroom_materials WHERE is_deleted = 0 ORDER BY created_at DESC");
if (!$materials) {
    error_log("Error fetching materials: " . $conn->error);
    $materials = new mysqli_result($conn);
}

$support_messages = $conn->query("
    SELECT sm.*, u.name as sender_name, u.profile_pic, u.role 
    FROM support_messages sm 
    JOIN users u ON sm.sender_id = u.id 
    ORDER BY sm.created_at DESC
");
if (!$support_messages) {
    error_log("Error fetching support messages: " . $conn->error);
    $support_messages = new mysqli_result($conn);
}

// Revenue analytics
$total_revenue = 0;
$revenue_result = $conn->query("SELECT COALESCE(SUM(amount), 0) as total FROM earnings");
if ($revenue_result) {
    $total_revenue = $revenue_result->fetch_assoc()['total'];
}

// Recent activity
$recent_bookings = $conn->query("
    SELECT b.*, s.name as student_name, t.name as teacher_name 
    FROM bookings b 
    JOIN users s ON b.student_id = s.id 
    JOIN users t ON b.teacher_id = t.id 
    ORDER BY b.booking_date DESC LIMIT 10
");
if (!$recent_bookings) {
    error_log("Error fetching recent bookings: " . $conn->error);
    $recent_bookings = new mysqli_result($conn);
}

// Engagement metrics
// Check if last_active column exists
$last_active_exists = false;
$col_check = $conn->query("SHOW COLUMNS FROM users LIKE 'last_active'");
if ($col_check && $col_check->num_rows > 0) {
    $last_active_exists = true;
}

$inactive_students_query = "
    SELECT u.* FROM users u 
    WHERE u.role = 'student' 
    AND u.id NOT IN (SELECT student_id FROM bookings WHERE booking_date > DATE_SUB(NOW(), INTERVAL 30 DAY))
";
if ($last_active_exists) {
    $inactive_students_query .= " ORDER BY u.last_active ASC";
} else {
    $inactive_students_query .= " ORDER BY u.reg_date ASC";
}
$inactive_students_query .= " LIMIT 10";

$inactive_students = $conn->query($inactive_students_query);
if (!$inactive_students) {
    error_log("Error fetching inactive students: " . $conn->error);
    $inactive_students = new mysqli_result($conn);
}

$unread_support = $admin_stats['open_support'];
$active_tab = 'dashboard';

// Get all users for admin chat
$chat_users_stmt = $conn->prepare("
    SELECT DISTINCT u.id, u.name, u.email, u.role, u.profile_pic,
           COALESCE((SELECT COUNT(*) FROM messages m WHERE m.receiver_id = ? AND m.sender_id = u.id AND m.is_read = 0 AND m.message_type = 'direct'), 0) as unread_count,
           (SELECT MAX(sent_at) FROM messages m WHERE ((m.sender_id = ? AND m.receiver_id = u.id) OR (m.sender_id = u.id AND m.receiver_id = ?)) AND m.message_type = 'direct') as last_message_time
    FROM users u
    WHERE u.id != ? AND u.role != 'admin'
    ORDER BY last_message_time IS NULL, last_message_time DESC, u.name ASC
");
$chat_users_stmt->bind_param("iiii", $admin_id, $admin_id, $admin_id, $admin_id);
$chat_users_stmt->execute();
$chat_users_result = $chat_users_stmt->get_result();
$chat_users = [];
while ($row = $chat_users_result->fetch_assoc()) {
    $chat_users[] = $row;
}
$chat_users_stmt->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=5.0, user-scalable=yes">
    <meta name="theme-color" content="#004080">
    <meta name="mobile-web-app-capable" content="yes">
    <title>Admin Dashboard - Staten Academy</title>
    <link rel="stylesheet" href="<?php echo getAssetPath('styles.css'); ?>">
    <link rel="stylesheet" href="<?php echo getAssetPath('css/dashboard.css'); ?>">
    <link rel="stylesheet" href="<?php echo getAssetPath('css/mobile.css'); ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <script src="<?php echo getAssetPath('js/toast.js'); ?>" defer></script>
</head>
<body class="dashboard-layout">

<?php include __DIR__ . '/app/Views/components/dashboard-header.php'; ?>

<div class="content-wrapper">
    <?php 
    // Make admin_stats available to sidebar
    $admin_stats_for_sidebar = $admin_stats;
    include __DIR__ . '/app/Views/components/dashboard-sidebar.php'; 
    ?>

    <div class="main">
        
        <!-- Dashboard Tab -->
        <div id="dashboard" class="tab-content active">
            <h1>Admin Dashboard</h1>
            
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-icon"><i class="fas fa-user-graduate"></i></div>
                    <div class="stat-info">
                        <h3><?php echo $admin_stats['students']; ?></h3>
                        <p>Students</p>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon success"><i class="fas fa-chalkboard-teacher"></i></div>
                    <div class="stat-info">
                        <h3><?php echo $admin_stats['teachers']; ?></h3>
                        <p>Teachers</p>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon warning"><i class="fas fa-user-plus"></i></div>
                    <div class="stat-info">
                        <h3><?php echo $admin_stats['pending_apps']; ?></h3>
                        <p>Pending Apps</p>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon info"><i class="fas fa-dollar-sign"></i></div>
                    <div class="stat-info">
                        <h3><?php echo formatCurrency($total_revenue); ?></h3>
                        <p>Total Revenue</p>
                    </div>
                </div>
            </div>

            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
                <div class="card">
                    <h2><i class="fas fa-exclamation-circle"></i> Pending Actions</h2>
                    <div style="display: flex; flex-direction: column; gap: 10px;">
                        <?php 
                        $total_pending = $admin_stats['pending_apps'] + $admin_stats['pending_updates'];
                        if ($total_pending > 0): ?>
                        <a href="#" onclick="switchTab('pending-requests')" class="quick-action-btn" style="flex-direction: row; justify-content: space-between; background: #fff5f5; border-color: #dc3545;">
                            <span><i class="fas fa-exclamation-circle"></i> Pending Requests</span>
                            <span class="notification-badge" style="position: static; background: #dc3545; color: white;"><?php echo $total_pending; ?></span>
                        </a>
                        <?php endif; ?>
                        <?php if ($unread_support > 0): ?>
                        <a href="#" onclick="switchTab('support')" class="quick-action-btn" style="flex-direction: row; justify-content: space-between; background: #fff5f5; border-color: var(--danger);">
                            <span><i class="fas fa-headset"></i> Support Messages</span>
                            <span class="notification-badge" style="position: static; background: var(--danger);"><?php echo $unread_support; ?></span>
                        </a>
                        <?php endif; ?>
                        <?php if ($total_pending == 0 && $unread_support == 0): ?>
                        <p style="color: var(--gray); text-align: center; padding: 20px;">
                            <i class="fas fa-check-circle" style="color: var(--success);"></i> All caught up!
                        </p>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="card">
                    <h2><i class="fas fa-history"></i> Recent Bookings</h2>
                    <?php if ($recent_bookings && $recent_bookings->num_rows > 0): ?>
                        <?php while ($booking = $recent_bookings->fetch_assoc()): ?>
                        <div style="display: flex; justify-content: space-between; padding: 10px 0; border-bottom: 1px solid var(--border);">
                            <div>
                                <strong><?php echo h($booking['student_name']); ?></strong>
                                <span style="color: var(--gray);">→</span>
                                <strong><?php echo h($booking['teacher_name']); ?></strong>
                            </div>
                            <span style="color: var(--gray); font-size: 0.85rem;">
                                <?php echo date('M d', strtotime($booking['booking_date'])); ?>
                            </span>
                        </div>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <p style="color: var(--gray); text-align: center;">No recent bookings</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Reports & Analytics Tab (Combined) -->
        <div id="reports" class="tab-content">
            <h1>Reports & Analytics</h1>
            <div style="display: flex; gap: 15px; margin-bottom: 30px; border-bottom: 2px solid #dee2e6; padding-bottom: 15px;">
                <button onclick="switchReportsSubTab('analytics')" class="btn-outline" id="rep-analytics-btn" style="border-bottom: 3px solid #0b6cf5;">
                    <i class="fas fa-chart-line"></i> Analytics
                </button>
                <button onclick="switchReportsSubTab('reports')" class="btn-outline" id="rep-reports-btn">
                    <i class="fas fa-file-alt"></i> Reports
                </button>
            </div>
            
            <div id="reports-analytics" class="reports-subtab active">
                <h2>Analytics</h2>
            
            <div class="earnings-summary">
                <div class="earnings-card primary">
                    <div class="earnings-amount"><?php echo formatCurrency($total_revenue); ?></div>
                    <div class="earnings-label">Total Revenue</div>
                </div>
                <div class="earnings-card">
                    <div class="earnings-amount"><?php echo $admin_stats['total_bookings']; ?></div>
                    <div class="earnings-label">Total Bookings</div>
                </div>
                <div class="earnings-card">
                    <div class="earnings-amount"><?php echo $admin_stats['students'] + $admin_stats['teachers']; ?></div>
                    <div class="earnings-label">Total Users</div>
                </div>
            </div>

            <div class="card">
                <h2><i class="fas fa-trophy"></i> Top Teachers</h2>
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Teacher</th>
                            <th>Rating</th>
                            <th>Students</th>
                            <th>Hours</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        $teachers->data_seek(0);
                        $count = 0;
                        while ($t = $teachers->fetch_assoc()): 
                            if ($count++ >= 10) break;
                        ?>
                        <tr>
                            <td data-label="Teacher">
                                <div style="display: flex; align-items: center; gap: 10px;">
                                    <img src="<?php echo h($t['profile_pic']); ?>" alt="<?php echo h($t['name']); ?>" style="width: 35px; height: 35px; border-radius: 50%; object-fit: cover;" onerror="this.src='<?php echo getAssetPath('images/placeholder-teacher.svg'); ?>'">
                                    <?php echo h($t['name']); ?>
                                </div>
                            </td>
                            <td data-label="Rating"><?php echo getStarRatingHtml($t['avg_rating'] ?? 0); ?></td>
                            <td data-label="Students"><?php echo $t['student_count'] ?? 0; ?></td>
                            <td data-label="Hours"><?php echo $t['hours_taught'] ?? 0; ?> hrs</td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>

            <div class="card">
                <h2><i class="fas fa-user-clock"></i> Inactive Students (30+ days)</h2>
                <?php if ($inactive_students && $inactive_students->num_rows > 0): ?>
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Student</th>
                            <th>Email</th>
                            <th>Joined</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($s = $inactive_students->fetch_assoc()): ?>
                        <tr>
                            <td data-label="Student"><?php echo h($s['name']); ?></td>
                            <td data-label="Email"><?php echo h($s['email']); ?></td>
                            <td data-label="Joined"><?php echo date('M d, Y', strtotime($s['reg_date'])); ?></td>
                            <td data-label="Actions">
                                <a href="mailto:<?php echo h($s['email']); ?>" class="btn-outline btn-sm">Send Email</a>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
                <?php else: ?>
                <p style="color: var(--gray); text-align: center; padding: 20px;">No inactive students found!</p>
                <?php endif; ?>
            </div>
            </div>
            
            <div id="reports-reports" class="reports-subtab" style="display: none;">
                <h2>Financial Reports</h2>
                
                <?php
                // Get financial report filters
                $report_date_from = $_GET['report_date_from'] ?? date('Y-m-01');
                $report_date_to = $_GET['report_date_to'] ?? date('Y-m-d');
                $report_type = $_GET['report_type'] ?? 'all';
                
                // Calculate revenue metrics
                $revenue_sql = "SELECT 
                    COALESCE(SUM(CASE WHEN type = 'purchase' THEN amount ELSE 0 END), 0) as total_purchases,
                    COALESCE(SUM(CASE WHEN type = 'trial' THEN amount ELSE 0 END), 0) as total_trials,
                    COALESCE(SUM(CASE WHEN type = 'refund' THEN amount ELSE 0 END), 0) as total_refunds,
                    COUNT(CASE WHEN type = 'purchase' THEN 1 END) as purchase_count,
                    COUNT(CASE WHEN type = 'trial' THEN 1 END) as trial_count,
                    COUNT(CASE WHEN type = 'refund' THEN 1 END) as refund_count
                    FROM wallet_transactions 
                    WHERE DATE(created_at) BETWEEN ? AND ? AND status = 'confirmed'";
                $revenue_stmt = $conn->prepare($revenue_sql);
                $revenue_stmt->bind_param("ss", $report_date_from, $report_date_to);
                $revenue_stmt->execute();
                $revenue_data = $revenue_stmt->get_result()->fetch_assoc();
                $revenue_stmt->close();
                
                // Get wallet top-ups
                $topups_sql = "SELECT COUNT(*) as count, SUM(amount) as total 
                              FROM wallet_transactions 
                              WHERE type = 'purchase' AND DATE(created_at) BETWEEN ? AND ? AND status = 'confirmed'";
                $topups_stmt = $conn->prepare($topups_sql);
                $topups_stmt->bind_param("ss", $report_date_from, $report_date_to);
                $topups_stmt->execute();
                $topups_data = $topups_stmt->get_result()->fetch_assoc();
                $topups_stmt->close();
                
                // Get refunds detail
                $refunds_sql = "SELECT wt.*, u.name as student_name, u.email as student_email
                               FROM wallet_transactions wt
                               JOIN users u ON wt.student_id = u.id
                               WHERE wt.type = 'refund' AND DATE(wt.created_at) BETWEEN ? AND ?
                               ORDER BY wt.created_at DESC LIMIT 50";
                $refunds_stmt = $conn->prepare($refunds_sql);
                $refunds_stmt->bind_param("ss", $report_date_from, $report_date_to);
                $refunds_stmt->execute();
                $refunds_result = $refunds_stmt->get_result();
                ?>
                
                <div class="card" style="margin-bottom: 30px;">
                    <h2><i class="fas fa-filter"></i> Report Filters</h2>
                    <form method="GET" action="" style="display: grid; grid-template-columns: 1fr 1fr auto; gap: 15px; align-items: end;">
                        <input type="hidden" name="tab" value="reports">
                        <input type="hidden" name="reports_subtab" value="reports">
                        <div>
                            <label>Date From</label>
                            <input type="date" name="report_date_from" value="<?php echo h($report_date_from); ?>" class="form-control">
                        </div>
                        <div>
                            <label>Date To</label>
                            <input type="date" name="report_date_to" value="<?php echo h($report_date_to); ?>" class="form-control">
                        </div>
                        <div>
                            <button type="submit" class="btn-primary">
                                <i class="fas fa-search"></i> Generate Report
                            </button>
                        </div>
                    </form>
                </div>
                
                <div class="stats-grid" style="margin-bottom: 30px;">
                    <div class="stat-card">
                        <div class="stat-icon success"><i class="fas fa-dollar-sign"></i></div>
                        <div class="stat-info">
                            <h3><?php echo formatCurrency($revenue_data['total_purchases'] ?? 0); ?></h3>
                            <p>Total Purchases</p>
                            <small style="color: #666;"><?php echo $revenue_data['purchase_count'] ?? 0; ?> transactions</small>
                        </div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon info"><i class="fas fa-gift"></i></div>
                        <div class="stat-info">
                            <h3><?php echo formatCurrency($revenue_data['total_trials'] ?? 0); ?></h3>
                            <p>Trial Payments</p>
                            <small style="color: #666;"><?php echo $revenue_data['trial_count'] ?? 0; ?> trials</small>
                        </div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon warning"><i class="fas fa-undo"></i></div>
                        <div class="stat-info">
                            <h3><?php echo formatCurrency($revenue_data['total_refunds'] ?? 0); ?></h3>
                            <p>Total Refunds</p>
                            <small style="color: #666;"><?php echo $revenue_data['refund_count'] ?? 0; ?> refunds</small>
                        </div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon"><i class="fas fa-wallet"></i></div>
                        <div class="stat-info">
                            <h3><?php echo formatCurrency(($revenue_data['total_purchases'] ?? 0) + ($revenue_data['total_trials'] ?? 0) - ($revenue_data['total_refunds'] ?? 0)); ?></h3>
                            <p>Net Revenue</p>
                            <small style="color: #666;">After refunds</small>
                        </div>
                    </div>
                </div>
                
                <div class="card" style="margin-bottom: 30px;">
                    <h2><i class="fas fa-chart-bar"></i> Revenue Breakdown</h2>
                    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 20px;">
                        <div style="padding: 20px; background: #f8f9fa; border-radius: 8px;">
                            <h3 style="margin-top: 0; color: #004080;">Wallet Top-ups</h3>
                            <div style="font-size: 2rem; font-weight: bold; color: #28a745;">
                                <?php echo formatCurrency($topups_data['total'] ?? 0); ?>
                            </div>
                            <small style="color: #666;"><?php echo $topups_data['count'] ?? 0; ?> top-ups</small>
                        </div>
                        <div style="padding: 20px; background: #f8f9fa; border-radius: 8px;">
                            <h3 style="margin-top: 0; color: #004080;">Trial Lessons</h3>
                            <div style="font-size: 2rem; font-weight: bold; color: #0b6cf5;">
                                <?php echo formatCurrency($revenue_data['total_trials'] ?? 0); ?>
                            </div>
                            <small style="color: #666;"><?php echo $revenue_data['trial_count'] ?? 0; ?> trials</small>
                        </div>
                        <div style="padding: 20px; background: #f8f9fa; border-radius: 8px;">
                            <h3 style="margin-top: 0; color: #004080;">Refunds</h3>
                            <div style="font-size: 2rem; font-weight: bold; color: #dc3545;">
                                -<?php echo formatCurrency($revenue_data['total_refunds'] ?? 0); ?>
                            </div>
                            <small style="color: #666;"><?php echo $revenue_data['refund_count'] ?? 0; ?> refunds</small>
                        </div>
                    </div>
                </div>
                
                <?php if ($refunds_result && $refunds_result->num_rows > 0): ?>
                <div class="card" style="margin-bottom: 30px;">
                    <h2><i class="fas fa-undo"></i> Refund Tracking</h2>
                    <div style="overflow-x: auto;">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>Date</th>
                                    <th>Student</th>
                                    <th>Amount</th>
                                    <th>Reference</th>
                                    <th>Description</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php while ($refund = $refunds_result->fetch_assoc()): ?>
                                <tr>
                                    <td><?php echo date('M d, Y H:i', strtotime($refund['created_at'])); ?></td>
                                    <td>
                                        <div><?php echo h($refund['student_name']); ?></div>
                                        <small style="color: #666;"><?php echo h($refund['student_email']); ?></small>
                                    </td>
                                    <td style="color: #dc3545; font-weight: 600;">
                                        -<?php echo formatCurrency($refund['amount']); ?>
                                    </td>
                                    <td><small><?php echo h($refund['reference_id'] ?? '-'); ?></small></td>
                                    <td><?php echo h($refund['description'] ?? '-'); ?></td>
                                </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
                <?php endif; ?>
                <?php if (isset($refunds_stmt)) $refunds_stmt->close(); ?>
                
                <div class="card">
                    <h2><i class="fas fa-file-export"></i> Export Reports</h2>
                    <div class="quick-actions" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px;">
                        <a href="?export_wallet_csv=1&date_from=<?php echo urlencode($report_date_from); ?>&date_to=<?php echo urlencode($report_date_to); ?>" class="quick-action-btn">
                            <i class="fas fa-download"></i>
                            <span>Export Wallet Transactions</span>
                        </a>
                        <a href="api/export.php?type=students" class="quick-action-btn">
                            <i class="fas fa-users"></i>
                            <span>Export Students</span>
                        </a>
                        <a href="api/export.php?type=teachers" class="quick-action-btn">
                            <i class="fas fa-chalkboard-teacher"></i>
                            <span>Export Teachers</span>
                        </a>
                        <a href="api/export.php?type=bookings" class="quick-action-btn">
                            <i class="fas fa-calendar"></i>
                            <span>Export Bookings</span>
                        </a>
                        <a href="api/export.php?type=earnings" class="quick-action-btn">
                            <i class="fas fa-dollar-sign"></i>
                            <span>Export Earnings</span>
                        </a>
                    </div>
                </div>

                <div class="card">
                    <h2><i class="fas fa-chart-bar"></i> Summary Statistics</h2>
                    <table class="data-table">
                        <tr>
                            <td><strong>Total Students</strong></td>
                            <td><?php echo $admin_stats['students']; ?></td>
                        </tr>
                        <tr>
                            <td><strong>Total Teachers</strong></td>
                            <td><?php echo $admin_stats['teachers']; ?></td>
                        </tr>
                        <tr>
                            <td><strong>Total Bookings</strong></td>
                            <td><?php echo $admin_stats['total_bookings']; ?></td>
                        </tr>
                        <tr>
                            <td><strong>Total Revenue</strong></td>
                            <td><?php echo formatCurrency($total_revenue); ?></td>
                        </tr>
                        <tr>
                            <td><strong>Pending Applications</strong></td>
                            <td><?php echo $admin_stats['pending_apps']; ?></td>
                        </tr>
                        <tr>
                            <td><strong>Open Support Tickets</strong></td>
                            <td><?php echo $unread_support; ?></td>
                        </tr>
                    </table>
                </div>
            </div>
        </div>

        <!-- Analytics Tab (legacy - redirects to reports) -->
        <div id="analytics" class="tab-content">
            <script>
                if (window.location.hash === '#analytics') {
                    window.location.hash = '#reports';
                    if (typeof switchTab === 'function') switchTab('reports');
                    if (typeof switchReportsSubTab === 'function') switchReportsSubTab('analytics');
                }
            </script>
            <p>Redirecting to Reports & Analytics tab...</p>
        </div>

        <!-- Unified Pending Requests Tab -->
        <div id="pending-requests" class="tab-content">
            <h1><i class="fas fa-exclamation-circle"></i> Pending Requests</h1>
            <p style="color: var(--gray); margin-bottom: 20px;">Review and approve teacher applications and profile update requests.</p>
            
            <?php 
            $total_pending = $admin_stats['pending_apps'] + $admin_stats['pending_updates'];
            if ($total_pending == 0): ?>
            <div class="empty-state">
                <i class="fas fa-check-circle" style="color: var(--success);"></i>
                <h3>All Caught Up!</h3>
                <p>There are no pending requests at this time.</p>
            </div>
            <?php else: ?>
            
            <!-- Teacher Applications Section -->
            <?php if ($admin_stats['pending_apps'] > 0): ?>
            <div style="margin-bottom: 40px;">
                <h2 style="display: flex; align-items: center; gap: 10px; margin-bottom: 15px;">
                    <i class="fas fa-user-plus" style="color: var(--primary);"></i> 
                    Teacher Applications
                    <span class="notification-badge" style="background: #dc3545; color: white; margin-left: 10px;"><?php echo $admin_stats['pending_apps']; ?></span>
                </h2>
                <?php 
                $applications->data_seek(0); // Reset pointer
                if ($applications->num_rows > 0): ?>
                <div style="overflow-x: auto;">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Applicant</th>
                            <th>Email</th>
                            <th>Bio</th>
                            <th>Calendly</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while($app = $applications->fetch_assoc()): ?>
                        <tr>
                            <td data-label="Applicant">
                                <div style="display: flex; align-items: center; gap: 10px;">
                                    <img src="<?php echo h($app['profile_pic']); ?>" alt="<?php echo h($app['name']); ?>" style="width: 40px; height: 40px; border-radius: 50%; object-fit: cover;" onerror="this.src='<?php echo getAssetPath('images/placeholder-teacher.svg'); ?>'">
                                    <?php echo h($app['name']); ?>
                                </div>
                            </td>
                            <td data-label="Email"><?php echo h($app['email']); ?></td>
                            <td data-label="Bio"><?php echo h(substr($app['bio'] ?? '', 0, 50)); ?><?php echo strlen($app['bio'] ?? '') > 50 ? '...' : ''; ?></td>
                            <td data-label="Calendly">
                                <?php if ($app['calendly_link']): ?>
                                <a href="<?php echo h($app['calendly_link']); ?>" target="_blank" class="btn-outline btn-sm">View</a>
                                <?php else: ?>
                                <span style="color: var(--gray);">Not set</span>
                                <?php endif; ?>
                            </td>
                            <td data-label="Actions">
                                <form action="admin-actions.php" method="POST" style="display: inline-flex; gap: 5px;">
                                    <input type="hidden" name="user_id" value="<?php echo $app['id']; ?>">
                                    <button type="submit" name="action" value="approve_teacher" class="btn-success btn-sm">Approve</button>
                                    <button type="submit" name="action" value="reject_teacher" class="btn-danger btn-sm">Reject</button>
                                </form>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
                </div>
                <?php endif; ?>
            </div>
            <?php endif; ?>
            
            <!-- Profile Update Requests Section -->
            <?php if ($admin_stats['pending_updates'] > 0): ?>
            <div>
                <h2 style="display: flex; align-items: center; gap: 10px; margin-bottom: 15px;">
                    <i class="fas fa-edit" style="color: var(--primary);"></i> 
                    Profile Update Requests
                    <span class="notification-badge" style="background: #dc3545; color: white; margin-left: 10px;"><?php echo $admin_stats['pending_updates']; ?></span>
                </h2>
                <?php 
                $pending_updates->data_seek(0); // Reset pointer
                if ($pending_updates->num_rows > 0): ?>
                <div style="overflow-x: auto;">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>User</th>
                            <th>Name</th>
                            <th>Bio</th>
                            <th>About</th>
                            <th>Picture</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while($up = $pending_updates->fetch_assoc()): ?>
                        <tr>
                            <td><?php echo h($up['user_email']); ?></td>
                            <td><?php echo h($up['name']); ?></td>
                            <td title="<?php echo h($up['bio'] ?? ''); ?>"><?php echo h(substr($up['bio'] ?? '', 0, 30)); ?><?php echo strlen($up['bio'] ?? '') > 30 ? '...' : ''; ?></td>
                            <td title="<?php echo h($up['about_text'] ?? ''); ?>"><?php echo h(substr($up['about_text'] ?? '', 0, 30)); ?><?php echo strlen($up['about_text'] ?? '') > 30 ? '...' : ''; ?></td>
                            <td>
                                <?php if ($up['profile_pic']): ?>
                                <a href="<?php echo h($up['profile_pic']); ?>" target="_blank">
                                    <img src="<?php echo h($up['profile_pic']); ?>" alt="" style="width: 40px; height: 40px; border-radius: 5px; object-fit: cover;">
                                </a>
                                <?php else: ?>
                                <span style="color: var(--gray);">None</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <form action="admin-actions.php" method="POST" style="display: inline-flex; gap: 5px;">
                                    <input type="hidden" name="update_id" value="<?php echo $up['id']; ?>">
                                    <button type="submit" name="action" value="approve_profile" class="btn-success btn-sm">Approve</button>
                                    <button type="submit" name="action" value="reject_profile" class="btn-danger btn-sm">Reject</button>
                                </form>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
                </div>
                <?php endif; ?>
            </div>
            <?php endif; ?>
            
            <?php endif; ?>
        </div>

        <!-- Applications Tab (kept for backward compatibility) -->
        <div id="applications" class="tab-content">
            <h1>Teacher Applications</h1>
            <p style="color: var(--gray); margin-bottom: 20px;">This section has been moved to <a href="#" onclick="switchTab('pending-requests')" style="color: var(--primary);">Pending Requests</a>.</p>
            <?php if ($applications->num_rows > 0): ?>
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Applicant</th>
                        <th>Email</th>
                        <th>Bio</th>
                        <th>Calendly</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    $applications->data_seek(0);
                    while($app = $applications->fetch_assoc()): ?>
                    <tr>
                        <td data-label="Applicant">
                            <div style="display: flex; align-items: center; gap: 10px;">
                                <img src="<?php echo h($app['profile_pic']); ?>" alt="<?php echo h($app['name']); ?>" style="width: 40px; height: 40px; border-radius: 50%; object-fit: cover;" onerror="this.src='<?php echo getAssetPath('images/placeholder-teacher.svg'); ?>'">
                                <?php echo h($app['name']); ?>
                            </div>
                        </td>
                        <td data-label="Email"><?php echo h($app['email']); ?></td>
                        <td data-label="Bio"><?php echo h(substr($app['bio'] ?? '', 0, 50)); ?>...</td>
                        <td data-label="Calendly">
                            <?php if ($app['calendly_link']): ?>
                            <a href="<?php echo h($app['calendly_link']); ?>" target="_blank" class="btn-outline btn-sm">View</a>
                            <?php else: ?>
                            <span style="color: var(--gray);">Not set</span>
                            <?php endif; ?>
                        </td>
                        <td data-label="Actions">
                            <form action="admin-actions.php" method="POST" style="display: inline-flex; gap: 5px;">
                                <input type="hidden" name="user_id" value="<?php echo $app['id']; ?>">
                                <button type="submit" name="action" value="approve_teacher" class="btn-success btn-sm">Approve</button>
                                <button type="submit" name="action" value="reject_teacher" class="btn-danger btn-sm">Reject</button>
                            </form>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
            <?php else: ?>
            <div class="empty-state">
                <i class="fas fa-user-plus"></i>
                <h3>No Pending Applications</h3>
                <p>New teacher applications will appear here.</p>
            </div>
            <?php endif; ?>
        </div>

        <!-- Approvals Tab (kept for backward compatibility) -->
        <div id="approvals" class="tab-content">
            <h1>Profile Update Requests</h1>
            <p style="color: var(--gray); margin-bottom: 20px;">This section has been moved to <a href="#" onclick="switchTab('pending-requests')" style="color: var(--primary);">Pending Requests</a>.</p>
            <?php if ($pending_updates->num_rows > 0): ?>
            <div style="overflow-x: auto;">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>User</th>
                        <th>Name</th>
                        <th>Bio</th>
                        <th>About</th>
                        <th>Picture</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    $pending_updates->data_seek(0);
                    while($up = $pending_updates->fetch_assoc()): ?>
                    <tr>
                        <td><?php echo h($up['user_email']); ?></td>
                        <td><?php echo h($up['name']); ?></td>
                        <td title="<?php echo h($up['bio'] ?? ''); ?>"><?php echo h(substr($up['bio'] ?? '', 0, 30)); ?>...</td>
                        <td title="<?php echo h($up['about_text'] ?? ''); ?>"><?php echo h(substr($up['about_text'] ?? '', 0, 30)); ?>...</td>
                        <td>
                            <?php if ($up['profile_pic']): ?>
                            <a href="<?php echo h($up['profile_pic']); ?>" target="_blank">
                                <img src="<?php echo h($up['profile_pic']); ?>" alt="" style="width: 40px; height: 40px; border-radius: 5px; object-fit: cover;">
                            </a>
                            <?php else: ?>
                            <span style="color: var(--gray);">None</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <form action="admin-actions.php" method="POST" style="display: inline-flex; gap: 5px;">
                                <input type="hidden" name="update_id" value="<?php echo $up['id']; ?>">
                                <button type="submit" name="action" value="approve_profile" class="btn-success btn-sm">Approve</button>
                                <button type="submit" name="action" value="reject_profile" class="btn-danger btn-sm">Reject</button>
                            </form>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
            </div>
            <?php else: ?>
            <div class="empty-state">
                <i class="fas fa-check-circle"></i>
                <h3>No Pending Updates</h3>
                <p>Profile update requests will appear here.</p>
            </div>
            <?php endif; ?>
        </div>

        <!-- Users Tab (Combined Teachers & Students) -->
        <div id="users" class="tab-content">
            <h1>User Management</h1>
            
            <!-- Search and Filter Bar -->
            <div class="card" style="margin-bottom: 30px;">
                <form method="GET" action="" style="display: grid; grid-template-columns: 2fr 1fr auto; gap: 15px; align-items: end;">
                    <input type="hidden" name="tab" value="users">
                    <div>
                        <label>Search Users</label>
                        <input type="text" name="user_search" value="<?php echo h($user_search ?? ''); ?>" 
                               placeholder="Search by name or email..." class="form-control">
                    </div>
                    <div>
                        <label>Filter by Role</label>
                        <select name="user_role_filter" class="form-control">
                            <option value="">All Roles</option>
                            <option value="teacher" <?php echo ($user_role_filter ?? '') === 'teacher' ? 'selected' : ''; ?>>Teachers</option>
                            <option value="student" <?php echo ($user_role_filter ?? '') === 'student' ? 'selected' : ''; ?>>Students</option>
                        </select>
                    </div>
                    <div>
                        <button type="submit" class="btn-primary">
                            <i class="fas fa-search"></i> Search
                        </button>
                        <?php if (!empty($user_search) || !empty($user_role_filter)): ?>
                        <a href="admin-dashboard.php#users" class="btn-outline" style="margin-left: 10px;">
                            <i class="fas fa-times"></i> Clear
                        </a>
                        <?php endif; ?>
                    </div>
                </form>
            </div>
            
            <div style="display: flex; gap: 15px; margin-bottom: 30px; border-bottom: 2px solid #dee2e6; padding-bottom: 15px;">
                <button onclick="switchUsersSubTab('all')" class="btn-outline" id="usr-all-btn" style="border-bottom: 3px solid #0b6cf5;">
                    <i class="fas fa-users"></i> All Users
                </button>
                <button onclick="switchUsersSubTab('teachers')" class="btn-outline" id="usr-teachers-btn">
                    <i class="fas fa-chalkboard-teacher"></i> Teachers
                </button>
                <button onclick="switchUsersSubTab('students')" class="btn-outline" id="usr-students-btn">
                    <i class="fas fa-user-graduate"></i> Students
                </button>
            </div>
            
            <?php if (isset($user_msg)): ?>
                <div class="alert-success" style="margin-bottom: 20px;">
                    <?php echo h($user_msg); ?>
                </div>
            <?php endif; ?>
            <?php if (isset($user_error)): ?>
                <div class="alert-error" style="margin-bottom: 20px;">
                    <?php echo h($user_error); ?>
                </div>
            <?php endif; ?>
            
            <!-- All Users Tab -->
            <div id="users-all" class="users-subtab active">
                <h2>All Users Management</h2>
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>User</th>
                            <th>Email</th>
                            <th>Role</th>
                            <th>Categories</th>
                            <th>Wallet Balance</th>
                            <th>Status</th>
                            <th>Joined</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        $all_users->data_seek(0);
                        while($u = $all_users->fetch_assoc()): 
                            $categories = isset($u['categories']) && !empty($u['categories']) ? explode(',', $u['categories']) : [];
                            $is_suspended = ($u['application_status'] ?? 'approved') === 'rejected';
                            $wallet_balance = floatval($u['wallet_balance'] ?? 0);
                        ?>
                        <tr>
                            <td data-label="User">
                                <div style="display: flex; align-items: center; gap: 10px;">
                                    <img src="<?php echo h($u['profile_pic'] ?? getAssetPath('images/placeholder-teacher.svg')); ?>" 
                                         alt="<?php echo h($u['name']); ?>" 
                                         style="width: 40px; height: 40px; border-radius: 50%; object-fit: cover;"
                                         onerror="this.src='<?php echo getAssetPath('images/placeholder-teacher.svg'); ?>'">
                                    <?php echo h($u['name']); ?>
                                </div>
                            </td>
                            <td data-label="Email"><?php echo h($u['email']); ?></td>
                            <td data-label="Role">
                                <span class="badge badge-info"><?php echo ucfirst($u['role']); ?></span>
                            </td>
                            <td data-label="Categories">
                                <?php if ($u['role'] === 'teacher'): ?>
                                    <?php if (!empty($categories)): ?>
                                        <?php foreach ($categories as $cat): ?>
                                            <span class="badge badge-info" style="margin-right: 5px;">
                                                <?php echo ucfirst(str_replace('_', ' ', $cat)); ?>
                                            </span>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <span style="color: #999;">Not assigned</span>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <span style="color: #999;">N/A</span>
                                <?php endif; ?>
                            </td>
                            <td data-label="Wallet">
                                <?php if ($u['role'] === 'student' || $u['role'] === 'new_student'): ?>
                                    $<?php echo number_format($wallet_balance, 2); ?>
                                <?php else: ?>
                                    <span style="color: #999;">N/A</span>
                                <?php endif; ?>
                            </td>
                            <td data-label="Status">
                                <?php if ($is_suspended): ?>
                                    <span class="badge badge-danger">Suspended</span>
                                <?php else: ?>
                                    <span class="badge badge-success">Active</span>
                                <?php endif; ?>
                            </td>
                            <td data-label="Joined"><?php echo date('M d, Y', strtotime($u['reg_date'])); ?></td>
                            <td data-label="Actions">
                                <div style="display: flex; gap: 5px; flex-wrap: wrap;">
                                    <a href="profile.php?id=<?php echo $u['id']; ?>" class="btn-outline btn-sm">View</a>
                                    <?php if ($u['role'] === 'teacher'): ?>
                                        <button onclick="showCategoryModal(<?php echo $u['id']; ?>, '<?php echo h($u['categories'] ?? ''); ?>')" class="btn-outline btn-sm">
                                            <i class="fas fa-tags"></i> Categories
                                        </button>
                                    <?php endif; ?>
                                    <?php if ($u['role'] === 'student' || $u['role'] === 'new_student'): ?>
                                        <button onclick="showWalletModal(<?php echo $u['id']; ?>, '<?php echo h($u['name']); ?>', <?php echo $wallet_balance; ?>)" class="btn-outline btn-sm">
                                            <i class="fas fa-wallet"></i> Wallet
                                        </button>
                                    <?php endif; ?>
                                    <?php if ($is_suspended): ?>
                                        <form method="POST" style="display: inline;" onsubmit="return confirm('Activate this user account?');">
                                            <input type="hidden" name="user_id" value="<?php echo $u['id']; ?>">
                                            <input type="hidden" name="action_type" value="activate">
                                            <button type="submit" name="toggle_account_status" class="btn-success btn-sm">
                                                <i class="fas fa-check"></i> Activate
                                            </button>
                                        </form>
                                    <?php else: ?>
                                        <form method="POST" style="display: inline;" onsubmit="return confirm('Suspend this user account?');">
                                            <input type="hidden" name="user_id" value="<?php echo $u['id']; ?>">
                                            <input type="hidden" name="action_type" value="suspend">
                                            <button type="submit" name="toggle_account_status" class="btn-danger btn-sm">
                                                <i class="fas fa-ban"></i> Suspend
                                            </button>
                                        </form>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
            
            <div id="users-teachers" class="users-subtab" style="display: none;">
                <h2>Teacher Management</h2>
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Teacher</th>
                        <th>Email</th>
                        <th>Categories</th>
                        <th>Rating</th>
                        <th>Students</th>
                        <th>Hours</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    $teachers->data_seek(0);
                    while($t = $teachers->fetch_assoc()): 
                        $categories = isset($t['categories']) && !empty($t['categories']) ? explode(',', $t['categories']) : [];
                        $is_suspended = ($t['application_status'] ?? 'approved') === 'rejected';
                    ?>
                    <tr>
                        <td data-label="Teacher">
                            <div style="display: flex; align-items: center; gap: 10px;">
                                <img src="<?php echo h($t['profile_pic']); ?>" alt="<?php echo h($t['name']); ?>" style="width: 40px; height: 40px; border-radius: 50%; object-fit: cover;" onerror="this.src='<?php echo getAssetPath('images/placeholder-teacher.svg'); ?>'">
                                <?php echo h($t['name']); ?>
                            </div>
                        </td>
                        <td data-label="Email"><?php echo h($t['email']); ?></td>
                        <td data-label="Categories">
                            <?php if (!empty($categories)): ?>
                                <?php foreach ($categories as $cat): ?>
                                    <span class="badge badge-info" style="margin-right: 5px;">
                                        <?php echo ucfirst(str_replace('_', ' ', $cat)); ?>
                                    </span>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <span style="color: #999;">Not assigned</span>
                            <?php endif; ?>
                        </td>
                        <td data-label="Rating"><?php echo getStarRatingHtml($t['avg_rating'] ?? 0); ?></td>
                        <td data-label="Students"><?php echo $t['student_count'] ?? 0; ?></td>
                        <td data-label="Hours"><?php echo $t['hours_taught'] ?? 0; ?> hrs</td>
                        <td data-label="Status">
                            <?php if ($is_suspended): ?>
                                <span class="badge badge-danger">Suspended</span>
                            <?php else: ?>
                                <span class="badge badge-success">Active</span>
                            <?php endif; ?>
                        </td>
                        <td data-label="Actions">
                            <div style="display: flex; gap: 5px; flex-wrap: wrap;">
                                <a href="profile.php?id=<?php echo $t['id']; ?>" class="btn-outline btn-sm">View</a>
                                <button onclick="showRoleModal(<?php echo $t['id']; ?>, '<?php echo h($t['role']); ?>')" class="btn-outline btn-sm">
                                    <i class="fas fa-user-tag"></i> Role
                                </button>
                                <button onclick="showCategoryModal(<?php echo $t['id']; ?>, '<?php echo h($t['categories'] ?? ''); ?>')" class="btn-outline btn-sm">
                                    <i class="fas fa-tags"></i> Category
                                </button>
                                <?php if ($is_suspended): ?>
                                    <form method="POST" style="display: inline;" onsubmit="return confirm('Activate this teacher account?');">
                                        <input type="hidden" name="user_id" value="<?php echo $t['id']; ?>">
                                        <input type="hidden" name="action_type" value="activate">
                                        <button type="submit" name="toggle_account_status" class="btn-success btn-sm">
                                            <i class="fas fa-check"></i> Activate
                                        </button>
                                    </form>
                                <?php else: ?>
                                    <form method="POST" style="display: inline;" onsubmit="return confirm('Suspend this teacher account?');">
                                        <input type="hidden" name="user_id" value="<?php echo $t['id']; ?>">
                                        <input type="hidden" name="action_type" value="suspend">
                                        <button type="submit" name="toggle_account_status" class="btn-danger btn-sm">
                                            <i class="fas fa-ban"></i> Suspend
                                        </button>
                                    </form>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
            </div>
            
            <div id="users-students" class="users-subtab" style="display: none;">
                <h2>Student Management</h2>
                <table class="data-table">
                <thead>
                    <tr>
                        <th>Student</th>
                        <th>Email</th>
                        <th>Category</th>
                        <th>Joined</th>
                        <th>Wallet Balance</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    $students->data_seek(0);
                    while($s = $students->fetch_assoc()): 
                        $wallet_balance = floatval($s['wallet_balance'] ?? 0);
                        $is_suspended = ($s['application_status'] ?? 'approved') === 'rejected';
                    ?>
                    <tr>
                        <td data-label="Student">
                            <div style="display: flex; align-items: center; gap: 10px;">
                                <img src="<?php echo h($s['profile_pic']); ?>" alt="<?php echo h($s['name']); ?>" style="width: 40px; height: 40px; border-radius: 50%; object-fit: cover;" onerror="this.src='<?php echo getAssetPath('images/placeholder-teacher.svg'); ?>'">
                                <?php echo h($s['name']); ?>
                            </div>
                        </td>
                        <td data-label="Email"><?php echo h($s['email']); ?></td>
                        <td data-label="Category">
                            <?php if ($s['preferred_category']): ?>
                                <span class="badge badge-info">
                                    <?php echo ucfirst(str_replace('_', ' ', $s['preferred_category'])); ?>
                                </span>
                            <?php else: ?>
                                <span style="color: #999;">Not set</span>
                            <?php endif; ?>
                        </td>
                        <td data-label="Joined"><?php echo date('M d, Y', strtotime($s['reg_date'])); ?></td>
                        <td data-label="Wallet"><?php echo formatCurrency($wallet_balance); ?></td>
                        <td data-label="Status">
                            <?php if ($is_suspended): ?>
                                <span class="badge badge-danger">Suspended</span>
                            <?php else: ?>
                                <span class="badge badge-success">Active</span>
                            <?php endif; ?>
                        </td>
                        <td data-label="Actions">
                            <div style="display: flex; gap: 5px; flex-wrap: wrap;">
                                <a href="profile.php?id=<?php echo $s['id']; ?>" class="btn-outline btn-sm">View</a>
                                <button onclick="showRoleModal(<?php echo $s['id']; ?>, '<?php echo h($s['role']); ?>')" class="btn-outline btn-sm">
                                    <i class="fas fa-user-tag"></i> Role
                                </button>
                                <button onclick="showWalletModal(<?php echo $s['id']; ?>, '<?php echo h($s['name']); ?>', <?php echo $wallet_balance; ?>)" class="btn-outline btn-sm">
                                    <i class="fas fa-wallet"></i> Wallet
                                </button>
                                <?php if ($is_suspended): ?>
                                    <form method="POST" style="display: inline;" onsubmit="return confirm('Activate this student account?');">
                                        <input type="hidden" name="user_id" value="<?php echo $s['id']; ?>">
                                        <input type="hidden" name="action_type" value="activate">
                                        <button type="submit" name="toggle_account_status" class="btn-success btn-sm">
                                            <i class="fas fa-check"></i> Activate
                                        </button>
                                    </form>
                                <?php else: ?>
                                    <form method="POST" style="display: inline;" onsubmit="return confirm('Suspend this student account?');">
                                        <input type="hidden" name="user_id" value="<?php echo $s['id']; ?>">
                                        <input type="hidden" name="action_type" value="suspend">
                                        <button type="submit" name="toggle_account_status" class="btn-danger btn-sm">
                                            <i class="fas fa-ban"></i> Suspend
                                        </button>
                                    </form>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
                </table>
            </div>
        </div>

        <!-- Scheduling Tab -->
        <div id="scheduling" class="tab-content">
            <h1>Scheduling Oversight</h1>
            
            <?php
            // Get all lessons with teacher and student info
            $lessons_sql = "SELECT l.*, 
                           t.name as teacher_name, t.email as teacher_email,
                           s.name as student_name, s.email as student_email
                           FROM lessons l
                           JOIN users t ON l.teacher_id = t.id
                           JOIN users s ON l.student_id = s.id
                           ORDER BY l.lesson_date DESC, l.lesson_time DESC
                           LIMIT 100";
            $all_lessons = $conn->query($lessons_sql);
            if (!$all_lessons) {
                error_log("Error fetching lessons: " . $conn->error);
                $all_lessons = new mysqli_result($conn);
            }
            
            // Get conflicts (overlapping lessons for same teacher)
            $conflicts_sql = "SELECT l1.id as lesson1_id, l2.id as lesson2_id,
                             l1.teacher_id, l1.lesson_date, l1.lesson_time,
                             l1.duration, t.name as teacher_name
                             FROM lessons l1
                             JOIN lessons l2 ON l1.teacher_id = l2.teacher_id 
                             AND l1.id < l2.id
                             JOIN users t ON l1.teacher_id = t.id
                             WHERE l1.lesson_date = l2.lesson_date
                             AND l1.status != 'cancelled'
                             AND l2.status != 'cancelled'
                             AND (
                                 (l1.lesson_time <= l2.lesson_time AND ADDTIME(l1.lesson_time, SEC_TO_TIME(l1.duration * 60)) > l2.lesson_time)
                                 OR (l2.lesson_time <= l1.lesson_time AND ADDTIME(l2.lesson_time, SEC_TO_TIME(l2.duration * 60)) > l1.lesson_time)
                             )
                             ORDER BY l1.lesson_date DESC, l1.lesson_time DESC";
            $conflicts = $conn->query($conflicts_sql);
            if (!$conflicts) {
                error_log("Error fetching conflicts: " . $conn->error);
                $conflicts = new mysqli_result($conn);
            }
            ?>
            
            <div class="card" style="margin-bottom: 30px;">
                <h2><i class="fas fa-calendar-plus"></i> Create Lesson Manually</h2>
                <form method="POST" action="api/admin-create-lesson.php" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 15px;">
                    <div>
                        <label>Teacher</label>
                        <select name="teacher_id" class="form-control" required>
                            <option value="">Select Teacher</option>
                            <?php
                            $teachers_for_schedule = $conn->query("SELECT id, name FROM users WHERE role='teacher' ORDER BY name");
                            if ($teachers_for_schedule) {
                                while ($t = $teachers_for_schedule->fetch_assoc()): ?>
                                    <option value="<?php echo $t['id']; ?>"><?php echo h($t['name']); ?></option>
                                <?php endwhile;
                            }
                            ?>
                        </select>
                    </div>
                    <div>
                        <label>Student</label>
                        <select name="student_id" class="form-control" required>
                            <option value="">Select Student</option>
                            <?php
                            $students_for_schedule = $conn->query("SELECT id, name FROM users WHERE role='student' ORDER BY name");
                            if ($students_for_schedule) {
                                while ($s = $students_for_schedule->fetch_assoc()): ?>
                                    <option value="<?php echo $s['id']; ?>"><?php echo h($s['name']); ?></option>
                                <?php endwhile;
                            }
                            ?>
                        </select>
                    </div>
                    <div>
                        <label>Date</label>
                        <input type="date" name="lesson_date" class="form-control" required>
                    </div>
                    <div>
                        <label>Time</label>
                        <input type="time" name="lesson_time" class="form-control" required>
                    </div>
                    <div>
                        <label>Duration (minutes)</label>
                        <input type="number" name="duration" class="form-control" value="60" min="15" step="15" required>
                    </div>
                    <div>
                        <label>Category</label>
                        <select name="category" class="form-control" required>
                            <option value="young_learners">Young Learners</option>
                            <option value="adults">Adults</option>
                            <option value="coding">English for Coding/Tech</option>
                        </select>
                    </div>
                    <div style="grid-column: 1 / -1;">
                        <label>Notes</label>
                        <textarea name="notes" class="form-control" rows="3"></textarea>
                    </div>
                    <div style="grid-column: 1 / -1;">
                        <button type="submit" class="btn-primary">
                            <i class="fas fa-plus"></i> Create Lesson
                        </button>
                    </div>
                </form>
            </div>
            
            <?php if ($conflicts && $conflicts->num_rows > 0): ?>
            <div class="card" style="margin-bottom: 30px; border-left: 4px solid #dc3545;">
                <h2><i class="fas fa-exclamation-triangle" style="color: #dc3545;"></i> Scheduling Conflicts</h2>
                <p style="color: #666; margin-bottom: 15px;">The following lessons have overlapping times for the same teacher:</p>
                <div style="overflow-x: auto;">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Teacher</th>
                                <th>Date</th>
                                <th>Time</th>
                                <th>Lesson 1 ID</th>
                                <th>Lesson 2 ID</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($conflict = $conflicts->fetch_assoc()): ?>
                            <tr>
                                <td><?php echo h($conflict['teacher_name']); ?></td>
                                <td><?php echo date('M d, Y', strtotime($conflict['lesson_date'])); ?></td>
                                <td><?php echo date('H:i', strtotime($conflict['lesson_time'])); ?></td>
                                <td><a href="admin-dashboard.php#scheduling" onclick="highlightLesson(<?php echo $conflict['lesson1_id']; ?>)">#<?php echo $conflict['lesson1_id']; ?></a></td>
                                <td><a href="admin-dashboard.php#scheduling" onclick="highlightLesson(<?php echo $conflict['lesson2_id']; ?>)">#<?php echo $conflict['lesson2_id']; ?></a></td>
                                <td>
                                    <a href="admin-dashboard.php#scheduling" class="btn-outline btn-sm">View</a>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <?php endif; ?>
            
            <div class="card">
                <h2><i class="fas fa-calendar-alt"></i> All Lessons</h2>
                <div style="overflow-x: auto;">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Time</th>
                                <th>Teacher</th>
                                <th>Student</th>
                                <th>Duration</th>
                                <th>Category</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($all_lessons && $all_lessons->num_rows > 0): ?>
                                <?php while ($lesson = $all_lessons->fetch_assoc()): ?>
                                <tr id="lesson-<?php echo $lesson['id']; ?>" style="transition: background 0.3s;">
                                    <td><?php echo date('M d, Y', strtotime($lesson['lesson_date'])); ?></td>
                                    <td><?php echo date('H:i', strtotime($lesson['lesson_time'])); ?></td>
                                    <td>
                                        <div><?php echo h($lesson['teacher_name']); ?></div>
                                        <small style="color: #666;"><?php echo h($lesson['teacher_email']); ?></small>
                                    </td>
                                    <td>
                                        <div><?php echo h($lesson['student_name']); ?></div>
                                        <small style="color: #666;"><?php echo h($lesson['student_email']); ?></small>
                                    </td>
                                    <td><?php echo $lesson['duration']; ?> min</td>
                                    <td>
                                        <span class="badge badge-info">
                                            <?php echo ucfirst(str_replace('_', ' ', $lesson['category'] ?? 'N/A')); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php
                                        $status_class = 'badge-info';
                                        if ($lesson['status'] === 'completed') $status_class = 'badge-success';
                                        elseif ($lesson['status'] === 'cancelled') $status_class = 'badge-danger';
                                        elseif ($lesson['status'] === 'pending') $status_class = 'badge-warning';
                                        ?>
                                        <span class="badge <?php echo $status_class; ?>">
                                            <?php echo ucfirst($lesson['status'] ?? 'pending'); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <div style="display: flex; gap: 5px;">
                                            <a href="classroom.php?lesson_id=<?php echo $lesson['id']; ?>" class="btn-outline btn-sm" target="_blank">
                                                <i class="fas fa-door-open"></i> View
                                            </a>
                                            <?php if ($lesson['status'] !== 'cancelled' && $lesson['status'] !== 'completed'): ?>
                                            <form method="POST" action="api/admin-cancel-lesson.php" style="display: inline;" onsubmit="return confirm('Cancel this lesson?');">
                                                <input type="hidden" name="lesson_id" value="<?php echo $lesson['id']; ?>">
                                                <button type="submit" class="btn-danger btn-sm">
                                                    <i class="fas fa-times"></i> Cancel
                                                </button>
                                            </form>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="8" style="text-align: center; padding: 20px; color: #666;">
                                        No lessons found.
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Teachers Tab (legacy - redirects to users) -->
        <div id="teachers" class="tab-content">
            <script>
                if (window.location.hash === '#teachers') {
                    window.location.hash = '#users';
                    if (typeof switchTab === 'function') switchTab('users');
                    if (typeof switchUsersSubTab === 'function') switchUsersSubTab('teachers');
                }
            </script>
            <p>Redirecting to Users tab...</p>
        </div>

        <!-- Students Tab (legacy - redirects to users) -->
        <div id="students" class="tab-content">
            <script>
                if (window.location.hash === '#students') {
                    window.location.hash = '#users';
                    if (typeof switchTab === 'function') switchTab('users');
                    if (typeof switchUsersSubTab === 'function') switchUsersSubTab('students');
                }
            </script>
            <p>Redirecting to Users tab...</p>
        </div>

        <!-- Messages Tab -->
        <div id="messages" class="tab-content">
            <h1><i class="fas fa-comments"></i> Chat with Users</h1>
            <p style="color: #666; margin-bottom: 20px;">Start a conversation with any user or continue existing conversations.</p>
            
            <div style="display: grid; grid-template-columns: 300px 1fr; gap: 20px; height: 600px;">
                <!-- Users List -->
                <div style="background: white; border-radius: 8px; padding: 15px; overflow-y: auto; border: 1px solid #ddd;">
                    <h3 style="margin-top: 0; font-size: 1.1rem; color: #004080;">All Users</h3>
                    <div style="display: flex; flex-direction: column; gap: 10px;">
                        <?php if (count($chat_users) > 0): ?>
                            <?php foreach ($chat_users as $chat_user): ?>
                                <a href="message_threads.php?user_id=<?php echo $chat_user['id']; ?>" 
                                   style="display: flex; align-items: center; gap: 10px; padding: 12px; background: #f8f9fa; border-radius: 8px; text-decoration: none; color: inherit; transition: all 0.2s;"
                                   onmouseover="this.style.background='#f0f7ff'; this.style.transform='translateX(5px)';"
                                   onmouseout="this.style.background='#f8f9fa'; this.style.transform='translateX(0)';">
                                    <img src="<?php echo h($chat_user['profile_pic'] ?? getAssetPath('images/placeholder-teacher.svg')); ?>" 
                                         alt="" style="width: 45px; height: 45px; border-radius: 50%; object-fit: cover;"
                                         onerror="this.src='<?php echo getAssetPath('images/placeholder-teacher.svg'); ?>'">
                                    <div style="flex: 1; min-width: 0;">
                                        <div style="font-weight: 600; color: #333; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">
                                            <?php echo h($chat_user['name']); ?>
                                        </div>
                                        <div style="font-size: 0.85rem; color: #666;">
                                            <span class="tag <?php echo $chat_user['role']; ?>" style="font-size: 0.75rem; padding: 2px 8px;">
                                                <?php echo ucfirst($chat_user['role']); ?>
                                            </span>
                                        </div>
                                    </div>
                                    <?php if ($chat_user['unread_count'] > 0): ?>
                                        <span style="background: #dc3545; color: white; border-radius: 50%; width: 20px; height: 20px; display: flex; align-items: center; justify-content: center; font-size: 0.75rem; font-weight: bold;">
                                            <?php echo $chat_user['unread_count']; ?>
                                        </span>
                                    <?php endif; ?>
                                </a>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <p style="color: #999; text-align: center; padding: 20px;">No users found</p>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- Chat Area Placeholder -->
                <div style="background: white; border-radius: 8px; padding: 20px; border: 1px solid #ddd; display: flex; align-items: center; justify-content: center; flex-direction: column; color: #999;">
                    <i class="fas fa-comments" style="font-size: 3rem; margin-bottom: 15px; opacity: 0.3;"></i>
                    <p style="font-size: 1.1rem; margin: 0;">Select a user from the list to start chatting</p>
                    <p style="font-size: 0.9rem; margin-top: 10px;">Or click on a user to open the full chat interface</p>
                </div>
            </div>
        </div>

        <!-- Support Tab -->
        <div id="support" class="tab-content">
            <h1>Support Messages</h1>
            
            <div style="margin-bottom: 20px;">
                <label style="margin-right: 15px;">
                    <input type="radio" name="support-filter" value="all" checked onchange="filterSupport('all')"> All
                </label>
                <label style="margin-right: 15px;">
                    <input type="radio" name="support-filter" value="student" onchange="filterSupport('student')"> Students
                </label>
                <label>
                    <input type="radio" name="support-filter" value="teacher" onchange="filterSupport('teacher')"> Teachers
                </label>
            </div>
            
            <?php if ($support_messages->num_rows > 0): ?>
            <table class="data-table">
                <thead>
                    <tr>
                        <th>From</th>
                        <th>Role</th>
                        <th>Subject</th>
                        <th>Date</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody id="supportTableBody">
                    <?php while($sm = $support_messages->fetch_assoc()): ?>
                    <tr class="support-row" data-role="<?php echo $sm['sender_role']; ?>">
                        <td>
                            <div style="display: flex; align-items: center; gap: 10px;">
                                <img src="<?php echo h($sm['profile_pic']); ?>" alt="" style="width: 35px; height: 35px; border-radius: 50%; object-fit: cover;" onerror="this.src='<?php echo getAssetPath('images/placeholder-teacher.svg'); ?>'">
                                <?php echo h($sm['sender_name']); ?>
                            </div>
                        </td>
                        <td>
                            <span class="tag <?php echo $sm['sender_role']; ?>"><?php echo ucfirst($sm['sender_role']); ?></span>
                        </td>
                        <td><strong><?php echo h($sm['subject']); ?></strong></td>
                        <td><?php echo date('M d, Y', strtotime($sm['created_at'])); ?></td>
                        <td>
                            <span class="tag <?php echo $sm['status'] === 'open' ? 'pending' : 'active'; ?>">
                                <?php echo ucfirst($sm['status']); ?>
                            </span>
                        </td>
                        <td>
                            <button onclick="viewSupportMessage(<?php echo $sm['id']; ?>, <?php echo $sm['sender_id']; ?>, '<?php echo h(addslashes($sm['sender_name'])); ?>', '<?php echo h(addslashes($sm['subject'])); ?>', '<?php echo h(addslashes($sm['message'])); ?>', '<?php echo h(addslashes($sm['sender_role'])); ?>')" class="btn-outline btn-sm">View & Reply</button>
                            <?php if ($sm['status'] === 'open'): ?>
                            <form action="admin-actions.php" method="POST" style="display: inline;">
                                <input type="hidden" name="support_id" value="<?php echo $sm['id']; ?>">
                                <button type="submit" name="action" value="mark_support_read" class="btn-success btn-sm">Mark Read</button>
                            </form>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
            <?php else: ?>
            <div class="empty-state">
                <i class="fas fa-headset"></i>
                <h3>No Support Messages</h3>
                <p>Support requests will appear here.</p>
            </div>
            <?php endif; ?>
        </div>

        <!-- Slot Requests Tab -->
        <div id="slot-requests" class="tab-content">
            <h1>Slot & Group Class Requests</h1>
            
            <?php
            // Fetch slot requests
            $slot_requests = $conn->query("
                SELECT sr.*, 
                       a.name as admin_name, 
                       t.name as teacher_name, 
                       t.email as teacher_email
                FROM admin_slot_requests sr
                JOIN users a ON sr.admin_id = a.id
                JOIN users t ON sr.teacher_id = t.id
                ORDER BY sr.created_at DESC
            ");
            ?>
            
            <div class="card" style="margin-bottom: 20px;">
                <h2><i class="fas fa-plus-circle"></i> Request New Slot or Group Class</h2>
                <form action="admin-actions.php" method="POST" id="slotRequestForm">
                    <input type="hidden" name="action" value="create_slot_request">
                    
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px; margin-bottom: 15px;">
                        <div>
                            <label>Request Type *</label>
                            <select name="request_type" id="requestType" required onchange="toggleRequestFields()">
                                <option value="time_slot">Time Slot</option>
                                <option value="group_class">Group Class</option>
                            </select>
                        </div>
                        <div>
                            <label>Teacher *</label>
                            <select name="teacher_id" required>
                                <option value="">Select Teacher</option>
                                <?php
                                $teacher_list = $conn->query("SELECT id, name FROM users WHERE role = 'teacher' ORDER BY name");
                                while ($t = $teacher_list->fetch_assoc()): ?>
                                    <option value="<?php echo $t['id']; ?>"><?php echo h($t['name']); ?></option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                    </div>
                    
                    <div id="timeSlotFields">
                        <div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 15px; margin-bottom: 15px;">
                            <div>
                                <label>Date *</label>
                                <input type="date" name="requested_date" required>
                            </div>
                            <div>
                                <label>Time *</label>
                                <input type="time" name="requested_time" required>
                            </div>
                            <div>
                                <label>Duration (minutes) *</label>
                                <input type="number" name="duration_minutes" value="60" min="15" step="15" required>
                            </div>
                        </div>
                    </div>
                    
                    <div id="groupClassFields" style="display: none;">
                        <div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 15px; margin-bottom: 15px;">
                            <div>
                                <label>Track *</label>
                                <select name="group_class_track" required>
                                    <option value="">Select Track</option>
                                    <option value="kids">Kids</option>
                                    <option value="adults">Adults</option>
                                    <option value="coding">Coding</option>
                                </select>
                            </div>
                            <div>
                                <label>Date *</label>
                                <input type="date" name="group_class_date" required>
                            </div>
                            <div>
                                <label>Time *</label>
                                <input type="time" name="group_class_time" required>
                            </div>
                        </div>
                    </div>
                    
                    <div>
                        <label>Message (optional)</label>
                        <textarea name="message" rows="3" placeholder="Add any notes or instructions for the teacher..."></textarea>
                    </div>
                    
                    <button type="submit" class="btn-primary" style="margin-top: 15px;">
                        <i class="fas fa-paper-plane"></i> Send Request
                    </button>
                </form>
            </div>
            
            <h2 style="margin-top: 30px;">Pending & Recent Requests</h2>
            <?php if ($slot_requests && $slot_requests->num_rows > 0): ?>
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Type</th>
                        <th>Teacher</th>
                        <th>Date/Time</th>
                        <th>Status</th>
                        <th>Requested</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while($sr = $slot_requests->fetch_assoc()): ?>
                    <tr>
                        <td>
                            <span class="tag <?php echo $sr['request_type'] === 'group_class' ? 'warning' : 'info'; ?>">
                                <?php echo ucfirst(str_replace('_', ' ', $sr['request_type'])); ?>
                            </span>
                        </td>
                        <td><?php echo h($sr['teacher_name']); ?></td>
                        <td>
                            <?php if ($sr['request_type'] === 'time_slot'): ?>
                                <?php echo date('M d, Y', strtotime($sr['requested_date'])); ?> at <?php echo date('g:i A', strtotime($sr['requested_time'])); ?>
                                (<?php echo $sr['duration_minutes']; ?> min)
                            <?php else: ?>
                                <?php echo date('M d, Y', strtotime($sr['group_class_date'])); ?> at <?php echo date('g:i A', strtotime($sr['group_class_time'])); ?>
                                (<?php echo ucfirst($sr['group_class_track']); ?>)
                            <?php endif; ?>
                        </td>
                        <td>
                            <span class="tag <?php 
                                echo $sr['status'] === 'pending' ? 'pending' : 
                                    ($sr['status'] === 'accepted' ? 'active' : 
                                    ($sr['status'] === 'rejected' ? 'danger' : 'success')); 
                            ?>">
                                <?php echo ucfirst($sr['status']); ?>
                            </span>
                        </td>
                        <td><?php echo date('M d, Y g:i A', strtotime($sr['created_at'])); ?></td>
                        <td>
                            <?php if ($sr['status'] === 'pending'): ?>
                                <button onclick="viewSlotRequest(<?php echo $sr['id']; ?>)" class="btn-outline btn-sm">View</button>
                            <?php else: ?>
                                <button onclick="viewSlotRequest(<?php echo $sr['id']; ?>)" class="btn-outline btn-sm">Details</button>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
            <?php else: ?>
            <div class="card">
                <p>No slot requests yet.</p>
            </div>
            <?php endif; ?>
        </div>

        <!-- Reports Tab (legacy - redirects to reports) -->
        <div id="reports-old" class="tab-content" style="display: none;">
            <script>
                if (window.location.hash === '#reports' && !document.getElementById('reports').classList.contains('active')) {
                    // This is handled by the consolidated reports tab
                }
            </script>
        </div>

        <!-- Wallet Reconciliation Tab -->
        <div id="wallet-reconciliation" class="tab-content">
            <h1><i class="fas fa-wallet"></i> Wallet Reconciliation</h1>
            
            <?php
            // Get wallet statistics
            $wallet_stats_sql = "SELECT 
                COUNT(DISTINCT student_id) as total_students,
                SUM(balance) as total_balance,
                SUM(trial_credits) as total_trial_credits
                FROM student_wallet";
            $wallet_stats_result = $conn->query($wallet_stats_sql);
            $wallet_stats = $wallet_stats_result ? $wallet_stats_result->fetch_assoc() : ['total_students' => 0, 'total_balance' => 0, 'total_trial_credits' => 0];
            
            // Get filter parameters
            $filter_student = $_GET['filter_student'] ?? '';
            $filter_type = $_GET['filter_type'] ?? '';
            $filter_status = $_GET['filter_status'] ?? '';
            $date_from = $_GET['date_from'] ?? date('Y-m-01');
            $date_to = $_GET['date_to'] ?? date('Y-m-d');
            
            // Build transaction query
            $transactions_sql = "SELECT wt.*, u.name as student_name, u.email as student_email 
                               FROM wallet_transactions wt 
                               JOIN users u ON wt.student_id = u.id 
                               WHERE DATE(wt.created_at) BETWEEN ? AND ?";
            $params = [$date_from, $date_to];
            $types = "ss";
            
            if ($filter_student) {
                $transactions_sql .= " AND wt.student_id = ?";
                $params[] = $filter_student;
                $types .= "i";
            }
            if ($filter_type) {
                $transactions_sql .= " AND wt.type = ?";
                $params[] = $filter_type;
                $types .= "s";
            }
            if ($filter_status) {
                $transactions_sql .= " AND wt.status = ?";
                $params[] = $filter_status;
                $types .= "s";
            }
            
            $transactions_sql .= " ORDER BY wt.created_at DESC LIMIT 500";
            
            $transactions_stmt = $conn->prepare($transactions_sql);
            if ($transactions_stmt) {
                $transactions_stmt->bind_param($types, ...$params);
                $transactions_stmt->execute();
                $transactions_result = $transactions_stmt->get_result();
            } else {
                $transactions_result = null;
            }
            
            // Get all students for filter dropdown
            $students_list = $conn->query("SELECT id, name, email FROM users WHERE role = 'student' ORDER BY name");
            ?>
            
            <div class="stats-grid" style="margin-bottom: 30px;">
                <div class="stat-card">
                    <div class="stat-icon"><i class="fas fa-users"></i></div>
                    <div class="stat-info">
                        <h3><?php echo $wallet_stats['total_students']; ?></h3>
                        <p>Students with Wallets</p>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon success"><i class="fas fa-dollar-sign"></i></div>
                    <div class="stat-info">
                        <h3><?php echo formatCurrency($wallet_stats['total_balance'] ?? 0); ?></h3>
                        <p>Total Wallet Balance</p>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon warning"><i class="fas fa-gift"></i></div>
                    <div class="stat-info">
                        <h3><?php echo $wallet_stats['total_trial_credits'] ?? 0; ?></h3>
                        <p>Total Trial Credits</p>
                    </div>
                </div>
            </div>
            
            <div class="card" style="margin-bottom: 30px;">
                <h2><i class="fas fa-filter"></i> Filters & Export</h2>
                <form method="GET" action="" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px; align-items: end;">
                    <input type="hidden" name="tab" value="wallet-reconciliation">
                    <div>
                        <label>Student</label>
                        <select name="filter_student" class="form-control">
                            <option value="">All Students</option>
                            <?php if ($students_list): 
                                $students_list->data_seek(0);
                                while ($student = $students_list->fetch_assoc()): ?>
                                <option value="<?php echo $student['id']; ?>" <?php echo $filter_student == $student['id'] ? 'selected' : ''; ?>>
                                    <?php echo h($student['name']); ?>
                                </option>
                            <?php endwhile; endif; ?>
                        </select>
                    </div>
                    <div>
                        <label>Type</label>
                        <select name="filter_type" class="form-control">
                            <option value="">All Types</option>
                            <option value="purchase" <?php echo $filter_type == 'purchase' ? 'selected' : ''; ?>>Purchase</option>
                            <option value="deduction" <?php echo $filter_type == 'deduction' ? 'selected' : ''; ?>>Deduction</option>
                            <option value="refund" <?php echo $filter_type == 'refund' ? 'selected' : ''; ?>>Refund</option>
                            <option value="trial" <?php echo $filter_type == 'trial' ? 'selected' : ''; ?>>Trial</option>
                            <option value="adjustment" <?php echo $filter_type == 'adjustment' ? 'selected' : ''; ?>>Adjustment</option>
                        </select>
                    </div>
                    <div>
                        <label>Status</label>
                        <select name="filter_status" class="form-control">
                            <option value="">All Statuses</option>
                            <option value="pending" <?php echo $filter_status == 'pending' ? 'selected' : ''; ?>>Pending</option>
                            <option value="confirmed" <?php echo $filter_status == 'confirmed' ? 'selected' : ''; ?>>Confirmed</option>
                            <option value="failed" <?php echo $filter_status == 'failed' ? 'selected' : ''; ?>>Failed</option>
                            <option value="cancelled" <?php echo $filter_status == 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                        </select>
                    </div>
                    <div>
                        <label>Date From</label>
                        <input type="date" name="date_from" value="<?php echo h($date_from); ?>" class="form-control">
                    </div>
                    <div>
                        <label>Date To</label>
                        <input type="date" name="date_to" value="<?php echo h($date_to); ?>" class="form-control">
                    </div>
                    <div style="display: flex; gap: 10px;">
                        <button type="submit" class="btn-primary">
                            <i class="fas fa-search"></i> Filter
                        </button>
                        <a href="?export_wallet_csv=1&date_from=<?php echo urlencode($date_from); ?>&date_to=<?php echo urlencode($date_to); ?>&filter_student=<?php echo urlencode($filter_student); ?>&filter_type=<?php echo urlencode($filter_type); ?>&filter_status=<?php echo urlencode($filter_status); ?>" class="btn-outline">
                            <i class="fas fa-download"></i> Export CSV
                        </a>
                    </div>
                </form>
            </div>
            
            <div class="card">
                <h2><i class="fas fa-list"></i> Transaction Ledger</h2>
                <?php if ($transactions_result && $transactions_result->num_rows > 0): ?>
                <div style="overflow-x: auto;">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Student</th>
                                <th>Type</th>
                                <th>Amount</th>
                                <th>Status</th>
                                <th>Reference</th>
                                <th>Description</th>
                                <th>Date</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($txn = $transactions_result->fetch_assoc()): ?>
                            <tr>
                                <td><?php echo $txn['id']; ?></td>
                                <td>
                                    <div><?php echo h($txn['student_name']); ?></div>
                                    <small style="color: #666;"><?php echo h($txn['student_email']); ?></small>
                                </td>
                                <td>
                                    <span class="badge badge-<?php 
                                        echo $txn['type'] == 'purchase' || $txn['type'] == 'trial' ? 'success' : 
                                            ($txn['type'] == 'deduction' ? 'warning' : 'info'); 
                                    ?>">
                                        <?php echo ucfirst($txn['type']); ?>
                                    </span>
                                </td>
                                <td style="font-weight: 600; color: <?php echo $txn['type'] == 'purchase' || $txn['type'] == 'trial' ? '#28a745' : '#dc3545'; ?>;">
                                    <?php echo ($txn['type'] == 'purchase' || $txn['type'] == 'trial' ? '+' : '-'); ?>
                                    <?php echo formatCurrency(abs($txn['amount'])); ?>
                                </td>
                                <td>
                                    <span class="badge badge-<?php 
                                        echo $txn['status'] == 'confirmed' ? 'success' : 
                                            ($txn['status'] == 'pending' ? 'warning' : 'danger'); 
                                    ?>">
                                        <?php echo ucfirst($txn['status'] ?? 'confirmed'); ?>
                                    </span>
                                </td>
                                <td>
                                    <small><?php echo h($txn['reference_id'] ?? '-'); ?></small>
                                    <?php if ($txn['stripe_payment_id']): ?>
                                        <br><small style="color: #666;">Stripe: <?php echo substr($txn['stripe_payment_id'], 0, 20); ?>...</small>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo h($txn['description'] ?? '-'); ?></td>
                                <td><?php echo date('M d, Y H:i', strtotime($txn['created_at'])); ?></td>
                            </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
                <?php else: ?>
                <p style="text-align: center; color: #666; padding: 40px;">
                    <i class="fas fa-inbox" style="font-size: 3rem; color: #ddd; margin-bottom: 15px;"></i><br>
                    No transactions found for the selected filters.
                </p>
                <?php endif; ?>
                <?php if ($transactions_stmt) $transactions_stmt->close(); ?>
            </div>
            
            <div class="card" style="margin-top: 30px;">
                <h2><i class="fas fa-edit"></i> Manual Wallet Adjustment</h2>
                <form method="POST" action="" style="max-width: 600px;">
                    <input type="hidden" name="adjust_wallet" value="1">
                    <div style="margin-bottom: 15px;">
                        <label>Student</label>
                        <select name="student_id" class="form-control" required>
                            <option value="">Select Student</option>
                            <?php if ($students_list): 
                                $students_list->data_seek(0);
                                while ($student = $students_list->fetch_assoc()): ?>
                                <option value="<?php echo $student['id']; ?>">
                                    <?php echo h($student['name']); ?> (<?php echo h($student['email']); ?>)
                                </option>
                            <?php endwhile; endif; ?>
                        </select>
                    </div>
                    <div style="margin-bottom: 15px;">
                        <label>Adjustment Type</label>
                        <select name="adjustment_type" class="form-control" required>
                            <option value="add">Add Funds</option>
                            <option value="deduct">Deduct Funds</option>
                        </select>
                    </div>
                    <div style="margin-bottom: 15px;">
                        <label>Amount</label>
                        <input type="number" name="amount" step="0.01" min="0.01" class="form-control" required>
                    </div>
                    <div style="margin-bottom: 15px;">
                        <label>Reason</label>
                        <textarea name="reason" class="form-control" rows="3" placeholder="Reason for adjustment..." required></textarea>
                    </div>
                    <button type="submit" class="btn-primary">
                        <i class="fas fa-check"></i> Apply Adjustment
                    </button>
                </form>
                <?php if (isset($wallet_msg)): ?>
                    <div class="alert-success" style="margin-top: 15px;">
                        <?php echo h($wallet_msg); ?>
                    </div>
                <?php endif; ?>
                <?php if (isset($wallet_error)): ?>
                    <div class="alert-error" style="margin-top: 15px;">
                        <?php echo h($wallet_error); ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Settings Tab (Combined My Profile, Security, Classroom) -->
        <div id="settings" class="tab-content">
            <h1>Settings</h1>
            <div style="display: flex; gap: 15px; margin-bottom: 30px; border-bottom: 2px solid #dee2e6; padding-bottom: 15px;">
                <button onclick="switchAdminSettingsSubTab('profile')" class="btn-outline" id="adm-set-profile-btn" style="border-bottom: 3px solid #0b6cf5;">
                    <i class="fas fa-user-edit"></i> Profile
                </button>
                <button onclick="switchAdminSettingsSubTab('security')" class="btn-outline" id="adm-set-security-btn">
                    <i class="fas fa-lock"></i> Security
                </button>
                <button onclick="switchAdminSettingsSubTab('classroom')" class="btn-outline" id="adm-set-classroom-btn">
                    <i class="fas fa-book-open"></i> Classroom
                </button>
                <button onclick="switchAdminSettingsSubTab('global')" class="btn-outline" id="adm-set-global-btn">
                    <i class="fas fa-cog"></i> Global Settings
                </button>
                <button onclick="switchAdminSettingsSubTab('pricing')" class="btn-outline" id="adm-set-pricing-btn">
                    <i class="fas fa-dollar-sign"></i> Pricing & Plans
                </button>
            </div>
            
            <div id="settings-profile" class="admin-settings-subtab active">
                <h2>My Profile</h2>
            <div class="card">
                <form method="POST" enctype="multipart/form-data">
                    <div style="display: flex; gap: 30px; margin-bottom: 25px; align-items: flex-start;">
                        <div style="text-align: center;">
                            <img src="<?php echo h($user['profile_pic']); ?>" alt="Profile" 
                                 style="width: 120px; height: 120px; border-radius: 50%; object-fit: cover; border: 4px solid var(--primary-light);"
                                 onerror="this.src='<?php echo getAssetPath('images/placeholder-teacher.svg'); ?>'">
                            <div style="margin-top: 15px;">
                                <label class="btn-outline btn-sm" style="cursor: pointer;">
                                    <i class="fas fa-camera"></i> Change
                                    <input type="file" name="profile_pic_file" accept="image/*" style="display: none;">
                                </label>
                            </div>
                        </div>
                        <div style="flex: 1;">
                            <div class="profile-grid">
                                <div class="form-group">
                                    <label>Display Name</label>
                                    <input type="text" name="name" value="<?php echo h($user['name']); ?>">
                                </div>
                                <div class="form-group">
                                    <label>Date of Birth</label>
                                    <input type="date" name="dob" value="<?php echo $user['dob']; ?>">
                                </div>
                                <div class="form-group">
                                    <label>Calendly Link</label>
                                    <input type="url" name="calendly" value="<?php echo h($user['calendly_link']); ?>">
                                </div>
                                <div class="form-group">
                                    <label>Backup Email</label>
                                    <input type="email" name="backup_email" value="<?php echo h($user['backup_email'] ?? ''); ?>">
                                </div>
                                <div class="form-group">
                                    <label>Age</label>
                                    <input type="number" name="age" value="<?php echo h($user['age'] ?? ''); ?>">
                                </div>
                                <div class="form-group">
                                    <label>Age Visibility</label>
                                    <select name="age_visibility">
                                        <option value="private" <?php echo ($user['age_visibility'] === 'private') ? 'selected' : ''; ?>>Private</option>
                                        <option value="public" <?php echo ($user['age_visibility'] === 'public') ? 'selected' : ''; ?>>Public</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label>Bio</label>
                        <textarea name="bio" rows="4"><?php echo h($user['bio']); ?></textarea>
                    </div>
                    
                    <button type="submit" name="update_profile" class="btn-primary">
                        <i class="fas fa-save"></i> Save Changes
                    </button>
                </form>
            </div>
            </div>
            
            <div id="settings-security" class="admin-settings-subtab" style="display: none;">
                <h2>Security Settings</h2>
                <?php include __DIR__ . '/app/Views/components/password-change-form.php'; ?>
            </div>
            
            <div id="settings-classroom" class="admin-settings-subtab" style="display: none;">
                <h2>Classroom Materials</h2>
            
            <div class="card">
                <h2><i class="fas fa-plus-circle"></i> Add Material</h2>
                <form method="POST">
                    <div class="profile-grid">
                        <div class="form-group">
                            <label>Title</label>
                            <input type="text" name="title" required>
                        </div>
                        <div class="form-group">
                            <label>Type</label>
                            <select name="type">
                                <option value="link">Link / URL</option>
                                <option value="video">Video</option>
                                <option value="file">File</option>
                            </select>
                        </div>
                    </div>
                    <div class="form-group">
                        <label>Content URL</label>
                        <input type="url" name="content_url" placeholder="https://..." required>
                    </div>
                    <button type="submit" name="upload_material" class="btn-primary">
                        <i class="fas fa-plus"></i> Add Material
                    </button>
                </form>
            </div>

            <h2 style="margin-top: 30px;">Current Materials</h2>
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Title</th>
                        <th>Type</th>
                        <th>Link</th>
                        <th>Added</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while($m = $materials->fetch_assoc()): ?>
                    <tr>
                        <td><?php echo h($m['title']); ?></td>
                        <td><span class="tag <?php echo $m['type']; ?>"><?php echo ucfirst($m['type']); ?></span></td>
                        <td><a href="<?php echo h($m['link_url']); ?>" target="_blank" class="btn-outline btn-sm">Open</a></td>
                        <td><?php echo date('M d, Y', strtotime($m['created_at'])); ?></td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
        
        <div id="settings-pricing" class="admin-settings-subtab" style="display: none;">
            <h2><i class="fas fa-dollar-sign"></i> Pricing & Plans Management</h2>
            <p style="color: #666; margin-bottom: 30px;">Manage subscription plan prices for all sections. Plans are universal per section and cannot be modified by teachers.</p>
            
            <?php
            require_once __DIR__ . '/app/Models/SubscriptionPlan.php';
            $planModel = new SubscriptionPlan($conn);
            $all_plans = [];
            foreach (['kids', 'adults', 'coding'] as $track) {
                $track_plans = $planModel->getPlansByTrack($track);
                foreach ($track_plans as $plan) {
                    $plan['track'] = $track;
                    $all_plans[] = $plan;
                }
            }
            ?>
            
            <div class="card">
                <h3>Edit Plan Prices</h3>
                <form method="POST" id="pricingForm">
                    <input type="hidden" name="update_plan_prices" value="1">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Plan Name</th>
                                <th>Track</th>
                                <th>Current Price</th>
                                <th>New Price</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($all_plans as $plan): ?>
                                <tr>
                                    <td><strong><?php echo htmlspecialchars($plan['name']); ?></strong></td>
                                    <td>
                                        <span class="badge badge-info">
                                            <?php echo ucfirst($plan['track']); ?>
                                        </span>
                                    </td>
                                    <td>$<?php echo number_format($plan['price'] ?? 0, 2); ?>/month</td>
                                    <td>
                                        <input type="hidden" name="plan_ids[]" value="<?php echo intval($plan['id']); ?>">
                                        <input type="number" 
                                               name="plan_prices[]" 
                                               value="<?php echo number_format($plan['price'] ?? 0, 2); ?>" 
                                               step="0.01" 
                                               min="0" 
                                               style="width: 120px; padding: 8px; border: 2px solid #ddd; border-radius: 5px;">
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    <div style="margin-top: 20px; display: flex; gap: 10px; justify-content: flex-end;">
                        <button type="submit" class="btn-primary">
                            <i class="fas fa-save"></i> Save All Prices
                        </button>
                    </div>
                </form>
            </div>
            
            <div class="card" style="margin-top: 30px;">
                <h3><i class="fas fa-money-bill-wave"></i> Teacher Salary & Commission Management</h3>
                <p style="color: #666; margin-bottom: 20px;">Set salary rates and commission percentages for teachers. These can be set per teacher or per section.</p>
                
                <form method="POST" id="salaryForm">
                    <input type="hidden" name="update_teacher_salaries" value="1">
                    <div style="margin-bottom: 20px;">
                        <label style="display: block; margin-bottom: 10px; font-weight: 600;">Default Commission Rate (%)</label>
                        <input type="number" 
                               name="default_commission_rate" 
                               value="<?php 
                               try {
                                   $default_commission = $conn->query("SELECT value FROM admin_settings WHERE setting_key = 'default_commission_rate'");
                                   echo $default_commission && $default_commission->num_rows > 0 ? floatval($default_commission->fetch_assoc()['value']) : 50;
                               } catch (Exception $e) {
                                   echo 50; // Default fallback
                               }
                               ?>" 
                               step="0.1" 
                               min="0" 
                               max="100"
                               style="width: 200px; padding: 8px; border: 2px solid #ddd; border-radius: 5px;">
                        <small style="display: block; color: #666; margin-top: 5px;">Default commission percentage for all teachers (can be overridden per teacher)</small>
                    </div>
                    
                    <h4 style="margin-top: 30px; margin-bottom: 15px;">Per-Teacher Salary Settings</h4>
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Teacher</th>
                                <th>Hourly Rate</th>
                                <th>Commission Rate (%)</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            $teachers_for_salary = $conn->query("SELECT id, name, hourly_rate FROM users WHERE role = 'teacher' AND application_status = 'approved' ORDER BY name");
                            if ($teachers_for_salary && $teachers_for_salary->num_rows > 0):
                                while($t = $teachers_for_salary->fetch_assoc()): 
                                    $salary_info = $conn->query("SELECT commission_rate FROM teacher_salary_settings WHERE teacher_id = " . intval($t['id']));
                                    $commission_rate = $salary_info && $salary_info->num_rows > 0 ? floatval($salary_info->fetch_assoc()['commission_rate']) : null;
                            ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($t['name']); ?></td>
                                    <td>$<?php echo number_format($t['hourly_rate'] ?? 0, 2); ?>/hr</td>
                                    <td>
                                        <input type="hidden" name="teacher_ids[]" value="<?php echo intval($t['id']); ?>">
                                        <input type="number" 
                                               name="commission_rates[]" 
                                               value="<?php echo $commission_rate !== null ? $commission_rate : ''; ?>" 
                                               placeholder="Use default"
                                               step="0.1" 
                                               min="0" 
                                               max="100"
                                               style="width: 120px; padding: 8px; border: 2px solid #ddd; border-radius: 5px;">
                                    </td>
                                    <td>
                                        <a href="admin-dashboard.php?tab=users&teacher_id=<?php echo intval($t['id']); ?>" class="btn-outline btn-sm">View Details</a>
                                    </td>
                                </tr>
                            <?php 
                                endwhile;
                            else:
                            ?>
                                <tr>
                                    <td colspan="4" style="text-align: center; color: #666; padding: 20px;">No approved teachers found</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                    <div style="margin-top: 20px; display: flex; gap: 10px; justify-content: flex-end;">
                        <button type="submit" class="btn-primary">
                            <i class="fas fa-save"></i> Save Salary Settings
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Security Tab -->
        <div id="my-security" class="tab-content">
            <h1>Security Settings</h1>
            <?php include __DIR__ . '/app/Views/components/password-change-form.php'; ?>
        </div>

    </div>
</div>

<!-- Message View Modal -->
<div class="modal-overlay" id="messageModal">
    <div class="modal">
        <div class="modal-header">
            <h3 id="modalSubject">Subject</h3>
            <button class="modal-close" onclick="closeMessageModal()">&times;</button>
        </div>
        <p><strong>From:</strong> <span id="modalSender"></span></p>
        <div style="background: var(--light-gray); padding: 15px; border-radius: 8px; margin-top: 15px; white-space: pre-wrap;" id="modalMessage"></div>
    </div>
</div>

<script>
function toggleRequestFields() {
    const requestType = document.getElementById('requestType').value;
    const timeSlotFields = document.getElementById('timeSlotFields');
    const groupClassFields = document.getElementById('groupClassFields');
    
    if (requestType === 'time_slot') {
        timeSlotFields.style.display = 'block';
        groupClassFields.style.display = 'none';
        // Make time slot fields required
        document.querySelector('[name="requested_date"]').required = true;
        document.querySelector('[name="requested_time"]').required = true;
        document.querySelector('[name="duration_minutes"]').required = true;
        // Make group class fields not required
        document.querySelector('[name="group_class_track"]').required = false;
        document.querySelector('[name="group_class_date"]').required = false;
        document.querySelector('[name="group_class_time"]').required = false;
    } else {
        timeSlotFields.style.display = 'none';
        groupClassFields.style.display = 'block';
        // Make group class fields required
        document.querySelector('[name="group_class_track"]').required = true;
        document.querySelector('[name="group_class_date"]').required = true;
        document.querySelector('[name="group_class_time"]').required = true;
        // Make time slot fields not required
        document.querySelector('[name="requested_date"]').required = false;
        document.querySelector('[name="requested_time"]').required = false;
        document.querySelector('[name="duration_minutes"]').required = false;
    }
}

function viewSlotRequest(id) {
    // TODO: Implement modal or detail view
    alert('Slot request details view - ID: ' + id);
}

function viewSupportMessage(supportId, senderId, senderName, subject, message, senderRole) {
    // Create modal for viewing and replying to support message
    const modal = document.createElement('div');
    modal.className = 'action-selection-modal-overlay';
    modal.id = 'supportMessageModal';
    modal.innerHTML = `
        <div class="action-selection-modal" style="max-width: 700px;">
            <div class="modal-header">
                <h3>Support Message</h3>
                <button class="modal-close-btn" onclick="document.getElementById('supportMessageModal').remove()">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div style="padding: 20px 0;">
                <div style="margin-bottom: 20px;">
                    <p><strong>From:</strong> ${escapeHtml(senderName)} (${escapeHtml(senderRole)})</p>
                    <p><strong>Subject:</strong> ${escapeHtml(subject)}</p>
                    <p><strong>Date:</strong> ${new Date().toLocaleString()}</p>
                </div>
                <div style="background: #f8f9fa; padding: 15px; border-radius: 8px; margin-bottom: 20px; border-left: 4px solid #0b6cf5;">
                    <strong>Message:</strong>
                    <div style="margin-top: 10px; white-space: pre-wrap;">${escapeHtml(message)}</div>
                </div>
                <div class="form-group">
                    <label><strong>Reply:</strong></label>
                    <textarea id="supportReplyMessage" rows="5" class="form-control" placeholder="Type your reply here..."></textarea>
                </div>
            </div>
            <div class="modal-actions">
                <button class="btn-primary" onclick="sendSupportReply(${supportId}, ${senderId}, '${escapeHtml(senderName)}')">
                    <i class="fas fa-paper-plane"></i> Send Reply
                </button>
                <button class="btn-secondary" onclick="document.getElementById('supportMessageModal').remove()">Close</button>
            </div>
        </div>
    `;
    
    document.body.appendChild(modal);
    
    // Close on overlay click
    modal.addEventListener('click', (e) => {
        if (e.target === modal) {
            modal.remove();
        }
    });
    
    // Focus on textarea
    setTimeout(() => {
        const textarea = document.getElementById('supportReplyMessage');
        if (textarea) textarea.focus();
    }, 100);
}

async function sendSupportReply(supportId, receiverId, receiverName) {
    const messageText = document.getElementById('supportReplyMessage').value.trim();
    
    if (!messageText) {
        alert('Please enter a reply message.');
        return;
    }
    
    const sendBtn = event.target;
    const originalHTML = sendBtn.innerHTML;
    sendBtn.disabled = true;
    sendBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Sending...';
    
    try {
        const formData = new FormData();
        formData.append('receiver_id', receiverId);
        formData.append('message', messageText);
        
        const response = await fetch('send_message.php', {
            method: 'POST',
            body: formData
        });
        
        const data = await response.json();
        
        if (data.success) {
            // Mark support message as read
            const markReadForm = new FormData();
            markReadForm.append('action', 'mark_support_read');
            markReadForm.append('support_id', supportId);
            
            await fetch('admin-actions.php', {
                method: 'POST',
                body: markReadForm
            });
            
            if (typeof toast !== 'undefined') {
                toast.success('Reply sent successfully!');
            } else {
                alert('Reply sent successfully!');
            }
            
            document.getElementById('supportMessageModal').remove();
            // Refresh the page to update the message list
            window.location.reload();
        } else {
            if (typeof toast !== 'undefined') {
                toast.error(data.message || 'Failed to send reply');
            } else {
                alert('Error: ' + (data.message || 'Failed to send reply'));
            }
            sendBtn.disabled = false;
            sendBtn.innerHTML = originalHTML;
        }
    } catch (error) {
        console.error('Error sending reply:', error);
        if (typeof toast !== 'undefined') {
            toast.error('An error occurred. Please try again.');
        } else {
            alert('An error occurred. Please try again.');
        }
        sendBtn.disabled = false;
        sendBtn.innerHTML = originalHTML;
    }
}

// Make escapeHtml available globally if not already defined
if (typeof window.escapeHtml === 'undefined') {
    window.escapeHtml = function(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    };
}

function escapeHtml(text) {
    return window.escapeHtml(text);
}

function filterSupport(role) {
    const rows = document.querySelectorAll('.support-row');
    rows.forEach(row => {
        if (role === 'all' || row.dataset.role === role) {
            row.style.display = '';
        } else {
            row.style.display = 'none';
        }
    });
}

// Sub-tab switching functions for admin dashboard
function switchReportsSubTab(subTab) {
    document.querySelectorAll('.reports-subtab').forEach(el => el.style.display = 'none');
    document.querySelectorAll('#rep-analytics-btn, #rep-reports-btn').forEach(btn => {
        if (btn) btn.style.borderBottom = 'none';
    });
    
    const targetTab = document.getElementById('reports-' + subTab);
    const targetBtn = document.getElementById('rep-' + subTab + '-btn');
    if (targetTab) targetTab.style.display = 'block';
    if (targetBtn) targetBtn.style.borderBottom = '3px solid #0b6cf5';
}

// Role Change Modal
function showRoleModal(userId, currentRole) {
    let modal = document.getElementById('roleModal');
    if (!modal) {
        const modalHtml = `
            <div id="roleModal" class="modal" style="display: none; position: fixed; z-index: 1000; left: 0; top: 0; width: 100%; height: 100%; overflow: auto; background-color: rgba(0,0,0,0.4);">
                <div class="modal-content" style="background-color: #fefefe; margin: 15% auto; padding: 20px; border: 1px solid #888; width: 90%; max-width: 500px; border-radius: 10px;">
                    <span class="close" onclick="closeRoleModal()" style="color: #aaa; float: right; font-size: 28px; font-weight: bold; cursor: pointer;">&times;</span>
                    <h2>Change User Role</h2>
                    <form method="POST" id="roleForm">
                        <input type="hidden" name="change_user_role" value="1">
                        <input type="hidden" name="user_id" id="roleUserId">
                        <div style="margin-bottom: 20px;">
                            <label>New Role</label>
                            <select name="new_role" id="roleSelect" class="form-control" required>
                                <option value="student">Student</option>
                                <option value="teacher">Teacher</option>
                                <option value="admin">Admin</option>
                            </select>
                        </div>
                        <div style="display: flex; gap: 10px; justify-content: flex-end;">
                            <button type="button" onclick="closeRoleModal()" class="btn-outline">Cancel</button>
                            <button type="submit" class="btn-primary">Change Role</button>
                        </div>
                    </form>
                </div>
            </div>
        `;
        document.body.insertAdjacentHTML('beforeend', modalHtml);
        modal = document.getElementById('roleModal');
    }
    document.getElementById('roleUserId').value = userId;
    document.getElementById('roleSelect').value = currentRole;
    modal.style.display = 'block';
}

function closeRoleModal() {
    const modal = document.getElementById('roleModal');
    if (modal) modal.style.display = 'none';
}

// Section Approval Modal
function showCategoryModal(teacherId, currentCategories) {
    let modal = document.getElementById('categoryModal');
    if (!modal) {
        const modalHtml = `
            <div id="categoryModal" class="modal" style="display: none; position: fixed; z-index: 1000; left: 0; top: 0; width: 100%; height: 100%; overflow: auto; background-color: rgba(0,0,0,0.4);">
                <div class="modal-content" style="background-color: #fefefe; margin: 5% auto; padding: 30px; border: 1px solid #888; width: 90%; max-width: 600px; border-radius: 10px; max-height: 90vh; overflow-y: auto;">
                    <span class="close" onclick="closeCategoryModal()" style="color: #aaa; float: right; font-size: 28px; font-weight: bold; cursor: pointer;">&times;</span>
                    <h2><i class="fas fa-tags"></i> Section Approval Management</h2>
                    <p style="color: #666; margin-bottom: 20px;">Approve or reject this teacher for specific sections (Kids, Adults, Coding).</p>
                    <form method="POST" id="categoryForm">
                        <input type="hidden" name="manage_section_approvals" value="1">
                        <input type="hidden" name="teacher_id" id="categoryTeacherId">
                        <div style="display: flex; flex-direction: column; gap: 15px; margin-bottom: 25px;">
                            <div style="border: 2px solid #e0e0e0; border-radius: 8px; padding: 15px;">
                                <label style="display: flex; align-items: center; justify-content: space-between; cursor: pointer;">
                                    <div>
                                        <strong style="color: #ff6b9d;"><i class="fas fa-child"></i> Kids Classes</strong>
                                        <p style="margin: 5px 0 0 0; color: #666; font-size: 0.9rem;">Young Learners (ages 3-11)</p>
                                    </div>
                                    <div style="display: flex; gap: 10px;">
                                        <label style="cursor: pointer; padding: 8px 15px; border-radius: 5px; background: #28a745; color: white; margin: 0;">
                                            <input type="radio" name="kids_status" value="approved" style="display: none;">
                                            <i class="fas fa-check"></i> Approve
                                        </label>
                                        <label style="cursor: pointer; padding: 8px 15px; border-radius: 5px; background: #dc3545; color: white; margin: 0;">
                                            <input type="radio" name="kids_status" value="rejected" style="display: none;">
                                            <i class="fas fa-times"></i> Reject
                                        </label>
                                        <label style="cursor: pointer; padding: 8px 15px; border-radius: 5px; background: #6c757d; color: white; margin: 0;">
                                            <input type="radio" name="kids_status" value="none" checked style="display: none;">
                                            <i class="fas fa-minus"></i> None
                                        </label>
                                    </div>
                                </label>
                            </div>
                            <div style="border: 2px solid #e0e0e0; border-radius: 8px; padding: 15px;">
                                <label style="display: flex; align-items: center; justify-content: space-between; cursor: pointer;">
                                    <div>
                                        <strong style="color: #004080;"><i class="fas fa-user-graduate"></i> Adults Classes</strong>
                                        <p style="margin: 5px 0 0 0; color: #666; font-size: 0.9rem;">General English for adults</p>
                                    </div>
                                    <div style="display: flex; gap: 10px;">
                                        <label style="cursor: pointer; padding: 8px 15px; border-radius: 5px; background: #28a745; color: white; margin: 0;">
                                            <input type="radio" name="adults_status" value="approved" style="display: none;">
                                            <i class="fas fa-check"></i> Approve
                                        </label>
                                        <label style="cursor: pointer; padding: 8px 15px; border-radius: 5px; background: #dc3545; color: white; margin: 0;">
                                            <input type="radio" name="adults_status" value="rejected" style="display: none;">
                                            <i class="fas fa-times"></i> Reject
                                        </label>
                                        <label style="cursor: pointer; padding: 8px 15px; border-radius: 5px; background: #6c757d; color: white; margin: 0;">
                                            <input type="radio" name="adults_status" value="none" checked style="display: none;">
                                            <i class="fas fa-minus"></i> None
                                        </label>
                                    </div>
                                </label>
                            </div>
                            <div style="border: 2px solid #e0e0e0; border-radius: 8px; padding: 15px;">
                                <label style="display: flex; align-items: center; justify-content: space-between; cursor: pointer;">
                                    <div>
                                        <strong style="color: #28a745;"><i class="fas fa-code"></i> Coding Classes</strong>
                                        <p style="margin: 5px 0 0 0; color: #666; font-size: 0.9rem;">English for Coding/Tech</p>
                                    </div>
                                    <div style="display: flex; gap: 10px;">
                                        <label style="cursor: pointer; padding: 8px 15px; border-radius: 5px; background: #28a745; color: white; margin: 0;">
                                            <input type="radio" name="coding_status" value="approved" style="display: none;">
                                            <i class="fas fa-check"></i> Approve
                                        </label>
                                        <label style="cursor: pointer; padding: 8px 15px; border-radius: 5px; background: #dc3545; color: white; margin: 0;">
                                            <input type="radio" name="coding_status" value="rejected" style="display: none;">
                                            <i class="fas fa-times"></i> Reject
                                        </label>
                                        <label style="cursor: pointer; padding: 8px 15px; border-radius: 5px; background: #6c757d; color: white; margin: 0;">
                                            <input type="radio" name="coding_status" value="none" checked style="display: none;">
                                            <i class="fas fa-minus"></i> None
                                        </label>
                                    </div>
                                </label>
                            </div>
                        </div>
                        <div style="display: flex; gap: 10px; justify-content: flex-end;">
                            <button type="button" onclick="closeCategoryModal()" class="btn-outline">Cancel</button>
                            <button type="submit" class="btn-primary"><i class="fas fa-save"></i> Save Approvals</button>
                        </div>
                    </form>
                </div>
            </div>
        `;
        document.body.insertAdjacentHTML('beforeend', modalHtml);
        modal = document.getElementById('categoryModal');
    }
    document.getElementById('categoryTeacherId').value = teacherId;
    const cats = currentCategories ? currentCategories.split(',') : [];
    // Set current status for each section
    const kidsApproved = cats.includes('young_learners');
    const adultsApproved = cats.includes('adults');
    const codingApproved = cats.includes('coding');
    
    // Set radio buttons based on current categories
    const kidsApprovedRadio = modal.querySelector('input[name="kids_status"][value="approved"]');
    const kidsNoneRadio = modal.querySelector('input[name="kids_status"][value="none"]');
    const adultsApprovedRadio = modal.querySelector('input[name="adults_status"][value="approved"]');
    const adultsNoneRadio = modal.querySelector('input[name="adults_status"][value="none"]');
    const codingApprovedRadio = modal.querySelector('input[name="coding_status"][value="approved"]');
    const codingNoneRadio = modal.querySelector('input[name="coding_status"][value="none"]');
    
    if (kidsApproved && kidsApprovedRadio) {
        kidsApprovedRadio.checked = true;
        kidsApprovedRadio.closest('label').style.background = '#28a745';
    } else if (kidsNoneRadio) {
        kidsNoneRadio.checked = true;
    }
    
    if (adultsApproved && adultsApprovedRadio) {
        adultsApprovedRadio.checked = true;
        adultsApprovedRadio.closest('label').style.background = '#28a745';
    } else if (adultsNoneRadio) {
        adultsNoneRadio.checked = true;
    }
    
    if (codingApproved && codingApprovedRadio) {
        codingApprovedRadio.checked = true;
        codingApprovedRadio.closest('label').style.background = '#28a745';
    } else if (codingNoneRadio) {
        codingNoneRadio.checked = true;
    }
    
    // Add visual feedback for radio button selection
    modal.querySelectorAll('input[type="radio"]').forEach(radio => {
        radio.addEventListener('change', function() {
            const label = this.closest('label');
            const parent = label.parentElement;
            parent.querySelectorAll('label').forEach(l => {
                const input = l.querySelector('input[type="radio"]');
                if (input) {
                    l.style.background = input.value === 'approved' ? '#28a745' : 
                                        input.value === 'rejected' ? '#dc3545' : '#6c757d';
                }
            });
        });
    });
    
    modal.style.display = 'block';
}

function closeCategoryModal() {
    const modal = document.getElementById('categoryModal');
    if (modal) modal.style.display = 'none';
}

// Wallet Management Modal
function showWalletModal(studentId, studentName, currentBalance) {
    let modal = document.getElementById('walletModal');
    if (!modal) {
        const modalHtml = `
            <div id="walletModal" class="modal" style="display: none; position: fixed; z-index: 1000; left: 0; top: 0; width: 100%; height: 100%; overflow: auto; background-color: rgba(0,0,0,0.4);">
                <div class="modal-content" style="background-color: #fefefe; margin: 10% auto; padding: 30px; border: 1px solid #888; width: 90%; max-width: 500px; border-radius: 10px;">
                    <span class="close" onclick="closeWalletModal()" style="color: #aaa; float: right; font-size: 28px; font-weight: bold; cursor: pointer;">&times;</span>
                    <h2><i class="fas fa-wallet"></i> Wallet Management</h2>
                    <p style="color: #666; margin-bottom: 20px;">Manage wallet balance for <strong id="walletStudentName"></strong></p>
                    <p style="font-size: 1.2rem; margin-bottom: 20px;">Current Balance: <strong id="walletCurrentBalance" style="color: #0b6cf5;"></strong></p>
                    <form method="POST" id="walletForm">
                        <input type="hidden" name="adjust_wallet" value="1">
                        <input type="hidden" name="student_id" id="walletStudentId">
                        <div style="margin-bottom: 20px;">
                            <label style="display: block; margin-bottom: 8px; font-weight: 600;">Action</label>
                            <select name="adjustment_type" id="walletAdjustmentType" class="form-control" required>
                                <option value="add">Add Funds</option>
                                <option value="deduct">Deduct Funds</option>
                            </select>
                        </div>
                        <div style="margin-bottom: 20px;">
                            <label style="display: block; margin-bottom: 8px; font-weight: 600;">Amount ($)</label>
                            <input type="number" name="amount" step="0.01" min="0.01" class="form-control" required placeholder="0.00">
                        </div>
                        <div style="margin-bottom: 20px;">
                            <label style="display: block; margin-bottom: 8px; font-weight: 600;">Reason</label>
                            <textarea name="reason" class="form-control" rows="3" placeholder="Reason for this adjustment...">Manual adjustment by admin</textarea>
                        </div>
                        <div style="display: flex; gap: 10px; justify-content: flex-end;">
                            <button type="button" onclick="closeWalletModal()" class="btn-outline">Cancel</button>
                            <button type="submit" class="btn-primary"><i class="fas fa-save"></i> Apply Adjustment</button>
                        </div>
                    </form>
                </div>
            </div>
        `;
        document.body.insertAdjacentHTML('beforeend', modalHtml);
        modal = document.getElementById('walletModal');
    }
    document.getElementById('walletStudentId').value = studentId;
    document.getElementById('walletStudentName').textContent = studentName;
    document.getElementById('walletCurrentBalance').textContent = '$' + parseFloat(currentBalance).toFixed(2);
    modal.style.display = 'block';
}

function closeWalletModal() {
    const modal = document.getElementById('walletModal');
    if (modal) modal.style.display = 'none';
}

// Close modals when clicking outside
window.onclick = function(event) {
    const roleModal = document.getElementById('roleModal');
    const categoryModal = document.getElementById('categoryModal');
    const walletModal = document.getElementById('walletModal');
    if (event.target == roleModal) {
        closeRoleModal();
    }
    if (event.target == categoryModal) {
        closeCategoryModal();
    }
    if (event.target == walletModal) {
        closeWalletModal();
    }
}

function highlightLesson(lessonId) {
    const row = document.getElementById('lesson-' + lessonId);
    if (row) {
        row.style.background = '#fff3cd';
        setTimeout(() => {
            row.scrollIntoView({ behavior: 'smooth', block: 'center' });
            setTimeout(() => {
                row.style.background = '';
            }, 3000);
        }, 100);
    }
}

function switchUsersSubTab(subTab) {
    document.querySelectorAll('.users-subtab').forEach(el => el.style.display = 'none');
    document.querySelectorAll('#usr-all-btn, #usr-teachers-btn, #usr-students-btn').forEach(btn => {
        if (btn) btn.style.borderBottom = 'none';
    });
    
    const targetTab = document.getElementById('users-' + subTab);
    const targetBtn = document.getElementById('usr-' + subTab + '-btn');
    if (targetTab) targetTab.style.display = 'block';
    if (targetBtn) targetBtn.style.borderBottom = '3px solid #0b6cf5';
}

function switchAdminSettingsSubTab(subTab) {
    document.querySelectorAll('.admin-settings-subtab').forEach(el => el.style.display = 'none');
    document.querySelectorAll('#adm-set-profile-btn, #adm-set-security-btn, #adm-set-classroom-btn, #adm-set-global-btn').forEach(btn => {
        if (btn) btn.style.borderBottom = 'none';
    });
    
    const targetTab = document.getElementById('settings-' + subTab);
    const targetBtn = document.getElementById('adm-set-' + subTab + '-btn');
    if (targetTab) targetTab.style.display = 'block';
    if (targetBtn) targetBtn.style.borderBottom = '3px solid #0b6cf5';
}

function switchTab(id) {
    if (event) event.preventDefault();
    
    document.querySelectorAll('.tab-content').forEach(el => el.classList.remove('active'));
    
    // Handle sub-tab navigation
    if (id === 'reports') {
        const hashParts = window.location.hash.split('-');
        if (hashParts.length > 1 && (hashParts[1] === 'analytics' || hashParts[1] === 'reports')) {
            switchReportsSubTab(hashParts[1]);
        } else {
            switchReportsSubTab('analytics'); // Default to analytics
        }
    }
    if (id === 'users') {
        const hashParts = window.location.hash.split('-');
        if (hashParts.length > 1 && (hashParts[1] === 'teachers' || hashParts[1] === 'students')) {
            switchUsersSubTab(hashParts[1]);
        } else {
            switchUsersSubTab('teachers'); // Default to teachers
        }
    }
    if (id === 'settings') {
        const hashParts = window.location.hash.split('-');
        if (hashParts.length > 1 && (hashParts[1] === 'profile' || hashParts[1] === 'security' || hashParts[1] === 'classroom')) {
            switchAdminSettingsSubTab(hashParts[1]);
        } else {
            switchAdminSettingsSubTab('profile'); // Default to profile
        }
    }
    const targetTab = document.getElementById(id);
    if (targetTab) {
        targetTab.classList.add('active');
    }
    
    document.querySelectorAll('.sidebar-menu a').forEach(el => el.classList.remove('active'));
    const activeLink = document.querySelector(`.sidebar-menu a[onclick*="${id}"]`);
    if (activeLink) activeLink.classList.add('active');
    
    // Also check sidebar header button
    const sidebarHeader = document.querySelector('.sidebar-header a');
    if (sidebarHeader && id === 'dashboard') {
        sidebarHeader.classList.add('active');
    }
    
    // Scroll to top of main content
    const mainContent = document.querySelector('.main');
    if (mainContent) mainContent.scrollTop = 0;
    
    // Update URL hash without triggering page reload
    if (window.location.hash !== '#' + id) {
        window.history.pushState(null, null, '#' + id);
    }
}

document.addEventListener('DOMContentLoaded', function() {
    const hash = window.location.hash.substring(1);
    if (hash && document.getElementById(hash)) {
        switchTab(hash);
    } else {
        // Default to dashboard if no hash
        const dashboardTab = document.getElementById('dashboard');
        if (dashboardTab) {
            dashboardTab.classList.add('active');
        }
    }
});

// Handle browser back/forward buttons (hashchange event)
window.addEventListener('hashchange', function() {
    const hash = window.location.hash.substring(1);
    if (hash && document.getElementById(hash)) {
        switchTab(hash);
    }
});

function filterSupport(role) {
    document.querySelectorAll('.support-row').forEach(row => {
        if (role === 'all' || row.dataset.role === role) {
            row.style.display = '';
        } else {
            row.style.display = 'none';
        }
    });
}

function viewMessage(id, sender, subject, message) {
    document.getElementById('modalSender').textContent = sender;
    document.getElementById('modalSubject').textContent = subject;
    document.getElementById('modalMessage').textContent = message;
    document.getElementById('messageModal').classList.add('active');
}

function closeMessageModal() {
    document.getElementById('messageModal').classList.remove('active');
}

function toggleMobileSidebar() {
    document.querySelector('.sidebar').classList.toggle('active');
    document.querySelector('.sidebar-overlay').classList.toggle('active');
}

// Handle demote confirmation with toast
async function handleDemoteClick(event, button) {
    event.preventDefault();
    if (typeof toast !== 'undefined') {
        const confirmed = await toast.confirm('Demote this teacher to student?', 'Confirm Demotion');
        if (confirmed) {
            button.closest('form').submit();
        }
        return false;
    } else {
        if (confirm('Demote this teacher to student?')) {
            return true;
        }
        return false;
    }
}
</script>

</body>
</html>
<?php
// End output buffering
ob_end_flush();
?>
