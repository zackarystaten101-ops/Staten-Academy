<?php
session_start();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Thank You - Staten Academy</title>
  <?php
  // Ensure getAssetPath is available
  if (!function_exists('getAssetPath')) {
      if (file_exists(__DIR__ . '/app/Views/components/dashboard-functions.php')) {
          require_once __DIR__ . '/app/Views/components/dashboard-functions.php';
      } else {
          function getAssetPath($asset) {
              $asset = ltrim($asset, '/');
              if (strpos($asset, 'assets/') === 0) {
                  $assetPath = $asset;
              } else {
                  $assetPath = 'assets/' . $asset;
              }
              return '/' . $assetPath;
          }
      }
  }
  ?>
  <link rel="stylesheet" href="<?php echo getAssetPath('styles.css'); ?>">
  <style>
    .thank-you-box {
        max-width: 600px;
        margin: 80px auto;
        text-align: center;
        padding: 40px;
        background: white;
        border-radius: 10px;
        box-shadow: 0 4px 12px rgba(0,0,0,0.1);
    }
    .thank-you-box h1 {
        color: #004080;
        margin-bottom: 20px;
    }
    .thank-you-box p {
        color: #666;
        margin-bottom: 15px;
    }
    .btn {
        display: inline-block;
        background: #004080;
        color: white;
        padding: 12px 24px;
        text-decoration: none;
        border-radius: 5px;
        margin-top: 15px;
        border: none;
        cursor: pointer;
        font-size: 1rem;
    }
    .btn:hover {
        background: #0056b3;
    }
  </style>
</head>
<body>
  <div class="thank-you-box">
    <h1>Thank You for Your Request!</h1>
    <p>Hi there! Your lesson request has been received. We will get back to you soon to confirm the details.</p>
    <?php if (isset($_SESSION['user_id'])): ?>
        <a href="student-dashboard.php" class="btn">Go to Dashboard</a>
    <?php else: ?>
        <a href="index.php" class="btn">Back to Homepage</a>
    <?php endif; ?>
  </div>
</body>
</html>

