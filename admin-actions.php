<?php
session_start();
require_once 'db.php';

// Security check: Must be Admin
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
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
         
         // Get pending update details
         $update_stmt = $conn->prepare("SELECT user_id, name, bio, profile_pic, about_text, video_url FROM pending_updates WHERE id = ?");
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
             
             // Apply approved changes
             $stmt = $conn->prepare("UPDATE users SET bio = ?, profile_pic = ?, about_text = ?, video_url = ? WHERE id = ?");
             $stmt->bind_param("ssssi", $bio, $profile_pic, $about_text, $video_url, $user_id);
             $stmt->execute();
             $stmt->close();
             
             // Delete from pending
             $del_stmt = $conn->prepare("DELETE FROM pending_updates WHERE id = ?");
             $del_stmt->bind_param("i", $update_id);
             $del_stmt->execute();
             $del_stmt->close();
         }
         $update_stmt->close();
         header("Location: admin-dashboard.php?msg=success");
         exit();
     } elseif ($action === 'reject_profile') {
         $update_id = intval($_POST['update_id']);
         
         // Get pending update to delete uploaded image if needed
         $update_stmt = $conn->prepare("SELECT profile_pic FROM pending_updates WHERE id = ?");
         $update_stmt->bind_param("i", $update_id);
         $update_stmt->execute();
         $update_result = $update_stmt->get_result();
         
         if ($update_result->num_rows > 0) {
             $update = $update_result->fetch_assoc();
             // Delete uploaded image
             if (file_exists($update['profile_pic'])) {
                 unlink($update['profile_pic']);
             }
         }
         $update_stmt->close();
         
         // Delete from pending
         $stmt = $conn->prepare("DELETE FROM pending_updates WHERE id = ?");
         $stmt->bind_param("i", $update_id);
     } elseif ($action === 'mark_support_read') {
         $support_id = intval($_POST['support_id']);
         $stmt = $conn->prepare("UPDATE support_messages SET status = 'read' WHERE id = ?");
         $stmt->bind_param("i", $support_id);
     } else {
         die("Invalid action");
     }

     if (isset($stmt)) {
         if ($stmt->execute()) {
             header("Location: admin-dashboard.php?msg=success");
         } else {
             echo "Error updating record: " . $conn->error;
         }
         $stmt->close();
     }
 }
 $conn->close();
?>
