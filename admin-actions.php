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

// Security check: Must be Admin
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
    ob_end_clean(); // Clear output buffer before die
    die("Access denied.");
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
     $action = $_POST['action'];

     if ($action === 'make_teacher') {
         $user_id = intval($_POST['user_id']);
         $stmt = $conn->prepare("UPDATE users SET role = 'teacher', application_status = 'approved' WHERE id = ?");
         $stmt->bind_param("i", $user_id);
     } elseif ($action === 'make_student') {
         $user_id = intval($_POST['user_id']);
         $stmt = $conn->prepare("UPDATE users SET role = 'student' WHERE id = ?");
         $stmt->bind_param("i", $user_id);
     } elseif ($action === 'approve_teacher') {
         $user_id = intval($_POST['user_id']);
         $stmt = $conn->prepare("UPDATE users SET role = 'teacher', application_status = 'approved' WHERE id = ?");
         $stmt->bind_param("i", $user_id);
     } elseif ($action === 'reject_teacher') {
         $user_id = intval($_POST['user_id']);
         $stmt = $conn->prepare("UPDATE users SET application_status = 'rejected' WHERE id = ?");
         $stmt->bind_param("i", $user_id);
     } elseif ($action === 'approve_profile') {
         $update_id = intval($_POST['update_id']);
         error_log("Admin approving profile update - Update ID: $update_id, Admin ID: " . ($_SESSION['user_id'] ?? 'unknown'));
         
         // Get pending update details
         $update_stmt = $conn->prepare("SELECT user_id, name, bio, profile_pic, about_text, video_url FROM pending_updates WHERE id = ?");
         if (!$update_stmt) {
             error_log("Error preparing approve_profile statement: " . $conn->error);
             ob_end_clean();
             header("Location: admin-dashboard.php?msg=error");
             exit();
         }
         
         $update_stmt->bind_param("i", $update_id);
         $update_stmt->execute();
         $update_result = $update_stmt->get_result();
         
         if ($update_result->num_rows > 0) {
             $update = $update_result->fetch_assoc();
             $user_id = $update['user_id'];
             $name = $update['name'];
             $bio = $update['bio'];
             $profile_pic = $update['profile_pic'];
             $about_text = $update['about_text'];
             $video_url = $update['video_url'];
             
             error_log("Profile approval data - User ID: $user_id, Name: " . ($name ?: 'no change') . ", Bio length: " . strlen($bio) . ", About length: " . strlen($about_text) . ", Video URL: " . ($video_url ?: 'none') . ", Profile pic: " . ($profile_pic ?: 'none'));
             
             // Apply approved changes
             $stmt = $conn->prepare("UPDATE users SET bio = ?, profile_pic = ?, about_text = ?, video_url = ? WHERE id = ?");
             if ($stmt) {
                 $stmt->bind_param("ssssi", $bio, $profile_pic, $about_text, $video_url, $user_id);
                 if ($stmt->execute()) {
                     error_log("Profile update approved successfully - User ID: $user_id");
                     
                     // Delete from pending
                     $del_stmt = $conn->prepare("DELETE FROM pending_updates WHERE id = ?");
                     if ($del_stmt) {
                         $del_stmt->bind_param("i", $update_id);
                         $del_stmt->execute();
                         $del_stmt->close();
                     }
                     
                     // Notify teacher
                     if (function_exists('createNotification')) {
                         require_once __DIR__ . '/app/Views/components/dashboard-functions.php';
                         createNotification($conn, $user_id, 'profile_approved', 'Profile Update Approved', 
                             "Your profile update has been approved by an administrator.", 
                             'teacher-dashboard.php#profile');
                     }
                 } else {
                     error_log("Error executing profile approval update - User ID: $user_id, Error: " . $stmt->error);
                 }
                 $stmt->close();
             } else {
                 error_log("Error preparing profile approval update statement - User ID: $user_id, Error: " . $conn->error);
             }
         } else {
             error_log("Profile update not found - Update ID: $update_id");
         }
        $update_stmt->close();
        ob_end_clean(); // Clear output buffer before redirect
        header("Location: admin-dashboard.php?msg=success");
        exit();
     } elseif ($action === 'reject_profile') {
         $update_id = intval($_POST['update_id']);
         error_log("Admin rejecting profile update - Update ID: $update_id, Admin ID: " . ($_SESSION['user_id'] ?? 'unknown'));
         
         // Get pending update to delete uploaded image if needed
         $update_stmt = $conn->prepare("SELECT user_id, profile_pic FROM pending_updates WHERE id = ?");
         if (!$update_stmt) {
             error_log("Error preparing reject_profile statement: " . $conn->error);
             ob_end_clean();
             header("Location: admin-dashboard.php?msg=error");
             exit();
         }
         
         $update_stmt->bind_param("i", $update_id);
         $update_stmt->execute();
         $update_result = $update_stmt->get_result();
         
         if ($update_result->num_rows > 0) {
             $update = $update_result->fetch_assoc();
             $user_id = $update['user_id'] ?? 0;
             
             // Delete uploaded image if it exists
             if (!empty($update['profile_pic'])) {
                 $image_path = __DIR__ . $update['profile_pic'];
                 if (file_exists($image_path)) {
                     if (unlink($image_path)) {
                         error_log("Deleted rejected profile image: " . $image_path);
                     } else {
                         error_log("Failed to delete rejected profile image: " . $image_path);
                     }
                 }
             }
             
             // Notify teacher
             if ($user_id && function_exists('createNotification')) {
                 require_once __DIR__ . '/app/Views/components/dashboard-functions.php';
                 createNotification($conn, $user_id, 'profile_rejected', 'Profile Update Rejected', 
                     "Your profile update has been rejected by an administrator. Please review and resubmit.", 
                     'teacher-dashboard.php#profile');
             }
         }
         $update_stmt->close();
         
         // Delete from pending
         $stmt = $conn->prepare("DELETE FROM pending_updates WHERE id = ?");
         if ($stmt) {
             $stmt->bind_param("i", $update_id);
             if ($stmt->execute()) {
                 error_log("Profile update rejected and deleted - Update ID: $update_id");
             } else {
                 error_log("Error deleting rejected profile update - Update ID: $update_id, Error: " . $stmt->error);
             }
             $stmt->close();
         } else {
             error_log("Error preparing delete statement for rejected profile - Update ID: $update_id, Error: " . $conn->error);
         }
     } elseif ($action === 'create_slot_request') {
         $admin_id = $_SESSION['user_id'];
         $teacher_id = intval($_POST['teacher_id']);
         $request_type = $_POST['request_type'];
         $message = trim($_POST['message'] ?? '');
         
         if ($request_type === 'time_slot') {
             $requested_date = $_POST['requested_date'];
             $requested_time = $_POST['requested_time'];
             $duration_minutes = intval($_POST['duration_minutes']);
             $stmt = $conn->prepare("INSERT INTO admin_slot_requests (admin_id, teacher_id, request_type, requested_date, requested_time, duration_minutes, message) VALUES (?, ?, ?, ?, ?, ?, ?)");
             $stmt->bind_param("iisssis", $admin_id, $teacher_id, $request_type, $requested_date, $requested_time, $duration_minutes, $message);
         } else {
             $group_class_track = $_POST['group_class_track'];
             $group_class_date = $_POST['group_class_date'];
             $group_class_time = $_POST['group_class_time'];
             $stmt = $conn->prepare("INSERT INTO admin_slot_requests (admin_id, teacher_id, request_type, group_class_track, group_class_date, group_class_time, message) VALUES (?, ?, ?, ?, ?, ?, ?)");
             $stmt->bind_param("iisssss", $admin_id, $teacher_id, $request_type, $group_class_track, $group_class_date, $group_class_time, $message);
         }
         
         $stmt->execute();
         $stmt->close();
         
        // Notify teacher
        require_once __DIR__ . '/app/Views/components/dashboard-functions.php';
        if (function_exists('createNotification')) {
            $request_desc = $request_type === 'time_slot' 
                ? "time slot on " . date('M d, Y', strtotime($requested_date)) . " at " . date('g:i A', strtotime($requested_time))
                : "group class for " . ucfirst($group_class_track) . " track on " . date('M d, Y', strtotime($group_class_date)) . " at " . date('g:i A', strtotime($group_class_time));
            createNotification($conn, $teacher_id, 'slot_request', 'New Slot Request', 
                "Admin has requested you to open a " . $request_desc, 
                'teacher-dashboard.php');
        }
         
        ob_end_clean();
        header("Location: admin-dashboard.php#slot-requests");
        exit();
     } elseif ($action === 'mark_support_read') {
         $support_id = intval($_POST['support_id']);
         $stmt = $conn->prepare("UPDATE support_messages SET status = 'read' WHERE id = ?");
         $stmt->bind_param("i", $support_id);
     } else {
         die("Invalid action");
     }

     if (isset($stmt)) {
         if ($stmt->execute()) {
             ob_end_clean(); // Clear output buffer before redirect
             header("Location: admin-dashboard.php?msg=success");
             exit();
         } else {
             ob_end_clean(); // Clear output buffer before echo
             echo "Error updating record: " . $conn->error;
         }
         $stmt->close();
     }
 }
 ob_end_clean(); // Clear output buffer before closing
 $conn->close();
?>
