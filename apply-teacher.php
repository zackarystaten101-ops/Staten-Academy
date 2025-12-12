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

// Students and new_students can apply (Teachers/Admins already have roles)
 if (!isset($_SESSION['user_id'])) {
     ob_end_clean(); // Clear output buffer before redirect
     header("Location: login.php");
     exit();
 }

// Allow students and new_students to apply
if (!in_array($_SESSION['user_role'], ['student', 'new_student'])) {
    ob_end_clean();
    header("Location: index.php");
    exit();
}

$user_role = $_SESSION['user_role'];
$msg = "";
$msg_type = ""; // 'success' or 'error'

// Check current status
$status = 'none';
$stmt = $conn->prepare("SELECT application_status FROM users WHERE id = ?");
$stmt->bind_param("i", $_SESSION['user_id']);
$stmt->execute();
$stmt->bind_result($status);
$stmt->fetch();
$stmt->close();

if ($_SERVER["REQUEST_METHOD"] == "POST") {
     $calendly = $_POST['calendly'];
     $bio = $_POST['bio'];
     $profile_pic = null;
     $error = "";
     
     // Handle mandatory file upload
     if (isset($_FILES['profile_pic_file']) && $_FILES['profile_pic_file']['error'] === UPLOAD_ERR_OK) {
         $file = $_FILES['profile_pic_file'];
         $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
         $allowed = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
         
         // Validate file size (max 5MB)
         if ($file['size'] > 5 * 1024 * 1024) {
             $error = "File size exceeds 5MB limit.";
         } elseif (in_array($ext, $allowed)) {
             $filename = 'teacher_' . $_SESSION['user_id'] . '_' . time() . '.' . $ext;
             
             // Determine upload directory - works for both localhost and cPanel
             $upload_base = __DIR__;
             $public_images_dir = $upload_base . DIRECTORY_SEPARATOR . 'public' . DIRECTORY_SEPARATOR . 'assets' . DIRECTORY_SEPARATOR . 'images';
             $flat_images_dir = $upload_base . DIRECTORY_SEPARATOR . 'assets' . DIRECTORY_SEPARATOR . 'images';
             
             // Check which directory structure exists and create if needed
             if (is_dir($public_images_dir)) {
                 // Local dev structure: /public/assets/images/
                 $target_dir = $public_images_dir;
                 $profile_pic = '/assets/images/' . $filename;
             } elseif (is_dir($flat_images_dir)) {
                 // cPanel flat structure: /assets/images/
                 $target_dir = $flat_images_dir;
                 $profile_pic = '/assets/images/' . $filename;
             } else {
                 // Create directory based on what exists
                 if (is_dir($upload_base . DIRECTORY_SEPARATOR . 'public')) {
                     // Create in public/assets/images/
                     $target_dir = $public_images_dir;
                     $profile_pic = '/assets/images/' . $filename;
                 } else {
                     // Create in assets/images/ (cPanel flat structure)
                     $target_dir = $flat_images_dir;
                     $profile_pic = '/assets/images/' . $filename;
                 }
                 // Create directory with proper permissions
                 if (!is_dir($target_dir)) {
                     @mkdir($target_dir, 0755, true);
                 }
            }
            
            $target_path = $target_dir . DIRECTORY_SEPARATOR . $filename;
            
            // Security check: verify file was actually uploaded
            if (!is_uploaded_file($file['tmp_name'])) {
                $error = "Invalid upload detected. Security check failed.";
                error_log("Security check failed for: " . $file['tmp_name']);
            } elseif (!move_uploaded_file($file['tmp_name'], $target_path)) {
                $error = "Failed to upload image. Please check directory permissions.";
            } else {
                // File uploaded successfully
            }
         } else {
             $error = "Invalid file format. Please upload JPG, PNG, GIF, or WebP.";
         }
     } else {
         if (isset($_FILES['profile_pic_file'])) {
             $upload_error = $_FILES['profile_pic_file']['error'];
             if ($upload_error === UPLOAD_ERR_INI_SIZE || $upload_error === UPLOAD_ERR_FORM_SIZE) {
                 $error = "File size exceeds maximum allowed size.";
             } elseif ($upload_error === UPLOAD_ERR_NO_FILE) {
                 $error = "Profile picture is required.";
             } else {
                 $error = "Upload error. Please try again.";
             }
         } else {
             $error = "Profile picture is required.";
         }
     }
     
     if (!$error) {
         // Update user record with application
         $stmt = $conn->prepare("UPDATE users SET calendly_link = ?, bio = ?, profile_pic = ?, application_status = 'pending' WHERE id = ?");
         $stmt->bind_param("sssi", $calendly, $bio, $profile_pic, $_SESSION['user_id']);
         
        if ($stmt->execute()) {
            $status = 'pending';
            $msg = "Application submitted successfully! Waiting for admin approval.";
            $msg_type = "success";
        } else {
            $msg = "Error submitting application. Please try again.";
            $msg_type = "error";
        }
        $stmt->close();
    } else {
        $msg = $error;
        $msg_type = "error";
    }
 }
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Apply to Teach - Staten Academy</title>
    <?php
    // Load dashboard functions for getLogoPath
    if (file_exists(__DIR__ . '/app/Views/components/dashboard-functions.php')) {
        require_once __DIR__ . '/app/Views/components/dashboard-functions.php';
    }
    
    // Ensure getAssetPath is available - use same logic as index.php
    if (!function_exists('getAssetPath')) {
        function getAssetPath($asset) {
            // Remove leading slash if present
            $asset = ltrim($asset, '/');
            
            // Build base asset path
            if (strpos($asset, 'assets/') === 0) {
                $assetPath = $asset;
            } else {
                $assetPath = 'assets/' . $asset;
            }
            
            // Get base path from SCRIPT_NAME - more reliable for subdirectories
            $scriptName = $_SERVER['SCRIPT_NAME'] ?? '';
            $basePath = dirname($scriptName);
            $basePath = str_replace('\\', '/', $basePath);
            
            // Handle root case
            if ($basePath === '.' || $basePath === '/' || empty($basePath)) {
                $basePath = '';
            } else {
                // Ensure leading slash and remove trailing
                $basePath = '/' . trim($basePath, '/');
            }
            
            // Check if file exists in public/ directory (local development)
            $publicAssetPath = __DIR__ . DIRECTORY_SEPARATOR . 'public' . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $assetPath);
            
            // If file exists in public/ directory, use that path
            if (file_exists($publicAssetPath)) {
                // For local dev with public/ directory structure
                return $basePath . '/public/' . $assetPath;
            }
            
            // For cPanel flat structure (files directly in public_html/assets/)
            return $basePath . '/' . $assetPath;
        }
    }
    ?>
    <link rel="stylesheet" href="<?php echo getAssetPath('styles.css'); ?>">
    <link rel="stylesheet" href="<?php echo getAssetPath('css/auth.css'); ?>">
    <link rel="stylesheet" href="<?php echo getAssetPath('css/dashboard.css'); ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        body { 
            background: #f4f4f9; 
            padding: 20px; 
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, sans-serif;
        }
        .container { 
            max-width: 700px; 
            margin: 40px auto; 
            background: white; 
            padding: 40px; 
            border-radius: 12px; 
            box-shadow: 0 4px 20px rgba(0,0,0,0.1);
        }
        h1 { 
            color: #004080; 
            margin-top: 0; 
            margin-bottom: 10px;
            font-size: 2rem;
        }
        .subtitle {
            color: #666;
            margin-bottom: 30px;
            font-size: 1.1rem;
        }
        .form-group {
            margin-bottom: 25px;
        }
        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: #333;
        }
        .form-group input[type="text"],
        .form-group input[type="url"],
        .form-group input[type="file"],
        .form-group textarea {
            width: 100%;
            padding: 12px;
            border: 1px solid #ddd;
            border-radius: 6px;
            font-size: 1rem;
            box-sizing: border-box;
        }
        .form-group textarea {
            resize: vertical;
            min-height: 120px;
        }
        .form-group small {
            display: block;
            margin-top: 5px;
            color: #666;
            font-size: 0.9rem;
        }
        button { 
            background: #0b6cf5; 
            color: white; 
            padding: 14px 30px; 
            border: none; 
            border-radius: 6px; 
            cursor: pointer; 
            font-size: 1rem;
            font-weight: 600;
            transition: background 0.3s;
            width: 100%;
        }
        button:hover { 
            background: #0056b3; 
        }
        .status-box {
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 20px;
            text-align: center;
        }
        .status-box.pending {
            background: #fff3cd;
            border: 2px solid #ffc107;
            color: #856404;
        }
        .status-box.approved {
            background: #d4edda;
            border: 2px solid #28a745;
            color: #155724;
        }
        .status-box.rejected {
            background: #f8d7da;
            border: 2px solid #dc3545;
            color: #721c24;
        }
        .status-box h3 {
            margin-top: 0;
            margin-bottom: 10px;
        }
        .alert {
            padding: 15px;
            border-radius: 6px;
            margin-bottom: 20px;
        }
        .alert-success {
            background: #d4edda;
            border: 1px solid #c3e6cb;
            color: #155724;
        }
        .alert-error {
            background: #f8d7da;
            border: 1px solid #f5c6cb;
            color: #721c24;
        }
        .back-link {
            margin-top: 30px;
            text-align: center;
        }
        .back-link a {
            color: #0b6cf5;
            text-decoration: none;
            font-weight: 500;
        }
        .back-link a:hover {
            text-decoration: underline;
        }
        @media (max-width: 768px) {
            body {
                padding: 10px;
            }
            .container {
                padding: 20px;
                margin: 20px auto;
            }
        }
    </style>
</head>
<body>
     <header class="site-header">
          <div class="header-left"><a href="index.php"><img src="<?php echo getLogoPath(); ?>" alt="Logo" class="site-logo"></a></div>
          <!-- User profile section (clean component) -->
          <?php include 'header-user.php'; ?>
      </header>

    <div class="container">
        <h1><i class="fas fa-chalkboard-teacher"></i> Become a Teacher</h1>
        <p class="subtitle">Share your knowledge and help students learn. Join our community of educators!</p>
        
        <?php if ($msg): ?>
            <div class="alert alert-<?php echo $msg_type === 'success' ? 'success' : 'error'; ?>">
                <i class="fas fa-<?php echo $msg_type === 'success' ? 'check-circle' : 'exclamation-circle'; ?>"></i> <?php echo htmlspecialchars($msg); ?>
            </div>
        <?php endif; ?>

        <?php if ($status === 'pending'): ?>
            <div class="status-box pending">
                <h3><i class="fas fa-clock"></i> Application Pending</h3>
                <p>We have received your application. An administrator will review it shortly. You will be notified once a decision has been made.</p>
                <p style="margin-top: 15px; font-size: 0.9rem;"><strong>What happens next?</strong><br>
                - Our team will review your qualifications<br>
                - We'll check your profile information<br>
                - You'll receive an email notification with the decision</p>
            </div>
            <div class="back-link">
                <a href="<?php echo in_array($user_role, ['student', 'new_student']) ? 'student-dashboard.php' : 'index.php'; ?>"><i class="fas fa-arrow-left"></i> Back to Dashboard</a>
            </div>
        <?php elseif ($status === 'approved'): ?>
            <div class="status-box approved">
                 <h3><i class="fas fa-check-circle"></i> Application Approved!</h3>
                 <p><strong>Congratulations!</strong> Your application has been approved. You are now a teacher on Staten Academy.</p>
                 <p style="margin-top: 15px;">Please <a href="logout.php" style="color: #155724; font-weight: bold;">log out and log back in</a> to access your teacher dashboard with all the new features available to educators.</p>
             </div>
             <div class="back-link">
                 <a href="logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a>
             </div>
        <?php elseif ($status === 'rejected'): ?>
            <div class="status-box rejected">
                 <h3><i class="fas fa-times-circle"></i> Application Status</h3>
                 <p>Your previous application was not approved at this time.</p>
                 <p style="margin-top: 15px;">If you believe this was a mistake or have additional qualifications to add, please contact our support team.</p>
             </div>
             <div class="back-link">
                 <a href="support_contact.php"><i class="fas fa-headset"></i> Contact Support</a> | 
                 <a href="<?php echo in_array($user_role, ['student', 'new_student']) ? 'student-dashboard.php' : 'index.php'; ?>"><i class="fas fa-arrow-left"></i> Back to Dashboard</a>
             </div>
        <?php else: ?>
             <form method="POST" enctype="multipart/form-data">
                 <div class="form-group">
                     <label><i class="fas fa-image"></i> Profile Picture *</label>
                     <input type="file" name="profile_pic_file" accept="image/jpeg,image/jpg,image/png,image/gif,image/webp" required>
                     <small>Upload a professional photo (JPG, PNG, GIF, or WebP format, max 5MB). This will be displayed on your teacher profile.</small>
                 </div>
                 <div class="form-group">
                     <label><i class="fas fa-calendar-alt"></i> Calendly URL *</label>
                     <input type="url" name="calendly" placeholder="https://calendly.com/your-name" required>
                     <small>Provide your Calendly scheduling link so students can book lessons with you.</small>
                 </div>
                 <div class="form-group">
                     <label><i class="fas fa-user-edit"></i> Bio / Teaching Experience *</label>
                     <textarea name="bio" rows="6" placeholder="Tell us about your teaching experience, qualifications, and what subjects or topics you'd like to teach..." required></textarea>
                     <small>Share your background, qualifications, teaching style, and areas of expertise. This helps students find the right teacher for them.</small>
                 </div>
                 <button type="submit"><i class="fas fa-paper-plane"></i> Submit Application</button>
             </form>
             <div class="back-link">
                 <a href="<?php echo in_array($user_role, ['student', 'new_student']) ? 'student-dashboard.php' : 'index.php'; ?>"><i class="fas fa-arrow-left"></i> Back to Dashboard</a>
             </div>
         <?php endif; ?>
    </div>
    <script src="<?php echo getAssetPath('js/menu.js'); ?>" defer></script>
</body>
</html>
<?php
// End output buffering
ob_end_flush();
?>
