<?php
session_start();
require_once 'db.php';

// Only Students can apply (Teachers/Admins already have roles)
 if (!isset($_SESSION['user_id'])) {
     header("Location: login.php");
     exit();
 }

if ($_SESSION['user_role'] !== 'student') {
    echo "You are already a teacher or admin.";
    exit();
}

$msg = "";

// Check current status
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
         $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
         $allowed = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
         
         if (in_array(strtolower($ext), $allowed)) {
             $filename = 'teacher_' . $_SESSION['user_id'] . '_' . time() . '.' . $ext;
             $target_path = 'images/' . $filename;
             if (move_uploaded_file($file['tmp_name'], $target_path)) {
                 $profile_pic = $target_path;
             } else {
                 $error = "Failed to upload image. Please try again.";
             }
         } else {
             $error = "Invalid file format. Please upload JPG, PNG, GIF, or WebP.";
         }
     } else {
         $error = "Profile picture is required.";
     }
     
     if (!$error) {
         // Update user record
         $stmt = $conn->prepare("UPDATE users SET calendly_link = ?, bio = ?, profile_pic = ?, application_status = 'pending' WHERE id = ?");
         $stmt->bind_param("sssi", $calendly, $bio, $profile_pic, $_SESSION['user_id']);
         
         if ($stmt->execute()) {
             $status = 'pending';
             $msg = "Application submitted successfully! Waiting for admin approval.";
         } else {
             $msg = "Error submitting application.";
         }
         $stmt->close();
     } else {
         $msg = $error;
     }
 }
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Apply to Teach - Staten Academy</title>
    <link rel="stylesheet" href="styles.css">
    <link rel="stylesheet" href="css/auth.css">
    <style>
        body { background: #f4f4f9; padding: 40px; }
        .container { max-width: 600px; margin: 0 auto; background: white; padding: 40px; border-radius: 10px; box-shadow: 0 4px 12px rgba(0,0,0,0.1); }
        h1 { color: #004080; margin-top: 0; }
        button { background: #0b6cf5; color: white; padding: 12px 20px; border: none; border-radius: 5px; cursor: pointer; font-size: 1rem; }
        button:hover { background: #0056b3; }
    </style>
</head>
<body>
     <header class="site-header">
          <div class="header-left"><a href="index.php"><img src="logo.png" alt="Logo" class="site-logo"></a></div>
          <!-- User profile section (clean component) -->
          <?php include 'header-user.php'; ?>
      </header>

    <div class="container">
        <h1>Become a Teacher</h1>
        
        <?php if ($msg): ?>
            <p style="color: green; font-weight: bold;"><?php echo $msg; ?></p>
        <?php endif; ?>

        <?php if ($status === 'pending'): ?>
            <div class="status-box pending">
                <h3>Application Pending</h3>
                <p>We have received your application. An administrator will review it shortly.</p>
            </div>
        <?php elseif ($status === 'approved'): ?>
            <div class="status-box approved">
                 <h3>Approved!</h3>
                 <p>You are now a teacher. Please <a href="login.php">log in again</a> to access your dashboard.</p>
             </div>
        <?php else: ?>
             <p>Join our team of educators! Please provide your details below.</p>
             <form method="POST" enctype="multipart/form-data">
                 <div class="form-group">
                     <label>Profile Picture *</label>
                     <p style="color: #666; font-size: 0.9rem; margin-bottom: 10px;">Upload a professional photo from your computer. Formats: JPG, PNG, GIF, WebP (Max 5MB)</p>
                     <input type="file" name="profile_pic_file" accept="image/*" required>
                 </div>
                 <div class="form-group">
                     <label>Your Calendly URL (for scheduling)</label>
                     <input type="url" name="calendly" placeholder="https://calendly.com/your-name" required>
                 </div>
                 <div class="form-group">
                     <label>Bio / Experience</label>
                     <textarea name="bio" rows="5" required></textarea>
                 </div>
                 <button type="submit">Submit Application</button>
             </form>
         <?php endif; ?>
        
        <p style="margin-top: 20px;"><a href="schedule.php">Back to Schedule</a></p>
    </div>
    <script src="js/menu.js" defer></script>
</body>
</html>
