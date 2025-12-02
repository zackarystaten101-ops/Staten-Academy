<?php
session_start();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payment Cancelled - Staten Academy</title>
    <link rel="stylesheet" href="styles.css">
    <style>
        .message-box {
            max-width: 600px;
            margin: 100px auto;
            text-align: center;
            padding: 40px;
            background: white;
            border-radius: 10px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
        }
        .cancel-icon {
            color: #dc3545;
            font-size: 4rem;
            margin-bottom: 20px;
        }
        .btn {
            display: inline-block;
            background: #004080;
            color: white;
            padding: 10px 20px;
            text-decoration: none;
            border-radius: 5px;
            margin-top: 20px;
        }
        .btn:hover {
            background: #0056b3;
        }
    </style>
</head>
<body>
    <div class="message-box">
        <div class="cancel-icon">✕</div>
        <h1>Payment Cancelled</h1>
        <p>You have cancelled the payment process. No charges were made.</p>
        <?php if (isset($_SESSION['user_id'])): ?>
            <a href="payment.php" class="btn">Try Again</a>
            <a href="index.php" class="btn" style="background: #6c757d; margin-left: 10px;">Return to Home</a>
        <?php else: ?>
            <a href="index.php" class="btn">Return to Home</a>
        <?php endif; ?>
    </div>
</body>
</html>

