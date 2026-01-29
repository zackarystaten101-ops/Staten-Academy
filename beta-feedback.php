<?php
/**
 * Beta Feedback Page
 * Allows users to submit feedback about the platform
 */

session_start();
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/app/Views/components/dashboard-functions.php';
require_once __DIR__ . '/app/Helpers/SecurityHelper.php';

// Set security headers
SecurityHelper::setSecurityHeaders();

$user_id = $_SESSION['user_id'] ?? null;
$user_role = $_SESSION['user_role'] ?? 'guest';
$user = $user_id ? getUserById($conn, $user_id) : null;

$success_msg = null;
$error_msg = null;

// Handle feedback submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_feedback'])) {
    $csrf_token = $_POST['csrf_token'] ?? '';
    
    if (!SecurityHelper::verifyCSRFToken($csrf_token)) {
        $error_msg = 'Invalid security token. Please refresh the page and try again.';
    } else {
        $feedback_type = SecurityHelper::sanitizeInput($_POST['feedback_type'] ?? 'general');
        $category = SecurityHelper::sanitizeInput($_POST['category'] ?? 'other');
        $title = SecurityHelper::sanitizeInput($_POST['title'] ?? '');
        $description = SecurityHelper::sanitizeInput($_POST['description'] ?? '');
        $priority = SecurityHelper::sanitizeInput($_POST['priority'] ?? 'medium');
        $page_url = SecurityHelper::sanitizeInput($_POST['page_url'] ?? $_SERVER['HTTP_REFERER'] ?? '');
        $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';
        $ip_address = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        
        if (empty($title) || empty($description)) {
            $error_msg = 'Title and description are required.';
        } else {
            $sql = "INSERT INTO beta_feedback 
                    (user_id, feedback_type, category, title, description, priority, page_url, user_agent, ip_address, status) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending')";
            
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("issssssss", $user_id, $feedback_type, $category, $title, $description, $priority, $page_url, $user_agent, $ip_address);
            
            if ($stmt->execute()) {
                $success_msg = 'Thank you for your feedback! We appreciate your input and will review it soon.';
                // Clear form
                $_POST = [];
            } else {
                $error_msg = 'Failed to submit feedback. Please try again.';
            }
            
            $stmt->close();
        }
    }
}

$csrf_token = SecurityHelper::generateCSRFToken();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Beta Feedback - Staten Academy</title>
    <link rel="stylesheet" href="<?php echo getAssetPath('styles.css'); ?>">
    <link rel="stylesheet" href="<?php echo getAssetPath('css/dashboard.css'); ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .feedback-container {
            max-width: 800px;
            margin: 40px auto;
            padding: 0 20px;
        }
        .feedback-form {
            background: white;
            padding: 30px;
            border-radius: 12px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
        }
        .form-group {
            margin-bottom: 20px;
        }
        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: bold;
            color: #333;
        }
        .form-group input,
        .form-group select,
        .form-group textarea {
            width: 100%;
            padding: 12px;
            border: 1px solid #ddd;
            border-radius: 6px;
            font-size: 1rem;
        }
        .form-group textarea {
            min-height: 150px;
            resize: vertical;
        }
        .priority-badges {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }
        .priority-badge {
            padding: 8px 16px;
            border-radius: 20px;
            cursor: pointer;
            border: 2px solid #ddd;
            background: white;
            transition: all 0.3s;
        }
        .priority-badge:hover {
            border-color: #0b6cf5;
        }
        .priority-badge.selected {
            background: #0b6cf5;
            color: white;
            border-color: #0b6cf5;
        }
        .priority-badge.low.selected { background: #17a2b8; border-color: #17a2b8; }
        .priority-badge.medium.selected { background: #ffc107; border-color: #ffc107; }
        .priority-badge.high.selected { background: #fd7e14; border-color: #fd7e14; }
        .priority-badge.critical.selected { background: #dc3545; border-color: #dc3545; }
    </style>
</head>
<body>
    <?php if ($user_id): ?>
        <?php include __DIR__ . '/app/Views/components/dashboard-header.php'; ?>
    <?php else: ?>
        <?php include __DIR__ . '/header-user.php'; ?>
    <?php endif; ?>

    <div class="feedback-container">
        <div style="text-align: center; margin-bottom: 30px;">
            <h1><i class="fas fa-comment-dots"></i> Beta Feedback</h1>
            <p style="color: #666;">Help us improve Staten Academy by sharing your feedback</p>
        </div>

        <?php if ($success_msg): ?>
            <div class="card" style="background: #d4edda; border-color: #28a745; margin-bottom: 20px;">
                <p style="color: #155724; margin: 0;"><i class="fas fa-check-circle"></i> <?php echo h($success_msg); ?></p>
            </div>
        <?php endif; ?>

        <?php if ($error_msg): ?>
            <div class="card" style="background: #f8d7da; border-color: #dc3545; margin-bottom: 20px;">
                <p style="color: #721c24; margin: 0;"><i class="fas fa-exclamation-circle"></i> <?php echo h($error_msg); ?></p>
            </div>
        <?php endif; ?>

        <div class="feedback-form">
            <form method="POST" action="">
                <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                
                <div class="form-group">
                    <label>Feedback Type *</label>
                    <select name="feedback_type" required>
                        <option value="general">General Feedback</option>
                        <option value="bug">Bug Report</option>
                        <option value="feature_request">Feature Request</option>
                        <option value="ui_issue">UI/UX Issue</option>
                        <option value="performance">Performance Issue</option>
                    </select>
                </div>

                <div class="form-group">
                    <label>Category</label>
                    <select name="category">
                        <option value="other">Other</option>
                        <option value="dashboard">Dashboard</option>
                        <option value="booking">Booking System</option>
                        <option value="classroom">Classroom</option>
                        <option value="payment">Payment</option>
                        <option value="mobile">Mobile Experience</option>
                    </select>
                </div>

                <div class="form-group">
                    <label>Title *</label>
                    <input type="text" name="title" placeholder="Brief summary of your feedback" required maxlength="255">
                </div>

                <div class="form-group">
                    <label>Description *</label>
                    <textarea name="description" placeholder="Please provide detailed information about your feedback..." required></textarea>
                </div>

                <div class="form-group">
                    <label>Priority</label>
                    <div class="priority-badges">
                        <div class="priority-badge low" data-priority="low" onclick="selectPriority('low')">
                            <i class="fas fa-info-circle"></i> Low
                        </div>
                        <div class="priority-badge medium selected" data-priority="medium" onclick="selectPriority('medium')">
                            <i class="fas fa-exclamation-circle"></i> Medium
                        </div>
                        <div class="priority-badge high" data-priority="high" onclick="selectPriority('high')">
                            <i class="fas fa-exclamation-triangle"></i> High
                        </div>
                        <div class="priority-badge critical" data-priority="critical" onclick="selectPriority('critical')">
                            <i class="fas fa-times-circle"></i> Critical
                        </div>
                    </div>
                    <input type="hidden" name="priority" id="priority_input" value="medium">
                </div>

                <input type="hidden" name="page_url" value="<?php echo h($_SERVER['REQUEST_URI'] ?? ''); ?>">

                <button type="submit" name="submit_feedback" class="btn-primary" style="width: 100%; padding: 14px; font-size: 1.1rem;">
                    <i class="fas fa-paper-plane"></i> Submit Feedback
                </button>
            </form>
        </div>

        <div class="card" style="margin-top: 30px; background: #f0f7ff; border-left: 4px solid #0b6cf5;">
            <h3 style="color: #004080; margin-top: 0;"><i class="fas fa-info-circle"></i> About Beta Feedback</h3>
            <p style="color: #666; margin-bottom: 10px;">
                Your feedback helps us improve the platform. We review all submissions and prioritize based on impact and feasibility.
            </p>
            <p style="color: #666; margin: 0;">
                <strong>What happens next?</strong> Our team will review your feedback and may reach out for more information if needed.
            </p>
        </div>
    </div>

    <script>
        function selectPriority(priority) {
            document.querySelectorAll('.priority-badge').forEach(badge => {
                badge.classList.remove('selected');
            });
            document.querySelector(`[data-priority="${priority}"]`).classList.add('selected');
            document.getElementById('priority_input').value = priority;
        }
    </script>
</body>
</html>
